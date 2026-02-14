<?php
session_start();
require_once '../includes/db.php';
require_once '../clases/Post.php';

header('Content-Type: application/json'); // Decimos que vamos a responder con JSON

if (!isset($_SESSION['id_usuario']) || empty($_POST['id_publicacion'])) {
    echo json_encode(['status' => 'error', 'message' => 'No logueado o sin ID']);
    exit;
}

$postModel = new Post($pdo);
$id_usuario = $_SESSION['id_usuario'];
$id_publicacion = (int)$_POST['id_publicacion'];

// Ejecutamos el toggle
$postModel->toggleLike($id_usuario, $id_publicacion);

// Obtenemos el nuevo conteo para actualizar la interfaz sin recargar
$stmt = $pdo->prepare("SELECT COUNT(*) FROM likes WHERE id_publicacion = ?");
$stmt->execute([$id_publicacion]);
$nuevoTotal = $stmt->fetchColumn();

// Comprobamos si ahora tiene like o no
$yaTieneLike = $postModel->haDadoLike($id_usuario, $id_publicacion);

echo json_encode([
    'status' => 'success',
    'totalLikes' => $nuevoTotal,
    'liked' => $yaTieneLike
]);
