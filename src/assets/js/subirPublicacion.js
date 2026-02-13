document.addEventListener("DOMContentLoaded", function() {
        const modal = document.getElementById("modalPublicar");
        const btnAbrir = document.getElementById("abrirModal");
        const btnCerrar = document.querySelector(".close-btn");
        const fileInput = document.getElementById('file-input');
        const previewContainer = document.getElementById('preview-container');
        const imgPreview = document.getElementById('img-preview');
        const previewContent = document.getElementById('preview-content');

        // VERIFICACIÓN: Si el botón existe, le asignamos el evento
        if (btnAbrir) {
            btnAbrir.onclick = function(e) {
                e.preventDefault(); // Evita cualquier comportamiento raro del enlace
                modal.style.display = "block";
            }
        }

        // Cerrar modal
        btnCerrar.addEventListener('click', () => {
            imgPreview.style.display = 'none';
            previewContent.style.display = 'flex';
        });

        // Cerrar si hacen clic fuera del contenido
        window.onclick = function(event) {
            if (event.target == modal) {
                modal.style.display = "none";
            }
        }

        // Lógica de la previsualización de imagen que querías
        fileInput.onchange = function() {
            const file = fileInput.files[0];
            if (file) {
                const reader = new FileReader();

                reader.onload = function(e) {
                    // Mostramos la imagen y ocultamos el texto decorativo
                    imgPreview.src = e.target.result;
                    imgPreview.style.display = 'block';
                    previewContent.style.display = 'none';
                }

                reader.readAsDataURL(file);
            }
        }
    });