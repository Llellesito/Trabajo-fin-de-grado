<?php
session_start();
require_once '../includes/db.php';     // Esto ya crea la variable $pdo
require_once '../clases/User.php';    // Importamos la lógica de usuario

// 1. Verificación de seguridad: ¿Está logueado?
if (!isset($_SESSION['id_usuario'])) {
    header("Location: ../login.php");
    exit();
}

// 2. Obtener IDs
$id_seguidor = $_SESSION['id_usuario']; // ID del que pulsa el botón (Seguro)
$id_seguido  = $_POST['id_seguido'] ?? null; // ID del perfil visitado

if ($id_seguido && $id_seguidor != $id_seguido) {
    try {
        // 3. Usar la clase User para procesar la acción
        $userObj = new User($pdo);
        $userObj->toggleFollow($id_seguidor, $id_seguido);
        
    } catch (Exception $e) {
        // Opcional: registrar error
    }
}

// 4. Redirigir de vuelta al perfil inmediatamente
if (isset($_SERVER['HTTP_REFERER'])) {
    header("Location: " . $_SERVER['HTTP_REFERER']);
} else {
    header("Location: ../index.php");
}
exit();
