<?php
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/image.php';

$db = getDB();
$slug = $_GET['slug'] ?? '';
$paso = $_GET['paso'] ?? 'verificar';
$error = '';
$success = '';

if (!$slug) {
    header('Location: ' . BASE_URL);
    exit;
}

// Obtener emprendimiento
$stmt = $db->prepare("SELECT e.*, ee.email as owner_email FROM emprendimientos e LEFT JOIN emprendimiento_emails ee ON e.id = ee.emprendimiento_id WHERE e.slug = ?");
$stmt->execute([$slug]);
$emp = $stmt->fetch();

if (!$emp) {
    header('Location: ' . BASE_URL);
    exit;
}

// Si no tiene email de propietario, usar el email del emprendimiento
$ownerEmail = $emp['owner_email'] ?: $emp['email'];

if (!$ownerEmail) {
    $error = 'Este emprendimiento no tiene un correo de contacto registrado. Contacta al administrador.';
    $paso = 'error';
}

// Verificar si ya está autenticado para editar este emprendimiento
$canEdit = isset($_SESSION['edit_emprendimiento_id']) && $_SESSION['edit_emprendimiento_id'] == $emp['id'];

// Enviar código
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['enviar_codigo'])) {
    // Limpiar códigos anteriores
    $stmt = $db->prepare("UPDATE verificacion_codigos SET usado = 1 WHERE email = ?");
    $stmt->execute([$ownerEmail]);
    
    // Generar código
    $codigo = generarCodigo();
    $stmt = $db->prepare("INSERT INTO verificacion_codigos (email, codigo, expira_at) VALUES (?, ?, DATE_ADD(NOW(), INTERVAL 10 MINUTE))");
    $stmt->execute([$ownerEmail, $codigo]);
    
    // Enviar correo
    // Ocultar email: est***@pri***.cl
    $partes = explode('@', $ownerEmail);
    $usuario = substr($partes[0], 0, 3) . '***';
    $dominioParts = explode('.', $partes[1]);
    $dominio = substr($dominioParts[0], 0, 3) . '***.' . end($dominioParts);
    $emailOculto = $usuario . '@' . $dominio;
    
    $htmlBody = '
    <!DOCTYPE html>
    <html>
    <head><meta charset="UTF-8"></head>
    <body style="font-family: -apple-system, BlinkMacSystemFont, \'Segoe UI\', Roboto, sans-serif; background: #f0f7fc; padding: 40px 20px;">
        <div style="max-width: 400px; margin: 0 auto; background: white; border-radius: 12px; padding: 40px; box-shadow: 0 4px 20px rgba(0,0,0,0.1);">
            <div style="text-align: center; margin-bottom: 30px;">
                <span style="font-size: 2.5rem;">🌊</span>
                <h1 style="margin: 10px 0 0; color: #0c2d4a; font-size: 1.5rem;">Mi Valdivia Online</h1>
            </div>
            <p style="color: #2c3e50; margin-bottom: 10px;">Código para editar:</p>
            <p style="color: #666; font-size: 0.9rem; margin-bottom: 20px;"><strong>' . e($emp['nombre']) . '</strong></p>
            <div style="background: #dce8f4; border-radius: 8px; padding: 20px; text-align: center; margin-bottom: 20px;">
                <span style="font-size: 2rem; font-weight: bold; letter-spacing: 0.3em; color: #0c2d4a;">' . $codigo . '</span>
            </div>
            <p style="color: #6b7280; font-size: 0.9rem; margin: 0;">Este código expira en 10 minutos.</p>
        </div>
    </body>
    </html>';
    
    if (enviarCorreo($ownerEmail, 'Código para editar tu emprendimiento - Mi Valdivia Online', $htmlBody)) {
        $_SESSION['edit_pending_email'] = $ownerEmail;
        $_SESSION['edit_pending_emp_id'] = $emp['id'];
        $paso = 'codigo';
    } else {
        $error = 'No pudimos enviar el correo. Intenta de nuevo.';
    }
}

// Verificar código
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['verificar_codigo'])) {
    $codigoIngresado = preg_replace('/\s+/', '', $_POST['codigo'] ?? '');
    $email = $_SESSION['edit_pending_email'] ?? '';
    
    $stmt = $db->prepare("SELECT * FROM verificacion_codigos WHERE email = ? AND usado = 0 AND expira_at > NOW() ORDER BY created_at DESC LIMIT 1");
    $stmt->execute([$email]);
    $registro = $stmt->fetch();
    
    if (!$registro) {
        $error = 'El código ha expirado. Solicita uno nuevo.';
        $paso = 'verificar';
    } elseif ($registro['intentos'] >= 5) {
        $error = 'Demasiados intentos. Solicita un nuevo código.';
        $paso = 'verificar';
    } elseif ($codigoIngresado != $registro['codigo']) {
        $db->prepare("UPDATE verificacion_codigos SET intentos = intentos + 1 WHERE id = ?")->execute([$registro['id']]);
        $error = 'Código incorrecto. Te quedan ' . (4 - $registro['intentos']) . ' intentos.';
        $paso = 'codigo';
    } else {
        // Éxito
        $db->prepare("UPDATE verificacion_codigos SET usado = 1 WHERE id = ?")->execute([$registro['id']]);
        $_SESSION['edit_emprendimiento_id'] = $emp['id'];
        $canEdit = true;
        $paso = 'editar';
        unset($_SESSION['edit_pending_email']);
        unset($_SESSION['edit_pending_emp_id']);
    }
}

// Guardar edición
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['guardar_edicion']) && $canEdit) {
    $nombre = trim($_POST['nombre'] ?? '');
    $descripcion_corta = trim($_POST['descripcion_corta'] ?? '');
    $descripcion = trim($_POST['descripcion'] ?? '');
    $direccion = trim($_POST['direccion'] ?? '');
    $telefono = trim($_POST['telefono'] ?? '');
    $whatsapp = trim($_POST['whatsapp'] ?? '');
    $email_contacto = trim($_POST['email'] ?? '');
    $web = trim($_POST['web'] ?? '');
    $instagram = trim($_POST['instagram'] ?? '');
    $facebook = trim($_POST['facebook'] ?? '');
    $horarios = trim($_POST['horarios'] ?? '');
    
    if (!$nombre || !$descripcion_corta) {
        $error = 'El nombre y descripción corta son obligatorios.';
        $paso = 'editar';
    } else {
        // Manejar imagen
        $imagenPrincipal = $emp['imagen_principal'];
        if (isset($_FILES['imagen']) && $_FILES['imagen']['error'] === UPLOAD_ERR_OK && $_FILES['imagen']['size'] > 0) {
            $uploadDir = UPLOADS_PATH . '/emprendimientos';
            $filename = $emp['slug'] . '-' . time();
            $resultado = handleImageUpload($_FILES['imagen'], $filename, $uploadDir, 1200);
            if ($resultado) {
                $imagenPrincipal = $resultado;
            }
        }
        
        // Actualizar y poner en pendiente
        $stmt = $db->prepare("
            UPDATE emprendimientos SET
                nombre = ?, descripcion_corta = ?, descripcion = ?,
                direccion = ?, telefono = ?, whatsapp = ?, email = ?, web = ?,
                instagram = ?, facebook = ?, horarios = ?,
                imagen_principal = ?, estado = 'pendiente', updated_at = NOW()
            WHERE id = ?
        ");
        
        $stmt->execute([
            $nombre, $descripcion_corta, $descripcion,
            $direccion, $telefono, $whatsapp, $email_contacto, $web,
            $instagram, $facebook, $horarios,
            $imagenPrincipal, $emp['id']
        ]);
        
        // Limpiar sesión de edición
        unset($_SESSION['edit_emprendimiento_id']);
        
        flash('success', 'Cambios guardados. Tu emprendimiento está en revisión y volverá a publicarse cuando sea aprobado.');
        header('Location: ' . BASE_URL . '/editar-emprendimiento.php?slug=' . $slug . '&paso=completado');
        exit;
    }
}

// Obtener categorías
$categorias = $db->query("SELECT * FROM categorias WHERE activo = 1 ORDER BY orden")->fetchAll();

// Recargar datos del emprendimiento si estamos en modo edición
if ($canEdit) {
    $stmt = $db->prepare("SELECT e.*, ee.email as owner_email FROM emprendimientos e LEFT JOIN emprendimiento_emails ee ON e.id = ee.emprendimiento_id WHERE e.slug = ?");
    $stmt->execute([$slug]);
    $emp = $stmt->fetch();
    $paso = 'editar';
}

$successFlash = flash('success');
?>
<!DOCTYPE html>
<html lang="es">
<head>
<?php include __DIR__ . '/../includes/analytics.php'; ?>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Editar: <?= e($emp['nombre']) ?> — Mi Valdivia Online</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@400;500;600;700&family=Fraunces:opsz,wght@9..144,400;9..144,600&display=swap" rel="stylesheet">
    <style>
        :root {
            --river-600: #1976d2;
            --river-700: #1565a0;
            --river-900: #0c2d4a;
            --deep-900: #0a1628;
            --earth-100: #f0f7fc;
            --earth-200: #dce8f4;
            --earth-800: #2c3e50;
            --earth-900: #1a252f;
            --white: #ffffff;
            --red-500: #dc2626;
            --red-100: #fee2e2;
            --green-600: #16a34a;
            --yellow-500: #eab308;
            --yellow-100: #fef9c3;
            --font-display: 'Fraunces', Georgia, serif;
            --font-body: 'Outfit', system-ui, sans-serif;
        }
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body {
            font-family: var(--font-body);
            background: var(--earth-100);
            min-height: 100vh;
        }
        .header {
            background: linear-gradient(135deg, var(--river-900) 0%, var(--deep-900) 100%);
            padding: 1.5rem;
        }
        .header-inner {
            max-width: 700px;
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
        .header a.back { color: var(--earth-200); text-decoration: none; font-size: 0.9rem; }
        
        .container {
            max-width: 700px;
            margin: 0 auto;
            padding: 2rem 1.5rem 4rem;
        }
        
        .card {
            background: var(--white);
            border-radius: 16px;
            padding: 2rem;
            box-shadow: 0 4px 20px rgba(0,0,0,0.08);
        }
        
        h1 {
            font-family: var(--font-display);
            font-size: 1.5rem;
            color: var(--earth-900);
            margin-bottom: 0.5rem;
        }
        
        .subtitle {
            color: var(--earth-800);
            opacity: 0.7;
            margin-bottom: 2rem;
        }
        
        .warning-box {
            background: var(--yellow-100);
            border: 2px solid var(--yellow-500);
            border-radius: 12px;
            padding: 1.25rem;
            margin-bottom: 2rem;
            display: flex;
            gap: 1rem;
            align-items: flex-start;
        }
        
        .warning-icon {
            font-size: 1.5rem;
            flex-shrink: 0;
        }
        
        .warning-text {
            color: #854d0e;
            font-size: 0.95rem;
            line-height: 1.5;
        }
        
        .warning-text strong {
            display: block;
            margin-bottom: 0.25rem;
        }
        
        .form-group { margin-bottom: 1.25rem; }
        
        label {
            display: block;
            font-size: 0.85rem;
            font-weight: 500;
            color: var(--earth-800);
            margin-bottom: 0.4rem;
        }
        
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
        
        input:focus, textarea:focus {
            outline: none;
            border-color: var(--river-600);
        }
        
        textarea { resize: vertical; min-height: 100px; }
        
        .codigo-input {
            text-align: center;
            font-size: 1.5rem;
            font-weight: 600;
            letter-spacing: 0.3em;
            padding: 1rem;
        }
        
        .btn {
            padding: 1rem 2rem;
            background: var(--river-600);
            color: var(--white);
            border: none;
            border-radius: 8px;
            font-family: var(--font-body);
            font-size: 1rem;
            font-weight: 600;
            cursor: pointer;
            width: 100%;
        }
        
        .btn:hover { background: var(--river-700); }
        
        .btn-warning {
            background: var(--yellow-500);
            color: #422006;
        }
        
        .btn-warning:hover { background: #ca8a04; }
        
        .error {
            background: var(--red-100);
            border: 1px solid #fecaca;
            color: var(--red-500);
            padding: 1rem;
            border-radius: 8px;
            margin-bottom: 1.5rem;
        }
        
        .success {
            background: #dcfce7;
            border: 1px solid #bbf7d0;
            color: var(--green-600);
            padding: 1rem;
            border-radius: 8px;
            margin-bottom: 1.5rem;
        }
        
        .email-hint {
            background: var(--earth-100);
            border-radius: 8px;
            padding: 1rem;
            margin-bottom: 1.5rem;
            text-align: center;
            color: var(--earth-800);
        }
        
        .emp-name {
            font-weight: 600;
            color: var(--river-700);
        }
        
        .section-title {
            font-size: 1rem;
            font-weight: 600;
            color: var(--river-700);
            margin: 1.5rem 0 1rem;
            padding-bottom: 0.5rem;
            border-bottom: 2px solid var(--earth-200);
        }
        
        .form-row {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 1rem;
        }
        
        .current-img img {
            max-width: 200px;
            border-radius: 8px;
            margin-top: 0.5rem;
        }
        
        .completado-icon {
            font-size: 4rem;
            text-align: center;
            margin-bottom: 1rem;
        }
        
        .links {
            text-align: center;
            margin-top: 1.5rem;
        }
        
        .links a {
            color: var(--river-600);
            text-decoration: none;
        }
        
        @media (max-width: 640px) {
            .form-row { grid-template-columns: 1fr; }
        }
    </style>
</head>
<body>
    <header class="header">
        <div class="header-inner">
            <a href="<?= BASE_URL ?>" class="logo">🌊 Mi Valdivia Online</a>
            <a href="<?= BASE_URL ?>/ver.php?slug=<?= e($slug) ?>" class="back">← Volver</a>
        </div>
    </header>
    
    <div class="container">
        <?php if ($paso === 'error'): ?>
            <div class="card">
                <h1>No se puede editar</h1>
                <div class="error" style="margin-top: 1rem;"><?= $error ?></div>
                <p class="links"><a href="<?= BASE_URL ?>">← Volver al directorio</a></p>
            </div>
            
        <?php elseif ($paso === 'completado'): ?>
            <div class="card" style="text-align: center;">
                <div class="completado-icon">✅</div>
                <h1>¡Cambios guardados!</h1>
                <?php if ($successFlash): ?>
                    <div class="success" style="margin-top: 1rem;"><?= e($successFlash) ?></div>
                <?php endif; ?>
                <p style="color: var(--earth-800); margin-top: 1rem;">
                    Tu emprendimiento está en revisión.<br>
                    Te notificaremos cuando sea aprobado nuevamente.
                </p>
                <p class="links" style="margin-top: 2rem;">
                    <a href="<?= BASE_URL ?>">← Volver al directorio</a>
                </p>
            </div>
            
        <?php elseif ($paso === 'verificar'): ?>
            <div class="card">
                <h1>Editar: <?= e($emp['nombre']) ?></h1>
                <p class="subtitle">Verifica que eres el propietario para continuar.</p>
                
                <?php if ($error): ?>
                    <div class="error"><?= $error ?></div>
                <?php endif; ?>
                
                <?php
                // Ocultar email para mostrar
                $partes = explode('@', $ownerEmail);
                $usuario = substr($partes[0], 0, 3) . '***';
                $dominioParts = explode('.', $partes[1]);
                $dominio = substr($dominioParts[0], 0, 3) . '***.' . end($dominioParts);
                $emailMostrar = $usuario . '@' . $dominio;
                ?>
                <div class="email-hint">
                    Enviaremos un código de verificación a:<br>
                    <strong><?= e($emailMostrar) ?></strong>
                </div>
                
                <form method="POST">
                    <button type="submit" name="enviar_codigo" class="btn">Enviar código de verificación</button>
                </form>
                
                <p class="links"><a href="<?= BASE_URL ?>/ver.php?slug=<?= e($slug) ?>">← Cancelar</a></p>
            </div>
            
        <?php elseif ($paso === 'codigo'): ?>
            <div class="card">
                <h1>Ingresa el código</h1>
                <p class="subtitle">Revisa tu correo (y la carpeta spam).</p>
                
                <?php if ($error): ?>
                    <div class="error"><?= $error ?></div>
                <?php endif; ?>
                
                <form method="POST">
                    <div class="form-group">
                        <label>Código de 6 dígitos</label>
                        <input type="text" name="codigo" class="codigo-input" maxlength="6" pattern="[0-9]{6}" required autofocus autocomplete="off">
                    </div>
                    <button type="submit" name="verificar_codigo" class="btn">Verificar</button>
                </form>
                
                <p class="links"><a href="<?= BASE_URL ?>/editar-emprendimiento.php?slug=<?= e($slug) ?>">← Reenviar código</a></p>
            </div>
            
        <?php elseif ($paso === 'editar' && $canEdit): ?>
            <div class="card">
                <h1>Editar: <span class="emp-name"><?= e($emp['nombre']) ?></span></h1>
                
                <div class="warning-box">
                    <span class="warning-icon">⚠️</span>
                    <div class="warning-text">
                        <strong>Importante: Tu emprendimiento saldrá de publicación</strong>
                        Al guardar los cambios, tu emprendimiento entrará en revisión y dejará de aparecer en el directorio hasta que sea aprobado nuevamente.
                    </div>
                </div>
                
                <?php if ($error): ?>
                    <div class="error"><?= $error ?></div>
                <?php endif; ?>
                
                <form method="POST" enctype="multipart/form-data">
                    <h3 class="section-title">📋 Información básica</h3>
                    
                    <div class="form-group">
                        <label>Nombre <span class="required">*</span></label>
                        <input type="text" name="nombre" value="<?= e($emp['nombre']) ?>" required>
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
                            <label>Email público</label>
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
                                <p style="font-size: 0.85rem; color: var(--earth-800); margin-top: 0.5rem;">Imagen actual:</p>
                                <img src="<?= e(getImageUrl($emp['imagen_principal'])) ?>" alt="">
                            </div>
                        <?php endif; ?>
                    </div>
                    
                    <button type="submit" name="guardar_edicion" class="btn btn-warning" style="margin-top: 1.5rem;">
                        ⚠️ Guardar cambios y enviar a revisión
                    </button>
                </form>
                
                <p class="links"><a href="<?= BASE_URL ?>/ver.php?slug=<?= e($slug) ?>">← Cancelar</a></p>
            </div>
        <?php endif; ?>
    </div>
</body>
</html>
