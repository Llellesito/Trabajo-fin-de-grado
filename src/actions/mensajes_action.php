<?php
error_reporting(0);
ini_set('display_errors', 0);
ob_start();

session_start();
require_once '../includes/db.php';
require_once '../includes/lib.php';

ob_clean();
header('Content-Type: application/json; charset=utf-8');

if (!isset($_SESSION['id_usuario'])) {
    http_response_code(401);
    echo json_encode(['error' => 'No autenticado'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE);
    exit();
}

// ── Crear tablas si no existen ───────────────────────────────────────────────
$pdo->exec("
    CREATE TABLE IF NOT EXISTS `conversaciones` (
        `id_conversacion` BIGINT NOT NULL AUTO_INCREMENT,
        `es_grupo`        TINYINT(1) NOT NULL DEFAULT 0,
        `nombre_grupo`    VARCHAR(100) DEFAULT NULL,
        `foto_grupo`      LONGBLOB DEFAULT NULL,
        `creado_por`      BIGINT NOT NULL,
        `fecha_creacion`  TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (`id_conversacion`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
");

$pdo->exec("
    CREATE TABLE IF NOT EXISTS `conversacion_miembros` (
        `id_conversacion` BIGINT NOT NULL,
        `id_usuario`      BIGINT NOT NULL,
        `fecha_union`     TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        `ultimo_leido`    BIGINT DEFAULT NULL,
        PRIMARY KEY (`id_conversacion`, `id_usuario`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
");

$pdo->exec("
    CREATE TABLE IF NOT EXISTS `mensajes_conv` (
        `id_mensaje`      BIGINT NOT NULL AUTO_INCREMENT,
        `id_conversacion` BIGINT NOT NULL,
        `id_emisor`       BIGINT NOT NULL,
        `contenido`       TEXT NOT NULL,
        `fecha_envio`     TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (`id_mensaje`),
        KEY `idx_conv` (`id_conversacion`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
");

$mi_id  = (int)$_SESSION['id_usuario'];
$accion = $_POST['accion'] ?? $_GET['accion'] ?? '';

try {

    // ── Listar mis conversaciones ────────────────────────────────────────────────
    if ($accion === 'listar') {
        $stmt = $pdo->prepare("
        SELECT c.id_conversacion, c.es_grupo, c.nombre_grupo, c.foto_grupo,
               -- Último mensaje
               (SELECT mc.contenido FROM mensajes_conv mc
                WHERE mc.id_conversacion = c.id_conversacion
                ORDER BY mc.fecha_envio DESC LIMIT 1) AS ultimo_mensaje,
               (SELECT mc.fecha_envio FROM mensajes_conv mc
                WHERE mc.id_conversacion = c.id_conversacion
                ORDER BY mc.fecha_envio DESC LIMIT 1) AS ultima_fecha,
               -- No leídos
               (SELECT COUNT(*) FROM mensajes_conv mc
                WHERE mc.id_conversacion = c.id_conversacion
                  AND mc.id_emisor != ?
                  AND mc.id_mensaje > COALESCE(cm2.ultimo_leido, 0)
               ) AS no_leidos
        FROM conversaciones c
        JOIN conversacion_miembros cm  ON cm.id_conversacion = c.id_conversacion AND cm.id_usuario = ?
        LEFT JOIN conversacion_miembros cm2 ON cm2.id_conversacion = c.id_conversacion AND cm2.id_usuario = ?
        ORDER BY ultima_fecha DESC
    ");
        $stmt->execute([$mi_id, $mi_id, $mi_id]);
        $convs = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // Para cada DM, obtener datos del otro usuario
        foreach ($convs as &$c) {
            if (!$c['es_grupo']) {
                $stmt2 = $pdo->prepare("
                SELECT u.id_usuario, u.username, u.foto_perfil
                FROM conversacion_miembros cm
                JOIN usuarios u ON u.id_usuario = cm.id_usuario
                WHERE cm.id_conversacion = ? AND cm.id_usuario != ?
                LIMIT 1
            ");
                $stmt2->execute([$c['id_conversacion'], $mi_id]);
                $otro = $stmt2->fetch(PDO::FETCH_ASSOC);
                if ($otro) {
                    $c['nombre_display'] = '@' . $otro['username'];
                    $c['avatar']         = avatarSrc($otro['foto_perfil'], $otro['username']);
                    $c['id_otro']        = $otro['id_usuario'];
                }
            } else {
                $c['nombre_display'] = $c['nombre_grupo'];
                $c['avatar']         = $c['foto_grupo']
                    ? 'data:image/jpeg;base64,' . base64_encode($c['foto_grupo'])
                    : 'https://ui-avatars.com/api/?name=' . urlencode($c['nombre_grupo']) . '&background=1a4fad&color=fff&bold=true';
            }
            unset($c['foto_grupo']);
        }

        echo json_encode(['ok' => true, 'conversaciones' => $convs], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE);
        exit();
    }

    // ── Obtener mensajes de una conversación ─────────────────────────────────────
    if ($accion === 'mensajes') {
        $id_conv   = (int)($_GET['id_conv'] ?? 0);
        $desde_id  = (int)($_GET['desde_id'] ?? 0); // para polling incremental

        // Verificar que soy miembro
        $stmt = $pdo->prepare("SELECT 1 FROM conversacion_miembros WHERE id_conversacion=? AND id_usuario=?");
        $stmt->execute([$id_conv, $mi_id]);
        if (!$stmt->fetch()) {
            echo json_encode(['error' => 'Sin acceso'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE);
            exit();
        }

        $stmt = $pdo->prepare("
        SELECT mc.id_mensaje, mc.contenido, mc.fecha_envio, mc.id_emisor,
               u.username, u.foto_perfil
        FROM mensajes_conv mc
        JOIN usuarios u ON u.id_usuario = mc.id_emisor
        WHERE mc.id_conversacion = ? AND mc.id_mensaje > ?
        ORDER BY mc.fecha_envio ASC
        LIMIT 100
    ");
        $stmt->execute([$id_conv, $desde_id]);
        $msgs = $stmt->fetchAll(PDO::FETCH_ASSOC);

        foreach ($msgs as &$m) {
            $m['avatar']      = avatarSrc($m['foto_perfil'], $m['username']);
            $m['es_mio']      = ($m['id_emisor'] == $mi_id);
            $m['foto_perfil'] = null; // no enviar blob
        }

        // Marcar como leído
        if (!empty($msgs)) {
            $ultimo = end($msgs)['id_mensaje'];
            $pdo->prepare("UPDATE conversacion_miembros SET ultimo_leido=? WHERE id_conversacion=? AND id_usuario=?")
                ->execute([$ultimo, $id_conv, $mi_id]);
        }

        // Info de la conversación (sin blobs)
        $stmt = $pdo->prepare("
        SELECT c.id_conversacion, c.es_grupo, c.nombre_grupo, c.creado_por,
               u.id_usuario, u.username
        FROM conversaciones c
        JOIN conversacion_miembros cm ON cm.id_conversacion = c.id_conversacion
        JOIN usuarios u ON u.id_usuario = cm.id_usuario
        WHERE c.id_conversacion = ? AND cm.id_usuario != ?
        LIMIT 50");
        $stmt->execute([$id_conv, $mi_id]);
        $miembros = $stmt->fetchAll(PDO::FETCH_ASSOC);

        echo json_encode(['ok' => true, 'mensajes' => $msgs, 'miembros' => $miembros], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE);
        exit();
    }

    // ── Enviar mensaje ───────────────────────────────────────────────────────────
    if ($accion === 'enviar') {
        $id_conv   = (int)($_POST['id_conv'] ?? 0);
        $contenido = trim($_POST['contenido'] ?? '');

        if (!$id_conv || $contenido === '') {
            echo json_encode(['error' => 'Datos incompletos'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE);
            exit();
        }

        $stmt = $pdo->prepare("SELECT 1 FROM conversacion_miembros WHERE id_conversacion=? AND id_usuario=?");
        $stmt->execute([$id_conv, $mi_id]);
        if (!$stmt->fetch()) {
            echo json_encode(['error' => 'Sin acceso'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE);
            exit();
        }

        $stmt = $pdo->prepare("INSERT INTO mensajes_conv (id_conversacion, id_emisor, contenido) VALUES (?,?,?)");
        $stmt->execute([$id_conv, $mi_id, $contenido]);
        $id_msg = $pdo->lastInsertId();

        // Marcar como leído por el emisor
        $pdo->prepare("UPDATE conversacion_miembros SET ultimo_leido=? WHERE id_conversacion=? AND id_usuario=?")
            ->execute([$id_msg, $id_conv, $mi_id]);

        echo json_encode(['ok' => true, 'id_mensaje' => $id_msg], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE);
        exit();
    }

    // ── Crear DM o grupo ─────────────────────────────────────────────────────────
    if ($accion === 'crear') {
        $es_grupo     = (int)($_POST['es_grupo'] ?? 0);
        $ids_usuarios = json_decode($_POST['ids_usuarios'] ?? '[]', true);
        $nombre_grupo = trim($_POST['nombre_grupo'] ?? '');

        if (empty($ids_usuarios)) {
            echo json_encode(['error' => 'Sin destinatarios'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE);
            exit();
        }

        // Para DM: comprobar si ya existe conversación entre los dos
        if (!$es_grupo && count($ids_usuarios) === 1) {
            $otro_id = (int)$ids_usuarios[0];
            $stmt = $pdo->prepare("
            SELECT c.id_conversacion FROM conversaciones c
            JOIN conversacion_miembros cm1 ON cm1.id_conversacion=c.id_conversacion AND cm1.id_usuario=?
            JOIN conversacion_miembros cm2 ON cm2.id_conversacion=c.id_conversacion AND cm2.id_usuario=?
            WHERE c.es_grupo=0
            LIMIT 1
        ");
            $stmt->execute([$mi_id, $otro_id]);
            $existing = $stmt->fetchColumn();
            if ($existing) {
                echo json_encode(['ok' => true, 'id_conversacion' => $existing, 'existente' => true], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE);
                exit();
            }
        }

        // Crear conversación
        $stmt = $pdo->prepare("INSERT INTO conversaciones (es_grupo, nombre_grupo, creado_por) VALUES (?,?,?)");
        $stmt->execute([$es_grupo, $es_grupo ? $nombre_grupo : null, $mi_id]);
        $id_conv = $pdo->lastInsertId();

        // Añadir miembros (incluido yo)
        $todos = array_unique(array_merge([$mi_id], array_map('intval', $ids_usuarios)));
        $ins   = $pdo->prepare("INSERT INTO conversacion_miembros (id_conversacion, id_usuario) VALUES (?,?)");
        foreach ($todos as $uid) {
            $ins->execute([$id_conv, $uid]);
        }

        echo json_encode(['ok' => true, 'id_conversacion' => $id_conv], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE);
        exit();
    }

    // ── Buscar usuarios para añadir ──────────────────────────────────────────────
    if ($accion === 'buscar_usuarios') {
        $q = trim($_GET['q'] ?? '');
        if (strlen($q) < 1) {
            echo json_encode(['ok' => true, 'usuarios' => []], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE);
            exit();
        }

        $stmt = $pdo->prepare("
        SELECT id_usuario, username, nombre, foto_perfil
        FROM usuarios
        WHERE (username LIKE ? OR nombre LIKE ?) AND id_usuario != ?
        ORDER BY username ASC LIMIT 10
    ");
        $like = '%' . $q . '%';
        $stmt->execute([$like, $like, $mi_id]);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $usuarios = [];
        foreach ($rows as $u) {
            $usuarios[] = [
                'id_usuario' => $u['id_usuario'],
                'username'   => $u['username'],
                'nombre'     => $u['nombre'] ?? '',
                'avatar'     => avatarSrc($u['foto_perfil'], $u['username']),
            ];
        }
        echo json_encode(['ok' => true, 'usuarios' => $usuarios], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE);
        exit();
    }

    // ── Info de la conversación (miembros) ───────────────────────────────────────
    if ($accion === 'info_conv') {
        $id_conv = (int)($_GET['id_conv'] ?? 0);
        $stmt = $pdo->prepare("SELECT 1 FROM conversacion_miembros WHERE id_conversacion=? AND id_usuario=?");
        $stmt->execute([$id_conv, $mi_id]);
        if (!$stmt->fetch()) {
            echo json_encode(['error' => 'Sin acceso'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE);
            exit();
        }

        $stmt = $pdo->prepare("
        SELECT u.id_usuario, u.username, u.foto_perfil, u.nombre
        FROM conversacion_miembros cm
        JOIN usuarios u ON u.id_usuario = cm.id_usuario
        WHERE cm.id_conversacion = ?
    ");
        $stmt->execute([$id_conv]);
        $miembros = $stmt->fetchAll(PDO::FETCH_ASSOC);
        foreach ($miembros as &$m) {
            $m['avatar']      = avatarSrc($m['foto_perfil'], $m['username']);
            $m['foto_perfil'] = null;
        }

        $stmt = $pdo->prepare("SELECT id_conversacion, es_grupo, nombre_grupo, creado_por, fecha_creacion FROM conversaciones WHERE id_conversacion=?");
        $stmt->execute([$id_conv]);
        $conv = $stmt->fetch(PDO::FETCH_ASSOC);

        echo json_encode(['ok' => true, 'conv' => $conv, 'miembros' => $miembros], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE);
        exit();
    }

    // ── Borrar conversación (solo para mí) ───────────────────────────────────────
    if ($accion === 'borrar_conv') {
        $id_conv = (int)($_POST['id_conv'] ?? 0);
        $stmt = $pdo->prepare("SELECT 1 FROM conversacion_miembros WHERE id_conversacion=? AND id_usuario=?");
        $stmt->execute([$id_conv, $mi_id]);
        if (!$stmt->fetch()) {
            echo json_encode(['error' => 'Sin acceso'], JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE);
            exit();
        }

        // Simplemente me elimino como miembro (el resto sigue viéndola)
        $pdo->prepare("DELETE FROM conversacion_miembros WHERE id_conversacion=? AND id_usuario=?")
            ->execute([$id_conv, $mi_id]);

        // Si ya no hay miembros, borrar la conversación y sus mensajes
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM conversacion_miembros WHERE id_conversacion=?");
        $stmt->execute([$id_conv]);
        if ($stmt->fetchColumn() == 0) {
            $pdo->prepare("DELETE FROM mensajes_conv WHERE id_conversacion=?")->execute([$id_conv]);
            $pdo->prepare("DELETE FROM conversaciones WHERE id_conversacion=?")->execute([$id_conv]);
        }

        echo json_encode(['ok' => true], JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE);
        exit();
    }

    // ── Borrar mensaje (solo el emisor) ─────────────────────────────────────────
    if ($accion === 'borrar_mensaje') {
        $id_mensaje = (int)($_POST['id_mensaje'] ?? 0);
        $stmt = $pdo->prepare("SELECT id_emisor, id_conversacion FROM mensajes_conv WHERE id_mensaje=?");
        $stmt->execute([$id_mensaje]);
        $msg = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$msg || $msg['id_emisor'] != $mi_id) {
            echo json_encode(['error' => 'Sin permiso'], JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE);
            exit();
        }

        $pdo->prepare("DELETE FROM mensajes_conv WHERE id_mensaje=?")->execute([$id_mensaje]);
        echo json_encode(['ok' => true], JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE);
        exit();
    }

    // ── Editar mensaje (solo el emisor) ─────────────────────────────────────────
    if ($accion === 'editar_mensaje') {
        $id_mensaje = (int)($_POST['id_mensaje'] ?? 0);
        $contenido  = trim($_POST['contenido'] ?? '');

        if (!$id_mensaje || $contenido === '') {
            echo json_encode(['error' => 'Datos incompletos'], JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE);
            exit();
        }

        $stmt = $pdo->prepare("SELECT id_emisor FROM mensajes_conv WHERE id_mensaje=?");
        $stmt->execute([$id_mensaje]);
        $msg = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$msg || $msg['id_emisor'] != $mi_id) {
            echo json_encode(['error' => 'Sin permiso'], JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE);
            exit();
        }

        $pdo->prepare("UPDATE mensajes_conv SET contenido=? WHERE id_mensaje=?")->execute([$contenido, $id_mensaje]);
        echo json_encode(['ok' => true, 'contenido' => $contenido], JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE);
        exit();
    }

    echo json_encode(['error' => 'Acción desconocida'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => $e->getMessage()], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE);
}
