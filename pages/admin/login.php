<?php
$page_title = 'Login Administrativo - Royal Tech';
?>

<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $page_title; ?></title>
    <link rel="stylesheet" href="../../assets/css/style.css">
    <link rel="stylesheet" href="../../assets/css/admin.css">
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
            
            <form class="login-form" action="index.php" method="POST">
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
                    <a href="#" class="forgot-password">Esqueceu a senha?</a>
                </div>
                
                <button type="submit" class="btn btn-primary" style="width: 100%;">
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
    
    <style>
        .login-page {
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            background: linear-gradient(135deg, var(--color-black) 0%, var(--color-black-light) 100%);
        }
        
        .login-container {
            width: 100%;
            max-width: 450px;
            padding: 20px;
        }
        
        .login-box {
            background: var(--color-black-light);
            border: 1px solid var(--color-border);
            border-radius: 15px;
            padding: 50px 40px;
            text-align: center;
        }
        
        .login-header {
            margin-bottom: 40px;
        }
        
        .login-logo {
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 10px;
            margin-bottom: 30px;
        }
        
        .login-logo .logo-icon {
            font-size: 2.5rem;
            color: var(--color-primary);
        }
        
        .login-logo .logo-text {
            font-family: var(--font-secondary);
            font-size: 2rem;
            font-weight: 700;
            color: var(--color-white);
        }
        
        .login-logo .logo-text span {
            color: var(--color-primary);
        }
        
        .login-header h2 {
            font-size: 1.5rem;
            margin-bottom: 10px;
        }
        
        .login-header p {
            color: var(--color-gray);
        }
        
        .login-options {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 25px;
        }
        
        .remember-me {
            display: flex;
            align-items: center;
            gap: 10px;
            color: var(--color-gray-light);
            cursor: pointer;
        }
        
        .remember-me input {
            accent-color: var(--color-primary);
        }
        
        .forgot-password {
            color: var(--color-primary);
            font-size: 0.9rem;
        }
        
        .forgot-password:hover {
            text-decoration: underline;
        }
        
        .login-footer {
            margin-top: 30px;
            padding-top: 30px;
            border-top: 1px solid var(--color-border);
        }
        
        .back-to-site {
            display: inline-flex;
            align-items: center;
            gap: 10px;
            color: var(--color-gray-light);
            font-size: 0.9rem;
            transition: var(--transition);
        }
        
        .back-to-site:hover {
            color: var(--color-primary);
        }
    </style>
</body>
</html>
