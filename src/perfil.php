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
    <title><?= htmlspecialchars($user['nombre']) ?></title>
    <link rel="stylesheet" href="assets/css/perfil.css">
    <link rel="shortcut icon" href="icono.png">
</head>

<body>

    <!-- Modal -->
    <div id="postModal" class="modal">
        <div class="modal-content">
            <span class="close">&times;</span>
            <img id="modal-img" class="modal-img" alt="">
            <div class="modal-info">
                <br>
                <span><strong>@<?= htmlspecialchars($user['username']) ?></strong></span>
                <span id="modal-text"></span>
                <p id="modal-text"></p>
                <small id="modal-date"></small>
            </div>
        </div>
    </div>

    <script>
        document.addEventListener("DOMContentLoaded", () => {
            const modal = document.getElementById("postModal");
            const modalImg = document.getElementById("modal-img");
            const modalText = document.getElementById("modal-text");
            const modalDate = document.getElementById("modal-date");
            const closeBtn = document.querySelector(".close");

            // Buscar todas las imágenes
            const images = document.querySelectorAll(".post-image");
            console.log("Encontradas:", images.length, "imágenes");

            // Agregar eventos de clic
            images.forEach(img => {
                img.addEventListener("click", () => {
                    console.log("Click en imagen"); // <- prueba
                    modal.style.display = "flex"; // mostrar modal
                    modalImg.src = img.src;
                    modalText.textContent = img.getAttribute("data-text");
                    modalDate.textContent = img.getAttribute("data-date");
                });
            });

            // Cerrar con la X
            closeBtn.addEventListener("click", () => modal.style.display = "none");

            // Cerrar al hacer clic fuera
            modal.addEventListener("click", e => {
                if (e.target === modal) modal.style.display = "none";
            });
        });
    </script>


    <h1><?= htmlspecialchars($user['nombre']) ?> (@<?= htmlspecialchars($user['username']) ?>)</h1>
    <img src="data:image/jpeg;base64,<?= base64_encode($user['foto_perfil']) ?>" alt="Foto de perfil" width="150" style="border-radius:50%;">
    <p><?= nl2br(htmlspecialchars($user['bio'])) ?></p>

    <h2>Publicaciones</h2>

    <div class="publicaciones">
        <?php foreach ($publicaciones as $post): ?>
            <div class="post-card">
                <div class="post-content">
                    <?php if (!empty($post['media'])): ?>
                        <img
                            src="data:image/jpeg;base64,<?= base64_encode($post['media']); ?>"
                            alt="imagen del post"
                            class="post-image"
                            data-text="<?= htmlspecialchars($post['contenido_texto']); ?>"
                            data-date="<?= htmlspecialchars($post['fecha_publicacion']); ?>">
                    <?php endif; ?>
                    <p><?= nl2br(htmlspecialchars($post['contenido_texto'])); ?></p>
                    <p><?= htmlspecialchars($post['fecha_publicacion']); ?></p>
                </div>
            </div>
        <?php endforeach; ?>
    </div>

    <!-- Modal que se superpone -->
    <div id="postModal" class="modal">
        <div class="modal-content">
            <span class="close">&times;</span>
            <img id="modal-img" class="modal-img" alt="">
            <div class="modal-info">
                <p id="modal-text"></p>
                <small id="modal-date"></small>
            </div>
        </div>
    </div>


</body>

</html>