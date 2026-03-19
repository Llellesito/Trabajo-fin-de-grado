<?php
session_start();
require 'includes/db.php';
require 'includes/lib.php';
require_once 'clases/Post.php';

if (!isset($_SESSION['id_usuario'])) {
    header("Location: login.php");
    exit();
}

$id_usuario_sesion = $_SESSION['id_usuario'];

if (isset($_GET['id']) && !empty($_GET['id'])) {
    $id_usuario = (int)$_GET['id'];
} else {
    $id_usuario = $id_usuario_sesion;
}
// Datos del usuario
$stmt = $pdo->prepare("SELECT id_usuario, username, nombre, bio, foto_perfil FROM usuarios WHERE id_usuario = ?");
$stmt->execute([$id_usuario]);
$user = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$user) {
    echo "Usuario no encontrado";
    exit;
}

$postModel = new Post($pdo);

// Publicaciones
$stmt = $pdo->prepare("SELECT id_publicacion, media, contenido_texto, fecha_publicacion FROM publicaciones WHERE id_usuario = ? ORDER BY fecha_publicacion DESC");
$stmt->execute([$id_usuario]);
$publicaciones = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Contadores
$numPublicaciones = count($publicaciones);
$totalSeguidores = $pdo->query("SELECT COUNT(*) FROM seguidores WHERE id_seguido = $id_usuario")->fetchColumn();
$totalSeguidos = $pdo->query("SELECT COUNT(*) FROM seguidores WHERE id_seguidor = $id_usuario")->fetchColumn();
?>


<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="UTF-8">
    <title>@<?= htmlspecialchars($user['nombre']) ?> - 8Mangos</title>
    <link rel="stylesheet" href="assets/css/perfil.css">
    <link rel="shortcut icon" href="assets/images/8mangos.png">
</head>

<body>
    <!-- Modal -->
    <div id="postModal" class="modal">
        <div class="modal-content">
            <div class="post-header">
                <img id="modal-avatar" src="" class="post-avatar" alt="avatar">
                <h3 class="username">
                    <a id="modal-username-link" href="#">@<span id="modal-username"></span></a>
                </h3>
                <div class="post-header-right">
                    <span id="modal-date" class="post-date"></span>
                    <button class="modal-close btn-options">✕</button>
                </div>
            </div>
            <div class="post-content">
                <img id="modal-img" class="post-media" alt="Publicación" style="display:none;">
                <p id="modal-text" class="modal-text-content"></p>
            </div>
            <div class="post-footer">
                <div class="boton">
                    <button id="modal-like-btn" class="btn-like-ajax" data-id="">
                        <span class="icon">🤍</span>
                    </button>
                    <strong class="count">0</strong>
                </div>
                <div class="boton">
                    <button class="btn-toggle-comments comentar" id="modal-comment-btn" data-id="">💬</button>
                    <strong class="comment-count-modal">0</strong>
                </div>
            </div>
            <div class="comments-section" id="modal-comments-section">
                <div class="comments-list" id="modal-comments-list"></div>
                <div class="comment-form">
                    <input type="text" id="modal-comment-input" class="comment-input"
                        placeholder="Escribe un comentario..." maxlength="500">
                    <button class="btn-send-comment" id="modal-send-comment">Enviar</button>
                </div>
            </div>
        </div>
    </div>

    <main>

        <?php include('includes/WIP_aside.php') ?>

        <div class="contenido">

            <div class="perfil">
                <img src="<?= avatarSrc($user['foto_perfil'], $user['username']) ?>" alt="Foto de perfil" width="150" height="150" style="border-radius:50%;">
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

            <?php echo renderizarBotonPerfil($user['id_usuario'], $pdo); ?>

            <h2>Publicaciones</h2>

            <hr>

            <div class="publicaciones">
                <?php foreach ($publicaciones as $post):
                    // Verificamos si el usuario logueado ya dio like a esta foto
                    $yaTieneLike = $postModel->haDadoLike($id_usuario_sesion, $post['id_publicacion']);

                    // Contamos likes
                    $stmtLikes = $pdo->prepare("SELECT COUNT(*) FROM likes WHERE id_publicacion = ?");
                    $stmtLikes->execute([$post['id_publicacion']]);
                    $likesCount = $stmtLikes->fetchColumn();

                    $stmtComments = $pdo->prepare("SELECT COUNT(*) FROM comentarios WHERE id_publicacion = ? AND (id_padre IS NULL OR id_padre = 0)");
                    $stmtComments->execute([$post['id_publicacion']]);
                    $commentsCount = $stmtComments->fetchColumn();
                ?>
                    <div class="post-card <?= empty($post['media']) ? 'text-only' : '' ?>">
                        <?php if (!empty($post['media'])): ?>
                            <img src="data:image/jpeg;base64,<?= base64_encode($post['media']); ?>"
                                class="post-image"
                                data-id="<?= $post['id_publicacion'] ?>"
                                data-text="<?= htmlspecialchars($post['contenido_texto']); ?>"
                                data-date="<?= htmlspecialchars($post['fecha_publicacion']); ?>"
                                data-likes="<?= $likesCount ?>"
                                data-comments="<?= $commentsCount ?>"
                                data-user-liked="<?= $yaTieneLike ? '1' : '0' ?>">
                            <?php if (!empty($post['contenido_texto'])): ?>
                                <div class="post-overlay">
                                    <div class="ov-text"><?= htmlspecialchars($post['contenido_texto']) ?></div>
                                </div>
                            <?php endif; ?>
                        <?php else: ?>
                            <div class="post-text-content"
                                data-id="<?= $post['id_publicacion'] ?>"
                                data-text="<?= htmlspecialchars($post['contenido_texto']); ?>"
                                data-date="<?= htmlspecialchars($post['fecha_publicacion']); ?>"
                                data-likes="<?= $likesCount ?>"
                                data-comments="<?= $commentsCount ?>"
                                data-user-liked="<?= $yaTieneLike ? '1' : '0' ?>"
                                style="cursor:pointer;width:100%;height:100%;display:flex;align-items:center;justify-content:center;padding:14px;box-sizing:border-box;">
                                <?= nl2br(htmlspecialchars($post['contenido_texto'])) ?>
                            </div>
                        <?php endif; ?>
                    </div>
                <?php endforeach; ?>
            </div>

    </main>

    <script>
        window.SESSION_USER_ID = <?= $id_usuario_sesion ?>;
        window.PROFILE_AVATAR = <?= json_encode(avatarSrc($user['foto_perfil'], $user['username'])) ?>;
        window.PROFILE_USERNAME = <?= json_encode($user['username']) ?>;
        window.PROFILE_USER_ID = <?= $user['id_usuario'] ?>;
    </script>
    <script src="assets/js/perfil_modal.js"></script>
</body>

</html>