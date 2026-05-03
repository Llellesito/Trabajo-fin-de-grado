<?php

/**
 * post_card.php
 * Si $publicaciones ya está definido desde fuera, lo usa directamente.
 * Si no, hace su propia query (compatibilidad con páginas antiguas).
 */
require_once('includes/db.php');
require_once('clases/Post.php');
require_once('includes/lib.php');

$postModel = new Post($pdo);
$id_usuario_sesion = $_SESSION['id_usuario'] ?? 0;

if (!isset($publicaciones)) {
    $sql = "SELECT p.*,
                   u.username,
                   u.foto_perfil,
                   u.id_usuario AS autor_id,
                   (SELECT COUNT(*) FROM likes WHERE id_publicacion = p.id_publicacion) as totalLikes,
                   (SELECT COUNT(*) FROM comentarios WHERE id_publicacion = p.id_publicacion AND (id_padre IS NULL OR id_padre = 0)) as totalComentarios
            FROM publicaciones p
            JOIN usuarios u ON p.id_usuario = u.id_usuario
            ORDER BY p.fecha_publicacion DESC";
    $stmt = $pdo->query($sql);
    $publicaciones = $stmt->fetchAll(PDO::FETCH_ASSOC);
}

foreach ($publicaciones as $post):
    $id_post = $post['id_publicacion'];
    $yaDioLike = ($id_usuario_sesion > 0) ? $postModel->haDadoLike($id_usuario_sesion, $id_post) : false;
?>
    <div class="post-card">
        <div class="post-header">
            <?php if (!empty($es_sugerencia)): ?>
                <span class="badge-sugerencia">· Sugerencia</span>
            <?php endif; ?>
            <img src="<?= avatarSrc($post['foto_perfil'], $post['username']) ?>" alt="Foto de perfil"
                class="post-avatar">
            <h3 class="username">
                <a href="perfil.php?id=<?= $post['autor_id'] ?>"> @<?= htmlspecialchars($post['username']); ?></a>
            </h3>
            <div class="post-header-right">
                <span class="post-date"><?= htmlspecialchars($post['fecha_publicacion']); ?></span>
                <div class="post-options-container">
                    <button class="btn-options" onclick="toggleMenu(<?= $id_post ?>)">⋮</button>
                    <div id="menu-<?= $id_post ?>" class="options-menu">
                        <?php if ($id_usuario_sesion == $post['autor_id']): ?>
                            <a href="actions/editar_post.php?id=<?= $id_post ?>">✏️ Editar</a>
                            <a href="actions/borrar_post.php?id=<?= $id_post ?>" class="delete-link" onclick="return confirm('¿Estás seguro de borrar este post?')">🗑️ Borrar</a>
                        <?php else: ?>
                            <button class="btn-report-post" data-id="<?= $id_post ?>">🚩 Reportar</button>
                        <?php endif; ?>
                    </div>
                </div>
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

<?php if (empty($skip_post_card_js)): ?>
    <script>
        window.SESSION_USER_ID = <?= $id_usuario_sesion ?>;
        const SESSION_USER_ID = window.SESSION_USER_ID;

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
                enviarComentario(postId, null);
            });
        });

        document.querySelectorAll('[id^="comment-input-"]').forEach(input => {
            input.addEventListener('keydown', function(e) {
                if (e.key === 'Enter') {
                    const postId = this.id.replace('comment-input-', '');
                    enviarComentario(postId, null);
                }
            });
        });

        function enviarComentario(postId, idPadre) {
            const inputId = idPadre ? 'reply-input-' + idPadre : 'comment-input-' + postId;
            const input = document.getElementById(inputId);
            const contenido = input.value.trim();
            if (!contenido) return;

            const formData = new FormData();
            formData.append('accion', 'agregar');
            formData.append('id_publicacion', postId);
            formData.append('contenido', contenido);
            if (idPadre) formData.append('id_padre', idPadre);

            fetch('actions/comment_action.php', {
                    method: 'POST',
                    body: formData
                })
                .then(r => r.json())
                .then(data => {
                    if (data.status === 'success') {
                        input.value = '';
                        if (idPadre) {
                            const replyBox = document.getElementById('reply-box-' + idPadre);
                            if (replyBox) replyBox.remove();
                        }
                        renderComentarios(postId, data.comentarios);
                        const countEl = document.querySelector('.comment-count-' + postId);
                        if (countEl) countEl.textContent = data.totalComentarios;
                    }
                });
        }

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
                lista.appendChild(buildComment(c, postId, false));
                if (c.respuestas && c.respuestas.length > 0) {
                    const repliesWrap = document.createElement('div');
                    repliesWrap.className = 'replies-list';
                    c.respuestas.forEach(r => repliesWrap.appendChild(buildComment(r, postId, true)));
                    lista.appendChild(repliesWrap);
                }
            });
        }

        function buildComment(c, postId, esRespuesta) {
            const div = document.createElement('div');
            div.className = esRespuesta ? 'comment-item comment-reply' : 'comment-item';

            const avatarSrc = c.foto_perfil ?
                'data:image/jpeg;base64,' + c.foto_perfil :
                'https://ui-avatars.com/api/?name=' + encodeURIComponent(c.username) + '&background=444&color=fff';

            const liked = c.yaDioLike == 1 || c.yaDioLike === true;
            const deleteBtn = (parseInt(c.id_usuario) === (window.SESSION_USER_ID || 0)) ?
                `<div class="comment-options-container">
                     <button class="btn-comment-options" title="Opciones">⋮</button>
                     <div class="comment-options-menu">
                         <button class="btn-delete-comment" data-comentario="${c.id_comentario}" data-publicacion="${postId}">🗑️ Eliminar</button>
                         <button class="btn-cancel-comment-menu">Cancelar</button>
                     </div>
                   </div>` :
                `<div class="comment-options-container">
                     <button class="btn-comment-options" title="Opciones">⋮</button>
                     <div class="comment-options-menu">
                         <button class="btn-report-comment" data-comentario="${c.id_comentario}">🚩 Reportar</button>
                         <button class="btn-cancel-comment-menu">Cancelar</button>
                     </div>
                   </div>`;

            const replyBtn = !esRespuesta ?
                `<button class="btn-reply-comment" data-comentario="${c.id_comentario}" data-post="${postId}">↩ Responder</button>` :
                '';

            div.innerHTML = `
        <img src="${avatarSrc}" class="comment-avatar" alt="${escapeHtml(c.username)}">
        <div class="comment-body">
            <div class="comment-username"><a href="perfil.php?id=${c.id_usuario}">@${escapeHtml(c.username)}</a></div>
            <div class="comment-text">${escapeHtml(c.contenido)}</div>
            <div class="comment-actions">
                <button class="btn-like-comment ${liked ? 'liked' : ''}" data-comentario="${c.id_comentario}">
                    <span class="like-heart">${liked ? '❤️' : '🤍'}</span>
                    <span class="like-count-comment">${c.totalLikes ?? 0}</span>
                </button>
                ${replyBtn}
                <span class="comment-date">${c.fecha_comentario}</span>
            </div>
        </div>
        ${deleteBtn}`;

            div.querySelector('.btn-like-comment').addEventListener('click', function() {
                const idComentario = this.dataset.comentario;
                const formData = new FormData();
                formData.append('accion', 'like_comentario');
                formData.append('id_comentario', idComentario);
                fetch('actions/comment_action.php', {
                        method: 'POST',
                        body: formData
                    })
                    .then(r => r.json())
                    .then(data => {
                        if (data.status === 'success') {
                            this.querySelector('.like-heart').textContent = data.liked ? '❤️' : '🤍';
                            this.querySelector('.like-count-comment').textContent = data.totalLikes;
                            this.classList.toggle('liked', data.liked);
                        }
                    });
            });

            const replyBtnEl = div.querySelector('.btn-reply-comment');
            if (replyBtnEl) {
                replyBtnEl.addEventListener('click', function() {
                    const idComentario = this.dataset.comentario;
                    const existingBox = document.getElementById('reply-box-' + idComentario);
                    if (existingBox) {
                        existingBox.remove();
                        return;
                    }
                    const replyBox = document.createElement('div');
                    replyBox.className = 'reply-box';
                    replyBox.id = 'reply-box-' + idComentario;
                    replyBox.innerHTML = `
                <input type="text" id="reply-input-${idComentario}" class="comment-input reply-input"
                       placeholder="Responde a @${escapeHtml(c.username)}..." maxlength="500">
                <button class="btn-send-reply btn-send-comment" data-post="${postId}" data-padre="${idComentario}">Enviar</button>`;
                    div.after(replyBox);
                    replyBox.querySelector('.btn-send-reply').addEventListener('click', function() {
                        enviarComentario(this.dataset.post, this.dataset.padre);
                    });
                    replyBox.querySelector('input').addEventListener('keydown', function(e) {
                        if (e.key === 'Enter') enviarComentario(postId, idComentario);
                    });
                    replyBox.querySelector('input').focus();
                });
            }

            const optionsBtnEl = div.querySelector('.btn-comment-options');
            if (optionsBtnEl) {
                const optionsMenu = div.querySelector('.comment-options-menu');
                optionsBtnEl.addEventListener('click', function(e) {
                    e.stopPropagation();
                    document.querySelectorAll('.comment-options-menu.show').forEach(m => {
                        if (m !== optionsMenu) m.classList.remove('show');
                    });
                    optionsMenu.classList.toggle('show');
                });
                div.querySelector('.btn-cancel-comment-menu').addEventListener('click', () => optionsMenu.classList.remove('show'));
            }

            // Reportar comentario ajeno
            const reportCommentEl = div.querySelector('.btn-report-comment');
            if (reportCommentEl) {
                reportCommentEl.addEventListener('click', function() {
                    div.querySelector('.comment-options-menu').classList.remove('show');
                    abrirModalReporte('comentario', this.dataset.comentario);
                });
            }

            const deleteBtnEl = div.querySelector('.btn-delete-comment');
            if (deleteBtnEl) {
                deleteBtnEl.addEventListener('click', function() {
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
                                const countEl = document.querySelector('.comment-count-' + postId);
                                if (countEl) countEl.textContent = data.totalComentarios;
                            }
                        });
                });
            }

            return div;
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

        window.addEventListener('click', function(event) {
            if (!event.target.closest('.btn-options') && !event.target.closest('.options-menu')) {
                document.querySelectorAll('.options-menu').forEach(menu => menu.classList.remove('show'));
            }
            if (!event.target.closest('.btn-comment-options') && !event.target.closest('.comment-options-menu')) {
                document.querySelectorAll('.comment-options-menu.show').forEach(menu => menu.classList.remove('show'));
            }
        });

        // ── Modal de reporte ──────────────────────────────────────────────────
        const reportModal = document.createElement('div');
        reportModal.id = 'modal-reporte';
        reportModal.innerHTML = `
            <div class="modal-reporte-box">
                <h3>🚩 Reportar contenido</h3>
                <p class="modal-reporte-subtitle">Indica el motivo del reporte</p>
                <div class="motivos-grid">
                    <button class="motivo-btn" data-motivo="Contenido inapropiado">🔞 Inapropiado</button>
                    <button class="motivo-btn" data-motivo="Spam o publicidad">📢 Spam</button>
                    <button class="motivo-btn" data-motivo="Acoso o bullying">😠 Acoso</button>
                    <button class="motivo-btn" data-motivo="Información falsa">❌ Falso</button>
                    <button class="motivo-btn" data-motivo="Odio o discriminación">🚫 Odio</button>
                    <button class="motivo-btn" data-motivo="Otro">💬 Otro</button>
                </div>
                <div class="modal-reporte-btns">
                    <button id="btn-cancelar-reporte">Cancelar</button>
                </div>
            </div>
        `;
        reportModal.style.cssText = `
            display:none;position:fixed;inset:0;background:rgba(0,0,0,0.65);
            z-index:9999;align-items:center;justify-content:center;
        `;
        document.body.appendChild(reportModal);

        let _reportTipo = null,
            _reportIdContenido = null;

        function abrirModalReporte(tipo, idContenido) {
            _reportTipo = tipo;
            _reportIdContenido = idContenido;
            reportModal.style.display = 'flex';
        }

        reportModal.addEventListener('click', function(e) {
            if (e.target === this) this.style.display = 'none';
        });
        document.getElementById('btn-cancelar-reporte').addEventListener('click', () => {
            reportModal.style.display = 'none';
        });

        reportModal.querySelectorAll('.motivo-btn').forEach(btn => {
            btn.addEventListener('click', function() {
                const motivo = this.dataset.motivo;
                const fd = new FormData();
                fd.append('accion', 'reportar');
                fd.append('tipo', _reportTipo);
                fd.append('id_contenido', _reportIdContenido);
                fd.append('motivo', motivo);
                fetch('actions/reportar.php', {
                        method: 'POST',
                        body: fd
                    })
                    .then(r => r.json())
                    .then(data => {
                        reportModal.style.display = 'none';
                        const msg = document.createElement('div');
                        msg.className = 'toast-reporte';
                        msg.textContent = data.status === 'success' ?
                            '✅ Reporte enviado' :
                            (data.status === 'already' ? '⚠️ Ya reportaste este contenido' : '❌ Error al reportar');
                        document.body.appendChild(msg);
                        setTimeout(() => msg.remove(), 3000);
                    });
            });
        });

        // Delegar click en botones de reportar publicación
        document.addEventListener('click', function(e) {
            const btn = e.target.closest('.btn-report-post');
            if (btn) {
                e.stopPropagation();
                document.querySelectorAll('.options-menu').forEach(m => m.classList.remove('show'));
                abrirModalReporte('publicacion', btn.dataset.id);
            }
        });
    </script>
    <style>
        .modal-reporte-box {
            background: var(--bg-card, #1e1e2e);
            border-radius: 14px;
            padding: 28px 32px;
            width: 340px;
            max-width: 95vw;
            color: var(--texto-general, #fff);
            box-shadow: 0 8px 40px rgba(0, 0, 0, 0.5);
        }

        .modal-reporte-box h3 {
            margin: 0 0 6px;
            font-size: 18px;
        }

        .modal-reporte-subtitle {
            margin: 0 0 18px;
            font-size: 13px;
            opacity: .7;
        }

        .motivos-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 10px;
            margin-bottom: 18px;
        }

        .motivo-btn {
            background: var(--bg-deep, #141420);
            border: 1px solid var(--border-soft, #333);
            color: var(--texto-general, #fff);
            border-radius: 8px;
            padding: 10px 8px;
            cursor: pointer;
            font-size: 13px;
            transition: background .15s, border-color .15s;
            text-align: left;
        }

        .motivo-btn:hover {
            background: var(--magenta-main, #e040fb);
            border-color: var(--magenta-main, #e040fb);
        }

        .modal-reporte-btns {
            text-align: right;
        }

        .modal-reporte-btns button {
            background: transparent;
            border: 1px solid var(--border-soft, #444);
            color: var(--texto-general, #ccc);
            border-radius: 7px;
            padding: 7px 16px;
            cursor: pointer;
            font-size: 13px;
        }

        .toast-reporte {
            position: fixed;
            bottom: 24px;
            left: 50%;
            transform: translateX(-50%);
            background: #222;
            color: #fff;
            border-radius: 8px;
            padding: 10px 20px;
            font-size: 14px;
            z-index: 10000;
            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.4);
            animation: fadeInUp .2s ease;
        }

        @keyframes fadeInUp {
            from {
                opacity: 0;
                transform: translateX(-50%) translateY(10px);
            }

            to {
                opacity: 1;
                transform: translateX(-50%) translateY(0);
            }
        }

        .btn-report-post {
            background: none;
            border: none;
            color: var(--texto-general, #ccc);
            cursor: pointer;
            font-size: 13px;
            padding: 8px 12px;
            width: 100%;
            text-align: left;
        }

        .btn-report-post:hover {
            color: #ff6b6b;
        }
    </style>
<?php endif; ?>