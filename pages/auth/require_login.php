<?php
session_start();
$_SESSION['auth_error'] = 'Para usar funcionalidades da loja, faça login primeiro.';
$redirect = $_GET['redirect'] ?? '../auth/login.php';
header('Location: ../auth/login.php?next=' . urlencode($redirect));
exit;
?>
