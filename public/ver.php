<?php
require_once __DIR__ . '/../includes/config.php';

$slug = $_GET['slug'] ?? '';
if (!$slug) {
    header('Location: ' . BASE_URL);
    exit;
}

$db = getDB();
$stmt = $db->prepare("
    SELECT e.*, c.nombre as categoria_nombre, c.icono as categoria_icono 
    FROM emprendimientos e 
    JOIN categorias c ON e.categoria_id = c.id 
    WHERE e.slug = ? AND e.estado = 'aprobado'
");
$stmt->execute([$slug]);
$emp = $stmt->fetch();

if (!$emp) {
    header('Location: ' . BASE_URL);
    exit;
}

// Incrementar visitas
$db->prepare("UPDATE emprendimientos SET visitas = visitas + 1 WHERE id = ?")->execute([$emp['id']]);

// Obtener emprendimientos relacionados (misma categoría)
$stmt = $db->prepare("
    SELECT e.*, c.icono as categoria_icono 
    FROM emprendimientos e 
    JOIN categorias c ON e.categoria_id = c.id 
    WHERE e.categoria_id = ? AND e.id != ? AND e.estado = 'aprobado'
    ORDER BY RAND()
    LIMIT 3
");
$stmt->execute([$emp['categoria_id'], $emp['id']]);
$relacionados = $stmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="es">
<head>
<?php include __DIR__ . "/../includes/analytics.php"; ?>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= e($emp['nombre']) ?> — Mi Valdivia Online</title>
    <meta name="description" content="<?= e($emp['descripcion_corta']) ?>">
    
    <meta property="og:title" content="<?= e($emp['nombre']) ?> — Mi Valdivia Online">
    <meta property="og:description" content="<?= e($emp['descripcion_corta']) ?>">
    <?php if ($emp['imagen_principal']): ?>
    <meta property="og:image" content="<?= e(getImageUrl($emp['imagen_principal'])) ?>">
    <?php endif; ?>
    
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@400;500;600;700&family=Fraunces:opsz,wght@9..144,400;9..144,600&display=swap" rel="stylesheet">
    <style>
        :root {
            --river-600: #1976d2;
            --river-700: #1565a0;
            --river-900: #0c2d4a;
            --deep-900: #0a1628;
            --deep-400: #5dade2;
            --accent-500: #039be5;
            --earth-100: #f0f7fc;
            --earth-200: #dce8f4;
            --earth-800: #2c3e50;
            --earth-900: #1a252f;
            --white: #ffffff;
            --green-600: #16a34a;
            --font-display: 'Fraunces', Georgia, serif;
            --font-body: 'Outfit', system-ui, sans-serif;
        }
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body {
            font-family: var(--font-body);
            background: var(--earth-100);
            color: var(--earth-900);
            line-height: 1.6;
        }
        
        .header {
            background: linear-gradient(135deg, var(--river-900) 0%, var(--deep-900) 100%);
            padding: 1rem 1.5rem;
        }
        .header-inner {
            max-width: 1000px;
            margin: 0 auto;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        .logo {
            font-family: var(--font-display);
            font-size: 1.25rem;
            color: var(--white);
            text-decoration: none;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }
        .header-links { display: flex; gap: 1.5rem; }
        .header-links a { color: var(--earth-200); text-decoration: none; font-size: 0.9rem; opacity: 0.9; }
        .header-links a:hover { opacity: 1; }
        
        .hero-img {
            width: 100%;
            height: 350px;
            object-fit: cover;
            background: var(--earth-200);
        }
        .hero-placeholder {
            width: 100%;
            height: 350px;
            background: linear-gradient(135deg, var(--earth-200) 0%, var(--earth-300, #d5c9bb) 100%);
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 6rem;
        }
        
        .container {
            max-width: 1000px;
            margin: 0 auto;
            padding: 0 1.5rem;
        }
        
        .main-content {
            display: grid;
            grid-template-columns: 1fr 320px;
            gap: 2rem;
            margin-top: -60px;
            position: relative;
            z-index: 10;
            padding-bottom: 4rem;
        }
        
        .content {
            background: var(--white);
            border-radius: 16px;
            padding: 2rem;
            box-shadow: 0 4px 20px rgba(0,0,0,0.1);
        }
        
        .breadcrumb {
            font-size: 0.85rem;
            color: var(--earth-800);
            margin-bottom: 1rem;
            opacity: 0.7;
        }
        .breadcrumb a { color: var(--river-600); text-decoration: none; }
        
        .category-badge {
            display: inline-flex;
            align-items: center;
            gap: 0.3rem;
            background: var(--earth-100);
            color: var(--river-700);
            padding: 0.4rem 0.8rem;
            border-radius: 100px;
            font-size: 0.85rem;
            font-weight: 500;
            margin-bottom: 1rem;
        }
        
        h1 {
            font-family: var(--font-display);
            font-size: 2rem;
            color: var(--earth-900);
            margin-bottom: 0.5rem;
            line-height: 1.2;
        }
        
        .tagline {
            font-size: 1.1rem;
            color: var(--earth-800);
            opacity: 0.8;
            margin-bottom: 1.5rem;
        }
        
        .badges {
            display: flex;
            gap: 0.75rem;
            flex-wrap: wrap;
            margin-bottom: 1.5rem;
        }
        .badge {
            padding: 0.35rem 0.75rem;
            border-radius: 6px;
            font-size: 0.8rem;
            font-weight: 500;
        }
        .badge-destacado { background: #fef3c7; color: #92400e; }
        .badge-precio { background: var(--earth-100); color: var(--earth-800); }
        
        .section-title {
            font-size: 1rem;
            font-weight: 600;
            color: var(--river-700);
            margin: 2rem 0 1rem;
            padding-bottom: 0.5rem;
            border-bottom: 2px solid var(--earth-200);
        }
        
        .description {
            color: var(--earth-800);
            white-space: pre-line;
        }
        
        .horarios {
            background: var(--earth-100);
            border-radius: 10px;
            padding: 1.25rem;
            white-space: pre-line;
            font-size: 0.95rem;
            color: var(--earth-800);
        }
        
        /* Sidebar */
        .sidebar {
            display: flex;
            flex-direction: column;
            gap: 1.5rem;
        }
        
        .sidebar-card {
            background: var(--white);
            border-radius: 16px;
            padding: 1.5rem;
            box-shadow: 0 4px 20px rgba(0,0,0,0.1);
        }
        
        .sidebar-title {
            font-size: 0.9rem;
            font-weight: 600;
            color: var(--river-700);
            margin-bottom: 1rem;
            text-transform: uppercase;
            letter-spacing: 0.03em;
        }
        
        .contact-item {
            display: flex;
            align-items: flex-start;
            gap: 0.75rem;
            padding: 0.75rem 0;
            border-bottom: 1px solid var(--earth-200);
        }
        .contact-item:last-child { border: none; }
        .contact-icon { font-size: 1.25rem; flex-shrink: 0; }
        .contact-info { flex: 1; }
        .contact-label { font-size: 0.75rem; color: var(--earth-800); opacity: 0.6; }
        .contact-value { color: var(--earth-900); font-weight: 500; }
        .contact-value a { color: var(--river-600); text-decoration: none; }
        .contact-value a:hover { text-decoration: underline; }
        
        .btn-whatsapp {
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 0.5rem;
            width: 100%;
            padding: 1rem;
            background: #25D366;
            color: white;
            text-decoration: none;
            border-radius: 10px;
            font-weight: 600;
            margin-top: 1rem;
            transition: background 0.2s;
        }
        .btn-whatsapp:hover { background: #1da851; }
        
        .social-links {
            display: flex;
            gap: 0.75rem;
        }
        .social-link {
            display: flex;
            align-items: center;
            justify-content: center;
            width: 44px;
            height: 44px;
            background: var(--earth-100);
            border-radius: 10px;
            text-decoration: none;
            font-size: 1.25rem;
            transition: background 0.2s;
        }
        .social-link:hover { background: var(--earth-200); }
        .social-link.instagram { color: #E4405F; }
        .social-link.instagram:hover { background: #E4405F; color: white; }
        .social-link.facebook { color: #1877F2; }
        .social-link.facebook:hover { background: #1877F2; color: white; }
        
        .owner-edit-link {
            display: block;
            text-align: center;
            color: var(--earth-800);
            text-decoration: none;
            font-size: 0.9rem;
            padding: 0.5rem;
            transition: color 0.2s;
        }
        .owner-edit-link:hover { color: var(--river-600); }
        
        /* Relacionados */
        .relacionados {
            margin-top: 2rem;
        }
        .relacionados h3 {
            font-family: var(--font-display);
            font-size: 1.25rem;
            margin-bottom: 1rem;
        }
        .rel-grid {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 1rem;
        }
        .rel-card {
            background: var(--white);
            border-radius: 10px;
            overflow: hidden;
            box-shadow: 0 2px 10px rgba(0,0,0,0.06);
            text-decoration: none;
            transition: transform 0.2s;
        }
        .rel-card:hover { transform: translateY(-3px); }
        .rel-img {
            width: 100%;
            height: 100px;
            object-fit: cover;
            background: var(--earth-200);
        }
        .rel-body { padding: 0.75rem; }
        .rel-title { font-weight: 600; color: var(--earth-900); font-size: 0.9rem; }
        
        .footer {
            background: var(--river-900);
            color: var(--earth-200);
            text-align: center;
            padding: 2rem;
            font-size: 0.85rem;
        }
        .footer a { color: var(--accent-500); text-decoration: none; }
        
        @media (max-width: 768px) {
            .main-content { grid-template-columns: 1fr; margin-top: -40px; }
            .hero-img, .hero-placeholder { height: 250px; }
            .rel-grid { grid-template-columns: 1fr; }
            h1 { font-size: 1.5rem; }
        }
    </style>
</head>
<body>
    <header class="header">
        <div class="header-inner">
            <a href="<?= BASE_URL ?>" class="logo">🌊 Mi Valdivia Online</a>
            <div class="header-links">
                <a href="<?= BASE_URL ?>">Explorar</a>
                <a href="<?= BASE_URL ?>/verificar.php">Agregar emprendimiento</a>
            </div>
        </div>
    </header>
    
    <?php if ($emp['imagen_principal']): ?>
        <img class="hero-img" src="<?= e(getImageUrl($emp['imagen_principal'])) ?>" alt="<?= e($emp['nombre']) ?>">
    <?php else: ?>
        <div class="hero-placeholder"><?= $emp['categoria_icono'] ?></div>
    <?php endif; ?>
    
    <div class="container">
        <div class="main-content">
            <div class="content">
                <p class="breadcrumb">
                    <a href="<?= BASE_URL ?>">Inicio</a> / 
                    <a href="<?= BASE_URL ?>?categoria=<?= e($emp['categoria_nombre']) ?>"><?= e($emp['categoria_nombre']) ?></a>
                </p>
                
                <span class="category-badge"><?= $emp['categoria_icono'] ?> <?= e($emp['categoria_nombre']) ?></span>
                
                <h1><?= e($emp['nombre']) ?></h1>
                <p class="tagline"><?= e($emp['descripcion_corta']) ?></p>
                
                <div class="badges">
                    <?php if ($emp['destacado']): ?>
                        <span class="badge badge-destacado">⭐ Destacado</span>
                    <?php endif; ?>
                    <?php if ($emp['rango_precios']): ?>
                        <span class="badge badge-precio"><?= e($emp['rango_precios']) ?></span>
                    <?php endif; ?>
                </div>
                
                <?php if ($emp['descripcion']): ?>
                    <h3 class="section-title">Acerca de</h3>
                    <div class="description"><?= nl2br(e($emp['descripcion'])) ?></div>
                <?php endif; ?>
                
                <?php if ($emp['horarios']): ?>
                    <h3 class="section-title">Horarios</h3>
                    <div class="horarios"><?= e($emp['horarios']) ?></div>
                <?php endif; ?>
                
                <?php if ($emp['direccion']): ?>
                    <h3 class="section-title">Ubicación</h3>
                    <p style="display: flex; align-items: center; gap: 0.5rem; color: var(--earth-800);">
                        🌊 <?= e($emp['direccion']) ?>
                    </p>
                <?php endif; ?>
            </div>
            
            <aside class="sidebar">
                <div class="sidebar-card">
                    <h4 class="sidebar-title">Contacto</h4>
                    
                    <?php if ($emp['telefono']): ?>
                    <div class="contact-item">
                        <span class="contact-icon">📞</span>
                        <div class="contact-info">
                            <div class="contact-label">Teléfono</div>
                            <div class="contact-value">
                                <a href="tel:<?= e($emp['telefono']) ?>"><?= e($emp['telefono']) ?></a>
                            </div>
                        </div>
                    </div>
                    <?php endif; ?>
                    
                    <?php if ($emp['email']): ?>
                    <div class="contact-item">
                        <span class="contact-icon">✉️</span>
                        <div class="contact-info">
                            <div class="contact-label">Email</div>
                            <div class="contact-value">
                                <a href="mailto:<?= e($emp['email']) ?>"><?= e($emp['email']) ?></a>
                            </div>
                        </div>
                    </div>
                    <?php endif; ?>
                    
                    <?php if ($emp['web']): ?>
                    <div class="contact-item">
                        <span class="contact-icon">🌐</span>
                        <div class="contact-info">
                            <div class="contact-label">Sitio web</div>
                            <div class="contact-value">
                                <a href="<?= e($emp['web']) ?>" target="_blank"><?= preg_replace('#^https?://#', '', $emp['web']) ?></a>
                            </div>
                        </div>
                    </div>
                    <?php endif; ?>
                    
                    <?php if ($emp['whatsapp']): ?>
                    <a href="https://wa.me/<?= preg_replace('/[^0-9]/', '', $emp['whatsapp']) ?>" class="btn-whatsapp" target="_blank">
                        💬 Contactar por WhatsApp
                    </a>
                    <?php endif; ?>
                </div>
                
                <?php if ($emp['instagram'] || $emp['facebook']): ?>
                <div class="sidebar-card">
                    <h4 class="sidebar-title">Redes Sociales</h4>
                    <div class="social-links">
                        <?php if ($emp['instagram']): ?>
                        <a href="https://instagram.com/<?= ltrim(e($emp['instagram']), '@') ?>" class="social-link instagram" target="_blank" title="Instagram">
                            <svg viewBox="0 0 24 24" width="24" height="24" fill="currentColor"><path d="M12 2.163c3.204 0 3.584.012 4.85.07 3.252.148 4.771 1.691 4.919 4.919.058 1.265.069 1.645.069 4.849 0 3.205-.012 3.584-.069 4.849-.149 3.225-1.664 4.771-4.919 4.919-1.266.058-1.644.07-4.85.07-3.204 0-3.584-.012-4.849-.07-3.26-.149-4.771-1.699-4.919-4.92-.058-1.265-.07-1.644-.07-4.849 0-3.204.013-3.583.07-4.849.149-3.227 1.664-4.771 4.919-4.919 1.266-.057 1.645-.069 4.849-.069zm0-2.163c-3.259 0-3.667.014-4.947.072-4.358.2-6.78 2.618-6.98 6.98-.059 1.281-.073 1.689-.073 4.948 0 3.259.014 3.668.072 4.948.2 4.358 2.618 6.78 6.98 6.98 1.281.058 1.689.072 4.948.072 3.259 0 3.668-.014 4.948-.072 4.354-.2 6.782-2.618 6.979-6.98.059-1.28.073-1.689.073-4.948 0-3.259-.014-3.667-.072-4.947-.196-4.354-2.617-6.78-6.979-6.98-1.281-.059-1.69-.073-4.949-.073zm0 5.838c-3.403 0-6.162 2.759-6.162 6.162s2.759 6.163 6.162 6.163 6.162-2.759 6.162-6.163c0-3.403-2.759-6.162-6.162-6.162zm0 10.162c-2.209 0-4-1.79-4-4 0-2.209 1.791-4 4-4s4 1.791 4 4c0 2.21-1.791 4-4 4zm6.406-11.845c-.796 0-1.441.645-1.441 1.44s.645 1.44 1.441 1.44c.795 0 1.439-.645 1.439-1.44s-.644-1.44-1.439-1.44z"/></svg>
                        </a>
                        <?php endif; ?>
                        <?php if ($emp['facebook']): ?>
                        <a href="<?= e($emp['facebook']) ?>" class="social-link facebook" target="_blank" title="Facebook">
                            <svg viewBox="0 0 24 24" width="24" height="24" fill="currentColor"><path d="M24 12.073c0-6.627-5.373-12-12-12s-12 5.373-12 12c0 5.99 4.388 10.954 10.125 11.854v-8.385H7.078v-3.47h3.047V9.43c0-3.007 1.792-4.669 4.533-4.669 1.312 0 2.686.235 2.686.235v2.953H15.83c-1.491 0-1.956.925-1.956 1.874v2.25h3.328l-.532 3.47h-2.796v8.385C19.612 23.027 24 18.062 24 12.073z"/></svg>
                        </a>
                        <?php endif; ?>
                    </div>
                </div>
                <?php endif; ?>
                
                <div class="sidebar-card" style="background: var(--earth-100); border: 1px dashed var(--earth-300, #ccc);">
                    <a href="<?= BASE_URL ?>/editar-emprendimiento.php?slug=<?= e($emp['slug']) ?>" class="owner-edit-link">
                        ✏️ Soy el propietario y quiero editar
                    </a>
                </div>
            </aside>
        </div>
        
        <?php if (!empty($relacionados)): ?>
        <div class="relacionados">
            <h3>Más en <?= e($emp['categoria_nombre']) ?></h3>
            <div class="rel-grid">
                <?php foreach ($relacionados as $rel): ?>
                <a href="<?= BASE_URL ?>/ver.php?slug=<?= e($rel['slug']) ?>" class="rel-card">
                    <?php if ($rel['imagen_principal']): ?>
                        <img class="rel-img" src="<?= e(getImageUrl($rel['imagen_principal'])) ?>" alt="">
                    <?php else: ?>
                        <div class="rel-img" style="display: flex; align-items: center; justify-content: center; font-size: 2rem;"><?= $rel['categoria_icono'] ?></div>
                    <?php endif; ?>
                    <div class="rel-body">
                        <div class="rel-title"><?= e($rel['nombre']) ?></div>
                    </div>
                </a>
                <?php endforeach; ?>
            </div>
        </div>
        <?php endif; ?>
    </div>
    
    <footer class="footer">
        <p>🌊 Mi Valdivia Online — Una iniciativa de <a href="https://www.elcorreodevaldivia.cl" target="_blank">El Correo de Valdivia</a></p>
    </footer>
</body>
</html>
