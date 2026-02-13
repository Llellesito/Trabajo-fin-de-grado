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
    <link rel="shortcut icon" href="../assets/images/8mangos.png">
    <title>Editar Perfil - 8Mangos</title>
</head>

<body>

    <main>
        <?php include('../includes/WIP_aside.php') ?>

        <div class="formulario">
            <form action="editar_perfil_action.php" method="POST" enctype="multipart/form-data" class="contenido">
                <h2>Editar perfil</h2>

                <div style="text-align: center; margin-bottom: 20px;">
                    <label for="foto-input" style="cursor: pointer; display: inline-block;">
                        <img id="img-perfil-preview"
                            src="<?= (!empty($user['foto_perfil'])) ? 'data:image/jpeg;base64,' . base64_encode($user['foto_perfil']) : '../assets/images/default.png'; ?>"
                            alt="Foto de perfil"
                            width="120" height="120"
                            style="border-radius: 50%; border: 2px solid var(--magenta-main); object-fit: cover;">
                        <input type="file" name="foto" id="foto-input" accept="image/*" hidden>
                    </label>
                </div>

                <label>Username:</label><br>
                <input type="text" name="username" value="<?= htmlspecialchars($user['username']) ?>" size="64" required><br><br>

                <label>Nombre:</label><br>
                <input type="text" name="nombre" value="<?= htmlspecialchars($user['nombre']) ?>" size="64" required><br><br>

                <label>Biografía:</label><br>
                <textarea name="bio" rows="4"><?= htmlspecialchars($user['bio']) ?></textarea><br><br>

                <div style="display: flex; gap: 20px;">
                    <button type="submit" class="boton">Guardar Cambios</button>
                    <a href="../miPerfil.php?id=<?= $_SESSION['id_usuario'] ?>" style="width: 46%;">
                        <button type="button" class="boton" style="width: 100%;">Cancelar</button>
                    </a>
                </div>
            </form>
        </div>
    </main>

    <script src="../assets/js/editarPerfil.js"></script>
</body>

</html>