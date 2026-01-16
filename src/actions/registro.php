<?php
session_start();
require '../includes/db.php'; // Conexión PDO

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $username = $_POST['username'];
    $nombre = $_POST['nombre'];
    $email = $_POST['email'];
    $password = $_POST['password'];
    $password2 = $_POST['password2'];

    // Validar que las contraseñas coincidan
    if ($password !== $password2) {
        die("Las contraseñas no coinciden.");
    }

    $password_hash = password_hash($password, PASSWORD_DEFAULT);

    try {
        $sql = "INSERT INTO usuarios (username, nombre, email, password_hash) 
                VALUES (:username, :nombre, :email, :password)";
        $stmt = $pdo->prepare($sql);
        $stmt->bindParam(':username', $username);
        $stmt->bindParam(':nombre', $nombre);
        $stmt->bindParam(':email', $email);
        $stmt->bindParam(':password', $password_hash);

        $stmt->execute();

        $_SESSION['usuario'] = $username;
        header("Location: perfil.php");
        exit;
    } catch (PDOException $e) {
        if ($e->getCode() == 23000) {
            echo "El correo o username ya está registrado.";
        } else {
            echo "Error: " . $e->getMessage();
        }
    }
}

?>

<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="UTF-8">
    <link rel="stylesheet" href="../assets/css/logeo.css">
    <title>Registro - 8Mangos</title>
</head>

<body>
    <form action="registro.php" method="POST" id="formRegistro">
        <h2>Registrarse</h2>
        <input type="text" name="username" placeholder="Username" size="50" required><br>
        <input type="text" name="nombre" placeholder="Nombre" size="50" required><br>
        <input type="email" name="email" placeholder="Correo electrónico" size="50" required><br>
        <input type="password" name="password" id="password" placeholder="Contraseña" size="50" required><br>
        <input type="password" name="password2" id="password2" placeholder="Repetir contraseña" size="50" required><br>
        <input type="submit" class="boton" name="enviar" id="enviar" value="Iniciar sesión">
        <p>¿Ya tienes una cuenta? <a href="login.php">Inicia sesión</a></p>
    </form>

    <script>
        document.getElementById('formRegistro').addEventListener('submit', function(e) {
            const pass1 = document.getElementById('password').value;
            const pass2 = document.getElementById('password2').value;
            if (pass1 !== pass2) {
                e.preventDefault();
                alert('Las contraseñas no coinciden');
            }
        });
    </script>
</body>

</html>