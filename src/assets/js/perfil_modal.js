document.addEventListener("DOMContentLoaded", () => {
    const modal = document.getElementById("postModal");
    const modalImg = document.getElementById("modal-img");
    const modalText = document.getElementById("modal-text");
    const modalDate = document.getElementById("modal-date");
    const modalLikeBtn = document.getElementById("modal-like-btn");
    const modalLikeIcon = modalLikeBtn.querySelector(".icon");
    const modalLikeCount = modalLikeBtn.parentElement.querySelector(".count");
    const closeBtn = document.querySelector(".close");

    // 1. ABRIR MODAL
    document.querySelectorAll(".post-image").forEach(img => {
        img.addEventListener("click", () => {
            modal.style.display = "flex";
            modalImg.src = img.src;

            const id = img.getAttribute("data-id");
            modalLikeBtn.setAttribute("data-id", id);
            modalText.innerHTML = img.getAttribute("data-text");
            modalDate.textContent = img.getAttribute("data-date");
            modalLikeCount.textContent = img.getAttribute("data-likes");
            modalLikeIcon.textContent = img.getAttribute("data-user-liked") === "1" ? "❤️" : "🤍";
        });
    });


    // 2. CERRAR MODAL
    closeBtn.onclick = () => modal.style.display = "none";
    window.addEventListener("click", (e) => { if (e.target === modal) modal.style.display = "none"; });


    // 3. LOGICA DE LIKE POR AJAX (DELEGACIÓN DE EVENTOS)
    document.body.addEventListener("click", (e) => {
        const btn = e.target.closest(".btn-like-ajax");
        if (!btn) return;

        e.preventDefault();
        const idPost = btn.getAttribute("data-id");
        const iconSpan = btn.querySelector(".icon");
        const countSpan = btn.parentElement.querySelector(".count");

        const formData = new FormData();
        formData.append('id_publicacion', idPost);

        fetch('actions/like_action.php', {
            method: 'POST',
            body: formData
        })
            .then(res => res.json())
            .then(data => {
                if (data.status === 'success') {
                    const heart = data.liked ? "❤️" : "🤍";
                    iconSpan.textContent = heart;
                    countSpan.textContent = data.totalLikes;

                    // Sincronizar con la imagen de la galería (opcional)
                    const galleryImg = document.querySelector(`.post-image[data-id="${idPost}"]`);
                    if (galleryImg) {
                        galleryImg.setAttribute("data-likes", data.totalLikes);
                        galleryImg.setAttribute("data-user-liked", data.liked ? "1" : "0");
                    }
                }
            })
            .catch(err => console.error("Error:", err));
    });
});