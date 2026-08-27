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
