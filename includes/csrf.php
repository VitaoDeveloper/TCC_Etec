<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

function csrf_token(): string {
    if (empty($_SESSION['_csrf_token'])) {
        $_SESSION['_csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['_csrf_token'];
}

function csrf_field(): string {
    return '<input type="hidden" name="_csrf_token" value="' . csrf_token() . '">';
}

function csrf_verify(?string $token): bool {
    if (empty($token) || empty($_SESSION['_csrf_token'])) {
        return false;
    }
    return hash_equals($_SESSION['_csrf_token'], $token);
}

function csrf_require_valid(): void {
    if (!csrf_verify($_POST['_csrf_token'] ?? null)) {
        http_response_code(419);
        exit('Sessão expirada. Recarregue a página.');
    }
}
