document.addEventListener("DOMContentLoaded", () => {
    const modal = document.getElementById("postModal");
    const modalImg = document.getElementById("modal-img");
    const modalText = document.getElementById("modal-text");
    const modalDate = document.getElementById("modal-date");
    const modalLikeBtn = document.getElementById("modal-like-btn");
    const modalCommentBtn = document.getElementById("modal-comment-btn");
    const commentCountEl = document.querySelector(".comment-count-modal");
    const closeBtn = document.querySelector(".modal-close");
    const commentsSection = document.getElementById("modal-comments-section");
    const commentsList = document.getElementById("modal-comments-list");
    const commentInput = document.getElementById("modal-comment-input");
    const sendCommentBtn = document.getElementById("modal-send-comment");

    const SESSION_USER_ID = window.SESSION_USER_ID ?? 0;
    let currentPostId = null;

    // ── Abrir modal ──────────────────────────────────────────────────────────
    document.querySelectorAll(".post-image").forEach(img => {
        img.addEventListener("click", () => {
            currentPostId = img.getAttribute("data-id");

            // Imagen del post — usar src de la miniatura directamente
            modalImg.src = img.src;
            modalImg.style.display = "block";
            modalText.textContent = img.getAttribute("data-text");
            modalDate.textContent = img.getAttribute("data-date");

            // Avatar y username desde variables PHP → JS globales (evita base64 en atributo)
            document.getElementById("modal-avatar").src = window.PROFILE_AVATAR ?? "";
            document.getElementById("modal-username").textContent = window.PROFILE_USERNAME ?? "";
            document.getElementById("modal-username-link").href =
                "perfil.php?id=" + (window.PROFILE_USER_ID ?? "");

            modalLikeBtn.setAttribute("data-id", currentPostId);
            modalLikeBtn.querySelector(".icon").textContent =
                img.getAttribute("data-user-liked") === "1" ? "❤️" : "🤍";
            modalLikeBtn.parentElement.querySelector("strong").textContent = img.getAttribute("data-likes");

            modalCommentBtn.setAttribute("data-id", currentPostId);
            commentCountEl.textContent = img.getAttribute("data-comments") ?? 0;

            commentsSection.classList.remove("open");
            commentsList.innerHTML = '';
            commentInput.value = '';

            modal.style.display = "flex";
        });
    });

    // ── Cerrar modal ─────────────────────────────────────────────────────────
    closeBtn.onclick = () => modal.style.display = "none";
    modal.addEventListener("click", (e) => {
        if (!modal.querySelector(".modal-content").contains(e.target))
            modal.style.display = "none";
    });

    // ── Like del post ────────────────────────────────────────────────────────
    modalLikeBtn.addEventListener("click", () => {
        const formData = new FormData();
        formData.append("id_publicacion", currentPostId);

        fetch("actions/like_action.php", { method: "POST", body: formData })
            .then(r => r.json())
            .then(data => {
                if (data.status === "success") {
                    modalLikeBtn.querySelector(".icon").textContent = data.liked ? "❤️" : "🤍";
                    modalLikeBtn.parentElement.querySelector("strong").textContent = data.totalLikes;

                    // Sincronizar galería
                    const galleryImg = document.querySelector(`.post-image[data-id="${currentPostId}"]`);
                    if (galleryImg) {
                        galleryImg.setAttribute("data-likes", data.totalLikes);
                        galleryImg.setAttribute("data-user-liked", data.liked ? "1" : "0");
                    }
                }
            });
    });

    // ── Toggle comentarios ───────────────────────────────────────────────────
    modalCommentBtn.addEventListener("click", () => {
        const isHidden = !commentsSection.classList.contains("open");
        commentsSection.classList.toggle("open", isHidden);
        if (isHidden) cargarComentarios();
    });

    // ── Enviar comentario ────────────────────────────────────────────────────
    sendCommentBtn.addEventListener("click", () => enviarComentario(null));
    commentInput.addEventListener("keydown", (e) => {
        if (e.key === "Enter") enviarComentario(null);
    });

    function enviarComentario(idPadre) {
        const inputEl = idPadre
            ? document.getElementById("reply-input-modal-" + idPadre)
            : commentInput;
        const contenido = inputEl.value.trim();
        if (!contenido) return;

        const formData = new FormData();
        formData.append("accion", "agregar");
        formData.append("id_publicacion", currentPostId);
        formData.append("contenido", contenido);
        if (idPadre) formData.append("id_padre", idPadre);

        fetch("actions/comment_action.php", { method: "POST", body: formData })
            .then(r => r.json())
            .then(data => {
                if (data.status === "success") {
                    inputEl.value = "";
                    if (idPadre) {
                        const box = document.getElementById("reply-box-modal-" + idPadre);
                        if (box) box.remove();
                    }
                    renderComentarios(data.comentarios);
                    commentCountEl.textContent = data.totalComentarios;
                    // Sincronizar galería
                    const galleryImg = document.querySelector(`.post-image[data-id="${currentPostId}"]`);
                    if (galleryImg) galleryImg.setAttribute("data-comments", data.totalComentarios);
                }
            });
    }

    // ── Cargar comentarios ───────────────────────────────────────────────────
    function cargarComentarios() {
        fetch("actions/comment_action.php?accion=obtener&id_publicacion=" + currentPostId)
            .then(r => r.json())
            .then(data => {
                if (data.status === "success") renderComentarios(data.comentarios);
            });
    }

    // ── Render comentarios ───────────────────────────────────────────────────
    function renderComentarios(comentarios) {
        commentsList.innerHTML = "";

        if (comentarios.length === 0) {
            commentsList.innerHTML = '<p class="comments-empty">Sé el primero en comentar 💬</p>';
            return;
        }

        comentarios.forEach(c => {
            commentsList.appendChild(buildComment(c, false));
            if (c.respuestas && c.respuestas.length > 0) {
                const wrap = document.createElement("div");
                wrap.className = "replies-list";
                c.respuestas.forEach(r => wrap.appendChild(buildComment(r, true)));
                commentsList.appendChild(wrap);
            }
        });
    }

    function buildComment(c, esRespuesta) {
        const div = document.createElement("div");
        div.className = esRespuesta ? "comment-item comment-reply" : "comment-item";

        const avatarSrc = c.foto_perfil
            ? "data:image/jpeg;base64," + c.foto_perfil
            : "https://ui-avatars.com/api/?name=" + encodeURIComponent(c.username) + "&background=444&color=fff";

        const liked = c.yaDioLike == 1 || c.yaDioLike === true;

        const deleteBtn = (parseInt(c.id_usuario) === SESSION_USER_ID)
            ? `<div class="comment-options-container">
                   <button class="btn-comment-options">⋮</button>
                   <div class="comment-options-menu">
                       <button class="btn-delete-comment" data-comentario="${c.id_comentario}" data-publicacion="${currentPostId}">🗑️ Eliminar</button>
                       <button class="btn-cancel-comment-menu">Cancelar</button>
                   </div>
               </div>` : "";

        const replyBtn = !esRespuesta
            ? `<button class="btn-reply-comment" data-comentario="${c.id_comentario}">↩ Responder</button>`
            : "";

        div.innerHTML = `
            <img src="${avatarSrc}" class="comment-avatar" alt="${escapeHtml(c.username)}">
            <div class="comment-body">
                <div class="comment-username"><a href="perfil.php?id=${c.id_usuario}">@${escapeHtml(c.username)}</a></div>
                <div class="comment-text">${escapeHtml(c.contenido)}</div>
                <div class="comment-actions">
                    <button class="btn-like-comment ${liked ? "liked" : ""}" data-comentario="${c.id_comentario}">
                        <span class="like-heart">${liked ? "❤️" : "🤍"}</span>
                        <span class="like-count-comment">${c.totalLikes ?? 0}</span>
                    </button>
                    ${replyBtn}
                    <span class="comment-date">${c.fecha_comentario}</span>
                </div>
            </div>
            ${deleteBtn}
        `;

        // Like comentario
        div.querySelector(".btn-like-comment").addEventListener("click", function () {
            const formData = new FormData();
            formData.append("accion", "like_comentario");
            formData.append("id_comentario", this.dataset.comentario);
            fetch("actions/comment_action.php", { method: "POST", body: formData })
                .then(r => r.json())
                .then(data => {
                    if (data.status === "success") {
                        this.querySelector(".like-heart").textContent = data.liked ? "❤️" : "🤍";
                        this.querySelector(".like-count-comment").textContent = data.totalLikes;
                        this.classList.toggle("liked", data.liked);
                    }
                });
        });

        // Responder
        const replyBtnEl = div.querySelector(".btn-reply-comment");
        if (replyBtnEl) {
            replyBtnEl.addEventListener("click", function () {
                const idComentario = this.dataset.comentario;
                const existing = document.getElementById("reply-box-modal-" + idComentario);
                if (existing) { existing.remove(); return; }

                const box = document.createElement("div");
                box.className = "reply-box";
                box.id = "reply-box-modal-" + idComentario;
                box.innerHTML = `
                    <input type="text" id="reply-input-modal-${idComentario}" class="comment-input reply-input"
                           placeholder="Responde a @${escapeHtml(c.username)}..." maxlength="500">
                    <button class="btn-send-comment">Enviar</button>
                `;
                div.after(box);
                box.querySelector(".btn-send-comment").addEventListener("click", () => enviarComentario(idComentario));
                box.querySelector("input").addEventListener("keydown", (e) => {
                    if (e.key === "Enter") enviarComentario(idComentario);
                });
                box.querySelector("input").focus();
            });
        }

        // Menú ⋮
        const optionsBtn = div.querySelector(".btn-comment-options");
        if (optionsBtn) {
            const optionsMenu = div.querySelector(".comment-options-menu");
            optionsBtn.addEventListener("click", (e) => {
                e.stopPropagation();
                document.querySelectorAll(".comment-options-menu.show").forEach(m => {
                    if (m !== optionsMenu) m.classList.remove("show");
                });
                optionsMenu.classList.toggle("show");

                // Posicionar el menú en fixed
                if (optionsMenu.classList.contains("show")) {
                    const rect = optionsBtn.getBoundingClientRect();
                    optionsMenu.style.top = (rect.top) + "px";
                    optionsMenu.style.left = (rect.right - 140) + "px";
                }
            });
            div.querySelector(".btn-cancel-comment-menu").addEventListener("click", () => {
                optionsMenu.classList.remove("show");
            });
        }

        // Borrar
        const deleteEl = div.querySelector(".btn-delete-comment");
        if (deleteEl) {
            deleteEl.addEventListener("click", function () {
                const formData = new FormData();
                formData.append("accion", "borrar");
                formData.append("id_comentario", this.dataset.comentario);
                formData.append("id_publicacion", this.dataset.publicacion);
                fetch("actions/comment_action.php", { method: "POST", body: formData })
                    .then(r => r.json())
                    .then(data => {
                        if (data.status === "success") {
                            cargarComentarios();
                            commentCountEl.textContent = data.totalComentarios;
                        }
                    });
            });
        }

        return div;
    }

    function escapeHtml(text) {
        const d = document.createElement("div");
        d.appendChild(document.createTextNode(text));
        return d.innerHTML;
    }

    // Cerrar menús de comentario al click fuera
    window.addEventListener("click", () => {
        document.querySelectorAll(".comment-options-menu.show").forEach(m => m.classList.remove("show"));
    });
});