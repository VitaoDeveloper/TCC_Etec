<?php

declare(strict_types=1);

/**
 * Migração idempotente de usernames legados.
 *
 * Usernames registrados antes das regras atuais podem conter acentos,
 * maiúsculas e espaços. Esta migração os normaliza para o formato novo
 * ([a-z0-9._-], minúsculas, sem acentos) e, se houver colisão, acrescenta
 * um sufixo numérico (fulano -> fulano2). O mapeamento old -> new fica
 * gravado em e5_username_migration_log (criada aqui, se não existir).
 *
 * Uso:
 *   php database/migration_username.php
 *
 * Idempotente: só altera linhas que ainda não estão no formato novo;
 * rodar de novo não muda nada.
 */

require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/validators.php';
loadEnv(__DIR__ . '/../.env');

require_once __DIR__ . '/connection.php';

$pdo->exec(
    'CREATE TABLE IF NOT EXISTS e5_username_migration_log (
        id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
        user_id INT UNSIGNED NOT NULL,
        old_username VARCHAR(30) NOT NULL,
        new_username VARCHAR(30) NOT NULL,
        migrated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        KEY idx_username_migration_user (user_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci'
);

$rows = $pdo->query(
    'SELECT id, name, username, email FROM e5_users ORDER BY id ASC'
)->fetchAll(PDO::FETCH_ASSOC);

$logInsert = $pdo->prepare(
    'INSERT INTO e5_username_migration_log (user_id, old_username, new_username)
     VALUES (:uid, :oldu, :newu)'
);
$check = $pdo->prepare('SELECT COUNT(*) FROM e5_users WHERE username = :u AND id != :id');
$update = $pdo->prepare('UPDATE e5_users SET username = :u, updated_at = NOW() WHERE id = :id');

$migrated = 0;
$unchanged = 0;

foreach ($rows as $row) {
    $current   = (string) $row['username'];
    $candidate = normalize_legacy_username($current);

    // Já está no formato novo: nada a fazer.
    if ($candidate === $current && is_valid_username($current)) {
        $unchanged++;
        continue;
    }

    // A normalização não rendeu algo válido: usa o local do e-mail.
    if ($candidate === '' || !is_valid_username($candidate)) {
        $candidate = normalize_legacy_username((string) strstr((string) $row['email'], '@', true));
    }

    // Último recurso: base neutra.
    if ($candidate === '' || !is_valid_username($candidate)) {
        $candidate = 'usuario';
    }

    // Garante unicidade com sufixo numérico, sem estourar o limite de 30.
    $base = $candidate;
    $suffix = 2;
    $check->execute([':u' => $candidate, ':id' => $row['id']]);

    while ((int) $check->fetchColumn() > 0) {
        $candidate = mb_substr($base, 0, 30 - strlen((string) $suffix)) . $suffix;
        $check->execute([':u' => $candidate, ':id' => $row['id']]);
        $suffix++;
    }

    $update->execute([':u' => $candidate, ':id' => $row['id']]);
    $logInsert->execute([':uid' => $row['id'], ':oldu' => $current, ':newu' => $candidate]);

    printf("  #%s  %s  ->  %s\n", $row['id'], $current, $candidate);
    $migrated++;
}

echo "Usuários migrados: {$migrated}\n";
echo "Já no formato novo (inalterados): {$unchanged}\n";

if ($migrated > 0) {
    echo "Mapeamento completo gravado em e5_username_migration_log.\n";
}