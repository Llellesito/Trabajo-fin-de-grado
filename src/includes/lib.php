<?php

/**
 * Devuelve el src de la foto de perfil.
 */
function avatarSrc($foto_perfil, $username = 'U')
{
    if (!empty($foto_perfil)) {
        return 'data:image/jpeg;base64,' . base64_encode($foto_perfil);
    }
    return 'https://ui-avatars.com/api/?name=' . urlencode($username)
        . '&background=1a4fad&color=ffffff&bold=true&size=128';
}

function renderizarBotonPerfil($id_perfil_visitado, $pdo)
{
    $mi_id = $_SESSION['id_usuario'] ?? null;
    if (!$mi_id) return '<button onclick="window.location.href=\'login.php\'">Inicia sesión para seguir</button>';

    if ($mi_id == $id_perfil_visitado) {
        return '<div style="text-align:center;margin:15px 0;">
            <a href="actions/WIP_editar_perfil.php" style="color:white;background:#2b7a2b;padding:10px 15px;border-radius:8px;text-decoration:none;display:inline-block;">Editar perfil</a>
        </div>';
    }

    require_once 'clases/User.php';
    $userObj    = new User($pdo);
    $ya_lo_sigo = $userObj->estaSiguiendo($mi_id, $id_perfil_visitado);
    $texto      = $ya_lo_sigo ? 'Dejar de seguir' : 'Seguir';
    $color      = $ya_lo_sigo ? '#6c757d' : '#007bff';

    return '<form method="post" action="actions/seguir.php" style="text-align:center;margin:15px 0;">
        <input type="hidden" name="id_seguido" value="' . htmlspecialchars($id_perfil_visitado) . '">
        <button type="submit" style="cursor:pointer;padding:10px 15px;border-radius:8px;background:' . $color . ';color:white;border:none;">' . $texto . '</button>
    </form>';
}

function isAdmin(): bool
{
    return isset($_SESSION['rol']) && in_array($_SESSION['rol'], ['admin', 'moderador']);
}

function requireAdmin(): void
{
    if (!isAdmin()) {
        http_response_code(403);
        die("Acceso denegado.");
    }
}

// ═══════════════════════════════════════════════════════════════════════════
// SISTEMA DE SANCIONES MÚLTIPLES
// Columnas independientes — se pueden combinar libremente:
//   ban_expira              DATETIME NULL   — NULL=sin ban; fecha=ban hasta esa fecha; '9999-01-01'=permanente
//   shadowban               TINYINT(1)      — invisible en feeds y buscador
//   mute_expira             DATETIME NULL   — NULL=sin mute; fecha=sin comentar hasta esa fecha
//   limite_comentarios_dia  TINYINT UNSIGNED— 0=sin límite; N=máximo N comentarios/día
//   shadowban_comentarios   TINYINT(1)      — sus comentarios solo los ve él
// ═══════════════════════════════════════════════════════════════════════════

/**
 * Asegura que las columnas de sanciones existen (idempotente).
 */
function ensureSanctionColumns($pdo): void
{
    static $done = false;
    if ($done) return;
    $done = true;

    $cols = [
        "ADD COLUMN ban_expira DATETIME NULL DEFAULT NULL",
        "ADD COLUMN shadowban TINYINT(1) NOT NULL DEFAULT 0",
        "ADD COLUMN mute_expira DATETIME NULL DEFAULT NULL",
        "ADD COLUMN limite_comentarios_dia TINYINT UNSIGNED NOT NULL DEFAULT 0",
        "ADD COLUMN shadowban_comentarios TINYINT(1) NOT NULL DEFAULT 0",
        "ADD COLUMN rol ENUM('usuario','admin','moderador') NOT NULL DEFAULT 'usuario'",
    ];
    foreach ($cols as $col) {
        try {
            $pdo->exec("ALTER TABLE usuarios $col");
        } catch (PDOException $e) {
        }
    }
}

/**
 * Carga las sanciones activas de un usuario.
 * Limpia automáticamente ban/mute expirados.
 */
function cargarSancion(int $id_usuario, $pdo): array
{
    ensureSanctionColumns($pdo);

    $stmt = $pdo->prepare("
        SELECT ban_expira, shadowban, mute_expira, limite_comentarios_dia, shadowban_comentarios
        FROM usuarios WHERE id_usuario = ?
    ");
    $stmt->execute([$id_usuario]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$row) return sanciones_vacias();

    $ahora = new DateTime();

    // Limpiar ban expirado
    if ($row['ban_expira'] && $row['ban_expira'] !== '9999-01-01 00:00:00') {
        if ($ahora > new DateTime($row['ban_expira'])) {
            $pdo->prepare("UPDATE usuarios SET ban_expira=NULL WHERE id_usuario=?")->execute([$id_usuario]);
            $row['ban_expira'] = null;
        }
    }

    // Limpiar mute expirado
    if ($row['mute_expira']) {
        if ($ahora > new DateTime($row['mute_expira'])) {
            $pdo->prepare("UPDATE usuarios SET mute_expira=NULL WHERE id_usuario=?")->execute([$id_usuario]);
            $row['mute_expira'] = null;
        }
    }

    return [
        'ban_expira'             => $row['ban_expira'],
        'shadowban'              => (bool)(int)$row['shadowban'],
        'mute_expira'            => $row['mute_expira'],
        'limite_comentarios_dia' => (int)$row['limite_comentarios_dia'],
        'shadowban_comentarios'  => (bool)(int)$row['shadowban_comentarios'],
    ];
}

function sanciones_vacias(): array
{
    return ['ban_expira' => null, 'shadowban' => false, 'mute_expira' => null, 'limite_comentarios_dia' => 0, 'shadowban_comentarios' => false];
}

/** ¿El usuario tiene un ban activo (no puede acceder)? */
function estaBaneado(array $s): bool
{
    if (!$s['ban_expira']) return false;
    if ($s['ban_expira'] === '9999-01-01 00:00:00') return true;
    return new DateTime() < new DateTime($s['ban_expira']);
}

/** ¿El usuario está shadowbaneado (invisible en feeds/buscador)? */
function esShadowbaneado(array $s): bool
{
    return $s['shadowban'];
}

/** ¿El usuario puede escribir comentarios ahora? */
function puedecomentar(int $id_usuario, array $s, $pdo): bool
{
    // Mute activo
    if ($s['mute_expira'] && new DateTime() < new DateTime($s['mute_expira'])) return false;

    // Límite diario
    if ($s['limite_comentarios_dia'] > 0) {
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM comentarios WHERE id_usuario=? AND DATE(fecha_comentario)=CURDATE()");
        $stmt->execute([$id_usuario]);
        if ($stmt->fetchColumn() >= $s['limite_comentarios_dia']) return false;
    }

    return true;
}

/** ¿Los comentarios de este usuario son visibles para otros? */
function comentarioVisible(array $s): bool
{
    return !$s['shadowban_comentarios'];
}

/** Mensaje de error cuando no puede comentar. */
function mensajeMute(array $s): string
{
    if ($s['mute_expira']) {
        return 'No puedes comentar hasta el ' . date('d/m/Y H:i', strtotime($s['mute_expira']));
    }
    return "Has alcanzado tu límite de {$s['limite_comentarios_dia']} comentarios por hoy.";
}
