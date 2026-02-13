<?php
session_start();
require_once '../includes/db.php';

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    if (!isset($_SESSION['id_usuario'])) {
        die("Error: Usuario no identificado.");
    }

    // Cambiamos $conexion por $pdo para que coincida con tu db.php
    if (!isset($pdo)) {
        die("Error: La variable de conexión \$pdo no existe. Revisa includes/db.php");
    }

    $id_usuario = $_SESSION['id_usuario'];
    $texto = $_POST['contenido_texto'] ?? '';

    $media = null;
    if (!empty($_FILES['media']['tmp_name'])) {
        $media = fopen($_FILES['media']['tmp_name'], 'rb');
    }

    try {
        // SQL sin privacidad
        $sql = "INSERT INTO publicaciones (id_usuario, media, contenido_texto) 
                VALUES (:id_usuario, :media, :texto)";

        $stmt = $pdo->prepare($sql); // Usamos $pdo aquí también

        $stmt->bindParam(':id_usuario', $id_usuario, PDO::PARAM_INT);
        $stmt->bindParam(':media', $media, PDO::PARAM_LOB);
        $stmt->bindParam(':texto', $texto, PDO::PARAM_STR);

        if ($stmt->execute()) {
            // Si existe una página de procedencia, vuelve a ella. Si no, va al index.
            $fallback = "../index.php";
            $referencia = $_SERVER['HTTP_REFERER'] ?? $fallback;

            header("Location: " . $referencia);
            exit();
        }
    } catch (PDOException $e) {
        echo "Error en la base de datos: " . $e->getMessage();
    }
}
