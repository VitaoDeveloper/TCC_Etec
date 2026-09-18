<?php
$page_title = 'VAI SÃO PAULO';
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>VAI SÃO PAULO 📣⚪🔴</title>
    <style>
        html, body {
            margin: 0;
            padding: 0;
            min-height: 100vh;
            background: #0b090a;
        }
        body {
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            gap: 32px;
            padding: 24px;
            box-sizing: border-box;
        }
        .gif-wrap {
            max-width: 360px;
            width: 100%;
        }
        .gif-wrap img {
            display: block;
            width: 100%;
            height: auto;
            border-radius: 14px;
        }
        h1 {
            margin: 0;
            text-align: center;
            font-family: 'Segoe UI', Arial, sans-serif;
            font-size: clamp(2.2rem, 8vw, 4.5rem);
            font-weight: 800;
            line-height: 1.1;
        }
        .w { color: #ffffff; }
        .r { color: #e32127; }
        .back {
            color: #888;
            font-family: 'Segoe UI', Arial, sans-serif;
            font-size: 0.9rem;
            text-decoration: none;
        }
        .back:hover { color: #ccc; }
    </style>
</head>
<body>
    <div class="gif-wrap">
        <img src="../../assets/img/memes/paulo-vitor-vai-sao-paulo.gif" alt="VAI SÃO PAULO">
    </div>
    <h1>VAI SÃO PAULO <span class="w">📣</span><span class="w">⚪</span><span class="r">🔴</span></h1>
    <a class="back" href="../products/about.php">← Voltar</a>
</body>
</html>