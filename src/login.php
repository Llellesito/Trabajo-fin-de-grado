<?php
session_start();
require 'db.php'; // Conexión PDO

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

            header("Location: miPerfil.php");
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
    <title>Iniciar sesión</title>
    <style>
        body {
            font-family: Arial;
            background: #f2f2f2;
        }

        form {
            background: #fff;
            padding: 20px;
            margin: 50px auto;
            width: 350px;
            border-radius: 10px;
            box-shadow: 0 0 10px rgba(0, 0, 0, 0.1);
        }

        input {
            width: 100%;
            padding: 10px;
            margin: 10px 0;
        }

        button {
            padding: 10px;
            width: 100%;
            background: #2196F3;
            color: #fff;
            border: none;
            cursor: pointer;
        }

        .error {
            color: red;
            text-align: center;
        }
    </style>
</head>

<body>
    <form method="POST" action="">
        <h2>Iniciar sesión</h2>
        <?php if (isset($error)) echo "<p class='error'>$error</p>"; ?>
        <input type="text" name="user" placeholder="Username o Email" required>
        <input type="password" name="password" placeholder="Contraseña" required>
        <button type="submit">Entrar</button>
    </form>
</body>

</html>