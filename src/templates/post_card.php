<style>
    .post-options-container {
        position: relative;
    }

    .btn-options {
        background: none;
        border: none;
        color: white;
        font-size: 20px;
        cursor: pointer;
        padding: 0 5px;
    }

    .options-menu {
        display: none;
        /* Oculto por defecto */
        position: absolute;
        right: 0;
        top: 25px;
        background-color: #333;
        border: 1px solid #444;
        border-radius: 5px;
        z-index: 100;
        min-width: 120px;
        box-shadow: 0px 4px 8px rgba(0, 0, 0, 0.5);
    }

    .options-menu a {
        display: block;
        color: white;
        padding: 10px;
        text-decoration: none;
        font-size: 14px;
    }

    .options-menu a:hover {
        background-color: #444;
    }

    .options-menu a.delete-link {
        color: #ff4d4d;
    }

    .show {
        display: block !important;
    }
</style>

<?php
require_once('includes/db.php');
require_once('clases/Post.php');

$postModel = new Post($pdo);
$id_usuario_sesion = $_SESSION['id_usuario'] ?? 0;

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
        <div class="post-header" style="display:flex; align-items:center; position: relative;">
            <img src="data:image/jpeg;base64,<?= base64_encode($post['foto_perfil']) ?>" alt="Foto de perfil"
                width="30" height="30" style="margin-right:10px; border-radius:50%;">

            <h3 class="username" style="margin:0;">
                <a href="perfil.php?id=<?= $post['autor_id'] ?>"> @<?= htmlspecialchars($post['username']); ?></a>
            </h3>

            <div style="margin-left:auto; display:flex; align-items:center; gap:10px;">
                <span style="font-size:12px; color:#aaa;"><?= htmlspecialchars($post['fecha_publicacion']); ?></span>

                <?php if ($id_usuario_sesion == $post['autor_id']): ?>
                    <div class="post-options-container">
                        <button class="btn-options" onclick="toggleMenu(<?= $id_post ?>)">⋮</button>
                        <div id="menu-<?= $id_post ?>" class="options-menu">
                            <a href="actions/editar_post.php?id=<?= $id_post ?>">✏️ Editar</a>
                            <a href="actions/borrar_post.php?id=<?= $id_post ?>" class="delete-link" onclick="return confirm('¿Estás seguro de borrar este post?')">🗑️ Borrar</a>
                        </div>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <div class="post-content" style="margin-top:10px;">
            <?php if (!empty($post['media'])): ?>
                <img src="data:image/jpeg;base64,<?= base64_encode($post['media']); ?>" alt="imagen del post" width="400" height="400" style="margin:auto; display:block;">
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

<script>
    function toggleMenu(postId) {
        // Evitar que el clic en el botón se propague al window
        event.stopPropagation();

        const menu = document.getElementById('menu-' + postId);

        // Cerrar todos los demás menús antes de abrir este
        document.querySelectorAll('.options-menu').forEach(m => {
            if (m.id !== 'menu-' + postId) {
                m.classList.remove('show');
            }
        });

        // Alternar el menú actual
        menu.classList.toggle('show');
    }

    // Cerrar el menú si se hace clic fuera de él
    window.onclick = function(event) {
        // Si el clic NO ocurrió dentro de un botón de opciones ni dentro del menú mismo
        if (!event.target.closest('.btn-options') && !event.target.closest('.options-menu')) {
            document.querySelectorAll('.options-menu').forEach(menu => {
                menu.classList.remove('show');
            });
        }
    }
</script>