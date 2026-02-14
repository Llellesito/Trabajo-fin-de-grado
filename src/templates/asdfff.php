<?php
require_once('includes/db.php');
require_once('clases/Post.php');

$postModel = new Post($pdo);
$id_usuario_sesion = $_SESSION['id_usuario']; // Asegúrate de usar el ID de la sesión

// Optimizamos la carga de usuarios para no hacer mil consultas
$stmt = $pdo->query("SELECT id_usuario, username, nombre, foto_perfil FROM usuarios");
$usuarios = $stmt->fetchAll(PDO::FETCH_UNIQUE | PDO::FETCH_ASSOC);

// Obtenemos publicaciones
$stmt = $pdo->query("SELECT * FROM publicaciones ORDER BY fecha_publicacion DESC");
$publicaciones = $stmt->fetchAll(PDO::FETCH_ASSOC);

foreach ($publicaciones as $post):
    $user = $usuarios[$post['id_usuario']] ?? null;
    if (!$user) continue;

    $id_post = $post['id_publicacion'];
    $yaDioLike = $postModel->haDadoLike($id_usuario_sesion, $id_post);

    // Conteo de likes
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM likes WHERE id_publicacion = ?");
    $stmt->execute([$id_post]);
    $totalLikes = $stmt->fetchColumn();

    // Conteo de comentarios
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM comentarios WHERE id_publicacion = ?");
    $stmt->execute([$id_post]);
    $totalComentarios = $stmt->fetchColumn();
?>
    <div class="post-card" data-post-id="<?= $id_post ?>">
        <div class="post-header" style="display:flex; align-items:center;">
            <img src="data:image/jpeg;base64,<?= base64_encode($user['foto_perfil']) ?>"
                width="30" height="30" style="margin-right:10px; border-radius:50%;">
            <h3 class="username" style="margin:0;">
                <a href="perfil.php?id=<?= $user['id_usuario'] ?>"> @<?= htmlspecialchars($user['username']); ?></a>
            </h3>
            <div style="margin-left:auto; font-size:12px; color:#aaa;">
                <?= htmlspecialchars($post['fecha_publicacion']); ?>
            </div>
        </div>

        <div class="post-content" style="margin-top:10px; text-align:center;">
            <?php if (!empty($post['media'])): ?>
                <img src="data:image/jpeg;base64,<?= base64_encode($post['media']); ?>"
                    alt="imagen del post" style="max-width:100%; height:auto; border-radius:8px;">
            <?php endif; ?>
            <p style="text-align:left;"><?= nl2br(htmlspecialchars($post['contenido_texto'])); ?></p>
        </div>

        <div class="post-footer">
            <div class="boton">
                <button class="btn-like-ajax" data-id="<?= $id_post ?>" style="background:none; border:none; cursor:pointer; font-size:1.2rem;">
                    <span class="icon"><?= $yaDioLike ? '❤️' : '🤍' ?></span>
                    <span class="count"><?= $totalLikes ?></span>
                </button>
            </div>

            <div class="boton">
                <button class="comentar" style="background:none; border:none; cursor:pointer; font-size:1.2rem;">
                    💬 <span class="count"><?= $totalComentarios ?></span>
                </button>
            </div>
        </div>
    </div>
<?php endforeach; ?>