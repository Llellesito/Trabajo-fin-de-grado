<?php
$host = "db";             // Nombre del servicio MySQL en docker-compose
$dbname = "ejemplo_db";   // Base de datos que creaste
$username = "root";       // Usuario MySQL
$password = "rootpass";   // Contraseña

try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    //echo "Base de datos conectada";
} catch (PDOException $e) {
    die("Error de conexión: " . $e->getMessage());
}
?>