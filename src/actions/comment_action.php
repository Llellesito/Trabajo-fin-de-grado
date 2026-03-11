<?php
session_start();
require_once '../includes/db.php';
require_once '../clases/Comment.php';

header('Content-Type: application/json');

if (!isset($_SESSION['id_usuario'])) {
    echo json_encode(['status' => 'error', 'message' => 'No logueado']);
    exit;
}

$id_usuario = $_SESSION['id_usuario'];
$accion = $_POST['accion'] ?? $_GET['accion'] ?? '';
$commentModel = new Comment($pdo);

// Obtener comentarios
if ($accion === 'obtener') {
    $id_publicacion = (int)($_GET['id_publicacion'] ?? 0);
    if (!$id_publicacion) {
        echo json_encode(['status' => 'error', 'message' => 'ID inválido']);
        exit;
    }

    $comentarios = $commentModel->getComentarios($id_publicacion, $id_usuario);
    foreach ($comentarios as &$c) {
        $c['foto_perfil'] = $c['foto_perfil'] ? base64_encode($c['foto_perfil']) : null;
    }
    echo json_encode(['status' => 'success', 'comentarios' => $comentarios]);
    exit;
}

// Agregar comentario o respuesta
if ($accion === 'agregar') {
    $id_publicacion = (int)($_POST['id_publicacion'] ?? 0);
    $contenido      = trim($_POST['contenido'] ?? '');
    $id_padre       = (int)($_POST['id_padre'] ?? 0) ?: null;

    if (!$id_publicacion || empty($contenido)) {
        echo json_encode(['status' => 'error', 'message' => 'Datos incompletos']);
        exit;
    }

    $ok = $commentModel->agregarComentario($id_usuario, $id_publicacion, $contenido, $id_padre);

    if ($ok) {
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM comentarios WHERE id_publicacion = ? AND (id_padre IS NULL OR id_padre = 0)");
        $stmt->execute([$id_publicacion]);
        $total = $stmt->fetchColumn();

        $comentarios = $commentModel->getComentarios($id_publicacion, $id_usuario);
        foreach ($comentarios as &$c) {
            $c['foto_perfil'] = $c['foto_perfil'] ? base64_encode($c['foto_perfil']) : null;
        }
        echo json_encode(['status' => 'success', 'totalComentarios' => $total, 'comentarios' => $comentarios]);
    } else {
        echo json_encode(['status' => 'error', 'message' => 'Error al guardar']);
    }
    exit;
}

// Borrar comentario
if ($accion === 'borrar') {
    $id_comentario  = (int)($_POST['id_comentario'] ?? 0);
    $id_publicacion = (int)($_POST['id_publicacion'] ?? 0);

    if (!$id_comentario) {
        echo json_encode(['status' => 'error', 'message' => 'ID inválido']);
        exit;
    }

    $ok = $commentModel->borrarComentario($id_comentario, $id_usuario);
    if ($ok) {
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM comentarios WHERE id_publicacion = ? AND (id_padre IS NULL OR id_padre = 0)");
        $stmt->execute([$id_publicacion]);
        $total = $stmt->fetchColumn();
        echo json_encode(['status' => 'success', 'totalComentarios' => $total]);
    } else {
        echo json_encode(['status' => 'error', 'message' => 'No autorizado o no existe']);
    }
    exit;
}

// Toggle like en comentario
if ($accion === 'like_comentario') {
    $id_comentario = (int)($_POST['id_comentario'] ?? 0);
    if (!$id_comentario) {
        echo json_encode(['status' => 'error', 'message' => 'ID inválido']);
        exit;
    }

    $commentModel->toggleLikeComentario($id_usuario, $id_comentario);
    $total     = $commentModel->getLikesComentario($id_comentario);
    $yaDioLike = $commentModel->haDadoLikeComentario($id_usuario, $id_comentario);

    echo json_encode(['status' => 'success', 'totalLikes' => $total, 'liked' => $yaDioLike]);
    exit;
}

echo json_encode(['status' => 'error', 'message' => 'Acción desconocida']);
