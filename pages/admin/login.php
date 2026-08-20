<?php
$page_title = 'Login Administrativo - Royal Tech';
require_once __DIR__ . '/../../includes/csrf.php';
?>

<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $page_title; ?></title>
    <link rel="stylesheet" href="../../assets/css/style.css">
    <link rel="stylesheet" href="../../assets/css/admin.css">
    <link rel="stylesheet" href="../../assets/css/mercadolivre-style.css">
    <link rel="stylesheet" href="../../assets/css/login.css">
    <link href="https://fonts.googleapis.com/css2?family=Playfair+Display:wght@400;600;700&family=Rajdhani:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>
<body class="login-page">
    <div class="login-container">
        <div class="login-box">
            <div class="login-header">
                <div class="login-logo">
                    <span class="logo-icon"><i class="fas fa-crown"></i></span>
                    <span class="logo-text">Royal<span>Tech</span></span>
                </div>
                <h2>Área Administrativa</h2>
                <p>Faça login para gerenciar sua loja</p>
            </div>

            <form class="login-form" action="authenticate.php" method="POST">
                <?php echo csrf_field(); ?>
                <div class="admin-form-group">
                    <label for="email">E-mail</label>
                    <input type="email" id="email" name="email" placeholder="Seu e-mail" required>
                </div>

                <div class="admin-form-group">
                    <label for="password">Senha</label>
                    <input type="password" id="password" name="password" placeholder="Sua senha" required>
                </div>

                <div class="login-options">
                    <label class="remember-me">
                        <input type="checkbox" name="remember">
                        <span>Lembrar-me</span>
                    </label>
                    <a href="../auth/forgot-password.php" class="forgot-link">Esqueceu a senha?</a>
                </div>

                <button type="submit" class="btn btn-primary">
                    <i class="fas fa-sign-in-alt"></i>
                    Entrar
                </button>
            </form>

            <div class="login-footer">
                <a href="../../index.php" class="back-to-site">
                    <i class="fas fa-arrow-left"></i>
                    Voltar para o site
                </a>
            </div>
        </div>
    </div>
</body>
</html>
