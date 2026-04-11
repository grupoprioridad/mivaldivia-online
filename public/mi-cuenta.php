<?php
require_once __DIR__ . '/../includes/config.php';
requireLogin();

$db = getDB();
$success = flash('success');

// Obtener emprendimientos del usuario
$stmt = $db->prepare("
    SELECT e.*, c.nombre as categoria_nombre, c.icono as categoria_icono 
    FROM emprendimientos e 
    JOIN categorias c ON e.categoria_id = c.id 
    WHERE e.usuario_id = ?
    ORDER BY e.created_at DESC
");
$stmt->execute([$_SESSION['usuario_id']]);
$emprendimientos = $stmt->fetchAll();

$estadoColores = [
    'pendiente' => '#f59e0b',
    'aprobado' => '#16a34a',
    'rechazado' => '#dc2626',
    'pausado' => '#6b7280'
];
$estadoTextos = [
    'pendiente' => '⏳ Pendiente de revisión',
    'aprobado' => '✅ Publicado',
    'rechazado' => '❌ Rechazado',
    'pausado' => '⏸️ Pausado'
];
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Mi cuenta — Mi Valdivia Online</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@400;500;600;700&family=Fraunces:opsz,wght@9..144,400;9..144,600&display=swap" rel="stylesheet">
    <style>
        :root {
            --river-600: #1976d2;
            --river-700: #1565a0;
            --river-900: #0c2d4a;
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
        body { font-family: var(--font-body); background: var(--earth-100); min-height: 100vh; }
        .header {
            background: var(--river-900);
            padding: 1rem 1.5rem;
        }
        .header-inner {
            max-width: 900px;
            margin: 0 auto;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        .logo { font-family: var(--font-display); font-size: 1.25rem; color: var(--white); text-decoration: none; }
        .header-links { display: flex; gap: 1.5rem; }
        .header-links a { color: var(--earth-200); text-decoration: none; font-size: 0.9rem; }
        .container { max-width: 900px; margin: 0 auto; padding: 2rem 1.5rem; }
        h1 { font-family: var(--font-display); font-size: 2rem; color: var(--earth-900); margin-bottom: 0.5rem; }
        .welcome { color: var(--earth-800); margin-bottom: 2rem; }
        .success { background: #f0fdf4; border: 1px solid #bbf7d0; color: var(--green-600); padding: 1rem; border-radius: 8px; margin-bottom: 1.5rem; }
        
        .actions-bar {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 1.5rem;
        }
        .btn {
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            padding: 0.75rem 1.25rem;
            background: var(--river-600);
            color: var(--white);
            text-decoration: none;
            border-radius: 8px;
            font-weight: 600;
            font-size: 0.9rem;
        }
        .btn:hover { background: var(--river-700); }
        
        .emp-list { display: flex; flex-direction: column; gap: 1rem; }
        .emp-card {
            background: var(--white);
            border-radius: 12px;
            padding: 1.5rem;
            display: flex;
            gap: 1.5rem;
            box-shadow: 0 2px 10px rgba(0,0,0,0.06);
        }
        .emp-img {
            width: 120px;
            height: 80px;
            border-radius: 8px;
            object-fit: cover;
            background: var(--earth-200);
            flex-shrink: 0;
        }
        .emp-content { flex: 1; }
        .emp-header { display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 0.5rem; }
        .emp-title { font-family: var(--font-display); font-size: 1.25rem; color: var(--earth-900); }
        .emp-title a { color: inherit; text-decoration: none; }
        .emp-status {
            font-size: 0.75rem;
            font-weight: 600;
            padding: 0.25rem 0.6rem;
            border-radius: 100px;
            white-space: nowrap;
        }
        .emp-cat { font-size: 0.85rem; color: var(--river-600); margin-bottom: 0.5rem; }
        .emp-desc { font-size: 0.9rem; color: var(--earth-800); opacity: 0.8; }
        .emp-actions { display: flex; gap: 1rem; margin-top: 1rem; }
        .emp-actions a { font-size: 0.85rem; color: var(--river-600); text-decoration: none; }
        
        .empty {
            background: var(--white);
            border-radius: 12px;
            padding: 3rem;
            text-align: center;
        }
        .empty-icon { font-size: 3rem; margin-bottom: 1rem; }
        .empty h3 { font-family: var(--font-display); color: var(--earth-900); margin-bottom: 0.5rem; }
        .empty p { color: var(--earth-800); margin-bottom: 1.5rem; }
        
        @media (max-width: 640px) {
            .emp-card { flex-direction: column; }
            .emp-img { width: 100%; height: 150px; }
        }
    </style>
</head>
<body>
    <header class="header">
        <div class="header-inner">
            <a href="<?= BASE_URL ?>" class="logo">🌊 Mi Valdivia Online</a>
            <div class="header-links">
                <a href="<?= BASE_URL ?>">Ver directorio</a>
                <a href="<?= BASE_URL ?>/logout.php">Cerrar sesión</a>
            </div>
        </div>
    </header>
    
    <div class="container">
        <h1>Mi cuenta</h1>
        <p class="welcome">Hola, <?= e($_SESSION['usuario_nombre']) ?> 👋</p>
        
        <?php if ($success): ?>
            <div class="success"><?= e($success) ?></div>
        <?php endif; ?>
        
        <div class="actions-bar">
            <h2 style="font-size: 1.1rem; color: var(--earth-800);">Mis emprendimientos</h2>
            <a href="<?= BASE_URL ?>/nuevo-emprendimiento.php" class="btn">➕ Agregar nuevo</a>
        </div>
        
        <?php if (empty($emprendimientos)): ?>
            <div class="empty">
                <div class="empty-icon">🏪</div>
                <h3>Aún no tienes emprendimientos</h3>
                <p>Registra tu primer emprendimiento y llega a más visitantes de Valdivia.</p>
                <a href="<?= BASE_URL ?>/nuevo-emprendimiento.php" class="btn">Registrar emprendimiento</a>
            </div>
        <?php else: ?>
            <div class="emp-list">
                <?php foreach ($emprendimientos as $emp): ?>
                    <div class="emp-card">
                        <?php if ($emp['imagen_principal']): ?>
                            <img class="emp-img" src="<?= e(getImageUrl($emp['imagen_principal'])) ?>" alt="">
                        <?php else: ?>
                            <div class="emp-img" style="display: flex; align-items: center; justify-content: center; font-size: 2rem;">
                                <?= $emp['categoria_icono'] ?>
                            </div>
                        <?php endif; ?>
                        <div class="emp-content">
                            <div class="emp-header">
                                <h3 class="emp-title">
                                    <?php if ($emp['estado'] === 'aprobado'): ?>
                                        <a href="<?= BASE_URL ?>/ver.php?slug=<?= e($emp['slug']) ?>"><?= e($emp['nombre']) ?></a>
                                    <?php else: ?>
                                        <?= e($emp['nombre']) ?>
                                    <?php endif; ?>
                                </h3>
                                <span class="emp-status" style="background: <?= $estadoColores[$emp['estado']] ?>20; color: <?= $estadoColores[$emp['estado']] ?>">
                                    <?= $estadoTextos[$emp['estado']] ?>
                                </span>
                            </div>
                            <p class="emp-cat"><?= $emp['categoria_icono'] ?> <?= e($emp['categoria_nombre']) ?></p>
                            <p class="emp-desc"><?= e($emp['descripcion_corta']) ?></p>
                            <div class="emp-actions">
                                <a href="<?= BASE_URL ?>/editar-emprendimiento.php?id=<?= $emp['id'] ?>">✏️ Editar</a>
                                <?php if ($emp['estado'] === 'aprobado'): ?>
                                    <a href="<?= BASE_URL ?>/ver.php?slug=<?= e($emp['slug']) ?>">👁️ Ver publicación</a>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>
</body>
</html>
