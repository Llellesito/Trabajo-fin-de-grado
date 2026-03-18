<?php
session_start();
require 'includes/db.php';
require 'includes/lib.php';

if (!isset($_SESSION['id_usuario'])) {
    header("Location: actions/login.php");
    exit();
}

$tipo = $_GET['tipo'] ?? 'usuarios';
?>
<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="UTF-8">
    <title>Buscador · 8Mangos</title>
    <link rel="stylesheet" href="assets/css/style.css">
    <link rel="stylesheet" href="assets/css/variables.css">
    <link rel="shortcut icon" href="assets/images/8mangos.png">
    <style>
        .search-wrapper {
            max-width: 600px;
            margin: 0 auto;
            padding: 20px 0;
        }

        .search-title {
            font-size: 22px;
            font-weight: bold;
            margin-bottom: 18px;
            background: linear-gradient(to right, #fff, var(--magenta-main));
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
        }

        .search-box {
            display: flex;
            align-items: center;
            background: var(--bg-card);
            border: 1px solid var(--border-soft);
            border-radius: 30px;
            padding: 10px 18px;
            gap: 10px;
            margin-bottom: 16px;
            transition: border-color 0.2s;
        }

        .search-box:focus-within {
            border-color: var(--magenta-main);
        }

        .search-box .search-icon {
            font-size: 18px;
            color: var(--text-low);
            flex-shrink: 0;
        }

        .search-box input {
            background: transparent;
            border: none;
            outline: none;
            color: var(--texto-general);
            font-size: 16px;
            width: 100%;
        }

        .search-box input::placeholder {
            color: var(--text-low);
        }

        .spinner {
            width: 18px;
            height: 18px;
            border: 2px solid var(--border-soft);
            border-top-color: var(--magenta-main);
            border-radius: 50%;
            animation: spin 0.6s linear infinite;
            flex-shrink: 0;
            display: none;
        }

        @keyframes spin {
            to {
                transform: rotate(360deg);
            }
        }

        .search-tabs {
            display: flex;
            gap: 10px;
            margin-bottom: 20px;
        }

        .search-tabs button {
            padding: 8px 20px;
            border-radius: 20px;
            font-size: 14px;
            font-weight: 600;
            border: 1px solid var(--border-soft);
            color: var(--text-low);
            background: transparent;
            cursor: pointer;
            transition: all 0.2s;
        }

        .search-tabs button.active {
            background: var(--magenta-main);
            border-color: var(--magenta-main);
            color: white;
        }

        .search-tabs button:not(.active):hover {
            border-color: var(--magenta-glow);
            color: var(--texto-general);
        }

        .search-placeholder {
            text-align: center;
            color: var(--text-low);
            padding: 60px 20px;
        }

        .search-placeholder .ph-icon {
            font-size: 48px;
            margin-bottom: 12px;
        }

        .results-count {
            font-size: 13px;
            color: var(--text-low);
            margin-bottom: 14px;
        }

        .results-count strong {
            color: var(--magenta-glow-claro);
        }

        .user-card {
            display: flex;
            align-items: center;
            gap: 14px;
            background: var(--bg-card);
            border: 1px solid var(--border-soft);
            border-radius: 14px;
            padding: 14px 16px;
            margin-bottom: 12px;
            text-decoration: none;
            color: var(--texto-general);
            transition: border-color 0.2s, transform 0.15s;
            animation: fadeIn 0.2s ease;
        }

        .user-card:hover {
            border-color: var(--blue-light);
            transform: translateY(-2px);
        }

        .user-card .avatar {
            width: 52px;
            height: 52px;
            border-radius: 50%;
            object-fit: cover;
            flex-shrink: 0;
        }

        .user-card .user-info {
            flex-grow: 1;
            overflow: hidden;
        }

        .user-card .user-username {
            font-weight: 700;
            font-size: 15px;
        }

        .user-card .user-nombre,
        .user-card .user-bio {
            font-size: 13px;
            color: var(--text-low);
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
            margin-top: 2px;
        }

        /* ── Grid de publicaciones ── */
        #results-container .results-count {
            grid-column: 1 / -1;
        }

        .results-grid {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 4px;
        }

        @media (max-width: 600px) {
            .results-grid {
                grid-template-columns: repeat(2, 1fr);
            }
        }

        .result-post {
            background: var(--bg-card);
            border: 1px solid var(--border-soft);
            border-radius: 0;
            border: none;
            padding: 0;
            margin-bottom: 0;
            transition: opacity 0.2s;
            animation: fadeIn 0.2s ease;
            overflow: hidden;
            position: relative;
        }

        .result-post:hover {
            opacity: 0.85;
        }

        .result-post:hover .post-overlay {
            opacity: 1;
        }

        /* Imagen cuadrada */
        .result-post .post-media {
            width: 100%;
            aspect-ratio: 1 / 1;
            object-fit: cover;
            display: block;
            border-radius: 0;
        }

        /* Overlay con autor y texto al hacer hover */
        .post-overlay {
            position: absolute;
            inset: 0;
            background: rgba(0, 0, 0, 0.55);
            opacity: 0;
            transition: opacity 0.2s;
            display: flex;
            flex-direction: column;
            justify-content: flex-end;
            padding: 10px;
            pointer-events: none;
        }

        .post-overlay .ov-author {
            display: flex;
            align-items: center;
            gap: 6px;
            margin-bottom: 4px;
        }

        .post-overlay .ov-author img {
            width: 24px;
            height: 24px;
            border-radius: 50%;
            object-fit: cover;
            flex-shrink: 0;
        }

        .post-overlay .ov-author strong {
            font-size: 12px;
            color: #fff;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }

        .post-overlay .ov-text {
            font-size: 11px;
            color: rgba(255, 255, 255, 0.85);
            line-height: 1.3;
            display: -webkit-box;
            -webkit-line-clamp: 2;
            -webkit-box-orient: vertical;
            overflow: hidden;
        }

        /* Post sin imagen: mostrar solo texto en cuadro */
        .result-post.text-only {
            aspect-ratio: 1 / 1;
            background: var(--bg-card);
            border: 1px solid var(--border-soft);
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 12px;
        }

        .result-post.text-only .post-text-content {
            font-size: 13px;
            color: var(--text-high);
            line-height: 1.4;
            text-align: center;
            display: -webkit-box;
            -webkit-line-clamp: 5;
            -webkit-box-orient: vertical;
            overflow: hidden;
        }

        @keyframes fadeIn {
            from {
                opacity: 0;
                transform: translateY(6px);
            }

            to {
                opacity: 1;
                transform: translateY(0);
            }
        }
    </style>
</head>

<body>
    <main>
        <?php include('includes/WIP_aside.php') ?>

        <div class="posts">
            <div class="search-wrapper">
                <div class="search-title">🔍 Buscador</div>

                <div class="search-box">
                    <span class="search-icon">🔍</span>
                    <input
                        type="text"
                        id="search-input"
                        placeholder="Buscar usuarios..."
                        autofocus
                        autocomplete="off">
                    <div class="spinner" id="spinner"></div>
                </div>

                <div class="search-tabs">
                    <button id="tab-usuarios" class="<?= $tipo === 'usuarios' ? 'active' : '' ?>">
                        👤 Usuarios
                    </button>
                    <button id="tab-publicaciones" class="<?= $tipo === 'publicaciones' ? 'active' : '' ?>">
                        📝 Publicaciones
                    </button>
                </div>

                <div id="results-container">
                    <div class="search-placeholder">
                        <div class="ph-icon">🔍</div>
                        <p>Escribe algo para buscar.</p>
                    </div>
                </div>
            </div>
        </div>
    </main>

    <script>
        (function() {
            const input = document.getElementById('search-input');
            const spinner = document.getElementById('spinner');
            const container = document.getElementById('results-container');
            const tabUsers = document.getElementById('tab-usuarios');
            const tabPosts = document.getElementById('tab-publicaciones');

            let currentTipo = '<?= $tipo ?>';
            let debounceTimer = null;
            let currentQuery = '';

            tabUsers.addEventListener('click', () => switchTab('usuarios'));
            tabPosts.addEventListener('click', () => switchTab('publicaciones'));

            function switchTab(tipo) {
                currentTipo = tipo;
                tabUsers.classList.toggle('active', tipo === 'usuarios');
                tabPosts.classList.toggle('active', tipo === 'publicaciones');
                input.placeholder = tipo === 'usuarios' ? 'Buscar usuarios...' : 'Buscar publicaciones...';
                if (currentQuery.length >= 1) fetchResults(currentQuery, tipo);
            }

            input.addEventListener('input', () => {
                const q = input.value.trim();
                currentQuery = q;
                clearTimeout(debounceTimer);

                if (q.length < 1) {
                    showPlaceholder(q.length === 0 ? 'Escribe algo para buscar.' : '');
                    return;
                }

                spinner.style.display = 'block';
                debounceTimer = setTimeout(() => fetchResults(q, currentTipo), 300);
            });

            function fetchResults(q, tipo) {
                fetch(`actions/buscar_ajax.php?q=${encodeURIComponent(q)}&tipo=${tipo}`)
                    .then(r => {
                        if (!r.ok) throw new Error(`HTTP ${r.status}`);
                        return r.text(); // leer como texto primero
                    })
                    .then(text => {
                        spinner.style.display = 'none';
                        let data;
                        try {
                            data = JSON.parse(text);
                        } catch (e) {
                            // Mostrar los primeros caracteres de la respuesta para debug
                            const preview = escHtml(text.substring(0, 200));
                            container.innerHTML = `<div class="search-placeholder"><div class="ph-icon">⚠️</div><p>Respuesta inesperada del servidor:</p><pre style="font-size:11px;color:#f88;text-align:left;margin-top:8px;white-space:pre-wrap">${preview}</pre></div>`;
                            return;
                        }
                        if (data.query !== currentQuery) return; // respuesta obsoleta
                        if (data.error) {
                            container.innerHTML = `<div class="search-placeholder"><div class="ph-icon">⚠️</div><p>Error: ${escHtml(data.error)}</p></div>`;
                            return;
                        }
                        renderResults(data);
                    })
                    .catch(err => {
                        spinner.style.display = 'none';
                        container.innerHTML = `<div class="search-placeholder"><div class="ph-icon">⚠️</div><p>Error de red: ${escHtml(err.message)}</p></div>`;
                    });
            }

            function renderResults({
                resultados,
                tipo,
                query
            }) {
                if (!resultados.length) {
                    container.innerHTML = `<div class="search-placeholder"><div class="ph-icon">😶</div><p>Sin resultados para "<strong>${escHtml(query)}</strong>".</p></div>`;
                    return;
                }

                const n = resultados.length;
                const singular = tipo === 'usuarios' ? 'usuario' : 'publicación';
                const plural = tipo === 'usuarios' ? 'usuarios' : 'publicaciones';
                let html = `<div class="results-count"><strong>${n}</strong> ${n === 1 ? singular : plural} encontrado${n === 1 ? '' : 's'}</div>`;

                if (tipo === 'usuarios') {
                    resultados.forEach(u => {
                        html += `
                <a class="user-card" href="perfil.php?id=${u.id_usuario}">
                    <img class="avatar" src="${u.avatar}" alt="${escHtml(u.username)}">
                    <div class="user-info">
                        <div class="user-username">@${highlight(escHtml(u.username), query)}</div>
                        ${u.nombre ? `<div class="user-nombre">${highlight(escHtml(u.nombre), query)}</div>` : ''}
                        ${u.bio    ? `<div class="user-bio">${escHtml(u.bio)}</div>` : ''}
                    </div>
                </a>`;
                    });
                } else {
                    html += `<div class="results-grid">`;
                    resultados.forEach(p => {
                        if (p.media) {
                            html += `
                    <a class="result-post" href="perfil.php?id=${p.id_usuario}" style="display:block;text-decoration:none;">
                        <img class="post-media" src="data:image/jpeg;base64,${p.media}" alt="Post">
                        <div class="post-overlay">
                            <div class="ov-author">
                                <img src="${p.avatar}" alt="${escHtml(p.username)}">
                                <strong>@${escHtml(p.username)}</strong>
                            </div>
                            ${p.contenido_texto ? `<div class="ov-text">${highlight(escHtml(p.contenido_texto), query)}</div>` : ''}
                        </div>
                    </a>`;
                        } else {
                            html += `
                    <a class="result-post text-only" href="perfil.php?id=${p.id_usuario}" style="text-decoration:none;">
                        <div class="post-text-content">${highlight(escHtml(p.contenido_texto), query)}</div>
                    </a>`;
                        }
                    });
                    html += `</div>`;
                }

                container.innerHTML = html;
            }

            function showPlaceholder(msg) {
                spinner.style.display = 'none';
                container.innerHTML = `<div class="search-placeholder"><div class="ph-icon">🔍</div><p>${msg}</p></div>`;
            }

            function escHtml(str) {
                return String(str ?? '').replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;');
            }

            function highlight(text, query) {
                if (!query) return text;
                return text.replace(new RegExp(`(${query.replace(/[.*+?^${}()|[\]\\]/g,'\\$&')})`, 'gi'), '<span class="highlight">$1</span>');
            }

            function formatDate(str) {
                if (!str) return '';
                const d = new Date(str);
                return d.toLocaleDateString('es-ES', {
                        day: '2-digit',
                        month: '2-digit',
                        year: 'numeric'
                    }) +
                    ' ' + d.toLocaleTimeString('es-ES', {
                        hour: '2-digit',
                        minute: '2-digit'
                    });
            }
        })();
    </script>
</body>

</html>