<?php

function renderizarBotonPerfil($id_perfil_visitado, $pdo)
{
    $mi_id = $_SESSION['id_usuario'] ?? null;

    if (!$mi_id) {
        return '<button onclick="window.location.href=\'login.php\'">Inicia sesión para seguir</button>';
    }

    if ($mi_id == $id_perfil_visitado) {
        // CASO: ES MI PERFIL
        return '
        <div style="text-align:center; margin: 15px 0;">
            <a href="actions/WIP_editar_perfil.php" style="color:white; background:#2b7a2b; padding:10px 15px; border-radius:8px; text-decoration:none; display:inline-block;">
                Editar perfil
            </a>
        </div>';
    } else {
        // CASO: PERFIL AJENO
        require_once 'clases/User.php';
        $userObj = new User($pdo);
        $ya_lo_sigo = $userObj->estaSiguiendo($mi_id, $id_perfil_visitado);

        // Configuramos el color y texto según el estado
        $texto_boton = $ya_lo_sigo ? 'Dejar de seguir' : 'Seguir';
        $color_boton = $ya_lo_sigo ? '#6c757d' : '#007bff'; // Gris si ya lo sigue, azul si no.

        return '
        <form method="post" action="actions/seguir.php" style="text-align:center; margin: 15px 0;">
            <input type="hidden" name="id_seguido" value="' . htmlspecialchars($id_perfil_visitado) . '">
            <button type="submit" style="cursor:pointer; padding:10px 15px; border-radius:8px; background:' . $color_boton . '; color:white; border:none;">
                ' . $texto_boton . '
            </button>
        </form>';
    }
}
