<?php
session_start();
require_once '../includes/db.php';
require_once '../includes/lib.php';

header('Content-Type: application/json');

if (!isset($_SESSION['id_usuario'])) {
    echo json_encode(['status' => 'error', 'message' => 'No autenticado']);
    exit;
}

$id_reportador = (int)$_SESSION['id_usuario'];

// Crear tabla de reportes si no existe
try {
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS reportes (
            id_reporte       INT AUTO_INCREMENT PRIMARY KEY,
            tipo             ENUM('publicacion','comentario') NOT NULL,
            id_contenido     INT NOT NULL,
            id_reportador    INT NOT NULL,
            motivo           VARCHAR(255) NOT NULL DEFAULT 'Sin motivo especificado',
            fecha_reporte    DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            estado           ENUM('pendiente','revisado','descartado') NOT NULL DEFAULT 'pendiente',
            id_moderador     INT NULL DEFAULT NULL,
            notas_moderador  TEXT NULL DEFAULT NULL,
            fecha_revision   DATETIME NULL DEFAULT NULL,
            INDEX idx_tipo_id  (tipo, id_contenido),
            INDEX idx_estado   (estado),
            INDEX idx_reportador (id_reportador)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
    ");
} catch (PDOException $e) {
    // La tabla ya existe, continuamos
}

$accion = $_POST['accion'] ?? '';

// ── Enviar reporte ────────────────────────────────────────────────────────────
if ($accion === 'reportar') {
    $tipo        = $_POST['tipo'] ?? '';
    $id_contenido = (int)($_POST['id_contenido'] ?? 0);
    $motivo      = trim($_POST['motivo'] ?? 'Sin motivo especificado');

    if (!in_array($tipo, ['publicacion', 'comentario']) || !$id_contenido) {
        echo json_encode(['status' => 'error', 'message' => 'Datos inválidos']);
        exit;
    }

    if (empty($motivo)) {
        $motivo = 'Sin motivo especificado';
    }

    // Verificar que el contenido existe
    if ($tipo === 'publicacion') {
        $stmt = $pdo->prepare("SELECT id_publicacion FROM publicaciones WHERE id_publicacion = ?");
    } else {
        $stmt = $pdo->prepare("SELECT id_comentario FROM comentarios WHERE id_comentario = ?");
    }
    $stmt->execute([$id_contenido]);
    if (!$stmt->fetch()) {
        echo json_encode(['status' => 'error', 'message' => 'Contenido no encontrado']);
        exit;
    }

    // Evitar reportes duplicados del mismo usuario para el mismo contenido
    $stmt = $pdo->prepare("
        SELECT id_reporte FROM reportes
        WHERE tipo = ? AND id_contenido = ? AND id_reportador = ? AND estado = 'pendiente'
    ");
    $stmt->execute([$tipo, $id_contenido, $id_reportador]);
    if ($stmt->fetch()) {
        echo json_encode(['status' => 'already', 'message' => 'Ya has reportado este contenido']);
        exit;
    }

    $stmt = $pdo->prepare("
        INSERT INTO reportes (tipo, id_contenido, id_reportador, motivo)
        VALUES (?, ?, ?, ?)
    ");
    $stmt->execute([$tipo, $id_contenido, $id_reportador, $motivo]);

    echo json_encode(['status' => 'success', 'message' => 'Reporte enviado correctamente']);
    exit;
}

// ── Acciones de moderación (solo admin/moderador) ─────────────────────────────
if (in_array($accion, ['revisar', 'descartar', 'eliminar_contenido'])) {
    if (!isAdmin()) {
        echo json_encode(['status' => 'error', 'message' => 'Acceso denegado']);
        exit;
    }

    $id_reporte     = (int)($_POST['id_reporte'] ?? 0);
    $notas          = trim($_POST['notas'] ?? '');

    if (!$id_reporte) {
        echo json_encode(['status' => 'error', 'message' => 'ID de reporte inválido']);
        exit;
    }

    // Obtener datos del reporte
    $stmt = $pdo->prepare("SELECT * FROM reportes WHERE id_reporte = ?");
    $stmt->execute([$id_reporte]);
    $reporte = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$reporte) {
        echo json_encode(['status' => 'error', 'message' => 'Reporte no encontrado']);
        exit;
    }

    if ($accion === 'revisar') {
        $pdo->prepare("
            UPDATE reportes SET estado='revisado', id_moderador=?, notas_moderador=?, fecha_revision=NOW()
            WHERE id_reporte=?
        ")->execute([$id_reportador, $notas ?: null, $id_reporte]);
        echo json_encode(['status' => 'success', 'message' => 'Reporte marcado como revisado']);
    } elseif ($accion === 'descartar') {
        $pdo->prepare("
            UPDATE reportes SET estado='descartado', id_moderador=?, notas_moderador=?, fecha_revision=NOW()
            WHERE id_reporte=?
        ")->execute([$id_reportador, $notas ?: null, $id_reporte]);
        echo json_encode(['status' => 'success', 'message' => 'Reporte descartado']);
    } elseif ($accion === 'eliminar_contenido') {
        // Eliminar el contenido reportado y marcar el reporte como revisado
        try {
            $pdo->beginTransaction();
            if ($reporte['tipo'] === 'publicacion') {
                // Borrar comentarios y likes primero
                $pdo->prepare("DELETE FROM comentarios WHERE id_publicacion=?")->execute([$reporte['id_contenido']]);
                $pdo->prepare("DELETE FROM likes WHERE id_publicacion=?")->execute([$reporte['id_contenido']]);
                $pdo->prepare("DELETE FROM publicaciones WHERE id_publicacion=?")->execute([$reporte['id_contenido']]);
                // Marcar todos los reportes de esta publicación como revisados
                $pdo->prepare("
                    UPDATE reportes SET estado='revisado', id_moderador=?, fecha_revision=NOW()
                    WHERE tipo='publicacion' AND id_contenido=?
                ")->execute([$id_reportador, $reporte['id_contenido']]);
            } else {
                $pdo->prepare("DELETE FROM comentarios WHERE id_comentario=?")->execute([$reporte['id_contenido']]);
                $pdo->prepare("
                    UPDATE reportes SET estado='revisado', id_moderador=?, fecha_revision=NOW()
                    WHERE tipo='comentario' AND id_contenido=?
                ")->execute([$id_reportador, $reporte['id_contenido']]);
            }
            $pdo->commit();
            echo json_encode(['status' => 'success', 'message' => 'Contenido eliminado y reporte resuelto']);
        } catch (PDOException $e) {
            $pdo->rollBack();
            echo json_encode(['status' => 'error', 'message' => 'Error al eliminar el contenido']);
        }
    }
    exit;
}

echo json_encode(['status' => 'error', 'message' => 'Acción desconocida']);
