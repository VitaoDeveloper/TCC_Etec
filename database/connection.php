<?php 
    $host = "localhost";
    $username = "root";
    $password = "";
    $database = "royaltech";

    $connection = mysqli_connect($host, $username, $password, $database);

    if ($connection) {
        echo "Succeed!";
    } else {
        echo "Error!";
    }
?>