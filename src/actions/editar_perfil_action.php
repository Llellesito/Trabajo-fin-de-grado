<?php
session_start();
require_once '../includes/db.php';
require_once '../clases/User.php';

// 1. Verificación de seguridad crítica
if (!isset($_SESSION['id_usuario'])) {
    header("Location: ../login.php");
    exit();
}

$id_usuario = $_SESSION['id_usuario']; // ID recuperado de la sesión segura

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $nuevo_username = trim($_POST['username']);
    $nombre = trim($_POST['nombre']);
    $bio = trim($_POST['bio']);
    $foto_blob = null;
    $borrar_foto = isset($_POST['borrar_foto']) && $_POST['borrar_foto'] === '1';

    $userObj = new User($pdo);

    // 2. Validar disponibilidad del username (si cambió)
    if (!$userObj->usernameDisponible($nuevo_username, $id_usuario)) {
        die("Error: El nombre de usuario '@$nuevo_username' ya está siendo usado por otra persona.");
    }

    // 3. Procesar foto
    if (!$borrar_foto && isset($_FILES['foto']) && $_FILES['foto']['error'] === UPLOAD_ERR_OK) {
        $foto_blob = file_get_contents($_FILES['foto']['tmp_name']);
    }

    // 4. Ejecutar cambios
    $exito = $userObj->updateProfile($id_usuario, $nuevo_username, $nombre, $bio, $foto_blob, $borrar_foto);

    if ($exito) {
        // ACTUALIZAR SESIÓN: Muy importante para que el header no falle
        $_SESSION['usuario'] = $nuevo_username;

        // REDIRECCIÓN SEGURA: Nos aseguramos de enviar el ID de vuelta
        header("Location: ../miPerfil.php");
    } else {
        die("Hubo un error al guardar los cambios en la base de datos.");
    }
}
