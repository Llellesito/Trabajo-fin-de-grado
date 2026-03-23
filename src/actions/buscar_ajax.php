<?php
// Suprimir warnings/notices para que no contaminen el JSON
error_reporting(0);
ini_set('display_errors', 0);
ob_start(); // Capturar cualquier output accidental

session_start();
require '../includes/db.php';
require '../includes/lib.php';

// Limpiar cualquier output previo y enviar cabecera JSON
ob_clean();
header('Content-Type: application/json; charset=utf-8');

if (!isset($_SESSION['id_usuario'])) {
    http_response_code(401);
    echo json_encode(['error' => 'No autenticado']);
    exit();
}

ensureSanctionColumns($pdo);

$id_usuario_sesion = (int)$_SESSION['id_usuario'];
$query = trim($_GET['q'] ?? '');
$tipo  = $_GET['tipo'] ?? 'usuarios';

if (strlen($query) < 1) {
    echo json_encode(['resultados' => [], 'tipo' => $tipo, 'query' => $query]);
    exit();
}

$resultados = [];

try {
    if ($tipo === 'usuarios') {
        $stmt = $pdo->prepare("
            SELECT id_usuario, username, nombre, bio, foto_perfil
            FROM usuarios
            WHERE (username LIKE ? OR nombre LIKE ?)
              AND id_usuario != ?
              AND (shadowban IS NULL OR shadowban = 0)
            ORDER BY username ASC
            LIMIT 15
        ");
        $like = '%' . $query . '%';
        $stmt->execute([$like, $like, $id_usuario_sesion]);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

        foreach ($rows as $u) {
            $resultados[] = [
                'id_usuario' => $u['id_usuario'],
                'username'   => $u['username'],
                'nombre'     => $u['nombre'] ?? '',
                'bio'        => $u['bio'] ?? '',
                'avatar'     => avatarSrc($u['foto_perfil'], $u['username']),
            ];
        }
    } else {
        $stmt = $pdo->prepare("
            SELECT p.id_publicacion, p.contenido_texto, p.fecha_publicacion, p.media,
                   u.id_usuario, u.username, u.foto_perfil
            FROM publicaciones p
            JOIN usuarios u ON p.id_usuario = u.id_usuario
            WHERE p.contenido_texto LIKE ?
              AND (u.shadowban IS NULL OR u.shadowban = 0)
            ORDER BY p.fecha_publicacion DESC
            LIMIT 15
        ");
        $stmt->execute(['%' . $query . '%']);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

        foreach ($rows as $p) {
            $resultados[] = [
                'id_publicacion'    => $p['id_publicacion'],
                'contenido_texto'   => $p['contenido_texto'] ?? '',
                'fecha_publicacion' => $p['fecha_publicacion'],
                'media'             => $p['media'] ? base64_encode($p['media']) : null,
                'id_usuario'        => $p['id_usuario'],
                'username'          => $p['username'],
                'avatar'            => avatarSrc($p['foto_perfil'], $p['username']),
            ];
        }
    }

    echo json_encode(['resultados' => $resultados, 'tipo' => $tipo, 'query' => $query]);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => $e->getMessage(), 'resultados' => [], 'tipo' => $tipo, 'query' => $query]);
}
