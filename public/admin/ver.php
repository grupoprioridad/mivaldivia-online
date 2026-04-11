<?php
require_once __DIR__ . '/../../includes/config.php';
require_once __DIR__ . '/../../includes/notificaciones.php';
requireAdmin();

$db = getDB();
$id = (int)($_GET['id'] ?? 0);

if (!$id) {
    header('Location: ' . BASE_URL . '/admin/');
    exit;
}

// Obtener emprendimiento con email del propietario
$stmt = $db->prepare("
    SELECT e.*, c.nombre as categoria_nombre, c.icono as categoria_icono, ee.email as owner_email
    FROM emprendimientos e 
    JOIN categorias c ON e.categoria_id = c.id 
    LEFT JOIN emprendimiento_emails ee ON e.id = ee.emprendimiento_id
    WHERE e.id = ?
");
$stmt->execute([$id]);
$emp = $stmt->fetch();

if (!$emp) {
    header('Location: ' . BASE_URL . '/admin/');
    exit;
}

// Procesar acciones rápidas
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $accion = $_POST['accion'] ?? '';
    
    if ($accion === 'aprobar') {
        $periodo = (int)($_POST['periodo'] ?? 12);
        $hastaFecha = date('Y-m-d', strtotime("+{$periodo} months"));
        $db->prepare("UPDATE emprendimientos SET estado = 'aprobado', aprobado_hasta = ? WHERE id = ?")->execute([$hastaFecha, $id]);
        
        // Notificar al propietario
        $emailPropietario = $emp['owner_email'] ?: $emp['email'];
        if ($emailPropietario) {
            notificarAprobacion($emp, $emailPropietario);
        }
        
        registrarAuditoria('aprobar', $id, $emp['nombre'], ['periodo_meses' => $periodo, 'hasta' => $hastaFecha]);
        flash('success', 'Emprendimiento aprobado hasta ' . date('d/m/Y', strtotime($hastaFecha)));
        header('Location: ' . BASE_URL . '/admin/');
        exit;
    } elseif ($accion === 'rechazar') {
        $razon = trim($_POST['razon_rechazo'] ?? '');
        $db->prepare("UPDATE emprendimientos SET estado = 'rechazado' WHERE id = ?")->execute([$id]);
        
        // Notificar al propietario
        $emailPropietario = $emp['owner_email'] ?: $emp['email'];
        if ($emailPropietario && $razon) {
            notificarRechazo($emp, $emailPropietario, $razon);
        }
        
        registrarAuditoria('rechazar', $id, $emp['nombre'], ['razon' => $razon]);
        flash('success', 'Emprendimiento rechazado y propietario notificado.');
        header('Location: ' . BASE_URL . '/admin/');
        exit;
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
<?php include __DIR__ . '/../../includes/analytics.php'; ?>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Revisar: <?= e($emp['nombre']) ?> — Admin Mi Valdivia Online</title>
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@400;500;600;700&display=swap" rel="stylesheet">
    <style>
        :root {
            --river-600: #1976d2;
            --river-700: #1565a0;
            --river-900: #0c2d4a;
            --earth-100: #f0f7fc;
            --earth-200: #dce8f4;
            --earth-800: #2c3e50;
            --earth-900: #1a252f;
            --white: #ffffff;
            --green-600: #16a34a;
            --green-100: #dcfce7;
            --red-500: #dc2626;
            --red-100: #fee2e2;
            --yellow-500: #eab308;
            --yellow-100: #fef9c3;
            --blue-500: #3b82f6;
            --font-body: 'Outfit', system-ui, sans-serif;
        }
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: var(--font-body); background: var(--earth-100); min-height: 100vh; }
        
        .header { background: var(--river-900); padding: 1rem 1.5rem; }
        .header-inner { max-width: 1000px; margin: 0 auto; display: flex; justify-content: space-between; align-items: center; }
        .logo { font-size: 1.25rem; color: var(--white); text-decoration: none; font-weight: 600; }
        .header-links { display: flex; gap: 1.5rem; }
        .header-links a { color: var(--earth-200); text-decoration: none; font-size: 0.9rem; }
        
        .container { max-width: 1000px; margin: 0 auto; padding: 2rem 1.5rem; }
        
        .status-bar {
            display: flex;
            justify-content: space-between;
            align-items: center;
            background: var(--white);
            padding: 1rem 1.5rem;
            border-radius: 12px;
            margin-bottom: 1.5rem;
            box-shadow: 0 2px 10px rgba(0,0,0,0.06);
        }
        
        .status-info { display: flex; align-items: center; gap: 1rem; }
        
        .badge {
            padding: 0.5rem 1rem;
            border-radius: 100px;
            font-size: 0.85rem;
            font-weight: 600;
        }
        .badge-pending { background: var(--yellow-100); color: #92400e; }
        .badge-approved { background: var(--green-100); color: #166534; }
        .badge-rejected { background: var(--red-100); color: #991b1b; }
        
        .actions-bar {
            display: flex;
            gap: 0.75rem;
            align-items: center;
        }
        
        .btn {
            padding: 0.6rem 1.2rem;
            border: none;
            border-radius: 8px;
            font-family: var(--font-body);
            font-size: 0.9rem;
            font-weight: 600;
            cursor: pointer;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 0.4rem;
        }
        .btn-approve { background: var(--green-600); color: white; }
        .btn-reject { background: var(--red-500); color: white; }
        .btn-edit { background: var(--blue-500); color: white; }
        .btn-secondary { background: var(--earth-200); color: var(--earth-800); }
        
        select.periodo { padding: 0.6rem; border-radius: 8px; border: 1px solid #ddd; font-family: var(--font-body); }
        
        .grid { display: grid; grid-template-columns: 1fr 350px; gap: 1.5rem; }
        
        .card {
            background: var(--white);
            border-radius: 12px;
            padding: 1.5rem;
            box-shadow: 0 2px 10px rgba(0,0,0,0.06);
        }
        
        .card-title {
            font-size: 0.85rem;
            font-weight: 600;
            color: var(--river-700);
            text-transform: uppercase;
            letter-spacing: 0.03em;
            margin-bottom: 1rem;
            padding-bottom: 0.75rem;
            border-bottom: 2px solid var(--earth-200);
        }
        
        .emp-name {
            font-size: 1.75rem;
            font-weight: 700;
            color: var(--earth-900);
            margin-bottom: 0.5rem;
        }
        
        .emp-category {
            color: var(--earth-800);
            font-size: 1.1rem;
            margin-bottom: 1rem;
        }
        
        .emp-desc-short {
            font-size: 1.1rem;
            color: var(--earth-800);
            line-height: 1.5;
            padding: 1rem;
            background: var(--earth-100);
            border-radius: 8px;
            margin-bottom: 1rem;
        }
        
        .emp-desc {
            color: var(--earth-800);
            line-height: 1.6;
            white-space: pre-line;
        }
        
        .info-row {
            display: flex;
            padding: 0.75rem 0;
            border-bottom: 1px solid var(--earth-200);
        }
        .info-row:last-child { border: none; }
        
        .info-label {
            width: 120px;
            font-size: 0.85rem;
            color: var(--earth-800);
            opacity: 0.7;
            flex-shrink: 0;
        }
        
        .info-value {
            flex: 1;
            color: var(--earth-900);
            word-break: break-word;
        }
        
        .info-value a { color: var(--river-600); }
        
        .info-value.empty { color: #999; font-style: italic; }
        
        .image-preview {
            width: 100%;
            border-radius: 8px;
            margin-bottom: 1rem;
        }
        
        .no-image {
            width: 100%;
            height: 200px;
            background: var(--earth-200);
            border-radius: 8px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 4rem;
            margin-bottom: 1rem;
        }
        
        .horarios {
            background: var(--earth-100);
            padding: 1rem;
            border-radius: 8px;
            white-space: pre-line;
            font-size: 0.95rem;
        }
        
        .owner-email {
            background: var(--yellow-100);
            border: 1px solid var(--yellow-500);
            padding: 1rem;
            border-radius: 8px;
            margin-top: 1rem;
        }
        
        .owner-email-label {
            font-size: 0.8rem;
            color: #92400e;
            margin-bottom: 0.25rem;
        }
        
        .owner-email-value {
            font-weight: 600;
            color: #854d0e;
        }
        
        @media (max-width: 768px) {
            .grid { grid-template-columns: 1fr; }
            .status-bar { flex-direction: column; gap: 1rem; }
        }
    </style>
</head>
<body>
    <header class="header">
        <div class="header-inner">
            <a href="<?= BASE_URL ?>/admin/" class="logo">🌊 Admin — Mi Valdivia Online</a>
            <div class="header-links">
                <a href="<?= BASE_URL ?>/admin/">← Volver al listado</a>
                <a href="<?= BASE_URL ?>">Ver sitio</a>
            </div>
        </div>
    </header>
    
    <div class="container">
        <div class="status-bar">
            <div class="status-info">
                <span class="badge <?= $emp['estado'] === 'pendiente' ? 'badge-pending' : ($emp['estado'] === 'aprobado' ? 'badge-approved' : 'badge-rejected') ?>">
                    <?= ucfirst($emp['estado']) ?>
                </span>
                <span style="color: var(--earth-800);">
                    <?php 
                    $tz = new DateTimeZone('America/Santiago');
                    $creado = new DateTime($emp['created_at'], new DateTimeZone('UTC'));
                    $creado->setTimezone($tz);
                    ?>
                    Creado: <?= $creado->format('d/m/Y H:i') ?>
                    <?php if ($emp['updated_at'] && $emp['updated_at'] !== $emp['created_at']): 
                        $editado = new DateTime($emp['updated_at'], new DateTimeZone('UTC'));
                        $editado->setTimezone($tz);
                    ?>
                        | Editado: <?= $editado->format('d/m/Y H:i') ?>
                    <?php endif; ?>
                </span>
            </div>
            
            <div class="actions-bar">
                <a href="<?= BASE_URL ?>/admin/editar.php?id=<?= $emp['id'] ?>" class="btn btn-edit">✏️ Editar</a>
                
                <?php if ($emp['estado'] === 'pendiente' || $emp['estado'] === 'rechazado'): ?>
                <form method="POST" style="display: flex; gap: 0.5rem; align-items: center;">
                    <input type="hidden" name="accion" value="aprobar">
                    <select name="periodo" class="periodo">
                        <option value="1">1 mes</option>
                        <option value="6">6 meses</option>
                        <option value="12" selected>1 año</option>
                    </select>
                    <button type="submit" class="btn btn-approve">✓ Aprobar</button>
                </form>
                <button type="button" class="btn btn-reject" onclick="abrirModalRechazo()">✗ Rechazar</button>
                <?php endif; ?>
            </div>
        </div>
        
        <div class="grid">
            <div>
                <div class="card" style="margin-bottom: 1.5rem;">
                    <h1 class="emp-name"><?= e($emp['nombre']) ?></h1>
                    <p class="emp-category"><?= $emp['categoria_icono'] ?> <?= e($emp['categoria_nombre']) ?></p>
                    
                    <div class="emp-desc-short"><?= e($emp['descripcion_corta']) ?></div>
                    
                    <?php if ($emp['descripcion']): ?>
                        <h3 class="card-title" style="margin-top: 1.5rem;">Descripción completa</h3>
                        <p class="emp-desc"><?= nl2br(e($emp['descripcion'])) ?></p>
                    <?php endif; ?>
                </div>
                
                <div class="card">
                    <h3 class="card-title">🌊 Contacto y ubicación</h3>
                    
                    <div class="info-row">
                        <span class="info-label">Dirección</span>
                        <span class="info-value <?= !$emp['direccion'] ? 'empty' : '' ?>">
                            <?= $emp['direccion'] ? e($emp['direccion']) : 'No especificada' ?>
                        </span>
                    </div>
                    
                    <div class="info-row">
                        <span class="info-label">Teléfono</span>
                        <span class="info-value <?= !$emp['telefono'] ? 'empty' : '' ?>">
                            <?= $emp['telefono'] ? '<a href="tel:' . e($emp['telefono']) . '">' . e($emp['telefono']) . '</a>' : 'No especificado' ?>
                        </span>
                    </div>
                    
                    <div class="info-row">
                        <span class="info-label">WhatsApp</span>
                        <span class="info-value <?= !$emp['whatsapp'] ? 'empty' : '' ?>">
                            <?= $emp['whatsapp'] ? '<a href="https://wa.me/' . preg_replace('/[^0-9]/', '', $emp['whatsapp']) . '" target="_blank">' . e($emp['whatsapp']) . '</a>' : 'No especificado' ?>
                        </span>
                    </div>
                    
                    <div class="info-row">
                        <span class="info-label">Email</span>
                        <span class="info-value <?= !$emp['email'] ? 'empty' : '' ?>">
                            <?= $emp['email'] ? '<a href="mailto:' . e($emp['email']) . '">' . e($emp['email']) . '</a>' : 'No especificado' ?>
                        </span>
                    </div>
                    
                    <div class="info-row">
                        <span class="info-label">Sitio web</span>
                        <span class="info-value <?= !$emp['web'] ? 'empty' : '' ?>">
                            <?= $emp['web'] ? '<a href="' . e($emp['web']) . '" target="_blank">' . e($emp['web']) . '</a>' : 'No especificado' ?>
                        </span>
                    </div>
                    
                    <div class="info-row">
                        <span class="info-label">Instagram</span>
                        <span class="info-value <?= !$emp['instagram'] ? 'empty' : '' ?>">
                            <?= $emp['instagram'] ? '<a href="https://instagram.com/' . ltrim(e($emp['instagram']), '@') . '" target="_blank">' . e($emp['instagram']) . '</a>' : 'No especificado' ?>
                        </span>
                    </div>
                    
                    <div class="info-row">
                        <span class="info-label">Facebook</span>
                        <span class="info-value <?= !$emp['facebook'] ? 'empty' : '' ?>">
                            <?= $emp['facebook'] ? '<a href="' . e($emp['facebook']) . '" target="_blank">' . e($emp['facebook']) . '</a>' : 'No especificado' ?>
                        </span>
                    </div>
                    
                    <?php if ($emp['owner_email']): ?>
                    <div class="owner-email">
                        <div class="owner-email-label">📧 Email del propietario (verificado)</div>
                        <div class="owner-email-value"><?= e($emp['owner_email']) ?></div>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
            
            <div>
                <div class="card" style="margin-bottom: 1.5rem;">
                    <h3 class="card-title">📷 Imagen</h3>
                    <?php if ($emp['imagen_principal']): ?>
                        <img src="<?= e(getImageUrl($emp['imagen_principal'])) ?>" alt="<?= e($emp['nombre']) ?>" class="image-preview">
                    <?php else: ?>
                        <div class="no-image"><?= $emp['categoria_icono'] ?></div>
                        <p style="text-align: center; color: #999; font-size: 0.9rem;">Sin imagen</p>
                    <?php endif; ?>
                </div>
                
                <?php if ($emp['horarios']): ?>
                <div class="card" style="margin-bottom: 1.5rem;">
                    <h3 class="card-title">🕐 Horarios</h3>
                    <div class="horarios"><?= e($emp['horarios']) ?></div>
                </div>
                <?php endif; ?>
                
                <div class="card">
                    <h3 class="card-title">📊 Info adicional</h3>
                    
                    <div class="info-row">
                        <span class="info-label">Precio</span>
                        <span class="info-value"><?= $emp['rango_precios'] ?: 'No especificado' ?></span>
                    </div>
                    
                    <div class="info-row">
                        <span class="info-label">Visitas</span>
                        <span class="info-value"><?= number_format($emp['visitas']) ?></span>
                    </div>
                    
                    <div class="info-row">
                        <span class="info-label">Destacado</span>
                        <span class="info-value"><?= $emp['destacado'] ? '⭐ Sí' : 'No' ?></span>
                    </div>
                    
                    <div class="info-row">
                        <span class="info-label">Slug</span>
                        <span class="info-value" style="font-family: monospace; font-size: 0.85rem;"><?= e($emp['slug']) ?></span>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Modal de Rechazo -->
    <div id="modalRechazo" class="modal" style="display: none;">
        <div class="modal-backdrop" onclick="cerrarModalRechazo()"></div>
        <div class="modal-content">
            <h3>✗ Rechazar Emprendimiento</h3>
            <p class="modal-emp-name"><?= e($emp['nombre']) ?></p>
            
            <form method="POST">
                <input type="hidden" name="accion" value="rechazar">
                
                <div class="form-group">
                    <label>Motivo del rechazo <span style="color: #dc2626;">*</span></label>
                    <textarea name="razon_rechazo" rows="4" placeholder="Explica al propietario qué debe corregir para que su emprendimiento sea aprobado..." required></textarea>
                    <p class="hint">Este mensaje se enviará por email al propietario junto con un link para editar.</p>
                </div>
                
                <div class="modal-actions">
                    <button type="button" class="btn-modal btn-cancel" onclick="cerrarModalRechazo()">Cancelar</button>
                    <button type="submit" class="btn-modal btn-confirm">Rechazar y notificar</button>
                </div>
            </form>
        </div>
    </div>
    
    <style>
        .modal {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            z-index: 1000;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        .modal-backdrop {
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0,0,0,0.5);
        }
        .modal-content {
            position: relative;
            background: white;
            border-radius: 16px;
            padding: 2rem;
            width: 90%;
            max-width: 500px;
            box-shadow: 0 20px 60px rgba(0,0,0,0.3);
        }
        .modal-content h3 {
            margin: 0 0 0.5rem;
            color: #dc2626;
            font-size: 1.25rem;
        }
        .modal-emp-name {
            color: #666;
            margin: 0 0 1.5rem;
            padding-bottom: 1rem;
            border-bottom: 1px solid #eee;
        }
        .modal-content .form-group { margin-bottom: 1rem; }
        .modal-content label { 
            display: block; 
            margin-bottom: 0.5rem; 
            font-weight: 500;
            color: #333;
        }
        .modal-content textarea {
            width: 100%;
            padding: 0.75rem;
            border: 1px solid #ddd;
            border-radius: 8px;
            font-family: inherit;
            font-size: 0.95rem;
            resize: vertical;
        }
        .modal-content textarea:focus {
            outline: none;
            border-color: #1976d2;
        }
        .modal-content .hint {
            font-size: 0.8rem;
            color: #888;
            margin-top: 0.5rem;
        }
        .modal-actions {
            display: flex;
            gap: 1rem;
            margin-top: 1.5rem;
        }
        .btn-modal {
            flex: 1;
            padding: 0.75rem 1rem;
            border: none;
            border-radius: 8px;
            font-family: inherit;
            font-size: 0.95rem;
            font-weight: 600;
            cursor: pointer;
        }
        .btn-cancel {
            background: #f3f4f6;
            color: #374151;
        }
        .btn-confirm {
            background: #dc2626;
            color: white;
        }
        .btn-confirm:hover {
            background: #b91c1c;
        }
    </style>
    
    <script>
        function abrirModalRechazo() {
            document.getElementById('modalRechazo').style.display = 'flex';
        }
        
        function cerrarModalRechazo() {
            document.getElementById('modalRechazo').style.display = 'none';
        }
        
        document.addEventListener('keydown', function(e) {
            if (e.key === 'Escape') cerrarModalRechazo();
        });
    </script>
</body>
</html>
