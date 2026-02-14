<?php
session_start();
require 'includes/db.php';
require 'includes/lib.php';
require_once 'clases/Post.php'; // Asegúrate de incluir tu clase Post

if (!isset($_SESSION['id_usuario'])) {
    header("Location: login.php");
    exit();
}

$id_usuario_sesion = $_SESSION['id_usuario'];
$id_usuario = $id_usuario_sesion; // Puedes cambiar esto si vas a ver perfiles ajenos

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
            <span class="close">&times;</span>

            <div class="modal-body">
                <img id="modal-img" class="modal-img" alt="Publicación" style="width:100%; display:block;">
            </div>

            <div class="modal-footer" style="padding: 15px;">
                <div class="post-footer" style="display:flex; gap: 20px; align-items: center; margin-bottom: 15px;">
                    <div class="boton">
                        <button id="modal-like-btn" class="btn-like-ajax btn-action" data-id="" style="background:none; border:none; cursor:pointer; font-size:1.2rem;">
                            <span class="icon" id="like-icon">🤍</span>
                        </button>
                        <strong id="like-count" class="count">0</strong>
                    </div>

                    <div class="boton">
                        <button class="comentar" style="background:none; border:none; cursor:pointer; font-size:1.2rem;">
                            💬 <strong>0</strong>
                        </button>
                    </div>
                </div>

                <div class="modal-desc">
                    <p style="margin:0;"><strong>@<?= htmlspecialchars($user['username']) ?></strong> <span id="modal-text"></span></p>
                    <small id="modal-date" class="modal-date" style="color: #888; font-size: 12px;"></small>
                </div>
            </div>
        </div>
    </div>

    <main>
        <?php include('includes/WIP_aside.php') ?>

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
                ?>
                    <div class="post-card">
                        <div class="post-content">

                            <?php if (!empty($post['media'])): ?>
                                <img src="data:image/jpeg;base64,<?= base64_encode($post['media']); ?>"
                                    class="post-image"
                                    data-id="<?= $post['id_publicacion'] ?>"
                                    data-text="<?= htmlspecialchars($post['contenido_texto']); ?>"
                                    data-date="<?= htmlspecialchars($post['fecha_publicacion']); ?>"
                                    data-likes="<?= $likesCount ?>"
                                    data-user-liked="<?= $yaTieneLike ? '1' : '0' ?>">
                            <?php endif; ?>
                            <!-- --- Contenido del texto de las publicaciones de los posts --- -->
                            <p><?= nl2br(htmlspecialchars($post['contenido_texto'])); ?></p>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
    </main>

    <script src="assets/js/perfil_modal.js"></script>
</body>

</html>