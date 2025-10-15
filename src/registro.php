<?php
session_start();
require 'db.php'; // Archivo donde definiste $pdo

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
    <title>Registro</title>
    <style>
        body {
            font-family: Arial, sans-serif;
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
            background: #4CAF50;
            color: #fff;
            border: none;
            cursor: pointer;
        }
    </style>
</head>

<body>
    <form action="registro.php" method="POST" id="formRegistro">
        <h2>Registrarse</h2>
        <input type="text" name="username" placeholder="Username" required>
        <input type="text" name="nombre" placeholder="Nombre" required>
        <input type="email" name="email" placeholder="Correo electrónico" required>
        <input type="password" name="password" id="password" placeholder="Contraseña" required>
        <input type="password" name="password2" id="password2" placeholder="Repetir contraseña" required>
        <button type="submit">Registrarse</button>
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