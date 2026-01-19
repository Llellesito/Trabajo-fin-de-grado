<?php
session_start();
require_once '../includes/db.php';
require_once '../clases/User.php';

if (!isset($_SESSION['id_usuario'])) {
    header("Location: login.php");
    exit;
}

// Obtenemos los datos actuales para precargar el formulario
$stmt = $pdo->prepare("SELECT username, nombre, foto_perfil, bio FROM usuarios WHERE id_usuario = ?");
$stmt->execute([$_SESSION['id_usuario']]);
$user = $stmt->fetch();
?>

<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="UTF-8">
    <link rel="stylesheet" href="../assets/css/acciones.css">
    <title>Editar Perfil - 8Mangos</title>
</head>

<body>

    <main>
        <?php include('../includes/WIP_aside.php') ?>

        <div class="formulario">
            <form action="editar_perfil_action.php" method="POST" enctype="multipart/form-data">
                <div class="contenido">
                    <h2>Editar perfil</h2>
                </div>

                <div class="contenido">
                    <img src="<?= (!empty($user['foto_perfil'])) ? 'data:image/jpeg;base64,' . base64_encode($user['foto_perfil'])  : 'assets/images/default.png'; ?>"
                        alt="Foto de perfil" width="75" height="75" style="border-radius: 50%;">

                    <button class="boton">
                        Cambiar foto
                        <input type="file" name="foto" accept="image/*">
                    </button><br>
                </div>

                <div class="contenido">
                    <label>Username:</label><br>
                    <input type="text" name="username" value="<?= htmlspecialchars($user['username']) ?>" size="64" required><br><br>

                    <label>Nombre:</label><br>
                    <input type="text" name="nombre" value="<?= htmlspecialchars($user['nombre']) ?>" size="64" required><br><br>

                    <label>Biografía:</label><br>
                    <textarea name="bio" rows="4"><?= htmlspecialchars($user['bio']) ?></textarea><br><br>

                    <button type="submit" class="boton" style="margin-right: 27px;">Guardar Cambios</button> <a href="../miPerfil.php?id=<?= $_SESSION['id_usuario'] ?>"><button class="boton">Cancelar</button></a>
                </div>
            </form>
        </div>
    </main>
</body>

</html>