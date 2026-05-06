<?php 
    echo "Processando autenticação";

    include "../../database/connection.php";
    if ($_SERVER["REQUEST_METHOD"] === "POST")
    {
        $identifier = filter_input(INPUT_POST, 'identifier');
        $password = filter_input(INPUT_POST, 'password');

        if (filter_var($identifier, FILTER_VALIDATE_EMAIL)) {
            $identifier_type = "email";
        } else {
            $identifier_type = "username";
        }

        $sql = "SELECT * FROM users WHERE $identifier_type = :identifier";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([
            ':identifier' => $identifier
        ]);
        
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        
        $db_identifier = $result[$identifier_type];
        $db_password = $result["password"];

        if (!empty($db_identifier)) 
        {
            if (!password_verify($password, $db_password)) echo "Senha incorreta!";
            else 
            {
                echo "Senha correta!";
                echo "<meta http-equiv='refresh' content='1; URL=../products/products.php'>";
            }
        }
        else
        {
            echo "Usuário ou Email não encontrado";
        }
    }

?>