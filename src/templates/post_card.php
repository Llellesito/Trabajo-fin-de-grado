<?php
require('includes/db.php');

// Obtener usuarios
$stmt = $pdo->query("SELECT id_usuario, username, nombre, bio, foto_perfil FROM usuarios");
$usuarios = [];
while ($u = $stmt->fetch(PDO::FETCH_ASSOC)) {
    $usuarios[$u['id_usuario']] = $u; // Guardamos por id_usuario para acceso rápido
}


// Obtener publicaciones
$tablaPublicacion = $pdo->query("SELECT id_publicacion, id_usuario, media, contenido_texto, fecha_publicacion, privacidad FROM publicaciones");
$publicaciones = $tablaPublicacion->fetchAll(PDO::FETCH_ASSOC);

?>


<?php foreach ($publicaciones as $post):
    $user = $usuarios[$post['id_usuario']] ?? null;
    if (!$user) continue;

    // Numero de likes
    $stmt = $pdo->prepare("SELECT COUNT(*) AS total_likes FROM likes WHERE id_publicacion = ?");
    $stmt->execute([$post['id_publicacion']]);
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    $totalLikes = $result['total_likes'];

    // Numero de comentarios
    $stmt = $pdo->prepare("SELECT COUNT(*) AS total_comentarios FROM comentarios WHERE id_publicacion = ?");
    $stmt->execute([$post['id_publicacion']]);
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    $totalComentarios = $result['total_comentarios'];
?>
    <div class="post-card">
        <div class="post-header" style="display:flex; align-items:center;">
            <img src="data:image/jpeg;base64,<?= base64_encode($user['foto_perfil']) ?>" alt="Foto de perfil"
                width="30" height="30" style="margin-right:10px; border-radius:50%;">
            <h3 class="username" style="margin:0;">
                <a href="perfil.php?id=<?= $user['id_usuario'] ?>"> @<?= htmlspecialchars($user['username']); ?></a>
            </h3>
            <div style="margin-left:auto; font-size:12px; color:#555;">
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
            <button class="btn like-btn"><?= $totalLikes ?> ❤️ </button>
            <button class="btn comment-btn"><?= $totalComentarios ?> 💬 </button>
        </div>
    </div>
<?php endforeach; ?>