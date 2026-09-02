<?php
function normalizeImagePath(string $rawPath, string $prefix = 'assets/img/products/'): string
{
    $path = trim($rawPath);
    if ($path === '') {
        return '';
    }
    if (preg_match('#^https?://#i', $path)) {
        return $path;
    }
    if ($path[0] !== '/') {
        $path = '/' . $prefix . ltrim($path, '/');
    }
    return $path;
}

function resolveImage(string $path, string $basePath): string
{
    if ($path === '') return $basePath . 'assets/img/placeholder-product.svg';
    if (preg_match('#^/#', $path)) return $basePath . ltrim($path, '/');
    if (preg_match('#^https?://#i', $path)) return $path;
    return $basePath . $path;
}

function uploadErrorMessage(int $code): string
{
    switch ($code) {
        case UPLOAD_ERR_INI_SIZE:
        case UPLOAD_ERR_FORM_SIZE:
            return 'A imagem excede o tamanho máximo permitido (2MB).';
        case UPLOAD_ERR_PARTIAL:
            return 'O upload foi interrompido no meio. Tente novamente.';
        case UPLOAD_ERR_NO_FILE:
            return 'Nenhum arquivo foi selecionado.';
        case UPLOAD_ERR_NO_TMP_DIR:
        case UPLOAD_ERR_CANT_WRITE:
            return 'Não foi possível gravar a imagem no servidor.';
        case UPLOAD_ERR_EXTENSION:
            return 'O upload foi bloqueado pelo servidor.';
        default:
            return 'Falha no upload da imagem.';
    }
}

function imageAvailable(string $path): bool
{
    if ($path === '') return false;
    if (preg_match('#^https?://#i', $path)) return true;
    $file = realpath(__DIR__ . '/../' . ltrim($path, '/'));
    return $file !== false && is_file($file);
}

function renderProductImage(string $path, string $basePath, string $fallback = 'assets/img/placeholder-product.svg'): string
{
    if (imageAvailable($path)) {
        return resolveImage($path, $basePath);
    }
    return $basePath . ltrim($fallback, '/');
}
