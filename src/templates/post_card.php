<?php
require_once('includes/db.php');
require_once('clases/Post.php');

$postModel = new Post($pdo);
$id_usuario_sesion = $_SESSION['id_usuario'] ?? 0;

// Consulta única: Traemos posts, autor y conteos (usamos alias autor_id para el enlace)
$sql = "SELECT p.*, 
               u.username, 
               u.foto_perfil, 
               u.id_usuario AS autor_id,
               (SELECT COUNT(*) FROM likes WHERE id_publicacion = p.id_publicacion) as totalLikes,
               (SELECT COUNT(*) FROM comentarios WHERE id_publicacion = p.id_publicacion) as totalComentarios
        FROM publicaciones p
        JOIN usuarios u ON p.id_usuario = u.id_usuario
        ORDER BY p.fecha_publicacion DESC";

$stmt = $pdo->query($sql);
$publicaciones = $stmt->fetchAll(PDO::FETCH_ASSOC);

foreach ($publicaciones as $post):
    $id_post = $post['id_publicacion'];
    $yaDioLike = ($id_usuario_sesion > 0) ? $postModel->haDadoLike($id_usuario_sesion, $id_post) : false;
?>
    <div class="post-card">
        <div class="post-header" style="display:flex; align-items:center;">
            <img src="data:image/jpeg;base64,<?= base64_encode($post['foto_perfil']) ?>" alt="Foto de perfil"
                width="30" height="30" style="margin-right:10px; border-radius:50%;">
            <h3 class="username" style="margin:0;">
                <a href="perfil.php?id=<?= $post['autor_id'] ?>"> @<?= htmlspecialchars($post['username']); ?></a>
            </h3>
            <div style="margin-left:auto; font-size:12px; color:#fff;">
                <?= htmlspecialchars($post['fecha_publicacion']); ?>
            </div>
        </div>

        <div class="post-content" style="margin-top:10px;">
            <?php if (!empty($post['media'])): ?>
                <img src="data:image/jpeg;base64,<?= base64_encode($post['media']); ?>" alt="imagen del post" width="400" height="400" style="margin:auto">
            <?php endif; ?>
            <p><?= nl2br(htmlspecialchars($post['contenido_texto'])); ?></p>
        </div>

        <div class="post-footer">
            <div class="boton">
                <button class="btn-like-ajax" data-id="<?= $id_post ?>" style="background:none; border:none; cursor:pointer;">
                    <span class="icon" style="font-size: 16px;"><?= $yaDioLike ? '❤️' : '🤍' ?></span>
                </button>
                <strong class="count"><?= $post['totalLikes'] ?></strong>
            </div>

            <div class="boton">
                <button class="comentar"> 💬 </button>
                <strong><?= $post['totalComentarios'] ?></strong>
            </div>
        </div>
    </div>
<?php endforeach; ?>