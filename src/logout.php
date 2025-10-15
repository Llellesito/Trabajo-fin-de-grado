<?php
// Inicia la sesión para poder eliminarla
session_start();

// Limpiar todas las variables de sesión
$_SESSION = [];

// Destruir la sesión completamente
if (ini_get("session.use_cookies")) {
    $params = session_get_cookie_params();
    setcookie(
        session_name(),
        '',
        time() - 42000,
        $params["path"],
        $params["domain"],
        $params["secure"],
        $params["httponly"]
    );
}

// Finalmente destruir la sesión
session_destroy();

// Redirigir al inicio de la página
header("Location: index.php");
exit;
