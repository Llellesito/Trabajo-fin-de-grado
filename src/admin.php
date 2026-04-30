<?php
session_start();
require 'includes/db.php';
require 'includes/lib.php';

ensureSanctionColumns($pdo);

if (!isset($_SESSION['id_usuario'])) {
    header("Location: actions/login.php");
    exit();
}
requireAdmin();

$mi_id = (int)$_SESSION['id_usuario'];

// ── Acciones POST ────────────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $accion     = $_POST['accion'] ?? '';
    $id_usuario = (int)($_POST['id_usuario'] ?? 0);

    if ($id_usuario && $id_usuario !== $mi_id) {

        if ($accion === 'cambiar_rol') {
            $nuevo_rol = $_POST['nuevo_rol'] ?? 'usuario';
            if (in_array($nuevo_rol, ['usuario', 'moderador', 'admin'])) {
                $pdo->prepare("UPDATE usuarios SET rol=? WHERE id_usuario=?")->execute([$nuevo_rol, $id_usuario]);
            }
        } elseif ($accion === 'sancionar') {
            $campos = [];
            $vals = [];

            // Ban
            $ban = $_POST['ban'] ?? 'ninguno';
            if ($ban === 'temporal') {
                $dias = max(1, (int)($_POST['ban_dias'] ?? 1));
                $campos[] = 'ban_expira=?';
                $vals[]   = (new DateTime())->modify("+{$dias} days")->format('Y-m-d H:i:s');
            } elseif ($ban === 'permanente') {
                $campos[] = 'ban_expira=?';
                $vals[] = '9999-01-01 00:00:00';
            } elseif ($ban === 'levantar') {
                $campos[] = 'ban_expira=NULL';
            }

            // Shadowban
            $campos[] = 'shadowban=?';
            $vals[]   = isset($_POST['shadowban']) ? 1 : 0;

            // Mute
            $mute = $_POST['mute'] ?? 'ninguno';
            if ($mute === 'temporal') {
                $dias = max(1, (int)($_POST['mute_dias'] ?? 1));
                $campos[] = 'mute_expira=?';
                $vals[]   = (new DateTime())->modify("+{$dias} days")->format('Y-m-d H:i:s');
            } elseif ($mute === 'levantar') {
                $campos[] = 'mute_expira=NULL';
            }

            // Límite comentarios
            $campos[] = 'limite_comentarios_dia=?';
            $vals[]   = max(0, (int)($_POST['limite_comentarios_dia'] ?? 0));

            // Shadowban comentarios
            $campos[] = 'shadowban_comentarios=?';
            $vals[]   = isset($_POST['shadowban_comentarios']) ? 1 : 0;

            if ($campos) {
                $vals[] = $id_usuario;
                $pdo->prepare("UPDATE usuarios SET " . implode(', ', $campos) . " WHERE id_usuario=?")
                    ->execute($vals);
            }
        } elseif ($accion === 'levantar_todo') {
            $pdo->prepare("UPDATE usuarios SET ban_expira=NULL, shadowban=0, mute_expira=NULL,
                           limite_comentarios_dia=0, shadowban_comentarios=0 WHERE id_usuario=?")
                ->execute([$id_usuario]);
        } elseif ($accion === 'eliminar' && $_SESSION['rol'] === 'admin') {
            $pdo->prepare("DELETE FROM usuarios WHERE id_usuario=?")->execute([$id_usuario]);
        }
    }
    header("Location: admin.php");
    exit();
}

// ── Filtros y búsqueda ───────────────────────────────────────────────────────
$buscar  = trim($_GET['q'] ?? '');
$filtro  = $_GET['rol'] ?? 'todos';
$pagina  = max(1, (int)($_GET['p'] ?? 1));
$por_pag = 20;
$offset  = ($pagina - 1) * $por_pag;

$where  = [];
$params = [];

if ($buscar) {
    $where[]  = "(username LIKE ? OR nombre LIKE ? OR email LIKE ?)";
    $like     = '%' . $buscar . '%';
    $params   = array_merge($params, [$like, $like, $like]);
}

if ($filtro !== 'todos') {
    $where[]  = "rol = ?";
    $params[] = $filtro;
}

$sql_where = $where ? 'WHERE ' . implode(' AND ', $where) : '';

$stmt = $pdo->prepare("SELECT COUNT(*) FROM usuarios $sql_where");
$stmt->execute($params);
$total = $stmt->fetchColumn();
$total_pags = max(1, ceil($total / $por_pag));

$stmt = $pdo->prepare("
    SELECT id_usuario, username, nombre, email, rol, fecha_registro, foto_perfil,
           ban_expira, shadowban, mute_expira, limite_comentarios_dia, shadowban_comentarios,
           (SELECT COUNT(*) FROM publicaciones WHERE id_usuario = u.id_usuario) AS num_posts,
           (SELECT COUNT(*) FROM seguidores WHERE id_seguido = u.id_usuario) AS num_seguidores
    FROM usuarios u
    $sql_where
    ORDER BY fecha_registro DESC
    LIMIT $por_pag OFFSET $offset
");
$stmt->execute($params);
$usuarios = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Stats globales
$stats = $pdo->query("
    SELECT
        COUNT(*) AS total_usuarios,
        SUM(rol='admin') AS total_admins,
        SUM(rol='moderador') AS total_mods,
        SUM(ban_expira IS NOT NULL) AS total_baneados,
        SUM(shadowban=1) AS total_shadowban,
        SUM(mute_expira IS NOT NULL OR limite_comentarios_dia > 0 OR shadowban_comentarios=1) AS total_limitados,
        (SELECT COUNT(*) FROM publicaciones) AS total_posts,
        (SELECT COUNT(*) FROM comentarios) AS total_comentarios
    FROM usuarios
")->fetch(PDO::FETCH_ASSOC);

// ── Reportes ─────────────────────────────────────────────────────────────────
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
            INDEX idx_tipo_id   (tipo, id_contenido),
            INDEX idx_estado    (estado),
            INDEX idx_reportador (id_reportador)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
    ");
} catch (PDOException $e) { /* ya existe */
}

$filtro_reportes = $_GET['estado_rep'] ?? 'pendiente';
$estados_validos = ['pendiente', 'revisado', 'descartado', 'todos'];
if (!in_array($filtro_reportes, $estados_validos)) $filtro_reportes = 'pendiente';

$where_rep = $filtro_reportes !== 'todos' ? "WHERE r.estado = '$filtro_reportes'" : '';

$reportes = $pdo->query("
    SELECT
        r.id_reporte, r.tipo, r.id_contenido, r.motivo, r.estado,
        r.fecha_reporte, r.notas_moderador, r.fecha_revision,
        u_rep.username  AS reporter_username,
        u_rep.id_usuario AS reporter_id,
        u_mod.username  AS moderador_username,
        -- Datos del contenido reportado
        CASE
            WHEN r.tipo = 'publicacion' THEN (SELECT contenido_texto FROM publicaciones WHERE id_publicacion = r.id_contenido)
            WHEN r.tipo = 'comentario'  THEN (SELECT texto           FROM comentarios    WHERE id_comentario  = r.id_contenido)
        END AS contenido_texto,
        CASE
            WHEN r.tipo = 'publicacion' THEN (SELECT id_usuario FROM publicaciones WHERE id_publicacion = r.id_contenido)
            WHEN r.tipo = 'comentario'  THEN (SELECT id_usuario FROM comentarios    WHERE id_comentario  = r.id_contenido)
        END AS autor_id,
        CASE
            WHEN r.tipo = 'publicacion' THEN (SELECT u2.username FROM publicaciones p2 JOIN usuarios u2 ON u2.id_usuario=p2.id_usuario WHERE p2.id_publicacion = r.id_contenido)
            WHEN r.tipo = 'comentario'  THEN (SELECT u2.username FROM comentarios   c2 JOIN usuarios u2 ON u2.id_usuario=c2.id_usuario WHERE c2.id_comentario  = r.id_contenido)
        END AS autor_username
    FROM reportes r
    JOIN usuarios u_rep ON u_rep.id_usuario = r.id_reportador
    LEFT JOIN usuarios u_mod ON u_mod.id_usuario = r.id_moderador
    $where_rep
    ORDER BY r.fecha_reporte DESC
    LIMIT 100
")->fetchAll(PDO::FETCH_ASSOC);

$total_pendientes = $pdo->query("SELECT COUNT(*) FROM reportes WHERE estado='pendiente'")->fetchColumn();

?>
<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="UTF-8">
    <title>Panel de administración · 8Mangos</title>
    <link rel="stylesheet" href="assets/css/style.css">
    <link rel="stylesheet" href="assets/css/variables.css">
    <link rel="shortcut icon" href="assets/images/8mangos.png">
    <style>
        *,
        *::before,
        *::after {
            box-sizing: border-box;
        }

        body {
            font-family: var(--font-main);
            background: var(--bg-deep);
            color: var(--texto-general);
            margin: 0;
        }

        main {
            display: flex;
            align-items: flex-start;
            min-height: 100vh;
        }

        .admin-wrapper {
            flex: 1;
            padding: 28px 32px;
            max-width: 1200px;
        }

        /* Cabecera */
        .admin-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            margin-bottom: 28px;
        }

        .admin-header h1 {
            margin: 0;
            font-size: 22px;
            font-weight: 700;
            background: linear-gradient(to right, #fff, var(--magenta-main));
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
        }

        .admin-badge {
            font-size: 11px;
            font-weight: 700;
            padding: 3px 10px;
            border-radius: 20px;
            letter-spacing: 0.05em;
            text-transform: uppercase;
        }

        .badge-admin {
            background: rgba(209, 44, 125, 0.15);
            color: var(--magenta-glow-claro);
            border: 1px solid var(--magenta-main);
        }

        .badge-moderador {
            background: rgba(26, 79, 173, 0.2);
            color: var(--blue-light);
            border: 1px solid var(--blue-main);
        }

        .badge-usuario {
            background: rgba(255, 255, 255, 0.06);
            color: var(--text-low);
            border: 1px solid var(--border-soft);
        }

        .badge-baneado {
            background: rgba(239, 68, 68, 0.12);
            color: #f87171;
            border: 1px solid #ef4444;
        }

        .badge-shadowban {
            background: rgba(139, 92, 246, 0.15);
            color: #a78bfa;
            border: 1px solid #7c3aed;
        }

        .badge-limite {
            background: rgba(251, 146, 60, 0.15);
            color: #fb923c;
            border: 1px solid #ea580c;
        }

        .badge-mute {
            background: rgba(251, 146, 60, 0.15);
            color: #fb923c;
            border: 1px solid #ea580c;
        }

        .badge-shadowban-comentarios {
            background: rgba(139, 92, 246, 0.1);
            color: #c4b5fd;
            border: 1px solid #6d28d9;
        }

        /* Estadísticas */
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
            gap: 14px;
            margin-bottom: 28px;
        }

        .stat-card {
            background: var(--bg-card);
            border: 1px solid var(--border-soft);
            border-radius: 14px;
            padding: 18px 20px;
            display: flex;
            flex-direction: column;
            gap: 6px;
        }

        .stat-card .stat-icon {
            font-size: 22px;
        }

        .stat-card .stat-value {
            font-size: 26px;
            font-weight: 700;
            line-height: 1;
        }

        .stat-card .stat-label {
            font-size: 12px;
            color: var(--text-low);
        }

        /* Filtros */
        .filtros {
            display: flex;
            gap: 10px;
            align-items: center;
            flex-wrap: wrap;
            margin-bottom: 20px;
        }

        .filtros form {
            display: flex;
            gap: 8px;
            flex-wrap: wrap;
            flex: 1;
        }

        .filtros input[type="text"] {
            background: var(--bg-card);
            border: 1px solid var(--border-soft);
            border-radius: 10px;
            padding: 8px 14px;
            color: var(--texto-general);
            font-size: 13px;
            outline: none;
            flex: 1;
            min-width: 160px;
            transition: border-color 0.2s;
        }

        .filtros input[type="text"]:focus {
            border-color: var(--magenta-main);
        }

        .filtros select {
            background: var(--bg-card);
            border: 1px solid var(--border-soft);
            border-radius: 10px;
            padding: 8px 12px;
            color: var(--texto-general);
            font-size: 13px;
            outline: none;
            cursor: pointer;
        }

        .btn-filtrar {
            background: var(--blue-main);
            border: none;
            border-radius: 10px;
            padding: 8px 16px;
            color: #fff;
            font-size: 13px;
            font-weight: 600;
            cursor: pointer;
            transition: background 0.15s;
            font-family: inherit;
        }

        .btn-filtrar:hover {
            background: var(--blue-light);
        }

        /* Tabla */
        .table-wrap {
            background: var(--bg-card);
            border: 1px solid var(--border-soft);
            border-radius: 16px;
            overflow: hidden;
        }

        table {
            width: 100%;
            border-collapse: collapse;
            font-size: 13px;
        }

        thead th {
            padding: 13px 16px;
            text-align: left;
            font-size: 11px;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.06em;
            color: var(--text-low);
            border-bottom: 1px solid var(--border-soft);
            white-space: nowrap;
        }

        tbody tr {
            border-bottom: 1px solid rgba(255, 255, 255, 0.04);
            transition: background 0.12s;
        }

        tbody tr:last-child {
            border-bottom: none;
        }

        tbody tr:hover {
            background: rgba(255, 255, 255, 0.025);
        }

        td {
            padding: 12px 16px;
            vertical-align: middle;
        }

        .user-cell {
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .user-cell img {
            width: 34px;
            height: 34px;
            border-radius: 50%;
            object-fit: cover;
            flex-shrink: 0;
            border-radius: 50% !important;
        }

        .user-cell .user-name {
            font-weight: 600;
            font-size: 13px;
        }

        .user-cell .user-email {
            font-size: 11px;
            color: var(--text-low);
            margin-top: 1px;
        }

        /* Acciones */
        .acciones {
            display: flex;
            gap: 6px;
            flex-wrap: wrap;
        }

        .btn-accion {
            border: none;
            border-radius: 7px;
            padding: 5px 10px;
            font-size: 12px;
            font-weight: 600;
            cursor: pointer;
            font-family: inherit;
            transition: opacity 0.15s, transform 0.12s;
            white-space: nowrap;
        }

        .btn-accion:hover {
            opacity: 0.85;
            transform: scale(1.03);
        }

        .btn-rol {
            background: rgba(26, 79, 173, 0.2);
            color: var(--blue-light);
            border: 1px solid var(--blue-main);
        }

        .btn-ban {
            background: rgba(239, 68, 68, 0.12);
            color: #f87171;
            border: 1px solid rgba(239, 68, 68, 0.3);
        }

        .btn-unban {
            background: rgba(76, 175, 80, 0.12);
            color: #4ade80;
            border: 1px solid rgba(76, 175, 80, 0.3);
        }

        .btn-delete {
            background: rgba(239, 68, 68, 0.18);
            color: #f87171;
            border: 1px solid #ef4444;
        }

        /* Selector de rol inline */
        .rol-form {
            display: flex;
            gap: 6px;
            align-items: center;
        }

        .rol-form select {
            background: var(--bg-deep);
            border: 1px solid var(--border-soft);
            border-radius: 7px;
            padding: 4px 8px;
            color: var(--texto-general);
            font-size: 12px;
            outline: none;
            cursor: pointer;
        }

        /* Paginación */
        .paginacion {
            display: flex;
            justify-content: center;
            gap: 6px;
            margin-top: 20px;
        }

        .paginacion a,
        .paginacion span {
            display: flex;
            align-items: center;
            justify-content: center;
            width: 34px;
            height: 34px;
            border-radius: 8px;
            font-size: 13px;
            font-weight: 600;
            text-decoration: none;
            border: 1px solid var(--border-soft);
            color: var(--texto-general);
            transition: background 0.15s;
        }

        .paginacion a:hover {
            background: rgba(255, 255, 255, 0.06);
        }

        .paginacion span.current {
            background: var(--magenta-main);
            border-color: var(--magenta-main);
        }

        .no-results {
            text-align: center;
            padding: 40px;
            color: var(--text-low);
            font-size: 14px;
        }

        /* ── Modal de sanción — multi-sanción ── */
        .modal-sancion {
            display: none;
            position: fixed;
            inset: 0;
            background: rgba(0, 0, 0, 0.75);
            backdrop-filter: blur(5px);
            z-index: 9999;
            align-items: center;
            justify-content: center;
        }

        .modal-sancion.open {
            display: flex;
        }

        .modal-sancion-box {
            background: var(--bg-card);
            border: 1px solid var(--border-soft);
            border-radius: 16px;
            padding: 26px;
            width: 480px;
            max-width: 95vw;
            box-shadow: 0 20px 60px rgba(0, 0, 0, 0.6);
        }

        .modal-sancion-box h3 {
            margin: 0 0 4px;
            font-size: 17px;
        }

        .modal-sancion-box .modal-subtitle {
            color: var(--text-low);
            font-size: 13px;
            margin-bottom: 20px;
        }

        .sancion-row {
            border: 1px solid var(--border-soft);
            border-radius: 10px;
            padding: 12px 14px;
            margin-bottom: 8px;
            transition: border-color 0.15s, background 0.15s;
        }

        .sancion-row.active {
            border-color: var(--magenta-main);
            background: rgba(209, 44, 125, 0.06);
        }

        .sancion-row-header {
            display: flex;
            align-items: center;
            gap: 10px;
            cursor: pointer;
        }

        .sancion-row-header .s-icon {
            font-size: 18px;
            flex-shrink: 0;
        }

        .sancion-row-header .s-info {
            flex: 1;
        }

        .sancion-row-header .s-label {
            font-size: 13px;
            font-weight: 600;
            color: var(--texto-general);
        }

        .sancion-row-header .s-desc {
            font-size: 11px;
            color: var(--text-low);
            margin-top: 1px;
        }

        /* Toggle switch */
        .toggle-switch {
            position: relative;
            width: 36px;
            height: 20px;
            flex-shrink: 0;
        }

        .toggle-switch input {
            opacity: 0;
            width: 0;
            height: 0;
        }

        .toggle-track {
            position: absolute;
            inset: 0;
            background: var(--border-soft);
            border-radius: 20px;
            cursor: pointer;
            transition: background 0.2s;
        }

        .toggle-track::after {
            content: '';
            position: absolute;
            width: 14px;
            height: 14px;
            left: 3px;
            top: 3px;
            background: #fff;
            border-radius: 50%;
            transition: transform 0.2s;
        }

        .toggle-switch input:checked+.toggle-track {
            background: var(--magenta-main);
        }

        .toggle-switch input:checked+.toggle-track::after {
            transform: translateX(16px);
        }

        .sancion-extra-row {
            display: none;
            margin-top: 10px;
            padding-top: 10px;
            border-top: 1px solid var(--border-soft);
            align-items: center;
            gap: 8px;
            font-size: 13px;
            color: var(--text-low);
        }

        .sancion-extra-row.visible {
            display: flex;
        }

        .sancion-extra-row input[type="number"] {
            width: 65px;
            background: var(--bg-deep);
            border: 1px solid var(--border-soft);
            border-radius: 7px;
            padding: 5px 8px;
            color: var(--texto-general);
            font-size: 13px;
            outline: none;
            font-family: inherit;
        }

        .sancion-extra-row input:focus {
            border-color: var(--magenta-main);
        }

        .modal-sancion-btns {
            display: flex;
            gap: 10px;
            justify-content: flex-end;
            margin-top: 18px;
        }

        .btn-cancelar-modal {
            background: none;
            border: 1px solid var(--border-soft);
            border-radius: 10px;
            padding: 9px 18px;
            color: var(--text-low);
            cursor: pointer;
            font-size: 14px;
            font-family: inherit;
        }

        .btn-cancelar-modal:hover {
            color: var(--texto-general);
        }

        .btn-aplicar-sancion {
            background: var(--magenta-main);
            border: none;
            border-radius: 10px;
            padding: 9px 20px;
            color: #fff;
            cursor: pointer;
            font-size: 14px;
            font-weight: 600;
            font-family: inherit;
            transition: background 0.15s;
        }

        .btn-aplicar-sancion:hover {
            background: var(--magenta-glow);
        }

        /* ── Tabs de navegación ──────────────────────────────────────── */
        .admin-tabs {
            display: flex;
            gap: 8px;
            margin-bottom: 22px;
            border-bottom: 2px solid var(--border-soft);
            padding-bottom: 0;
        }

        .admin-tab {
            padding: 10px 20px;
            border-radius: 8px 8px 0 0;
            font-size: 14px;
            font-weight: 600;
            color: var(--text-low, #888);
            text-decoration: none;
            position: relative;
            transition: color .15s, background .15s;
            display: flex;
            align-items: center;
            gap: 7px;
            margin-bottom: -2px;
            border: 2px solid transparent;
            border-bottom: none;
        }

        .admin-tab:hover {
            color: var(--texto-general);
            background: var(--bg-card, #1e1e2e);
        }

        .admin-tab.active {
            color: var(--texto-general);
            background: var(--bg-card, #1e1e2e);
            border-color: var(--border-soft);
            border-bottom-color: var(--bg-card, #1e1e2e);
        }

        .badge-count {
            background: #e53935;
            color: #fff;
            border-radius: 20px;
            font-size: 11px;
            font-weight: 700;
            padding: 1px 7px;
            min-width: 20px;
            text-align: center;
        }

        /* ── Filtros de reportes ─────────────────────────────────────── */
        .reportes-filtros {
            display: flex;
            gap: 8px;
            margin-bottom: 20px;
            flex-wrap: wrap;
        }

        .rep-filtro-btn {
            padding: 7px 16px;
            border-radius: 20px;
            font-size: 13px;
            font-weight: 500;
            color: var(--text-low, #888);
            text-decoration: none;
            border: 1px solid var(--border-soft);
            transition: all .15s;
        }

        .rep-filtro-btn:hover {
            color: var(--texto-general);
            border-color: var(--texto-general);
        }

        .rep-filtro-btn.activo {
            background: var(--magenta-main, #e040fb);
            color: #fff;
            border-color: var(--magenta-main, #e040fb);
        }

        /* ── Lista de reportes ───────────────────────────────────────── */
        .reportes-lista {
            display: flex;
            flex-direction: column;
            gap: 14px;
        }

        .reporte-card {
            background: var(--bg-card, #1e1e2e);
            border-radius: 12px;
            border: 1px solid var(--border-soft);
            overflow: hidden;
            transition: border-color .15s;
        }

        .reporte-card.estado-pendiente {
            border-left: 3px solid #ff9800;
        }

        .reporte-card.estado-revisado {
            border-left: 3px solid #4caf50;
        }

        .reporte-card.estado-descartado {
            border-left: 3px solid #607d8b;
            opacity: .7;
        }

        .reporte-card-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 12px 18px;
            border-bottom: 1px solid var(--border-soft);
            background: rgba(255, 255, 255, .03);
        }

        .reporte-tipo-badge {
            font-size: 13px;
            font-weight: 600;
        }

        .reporte-meta {
            display: flex;
            align-items: center;
            gap: 12px;
        }

        .reporte-fecha {
            font-size: 12px;
            opacity: .55;
        }

        .estado-pill-pendiente {
            background: rgba(255, 152, 0, .15);
            color: #ff9800;
            border-radius: 20px;
            padding: 2px 10px;
            font-size: 12px;
            font-weight: 600;
        }

        .estado-pill-revisado {
            background: rgba(76, 175, 80, .15);
            color: #4caf50;
            border-radius: 20px;
            padding: 2px 10px;
            font-size: 12px;
            font-weight: 600;
        }

        .estado-pill-descartado {
            background: rgba(96, 125, 139, .15);
            color: #90a4ae;
            border-radius: 20px;
            padding: 2px 10px;
            font-size: 12px;
            font-weight: 600;
        }

        .reporte-card-body {
            padding: 16px 18px;
        }

        .reporte-contenido-box {
            background: var(--bg-deep, #0f0f1a);
            border-radius: 8px;
            padding: 12px 14px;
            margin-bottom: 12px;
            border: 1px solid var(--border-soft);
        }

        .reporte-texto {
            margin: 0 0 6px;
            font-size: 14px;
            line-height: 1.5;
            color: var(--texto-general);
        }

        .reporte-texto.eliminado {
            color: #f44336;
            font-style: italic;
            font-size: 13px;
        }

        .reporte-autor {
            font-size: 12px;
            color: var(--magenta-main, #e040fb);
            text-decoration: none;
            opacity: .8;
        }

        .reporte-autor:hover {
            opacity: 1;
        }

        .reporte-info-row {
            display: flex;
            align-items: center;
            gap: 16px;
            font-size: 13px;
            margin-bottom: 10px;
            flex-wrap: wrap;
            opacity: .75;
        }

        .reporte-motivo strong {
            color: var(--texto-general);
            opacity: 1;
        }

        .reporte-notas {
            font-size: 13px;
            color: var(--text-low, #888);
            margin-bottom: 10px;
            padding: 8px 12px;
            background: rgba(255, 255, 255, .04);
            border-radius: 6px;
        }

        /* ── Botones de acción sobre reporte ─────────────────────────── */
        .reporte-acciones {
            display: flex;
            gap: 8px;
            flex-wrap: wrap;
            margin-top: 14px;
            padding-top: 12px;
            border-top: 1px solid var(--border-soft);
        }

        .btn-rep {
            padding: 7px 14px;
            border-radius: 8px;
            font-size: 12px;
            font-weight: 600;
            cursor: pointer;
            border: none;
            transition: opacity .15s, transform .1s;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 4px;
        }

        .btn-rep:hover {
            opacity: .85;
            transform: translateY(-1px);
        }

        .btn-rep-ver {
            background: #2196f3;
            color: #fff;
        }

        .btn-rep-descartar {
            background: var(--bg-deep, #0f0f1a);
            color: var(--text-low, #888);
            border: 1px solid var(--border-soft);
        }

        .btn-rep-revisar {
            background: #4caf50;
            color: #fff;
        }

        .btn-rep-eliminar {
            background: #f44336;
            color: #fff;
        }

        .btn-rep-sancionar {
            background: #ff9800;
            color: #fff;
        }

        /* ── Empty state ─────────────────────────────────────────────── */
        .reportes-empty {
            text-align: center;
            padding: 60px 20px;
            opacity: .5;
        }

        .reportes-empty p {
            font-size: 16px;
            margin-top: 12px;
        }
    </style>
</head>

<body>
    <main>
        <?php include('includes/WIP_aside.php') ?>

        <div class="admin-wrapper">
            <div class="admin-header">
                <h1>🛡️ Panel de administración</h1>
                <span class="admin-badge badge-<?= $_SESSION['rol'] ?>">
                    <?= htmlspecialchars($_SESSION['rol']) ?>
                </span>
            </div>

            <!-- Stats -->
            <div class="stats-grid">
                <div class="stat-card">
                    <div class="stat-icon">👥</div>
                    <div class="stat-value"><?= number_format($stats['total_usuarios']) ?></div>
                    <div class="stat-label">Usuarios</div>
                </div>
                <div class="stat-card">
                    <div class="stat-icon">🛡️</div>
                    <div class="stat-value"><?= $stats['total_admins'] ?></div>
                    <div class="stat-label">Administradores</div>
                </div>
                <div class="stat-card">
                    <div class="stat-icon">🔧</div>
                    <div class="stat-value"><?= $stats['total_mods'] ?></div>
                    <div class="stat-label">Moderadores</div>
                </div>
                <div class="stat-card">
                    <div class="stat-icon">🚫</div>
                    <div class="stat-value"><?= $stats['total_baneados'] ?></div>
                    <div class="stat-label">Baneados</div>
                </div>
                <div class="stat-card">
                    <div class="stat-icon">👻</div>
                    <div class="stat-value"><?= $stats['total_shadowban'] ?></div>
                    <div class="stat-label">Shadowban</div>
                </div>
                <div class="stat-card">
                    <div class="stat-icon">🔇</div>
                    <div class="stat-value"><?= $stats['total_limitados'] ?></div>
                    <div class="stat-label">Limitados</div>
                </div>
                <div class="stat-card">
                    <div class="stat-icon">📝</div>
                    <div class="stat-value"><?= number_format($stats['total_posts']) ?></div>
                    <div class="stat-label">Publicaciones</div>
                </div>
                <div class="stat-card">
                    <div class="stat-icon">💬</div>
                    <div class="stat-value"><?= number_format($stats['total_comentarios']) ?></div>
                    <div class="stat-label">Comentarios</div>
                </div>
            </div>

            <!-- Tabs de navegación -->
            <?php $tab_activa = $_GET['tab'] ?? 'usuarios'; ?>
            <div class="admin-tabs">
                <a href="admin.php?tab=usuarios" class="admin-tab <?= $tab_activa === 'usuarios' ? 'active' : '' ?>">
                    👥 Usuarios
                </a>
                <a href="admin.php?tab=reportes&estado_rep=pendiente" class="admin-tab <?= $tab_activa === 'reportes' ? 'active' : '' ?>">
                    🚩 Reportes
                    <?php if ($total_pendientes > 0): ?>
                        <span class="badge-count"><?= $total_pendientes ?></span>
                    <?php endif; ?>
                </a>
            </div>

            <?php if ($tab_activa === 'reportes'): ?>
                <!-- ═══════════════════ SECCIÓN REPORTES ═══════════════════ -->
                <div class="reportes-filtros">
                    <a href="admin.php?tab=reportes&estado_rep=pendiente" class="rep-filtro-btn <?= $filtro_reportes === 'pendiente' ? 'activo' : '' ?>">⏳ Pendientes</a>
                    <a href="admin.php?tab=reportes&estado_rep=revisado" class="rep-filtro-btn <?= $filtro_reportes === 'revisado'  ? 'activo' : '' ?>">✅ Revisados</a>
                    <a href="admin.php?tab=reportes&estado_rep=descartado" class="rep-filtro-btn <?= $filtro_reportes === 'descartado' ? 'activo' : '' ?>">🗑️ Descartados</a>
                    <a href="admin.php?tab=reportes&estado_rep=todos" class="rep-filtro-btn <?= $filtro_reportes === 'todos'     ? 'activo' : '' ?>">📋 Todos</a>
                </div>

                <?php if (empty($reportes)): ?>
                    <div class="reportes-empty">
                        <span style="font-size:48px;">✅</span>
                        <p>No hay reportes <?= $filtro_reportes !== 'todos' ? $filtro_reportes . 's' : '' ?>.</p>
                    </div>
                <?php else: ?>
                    <div class="reportes-lista">
                        <?php foreach ($reportes as $rep): ?>
                            <div class="reporte-card estado-<?= $rep['estado'] ?>">
                                <div class="reporte-card-header">
                                    <div class="reporte-tipo-badge">
                                        <?= $rep['tipo'] === 'publicacion' ? '📝 Publicación' : '💬 Comentario' ?>
                                    </div>
                                    <div class="reporte-meta">
                                        <span class="reporte-estado estado-pill-<?= $rep['estado'] ?>">
                                            <?php
                                            $labels = ['pendiente' => '⏳ Pendiente', 'revisado' => '✅ Revisado', 'descartado' => '🗑️ Descartado'];
                                            echo $labels[$rep['estado']] ?? $rep['estado'];
                                            ?>
                                        </span>
                                        <span class="reporte-fecha"><?= htmlspecialchars(date('d/m/Y H:i', strtotime($rep['fecha_reporte']))) ?></span>
                                    </div>
                                </div>

                                <div class="reporte-card-body">
                                    <!-- Contenido reportado -->
                                    <div class="reporte-contenido-box">
                                        <?php if (!empty($rep['contenido_texto'])): ?>
                                            <p class="reporte-texto"><?= nl2br(htmlspecialchars(mb_strimwidth($rep['contenido_texto'], 0, 200, '...'))) ?></p>
                                        <?php else: ?>
                                            <p class="reporte-texto eliminado">⚠️ Este contenido ya fue eliminado.</p>
                                        <?php endif; ?>
                                        <?php if (!empty($rep['autor_username'])): ?>
                                            <a href="perfil.php?id=<?= $rep['autor_id'] ?>" class="reporte-autor">
                                                por @<?= htmlspecialchars($rep['autor_username']) ?>
                                            </a>
                                        <?php endif; ?>
                                    </div>

                                    <!-- Info del reporte -->
                                    <div class="reporte-info-row">
                                        <span>🚩 Reportado por <a href="perfil.php?id=<?= $rep['reporter_id'] ?>" class="reporte-autor">@<?= htmlspecialchars($rep['reporter_username']) ?></a></span>
                                        <span class="reporte-motivo">Motivo: <strong><?= htmlspecialchars($rep['motivo']) ?></strong></span>
                                    </div>

                                    <?php if ($rep['notas_moderador']): ?>
                                        <div class="reporte-notas">
                                            💬 <em><?= htmlspecialchars($rep['notas_moderador']) ?></em>
                                            <?php if ($rep['moderador_username']): ?>
                                                — @<?= htmlspecialchars($rep['moderador_username']) ?>
                                                <?php if ($rep['fecha_revision']): ?>
                                                    <span style="opacity:.6;font-size:11px;"> el <?= date('d/m/Y H:i', strtotime($rep['fecha_revision'])) ?></span>
                                                <?php endif; ?>
                                            <?php endif; ?>
                                        </div>
                                    <?php endif; ?>

                                    <!-- Acciones (solo si pendiente y el contenido existe) -->
                                    <?php if ($rep['estado'] === 'pendiente'): ?>
                                        <div class="reporte-acciones">
                                            <!-- Ver contenido -->
                                            <?php if (!empty($rep['contenido_texto']) && $rep['tipo'] === 'publicacion'): ?>
                                                <a href="post.php?id=<?= $rep['id_contenido'] ?>" class="btn-rep btn-rep-ver" title="Ver publicación">👁️ Ver</a>
                                            <?php endif; ?>

                                            <!-- Descartar -->
                                            <button class="btn-rep btn-rep-descartar"
                                                onclick="accionReporte(<?= $rep['id_reporte'] ?>, 'descartar')">
                                                🗑️ Descartar
                                            </button>

                                            <!-- Marcar revisado -->
                                            <button class="btn-rep btn-rep-revisar"
                                                onclick="accionReporte(<?= $rep['id_reporte'] ?>, 'revisar')">
                                                ✅ Revisado
                                            </button>

                                            <!-- Eliminar contenido -->
                                            <?php if (!empty($rep['contenido_texto'])): ?>
                                                <button class="btn-rep btn-rep-eliminar"
                                                    onclick="accionReporte(<?= $rep['id_reporte'] ?>, 'eliminar_contenido')">
                                                    ⛔ Eliminar contenido
                                                </button>
                                            <?php endif; ?>

                                            <!-- Sancionar autor -->
                                            <?php if (!empty($rep['autor_id']) && !empty($rep['autor_username'])): ?>
                                                <button class="btn-rep btn-rep-sancionar"
                                                    onclick="abrirModalSancion(
                                        <?= $rep['autor_id'] ?>,
                                        '<?= addslashes(htmlspecialchars($rep['autor_username'])) ?>',
                                        {}
                                    )">
                                                    🔨 Sancionar @<?= htmlspecialchars($rep['autor_username']) ?>
                                                </button>
                                            <?php endif; ?>
                                        </div>
                                    <?php endif; ?>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>

            <?php else: ?>
                <!-- ═══════════════════ SECCIÓN USUARIOS ═══════════════════ -->

                <!-- Filtros -->
                <div class="filtros">
                    <form method="GET" action="admin.php">
                        <input type="text" name="q" value="<?= htmlspecialchars($buscar) ?>" placeholder="Buscar usuario, nombre o email...">
                        <select name="rol">
                            <option value="todos" <?= $filtro === 'todos' ? 'selected' : '' ?>>Todos los roles</option>
                            <option value="usuario" <?= $filtro === 'usuario' ? 'selected' : '' ?>>Usuarios</option>
                            <option value="moderador" <?= $filtro === 'moderador' ? 'selected' : '' ?>>Moderadores</option>
                            <option value="admin" <?= $filtro === 'admin' ? 'selected' : '' ?>>Administradores</option>
                        </select>
                        <button type="submit" class="btn-filtrar">Buscar</button>
                        <?php if ($buscar || $filtro !== 'todos'): ?>
                            <a href="admin.php" style="padding:8px 14px;border-radius:10px;border:1px solid var(--border-soft);color:var(--text-low);font-size:13px;text-decoration:none;">✕ Limpiar</a>
                        <?php endif; ?>
                    </form>
                </div>

                <!-- Tabla -->
                <div class="table-wrap">
                    <table>
                        <thead>
                            <tr>
                                <th>Usuario</th>
                                <th>Rol</th>
                                <th>Posts</th>
                                <th>Seguidores</th>
                                <th>Registro</th>
                                <th>Acciones</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($usuarios)): ?>
                                <tr>
                                    <td colspan="6" class="no-results">No se encontraron usuarios.</td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($usuarios as $u):
                                    $esBaneado = $u['verificado'] == -1;
                                    $esYo      = $u['id_usuario'] == $mi_id;
                                ?>
                                    <tr>
                                        <td>
                                            <a href="perfil.php?id=<?= $u['id_usuario'] ?>" class="user-cell" style="text-decoration:none;color:inherit;">
                                                <img src="<?= avatarSrc($u['foto_perfil'], $u['username']) ?>" alt="" style="width:34px;height:34px;border-radius:50%;object-fit:cover;flex-shrink:0;">
                                                <div>
                                                    <div class="user-name">@<?= htmlspecialchars($u['username']) ?></div>
                                                    <div class="user-email"><?= htmlspecialchars($u['email']) ?></div>
                                                </div>
                                            </a>
                                        </td>
                                        <td>
                                            <?php
                                            $ahora = new DateTime();
                                            $tiene_ban  = !empty($u['ban_expira']) && ($u['ban_expira'] === '9999-01-01 00:00:00' || $ahora < new DateTime($u['ban_expira']));
                                            $tiene_mute = !empty($u['mute_expira']) && $ahora < new DateTime($u['mute_expira']);
                                            $tiene_sb   = (bool)(int)($u['shadowban'] ?? 0);
                                            $tiene_sbc  = (bool)(int)($u['shadowban_comentarios'] ?? 0);
                                            $tiene_lim  = (int)($u['limite_comentarios_dia'] ?? 0) > 0;
                                            $hay_algo   = $tiene_ban || $tiene_sb || $tiene_mute || $tiene_sbc || $tiene_lim;
                                            ?>
                                            <?php if (!$hay_algo): ?>
                                                <span class="admin-badge badge-<?= $u['rol'] ?>"><?= htmlspecialchars($u['rol']) ?></span>
                                            <?php else: ?>
                                                <div style="display:flex;flex-wrap:wrap;gap:3px;">
                                                    <?php if ($tiene_ban): ?>
                                                        <span class="admin-badge badge-baneado"><?= $u['ban_expira'] === '9999-01-01 00:00:00' ? '🚫 Permanente' : '⏱️ Ban ' . date('d/m/y', strtotime($u['ban_expira'])) ?></span>
                                                    <?php endif; ?>
                                                    <?php if ($tiene_sb): ?>
                                                        <span class="admin-badge badge-shadowban">👻 Shadow</span>
                                                    <?php endif; ?>
                                                    <?php if ($tiene_mute): ?>
                                                        <span class="admin-badge badge-mute">🤐 Mute <?= date('d/m/y', strtotime($u['mute_expira'])) ?></span>
                                                    <?php endif; ?>
                                                    <?php if ($tiene_lim): ?>
                                                        <span class="admin-badge badge-limite">🔇 <?= $u['limite_comentarios_dia'] ?>/día</span>
                                                    <?php endif; ?>
                                                    <?php if ($tiene_sbc): ?>
                                                        <span class="admin-badge badge-shadowban-comentarios">🫥 Com.ocultos</span>
                                                    <?php endif; ?>
                                                </div>
                                            <?php endif; ?>
                                        </td>
                                        <td><?= $u['num_posts'] ?></td>
                                        <td><?= $u['num_seguidores'] ?></td>
                                        <td><?= date('d/m/Y', strtotime($u['fecha_registro'])) ?></td>
                                        <td>
                                            <?php if ($esYo): ?>
                                                <span style="font-size:12px;color:var(--text-low);">Eres tú</span>
                                            <?php else: ?>
                                                <div class="acciones">
                                                    <!-- Cambiar rol -->
                                                    <?php if ($_SESSION['rol'] === 'admin'): ?>
                                                        <form method="POST" action="admin.php" class="rol-form">
                                                            <input type="hidden" name="accion" value="cambiar_rol">
                                                            <input type="hidden" name="id_usuario" value="<?= $u['id_usuario'] ?>">
                                                            <select name="nuevo_rol">
                                                                <option value="usuario" <?= $u['rol'] === 'usuario'   ? 'selected' : '' ?>>Usuario</option>
                                                                <option value="moderador" <?= $u['rol'] === 'moderador' ? 'selected' : '' ?>>Moderador</option>
                                                                <option value="admin" <?= $u['rol'] === 'admin'     ? 'selected' : '' ?>>Admin</option>
                                                            </select>
                                                            <button type="submit" class="btn-accion btn-rol">✓</button>
                                                        </form>
                                                    <?php endif; ?>

                                                    <!-- Sancionar -->
                                                    <?php if ($hay_algo): ?>
                                                        <form method="POST" action="admin.php" style="display:inline;">
                                                            <input type="hidden" name="accion" value="levantar_todo">
                                                            <input type="hidden" name="id_usuario" value="<?= $u['id_usuario'] ?>">
                                                            <button type="submit" class="btn-accion btn-unban">✅ Levantar todo</button>
                                                        </form>
                                                    <?php endif; ?>

                                                    <button class="btn-accion btn-ban"
                                                        onclick="abrirModalSancion(<?= $u['id_usuario'] ?>, '<?= htmlspecialchars($u['username'], ENT_QUOTES) ?>', {
                                                ban: <?= $tiene_ban ? 'true' : 'false' ?>,
                                                ban_perm: <?= (!empty($u['ban_expira']) && $u['ban_expira'] === '9999-01-01 00:00:00') ? 'true' : 'false' ?>,
                                                sb: <?= $tiene_sb ? 'true' : 'false' ?>,
                                                mute: <?= $tiene_mute ? 'true' : 'false' ?>,
                                                lim: <?= (int)($u['limite_comentarios_dia'] ?? 0) ?>,
                                                sbc: <?= $tiene_sbc ? 'true' : 'false' ?>
                                            })">
                                                        ⚡ Sancionar
                                                    </button>

                                                    <!-- Eliminar (solo admin) -->
                                                    <?php if ($_SESSION['rol'] === 'admin'): ?>
                                                        <form method="POST" action="admin.php" style="display:inline;"
                                                            onsubmit="return confirm('¿Eliminar @<?= htmlspecialchars($u['username']) ?>? Esta acción es irreversible.')">
                                                            <input type="hidden" name="accion" value="eliminar">
                                                            <input type="hidden" name="id_usuario" value="<?= $u['id_usuario'] ?>">
                                                            <button type="submit" class="btn-accion btn-delete">🗑️</button>
                                                        </form>
                                                    <?php endif; ?>
                                                </div>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>

                <!-- Paginación -->
                <?php if ($total_pags > 1): ?>
                    <div class="paginacion">
                        <?php
                        $base = 'admin.php?' . http_build_query(array_filter(['q' => $buscar, 'rol' => $filtro !== 'todos' ? $filtro : '']));
                        for ($i = 1; $i <= $total_pags; $i++):
                            if ($i === $pagina): ?>
                                <span class="current"><?= $i ?></span>
                            <?php else: ?>
                                <a href="<?= $base ?>&p=<?= $i ?>"><?= $i ?></a>
                        <?php endif;
                        endfor; ?>
                    </div>
                <?php endif; ?>

            <?php endif; // fin tab usuarios 
            ?>

        </div>
    </main>
    <!-- Modal de sanción (multi) -->
    <div class="modal-sancion" id="modal-sancion">
        <div class="modal-sancion-box">
            <h3>⚡ Sancionar usuario</h3>
            <p class="modal-subtitle" id="modal-sancion-subtitle">@usuario</p>

            <form method="POST" action="admin.php" id="form-sancion">
                <input type="hidden" name="accion" value="sancionar">
                <input type="hidden" name="id_usuario" id="sancion-id-usuario" value="">

                <!-- Ban de acceso -->
                <div class="sancion-row" id="row-ban">
                    <label class="sancion-row-header">
                        <span class="s-icon">🚫</span>
                        <span class="s-info">
                            <span class="s-label">Ban de acceso</span>
                            <span class="s-desc">El usuario no puede iniciar sesión</span>
                        </span>
                        <label class="toggle-switch">
                            <input type="checkbox" id="toggle-ban" onchange="toggleBan(this)">
                            <span class="toggle-track"></span>
                        </label>
                    </label>
                    <div class="sancion-extra-row" id="extra-ban">
                        <select name="ban" id="sel-ban" style="background:var(--bg-deep);border:1px solid var(--border-soft);border-radius:7px;padding:5px 8px;color:var(--texto-general);font-size:13px;outline:none;">
                            <option value="temporal">Temporal</option>
                            <option value="permanente">Permanente</option>
                        </select>
                        <span id="lbl-ban-dias">durante</span>
                        <input type="number" name="ban_dias" id="input-ban-dias" value="7" min="1" max="365">
                        <span id="sfx-ban-dias">días</span>
                    </div>
                </div>

                <!-- Shadowban -->
                <div class="sancion-row" id="row-sb">
                    <label class="sancion-row-header">
                        <span class="s-icon">👻</span>
                        <span class="s-info">
                            <span class="s-label">Shadowban</span>
                            <span class="s-desc">Invisible en feeds y buscador</span>
                        </span>
                        <label class="toggle-switch">
                            <input type="checkbox" name="shadowban" value="1" id="toggle-sb" onchange="rowActive('row-sb',this.checked)">
                            <span class="toggle-track"></span>
                        </label>
                    </label>
                </div>

                <!-- Mute -->
                <div class="sancion-row" id="row-mute">
                    <label class="sancion-row-header">
                        <span class="s-icon">🤐</span>
                        <span class="s-info">
                            <span class="s-label">Mute de comentarios</span>
                            <span class="s-desc">No puede comentar durante X días</span>
                        </span>
                        <label class="toggle-switch">
                            <input type="checkbox" id="toggle-mute" onchange="toggleMute(this)">
                            <span class="toggle-track"></span>
                        </label>
                    </label>
                    <div class="sancion-extra-row" id="extra-mute">
                        <input type="hidden" name="mute" id="input-mute-hidden" value="ninguno">
                        <span>durante</span>
                        <input type="number" name="mute_dias" value="3" min="1" max="365">
                        <span>días</span>
                    </div>
                </div>

                <!-- Límite comentarios -->
                <div class="sancion-row" id="row-lim">
                    <label class="sancion-row-header">
                        <span class="s-icon">🔇</span>
                        <span class="s-info">
                            <span class="s-label">Límite de comentarios</span>
                            <span class="s-desc">Máximo N por día</span>
                        </span>
                        <label class="toggle-switch">
                            <input type="checkbox" id="toggle-lim" onchange="toggleLim(this)">
                            <span class="toggle-track"></span>
                        </label>
                    </label>
                    <div class="sancion-extra-row" id="extra-lim">
                        <span>Máximo</span>
                        <input type="number" name="limite_comentarios_dia" id="input-lim" value="3" min="1" max="50">
                        <span>comentarios/día</span>
                    </div>
                </div>

                <!-- Shadowban comentarios -->
                <div class="sancion-row" id="row-sbc">
                    <label class="sancion-row-header">
                        <span class="s-icon">🫥</span>
                        <span class="s-info">
                            <span class="s-label">Ocultar comentarios</span>
                            <span class="s-desc">Sus comentarios solo los ve él</span>
                        </span>
                        <label class="toggle-switch">
                            <input type="checkbox" name="shadowban_comentarios" value="1" id="toggle-sbc" onchange="rowActive('row-sbc',this.checked)">
                            <span class="toggle-track"></span>
                        </label>
                    </label>
                </div>

                <div class="modal-sancion-btns">
                    <button type="button" class="btn-cancelar-modal" onclick="cerrarModalSancion()">Cancelar</button>
                    <button type="submit" class="btn-aplicar-sancion">Aplicar</button>
                </div>
            </form>
        </div>
    </div>

    <script>
        // ── Acción sobre un reporte ───────────────────────────────────────────
        function accionReporte(idReporte, accion) {
            const confirmar = {
                'eliminar_contenido': '⚠️ ¿Eliminar este contenido permanentemente? Esta acción no se puede deshacer.',
                'descartar': '¿Descartar este reporte?',
                'revisar': null
            };
            const msg = confirmar[accion];
            if (msg && !confirm(msg)) return;

            const fd = new FormData();
            fd.append('accion', accion);
            fd.append('id_reporte', idReporte);

            fetch('actions/reportar.php', {
                    method: 'POST',
                    body: fd
                })
                .then(r => r.json())
                .then(data => {
                    if (data.status === 'success') {
                        location.reload();
                    } else {
                        alert('Error: ' + data.message);
                    }
                })
                .catch(() => alert('Error de red. Inténtalo de nuevo.'));
        }

        function abrirModalSancion(id, username, sanciones) {
            document.getElementById('sancion-id-usuario').value = id;
            document.getElementById('modal-sancion-subtitle').textContent = '@' + username;

            // Reset
            ['toggle-ban', 'toggle-sb', 'toggle-mute', 'toggle-lim', 'toggle-sbc'].forEach(id => {
                const el = document.getElementById(id);
                if (el) el.checked = false;
            });
            document.getElementById('extra-ban').classList.remove('visible');
            document.getElementById('extra-mute').classList.remove('visible');
            document.getElementById('extra-lim').classList.remove('visible');
            document.getElementById('input-mute-hidden').value = 'ninguno';
            ['row-ban', 'row-sb', 'row-mute', 'row-lim', 'row-sbc'].forEach(id => {
                document.getElementById(id).classList.remove('active');
            });

            // Pre-rellenar sanciones actuales
            if (sanciones.ban) {
                document.getElementById('toggle-ban').checked = true;
                document.getElementById('extra-ban').classList.add('visible');
                document.getElementById('row-ban').classList.add('active');
                document.getElementById('sel-ban').value = sanciones.ban_perm ? 'permanente' : 'temporal';
                toggleBanSelect(document.getElementById('sel-ban'));
            }
            if (sanciones.sb) {
                document.getElementById('toggle-sb').checked = true;
                rowActive('row-sb', true);
            }
            if (sanciones.mute) {
                document.getElementById('toggle-mute').checked = true;
                document.getElementById('extra-mute').classList.add('visible');
                document.getElementById('input-mute-hidden').value = 'temporal';
                rowActive('row-mute', true);
            }
            if (sanciones.lim > 0) {
                document.getElementById('toggle-lim').checked = true;
                document.getElementById('input-lim').value = sanciones.lim;
                document.getElementById('extra-lim').classList.add('visible');
                rowActive('row-lim', true);
            }
            if (sanciones.sbc) {
                document.getElementById('toggle-sbc').checked = true;
                rowActive('row-sbc', true);
            }

            document.getElementById('modal-sancion').classList.add('open');
        }

        function cerrarModalSancion() {
            document.getElementById('modal-sancion').classList.remove('open');
        }

        document.getElementById('modal-sancion').addEventListener('click', function(e) {
            if (e.target === this) cerrarModalSancion();
        });

        function rowActive(rowId, active) {
            document.getElementById(rowId).classList.toggle('active', active);
        }

        function toggleBan(cb) {
            document.getElementById('extra-ban').classList.toggle('visible', cb.checked);
            rowActive('row-ban', cb.checked);
            if (!cb.checked) {
                // Enviar ban=levantar
                document.getElementById('sel-ban').value = 'levantar';
            } else {
                document.getElementById('sel-ban').value = 'temporal';
                toggleBanSelect(document.getElementById('sel-ban'));
            }
        }

        function toggleBanSelect(sel) {
            const dias = document.getElementById('input-ban-dias');
            const lbl = document.getElementById('lbl-ban-dias');
            const sfx = document.getElementById('sfx-ban-dias');
            const perm = sel.value === 'permanente';
            dias.style.display = perm ? 'none' : '';
            lbl.style.display = perm ? 'none' : '';
            sfx.style.display = perm ? 'none' : '';
        }

        document.getElementById('sel-ban').addEventListener('change', function() {
            toggleBanSelect(this);
        });

        function toggleMute(cb) {
            document.getElementById('extra-mute').classList.toggle('visible', cb.checked);
            document.getElementById('input-mute-hidden').value = cb.checked ? 'temporal' : 'levantar';
            rowActive('row-mute', cb.checked);
        }

        function toggleLim(cb) {
            document.getElementById('extra-lim').classList.toggle('visible', cb.checked);
            rowActive('row-lim', cb.checked);
            if (!cb.checked) document.getElementById('input-lim').value = 0;
        }
    </script>
</body>

</html>