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
        <div class="post-header">
            <img src="data:image/jpeg;base64,<?= base64_encode($post['foto_perfil']) ?>" alt="Foto de perfil"
                class="post-avatar">

            <h3 class="username">
                <a href="perfil.php?id=<?= $post['autor_id'] ?>"> @<?= htmlspecialchars($post['username']); ?></a>
            </h3>

            <div class="post-header-right">
                <span class="post-date"><?= htmlspecialchars($post['fecha_publicacion']); ?></span>

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

        <div class="post-content">
            <?php if (!empty($post['media'])): ?>
                <img src="data:image/jpeg;base64,<?= base64_encode($post['media']); ?>" alt="imagen del post" class="post-media">
            <?php endif; ?>
            <p><?= nl2br(htmlspecialchars($post['contenido_texto'])); ?></p>
        </div>

        <div class="post-footer">
            <div class="boton">
                <button class="btn-like-ajax" data-id="<?= $id_post ?>">
                    <span class="icon"><?= $yaDioLike ? '❤️' : '🤍' ?></span>
                </button>
                <strong class="count"><?= $post['totalLikes'] ?></strong>
            </div>
            <div class="boton">
                <button class="btn-toggle-comments comentar" data-id="<?= $id_post ?>"> 💬 </button>
                <strong class="comment-count-<?= $id_post ?>"><?= $post['totalComentarios'] ?></strong>
            </div>
        </div>

        <!-- Sección de comentarios -->
        <div class="comments-section" id="comments-<?= $id_post ?>">
            <div class="comments-list" id="comments-list-<?= $id_post ?>"></div>
            <div class="comment-form">
                <input type="text"
                    id="comment-input-<?= $id_post ?>"
                    class="comment-input"
                    placeholder="Escribe un comentario..."
                    maxlength="500">
                <button class="btn-send-comment" data-id="<?= $id_post ?>">Enviar</button>
            </div>
        </div>
    </div>
<?php endforeach; ?>

<script>
    const SESSION_USER_ID = <?= $id_usuario_sesion ?>;

    document.querySelectorAll('.btn-toggle-comments').forEach(btn => {
        btn.addEventListener('click', function() {
            const postId = this.dataset.id;
            const section = document.getElementById('comments-' + postId);
            const isHidden = !section.classList.contains('open');
            section.classList.toggle('open', isHidden);
            if (isHidden) cargarComentarios(postId);
        });
    });

    document.querySelectorAll('.btn-send-comment').forEach(btn => {
        btn.addEventListener('click', function() {
            const postId = this.dataset.id;
            const input = document.getElementById('comment-input-' + postId);
            const contenido = input.value.trim();
            if (!contenido) return;

            const formData = new FormData();
            formData.append('accion', 'agregar');
            formData.append('id_publicacion', postId);
            formData.append('contenido', contenido);

            fetch('actions/comment_action.php', {
                    method: 'POST',
                    body: formData
                })
                .then(r => r.json())
                .then(data => {
                    if (data.status === 'success') {
                        input.value = '';
                        renderComentarios(postId, data.comentarios);
                        document.querySelector('.comment-count-' + postId).textContent = data.totalComentarios;
                    }
                });
        });
    });

    document.querySelectorAll('[id^="comment-input-"]').forEach(input => {
        input.addEventListener('keydown', function(e) {
            if (e.key === 'Enter') {
                const postId = this.id.replace('comment-input-', '');
                document.querySelector('.btn-send-comment[data-id="' + postId + '"]').click();
            }
        });
    });

    function cargarComentarios(postId) {
        fetch('actions/comment_action.php?accion=obtener&id_publicacion=' + postId)
            .then(r => r.json())
            .then(data => {
                if (data.status === 'success') renderComentarios(postId, data.comentarios);
            });
    }

    function renderComentarios(postId, comentarios) {
        const lista = document.getElementById('comments-list-' + postId);
        lista.innerHTML = '';

        if (comentarios.length === 0) {
            lista.innerHTML = '<p class="comments-empty">Sé el primero en comentar 💬</p>';
            return;
        }

        comentarios.forEach(c => {
            const div = document.createElement('div');
            div.className = 'comment-item';

            const avatarSrc = c.foto_perfil ?
                'data:image/jpeg;base64,' + c.foto_perfil :
                'https://ui-avatars.com/api/?name=' + encodeURIComponent(c.username) + '&background=555&color=fff';

            const deleteBtn = (parseInt(c.id_usuario) === SESSION_USER_ID) ?
                `<button class="btn-delete-comment" data-comentario="${c.id_comentario}" data-publicacion="${postId}" title="Borrar">🗑️</button>` :
                '';

            div.innerHTML = `
            <img src="${avatarSrc}" class="comment-avatar" alt="${c.username}">
            <div class="comment-body">
                <div class="comment-username">@${c.username}</div>
                <div class="comment-text">${escapeHtml(c.contenido)}</div>
                <div class="comment-date">${c.fecha_comentario}</div>
            </div>
            ${deleteBtn}
        `;
            lista.appendChild(div);
        });

        lista.querySelectorAll('.btn-delete-comment').forEach(btn => {
            btn.addEventListener('click', function() {
                if (!confirm('¿Borrar este comentario?')) return;
                const formData = new FormData();
                formData.append('accion', 'borrar');
                formData.append('id_comentario', this.dataset.comentario);
                formData.append('id_publicacion', this.dataset.publicacion);

                fetch('actions/comment_action.php', {
                        method: 'POST',
                        body: formData
                    })
                    .then(r => r.json())
                    .then(data => {
                        if (data.status === 'success') {
                            cargarComentarios(postId);
                            document.querySelector('.comment-count-' + postId).textContent = data.totalComentarios;
                        }
                    });
            });
        });
    }

    function escapeHtml(text) {
        const div = document.createElement('div');
        div.appendChild(document.createTextNode(text));
        return div.innerHTML;
    }

    function toggleMenu(postId) {
        event.stopPropagation();
        const menu = document.getElementById('menu-' + postId);
        document.querySelectorAll('.options-menu').forEach(m => {
            if (m.id !== 'menu-' + postId) m.classList.remove('show');
        });
        menu.classList.toggle('show');
    }

    window.addEventListener("click", function(event) {
        if (!event.target.closest('.btn-options') && !event.target.closest('.options-menu')) {
            document.querySelectorAll('.options-menu').forEach(menu => menu.classList.remove('show'));
        }
    })
</script>