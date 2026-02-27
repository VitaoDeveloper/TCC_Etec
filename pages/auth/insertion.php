<?php
    include "../../database/connection.php";
    if ($_SERVER["REQUEST_METHOD"] === "POST")
    {
      $name = filter_input(INPUT_POST, 'name');
      $email = filter_input(INPUT_POST, 'email');
      $username = filter_input(INPUT_POST, 'username');
      $password = filter_input(INPUT_POST, 'password');
      $postal_code = filter_input(INPUT_POST, 'postalcode');
      $street = filter_input(INPUT_POST, 'street');
      $number = filter_input(INPUT_POST, 'number') ?: 100;
      $complement = filter_input(INPUT_POST, 'complement') ?: null;

      $sql = "
        INSERT INTO users
        (name, email, username, password, postal_code, street, number, complement)
        VALUES
        (:name, :email, :username, :password, :postal_code, :street, :number, :complement)
      ";

      try {
        $stmt = $pdo->prepare($sql);

        $stmt->execute([
            ':name' => $name,
            ':email' => $email,
            ':username' => $username,
            ':password' => password_hash($password, PASSWORD_DEFAULT),
            ':postal_code' => $postal_code,
            ':street' => $street,
            ':number' => $number,
            ':complement' => $complement
        ]);
      } catch (PDOException $e) {
        $error = $e->getMessage();
        echo "<script>alert('Erro no cadastro: $error')</script>";
      } finally {
          echo "<meta http-equiv='refresh' content='0; URL=../products/products.php'>";
      }
    }
  ?>