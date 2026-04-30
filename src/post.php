<?php
session_start();
require 'includes/db.php';
require 'includes/lib.php';
require_once 'clases/Post.php';

if (!isset($_SESSION['usuario'])) {
    header("Location: actions/login.php");
    exit();
}

$id_usuario_sesion = (int)$_SESSION['id_usuario'];
$postModel = new Post($pdo);

$id_post = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if (!$id_post) {
    header("Location: index.php");
    exit();
}

$stmt = $pdo->prepare("
    SELECT p.id_publicacion, p.contenido_texto, p.fecha_publicacion, p.media,
           u.id_usuario AS autor_id, u.username, u.foto_perfil,
           (SELECT COUNT(*) FROM likes WHERE id_publicacion = p.id_publicacion) AS totalLikes,
           (SELECT COUNT(*) FROM comentarios WHERE id_publicacion = p.id_publicacion
                AND (id_padre IS NULL OR id_padre = 0)) AS totalComentarios
    FROM publicaciones p
    JOIN usuarios u ON p.id_usuario = u.id_usuario
    WHERE p.id_publicacion = ?
");
$stmt->execute([$id_post]);
$post = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$post) {
    header("Location: index.php");
    exit();
}

$publicaciones = [$post];
$skip_post_card_js = false;
?>
<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="UTF-8">
    <title>Publicación de @<?= htmlspecialchars($post['username']) ?> · 8Mangos</title>
    <link rel="stylesheet" href="assets/css/style.css">
    <link rel="shortcut icon" href="assets/images/8mangos.png">
    <style>
        .post-single-wrapper {
            max-width: 640px;
            margin: 0 auto;
            padding: 24px 16px;
        }

        .post-single-back {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            color: var(--text-low);
            text-decoration: none;
            font-size: 14px;
            margin-bottom: 18px;
            padding: 6px 12px;
            border-radius: 8px;
            transition: background .15s, color .15s;
        }

        .post-single-back:hover {
            background: var(--bg-card);
            color: var(--texto-general);
        }

        .post-single-back::before {
            content: '←';
            font-size: 16px;
        }

        /* Abrir sección de comentarios directamente */
        .comments-section {
            display: block !important;
        }
    </style>
</head>

<body>
    <main>
        <?php include('includes/WIP_aside.php'); ?>

        <div class="post-single-wrapper">
            <?php
            $back_url = isset($_SERVER['HTTP_REFERER']) ? htmlspecialchars($_SERVER['HTTP_REFERER']) : 'index.php';
            ?>
            <a href="<?= $back_url ?>" class="post-single-back">Volver</a>

            <?php include('templates/post_card.php'); ?>
        </div>
    </main>

    <script src="assets/js/likes_index.js"></script>
    <script>
        // Mostrar comentarios automáticamente al cargar
        document.addEventListener('DOMContentLoaded', function() {
            const section = document.getElementById('comments-<?= $id_post ?>');
            if (section) {
                section.classList.add('open');
                cargarComentarios(<?= $id_post ?>);
            }
        });
    </script>
</body>

</html>