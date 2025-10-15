<?php
session_start();

if (isset($_SESSION['id_usuario'])) {
    echo "ID usuario: " . $_SESSION['id_usuario'] . "<br>";
    echo "Usuario: " . $_SESSION['usuario'];
} else {
    echo "No hay sesión activa";
}
