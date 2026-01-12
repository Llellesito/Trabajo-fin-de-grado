<?php require('includes/db.php') ?>

<header>
    <h1>8Mangos</h1>
    <nav>
        <?php if (isset($_SESSION['usuario'])): ?>
            <span>

                Bienvenido, <a href="miPerfil.php?id=<?php echo $_SESSION['id_usuario']; ?>">
                    <?php echo htmlspecialchars($_SESSION['usuario']); ?>

            </span>
            <a href="/actions/logout.php" style="font-size: 20px; color: white; margin-left: 10px;">Cerrar sesión</a>
        <?php else: ?>
            <a href="/actions/registro.php" style="font-size: 20px; color: white; margin-right:10px;">Registrarse</a>
            <a href="/actions/login.php" style="font-size: 20px; color: white;">Inicia sesión</a>
        <?php endif; ?>
    </nav>
</header>