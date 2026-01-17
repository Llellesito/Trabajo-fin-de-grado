<?php
session_start();

if (isset($_SESSION['id_usuario'])) {
    echo "ID usuario: " . $_SESSION['id_usuario'] . "<br>";
    echo "Usuario: " . $_SESSION['usuario'];
} else {
    echo "No hay sesión activa";
}

echo "<h2>OTRA PRUEBA</h2>";

function obtenerFoto($nombreArchivo)
{
    $rutaBase = "assets/images/";
    $fotoDefault = "default.png";

    // Si el nombre en la BD está vacío o el archivo no existe físicamente
    if (empty($nombreArchivo) || !file_exists($rutaBase . $nombreArchivo)) {
        return $rutaBase . $fotoDefault;
    }

    return $rutaBase . $nombreArchivo;
}


// En el muro, comentarios o perfil:
$fotoAMostrar = obtenerFoto($usuario['foto']);
echo "<img src='$fotoAMostrar' class='avatar-circular'>";
