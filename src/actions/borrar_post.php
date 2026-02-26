<?php
session_start();
require_once '../includes/db.php';

// 1. Verificar que el usuario esté logueado
if (!isset($_SESSION['id_usuario'])) {
    header("Location: login.php");
    exit();
}

$id_post = $_GET['id'] ?? 0;
$id_usuario = $_SESSION['id_usuario'];

if ($id_post > 0) {
    // 2. Intentar borrar asegurándonos de que el post pertenezca al usuario
    $sql = "DELETE FROM publicaciones WHERE id_publicacion = :id_post AND id_usuario = :id_usuario";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([
        ':id_post' => $id_post,
        ':id_usuario' => $id_usuario
    ]);

    // Opcional: Podrías verificar si rowCount() > 0 para saber si realmente se borró algo
}

// 3. Redirigir de vuelta a la página principal o perfil
header("Location: ../index.php");
exit();
