<?php
session_start();
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
    <title>Cesped Instantaneo</title>
    <link rel="stylesheet" href="/assets/css/style.css">
    <link rel="shortcut icon" href="icono.png">
</head>

<body>

    <header>
        <h1 style="text-align:center;">📱 Cesped Instantaneo 🗣</h1>
        <nav style="text-align:center; margin-top:10px;">
            <?php if (isset($_SESSION['usuario'])): ?>
                <span style="font-size: 18px; color: white;">

                    Bienvenido, <a href="miPerfil.php?id=<?php echo $_SESSION['id_usuario']; ?>" 
                   style="color: #90ee90; text-decoration: underline;">
                   <?php echo htmlspecialchars($_SESSION['usuario']); ?>

                </span>
                <a href="logout.php" style="font-size: 20px; color: white; margin-left: 10px;">Cerrar sesión</a>
            <?php else: ?>
                <a href="registro.php" style="font-size: 20px; color: white; margin-right:10px;">Registrarse</a>
                <a href="login.php" style="font-size: 20px; color: white;">Inicia sesión</a>
            <?php endif; ?>
        </nav>
    </header>

    <?php include 'post_card.php'; ?>

</body>

</html>