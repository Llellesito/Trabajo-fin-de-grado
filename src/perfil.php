<?php
require 'db.php';

// Verificar que se pasó id
if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    echo "Usuario no especificado.";
    exit;
}

$id_usuario = (int)$_GET['id'];

// Obtener datos del usuario
$stmt = $pdo->prepare("SELECT id_usuario, username, nombre, bio, foto_perfil FROM usuarios WHERE id_usuario = ?");
$stmt->execute([$id_usuario]);
$user = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$user) {
    echo "Usuario no encontrado.";
    exit;
}

// Obtener publicaciones del usuario
$stmt = $pdo->prepare("SELECT id_publicacion, media, contenido_texto, fecha_publicacion FROM publicaciones WHERE id_usuario = ? ORDER BY fecha_publicacion DESC");
$stmt->execute([$id_usuario]);
$publicaciones = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="UTF-8">
    <title>Mi Red Social</title>
    <link rel="stylesheet" href="assets/css/perfil.css">
    <link rel="shortcut icon" href="icono.png">
</head>

<body>

    <h1><?= htmlspecialchars($user['nombre']) ?> (@<?= htmlspecialchars($user['username']) ?>)</h1>
    <p><?= nl2br(htmlspecialchars($user['bio'])) ?></p>
    <img src="data:image/jpeg;base64,<?= base64_encode($user['foto_perfil']) ?>" alt="Foto de perfil" width="100" style="border-radius:50%;">

    <h2>Publicaciones</h2>

    <?php foreach ($publicaciones as $post): ?>
        <div class="post-card">
            <div class="post-content">
                <?php if (!empty($post['media'])): ?>
                    <img src="data:image/jpeg;base64,<?= base64_encode($post['media']); ?>" alt="imagen del post" width="300">
                <?php endif; ?>
                <p><?= nl2br(htmlspecialchars($post['contenido_texto'])); ?></p>
                <small><?= htmlspecialchars($post['fecha_publicacion']); ?></small>
            </div>
        </div>
    <?php endforeach; ?>

</body>

</html>