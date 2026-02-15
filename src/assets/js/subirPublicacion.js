document.addEventListener("DOMContentLoaded", () => {
    const modalPublicacion = document.getElementById("modalPublicar");
    const btnAbrir = document.getElementById("abrirModal");
    const btnCerrar = document.querySelector(".close-btn");
    const fileInput = document.getElementById('file-input');
    const previewContainer = document.getElementById('preview-container');
    const imgPreview = document.getElementById('img-preview');
    const previewContent = document.getElementById('preview-content');

    // VERIFICACIÓN: Si el botón existe, le asignamos el evento
    if (btnAbrir) {
        btnAbrir.onclick = function (e) {
            e.preventDefault(); // Evita cualquier comportamiento raro del enlace
            modalPublicacion.style.display = "block";
        }
    }


    btnCerrar.onclick = () => modalPublicacion.style.display = "none";
    window.onclick = (f) => { if (f.target === modalPublicacion) modalPublicacion.style.display = "none"; };


    // Lógica de la previsualización de imagen que querías
    fileInput.onchange = function () {
        const file = fileInput.files[0];
        if (file) {
            const reader = new FileReader();

            reader.onload = function (e) {
                // Mostramos la imagen y ocultamos el texto decorativo
                imgPreview.src = e.target.result;
                imgPreview.style.display = 'block';
                previewContent.style.display = 'none';
            }

            reader.readAsDataURL(file);
        }
    }
});