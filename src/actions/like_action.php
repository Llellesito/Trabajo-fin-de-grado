<?php
session_start();
require_once '../includes/db.php';
require_once '../clases/Post.php';

// 1. Validar que existan los datos y que NO estén vacíos
if (!isset($_SESSION['id_usuario']) || empty($_POST['id_publicacion'])) {
    // Si falta el ID, redirigimos al index sin hacer nada
    header("Location: ../index.php");
    exit();
}

$postModel = new Post($pdo);
$id_usuario = $_SESSION['id_usuario'];

// 2. Forzar que sea un entero para mayor seguridad
$id_publicacion = (int)$_POST['id_publicacion'];

// 3. Solo ejecutar si el ID es válido (mayor a 0)
if ($id_publicacion > 0) {
    $postModel->toggleLike($id_usuario, $id_publicacion);
}

header("Location: " . $_SERVER['HTTP_REFERER']);
exit();
