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
    <link rel="stylesheet" href="assets/css/buscador.css">
    <link rel="shortcut icon" href="assets/images/8mangos.png">

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