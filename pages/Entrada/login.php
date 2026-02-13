<?php
$page_title = 'Login de Usuário';
?>

<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
    <title><?php echo $page_title; ?></title>

    <!-- Fontes Google -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Playfair+Display:wght@700&family=Rajdhani:wght@400;500;600;700&display=swap" rel="stylesheet">

    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">

    <!-- Seus estilos -->
    <link rel="stylesheet" href="../../assets/css/style.css">
    <link rel="stylesheet" href="../../assets/css/admin.css">

    <style>
        body {
            background: var(--color-black);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 40px 20px;
            overflow: hidden;
        }

        .auth-wrapper {
            width: 100%;
            max-width: 520px;
            background: var(--color-black-light);
            border: 1px solid var(--color-border);
            border-radius: 12px;
            padding: 45px 40px;
            box-shadow: var(--shadow);
            max-height: 95vh;
            overflow-y: auto;
        }

        .auth-logo {
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 12px;
            margin-bottom: 35px;
        }

        .auth-logo .logo-icon {
            font-size: 2.8rem;
            color: var(--color-primary);
        }

        .auth-logo .logo-text {
            font-size: 2.6rem;
            font-weight: 700;
            letter-spacing: 1px;
        }

        .auth-logo .logo-text span {
            color: var(--color-primary);
        }

        .form-title {
            font-size: 1.6rem;
            margin: 0 0 35px;
            text-align: center;
            color: var(--color-white);
        }

        .admin-form-group {
            position: relative;
            margin-bottom: 25px;
        }

        .admin-form-group input:focus {
            border-color: var(--color-primary);
            box-shadow: 0 0 0 3px rgba(212, 175, 55, 0.15);
        }

        .password-toggle {
            position: absolute;
            right: 16px;
            top: 50%;
            transform: translateY(-50%);
            color: var(--color-gray-light);
            cursor: pointer;
            font-size: 1.3rem;
            background: transparent;
            border: none;
        }

        .btn-auth {
            width: 100%;
            padding: 16px;
            background: var(--color-primary);
            color: var(--color-black);
            border: none;
            border-radius: 30px;
            font-size: 1.05rem;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 1px;
            cursor: pointer;
            transition: var(--transition);
            margin-top: 20px;
        }

        .btn-auth:hover {
            background: var(--color-primary-dark);
            transform: translateY(-2px);
            box-shadow: var(--shadow-hover);
        }

        .auth-options {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin: 15px 0 25px;
            font-size: 0.95rem;
            color: var(--color-gray-light);
        }

        .remember-me {
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .remember-me input {
            accent-color: var(--color-primary);
        }

        .forgot-password {
            color: var(--color-primary);
            text-decoration: none;
        }

        .forgot-password:hover {
            text-decoration: underline;
        }

        .auth-footer {
            text-align: center;
            margin-top: 30px;
            color: var(--color-gray-light);
            font-size: 0.95rem;
        }

        .auth-footer a {
            color: var(--color-primary);
            font-weight: 500;
            text-decoration: none;
        }

        .auth-footer a:hover {
            text-decoration: underline;
        }

        .back-to-site {
            display: block;
            text-align: center;
            margin-top: 25px;
            color: var(--color-gray-light);
            font-size: 0.95rem;
            text-decoration: none;
        }

        .back-to-site:hover {
            color: var(--color-primary);
        }

        @media (max-width: 576px) {
            .auth-wrapper {
                padding: 35px 25px;
            }
            .auth-logo .logo-text {
                font-size: 2.2rem;
            }
            .auth-logo .logo-icon {
                font-size: 2.4rem;
            }
        }
    </style>
</head>
<body>

    <div class="auth-wrapper">

        <!-- Logo centralizada -->
        <div class="auth-logo">
            <span class="logo-icon"><i class="fas fa-crown"></i></span>
            <span class="logo-text">Royal<span>Tech</span></span>
        </div>

        <h2 class="form-title">Área de Login</h2>

        <form class="login-form" action="index.php" method="POST">

            <div class="admin-form-group">
                <label for="email">E-mail</label>
                <input type="email" id="email" name="email" placeholder="seuemail@royaltech.com" required>
            </div>

            <div class="admin-form-group">
                <label for="password">Senha</label>
                <input type="password" id="password" name="password" placeholder="••••••••••••" required>
                <button type="button" class="password-toggle" id="togglePassword">
                    <i class="fas fa-eye"></i>
                </button>
            </div>

            <div class="auth-options">
                <label class="remember-me">
                    <input type="checkbox" name="remember">
                    <span>Lembrar-me</span>
                </label>
                <a href="#" class="forgot-password">Esqueceu a senha?</a>
            </div>

            <button type="submit" class="btn-auth">
                <i class="fas fa-sign-in-alt"></i> Entrar
            </button>

        </form>

        <div class="auth-footer">
            Ainda não tem conta? <a href="register.php">Crie uma agora</a>
        </div>

        <a href="../../index.php" class="back-to-site">
            <i class="fas fa-arrow-left"></i> Voltar para o site
        </a>

    </div>

    <script>
        // Toggle de visualização da senha
        const toggleBtn = document.getElementById('togglePassword');
        const passwordInput = document.getElementById('password');

        if (toggleBtn && passwordInput) {
            toggleBtn.addEventListener('click', () => {
                const type = passwordInput.type === 'password' ? 'text' : 'password';
                passwordInput.type = type;
                
                const icon = toggleBtn.querySelector('i');
                icon.classList.toggle('fa-eye');
                icon.classList.toggle('fa-eye-slash');
            });
        }
    </script>

</body>
</html>