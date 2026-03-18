<style>
    a {
        text-decoration: none;
        color: var(--texto-general);
    }

    /* --- Barra de navegación lateral --- */
    aside.sidebar {
        background-color: var(--bg-card);
        border-right: 1px solid var(--shadow-posts);
        width: 220px;
        height: 100vh;
        position: sticky;
        top: 0;
        display: flex;
        flex-direction: column;
        padding: 20px 15px;
    }

    /* Contenedor del Título */
    .sidebar-logo {
        padding: 10px 15px 30px 15px;
    }

    .sidebar-logo h2 {
        margin: 0;
        font-size: 24px;
        font-weight: bold;
        color: white;
        letter-spacing: 1px;
        background: linear-gradient(to right, #fff, var(--magenta-main));
        -webkit-background-clip: text;
        -webkit-text-fill-color: transparent;
    }

    .sidebar-nav ul {
        list-style: none;
        padding: 0;
        margin: 0;
    }

    .nav-link {
        display: flex;
        align-items: center;
        padding: 12px 15px;
        margin-bottom: 5px;
        border-radius: 12px;
        color: var(--texto-general);
        text-decoration: none;
        transition: all 0.2s ease-in-out;
    }

    .nav-link .icon {
        font-size: 1.3rem;
        margin-right: 15px;
    }

    .nav-link .text {
        font-size: 16px;
        font-weight: 500;
    }

    /* Efectos */
    .nav-link:hover {
        background-color: var(--blue-light-transparencia);
        /* Fondo verde muy sutil */
        color: #fff;
        transform: translateY(3px);
    }

    /* Estilos para el Modal */
    .modal-overlay {
        display: none;
        position: fixed;
        z-index: 9999;
        left: 0;
        top: 0;
        width: 100%;
        height: 100%;
        background: rgba(0, 0, 0, 0.85);
        backdrop-filter: blur(5px);
    }

    .modal-content {
        background: #151525;
        /* Color oscuro similar a tu sidebar */
        margin: 5% auto;
        padding: 25px;
        width: 40vw;
        max-width: 500px;
        border-radius: 20px;
        border: 1px solid #333;
        box-shadow: 0 10px 30px rgba(0, 0, 0, 0.5);
    }

    .modal-header {
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin-bottom: 15px;
    }

    .close-btn {
        cursor: pointer;
        font-size: 24px;
        color: #888;
    }

    .close-btn:hover {
        color: white;
    }

    textarea {
        width: 460px;
        height: 60px;
        background: #0a0a15;
        border: 1px solid #333;
        color: white;
        padding: 15px;
        border-radius: 12px;
        resize: none;
        margin-bottom: 15px;
    }

    .form-row {
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin-bottom: 20px;
    }

    .btn-principal {
        background: #ff4d94;
        color: white;
        border: none;
        padding: 12px;
        border-radius: 10px;
        width: 100%;
        font-weight: bold;
        cursor: pointer;
    }

    .upload-placeholder {
        display: flex;
        flex-direction: column;
        align-items: center;
        justify-content: center;
        width: 100%;
        height: 400px;
        min-height: 200px;
        background: #0a0a15;
        /* Fondo oscuro */
        border: 2px dashed #333;
        border-radius: 12px;
        cursor: pointer;
        margin-bottom: 15px;
        overflow: hidden;
        transition: border-color 0.3s;
    }

    .upload-placeholder:hover {
        border-color: #ff4d94;
        /* Color rosa de tu botón */
    }

    .upload-placeholder .icon {
        font-size: 40px;
        margin-bottom: 10px;
    }

    .upload-placeholder p {
        color: #888;
        font-size: 14px;
    }

    #img-preview {
        width: 100%;
        height: 400px;
        object-fit: cover;
        /* Ajusta la imagen al cuadro */
    }
</style>

<aside class="sidebar">
    <div class="sidebar-logo">
        <a href="/index.php">
            <h2>8Mangos</h2>
        </a>
    </div>

    <nav class="sidebar-nav">
        <ul>
            <li>
                <a href="/index.php" class="nav-link">
                    <span class="icon">🏠</span>
                    <span class="text">Home</span>
                </a>
            </li>
            <li>
                <a href="/buscador.php" class="nav-link">
                    <span class="icon">🔍</span>
                    <span class="text">Buscador</span>
                </a>
            </li>
            <li>
                <a href="javascript:void(0)" class="nav-link" id="abrirModal">
                    <span class="icon">➕</span>
                    <span class="text">Subir publicacion</span>
                </a>
            </li>
            <li>
                <a href="/miPerfil.php" class="nav-link">
                    <span class="icon">🗣</span>
                    <span class="text"><?php echo htmlspecialchars($_SESSION['usuario']) ?></span>
                </a>
            </li>

        </ul>
    </nav>

    <script src="../assets/js/subirPublicacion.js"></script>
</aside>

<!-- Modal fuera del aside para evitar problemas de stacking context -->
<div id="modalPublicar" class="modal-overlay">
    <div class="modal-content">
        <div class="modal-header">
            <h3>Nueva Publicación</h3>
            <span class="close-btn">&times;</span>
        </div>

        <form action="actions/procesar_post.php" method="POST" enctype="multipart/form-data">

            <label for="file-input" class="upload-placeholder" id="drop-area">
                <input type="file" name="media" id="file-input" accept="image/*" hidden>

                <div id="preview-content" style="display: flex">
                    <span class="icon">📷</span>
                    <p id="upload-text">Haz clic para agregar una foto</p>
                </div>

                <img id="img-preview" src="" style="display: none;">
            </label>
            <textarea name="contenido_texto" placeholder="¿Qué estás pensando?"></textarea>

            <button type="submit" class="btn-principal">Publicar en 8Mangos</button>
        </form>
    </div>
</div>