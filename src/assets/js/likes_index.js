document.addEventListener("DOMContentLoaded", () => {
    // Escuchamos clicks en toda la página, pero solo actuamos si es un botón de like
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
                    // Actualización instantánea
                    iconSpan.textContent = data.liked ? "❤️" : "🤍";
                    countSpan.textContent = data.totalLikes;

                    // Efecto visual opcional
                    btn.style.transform = "scale(1.3)";
                    setTimeout(() => btn.style.transform = "scale(1)", 100);
                }
            })
            .catch(err => console.error("Error en el like del home:", err));
    });
});