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

// Obtener comentarios de un post
if ($accion === 'obtener') {
    $id_publicacion = (int)($_GET['id_publicacion'] ?? 0);
    if (!$id_publicacion) {
        echo json_encode(['status' => 'error', 'message' => 'ID inválido']);
        exit;
    }
    $comentarios = $commentModel->getComentarios($id_publicacion);

    // Convertir foto_perfil a base64
    foreach ($comentarios as &$c) {
        $c['foto_perfil'] = $c['foto_perfil'] ? base64_encode($c['foto_perfil']) : null;
    }

    echo json_encode(['status' => 'success', 'comentarios' => $comentarios]);
    exit;
}

// Agregar comentario
if ($accion === 'agregar') {
    $id_publicacion = (int)($_POST['id_publicacion'] ?? 0);
    $contenido = trim($_POST['contenido'] ?? '');

    if (!$id_publicacion || empty($contenido)) {
        echo json_encode(['status' => 'error', 'message' => 'Datos incompletos']);
        exit;
    }

    $ok = $commentModel->agregarComentario($id_usuario, $id_publicacion, $contenido);

    if ($ok) {
        // Devolver el conteo actualizado
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM comentarios WHERE id_publicacion = ?");
        $stmt->execute([$id_publicacion]);
        $total = $stmt->fetchColumn();

        // Devolver también los comentarios actualizados
        $comentarios = $commentModel->getComentarios($id_publicacion);
        foreach ($comentarios as &$c) {
            $c['foto_perfil'] = $c['foto_perfil'] ? base64_encode($c['foto_perfil']) : null;
        }

        echo json_encode([
            'status' => 'success',
            'totalComentarios' => $total,
            'comentarios' => $comentarios
        ]);
    } else {
        echo json_encode(['status' => 'error', 'message' => 'Error al guardar']);
    }
    exit;
}

// Borrar comentario
if ($accion === 'borrar') {
    $id_comentario = (int)($_POST['id_comentario'] ?? 0);
    $id_publicacion = (int)($_POST['id_publicacion'] ?? 0);

    if (!$id_comentario) {
        echo json_encode(['status' => 'error', 'message' => 'ID inválido']);
        exit;
    }

    $ok = $commentModel->borrarComentario($id_comentario, $id_usuario);

    if ($ok) {
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM comentarios WHERE id_publicacion = ?");
        $stmt->execute([$id_publicacion]);
        $total = $stmt->fetchColumn();

        echo json_encode(['status' => 'success', 'totalComentarios' => $total]);
    } else {
        echo json_encode(['status' => 'error', 'message' => 'No autorizado o no existe']);
    }
    exit;
}

echo json_encode(['status' => 'error', 'message' => 'Acción desconocida']);
