<?php
session_start();
$role = $_SESSION['user_role'] ?? 'customer';
session_unset();
session_destroy();
header('Location: ' . ($role === 'admin' ? '../admin/login.php' : '../auth/login.php'));
exit;
