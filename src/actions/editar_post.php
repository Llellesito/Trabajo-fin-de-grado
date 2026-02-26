<?php
session_start();
require_once '../includes/db.php';

// Protección de sesión
if (!isset($_SESSION['id_usuario'])) {
    header("Location: login.php");
    exit();
}

$id_post = $_GET['id'] ?? 0;
$id_usuario = $_SESSION['id_usuario'];

// 1. Obtener datos y verificar que el usuario sea el dueño
$stmt = $pdo->prepare("SELECT * FROM publicaciones WHERE id_publicacion = :id AND id_usuario = :user");
$stmt->execute([':id' => $id_post, ':user' => $id_usuario]);
$post = $stmt->fetch();

if (!$post) {
    header("Location: ../index.php");
    exit();
}

// 2. Procesar la actualización
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $nuevo_texto = $_POST['contenido_texto'] ?? '';
    $nueva_imagen = null;

    if (isset($_FILES['media']) && $_FILES['media']['error'] === UPLOAD_ERR_OK) {
        $nueva_imagen = file_get_contents($_FILES['media']['tmp_name']);
    }

    try {
        if ($nueva_imagen !== null) {
            $update = $pdo->prepare("UPDATE publicaciones SET contenido_texto = :texto, media = :media WHERE id_publicacion = :id");
            $update->bindParam(':media', $nueva_imagen, PDO::PARAM_LOB);
        } else {
            $update = $pdo->prepare("UPDATE publicaciones SET contenido_texto = :texto WHERE id_publicacion = :id");
        }
        $update->bindParam(':texto', $nuevo_texto);
        $update->bindParam(':id', $id_post);
        $update->execute();

        header("Location: ../index.php");
        exit();
    } catch (PDOException $e) {
        $error = "Error al guardar cambios.";
    }
}
?>

<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Editar Publicación | 8Mangos</title>
    <style>
        :root {
            --bg-dark: #050510;
            --bg-card: #151525;
            --magenta-main: #ff4d94;
            --texto-general: #fff;
            --shadow-posts: rgba(0, 0, 0, 0.5);
        }

        body {
            background-color: var(--bg-dark);
            color: var(--texto-general);
            font-family: 'Segoe UI', sans-serif;
            display: flex;
            justify-content: center;
            align-items: center;
            min-height: 100vh;
            margin: 0;
        }

        .modal-content {
            background: var(--bg-card);
            padding: 25px;
            width: 90vw;
            max-width: 500px;
            border-radius: 20px;
            border: 1px solid #333;
            box-shadow: 0 10px 30px var(--shadow-posts);
        }

        .modal-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
        }

        .modal-header h3 {
            margin: 0;
            font-size: 22px;
            background: linear-gradient(to right, #fff, var(--magenta-main));
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
        }

        .close-btn {
            cursor: pointer;
            font-size: 28px;
            color: #888;
            text-decoration: none;
            line-height: 1;
        }

        .close-btn:hover {
            color: white;
        }

        /* Estilos del área de carga */
        .upload-placeholder {
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            width: 100%;
            height: 400px;
            background: #0a0a15;
            border: 2px dashed #333;
            border-radius: 12px;
            cursor: pointer;
            margin-bottom: 15px;
            position: relative;
            overflow: hidden;
            transition: border-color 0.3s;
        }

        .upload-placeholder:hover {
            border-color: var(--magenta-main);
        }

        .upload-placeholder .icon {
            font-size: 40px;
            margin-bottom: 10px;
        }

        .upload-placeholder p {
            color: #888;
            font-size: 14px;
            margin: 0;
        }

        #img-preview {
            width: 100%;
            height: 400px;
            object-fit: cover;
            display: block;
        }

        /* Formulario */
        textarea {
            width: 100%;
            height: 80px;
            background: #0a0a15;
            border: 1px solid #333;
            color: white;
            padding: 15px;
            border-radius: 12px;
            resize: none;
            margin-bottom: 20px;
            box-sizing: border-box;
            font-family: inherit;
        }

        textarea:focus {
            outline: none;
            border-color: var(--magenta-main);
        }

        .btn-principal {
            background: var(--magenta-main);
            color: white;
            border: none;
            padding: 14px;
            border-radius: 10px;
            width: 100%;
            font-weight: bold;
            font-size: 16px;
            cursor: pointer;
            transition: transform 0.2s, background 0.2s;
        }

        .btn-principal:hover {
            background: #e63e85;
            transform: translateY(-2px);
        }
    </style>
</head>

<body>

    <div class="modal-content">
        <div class="modal-header">
            <h3>Editar Publicación</h3>
            <a href="../index.php" class="close-btn">&times;</a>
        </div>

        <form action="" method="POST" enctype="multipart/form-data">

            <label for="file-input" class="upload-placeholder" id="drop-area">
                <input type="file" name="media" id="file-input" accept="image/*" hidden>

                <div id="preview-content" style="display: <?= $post['media'] ? 'none' : 'flex' ?>; flex-direction: column; align-items: center;">
                    <span class="icon">📷</span>
                    <p id="upload-text">Haz clic para cambiar la foto</p>
                </div>

                <?php if ($post['media']): ?>
                    <img id="img-preview" src="data:image/jpeg;base64,<?= base64_encode($post['media']) ?>">
                <?php else: ?>
                    <img id="img-preview" src="" style="display: none;">
                <?php endif; ?>
            </label>

            <textarea name="contenido_texto" placeholder="¿Qué estás pensando?"><?= htmlspecialchars($post['contenido_texto']) ?></textarea>

            <button type="submit" class="btn-principal">Guardar cambios en 8Mangos</button>
        </form>
    </div>

    <script>
        document.addEventListener("DOMContentLoaded", () => {
            const fileInput = document.getElementById('file-input');
            const imgPreview = document.getElementById('img-preview');
            const previewContent = document.getElementById('preview-content');

            fileInput.onchange = function() {
                const file = fileInput.files[0];
                if (file) {
                    const reader = new FileReader();
                    reader.onload = function(e) {
                        imgPreview.src = e.target.result;
                        imgPreview.style.display = 'block';
                        imgPreview.style.width = '100%';
                        imgPreview.style.height = '100%';
                        previewContent.style.display = 'none';
                    }
                    reader.readAsDataURL(file);
                }
            }
        });
    </script>
</body>

</html>