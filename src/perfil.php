<?php
session_start();
require 'includes/db.php';

// Verificar que se pasó id
if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    echo "Usuario no especificado.";
    exit;
}

$id_usuario = (int)$_GET['id'];

// Datos del usuario
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


// Numero de publicaciones
$stmt = $pdo->prepare("SELECT COUNT(*) AS total_publicaciones FROM publicaciones WHERE id_usuario = ?");
$stmt->execute([$id_usuario]);
$result = $stmt->fetch(PDO::FETCH_ASSOC);
$numPublicaciones = $result['total_publicaciones'];


// Numero de seguidores
$stmt = $pdo->prepare("SELECT COUNT(*) AS total_seguidores FROM seguidores WHERE id_seguido = ?");
$stmt->execute([$id_usuario]);
$result = $stmt->fetch(PDO::FETCH_ASSOC);
$totalSeguidores = $result['total_seguidores'];


// Numero de seguidos
$stmt = $pdo->prepare("SELECT COUNT(*) AS total_seguidos FROM seguidores WHERE id_seguidor = ?");
$stmt->execute([$id_usuario]);
$result = $stmt->fetch(PDO::FETCH_ASSOC);
$totalSeguidos = $result['total_seguidos'];



?>


<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="UTF-8">
    <title>@<?= htmlspecialchars($user['nombre']) ?> - 8Mangos</title>
    <link rel="stylesheet" href="assets/css/perfil.css">
    <link rel="shortcut icon" href="icono.png">
</head>

<body>
    <?php echo htmlspecialchars($_SESSION['usuario']) ?><?php echo htmlspecialchars($_SESSION['usuario']); ?><?php echo htmlspecialchars($_SESSION['usuario']); ?>
    <!-- Modal -->
    <div id="postModal" class="modal">
        <div class="modal-content">
            <span class="close">&times;</span>
            <img id="modal-img" class="modal-img" alt="">
            <div class="modal-info">
                <br>
                <div class="boton"><button id="modal-like" class="like"></button></div>
                <div class="boton"><button id="modal-comentar" class="comentar"></button></div>

                <br>
                <!-- --- IMPORTANTE --- -->
                <span><strong><a href="perfil.php?id=<?= $user['id_usuario'] ?>">@<?= htmlspecialchars($user['username']); ?></a></strong></span>
                <span id="modal-text"></span>
                <p id="modal-text"></p>
                <small id="modal-date"></small>
            </div>
        </div>
    </div>

    <script>
        // ----- JAVA SCRIPT PARA ENFOCARSE EN LA PUBLICACIONES DENTRO DE LOS PERFILES -----
        document.addEventListener("DOMContentLoaded", () => {
            const modal = document.getElementById("postModal");
            const modalImg = document.getElementById("modal-img");
            const modalText = document.getElementById("modal-text");
            const modalDate = document.getElementById("modal-date");
            const closeBtn = document.querySelector(".close");

            // --- Buscar todas las imágenes ---
            const images = document.querySelectorAll(".post-image");
            console.log("Encontradas:", images.length, "imágenes");

            // --- Agregar eventos de clic ---
            images.forEach(img => {
                img.addEventListener("click", () => {
                    modal.style.display = "flex";
                    modalImg.src = img.src;
                    modalText.textContent = img.getAttribute("data-text");
                    modalDate.textContent = img.getAttribute("data-date");

                    // --- Mostrar likes y comentarios ---
                    document.getElementById("modal-like").textContent = " ❤️ " + img.getAttribute("data-likes");
                    document.getElementById("modal-comentar").textContent = " 💬 " + img.getAttribute("data-comments");
                });
            });

            // --- Cerrar con la X ---
            closeBtn.addEventListener("click", () => modal.style.display = "none");

            // --- Cerrar al hacer clic fuera ---
            modal.addEventListener("click", e => {
                if (e.target === modal) modal.style.display = "none";
            });
        });
    </script>

    <div class="contenido">

        <div class="perfil">
            <img src="data:image/jpeg;base64,<?= base64_encode($user['foto_perfil']) ?>" alt="Foto de perfil" width="150" height="150" style="border-radius:50%;">
            <ul>
                <li>
                    <h1><?= htmlspecialchars($user['nombre']) ?> (@<?= htmlspecialchars($user['username']) ?>)</h1>
                    <span><strong><?= $numPublicaciones ?></strong> publicaciones </span> <span><strong><?= $totalSeguidores ?></strong> seguidores </span> <span><strong><?= $totalSeguidos ?></strong> seguidos </span>
                </li>
                <li>
                    <p class="biografia"><?= nl2br(htmlspecialchars($user['bio'])) ?></p>
                </li>
            </ul>
        </div>

        <h2>Publicaciones</h2>

        <hr>

        <div class="publicaciones">
            <?php foreach ($publicaciones as $post): ?>
                <?php
                // --- Numero de likes ---
                $stmt = $pdo->prepare("SELECT COUNT(*) AS total_likes FROM likes WHERE id_publicacion = ?");
                $stmt->execute([$post['id_publicacion']]);
                $totalLikes = $stmt->fetchColumn();

                // --- Numero de comentarios ---
                $stmt = $pdo->prepare("SELECT COUNT(*) AS total_comentarios FROM comentarios WHERE id_publicacion = ?");
                $stmt->execute([$post['id_publicacion']]);
                $result = $stmt->fetch(PDO::FETCH_ASSOC);
                $totalComentarios = $result['total_comentarios'];
                ?>

                <!-- --- TODOS LOS POST DEL PERFIL --- -->
                <div class="post-card">
                    <div class="post-content">
                        <?php if (!empty($post['media'])): ?>
                            <img
                                src="data:image/jpeg;base64,<?= base64_encode($post['media']); ?>"
                                class="post-image"
                                data-text="<?= htmlspecialchars($post['contenido_texto']); ?>"
                                data-date="<?= htmlspecialchars($post['fecha_publicacion']); ?>"
                                data-likes="<?= $totalLikes ?>"
                                data-comments="<?= $totalComentarios; ?>">
                        <?php endif; ?>
                        <!-- --- Contenido del texto de las publicaciones de los posts --- -->
                        <p><?= nl2br(htmlspecialchars($post['contenido_texto'])); ?></p>
                        <!-- --- Fecha del post --- -->
                        <p><?= htmlspecialchars($post['fecha_publicacion']); ?></p>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    </div>


    <!-- --- Modal que se superpone --- -->
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