<?php
session_start();
include "../../database/connection.php";

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: register.php');
    exit;
}

$name = trim((string) filter_input(INPUT_POST, 'name'));
$email = trim((string) filter_input(INPUT_POST, 'email'));
$username = trim((string) filter_input(INPUT_POST, 'username'));
$password = (string) filter_input(INPUT_POST, 'password');
$postalCode = trim((string) filter_input(INPUT_POST, 'postalcode'));
$street = trim((string) filter_input(INPUT_POST, 'street'));
$number = (int) (filter_input(INPUT_POST, 'number') ?: 0);
$complement = trim((string) filter_input(INPUT_POST, 'complement')) ?: null;

if ($name === '' || $email === '' || $username === '' || $password === '' || $postalCode === '' || $street === '' || $number <= 0) {
    $_SESSION['auth_error'] = 'Preencha todos os campos obrigatórios corretamente.';
    header('Location: register.php');
    exit;
}

$sql = 'INSERT INTO users (name, email, username, password, postal_code, street, number, complement) VALUES (:name, :email, :username, :password, :postal_code, :street, :number, :complement)';

try {
    $stmt = $pdo->prepare($sql);
    $stmt->execute([
        ':name' => $name,
        ':email' => $email,
        ':username' => $username,
        ':password' => password_hash($password, PASSWORD_DEFAULT),
        ':postal_code' => $postalCode,
        ':street' => $street,
        ':number' => $number,
        ':complement' => $complement,
    ]);

    $_SESSION['user_id'] = (int) $pdo->lastInsertId();
    $_SESSION['user_name'] = $name;
    $_SESSION['user_role'] = 'customer';

    header('Location: ../products/products.php');
    exit;
} catch (PDOException $e) {
    error_log('User registration error: ' . $e->getMessage());
    $_SESSION['auth_error'] = 'Não foi possível concluir o cadastro. Tente novamente.';
    header('Location: register.php');
    exit;
}
?>
