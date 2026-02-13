<?php
session_start();
require 'includes/db.php';

if (!isset($_SESSION['usuario'])) {
    header("Location: actions/login.php");
}

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
    <title>8Mangos</title>
    <link rel="stylesheet" href="assets/css/style.css">
    <link rel="shortcut icon" href="assets/images/8mangos.png">
</head>

<body>

    <main>
        <?php include('includes/WIP_aside.php') ?>

        <div class="posts">
            <?php include('includes/WIP_header.php') ?>

            <?php include('templates/post_card.php'); ?>

        </div>
    </main>


</body>

</html>