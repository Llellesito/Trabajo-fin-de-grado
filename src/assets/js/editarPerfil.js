document.addEventListener("DOMContentLoaded", function () {
    const fotoInput = document.getElementById('foto-input');
    const imgPreview = document.getElementById('img-perfil-preview'); // ID único aquí

    if (fotoInput && imgPreview) {
        fotoInput.onchange = function () {
            const file = fotoInput.files[0];
            if (file) {
                const nuevaRuta = URL.createObjectURL(file);
                imgPreview.src = nuevaRuta;
                console.log("Previsualización de perfil actualizada");
            }
        };
    }
});