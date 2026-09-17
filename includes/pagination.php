<?php
// Helpers de paginação reutilizáveis pelas listagens do painel admin.
// Mantêm os parâmetros atuais da query string (busca, filtros) ao trocar de página.

function pagination_page(): int
{
    return max(1, (int) ($_GET['page'] ?? 1));
}

function pagination_limit(int $default = 20, int $max = 100): int
{
    $limit = (int) ($_GET['per_page'] ?? $default);
    if ($limit < 1) {
        $limit = $default;
    }
    if ($limit > $max) {
        $limit = $max;
    }
    return $limit;
}

function pagination_offset(int $page, int $limit): int
{
    return ($page - 1) * $limit;
}

// Renderiza o controle de paginação (some quando há só uma página).
// $queryParams deve conter os filtros ativos, SEM a chave 'page'.
function pagination_render(int $page, int $totalPages, array $queryParams = []): string
{
    if ($totalPages <= 1) {
        return '';
    }
    $page = max(1, min($page, $totalPages));

    $url = static function (int $p) use ($queryParams): string {
        $queryParams['page'] = $p;
        return '?' . http_build_query($queryParams);
    };

    $html = '<nav class="admin-pagination" aria-label="Paginação">';

    if ($page > 1) {
        $html .= '<a class="page-link" href="' . htmlspecialchars($url($page - 1), ENT_QUOTES, 'UTF-8') . '" rel="prev" aria-label="Página anterior"><i class="fas fa-chevron-left"></i></a>';
    } else {
        $html .= '<span class="page-link disabled" aria-disabled="true"><i class="fas fa-chevron-left"></i></span>';
    }

    $window = 2;
    $pages = [];
    for ($p = 1; $p <= $totalPages; $p++) {
        if ($p === 1 || $p === $totalPages || abs($p - $page) <= $window) {
            $pages[] = $p;
        }
    }
    // Remove duplicatas e ordena.
    $pages = array_values(array_unique($pages));
    sort($pages);

    $previous = 0;
    foreach ($pages as $p) {
        if ($previous + 1 < $p) {
            $html .= '<span class="page-ellipsis">…</span>';
        }
        if ($p === $page) {
            $html .= '<span class="page-link current" aria-current="page">' . $p . '</span>';
        } else {
            $html .= '<a class="page-link" href="' . htmlspecialchars($url($p), ENT_QUOTES, 'UTF-8') . '">' . $p . '</a>';
        }
        $previous = $p;
    }

    if ($page < $totalPages) {
        $html .= '<a class="page-link" href="' . htmlspecialchars($url($page + 1), ENT_QUOTES, 'UTF-8') . '" rel="next" aria-label="Próxima página"><i class="fas fa-chevron-right"></i></a>';
    } else {
        $html .= '<span class="page-link disabled" aria-disabled="true"><i class="fas fa-chevron-right"></i></span>';
    }

    $html .= '</nav>';
    return $html;
}
