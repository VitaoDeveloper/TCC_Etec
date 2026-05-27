<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

function rate_limit_check(string $key, int $maxAttempts = 5, int $windowMinutes = 15): bool {
    $storageKey = '_rate_limit_' . $key;
    $now = time();
    if (!isset($_SESSION[$storageKey])) {
        $_SESSION[$storageKey] = [];
    }
    $_SESSION[$storageKey] = array_filter($_SESSION[$storageKey], function (int $ts) use ($now, $windowMinutes) {
        return $ts > $now - ($windowMinutes * 60);
    });
    if (count($_SESSION[$storageKey]) >= $maxAttempts) {
        return false;
    }
    $_SESSION[$storageKey][] = $now;
    return true;
}

function rate_limit_reset(string $key): void {
    unset($_SESSION['_rate_limit_' . $key]);
}
