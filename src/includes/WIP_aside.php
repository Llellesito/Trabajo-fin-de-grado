<aside class="sidebar">
    <div class="sidebar-logo">
        <a href="/index.php">
            <h2>8Mangos</h2>
        </a>
    </div>

    <nav class="sidebar-nav">
        <ul>
            <li>
                <a href="/index.php" class="nav-link">
                    <span class="icon">🏠</span>
                    <span class="text">Home</span>
                </a>
            </li>
            <li>
                <a href="#" class="nav-link">
                    <span class="icon">🔍</span>
                    <span class="text">Buscador</span>
                </a>
            </li>
            <li>
                <a href="#" class="nav-link">
                    <span class="icon">➕</span>
                    <span class="text">Subir publicacion</span>
                </a>
            </li>
            <li>
                <a href="/miPerfil.php" class="nav-link">
                    <span class="icon">🗣</span>
                    <span class="text"><?php echo htmlspecialchars($_SESSION['usuario']) ?></span>
                </a>
            </li>
            
        </ul>
    </nav>
</aside>