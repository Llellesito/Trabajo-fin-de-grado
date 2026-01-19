<aside class="sidebar">
    <div class="sidebar-logo">
        <h2>8Mangos</h2>
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
                <a href="/miPerfil.php" class="nav-link">
                    <span class="icon">🗣</span>
                    <span class="text"><?php echo htmlspecialchars($_SESSION['usuario']) ?></span>
                </a>
            </li>
        </ul>
    </nav>
</aside>