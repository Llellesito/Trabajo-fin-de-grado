<?php
session_start();
require '../includes/db.php';
require '../includes/lib.php';

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $user     = trim($_POST['user']);
    $password = $_POST['password'];

    $stmt = $pdo->prepare("SELECT * FROM usuarios WHERE username=:u OR email=:u");
    $stmt->bindParam(':u', $user);
    $stmt->execute();
    $usuario = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($usuario && password_verify($password, $usuario['password_hash'])) {
        $sancion = cargarSancion((int)$usuario['id_usuario'], $pdo);

        if (estaBaneado($sancion)) {
            $ban = $sancion['ban_expira'];
            $hasta = ($ban === '9999-01-01 00:00:00')
                ? ' permanentemente'
                : ' hasta el ' . date('d/m/Y H:i', strtotime($ban));
            $error = "Tu cuenta ha sido suspendida{$hasta}. Contacta con soporte.";
        } else {
            $_SESSION['id_usuario'] = $usuario['id_usuario'];
            $_SESSION['usuario']    = $usuario['username'];
            $_SESSION['rol']        = $usuario['rol'] ?? 'usuario';
            $_SESSION['sancion']    = $sancion;
            header("Location: ../index.php");
            exit;
        }
    } else {
        $error = $usuario ? "Contraseña incorrecta." : "Usuario no encontrado.";
    }
}
?>
<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="UTF-8">
    <link rel="stylesheet" href="../assets/css/logeo.css">
    <link rel="shortcut icon" href="../assets/images/8mangos.png">
    <title>Iniciar sesión - 8Mangos</title>
</head>

<body>
    <form method="POST" action="">
        <h2>Iniciar sesión</h2>
        <?php if (isset($error)) echo "<p class='error'>$error</p>"; ?>
        <input type="text" name="user" placeholder="Username o Email" size="50" required><br>
        <input type="password" name="password" placeholder="Contraseña" size="50" required><br>
        <input type="submit" class="boton" value="Iniciar sesión">
        <p>¿No tienes una cuenta? <a href="registro.php">Regístrate</a></p>
    </form>
</body>

</html>