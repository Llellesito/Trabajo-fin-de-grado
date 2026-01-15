<?php
session_start();
require 'includes/db.php';

// Inicializar variables
$user = null;
$publicaciones = [];
$numPublicaciones = 0;
$totalSeguidores = 0;
$totalSeguidos = 0;

// Si hay sesión, cargamos datos del usuario
if (isset($_SESSION['id_usuario'])) {
    $id_usuario = $_SESSION['id_usuario'];

    // Datos del usuario
    $stmt = $pdo->prepare("SELECT id_usuario, username, nombre, bio, foto_perfil FROM usuarios WHERE id_usuario = ?");
    $stmt->execute([$id_usuario]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($user) {
        // Publicaciones
        $stmt = $pdo->prepare("SELECT id_publicacion, media, contenido_texto, fecha_publicacion FROM publicaciones WHERE id_usuario = ? ORDER BY fecha_publicacion DESC");
        $stmt->execute([$id_usuario]);
        $publicaciones = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // Contadores
        $numPublicaciones = $pdo->prepare("SELECT COUNT(*) FROM publicaciones WHERE id_usuario = ?");
        $numPublicaciones->execute([$id_usuario]);
        $numPublicaciones = $numPublicaciones->fetchColumn();

        $totalSeguidores = $pdo->prepare("SELECT COUNT(*) FROM seguidores WHERE id_seguido = ?");
        $totalSeguidores->execute([$id_usuario]);
        $totalSeguidores = $totalSeguidores->fetchColumn();

        $totalSeguidos = $pdo->prepare("SELECT COUNT(*) FROM seguidores WHERE id_seguidor = ?");
        $totalSeguidos->execute([$id_usuario]);
        $totalSeguidos = $totalSeguidos->fetchColumn();
    }
}
?>

<!-- UNICAMENTE PARA PRUEBAS

<?php if ($user): ?>
    <h1><?= htmlspecialchars($user['nombre']) ?></h1>
    <p>Publicaciones: <?= $numPublicaciones ?></p>
    <?php foreach ($publicaciones as $post): ?>
        <p><?= htmlspecialchars($post['contenido_texto']) ?></p>
    <?php endforeach; ?>
<?php else: ?>
    <p>No hay usuario logueado.</p>
<?php endif; ?>
-->

<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="UTF-8">
    <title>@<?= htmlspecialchars($user['nombre']) ?> - 8Mangos</title>
    <link rel="stylesheet" href="assets/css/perfil.css">
    <link rel="shortcut icon" href="icono.png">
</head>

<body>
    <?php echo htmlspecialchars($_SESSION['usuario']); ?><?php echo htmlspecialchars($_SESSION['usuario']); ?><?php echo htmlspecialchars($_SESSION['usuario']); ?>
    <!-- Modal -->
    <div id="postModal" class="modal">
        <div class="modal-content">
            <span class="close">&times;</span>
            <img id="modal-img" class="modal-img" alt="">
            <div class="modal-info">
                <br>
                <button id="modal-likes" class="btn"></button>
                <button id="modal-comments" class="btn"></button>

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
                    modal.style.display = "flex";
                    modalImg.src = img.src;
                    modalText.textContent = img.getAttribute("data-text");
                    modalDate.textContent = img.getAttribute("data-date");

                    // Mostrar likes y comentarios
                    document.getElementById("modal-likes").textContent = img.getAttribute("data-likes") + " ❤️ Like";
                    document.getElementById("modal-comments").textContent = img.getAttribute("data-comments") + " 💬 Comentar";
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

    <div class="contenido">

        <div class="perfil">
            <img src="data:image/jpeg;base64,<?= base64_encode($user['foto_perfil']) ?>" alt="Foto de perfil" width="150" style="border-radius:50%;">
            <ul>
                <li>
                    <h1><?= htmlspecialchars($user['nombre']) ?> (@<?= htmlspecialchars($user['username']) ?>)</h1>
                    <span><strong><?= $numPublicaciones ?></strong> publicaciones </span>
                    <span><strong><?= $totalSeguidores ?></strong> seguidores </span>
                    <span><strong><?= $totalSeguidos ?></strong> seguidos </span>
                </li>
                <li>
                    <p class="biografia"><?= nl2br(htmlspecialchars($user['bio'])) ?></p>
                </li>
            </ul>
        </div>

        <!-- 🔹 AQUI agregas el código -->
        <?php if ($esPropio): ?>
            <a href="actions/WIP_editar_perfil.php">Editar perfil</a>
        <?php else: ?>
            <form method="post" action="seguir.php">
                <input type="hidden" name="id_seguido" value="<?= $id_usuario ?>">
                <button type="submit">Seguir</button>
            </form>
        <?php endif; ?>
        <?php
        $esPropio = isset($_SESSION['id_usuario']) && $_SESSION['id_usuario'] == $id_usuario;
        if ($esPropio): ?>
            <div style="text-align:center; margin: 15px 0;">
                <a href="actions/WIP_editar_perfil.php" style="color:white; background:#2b7a2b; padding:10px 15px; border-radius:8px; text-decoration:none;">
                    ✏️ Editar perfil
                </a>
            </div>
        <?php endif; ?>
        <!-- 🔹 Fin de la parte nueva -->

        <h2>Publicaciones</h2>
        <hr>


        <div class="publicaciones">
            <?php foreach ($publicaciones as $post): ?>
                <?php
                // Numero de likes
                $stmt = $pdo->prepare("SELECT COUNT(*) AS total_likes FROM likes WHERE id_publicacion = ?");
                $stmt->execute([$post['id_publicacion']]);
                $totalLikes = $stmt->fetchColumn();

                // Numero de comentarios
                $stmt = $pdo->prepare("SELECT COUNT(*) AS total_comentarios FROM comentarios WHERE id_publicacion = ?");
                $stmt->execute([$post['id_publicacion']]);
                $result = $stmt->fetch(PDO::FETCH_ASSOC);
                $totalComentarios = $result['total_comentarios'];
                ?>

                <div class="post-card">
                    <div class="post-content">
                        <?php if (!empty($post['media'])): ?>
                            <img
                                src="data:image/jpeg;base64,<?= base64_encode($post['media']); ?>"
                                class="post-image"
                                data-text="<?= htmlspecialchars($post['contenido_texto']); ?>"
                                data-date="<?= htmlspecialchars($post['fecha_publicacion']); ?>"
                                data-likes="<?= $totalLikes ?>"
                                data-comments="<?= $totalComentarios ?>">
                        <?php endif; ?>
                        <p><?= nl2br(htmlspecialchars($post['contenido_texto'])); ?></p>
                        <p><?= htmlspecialchars($post['fecha_publicacion']); ?></p>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
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