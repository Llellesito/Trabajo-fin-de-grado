<?php
session_start();
require 'includes/db.php';
require 'includes/lib.php';

if (!isset($_SESSION['id_usuario'])) {
    header("Location: actions/login.php");
    exit();
}

$mi_id       = (int)$_SESSION['id_usuario'];
$mi_username = $_SESSION['usuario'];

// Datos propios para el avatar
$stmt = $pdo->prepare("SELECT foto_perfil, username FROM usuarios WHERE id_usuario=?");
$stmt->execute([$mi_id]);
$yo = $stmt->fetch(PDO::FETCH_ASSOC);
$mi_avatar = avatarSrc($yo['foto_perfil'], $yo['username']);
?>
<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="UTF-8">
    <title>Mensajes · 8Mangos</title>
    <link rel="stylesheet" href="assets/css/style.css">
    <link rel="stylesheet" href="assets/css/variables.css">
    <link rel="shortcut icon" href="assets/images/8mangos.png">
    <style>
        /* Reset base */
        body,
        html {
            margin: 0;
            padding: 0;
            height: 100%;
            overflow: hidden;
            background: #000;
        }

        /* Contenedor padre */
        .app-wrap {
            display: flex;
            /* Aside izq, Chat der */
            width: 100%;
            height: 100vh;
        }

        /* FIX ASIDE: No se encoge, respeta sus 220px */
        aside.sidebar {
            flex-shrink: 0 !important;
            width: 220px !important;
        }

        /* Chat ocupa el resto */
        .main-chat {
            flex: 1;
            display: flex;
            overflow: hidden;
            border-left: 1px solid #262626;
        }

        /* ══ LAYOUT ══════════════════════════════════════════════════════════════ */
        .mensajes-wrapper {
            display: flex;
            flex: 1;
            height: 100vh;
            overflow: hidden;
            font-family: var(--font-main);
        }

        /* ══ SIDEBAR — Lista de conversaciones ══════════════════════════════════ */
        .conv-list-panel {
            width: 250px;
            flex-shrink: 0;
            display: flex;
            flex-direction: column;
            background: var(--bg-card);
            border-right: 1px solid rgba(255, 255, 255, 0.05);
        }

        .conv-list-header {
            padding: 18px 16px 14px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            border-bottom: 1px solid rgba(255, 255, 255, 0.05);
        }

        .conv-list-header h2 {
            margin: 0;
            font-size: 17px;
            font-weight: 700;
            letter-spacing: -0.3px;
            color: var(--texto-general);
        }

        .btn-nueva-conv {
            width: 32px;
            height: 32px;
            border-radius: 50%;
            border: none;
            background: var(--magenta-main);
            color: #fff;
            font-size: 19px;
            line-height: 1;
            display: flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            flex-shrink: 0;
            transition: background 0.2s, transform 0.15s;
            box-shadow: 0 2px 8px rgba(209, 44, 125, 0.35);
        }

        .btn-nueva-conv:hover {
            background: var(--magenta-glow);
            transform: scale(1.06);
        }

        .conv-search {
            padding: 10px 12px;
            border-bottom: 1px solid rgba(255, 255, 255, 0.04);
        }

        .conv-search-inner {
            display: flex;
            align-items: center;
            gap: 8px;
            background: var(--bg-deep);
            border-radius: 10px;
            padding: 7px 12px;
            border: 1px solid transparent;
            transition: border-color 0.2s;
        }

        .conv-search-inner:focus-within {
            border-color: var(--magenta-main);
        }

        .conv-search-inner svg {
            flex-shrink: 0;
            opacity: 0.4;
        }

        .conv-search-inner input {
            background: none;
            border: none;
            outline: none;
            color: var(--texto-general);
            font-size: 13px;
            width: 100%;
            font-family: inherit;
        }

        .conv-search-inner input::placeholder {
            color: var(--text-low);
        }

        .conv-list {
            flex: 1;
            overflow-y: auto;
            scrollbar-width: thin;
            scrollbar-color: rgba(255, 255, 255, 0.06) transparent;
        }

        /* Cada fila de conversación */
        .conv-item {
            display: flex;
            align-items: center;
            gap: 11px;
            padding: 11px 14px;
            cursor: pointer;
            position: relative;
            transition: background 0.15s;
            border-left: 3px solid transparent;
        }

        .conv-item:hover {
            background: rgba(255, 255, 255, 0.03);
        }

        .conv-item.active {
            background: rgba(209, 44, 125, 0.07);
            border-left-color: var(--magenta-main);
        }

        .conv-item .avatar {
            width: 42px;
            height: 42px;
            border-radius: 50%;
            object-fit: cover;
            flex-shrink: 0;
        }

        .conv-item .conv-info {
            flex: 1;
            min-width: 0;
        }

        .conv-item .conv-name {
            font-size: 14px;
            font-weight: 600;
            color: var(--texto-general);
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }

        .conv-item .conv-preview {
            font-size: 12px;
            color: var(--text-low);
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
            margin-top: 2px;
            line-height: 1.3;
        }

        .conv-item-right {
            display: flex;
            flex-direction: column;
            align-items: flex-end;
            gap: 4px;
            flex-shrink: 0;
        }

        .badge-unread {
            background: var(--magenta-main);
            color: #fff;
            font-size: 10px;
            font-weight: 700;
            border-radius: 10px;
            min-width: 18px;
            height: 18px;
            padding: 0 5px;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .conv-menu-btn {
            display: none;
            background: none;
            border: none;
            color: var(--text-low);
            font-size: 18px;
            cursor: pointer;
            padding: 2px 3px;
            border-radius: 5px;
            line-height: 1;
            flex-shrink: 0;
            transition: background 0.15s, color 0.15s;
        }

        .conv-item:hover .conv-menu-btn {
            display: flex;
            align-items: center;
        }

        .conv-menu-btn:hover {
            background: rgba(255, 255, 255, 0.08);
            color: var(--texto-general);
        }

        .conv-dropdown {
            display: none;
            position: fixed;
            background: var(--bg-card);
            border: 1px solid rgba(255, 255, 255, 0.08);
            border-radius: 10px;
            box-shadow: 0 8px 28px rgba(0, 0, 0, 0.55);
            z-index: 500;
            min-width: 160px;
            overflow: hidden;
        }

        .conv-dropdown.open {
            display: block;
        }

        .conv-dropdown button {
            display: flex;
            align-items: center;
            gap: 9px;
            width: 100%;
            padding: 10px 14px;
            background: none;
            border: none;
            color: var(--texto-general);
            font-size: 13px;
            cursor: pointer;
            text-align: left;
            transition: background 0.12s;
            font-family: inherit;
        }

        .conv-dropdown button:hover {
            background: rgba(255, 255, 255, 0.05);
        }

        .conv-dropdown button.danger {
            color: #f87171;
        }

        /* ══ PANEL CHAT ══════════════════════════════════════════════════════════ */
        .chat-panel {
            flex: 1;
            display: flex;
            flex-direction: column;
            overflow: hidden;
            background: var(--bg-deep);
        }

        /* Estado vacío */
        .chat-empty {
            flex: 1;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            gap: 14px;
            color: var(--text-low);
        }

        .chat-empty-icon {
            width: 64px;
            height: 64px;
            background: rgba(209, 44, 125, 0.1);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 28px;
        }

        .chat-empty p {
            font-size: 14px;
            margin: 0;
        }

        #chat-view {
            display: none;
            flex: 1;
            flex-direction: column;
            overflow: hidden;
            min-height: 0;
        }

        #chat-view.visible {
            display: flex;
        }

        /* Header del chat */
        .chat-header {
            padding: 12px 18px;
            display: flex;
            align-items: center;
            gap: 12px;
            background: var(--bg-card);
            border-bottom: 1px solid rgba(255, 255, 255, 0.05);
            flex-shrink: 0;
            box-shadow: 0 1px 10px rgba(0, 0, 0, 0.2);
            z-index: 10;
        }

        .chat-header-avatar {
            position: relative;
        }

        .chat-header-avatar img {
            width: 38px !important;
            height: 38px !important;
            min-width: 38px;
            border-radius: 50% !important;
            object-fit: cover;
            display: block;
        }

        /* Override global img styles for all avatars in mensajes */
        .conv-item .avatar,
        .msg-row .msg-avatar,
        .miembro-item img,
        .user-search-item img {
            border-radius: 50% !important;
            height: auto;
            max-width: unset;
        }

        .conv-item .avatar {
            width: 42px !important;
            height: 42px !important;
        }

        .msg-row .msg-avatar {
            width: 28px !important;
            height: 28px !important;
        }

        .chat-header-info {
            flex: 1;
            min-width: 0;
        }

        .chat-title {
            font-size: 15px;
            font-weight: 700;
            color: var(--texto-general);
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }

        .chat-subtitle {
            font-size: 11px;
            color: var(--magenta-glow-claro);
            margin-top: 1px;
            font-weight: 500;
            letter-spacing: 0.02em;
        }

        .chat-header-actions {
            display: flex;
            align-items: center;
            gap: 4px;
            margin-left: auto;
        }

        .btn-info,
        .btn-chat-menu {
            background: none;
            border: none;
            color: var(--text-low);
            font-size: 20px;
            cursor: pointer;
            width: 34px;
            height: 34px;
            border-radius: 8px;
            display: flex;
            align-items: center;
            justify-content: center;
            transition: background 0.15s, color 0.15s;
        }

        .btn-info:hover,
        .btn-chat-menu:hover {
            background: rgba(255, 255, 255, 0.06);
            color: var(--texto-general);
        }

        .chat-header-menu {
            position: relative;
        }

        .chat-header-dropdown {
            display: none;
            position: absolute;
            right: 0;
            top: calc(100% + 6px);
            background: var(--bg-card);
            border: 1px solid rgba(255, 255, 255, 0.08);
            border-radius: 10px;
            box-shadow: 0 8px 28px rgba(0, 0, 0, 0.55);
            z-index: 200;
            min-width: 170px;
            overflow: hidden;
        }

        .chat-header-dropdown.open {
            display: block;
        }

        .chat-header-dropdown button {
            display: flex;
            align-items: center;
            gap: 9px;
            width: 100%;
            padding: 10px 14px;
            background: none;
            border: none;
            color: var(--texto-general);
            font-size: 13px;
            cursor: pointer;
            text-align: left;
            transition: background 0.12s;
            font-family: inherit;
        }

        .chat-header-dropdown button:hover {
            background: rgba(255, 255, 255, 0.05);
        }

        .chat-header-dropdown button.danger {
            color: #f87171;
        }

        /* Zona de mensajes */
        .chat-messages {
            flex: 1;
            overflow-y: auto;
            padding: 20px 24px;
            display: flex;
            flex-direction: column;
            gap: 3px;
            scrollbar-width: thin;
            scrollbar-color: rgba(255, 255, 255, 0.07) transparent;
        }

        /* ══ BURBUJAS ════════════════════════════════════════════════════════════ */
        .msg-row {
            display: flex;
            align-items: flex-end;
            gap: 8px;
            position: relative;
            padding: 1px 0;
            animation: msgIn 0.18s ease;
        }

        .msg-row.mine {
            flex-direction: row-reverse;
        }

        .msg-row .msg-avatar {
            width: 28px;
            height: 28px;
            border-radius: 50%;
            object-fit: cover;
            flex-shrink: 0;
            margin-bottom: 2px;
            opacity: 0.9;
        }

        .msg-row.mine .msg-avatar {
            display: none;
        }

        .msg-col {
            display: flex;
            flex-direction: column;
            max-width: 62%;
        }

        .msg-row.mine .msg-col {
            align-items: flex-end;
        }

        .msg-row:not(.mine) .msg-col {
            align-items: flex-start;
        }

        .msg-sender {
            font-size: 11px;
            font-weight: 600;
            color: var(--magenta-glow-claro);
            margin-bottom: 3px;
            padding-left: 12px;
            letter-spacing: 0.02em;
        }

        .msg-bubble {
            padding: 9px 14px;
            border-radius: 18px;
            font-size: 14px;
            line-height: 1.5;
            word-break: break-word;
        }

        .msg-row.mine .msg-bubble {
            background: linear-gradient(135deg, var(--magenta-main), #c0217a);
            color: #fff;
            border-bottom-right-radius: 5px;
            box-shadow: 0 2px 8px rgba(209, 44, 125, 0.25);
        }

        .msg-row:not(.mine) .msg-bubble {
            background: rgba(255, 255, 255, 0.06);
            color: var(--texto-general);
            border-bottom-left-radius: 5px;
            border: 1px solid rgba(255, 255, 255, 0.05);
        }

        .msg-time {
            font-size: 10px;
            margin-top: 4px;
            padding: 0 4px;
            opacity: 0.55;
        }

        .msg-row.mine .msg-time {
            color: #fff;
            text-align: right;
        }

        .msg-row:not(.mine) .msg-time {
            color: var(--text-low);
        }

        .msg-edited {
            font-size: 10px;
            margin-left: 3px;
            opacity: 0.5;
            font-style: italic;
        }

        /* Menú de mensaje */
        .msg-menu-btn {
            display: none;
            position: absolute;
            top: 2px;
            width: 24px;
            height: 24px;
            background: var(--bg-card);
            border: 1px solid rgba(255, 255, 255, 0.1);
            border-radius: 50%;
            align-items: center;
            justify-content: center;
            font-size: 14px;
            cursor: pointer;
            color: var(--text-low);
            transition: background 0.15s, color 0.15s;
            z-index: 10;
        }

        .msg-menu-btn:hover {
            background: var(--bg-deep);
            color: var(--texto-general);
        }

        .msg-row.mine .msg-menu-btn {
            right: 0;
        }

        .msg-row:not(.mine) .msg-menu-btn {
            left: 36px;
        }

        .msg-row:hover .msg-menu-btn {
            display: flex;
        }

        .msg-actions {
            display: none;
            position: absolute;
            top: 26px;
            background: var(--bg-card);
            border: 1px solid rgba(255, 255, 255, 0.08);
            border-radius: 10px;
            box-shadow: 0 6px 20px rgba(0, 0, 0, 0.5);
            z-index: 100;
            overflow: hidden;
            min-width: 130px;
        }

        .msg-row.mine .msg-actions {
            right: 0;
        }

        .msg-row:not(.mine) .msg-actions {
            left: 36px;
        }

        .msg-actions.open {
            display: block;
        }

        .msg-action-btn {
            display: flex;
            align-items: center;
            gap: 8px;
            width: 100%;
            padding: 9px 14px;
            background: none;
            border: none;
            color: var(--texto-general);
            font-size: 13px;
            cursor: pointer;
            font-family: inherit;
            transition: background 0.12s;
        }

        .msg-action-btn:hover {
            background: rgba(255, 255, 255, 0.05);
        }

        .msg-action-btn.danger {
            color: #f87171;
        }

        /* Edición inline */
        .msg-edit-input {
            background: rgba(255, 255, 255, 0.06);
            border: 1px solid var(--magenta-main);
            border-radius: 12px;
            padding: 8px 12px;
            color: var(--texto-general);
            font-size: 14px;
            font-family: inherit;
            outline: none;
            resize: none;
            width: 100%;
            line-height: 1.45;
        }

        .msg-edit-actions {
            display: flex;
            gap: 6px;
            margin-top: 5px;
            justify-content: flex-end;
        }

        .msg-edit-actions button {
            padding: 4px 12px;
            border-radius: 7px;
            border: 1px solid rgba(255, 255, 255, 0.1);
            background: none;
            color: var(--text-low);
            font-size: 12px;
            cursor: pointer;
            font-family: inherit;
            transition: all 0.15s;
        }

        .msg-edit-actions .btn-guardar {
            background: var(--magenta-main);
            border-color: var(--magenta-main);
            color: #fff;
            font-weight: 600;
        }

        .chat-input-area {
            padding: 12px 18px;
            border-top: 1px solid rgba(255, 255, 255, 0.05);
            display: flex;
            gap: 10px;
            align-items: flex-end;
            background: var(--bg-card);
            flex-shrink: 0;
        }

        .chat-input-area textarea {
            flex: 1;
            background: rgba(255, 255, 255, 0.05);
            border: 1px solid rgba(255, 255, 255, 0.08);
            border-radius: 22px;
            padding: 10px 18px;
            color: var(--texto-general);
            font-size: 14px;
            outline: none;
            resize: none;
            height: 40px;
            max-height: 96px;
            /* ~4 líneas */
            overflow-y: hidden;
            font-family: inherit;
            line-height: 1.5;
            transition: border-color 0.2s, background 0.2s;
        }

        .chat-input-area textarea:focus {
            border-color: rgba(209, 44, 125, 0.5);
            background: rgba(255, 255, 255, 0.07);
        }

        .chat-input-area textarea::placeholder {
            color: var(--text-low);
        }

        .btn-send {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            border: none;
            background: var(--magenta-main);
            color: #fff;
            font-size: 16px;
            display: flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            flex-shrink: 0;
            transition: background 0.2s, transform 0.15s, box-shadow 0.2s;
            box-shadow: 0 2px 8px rgba(209, 44, 125, 0.35);
        }

        .btn-send:hover {
            background: var(--magenta-glow);
            transform: scale(1.06);
            box-shadow: 0 4px 14px rgba(209, 44, 125, 0.5);
        }

        .btn-send:disabled {
            background: rgba(255, 255, 255, 0.08);
            box-shadow: none;
            cursor: default;
            transform: none;
        }

        /* ══ PANEL INFO ══════════════════════════════════════════════════════════ */
        .info-panel {
            width: 0;
            overflow: hidden;
            border-left: 1px solid rgba(255, 255, 255, 0.05);
            background: var(--bg-card);
            display: flex;
            flex-direction: column;
            transition: width 0.25s ease;
        }

        .info-panel.open {
            width: 240px;
        }

        .info-panel-inner {
            padding: 20px 16px;
            width: 240px;
        }

        .info-panel h4 {
            margin: 0 0 16px;
            font-size: 13px;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.06em;
            color: var(--text-low);
        }

        .miembro-item {
            display: flex;
            align-items: center;
            gap: 10px;
            padding: 7px 0;
            font-size: 13px;
            border-bottom: 1px solid rgba(255, 255, 255, 0.04);
        }

        .miembro-item:last-child {
            border-bottom: none;
        }

        .miembro-item img {
            width: 32px;
            height: 32px;
            border-radius: 50%;
            object-fit: cover;
        }

        /* ══ MODAL ═══════════════════════════════════════════════════════════════ */
        .modal-overlay {
            display: none;
            position: fixed;
            inset: 0;
            background: rgba(0, 0, 0, 0.7);
            backdrop-filter: blur(6px);
            z-index: 9999;
            justify-content: center;
            align-items: center;
        }

        .modal-overlay.open {
            display: flex;
        }

        .modal-box {
            background: var(--bg-card);
            border: 1px solid rgba(255, 255, 255, 0.08);
            border-radius: 18px;
            padding: 26px;
            width: 420px;
            max-width: 95vw;
            box-shadow: 0 20px 60px rgba(0, 0, 0, 0.7);
        }

        .modal-box h3 {
            margin: 0 0 20px;
            font-size: 17px;
            font-weight: 700;
            color: var(--texto-general);
        }

        .modal-box input {
            width: 100%;
            background: rgba(255, 255, 255, 0.05);
            border: 1px solid rgba(255, 255, 255, 0.08);
            border-radius: 10px;
            padding: 10px 14px;
            color: var(--texto-general);
            font-size: 14px;
            outline: none;
            margin-bottom: 12px;
            font-family: inherit;
            transition: border-color 0.2s;
        }

        .modal-box input:focus {
            border-color: var(--magenta-main);
        }

        .modal-box input::placeholder {
            color: var(--text-low);
        }

        .modal-tipo {
            display: flex;
            gap: 8px;
            margin-bottom: 16px;
        }

        .modal-tipo button {
            flex: 1;
            padding: 9px 12px;
            border-radius: 10px;
            border: 1px solid rgba(255, 255, 255, 0.1);
            background: transparent;
            color: var(--text-low);
            font-size: 13px;
            font-weight: 500;
            cursor: pointer;
            transition: all 0.15s;
            font-family: inherit;
        }

        .modal-tipo button.active {
            background: var(--magenta-main);
            border-color: var(--magenta-main);
            color: #fff;
            box-shadow: 0 2px 10px rgba(209, 44, 125, 0.3);
        }

        .user-search-results {
            max-height: 180px;
            overflow-y: auto;
            border: 1px solid rgba(255, 255, 255, 0.08);
            border-radius: 10px;
            margin-bottom: 12px;
            background: rgba(255, 255, 255, 0.02);
        }

        .user-search-item {
            display: flex;
            align-items: center;
            gap: 10px;
            padding: 9px 12px;
            cursor: pointer;
            transition: background 0.12s;
        }

        .user-search-item:hover {
            background: rgba(255, 255, 255, 0.04);
        }

        .user-search-item.selected {
            background: rgba(209, 44, 125, 0.08);
        }

        .user-search-item img {
            width: 32px;
            height: 32px;
            border-radius: 50%;
            object-fit: cover;
        }

        .user-search-item span {
            font-size: 13px;
        }

        .user-search-item .check {
            margin-left: auto;
            color: var(--magenta-main);
            font-size: 15px;
            display: none;
        }

        .user-search-item.selected .check {
            display: block;
        }

        .selected-chips {
            display: flex;
            flex-wrap: wrap;
            gap: 6px;
            margin-bottom: 12px;
        }

        .chip {
            display: flex;
            align-items: center;
            gap: 5px;
            background: rgba(209, 44, 125, 0.12);
            border: 1px solid rgba(209, 44, 125, 0.35);
            border-radius: 20px;
            padding: 3px 10px 3px 8px;
            font-size: 12px;
            color: var(--magenta-glow-claro);
        }

        .chip button {
            background: none;
            border: none;
            color: var(--magenta-glow-claro);
            font-size: 15px;
            cursor: pointer;
            padding: 0;
            line-height: 1;
        }

        .modal-actions {
            display: flex;
            gap: 10px;
            justify-content: flex-end;
            margin-top: 6px;
        }

        .btn-cancelar {
            background: none;
            border: 1px solid rgba(255, 255, 255, 0.1);
            border-radius: 10px;
            padding: 9px 18px;
            color: var(--text-low);
            cursor: pointer;
            font-size: 14px;
            font-family: inherit;
            transition: border-color 0.15s, color 0.15s;
        }

        .btn-cancelar:hover {
            border-color: rgba(255, 255, 255, 0.25);
            color: var(--texto-general);
        }

        .btn-crear {
            background: var(--magenta-main);
            border: none;
            border-radius: 10px;
            padding: 9px 20px;
            color: #fff;
            cursor: pointer;
            font-size: 14px;
            font-weight: 600;
            font-family: inherit;
            transition: background 0.15s, box-shadow 0.15s;
            box-shadow: 0 2px 10px rgba(209, 44, 125, 0.3);
        }

        .btn-crear:hover {
            background: var(--magenta-glow);
            box-shadow: 0 4px 16px rgba(209, 44, 125, 0.45);
        }

        .btn-crear:disabled {
            background: rgba(255, 255, 255, 0.08);
            box-shadow: none;
            cursor: default;
        }

        /* ══ ANIMACIONES ═════════════════════════════════════════════════════════ */
        @keyframes msgIn {
            from {
                opacity: 0;
                transform: translateY(5px);
            }

            to {
                opacity: 1;
                transform: translateY(0);
            }
        }
    </style>
</head>

<body>
    <main>
        <?php include('includes/WIP_aside.php') ?>

        <div class="mensajes-wrapper">

            <!-- Lista de conversaciones -->
            <div class="conv-list-panel">
                <div class="conv-list-header">
                    <h2>💬 Mensajes</h2>
                    <button class="btn-nueva-conv" id="btn-nueva-conv" title="Nueva conversación">＋</button>
                </div>
                <div class="conv-search">
                    <div class="conv-search-inner">
                        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5">
                            <circle cx="11" cy="11" r="8" />
                            <path d="m21 21-4.35-4.35" />
                        </svg>
                        <input type="text" id="conv-search-input" placeholder="Buscar...">
                    </div>
                </div>
                <div class="conv-list" id="conv-list">
                    <div style="padding:20px;text-align:center;color:var(--text-low);font-size:13px;">Cargando...</div>
                </div>
            </div>

            <!-- Chat -->
            <div class="chat-panel" id="chat-panel">
                <div class="chat-empty" id="chat-empty">
                    <div class="chat-empty-icon">💬</div>
                    <p>Selecciona una conversación o crea una nueva</p>
                </div>

                <div id="chat-view">
                    <div class="chat-header" id="chat-header">
                        <div class="chat-header-avatar">
                            <img id="chat-avatar" src="" alt="">
                        </div>
                        <div class="chat-header-info">
                            <div class="chat-title" id="chat-title"></div>
                            <div class="chat-subtitle" id="chat-subtitle"></div>
                        </div>
                        <div class="chat-header-actions">
                            <button class="btn-info" id="btn-info" title="Info">ⓘ</button>
                            <div class="chat-header-menu">
                                <button class="btn-chat-menu" id="btn-chat-menu">⋮</button>
                                <div class="chat-header-dropdown" id="chat-header-dropdown">
                                    <button id="btn-info-grupo-menu">👥 Ver participantes</button>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="chat-messages" id="chat-messages"></div>

                    <div class="chat-input-area">
                        <textarea id="chat-input" placeholder="Escribe un mensaje..." rows="1"></textarea>
                        <button class="btn-send" id="btn-send">
                            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
                                <line x1="22" y1="2" x2="11" y2="13" />
                                <polygon points="22 2 15 22 11 13 2 9 22 2" />
                            </svg>
                        </button>
                    </div>
                </div>
            </div>

            <!-- Panel info -->
            <div class="info-panel" id="info-panel">
                <div class="info-panel-inner" id="info-panel-inner">
                    <h4>Participantes</h4>
                    <div id="miembros-list"></div>
                </div>
            </div>

        </div>

        <!-- Dropdown de conversación -->
        <div class="conv-dropdown" id="conv-dropdown">
            <button id="conv-dropdown-borrar" class="danger">🗑️ Borrar conversación</button>
        </div>

        <!-- Modal nueva conversación -->
        <div class="modal-overlay" id="modal-conv">
            <div class="modal-box">
                <h3 id="modal-title">Nueva conversación</h3>

                <div class="modal-tipo">
                    <button id="btn-tipo-dm" class="active" onclick="setTipo('dm')">👤 Mensaje directo</button>
                    <button id="btn-tipo-grupo" onclick="setTipo('grupo')">👥 Grupo</button>
                </div>

                <div id="campo-nombre-grupo" style="display:none;">
                    <input type="text" id="nombre-grupo-input" placeholder="Nombre del grupo">
                </div>

                <input type="text" id="modal-user-search" placeholder="Buscar usuarios...">
                <div class="user-search-results" id="user-search-results" style="display:none;"></div>

                <div class="selected-chips" id="selected-chips"></div>

                <div class="modal-actions">
                    <button class="btn-cancelar" onclick="cerrarModal()">Cancelar</button>
                    <button class="btn-crear" id="btn-crear" onclick="crearConversacion()">Crear</button>
                </div>
            </div>
        </div>
    </main>

    <script>
        const MI_ID = <?= $mi_id ?>;
        const MI_AVATAR = <?= json_encode($mi_avatar) ?>;

        let convActiva = null;
        let pollingTimer = null;
        let ultimoMsgId = 0;
        let tipoModal = 'dm';
        let seleccionados = []; // [{id, username, avatar}]
        let convs = [];

        // ── Inicializar ──────────────────────────────────────────────────────────────
        cargarConversaciones();

        // ── Cargar lista de conversaciones ───────────────────────────────────────────
        function cargarConversaciones() {
            fetch('/actions/mensajes_action.php?accion=listar')
                .then(r => r.text())
                .then(text => {
                    console.log('LISTAR respuesta raw:', text);
                    let data;
                    try {
                        data = JSON.parse(text);
                    } catch (e) {
                        console.error('JSON inválido listar:', text);
                        return;
                    }
                    if (!data.ok) return;
                    convs = data.conversaciones;
                    renderConvList(convs);
                });
        }

        function renderConvList(lista) {
            const el = document.getElementById('conv-list');
            if (!lista.length) {
                el.innerHTML = '<div style="padding:20px;text-align:center;color:var(--text-low);font-size:13px;">Sin conversaciones todavía.<br>Pulsa ＋ para empezar.</div>';
                return;
            }

            el.innerHTML = lista.map(c => `
        <div class="conv-item ${convActiva === c.id_conversacion ? 'active' : ''}"
             onclick="abrirConv(${c.id_conversacion})">
            <img class="avatar" src="${esc(c.avatar)}" alt="">
            <div class="conv-info">
                <div class="conv-name">${esc(c.nombre_display)}</div>
                <div class="conv-preview">${c.ultimo_mensaje ? esc(c.ultimo_mensaje.substring(0, 50)) : 'Sin mensajes'}</div>
            </div>
            ${c.no_leidos > 0 ? `<div class="badge-unread">${c.no_leidos}</div>` : ''}
            <button class="conv-menu-btn" data-id="${c.id_conversacion}" onclick="abrirMenuConv(event, ${c.id_conversacion})">⋮</button>
        </div>
    `).join('');
        }

        // Filtrar conversaciones con búsqueda
        document.getElementById('conv-search-input').addEventListener('input', function() {
            const q = this.value.toLowerCase();
            const filtradas = convs.filter(c => c.nombre_display.toLowerCase().includes(q));
            renderConvList(filtradas);
        });

        // ── Abrir conversación ───────────────────────────────────────────────────────
        function abrirConv(id_conv) {
            convActiva = id_conv;
            ultimoMsgId = 0;
            clearInterval(pollingTimer);

            // Marcar activa en lista
            document.querySelectorAll('.conv-item').forEach(el => {
                el.classList.toggle('active', parseInt(el.getAttribute('onclick').match(/\d+/)[0]) === id_conv);
            });

            // Mostrar chat con clase CSS
            document.getElementById('chat-empty').style.display = 'none';
            document.getElementById('chat-messages').innerHTML = '';
            document.getElementById('chat-view').classList.add('visible');

            // Cabecera
            const conv = convs.find(c => c.id_conversacion == id_conv);
            if (conv) {
                document.getElementById('chat-avatar').src = conv.avatar;
                document.getElementById('chat-title').textContent = conv.nombre_display;
                document.getElementById('chat-subtitle').textContent = conv.es_grupo ? 'Grupo' : '';
                document.getElementById('btn-info').style.display = conv.es_grupo ? 'flex' : 'none';
            }

            // Cerrar panel info
            document.getElementById('info-panel').classList.remove('open');

            cargarMensajes(true);
            pollingTimer = setInterval(() => cargarMensajes(false), 3000);
        }

        // ── Cargar mensajes ──────────────────────────────────────────────────────────
        function cargarMensajes(scroll) {
            if (!convActiva) return;
            fetch(`/actions/mensajes_action.php?accion=mensajes&id_conv=${convActiva}&desde_id=${ultimoMsgId}`)
                .then(r => r.text())
                .then(text => {
                    console.log('MENSAJES respuesta raw:', text);
                    let data;
                    try {
                        data = JSON.parse(text);
                    } catch (e) {
                        console.error('JSON inválido:', text);
                        return;
                    }
                    if (!data.ok || !data.mensajes.length) return;
                    const container = document.getElementById('chat-messages');
                    const conv = convs.find(c => c.id_conversacion == convActiva);
                    const esGrupo = conv?.es_grupo;

                    data.mensajes.forEach(m => {
                        ultimoMsgId = Math.max(ultimoMsgId, m.id_mensaje);
                        const div = document.createElement('div');
                        div.className = 'msg-row' + (m.es_mio ? ' mine' : '');
                        div.dataset.id = m.id_mensaje;

                        const hora = new Date(m.fecha_envio).toLocaleTimeString('es-ES', {
                            hour: '2-digit',
                            minute: '2-digit'
                        });

                        const menuMio = m.es_mio ? `
                    <button class="msg-menu-btn">⋮</button>
                    <div class="msg-actions">
                        <button class="msg-action-btn btn-editar-msg" data-id="${m.id_mensaje}">✏️ Editar</button>
                        <button class="msg-action-btn danger btn-borrar-msg" data-id="${m.id_mensaje}">🗑️ Borrar</button>
                    </div>` : '';

                        div.innerHTML = `
                    <img class="msg-avatar" src="${esc(m.avatar)}" alt="${esc(m.username)}">
                    <div class="msg-col">
                        ${(!m.es_mio && esGrupo) ? `<div class="msg-sender">@${esc(m.username)}</div>` : ''}
                        <div class="msg-bubble" data-id="${m.id_mensaje}">${esc(m.contenido).replace(/\n/g, '<br>')}</div>
                        <div class="msg-time">${hora}</div>
                    </div>
                    ${menuMio}`;

                        // Toggle ⋮ menú
                        const menuBtn = div.querySelector('.msg-menu-btn');
                        const menuEl = div.querySelector('.msg-actions');
                        if (menuBtn && menuEl) {
                            menuBtn.addEventListener('click', e => {
                                e.stopPropagation();
                                document.querySelectorAll('.msg-actions.open').forEach(m => {
                                    if (m !== menuEl) m.classList.remove('open');
                                });
                                menuEl.classList.toggle('open');
                            });
                        }

                        // Borrar mensaje
                        const btnBorrar = div.querySelector('.btn-borrar-msg');
                        if (btnBorrar) btnBorrar.addEventListener('click', () => borrarMensaje(m.id_mensaje, div));

                        // Editar mensaje
                        const btnEditar = div.querySelector('.btn-editar-msg');
                        if (btnEditar) btnEditar.addEventListener('click', () => editarMensaje(m.id_mensaje, div, m.contenido));

                        container.appendChild(div);
                    });

                    if (scroll) container.scrollTop = container.scrollHeight;
                    else {
                        const wasBottom = container.scrollHeight - container.scrollTop - container.clientHeight < 60;
                        if (wasBottom) container.scrollTop = container.scrollHeight;
                    }

                    // Quitar badge no leídos
                    cargarConversaciones();
                });
        }

        // ── Enviar mensaje ───────────────────────────────────────────────────────────
        document.getElementById('btn-send').addEventListener('click', enviar);
        document.getElementById('chat-input').addEventListener('keydown', e => {
            if (e.key === 'Enter' && !e.shiftKey) {
                e.preventDefault();
                enviar();
            }
        });

        // Auto-crecer hasta 4 líneas, luego scroll
        const chatInput = document.getElementById('chat-input');
        chatInput.addEventListener('input', function() {
            this.style.height = 'auto';
            const maxH = 96; // ~4 líneas
            const newH = Math.min(this.scrollHeight, maxH);
            this.style.height = newH + 'px';
            this.style.overflowY = this.scrollHeight > maxH ? 'auto' : 'hidden';
        });


        function enviar() {
            const input = document.getElementById('chat-input');
            const contenido = input.value.trim();
            if (!contenido || !convActiva) return;

            const btn = document.getElementById('btn-send');
            btn.disabled = true;

            const form = new FormData();
            form.append('accion', 'enviar');
            form.append('id_conv', convActiva);
            form.append('contenido', contenido);

            fetch('/actions/mensajes_action.php', {
                    method: 'POST',
                    body: form
                })
                .then(r => r.text())
                .then(text => {
                    console.log('ENVIAR respuesta raw:', text);
                    let data;
                    try {
                        data = JSON.parse(text);
                    } catch (e) {
                        console.error('JSON inválido:', text);
                        btn.disabled = false;
                        return;
                    }
                    if (data.ok) {
                        input.value = '';
                        input.style.height = '40px';
                        input.style.overflowY = 'hidden';
                        cargarMensajes(true);
                    } else {
                        console.error('Error al enviar:', data);
                    }
                })
                .catch(e => console.error('Fetch error:', e))
                .finally(() => btn.disabled = false);
        }

        // ── Menú ⋮ de cada conversación ──────────────────────────────────────────────
        let convMenuActiva = null;
        const convDropdown = document.getElementById('conv-dropdown');

        function abrirMenuConv(e, id_conv) {
            e.stopPropagation();
            convMenuActiva = id_conv;
            const rect = e.currentTarget.getBoundingClientRect();
            convDropdown.style.top = rect.bottom + 4 + 'px';
            convDropdown.style.left = rect.left - 100 + 'px';
            convDropdown.classList.toggle('open');
        }

        document.getElementById('conv-dropdown-borrar').addEventListener('click', () => {
            convDropdown.classList.remove('open');
            if (!convMenuActiva) return;
            const form = new FormData();
            form.append('accion', 'borrar_conv');
            form.append('id_conv', convMenuActiva);
            fetch('/actions/mensajes_action.php', {
                    method: 'POST',
                    body: form
                })
                .then(r => r.json())
                .then(data => {
                    if (data.ok) {
                        if (convActiva === convMenuActiva) {
                            clearInterval(pollingTimer);
                            convActiva = null;
                            document.getElementById('chat-view').classList.remove('visible');
                            document.getElementById('chat-empty').style.display = '';
                        }
                        convMenuActiva = null;
                        cargarConversaciones();
                    }
                });
        });


        document.getElementById('btn-chat-menu').addEventListener('click', e => {
            e.stopPropagation();
            document.getElementById('chat-header-dropdown').classList.toggle('open');
        });

        document.getElementById('btn-info-grupo-menu').addEventListener('click', () => {
            document.getElementById('chat-header-dropdown').classList.remove('open');
            const panel = document.getElementById('info-panel');
            panel.classList.toggle('open');
            if (panel.classList.contains('open')) cargarInfoGrupo();
        });

        // ── Panel info ───────────────────────────────────────────────────────────────
        document.addEventListener('click', () => {
            document.getElementById('chat-header-dropdown').classList.remove('open');
            document.getElementById('conv-dropdown').classList.remove('open');
            document.querySelectorAll('.msg-actions.open').forEach(m => m.classList.remove('open'));
        });



        function cargarInfoGrupo() {
            fetch(`/actions/mensajes_action.php?accion=info_conv&id_conv=${convActiva}`)
                .then(r => r.json())
                .then(data => {
                    if (!data.ok) return;
                    document.getElementById('miembros-list').innerHTML = data.miembros.map(m => `
                <div class="miembro-item">
                    <img src="${esc(m.avatar)}" alt="">
                    <span>@${esc(m.username)}</span>
                </div>`).join('');
                });
        }

        // ── Modal nueva conversación ─────────────────────────────────────────────────
        document.getElementById('btn-nueva-conv').addEventListener('click', () => {
            seleccionados = [];
            tipoModal = 'dm';
            document.getElementById('btn-tipo-dm').classList.add('active');
            document.getElementById('btn-tipo-grupo').classList.remove('active');
            document.getElementById('campo-nombre-grupo').style.display = 'none';
            document.getElementById('nombre-grupo-input').value = '';
            document.getElementById('modal-user-search').value = '';
            document.getElementById('user-search-results').style.display = 'none';
            document.getElementById('user-search-results').innerHTML = '';
            renderChips();
            document.getElementById('modal-conv').classList.add('open');
            document.getElementById('modal-user-search').focus();
        });

        function cerrarModal() {
            document.getElementById('modal-conv').classList.remove('open');
        }

        document.getElementById('modal-conv').addEventListener('click', e => {
            if (e.target === document.getElementById('modal-conv')) cerrarModal();
        });

        function setTipo(tipo) {
            tipoModal = tipo;
            document.getElementById('btn-tipo-dm').classList.toggle('active', tipo === 'dm');
            document.getElementById('btn-tipo-grupo').classList.toggle('active', tipo === 'grupo');
            document.getElementById('campo-nombre-grupo').style.display = tipo === 'grupo' ? 'block' : 'none';
            // Para DM solo 1 usuario
            if (tipo === 'dm' && seleccionados.length > 1) {
                seleccionados = [seleccionados[0]];
                renderChips();
            }
        }

        // Búsqueda de usuarios en modal
        let searchTimer = null;
        document.getElementById('modal-user-search').addEventListener('input', function() {
            clearTimeout(searchTimer);
            const q = this.value.trim();
            if (!q) {
                document.getElementById('user-search-results').style.display = 'none';
                return;
            }
            searchTimer = setTimeout(() => buscarUsuarios(q), 250);
        });

        function buscarUsuarios(q) {
            fetch(`/actions/mensajes_action.php?accion=buscar_usuarios&q=${encodeURIComponent(q)}`)
                .then(r => r.json())
                .then(data => {
                    if (!data.ok) return;
                    const container = document.getElementById('user-search-results');
                    if (!data.usuarios.length) {
                        container.style.display = 'block';
                        container.innerHTML = '<div style="padding:10px 12px;color:var(--text-low);font-size:13px;">Sin resultados</div>';
                        return;
                    }
                    container.style.display = 'block';
                    container.innerHTML = data.usuarios.map(u => {
                        const sel = seleccionados.some(s => s.id_usuario === u.id_usuario);
                        return `<div class="user-search-item ${sel ? 'selected' : ''}" onclick="toggleUsuario(${u.id_usuario}, '${esc(u.username)}', '${esc(u.avatar)}', this)">
                    <img src="${esc(u.avatar)}" alt="">
                    <span>@${esc(u.username)}</span>
                    <span class="check">✓</span>
                </div>`;
                    }).join('');
                });
        }

        function toggleUsuario(id, username, avatar, el) {
            const idx = seleccionados.findIndex(s => s.id_usuario === id);
            if (idx !== -1) {
                seleccionados.splice(idx, 1);
                el.classList.remove('selected');
            } else {
                // Para DM solo 1
                if (tipoModal === 'dm') seleccionados = [];
                document.querySelectorAll('.user-search-item').forEach(e => e.classList.remove('selected'));
                seleccionados.push({
                    id_usuario: id,
                    username,
                    avatar
                });
                el.classList.add('selected');
            }
            renderChips();
        }

        function renderChips() {
            document.getElementById('selected-chips').innerHTML = seleccionados.map(u => `
        <div class="chip">
            <img src="${esc(u.avatar)}" style="width:18px;height:18px;border-radius:50%;object-fit:cover;">
            @${esc(u.username)}
            <button onclick="quitarChip(${u.id_usuario})">×</button>
        </div>`).join('');
        }

        function quitarChip(id) {
            seleccionados = seleccionados.filter(s => s.id_usuario !== id);
            // Desmarcar en resultados si están visibles
            document.querySelectorAll('.user-search-item').forEach(el => {
                const onclick = el.getAttribute('onclick') || '';
                if (onclick.includes(`toggleUsuario(${id},`)) el.classList.remove('selected');
            });
            renderChips();
        }

        function crearConversacion() {
            if (!seleccionados.length) return;
            if (tipoModal === 'grupo' && !document.getElementById('nombre-grupo-input').value.trim()) {
                document.getElementById('nombre-grupo-input').focus();
                return;
            }

            const btn = document.getElementById('btn-crear');
            btn.disabled = true;

            const form = new FormData();
            form.append('accion', 'crear');
            form.append('es_grupo', tipoModal === 'grupo' ? '1' : '0');
            form.append('ids_usuarios', JSON.stringify(seleccionados.map(s => s.id_usuario)));
            if (tipoModal === 'grupo') form.append('nombre_grupo', document.getElementById('nombre-grupo-input').value.trim());

            fetch('/actions/mensajes_action.php', {
                    method: 'POST',
                    body: form
                })
                .then(r => r.json())
                .then(data => {
                    if (data.ok) {
                        cerrarModal();
                        cargarConversaciones();
                        setTimeout(() => abrirConv(data.id_conversacion), 300);
                    }
                })
                .finally(() => btn.disabled = false);
        }

        // ── Borrar y editar mensajes ─────────────────────────────────────────────────
        function borrarMensaje(id_mensaje, rowEl) {
            if (rowEl.querySelector('.msg-actions')) rowEl.querySelector('.msg-actions').classList.remove('open');
            const form = new FormData();
            form.append('accion', 'borrar_mensaje');
            form.append('id_mensaje', id_mensaje);
            fetch('/actions/mensajes_action.php', {
                    method: 'POST',
                    body: form
                })
                .then(r => r.json())
                .then(data => {
                    if (data.ok) rowEl.remove();
                });
        }

        function editarMensaje(id_mensaje, rowEl, contenidoActual) {
            const bubble = rowEl.querySelector('.msg-bubble');
            if (!bubble || rowEl.querySelector('.msg-edit-input')) return;

            const original = bubble.innerHTML;
            bubble.innerHTML = `
        <textarea class="msg-edit-input" rows="1">${contenidoActual}</textarea>
        <div class="msg-edit-actions">
            <button class="btn-cancelar-edit">Cancelar</button>
            <button class="btn-guardar">Guardar</button>
        </div>`;

            const textarea = bubble.querySelector('textarea');
            textarea.style.height = textarea.scrollHeight + 'px';
            textarea.focus();

            bubble.querySelector('.btn-cancelar-edit').addEventListener('click', () => {
                bubble.innerHTML = original;
            });

            bubble.querySelector('.btn-guardar').addEventListener('click', () => {
                const nuevo = textarea.value.trim();
                if (!nuevo) return;
                const form = new FormData();
                form.append('accion', 'editar_mensaje');
                form.append('id_mensaje', id_mensaje);
                form.append('contenido', nuevo);
                fetch('/actions/mensajes_action.php', {
                        method: 'POST',
                        body: form
                    })
                    .then(r => r.json())
                    .then(data => {
                        if (data.ok) {
                            bubble.innerHTML = esc(data.contenido).replace(/\n/g, '<br>') + ' <span class="msg-edited">(editado)</span>';
                        }
                    });
            });
        }

        // ── Helpers ──────────────────────────────────────────────────────────────────
        function esc(str) {
            return String(str ?? '').replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;');
        }
    </script>
</body>

</html>