<?php

declare(strict_types=1);

namespace TCC\Tests;

use PHPUnit\Framework\TestCase;

/**
 * Testes de integração dos endpoints api/account/*.
 *
 * Cada endpoint roda em um subprocesso (chama exit()/header()), dentro
 * de uma transação que é revertida no final — o banco sai do jeito que
 * entrou, mesmo para ações destrutivas como cancelar pedido ou inativar
 * cartão.
 */
class ApiAccountTest extends TestCase
{
    private const FIXTURE = __DIR__ . '/fixtures/call_api.php';
    private const API_DIR  = __DIR__ . '/../api/account/';

    /**
     * Executa um endpoint e devolve [status, payload].
     */
    private function call(string $endpoint, string $method, array $body = [], int $userId = 16, string $setupSql = '', string $csrf = ''): array
    {
        $command = sprintf(
            '%s %s %s %s %s %s %s %s 2>/dev/null',
            escapeshellarg(PHP_BINARY),
            escapeshellarg(self::FIXTURE),
            escapeshellarg(self::API_DIR . $endpoint),
            escapeshellarg($method),
            escapeshellarg(json_encode($body)),
            (string) $userId,
            escapeshellarg($setupSql),
            escapeshellarg($csrf)
        );

        $lines = [];
        exec($command, $lines);
        $out = implode("\n", $lines);

        // O fixture emite os marcadores ___STATUS___/___BODY___ mas a
        // ordem de flush depende do PHP; parseia pela posição, não pela
        // sequência.
        $status = 0;
        $json   = '';
        $posB = strpos($out, '___BODY___');
        $posS = strpos($out, '___STATUS___');

        if ($posS !== false) {
            preg_match('/___STATUS___(\d+)/', substr($out, $posS), $m);
            $status = (int) ($m[1] ?? 0);
        }

        if ($posB !== false) {
            $start = $posB + strlen('___BODY___');
            $end   = $posS !== false && $posS > $posB ? $posS : strlen($out);
            $json  = trim(substr($out, $start, $end - $start));
        }

        $payload = json_decode($json, true);

        if (!is_array($payload)) {
            $this->fail("resposta não é JSON: {$out}");
        }

        return [$status, $payload];
    }

    private function assertContract(array $payload): void
    {
        $this->assertArrayHasKey('ok', $payload);
        $this->assertArrayHasKey('message', $payload);
        $this->assertArrayHasKey('errors', $payload);
        $this->assertArrayHasKey('data', $payload);
        $this->assertIsBool($payload['ok']);
    }

    // =================================================================
    //  Proteção comum
    // =================================================================

    public function testAllEndpointsRequireLogin(): void
    {
        foreach (['profile.php', 'notifications.php', 'address.php', 'cards.php', 'cep.php'] as $file) {
            [$status, $json] = $this->call($file, 'GET', [], 0);
            $this->assertSame(401, $status, "{$file} deveria exigir login");
            $this->assertSame(false, $json['ok']);
        }

        // password.php e orders.php só aceitam POST; o 401 de sessão
        // perdida cai aqui no fluxo real (POST + CSRF válido).
        foreach (['password.php', 'orders.php'] as $file) {
            [$status, $json] = $this->call($file, 'POST', [], 0);
            $this->assertSame(401, $status, "{$file} deveria exigir login");
            $this->assertSame(false, $json['ok']);
        }
    }

    public function testWriteRejectsTamperedCsrfToken(): void
    {
        [$status, $json] = $this->call('notifications.php', 'POST', ['notify_email' => 1], 16, '', 'bad');

        $this->assertSame(403, $status);
        $this->assertSame(false, $json['ok']);
    }

    public function testWriteWithValidCsrfTokenSucceeds(): void
    {
        [$status, $json] = $this->call('notifications.php', 'POST', ['notify_email' => 1, 'notify_whatsapp' => 1]);

        $this->assertSame(200, $status);
        $this->assertSame(true, $json['ok']);
    }

    public function testUnknownActionIsRejected(): void
    {
        [$status, $json] = $this->call('address.php', 'POST', ['action' => 'explode']);

        $this->assertSame(400, $status);
        $this->assertArrayHasKey('action', $json['errors'] ?? []);
    }

    // =================================================================
    //  Profile
    // =================================================================

    public function testProfileGetReturnsSanitizedUser(): void
    {
        [$status, $json] = $this->call('profile.php', 'GET');
        $this->assertSame(200, $status);
        $this->assertContract($json);
        $this->assertTrue($json['ok']);

        $d = $json['data'];
        $this->assertArrayHasKey('name', $d);
        $this->assertArrayHasKey('email', $d);
        $this->assertArrayHasKey('avatar_path', $d);
        $this->assertArrayNotHasKey('password', $d);
    }

    public function testProfilePersonalUpdatePersistsChanges(): void
    {
        [$status, $json] = $this->call('profile.php', 'POST', [
            'action'      => 'personal',
            'name'        => 'Kauã Caetano da Silva',
            'username'    => 'kaua.caetano',
            'email'       => 'Kauacaetano@gmail.com',
            'cpf'         => '52998224725',
            'phone'       => '12978149392',
            'postal_code' => '12053831',
            'street'      => 'Praça Pádua Sales',
            'number'      => '128',
            'complement'  => '',
            'neighborhood'=> 'Centro',
            'city'        => 'Taubaté',
            'state'       => 'SP',
        ]);

        $this->assertSame(200, $status);
        $this->assertTrue($json['ok']);
        $this->assertSame('Kauã Caetano da Silva', $json['data']['name']);
        $this->assertSame('Praça Pádua Sales', $json['data']['street']);
        $this->assertSame('52998224725', $json['data']['cpf']);
        $this->assertSame('kaua.caetano', $json['data']['username']);
    }

    public function testProfilePersonalRejectsInvalidData(): void
    {
        [$status, $json] = $this->call('profile.php', 'POST', [
            'action'   => 'personal',
            'name'     => '',
            'username' => 'Kaua.Caetano',
            'email'    => 'não-e-email',
            'cpf'      => '123',
            'state'    => 'XYZ',
        ]);

        $this->assertSame(400, $status);
        $this->assertFalse($json['ok']);
        $this->assertIsArray($json['errors']);
        $this->assertArrayHasKey('name', $json['errors']);
        $this->assertArrayHasKey('email', $json['errors']);
        $this->assertArrayHasKey('cpf', $json['errors']);
    }

    public function testProfileUsernameUnchangedLegacyIsAccepted(): void
    {
        $setup = tempnam(sys_get_temp_dir(), 'tcc_legacy_');
        file_put_contents($setup,
            "INSERT INTO e5_users (id, name, username, email, password, role, postal_code, street, number)
             VALUES (4100, 'Cliente Legado', 'Cliente.Legado', 'legado@etec.com', '', 'customer', '', '', 0);"
        );

        try {
            [$status, $json] = $this->call('profile.php', 'POST', [
                'action'      => 'personal',
                'name'        => 'Cliente Legado',
                'username'    => 'Cliente.Legado',
                'email'       => 'legado@etec.com',
                'cpf'         => '52998224725',
                'phone'       => '12978149392',
                'postal_code' => '',
                'street'      => 'Rua Legado',
                'number'      => '12',
                'complement'  => '',
                'neighborhood'=> '',
                'city'        => '',
                'state'       => '',
            ], 4100, $setup);

            $this->assertSame(200, $status);
            $this->assertTrue($json['ok']);
            $this->assertSame('Cliente.Legado', $json['data']['username']);
        } finally {
            @unlink($setup);
        }
    }

    public function testProfileUsernameChangedOverridesLegacyValue(): void
    {
        $setup = tempnam(sys_get_temp_dir(), 'tcc_legacy_');
        file_put_contents($setup,
            "INSERT INTO e5_users (id, name, username, email, password, role, postal_code, street, number)
             VALUES (4100, 'Cliente Legado', 'Cliente.Legado', 'legado@etec.com', '', 'customer', '', '', 0);"
        );

        try {
            [$status, $json] = $this->call('profile.php', 'POST', [
                'action'      => 'personal',
                'name'        => 'Cliente Legado',
                'username'    => 'novo.legado',
                'email'       => 'legado@etec.com',
                'cpf'         => '52998224725',
                'phone'       => '12978149392',
                'postal_code' => '',
                'street'      => 'Rua Legado',
                'number'      => '12',
                'complement'  => '',
                'neighborhood'=> '',
                'city'        => '',
                'state'       => '',
            ], 4100, $setup);

            $this->assertSame(200, $status);
            $this->assertTrue($json['ok']);
            $this->assertSame('novo.legado', $json['data']['username']);
        } finally {
            @unlink($setup);
        }
    }

    public function testProfileUsernameCollisionReportsTheField(): void
    {
        $setup = tempnam(sys_get_temp_dir(), 'tcc_collid_');
        file_put_contents($setup,
            "INSERT INTO e5_users (id, name, username, email, password, role, postal_code, street, number) VALUES
             (4100, 'Cliente Um',   'cliente.um',   'um@etec.com',   '', 'customer', '', '', 0),
             (4101, 'Cliente Dois', 'cliente.dois', 'dois@etec.com', '', 'customer', '', '', 0);"
        );

        try {
            // Username trocado para um que já é de outra conta -> erra o campo username.
            [$status, $json] = $this->call('profile.php', 'POST', [
                'action'      => 'personal',
                'name'        => 'Cliente Um',
                'username'    => 'cliente.dois',
                'email'       => 'um@etec.com',
                'cpf'         => '52998224725',
                'phone'       => '12978149392',
                'postal_code' => '',
                'street'      => 'Rua Legado',
                'number'      => '12',
                'complement'  => '',
                'neighborhood'=> '',
                'city'        => '',
                'state'       => '',
            ], 4100, $setup);

            $this->assertSame(409, $status);
            $this->assertFalse($json['ok']);
            $this->assertArrayHasKey('username', $json['errors'] ?? []);
            $this->assertArrayNotHasKey('email', $json['errors'] ?? []);

            // E-mail trocado para o de outra conta -> erra o campo email.
            [$status, $json] = $this->call('profile.php', 'POST', [
                'action'      => 'personal',
                'name'        => 'Cliente Um',
                'username'    => 'cliente.um',
                'email'       => 'dois@etec.com',
                'cpf'         => '52998224725',
                'phone'       => '12978149392',
                'postal_code' => '',
                'street'      => 'Rua Legado',
                'number'      => '12',
                'complement'  => '',
                'neighborhood'=> '',
                'city'        => '',
                'state'       => '',
            ], 4100, $setup);

            $this->assertSame(409, $status);
            $this->assertFalse($json['ok']);
            $this->assertArrayHasKey('email', $json['errors'] ?? []);
            $this->assertArrayNotHasKey('username', $json['errors'] ?? []);
        } finally {
            @unlink($setup);
        }
    }

    public function testProfileAvatarRejectsMissingFile(): void
    {
        [$status, $json] = $this->call('profile.php', 'POST', ['action' => 'avatar']);
        $this->assertSame(400, $status);
        $this->assertArrayHasKey('avatar', $json['errors'] ?? []);
    }

    public function testProfileAddressUpdatePersistsChanges(): void
    {
        [$status, $json] = $this->call('profile.php', 'POST', [
            'action'      => 'address',
            'postal_code' => '12053831',
            'street'      => 'Rua Nova',
            'number'      => '456',
            'complement'  => 'Apto 101',
            'neighborhood'=> 'Vila Nova',
            'city'        => 'São Paulo',
            'state'       => 'SP',
        ]);

        $this->assertSame(200, $status);
        $this->assertTrue($json['ok']);
        $this->assertSame('Rua Nova', $json['data']['street']);
        $this->assertSame(456, (int) $json['data']['number']);
        $this->assertSame('12053-831', $json['data']['postal_code']);
        $this->assertSame('SP', $json['data']['state']);
    }

    public function testProfileAddressRejectsInvalidData(): void
    {
        [$status, $json] = $this->call('profile.php', 'POST', [
            'action'      => 'address',
            'postal_code' => '123',
            'street'      => '',
            'number'      => '',
            'city'        => '',
            'state'       => 'ZZ',
        ]);

        $this->assertSame(400, $status);
        $this->assertFalse($json['ok']);
        $this->assertIsArray($json['errors']);
        $this->assertArrayHasKey('postal_code', $json['errors']);
        $this->assertArrayHasKey('street', $json['errors']);
        $this->assertArrayHasKey('number', $json['errors']);
        $this->assertArrayHasKey('city', $json['errors']);
        $this->assertArrayHasKey('state', $json['errors']);
    }

    public function testProfileAddressRejectsNonNumericNumber(): void
    {
        [$status, $json] = $this->call('profile.php', 'POST', [
            'action'      => 'address',
            'postal_code' => '12053831',
            'street'      => 'Rua Teste',
            'number'      => 'abc',
            'city'        => 'São Paulo',
            'state'       => 'SP',
        ]);

        $this->assertSame(400, $status);
        $this->assertFalse($json['ok']);
        $this->assertArrayHasKey('number', $json['errors']);
    }

    // =================================================================
    //  CEP proxy
    // =================================================================

    public function testCepRejectsInvalidFormat(): void
    {
        [$status, $json] = $this->call('cep.php', 'GET', ['cep' => 'abcdef']);
        $this->assertSame(400, $status);
        $this->assertFalse($json['ok']);
        $this->assertArrayHasKey('cep', $json['errors'] ?? []);
    }

    // =================================================================
    //  Notificações
    // =================================================================

    public function testNotificationsReadAndWrite(): void
    {
        [$status, $json] = $this->call('notifications.php', 'GET');
        $this->assertSame(200, $status);
        $this->assertArrayHasKey('notify_email', $json['data']);

        [$status, $json] = $this->call('notifications.php', 'POST', [
            'notify_email'    => 0,
            'notify_whatsapp' => 1,
        ]);
        $this->assertSame(200, $status);
        $this->assertTrue($json['ok']);
        $this->assertSame(false, $json['data']['notify_email']);
        $this->assertSame(true, $json['data']['notify_whatsapp']);
    }

    // =================================================================
    //  Endereços
    // =================================================================

    public function testAddressCreateBecomesDefaultWhenNoDefaultExists(): void
    {
        [$status, $json] = $this->call('address.php', 'POST', [
            'action'       => 'create',
            'label'        => 'Casa',
            'postal_code'  => '12020080',
            'street'       => 'Rua XV de Novembro',
            'number'       => '99',
            'complement'   => 'Apto 12',
            'neighborhood' => 'Centro',
            'city'         => 'Taubaté',
            'state'        => 'SP',
        ]);

        $this->assertSame(201, $status);
        $this->assertTrue($json['ok']);

        $addr = $json['data']['addresses'];
        $this->assertCount(1, $addr);
        $this->assertSame(1, (int) $addr[0]['is_default']);
        $this->assertSame((int) $addr[0]['id'], $json['data']['default_id']);
    }

    public function testAddressRejectsInvalidCepAndState(): void
    {
        [$status, $json] = $this->call('address.php', 'POST', [
            'action'      => 'create',
            'label'       => 'Casa',
            'postal_code' => '123',
            'street'      => 'Rua Tal',
            'number'      => '1',
            'city'        => 'Taubaté',
            'state'       => 'ZZ',
        ]);

        $this->assertSame(400, $status);
        $this->assertArrayHasKey('postal_code', $json['errors'] ?? []);
        $this->assertArrayHasKey('state', $json['errors'] ?? []);
    }

    public function testAddressUpdateAltersOwnAddressOnly(): void
    {
        $setup = tempnam(sys_get_temp_dir(), 'tcc_addr_');
        file_put_contents($setup,
            "INSERT INTO e5_addresses (id, user_id, label, postal_code, street, number, complement, neighborhood, city, state, is_default)
             VALUES (9100, 16, 'Trabalho', '12020080', 'Av. Dom Pedro', '20', NULL, 'Centro', 'Taubaté', 'SP', 1);"
        );

        try {
            [$status, $json] = $this->call('address.php', 'POST', [
                'action' => 'update', 'id' => 9100, 'label' => 'Escritório',
                'postal_code' => '12020080', 'street' => 'Av. Dom Pedro', 'number' => '21',
                'complement' => '', 'neighborhood' => 'Centro', 'city' => 'Taubaté', 'state' => 'SP',
            ], 16, $setup);

            $this->assertSame(200, $status);
            $this->assertTrue($json['ok']);
            $this->assertCount(1, $json['data']['addresses']);
            $this->assertSame('Escritório', $json['data']['addresses'][0]['label']);
            $this->assertSame('21', $json['data']['addresses'][0]['number']);
        } finally {
            @unlink($setup);
        }
    }

    public function testAddressDeleteRemovesOwnAddress(): void
    {
        $setup = tempnam(sys_get_temp_dir(), 'tcc_addr_');
        file_put_contents($setup,
            "INSERT INTO e5_addresses (id, user_id, label, postal_code, street, number, city, state, is_default)
             VALUES (9101, 16, 'Para apagar', '12020080', 'Rua A', '1', 'Taubaté', 'SP', 1);"
        );

        try {
            [$status, $json] = $this->call('address.php', 'POST', ['action' => 'delete', 'id' => 9101], 16, $setup);

            $this->assertSame(200, $status);
            $this->assertTrue($json['ok']);
            $this->assertSame([], $json['data']['addresses']);
            $this->assertNull($json['data']['default_id']);
        } finally {
            @unlink($setup);
        }
    }

    public function testAddressDefaultMovesToNewestWhenDefaultDeleted(): void
    {
        $setup = tempnam(sys_get_temp_dir(), 'tcc_addr_');
        file_put_contents($setup,
            "INSERT INTO e5_addresses (id, user_id, label, postal_code, street, number, city, state, is_default) VALUES
             (9102, 16, 'Um', '12020080', 'Rua B', '1', 'Taubaté', 'SP', 1),
             (9103, 16, 'Dois', '12020080', 'Rua C', '2', 'Taubaté', 'SP', 0);"
        );

        try {
            [$status, $json] = $this->call('address.php', 'POST', ['action' => 'delete', 'id' => 9102], 16, $setup);

            $this->assertSame(200, $status);
            $this->assertCount(1, $json['data']['addresses']);
            $this->assertSame('Dois', $json['data']['addresses'][0]['label']);
            $this->assertSame(9103, $json['data']['default_id']);
        } finally {
            @unlink($setup);
        }
    }

    // =================================================================
    //  Cartões
    // =================================================================

    public function testCardsListAndDelete(): void
    {
        $setup = tempnam(sys_get_temp_dir(), 'tcc_cards_');
        file_put_contents($setup, "INSERT INTO e5_saved_cards (id, user_id, card_brand, holder_name, last_four, exp_month, exp_year) VALUES (9104, 16, 'visa', 'KAUA CAETANO', '4242', 12, 2028);");

        try {
            [$status, $json] = $this->call('cards.php', 'GET', [], 16, $setup);
            $this->assertSame(200, $status);
            $this->assertCount(1, $json['data']['cards']);
            $this->assertSame('4242', $json['data']['cards'][0]['last_four']);
            $this->assertArrayNotHasKey('number', $json['data']['cards'][0]);

            [$status, $json] = $this->call('cards.php', 'POST', ['action' => 'delete', 'id' => 9104], 16, $setup);
            $this->assertSame(200, $status);
            $this->assertTrue($json['ok']);
        } finally {
            @unlink($setup);
        }
    }

    // =================================================================
    //  Pedidos
    // =================================================================

    public function testOrderCancelUsesStateMachine(): void
    {
        $setup = tempnam(sys_get_temp_dir(), 'tcc_order_');
        file_put_contents($setup,
            "INSERT INTO e5_orders (id, user_id, status, payment_status, payment_method, total, shipping_method, shipping_cost, shipping_street, shipping_number, shipping_city, shipping_state, shipping_postal_code, created_at)
             VALUES (8100, 16, 'pending', 'pending', 'pix', 100.00, 'PAC', 0.00, 'Rua Teste', '10', 'Taubaté', 'SP', '12020080', NOW());"
        );

        try {
            [$status, $json] = $this->call('orders.php', 'POST', ['action' => 'cancel', 'order_id' => 8100], 16, $setup);
            $this->assertSame(200, $status);
            $this->assertTrue($json['ok']);
            $this->assertSame('canceled', $json['data']['order']['status']);
            $this->assertSame('canceled', $json['data']['order']['payment_status']);
        } finally {
            @unlink($setup);
        }
    }

    public function testOrderCancelRejectsAlreadyCanceledOrder(): void
    {
        $setup = tempnam(sys_get_temp_dir(), 'tcc_order_');
        file_put_contents($setup,
            "INSERT INTO e5_orders (id, user_id, status, payment_status, payment_method, total, shipping_method, shipping_cost, shipping_street, shipping_number, shipping_city, shipping_state, shipping_postal_code, created_at)
             VALUES (8103, 16, 'canceled', 'canceled', 'pix', 100.00, 'PAC', 0.00, 'Rua Teste', '10', 'Taubaté', 'SP', '12020080', NOW());"
        );

        try {
            [$status, $json] = $this->call('orders.php', 'POST', ['action' => 'cancel', 'order_id' => 8103], 16, $setup);
            $this->assertSame(409, $status);
            $this->assertFalse($json['ok']);
        } finally {
            @unlink($setup);
        }
    }

    public function testOrderCancelRejectsOtherUsersOrder(): void
    {
        $setup = tempnam(sys_get_temp_dir(), 'tcc_other_');
        file_put_contents($setup,
            "INSERT INTO e5_orders (id, user_id, status, payment_status, payment_method, total, shipping_method, shipping_cost, shipping_street, shipping_number, shipping_city, shipping_state, shipping_postal_code, created_at)
             VALUES (8101, 2, 'pending', 'pending', 'pix', 50.00, 'PAC', 0.00, 'Rua Alheia', '1', 'Taubaté', 'SP', '12020080', NOW());"
        );

        try {
            [$status, $json] = $this->call('orders.php', 'POST', ['action' => 'cancel', 'order_id' => 8101], 16, $setup);

            // Idêntico para "não existe" e "de outro usuário": não vaza
            // que o pedido existe.
            $this->assertSame(404, $status);
            $this->assertFalse($json['ok']);
        } finally {
            @unlink($setup);
        }
    }

    public function testOrderCancelRejectsShippedOrder(): void
    {
        $setup = tempnam(sys_get_temp_dir(), 'tcc_shipped_');
        file_put_contents($setup,
            "INSERT INTO e5_orders (id, user_id, status, payment_status, payment_method, total, shipping_method, shipping_cost, shipping_street, shipping_number, shipping_city, shipping_state, shipping_postal_code, created_at)
             VALUES (8102, 16, 'shipped', 'paid', 'pix', 90.00, 'PAC', 0.00, 'Rua Enviada', '1', 'Taubaté', 'SP', '12020080', NOW());"
        );

        try {
            [$status, $json] = $this->call('orders.php', 'POST', ['action' => 'cancel', 'order_id' => 8102], 16, $setup);

            $this->assertSame(409, $status);
            $this->assertFalse($json['ok']);
        } finally {
            @unlink($setup);
        }
    }
}