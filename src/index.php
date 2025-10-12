<?php
require 'db.php';

$sql = "SELECT p.id_publicacion, p.contenido_texto, p.fecha_publicacion,
               u.username, u.foto_perfil
        FROM publicaciones p
        JOIN usuarios u ON p.id_usuario = u.id_usuario
        ORDER BY p.fecha_publicacion DESC";
$stmt = $pdo->query($sql);
$posts = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="UTF-8">
    <title>Mi Red Social</title>
    <link rel="stylesheet" href="assets/css/style.css">
    <link rel="shortcut icon" href="icono.png">
</head>

<body>

    <header>
        <h1 style="text-align:center;">📱 Base de datos conectada 🗣</h1>
    </header>

    <?php include 'post_card.php'; ?>

</body>

</html>