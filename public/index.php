<?php
require_once __DIR__ . '/../includes/config.php';

$db = getDB();

// Obtener configuración
$bannerDestacados = $db->query("SELECT valor FROM configuracion WHERE clave = 'banner_destacados'")->fetchColumn() === '1';

// Obtener categorías
$categorias = $db->query("SELECT * FROM categorias WHERE activo = 1 ORDER BY orden")->fetchAll();

// Búsqueda
$busqueda = $_GET['q'] ?? '';
$categoriaFiltro = $_GET['categoria'] ?? '';

$sql = "SELECT e.*, c.nombre as categoria_nombre, c.icono as categoria_icono 
        FROM emprendimientos e 
        JOIN categorias c ON e.categoria_id = c.id 
        WHERE e.estado = 'aprobado'";
$params = [];

if ($busqueda) {
    $sql .= " AND (e.nombre LIKE ? OR e.descripcion LIKE ? OR e.descripcion_corta LIKE ?)";
    $params[] = "%$busqueda%";
    $params[] = "%$busqueda%";
    $params[] = "%$busqueda%";
}

if ($categoriaFiltro) {
    $sql .= " AND c.slug = ?";
    $params[] = $categoriaFiltro;
}

$stmt = $db->prepare($sql);
$stmt->execute($params);
$todos = $stmt->fetchAll();

// Separar destacados y no destacados, luego sortear cada grupo
$destacados = array_filter($todos, fn($e) => $e['destacado']);
$normales = array_filter($todos, fn($e) => !$e['destacado']);

shuffle($destacados);
shuffle($normales);

// Destacados primero, luego el resto
$emprendimientos = array_merge($destacados, $normales);

// Contar por categoría
$conteos = $db->query("SELECT c.slug, COUNT(e.id) as total 
                        FROM categorias c 
                        LEFT JOIN emprendimientos e ON c.id = e.categoria_id AND e.estado = 'aprobado'
                        GROUP BY c.id")->fetchAll(PDO::FETCH_KEY_PAIR);
?>
<!DOCTYPE html>
<html lang="es">
<head>
<?php include __DIR__ . "/../includes/analytics.php"; ?>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Mi Valdivia Online — Descubre emprendimientos locales</title>
    <meta name="description" content="Encuentra los mejores emprendimientos locales de Valdivia: gastronomía, cultura, naturaleza y más.">
    
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@400;500;600;700&family=Fraunces:opsz,wght@9..144,400;9..144,600&display=swap" rel="stylesheet">
    
    <style>
        :root {
            /* Paleta Valdivia: Río, Naturaleza, Costa */
            --river-900: #0c2d4a;
            --river-800: #0f3d5e;
            --river-700: #1565a0;
            --river-600: #1976d2;
            --river-500: #2196f3;
            
            --deep-900: #0a1628;
            --deep-700: #1a3a5c;
            --deep-500: #2980b9;
            --deep-400: #5dade2;
            --deep-100: #d6eaf8;
            
            --accent-600: #0277bd;
            --accent-500: #039be5;
            --accent-400: #4fc3f7;
            --accent-300: #81d4fa;
            
            --earth-100: #f0f7fc;
            --earth-200: #dce8f4;
            --earth-300: #b8d4e8;
            --earth-800: #2c3e50;
            --earth-900: #1a252f;
            
            --white: #ffffff;
            --black: #1a1814;
            
            --font-display: 'Fraunces', Georgia, serif;
            --font-body: 'Outfit', system-ui, sans-serif;
            
            --radius: 12px;
            --shadow: 0 4px 20px rgba(26, 46, 26, 0.1);
            --shadow-lg: 0 12px 40px rgba(26, 46, 26, 0.15);
        }
        
        * { margin: 0; padding: 0; box-sizing: border-box; }
        
        html { scroll-behavior: smooth; }
        
        body {
            font-family: var(--font-body);
            background: var(--earth-100);
            color: var(--earth-900);
            line-height: 1.6;
        }
        
        /* ═══════════════════════════════════════
           HERO — Vista al río
           ═══════════════════════════════════════ */
        .hero {
            background: linear-gradient(135deg, var(--river-900) 0%, var(--river-800) 50%, var(--deep-900) 100%);
            padding: 2rem 1.5rem 4rem;
            position: relative;
            overflow: hidden;
        }
        
        .hero::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: 
                radial-gradient(ellipse 80% 60% at 70% 120%, rgba(229, 120, 48, 0.15) 0%, transparent 50%),
                radial-gradient(ellipse 60% 40% at 30% 0%, rgba(33, 150, 179, 0.1) 0%, transparent 40%);
            pointer-events: none;
        }
        
        .hero-content {
            max-width: 900px;
            margin: 0 auto;
            position: relative;
            z-index: 1;
        }
        
        .hero-nav {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 3rem;
        }
        
        .logo {
            font-family: var(--font-display);
            font-size: 1.5rem;
            font-weight: 600;
            color: var(--white);
            text-decoration: none;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }
        
        .logo-icon {
            font-size: 1.8rem;
        }
        
        .nav-links {
            display: flex;
            gap: 1.5rem;
            align-items: center;
        }
        
        .nav-links a {
            color: var(--deep-100);
            text-decoration: none;
            font-size: 0.9rem;
            font-weight: 500;
            opacity: 0.9;
            transition: opacity 0.2s;
        }
        
        .nav-links a:hover { opacity: 1; }
        
        .btn-registrar {
            background: var(--accent-500);
            color: var(--white) !important;
            padding: 0.6rem 1.2rem;
            border-radius: 8px;
            opacity: 1 !important;
            transition: background 0.2s, transform 0.2s;
        }
        
        .btn-registrar:hover {
            background: var(--accent-400);
            transform: translateY(-1px);
        }
        
        .hero-text {
            text-align: center;
            margin-bottom: 2.5rem;
        }
        
        .hero h1 {
            font-family: var(--font-display);
            font-size: clamp(2rem, 6vw, 3.5rem);
            font-weight: 600;
            color: var(--white);
            line-height: 1.15;
            margin-bottom: 1rem;
        }
        
        .hero h1 span {
            color: var(--accent-400);
        }
        
        .hero-subtitle {
            font-size: 1.1rem;
            color: var(--deep-100);
            opacity: 0.9;
            max-width: 500px;
            margin: 0 auto;
        }
        
        /* Buscador */
        .search-box {
            background: var(--white);
            border-radius: 16px;
            padding: 0.5rem;
            display: flex;
            gap: 0.5rem;
            box-shadow: var(--shadow-lg);
            max-width: 600px;
            margin: 0 auto;
        }
        
        .search-box input {
            flex: 1;
            border: none;
            padding: 1rem 1.25rem;
            font-size: 1rem;
            font-family: var(--font-body);
            background: transparent;
            color: var(--earth-900);
        }
        
        .search-box input::placeholder {
            color: var(--earth-300);
        }
        
        .search-box input:focus {
            outline: none;
        }
        
        .search-box button {
            background: var(--river-600);
            color: var(--white);
            border: none;
            padding: 1rem 2rem;
            border-radius: 12px;
            font-family: var(--font-body);
            font-size: 1rem;
            font-weight: 600;
            cursor: pointer;
            display: flex;
            align-items: center;
            gap: 0.5rem;
            transition: background 0.2s;
        }
        
        .search-box button:hover {
            background: var(--river-500);
        }
        
        /* ═══════════════════════════════════════
           CATEGORÍAS
           ═══════════════════════════════════════ */
        .categorias {
            background: var(--white);
            border-bottom: 1px solid var(--earth-200);
            position: sticky;
            top: 0;
            z-index: 50;
        }
        
        .categorias-inner {
            max-width: 1100px;
            margin: 0 auto;
            padding: 1rem 1.5rem;
            display: flex;
            gap: 0.5rem;
            overflow-x: auto;
            scrollbar-width: none;
        }
        
        .categorias-inner::-webkit-scrollbar { display: none; }
        
        .cat-btn {
            display: flex;
            align-items: center;
            gap: 0.4rem;
            padding: 0.6rem 1rem;
            background: var(--earth-100);
            border: 1px solid var(--earth-200);
            border-radius: 100px;
            font-family: var(--font-body);
            font-size: 0.85rem;
            font-weight: 500;
            color: var(--earth-800);
            text-decoration: none;
            white-space: nowrap;
            transition: all 0.2s;
        }
        
        .cat-btn:hover {
            border-color: var(--river-500);
            color: var(--river-700);
        }
        
        .cat-btn.active {
            background: var(--river-600);
            border-color: var(--river-600);
            color: var(--white);
        }
        
        .cat-count {
            background: rgba(0,0,0,0.1);
            padding: 0.1rem 0.4rem;
            border-radius: 100px;
            font-size: 0.75rem;
        }
        
        .cat-btn.active .cat-count {
            background: rgba(255,255,255,0.2);
        }
        
        /* ═══════════════════════════════════════
           CONTENIDO PRINCIPAL
           ═══════════════════════════════════════ */
        .main {
            max-width: 1100px;
            margin: 0 auto;
            padding: 2rem 1.5rem 4rem;
        }
        
        .results-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 1.5rem;
        }
        
        .results-count {
            font-size: 0.95rem;
            color: var(--earth-800);
        }
        
        .results-count strong {
            color: var(--river-700);
        }
        
        /* Grid de emprendimientos */
        .grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(320px, 1fr));
            gap: 1.5rem;
        }
        
        /* Card de emprendimiento */
        .card {
            background: var(--white);
            border-radius: var(--radius);
            overflow: hidden;
            box-shadow: var(--shadow);
            transition: transform 0.2s, box-shadow 0.2s;
        }
        
        .card {
            cursor: pointer;
            text-decoration: none;
            color: inherit;
            display: block;
        }
        
        .card:hover {
            transform: translateY(-4px);
            box-shadow: var(--shadow-lg);
            background: linear-gradient(135deg, var(--white) 0%, var(--earth-100) 100%);
        }
        
        .card-img {
            width: 100%;
            aspect-ratio: 16/10;
            object-fit: cover;
            background: var(--earth-200);
        }
        
        .card-body {
            padding: 1.25rem;
        }
        
        .card-cat {
            display: inline-flex;
            align-items: center;
            gap: 0.3rem;
            font-size: 0.75rem;
            font-weight: 600;
            color: var(--river-600);
            text-transform: uppercase;
            letter-spacing: 0.03em;
            margin-bottom: 0.5rem;
        }
        
        .card-title {
            font-family: var(--font-display);
            font-size: 1.25rem;
            font-weight: 600;
            color: var(--earth-900);
            margin-bottom: 0.5rem;
            line-height: 1.3;
        }
        
        .card:hover .card-title {
            color: var(--river-600);
        }
        
        .card-desc {
            font-size: 0.9rem;
            color: var(--earth-800);
            opacity: 0.8;
            display: -webkit-box;
            -webkit-line-clamp: 2;
            -webkit-box-orient: vertical;
            overflow: hidden;
            margin-bottom: 1rem;
        }
        
        .card-meta {
            display: flex;
            flex-wrap: wrap;
            gap: 0.75rem;
            font-size: 0.8rem;
            color: var(--earth-800);
        }
        
        .card-meta span {
            display: flex;
            align-items: center;
            gap: 0.3rem;
        }
        
        .card-destacado {
            position: absolute;
            top: 0.75rem;
            right: 0.75rem;
            background: var(--accent-500);
            color: var(--white);
            padding: 0.25rem 0.6rem;
            border-radius: 4px;
            font-size: 0.7rem;
            font-weight: 600;
            text-transform: uppercase;
        }
        
        .card-img-wrapper {
            position: relative;
        }
        
        /* Empty state */
        .empty {
            text-align: center;
            padding: 4rem 2rem;
            grid-column: 1 / -1;
        }
        
        .empty-icon {
            font-size: 4rem;
            margin-bottom: 1rem;
        }
        
        .empty h3 {
            font-family: var(--font-display);
            font-size: 1.5rem;
            color: var(--earth-800);
            margin-bottom: 0.5rem;
        }
        
        .empty p {
            color: var(--earth-800);
            opacity: 0.7;
        }
        
        /* ═══════════════════════════════════════
           FOOTER
           ═══════════════════════════════════════ */
        .footer {
            background: var(--river-900);
            color: var(--deep-100);
            padding: 3rem 1.5rem;
            text-align: center;
        }
        
        .footer-logo {
            font-family: var(--font-display);
            font-size: 1.25rem;
            margin-bottom: 1rem;
        }
        
        .footer p {
            font-size: 0.85rem;
            opacity: 0.7;
        }
        
        .footer a {
            color: var(--accent-400);
            text-decoration: none;
        }
        
        /* ═══════════════════════════════════════
           RESPONSIVE
           ═══════════════════════════════════════ */
        @media (max-width: 640px) {
            .hero { padding: 1.5rem 1rem 3rem; }
            .hero-nav { flex-direction: column; gap: 1rem; }
            .nav-links { gap: 1rem; }
            .search-box { flex-direction: column; padding: 0.75rem; }
            .search-box button { width: 100%; justify-content: center; }
            .grid { grid-template-columns: 1fr; }
            .results-header { flex-direction: column; gap: 0.5rem; align-items: flex-start; }
            .promo-banner { flex-direction: column; gap: 0.5rem; padding: 0.75rem 1rem; }
            .promo-cta { display: none; }
        }
        
        /* Banner Promocional */
        .promo-banner {
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 1rem;
            background: linear-gradient(135deg, #4fc3f7 0%, #039be5 100%);
            padding: 0.6rem 1.5rem;
            text-decoration: none;
            color: var(--white);
            font-size: 0.9rem;
            transition: background 0.2s;
        }
        
        .promo-banner:hover {
            background: linear-gradient(135deg, #039be5 0%, #1565a0 100%);
        }
        
        .promo-badge {
            background: rgba(255,255,255,0.2);
            padding: 0.2rem 0.6rem;
            border-radius: 100px;
            font-size: 0.75rem;
            font-weight: 700;
        }
        
        .promo-text {
            flex: 1;
            text-align: center;
        }
        
        .promo-cta {
            background: var(--white);
            color: var(--river-700);
            padding: 0.4rem 1rem;
            border-radius: 6px;
            font-weight: 600;
            font-size: 0.85rem;
        }
    </style>
</head>
<body>
    <?php if ($bannerDestacados): ?>
    <!-- Banner Destacar -->
    <a href="<?= BASE_URL ?>/destacar.php" class="promo-banner">
        <span class="promo-badge">⭐ NUEVO</span>
        <span class="promo-text"><strong>¿Quieres más visibilidad?</strong> Destaca tu emprendimiento y aparece primero</span>
        <span class="promo-cta">Ver planes →</span>
    </a>
    <?php endif; ?>
    
    <section class="hero">
        <div class="hero-content">
            <nav class="hero-nav">
                <a href="<?= BASE_URL ?>" class="logo">
                    <span class="logo-icon">🌊</span>
                    Mi Valdivia Online
                </a>
                <div class="nav-links">
                    <?php if (isAdmin()): ?>
                        <a href="<?= BASE_URL ?>/admin/">Admin</a>
                        <a href="<?= BASE_URL ?>/logout.php">Salir</a>
                    <?php else: ?>
                        <a href="<?= BASE_URL ?>/verificar.php" class="btn-registrar">Agregar Emprendimiento</a>
                    <?php endif; ?>
                </div>
            </nav>
            
            <div class="hero-text">
                <h1>Descubre <span>Valdivia</span></h1>
                <p class="hero-subtitle">Encuentra los mejores emprendimientos a orillas del río: gastronomía, cultura, naturaleza y más.</p>
            </div>
            
            <form class="search-box" action="" method="GET">
                <input 
                    type="text" 
                    name="q" 
                    placeholder="¿Qué estás buscando?" 
                    value="<?= e($busqueda) ?>"
                    autocomplete="off"
                >
                <button type="submit">
                    🔍 Buscar
                </button>
            </form>
        </div>
    </section>
    
    <nav class="categorias">
        <div class="categorias-inner">
            <a href="<?= BASE_URL ?>" class="cat-btn <?= !$categoriaFiltro ? 'active' : '' ?>">
                🌊 Todos
                <span class="cat-count"><?= array_sum($conteos) ?></span>
            </a>
            <?php foreach ($categorias as $cat): ?>
                <a href="<?= BASE_URL ?>?categoria=<?= e($cat['slug']) ?>" 
                   class="cat-btn <?= $categoriaFiltro === $cat['slug'] ? 'active' : '' ?>">
                    <?= $cat['icono'] ?> <?= e($cat['nombre']) ?>
                    <span class="cat-count"><?= $conteos[$cat['slug']] ?? 0 ?></span>
                </a>
            <?php endforeach; ?>
        </div>
    </nav>
    
    <main class="main">
        <div class="results-header">
            <p class="results-count">
                <?php if ($busqueda): ?>
                    <strong><?= count($emprendimientos) ?></strong> resultados para "<?= e($busqueda) ?>"
                <?php elseif ($categoriaFiltro): ?>
                    <strong><?= count($emprendimientos) ?></strong> emprendimientos en esta categoría
                <?php else: ?>
                    <strong><?= count($emprendimientos) ?></strong> emprendimientos registrados
                <?php endif; ?>
            </p>
        </div>
        
        <div class="grid">
            <?php if (empty($emprendimientos)): ?>
                <div class="empty">
                    <div class="empty-icon">🔍</div>
                    <h3>No encontramos resultados</h3>
                    <p>Intenta con otra búsqueda o explora las categorías.</p>
                </div>
            <?php else: ?>
                <?php foreach ($emprendimientos as $emp): ?>
                    <a href="<?= BASE_URL ?>/ver.php?slug=<?= e($emp['slug']) ?>" class="card">
                        <div class="card-img-wrapper">
                            <?php if ($emp['imagen_principal']): ?>
                                <img class="card-img" src="<?= e(getImageUrl($emp['imagen_principal'])) ?>" alt="<?= e($emp['nombre']) ?>">
                            <?php else: ?>
                                <div class="card-img" style="display: flex; align-items: center; justify-content: center; font-size: 3rem; color: var(--earth-300);">
                                    <?= $emp['categoria_icono'] ?>
                                </div>
                            <?php endif; ?>
                            <?php if ($emp['destacado']): ?>
                                <span class="card-destacado">⭐ Destacado</span>
                            <?php endif; ?>
                        </div>
                        <div class="card-body">
                            <span class="card-cat"><?= $emp['categoria_icono'] ?> <?= e($emp['categoria_nombre']) ?></span>
                            <h3 class="card-title"><?= e($emp['nombre']) ?></h3>
                            <p class="card-desc"><?= e($emp['descripcion_corta'] ?: substr($emp['descripcion'], 0, 120)) ?></p>
                            <div class="card-meta">
                                <?php if ($emp['direccion']): ?>
                                    <span>🌊 <?= e(substr($emp['direccion'], 0, 30)) ?></span>
                                <?php endif; ?>
                                <?php if ($emp['telefono']): ?>
                                    <span>📞 <?= e($emp['telefono']) ?></span>
                                <?php endif; ?>
                            </div>
                        </div>
                    </a>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </main>
    
    <footer class="footer">
        <div class="footer-logo">🌊 Mi Valdivia Online</div>
        <p>Una iniciativa de <a href="https://www.elcorreodevaldivia.cl" target="_blank">El Correo de Valdivia</a></p>
        <p style="margin-top: 0.5rem;">© <?= date('Y') ?> — Todos los derechos reservados</p>
    </footer>
    
    <!-- Popup Inscribir Emprendimiento -->
    <div id="popup-inscribir" class="popup-overlay">
        <div class="popup-backdrop"></div>
        <a href="<?= BASE_URL ?>/verificar.php" class="popup-content">
            <img src="<?= BASE_URL ?>/img/popup-inscribir.jpg" alt="Inscribe tu negocio en Mi Valdivia Online">
        </a>
    </div>
    
    <style>
        .popup-overlay {
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            z-index: 9999;
            display: flex;
            align-items: center;
            justify-content: center;
            opacity: 0;
            visibility: hidden;
            transition: opacity 0.3s, visibility 0.3s;
        }
        .popup-overlay.active {
            opacity: 1;
            visibility: visible;
        }
        .popup-backdrop {
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: rgba(0, 0, 0, 0.7);
            cursor: pointer;
        }
        .popup-content {
            position: relative;
            z-index: 1;
            max-width: 90%;
            max-height: 90vh;
            animation: popupZoom 0.4s ease-out;
        }
        .popup-content img {
            display: block;
            max-width: 100%;
            max-height: 85vh;
            border-radius: 16px;
            box-shadow: 0 20px 60px rgba(0, 0, 0, 0.5);
            cursor: pointer;
            transition: transform 0.2s;
        }
        .popup-content img:hover {
            transform: scale(1.02);
        }
        @keyframes popupZoom {
            from {
                opacity: 0;
                transform: scale(0.8);
            }
            to {
                opacity: 1;
                transform: scale(1);
            }
        }
    </style>
    
    <script>
        (function() {
            const popup = document.getElementById('popup-inscribir');
            const backdrop = popup.querySelector('.popup-backdrop');
            
            // Mostrar popup solo si no se ha visto en esta sesión
            if (!sessionStorage.getItem('popup-inscribir-visto')) {
                setTimeout(() => {
                    popup.classList.add('active');
                }, 500);
            }
            
            // Cerrar al hacer click en el fondo
            backdrop.addEventListener('click', function() {
                popup.classList.remove('active');
                sessionStorage.setItem('popup-inscribir-visto', '1');
            });
            
            // Cerrar con tecla Escape
            document.addEventListener('keydown', function(e) {
                if (e.key === 'Escape' && popup.classList.contains('active')) {
                    popup.classList.remove('active');
                    sessionStorage.setItem('popup-inscribir-visto', '1');
                }
            });
        })();
    </script>
    
    <!-- Botón WhatsApp Flotante -->
    <a href="https://wa.me/56996422600?text=Hola%2C%20necesito%20ayuda%20con%20Mi%20Valdivia%20Online" 
       target="_blank" 
       class="whatsapp-float" 
       title="Soporte por WhatsApp">
        <svg viewBox="0 0 24 24" fill="currentColor">
            <path d="M17.472 14.382c-.297-.149-1.758-.867-2.03-.967-.273-.099-.471-.148-.67.15-.197.297-.767.966-.94 1.164-.173.199-.347.223-.644.075-.297-.15-1.255-.463-2.39-1.475-.883-.788-1.48-1.761-1.653-2.059-.173-.297-.018-.458.13-.606.134-.133.298-.347.446-.52.149-.174.198-.298.298-.497.099-.198.05-.371-.025-.52-.075-.149-.669-1.612-.916-2.207-.242-.579-.487-.5-.669-.51-.173-.008-.371-.01-.57-.01-.198 0-.52.074-.792.372-.272.297-1.04 1.016-1.04 2.479 0 1.462 1.065 2.875 1.213 3.074.149.198 2.096 3.2 5.077 4.487.709.306 1.262.489 1.694.625.712.227 1.36.195 1.871.118.571-.085 1.758-.719 2.006-1.413.248-.694.248-1.289.173-1.413-.074-.124-.272-.198-.57-.347m-5.421 7.403h-.004a9.87 9.87 0 01-5.031-1.378l-.361-.214-3.741.982.998-3.648-.235-.374a9.86 9.86 0 01-1.51-5.26c.001-5.45 4.436-9.884 9.888-9.884 2.64 0 5.122 1.03 6.988 2.898a9.825 9.825 0 012.893 6.994c-.003 5.45-4.437 9.884-9.885 9.884m8.413-18.297A11.815 11.815 0 0012.05 0C5.495 0 .16 5.335.157 11.892c0 2.096.547 4.142 1.588 5.945L.057 24l6.305-1.654a11.882 11.882 0 005.683 1.448h.005c6.554 0 11.89-5.335 11.893-11.893a11.821 11.821 0 00-3.48-8.413z"/>
        </svg>
        <span>Soporte</span>
    </a>
    
    <style>
        .whatsapp-float {
            position: fixed;
            bottom: 24px;
            right: 24px;
            height: 48px;
            padding: 0 18px 0 14px;
            background: #25D366;
            color: white;
            border-radius: 24px;
            display: flex;
            align-items: center;
            gap: 8px;
            box-shadow: 0 4px 12px rgba(37, 211, 102, 0.4);
            transition: all 0.3s ease;
            z-index: 1000;
            text-decoration: none;
            font-weight: 600;
            font-size: 0.9rem;
        }
        
        .whatsapp-float:hover {
            transform: scale(1.05);
            box-shadow: 0 6px 20px rgba(37, 211, 102, 0.5);
        }
        
        .whatsapp-float svg {
            width: 22px;
            height: 22px;
        }
        
        @media (max-width: 768px) {
            .whatsapp-float {
                bottom: 16px;
                right: 16px;
                height: 44px;
                padding: 0 14px 0 12px;
                font-size: 0.85rem;
            }
            .whatsapp-float svg {
                width: 20px;
                height: 20px;
            }
        }
    </style>
</body>
</html>
