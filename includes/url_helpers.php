<?php

declare(strict_types=1);

/**
 * Helpers de URL e de saída.
 *
 * Existe por causa de um defeito concreto: as páginas usavam
 * $base_path = '../../' escrito à mão, relativo ao diretório de cada
 * arquivo. Funciona enquanto a página fica em pages/auth/ e quebra
 * assim que o arquivo muda de lugar — e o sintoma é o navegador pedir
 * /assets/css/... e receber 404, sem erro no log do PHP.
 *
 * base_url() resolve a raiz da aplicação a partir do próprio
 * SCRIPT_NAME, então continua correta depois de qualquer movimentação
 * de arquivo, e serve para links e para <img src>.
 */

require_once __DIR__ . '/config.php';

/**
 * Raiz web da aplicação, sempre com barra final ('' ou '/TCC_Etec/').
 *
 * Calculada uma vez por requisição, a partir do caminho real do script
 * que está rodando. Um include não conta: é o arquivo entrance que
 * diz onde a aplicação está.
 */
function base_url(?string $path = null): string
{
    static $root = null;

    if ($root === null) {
        $script = (string) ($_SERVER['SCRIPT_NAME'] ?? '');

        // A aplicação está na raiz do host quando o script começa com "/"
        // seguido de um segmento que é o próprio arquivo (ex.: /index.php).
        // Caso contrário, o primeiro segmento é o nome da pasta da app.
        $dir = str_replace('\\', '/', dirname($script));

        // Se o script está na raiz do projeto, dirname devolve "/".
        $dir = rtrim($dir, '/');

        // Sobe um nível quando o script está dentro de pages/<area>/.
        if (preg_match('#/pages/[^/]+$#', $dir)) {
            $dir = (string) preg_replace('#/pages/[^/]+$#', '', $dir);
        }

        $root = $dir . '/';
    }

    if ($path === null) {
        return $root;
    }

    return $root . ltrim($path, '/');
}

/**
 * URL de um asset versionado (?v=ASSET_VERSION), para quebrar cache.
 */
function asset_url(string $path): string
{
    $url = base_url($path);
    $sep = str_contains($url, '?') ? '&' : '?';

    return $url . $sep . 'v=' . ASSET_VERSION;
}

/**
 * Escapa para HTML. Todo valor vindo do banco ou do usuário passa por
 * aqui antes de chegar ao template.
 *
 * ENT_QUOTES cobre aspas simples e duplas: sem isso, um nome com " no
 * meio quebraria o atributo e o resto do nome viraria tag.
 */
function e(?string $value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

/** Escapa para uso dentro de um atributo que vira URL. */
function e_url(?string $value): string
{
    return e(rawurlencode((string) $value));
}

/**
 * Monta uma query string já escapada a partir de um array.
 * Retorna '' quando vazio, para o HTML não ficar com "?&".
 */
function query_string(array $params): string
{
    $params = array_filter(
        $params,
        static fn($v) => $v !== null && $v !== '' && $v !== false
    );

    return $params === [] ? '' : '?' . http_build_query($params);
}

/**
 * Link para uma página da área da conta, preservando filtros.
 * Todo filtro que a tela usa (status, busca, página) entra no array, e
 * o valor '' é descartado — sem ele apareceriam "&status=" na URL.
 */
function url_with_query(string $page, array $params = []): string
{
    return e(base_url('pages/auth/' . ltrim($page, '/')) . query_string($params));
}
