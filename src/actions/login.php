<?php
session_start();
require '../includes/db.php'; // Conexión PDO

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $user = trim($_POST['user']); // username o email
    $password = $_POST['password'];

    // Buscar usuario por username o email
    $sql = "SELECT * FROM usuarios WHERE username = :user OR email = :user";
    $stmt = $pdo->prepare($sql);
    $stmt->bindParam(':user', $user);
    $stmt->execute();
    $usuario = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($usuario) {
        if (password_verify($password, $usuario['password_hash'])) {
            // Guardar sesión correctamente
            $_SESSION['id_usuario'] = $usuario['id_usuario'];
            $_SESSION['usuario'] = $usuario['username'];

            header("Location: ../index.php");
            exit;
        } else {
            $error = "Contraseña incorrecta.";
        }
    } else {
        $error = "Usuario no encontrado.";
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
        <input type="submit" class="boton" name="enviar" id="enviar" value="Iniciar sesión">
        <p>¿No tienes una cuenta? <a href="registro.php">Registrate</a></p>
    </form>
</body>

</html>