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

// Garantiza que las columnas de sanciones existen
ensureSanctionColumns($pdo);


// ── 1. Posts de gente que sigo (excluye shadowbaneados) ──────────────────────
$stmt = $pdo->prepare("
    SELECT p.id_publicacion, p.contenido_texto, p.fecha_publicacion, p.media,
           u.id_usuario AS autor_id, u.username, u.foto_perfil,
           (SELECT COUNT(*) FROM likes WHERE id_publicacion = p.id_publicacion) AS totalLikes,
           (SELECT COUNT(*) FROM comentarios WHERE id_publicacion = p.id_publicacion
                AND (id_padre IS NULL OR id_padre = 0)) AS totalComentarios
    FROM publicaciones p
    JOIN usuarios u ON p.id_usuario = u.id_usuario
    JOIN seguidores s ON s.id_seguido = p.id_usuario
    WHERE s.id_seguidor = ?
      AND (u.shadowban IS NULL OR u.shadowban = 0)
    ORDER BY p.fecha_publicacion DESC
    LIMIT 30
");
$stmt->execute([$id_usuario_sesion]);
$posts_seguidos = $stmt->fetchAll(PDO::FETCH_ASSOC);

// ── 2. Sugerencias (gente que NO sigo, excluyendo shadowbaneados) ────────────
$stmt = $pdo->prepare("
    SELECT p.id_publicacion, p.contenido_texto, p.fecha_publicacion, p.media,
           u.id_usuario AS autor_id, u.username, u.foto_perfil,
           (SELECT COUNT(*) FROM likes WHERE id_publicacion = p.id_publicacion) AS totalLikes,
           (SELECT COUNT(*) FROM comentarios WHERE id_publicacion = p.id_publicacion
                AND (id_padre IS NULL OR id_padre = 0)) AS totalComentarios
    FROM publicaciones p
    JOIN usuarios u ON p.id_usuario = u.id_usuario
    WHERE p.id_usuario != ?
      AND p.id_usuario NOT IN (
          SELECT id_seguido FROM seguidores WHERE id_seguidor = ?
      )
      AND (u.shadowban IS NULL OR u.shadowban = 0)
    ORDER BY totalLikes DESC, p.fecha_publicacion DESC
    LIMIT 20
");
$stmt->execute([$id_usuario_sesion, $id_usuario_sesion]);
$posts_sugeridos = $stmt->fetchAll(PDO::FETCH_ASSOC);

// IDs ya mostrados en el feed (para evitar duplicados en sugerencias)
$ids_seguidos = array_column($posts_seguidos, 'id_publicacion');
$posts_sugeridos = array_filter($posts_sugeridos, fn($p) => !in_array($p['id_publicacion'], $ids_seguidos));
?>

<?php
// ── Mezcla: una sugerencia cada 4 posts del feed ─────────────────────────────
$feed_mezclado = [];
$sugeridos     = array_values($posts_sugeridos);
$s_idx         = 0;
$cadencia      = 4;

foreach ($posts_seguidos as $i => $post) {
    $feed_mezclado[] = ['post' => $post, 'sugerencia' => false];
    if ((($i + 1) % $cadencia === 0) && isset($sugeridos[$s_idx])) {
        $feed_mezclado[] = ['post' => $sugeridos[$s_idx], 'sugerencia' => true];
        $s_idx++;
    }
}

if (empty($posts_seguidos)) {
    foreach ($sugeridos as $post) {
        $feed_mezclado[] = ['post' => $post, 'sugerencia' => true];
    }
}
?>
<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="UTF-8">
    <title>8Mangos</title>
    <link rel="stylesheet" href="assets/css/style.css">
    <link rel="shortcut icon" href="assets/images/8mangos.png">
    <style>
        .post-card:has(.badge-sugerencia) .post-header {
            flex-wrap: wrap;
        }

        .badge-sugerencia {
            width: 100%;
            font-size: 12px;
            color: var(--text-low);
            margin-bottom: 6px;
            display: flex;
            align-items: center;
            gap: 4px;
        }

        .badge-sugerencia::before {
            content: '';
            display: inline-block;
            width: 14px;
            height: 14px;
            background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 24 24' fill='%2371767c'%3E%3Cpath d='M12 1.5C6.2 1.5 1.5 6.2 1.5 12S6.2 22.5 12 22.5 22.5 17.8 22.5 12 17.8 1.5 12 1.5zm0 4c.8 0 1.5.7 1.5 1.5S12.8 8.5 12 8.5 10.5 7.8 10.5 7s.7-1.5 1.5-1.5zm2 9.5h-1v-5h-2v1h1v4H11v1h3v-1z'/%3E%3C/svg%3E");
            background-repeat: no-repeat;
            background-size: contain;
            flex-shrink: 0;
        }

        .feed-empty {
            text-align: center;
            color: var(--text-low);
            padding: 40px 20px;
            font-size: 14px;
            max-width: 430px;
            margin: 0 auto;
        }

        .feed-empty .empty-icon {
            font-size: 36px;
            margin-bottom: 8px;
        }

        .feed-empty a {
            color: var(--magenta-glow-claro);
            text-decoration: underline;
        }
    </style>
</head>

<body>
    <main>
        <?php include('includes/WIP_aside.php') ?>

        <div class="posts">
            <?php include('includes/WIP_header.php') ?>


            <?php if (empty($feed_mezclado)): ?>
                <div class="feed-empty">
                    <div class="empty-icon">🌴</div>
                    <p>Aún no hay nada por aquí.<br>
                        <a href="buscador.php">Busca usuarios</a> para seguirlos.
                    </p>
                </div>
            <?php else: ?>
                <?php foreach ($feed_mezclado as $item):
                    $publicaciones     = [$item['post']];
                    $es_sugerencia     = $item['sugerencia'];
                    $skip_post_card_js = true;
                    include 'templates/post_card.php';
                endforeach;
                unset($publicaciones, $es_sugerencia, $skip_post_card_js);
                ?>
            <?php endif; ?>

        </div>
    </main>

    <script src="assets/js/likes_index.js"></script>

    <?php
    $publicaciones     = [];
    $skip_post_card_js = false;
    include 'templates/post_card.php';
    unset($publicaciones, $skip_post_card_js);
    ?>
</body>

</html>