<?php
require 'db.php';

$stmt = $pdo->query("SELECT id_usuario, username, nombre, bio, foto_perfil FROM usuarios");
$usuarios = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html>

<head>
    <meta charset="UTF-8">
    <title>Usuarios</title>
</head>

<body>
    <h1>Lista de usuarios</h1>
    <ul>
        <?php foreach ($usuarios as $user): ?>
            <li>
                <strong>@<?= htmlspecialchars($user['username']) ?></strong>
                (<?= htmlspecialchars($user['nombre']) ?>) -
                <?= htmlspecialchars($user['bio']) ?>

                <?php if (!empty($user['foto_perfil'])): ?>
                    <br>
                    <img
                        src="data:image/jpeg;base64,<?= base64_encode($user['foto_perfil']) ?>"
                        alt="Foto de perfil"
                        width="200"
                        style="margin-right: 10px;">
                <?php else: ?>
                    <br>
                    <em>Sin foto de perfil</em>
                <?php endif; ?>
            </li>
        <?php endforeach; ?>
    </ul>
</body>

</html>