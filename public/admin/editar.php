<?php
require_once __DIR__ . '/../../includes/config.php';
require_once __DIR__ . '/../../includes/image.php';
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
    SELECT e.*, ee.email as owner_email 
    FROM emprendimientos e 
    LEFT JOIN emprendimiento_emails ee ON e.id = ee.emprendimiento_id
    WHERE e.id = ?
");
$stmt->execute([$id]);
$emp = $stmt->fetch();

if (!$emp) {
    header('Location: ' . BASE_URL . '/admin/');
    exit;
}

// Obtener categorías
$categorias = $db->query("SELECT * FROM categorias WHERE activo = 1 ORDER BY orden")->fetchAll();

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $nombre = trim($_POST['nombre'] ?? '');
    $categoria_id = (int)($_POST['categoria_id'] ?? 0);
    $descripcion_corta = trim($_POST['descripcion_corta'] ?? '');
    $descripcion = trim($_POST['descripcion'] ?? '');
    $direccion = trim($_POST['direccion'] ?? '');
    $telefono = trim($_POST['telefono'] ?? '');
    $whatsapp = trim($_POST['whatsapp'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $web = trim($_POST['web'] ?? '');
    $instagram = trim($_POST['instagram'] ?? '');
    $facebook = trim($_POST['facebook'] ?? '');
    $horarios = trim($_POST['horarios'] ?? '');
    $rango_precios = trim($_POST['rango_precios'] ?? '');
    $estado = $_POST['estado'] ?? $emp['estado'];
    $estadoAnterior = $emp['estado'];
    $razonRechazo = trim($_POST['razon_rechazo'] ?? '');
    $destacado = isset($_POST['destacado']) ? 1 : 0;
    $aprobado_hasta = $_POST['aprobado_hasta'] ?? $emp['aprobado_hasta'];
    $renovar_periodo = (int)($_POST['renovar_periodo'] ?? 0);
    
    // Si se selecciona renovar, calcular nueva fecha
    if ($renovar_periodo > 0) {
        $base = $aprobado_hasta && strtotime($aprobado_hasta) > time() ? $aprobado_hasta : date('Y-m-d');
        $aprobado_hasta = date('Y-m-d', strtotime("+{$renovar_periodo} months", strtotime($base)));
    }
    
    if (!$nombre || !$categoria_id || !$descripcion_corta) {
        $error = 'Nombre, categoría y descripción corta son obligatorios.';
    } else {
        // Manejar imagen
        $imagenPrincipal = $emp['imagen_principal'];
        if (isset($_FILES['imagen']) && $_FILES['imagen']['error'] === UPLOAD_ERR_OK && $_FILES['imagen']['size'] > 0) {
            $uploadDir = UPLOADS_PATH . '/emprendimientos';
            $filename = $emp['slug'] . '-' . time();
            
            $resultado = handleImageUpload($_FILES['imagen'], $filename, $uploadDir, 1200);
            if ($resultado) {
                $imagenPrincipal = $resultado;
            } else {
                $error = 'Error al procesar la imagen. Usa JPG, PNG, WebP o GIF.';
            }
        } elseif (isset($_FILES['imagen']) && $_FILES['imagen']['error'] !== UPLOAD_ERR_NO_FILE) {
            $errores = [
                UPLOAD_ERR_INI_SIZE => 'Archivo muy grande (límite PHP)',
                UPLOAD_ERR_FORM_SIZE => 'Archivo muy grande (límite form)',
                UPLOAD_ERR_PARTIAL => 'Archivo subido parcialmente',
                UPLOAD_ERR_NO_TMP_DIR => 'Sin carpeta temporal',
                UPLOAD_ERR_CANT_WRITE => 'Error al escribir',
            ];
            $error = $errores[$_FILES['imagen']['error']] ?? 'Error desconocido al subir';
        }
        
        $stmt = $db->prepare("
            UPDATE emprendimientos SET
                nombre = ?, categoria_id = ?, descripcion_corta = ?, descripcion = ?,
                direccion = ?, telefono = ?, whatsapp = ?, email = ?, web = ?,
                instagram = ?, facebook = ?, horarios = ?, rango_precios = ?,
                imagen_principal = ?, estado = ?, destacado = ?, aprobado_hasta = ?, updated_at = NOW()
            WHERE id = ?
        ");
        
        $stmt->execute([
            $nombre, $categoria_id, $descripcion_corta, $descripcion,
            $direccion, $telefono, $whatsapp, $email, $web,
            $instagram, $facebook, $horarios, $rango_precios,
            $imagenPrincipal, $estado, $destacado, $aprobado_hasta ?: null, $id
        ]);
        
        $success = 'Emprendimiento actualizado correctamente.';
        
        // Notificar si cambió el estado
        $emailPropietario = $emp['owner_email'] ?: $emp['email'];
        if ($emailPropietario && $estado !== $estadoAnterior) {
            if ($estado === 'aprobado') {
                // Actualizar $emp con el slug para la notificación
                $empNotif = $emp;
                $empNotif['nombre'] = $nombre;
                notificarAprobacion($empNotif, $emailPropietario);
                $success .= ' Propietario notificado por email.';
            } elseif ($estado === 'rechazado' && $razonRechazo) {
                $empNotif = $emp;
                $empNotif['nombre'] = $nombre;
                notificarRechazo($empNotif, $emailPropietario, $razonRechazo);
                $success .= ' Propietario notificado por email.';
            }
        }
        
        // Recargar datos
        $stmt = $db->prepare("
            SELECT e.*, ee.email as owner_email 
            FROM emprendimientos e 
            LEFT JOIN emprendimiento_emails ee ON e.id = ee.emprendimiento_id
            WHERE e.id = ?
        ");
        $stmt->execute([$id]);
        $emp = $stmt->fetch();
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
<?php include __DIR__ . "/../../includes/analytics.php"; ?>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Editar: <?= e($emp['nombre']) ?> — Admin Mi Valdivia Online</title>
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@400;500;600;700&display=swap" rel="stylesheet">
    <style>
        :root {
            --river-600: #1976d2;
            --river-700: #1565a0;
            --river-900: #0c2d4a;
            --earth-100: #f0f7fc;
            --earth-200: #dce8f4;
            --earth-300: #b8d4e8;
            --earth-800: #2c3e50;
            --earth-900: #1a252f;
            --white: #ffffff;
            --green-600: #16a34a;
            --red-500: #dc2626;
            --font-body: 'Outfit', system-ui, sans-serif;
        }
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: var(--font-body); background: var(--earth-100); min-height: 100vh; }
        
        .header { background: var(--river-900); padding: 1rem 1.5rem; }
        .header-inner { max-width: 900px; margin: 0 auto; display: flex; justify-content: space-between; align-items: center; }
        .logo { font-size: 1.25rem; color: var(--white); text-decoration: none; font-weight: 600; }
        .header a { color: var(--earth-200); text-decoration: none; font-size: 0.9rem; }
        
        .container { max-width: 900px; margin: 0 auto; padding: 2rem 1.5rem; }
        
        h1 { font-size: 1.5rem; color: var(--earth-900); margin-bottom: 1.5rem; }
        
        .card { background: var(--white); border-radius: 12px; padding: 2rem; box-shadow: 0 2px 10px rgba(0,0,0,0.06); }
        
        .section-title {
            font-size: 1rem;
            font-weight: 600;
            color: var(--river-700);
            margin: 1.5rem 0 1rem;
            padding-bottom: 0.5rem;
            border-bottom: 2px solid var(--earth-200);
        }
        .section-title:first-child { margin-top: 0; }
        
        .form-row { display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 1rem; margin-bottom: 1rem; }
        .form-group { margin-bottom: 1rem; }
        
        label { display: block; font-size: 0.85rem; font-weight: 500; color: var(--earth-800); margin-bottom: 0.4rem; }
        label .required { color: var(--red-500); }
        
        input, select, textarea {
            width: 100%;
            padding: 0.75rem 1rem;
            border: 1px solid var(--earth-200);
            border-radius: 8px;
            font-family: var(--font-body);
            font-size: 0.95rem;
            color: var(--earth-900);
        }
        input:focus, select:focus, textarea:focus { outline: none; border-color: var(--river-600); }
        textarea { resize: vertical; min-height: 100px; }
        
        .checkbox-group { display: flex; align-items: center; gap: 0.5rem; }
        .checkbox-group input { width: auto; }
        
        .current-img { margin-top: 0.5rem; }
        .current-img img { max-width: 200px; border-radius: 8px; }
        
        .actions { display: flex; gap: 1rem; margin-top: 2rem; padding-top: 1.5rem; border-top: 2px solid var(--earth-200); }
        
        .btn {
            padding: 0.875rem 1.5rem;
            border: none;
            border-radius: 8px;
            font-family: var(--font-body);
            font-size: 0.95rem;
            font-weight: 600;
            cursor: pointer;
            text-decoration: none;
        }
        .btn-primary { background: var(--river-600); color: white; }
        .btn-primary:hover { background: var(--river-700); }
        .btn-secondary { background: var(--earth-200); color: var(--earth-800); }
        
        .error, .success { padding: 1rem; border-radius: 8px; margin-bottom: 1.5rem; }
        .error { background: #fef2f2; border: 1px solid #fecaca; color: var(--red-500); }
        .success { background: #f0fdf4; border: 1px solid #bbf7d0; color: var(--green-600); }
        
        .status-badges { display: flex; gap: 0.5rem; flex-wrap: wrap; }
        .status-badge {
            padding: 0.5rem 1rem;
            border-radius: 8px;
            font-size: 0.85rem;
            cursor: pointer;
            border: 2px solid transparent;
        }
        .status-badge.pendiente { background: #fef3c7; color: #92400e; }
        .status-badge.aprobado { background: #dcfce7; color: #166534; }
        .status-badge.rechazado { background: #fee2e2; color: #991b1b; }
        .status-badge.pausado { background: #f3f4f6; color: #374151; }
        .status-badge.selected { border-color: var(--river-600); }
    </style>
</head>
<body>
    <header class="header">
        <div class="header-inner">
            <a href="<?= BASE_URL ?>/admin/" class="logo">🌊 Admin — Mi Valdivia Online</a>
            <a href="<?= BASE_URL ?>/admin/">← Volver al listado</a>
        </div>
    </header>
    
    <div class="container">
        <h1>Editar: <?= e($emp['nombre']) ?></h1>
        
        <?php if ($success): ?>
            <div class="success">✓ <?= e($success) ?></div>
        <?php endif; ?>
        
        <?php if ($error): ?>
            <div class="error"><?= e($error) ?></div>
        <?php endif; ?>
        
        <form method="POST" enctype="multipart/form-data" class="card">
            
            <h3 class="section-title">📋 Información básica</h3>
            
            <div class="form-group">
                <label>Nombre <span class="required">*</span></label>
                <input type="text" name="nombre" value="<?= e($emp['nombre']) ?>" required>
            </div>
            
            <div class="form-row">
                <div class="form-group">
                    <label>Categoría <span class="required">*</span></label>
                    <select name="categoria_id" required>
                        <?php foreach ($categorias as $cat): ?>
                            <option value="<?= $cat['id'] ?>" <?= $emp['categoria_id'] == $cat['id'] ? 'selected' : '' ?>>
                                <?= $cat['icono'] ?> <?= e($cat['nombre']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group">
                    <label>Rango de precios</label>
                    <select name="rango_precios">
                        <option value="">No especificar</option>
                        <option value="$" <?= $emp['rango_precios'] === '$' ? 'selected' : '' ?>>$ — Económico</option>
                        <option value="$$" <?= $emp['rango_precios'] === '$$' ? 'selected' : '' ?>>$$ — Moderado</option>
                        <option value="$$$" <?= $emp['rango_precios'] === '$$$' ? 'selected' : '' ?>>$$$ — Premium</option>
                    </select>
                </div>
            </div>
            
            <div class="form-group">
                <label>Descripción corta <span class="required">*</span></label>
                <input type="text" name="descripcion_corta" value="<?= e($emp['descripcion_corta']) ?>" required maxlength="300">
            </div>
            
            <div class="form-group">
                <label>Descripción completa</label>
                <textarea name="descripcion" rows="4"><?= e($emp['descripcion']) ?></textarea>
            </div>
            
            <h3 class="section-title">🌊 Ubicación y contacto</h3>
            
            <div class="form-group">
                <label>Dirección</label>
                <input type="text" name="direccion" value="<?= e($emp['direccion']) ?>">
            </div>
            
            <div class="form-row">
                <div class="form-group">
                    <label>Teléfono</label>
                    <input type="tel" name="telefono" value="<?= e($emp['telefono']) ?>">
                </div>
                <div class="form-group">
                    <label>WhatsApp</label>
                    <input type="tel" name="whatsapp" value="<?= e($emp['whatsapp']) ?>">
                </div>
            </div>
            
            <div class="form-row">
                <div class="form-group">
                    <label>Email</label>
                    <input type="email" name="email" value="<?= e($emp['email']) ?>">
                </div>
                <div class="form-group">
                    <label>Sitio web</label>
                    <input type="url" name="web" value="<?= e($emp['web']) ?>">
                </div>
            </div>
            
            <h3 class="section-title">📱 Redes sociales</h3>
            
            <div class="form-row">
                <div class="form-group">
                    <label>Instagram</label>
                    <input type="text" name="instagram" value="<?= e($emp['instagram']) ?>">
                </div>
                <div class="form-group">
                    <label>Facebook</label>
                    <input type="url" name="facebook" value="<?= e($emp['facebook']) ?>">
                </div>
            </div>
            
            <h3 class="section-title">🕐 Horarios</h3>
            
            <div class="form-group">
                <label>Horarios de atención</label>
                <textarea name="horarios" rows="3"><?= e($emp['horarios']) ?></textarea>
            </div>
            
            <h3 class="section-title">📷 Imagen</h3>
            
            <div class="form-group">
                <label>Cambiar imagen</label>
                <input type="file" name="imagen" accept="image/jpeg,image/png,image/webp,image/gif">
                <?php if ($emp['imagen_principal']): ?>
                    <div class="current-img">
                        <p style="font-size: 0.85rem; color: var(--earth-800); margin: 0.5rem 0;">Imagen actual:</p>
                        <img src="<?= e(getImageUrl($emp['imagen_principal'])) ?>" alt="">
                    </div>
                <?php endif; ?>
            </div>
            
            <h3 class="section-title">⚙️ Estado</h3>
            
            <div class="form-group">
                <label>Estado del emprendimiento</label>
                <div class="status-badges">
                    <label class="status-badge pendiente <?= $emp['estado'] === 'pendiente' ? 'selected' : '' ?>">
                        <input type="radio" name="estado" value="pendiente" <?= $emp['estado'] === 'pendiente' ? 'checked' : '' ?> style="display:none;">
                        ⏳ Pendiente
                    </label>
                    <label class="status-badge aprobado <?= $emp['estado'] === 'aprobado' ? 'selected' : '' ?>">
                        <input type="radio" name="estado" value="aprobado" <?= $emp['estado'] === 'aprobado' ? 'checked' : '' ?> style="display:none;">
                        ✅ Aprobado
                    </label>
                    <label class="status-badge rechazado <?= $emp['estado'] === 'rechazado' ? 'selected' : '' ?>">
                        <input type="radio" name="estado" value="rechazado" <?= $emp['estado'] === 'rechazado' ? 'checked' : '' ?> style="display:none;">
                        ❌ Rechazado
                    </label>
                    <label class="status-badge pausado <?= $emp['estado'] === 'pausado' ? 'selected' : '' ?>">
                        <input type="radio" name="estado" value="pausado" <?= $emp['estado'] === 'pausado' ? 'checked' : '' ?> style="display:none;">
                        ⏸️ Pausado
                    </label>
                </div>
            </div>
            
            <div class="form-group" id="razon-rechazo-box" style="display: none;">
                <label style="color: #dc2626;">Motivo del rechazo (se enviará por email al propietario)</label>
                <textarea name="razon_rechazo" id="razon_rechazo" rows="3" placeholder="Explica qué debe corregir para ser aprobado..."></textarea>
            </div>
            
            <div class="form-row">
                <div class="form-group">
                    <label>Aprobado hasta</label>
                    <input type="date" name="aprobado_hasta" value="<?= $emp['aprobado_hasta'] ? e($emp['aprobado_hasta']) : '' ?>">
                    <?php if ($emp['aprobado_hasta']): ?>
                        <p style="font-size: 0.8rem; color: var(--earth-800); margin-top: 0.3rem;">
                            <?php 
                            $expira = strtotime($emp['aprobado_hasta']);
                            $hoy = time();
                            if ($expira < $hoy) {
                                echo '⚠️ <span style="color: #dc2626;">Expirado</span>';
                            } else {
                                $dias = ceil(($expira - $hoy) / 86400);
                                echo "Quedan {$dias} días";
                            }
                            ?>
                        </p>
                    <?php endif; ?>
                </div>
                <div class="form-group">
                    <label>Renovar por</label>
                    <select name="renovar_periodo" style="padding: 0.75rem 1rem;">
                        <option value="">No renovar</option>
                        <option value="1">+1 mes</option>
                        <option value="6">+6 meses</option>
                        <option value="12">+1 año</option>
                    </select>
                </div>
            </div>
            
            <div class="form-group">
                <label class="checkbox-group">
                    <input type="checkbox" name="destacado" <?= $emp['destacado'] ? 'checked' : '' ?>>
                    ⭐ Emprendimiento destacado
                </label>
            </div>
            
            <div class="actions">
                <button type="submit" class="btn btn-primary">💾 Guardar cambios</button>
                <a href="<?= BASE_URL ?>/admin/" class="btn btn-secondary">Cancelar</a>
            </div>
        </form>
    </div>
    
    <script>
        const razonBox = document.getElementById('razon-rechazo-box');
        const razonTextarea = document.getElementById('razon_rechazo');
        
        function toggleRazonRechazo() {
            const rechazadoSelected = document.querySelector('input[name="estado"][value="rechazado"]').checked;
            const estadoActual = '<?= $emp['estado'] ?>';
            
            // Mostrar si se selecciona rechazado Y no estaba ya rechazado
            if (rechazadoSelected && estadoActual !== 'rechazado') {
                razonBox.style.display = 'block';
                razonTextarea.required = true;
            } else {
                razonBox.style.display = 'none';
                razonTextarea.required = false;
            }
        }
        
        document.querySelectorAll('.status-badge').forEach(badge => {
            badge.addEventListener('click', () => {
                document.querySelectorAll('.status-badge').forEach(b => b.classList.remove('selected'));
                badge.classList.add('selected');
                toggleRazonRechazo();
            });
        });
        
        // Check inicial
        toggleRazonRechazo();
    </script>
</body>
</html>
