<?php
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/image.php';

// Requiere email verificado
requireEmailVerified();

$db = getDB();
$categorias = $db->query("SELECT * FROM categorias WHERE activo = 1 ORDER BY orden")->fetchAll();

$error = '';
$success = flash('success');
$emailVerificado = getVerifiedEmail();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $nombre = trim($_POST['nombre'] ?? '');
    $categoria_id = (int)($_POST['categoria_id'] ?? 0);
    $descripcion_corta = trim($_POST['descripcion_corta'] ?? '');
    $descripcion = trim($_POST['descripcion'] ?? '');
    $direccion = trim($_POST['direccion'] ?? '');
    $telefono = trim($_POST['telefono'] ?? '');
    $whatsapp = trim($_POST['whatsapp'] ?? '');
    $email_contacto = trim($_POST['email_contacto'] ?? '');
    $web = trim($_POST['web'] ?? '');
    $instagram = trim($_POST['instagram'] ?? '');
    $facebook = trim($_POST['facebook'] ?? '');
    $horarios = trim($_POST['horarios'] ?? '');
    $rango_precios = trim($_POST['rango_precios'] ?? '');
    
    if (!$nombre || !$categoria_id || !$descripcion_corta) {
        $error = 'Completa al menos el nombre, categoría y descripción corta.';
    } else {
        // Generar slug único
        $baseSlug = slug($nombre);
        $slugFinal = $baseSlug;
        $counter = 1;
        while (true) {
            $stmt = $db->prepare("SELECT id FROM emprendimientos WHERE slug = ?");
            $stmt->execute([$slugFinal]);
            if (!$stmt->fetch()) break;
            $slugFinal = $baseSlug . '-' . $counter++;
        }
        
        // Manejar imagen
        $imagenPrincipal = null;
        if (isset($_FILES['imagen']) && $_FILES['imagen']['error'] === UPLOAD_ERR_OK && $_FILES['imagen']['size'] > 0) {
            $uploadDir = UPLOADS_PATH . '/emprendimientos';
            $filename = $slugFinal . '-' . time();
            
            $resultado = handleImageUpload($_FILES['imagen'], $filename, $uploadDir, 1200);
            if ($resultado) {
                $imagenPrincipal = $resultado;
            }
        }
        
        $stmt = $db->prepare("
            INSERT INTO emprendimientos 
            (usuario_id, categoria_id, nombre, slug, descripcion_corta, descripcion, 
             direccion, telefono, whatsapp, email, web, instagram, facebook, 
             horarios, rango_precios, imagen_principal, estado)
            VALUES (NULL, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'pendiente')
        ");
        
        $stmt->execute([
            $categoria_id,
            $nombre,
            $slugFinal,
            $descripcion_corta,
            $descripcion,
            $direccion,
            $telefono,
            $whatsapp,
            $email_contacto ?: $emailVerificado,
            $web,
            $instagram,
            $facebook,
            $horarios,
            $rango_precios,
            $imagenPrincipal
        ]);
        
        // Guardar relación email -> emprendimiento
        $empId = $db->lastInsertId();
        $stmt = $db->prepare("INSERT INTO emprendimiento_emails (emprendimiento_id, email) VALUES (?, ?)");
        $stmt->execute([$empId, $emailVerificado]);
        
        // Limpiar sesión de verificación
        unset($_SESSION['email_verificado']);
        unset($_SESSION['verified_email']);
        
        flash('success', '¡Emprendimiento enviado! Lo revisaremos pronto y te avisaremos por correo cuando esté publicado.');
        header('Location: ' . BASE_URL . '/gracias.php');
        exit;
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
<?php include __DIR__ . "/../includes/analytics.php"; ?>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Agregar Emprendimiento — Mi Valdivia Online</title>
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
            --earth-300: #b8d4e8;
            --earth-800: #2c3e50;
            --earth-900: #1a252f;
            --white: #ffffff;
            --red-500: #dc2626;
            --green-600: #16a34a;
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
            max-width: 800px;
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
        .header a.back { color: var(--earth-200); text-decoration: none; font-size: 0.9rem; opacity: 0.9; }
        .header a.back:hover { opacity: 1; }
        
        .container {
            max-width: 800px;
            margin: 0 auto;
            padding: 2rem 1.5rem 4rem;
        }
        
        .intro {
            text-align: center;
            margin-bottom: 2rem;
        }
        
        h1 {
            font-family: var(--font-display);
            font-size: 2rem;
            color: var(--earth-900);
            margin-bottom: 0.5rem;
        }
        .subtitle {
            color: var(--earth-800);
            opacity: 0.8;
        }
        
        .verified-badge {
            display: inline-flex;
            align-items: center;
            gap: 0.4rem;
            background: #dcfce7;
            color: var(--green-600);
            padding: 0.5rem 1rem;
            border-radius: 100px;
            font-size: 0.85rem;
            font-weight: 500;
            margin-top: 1rem;
        }
        
        .card {
            background: var(--white);
            border-radius: 16px;
            padding: 2rem;
            box-shadow: 0 4px 20px rgba(0,0,0,0.08);
        }
        
        .section-title {
            font-size: 1.1rem;
            font-weight: 600;
            color: var(--river-700);
            margin-bottom: 1.25rem;
            padding-bottom: 0.75rem;
            border-bottom: 2px solid var(--earth-200);
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }
        
        .section-title span {
            font-size: 1.25rem;
        }
        
        .form-section {
            margin-bottom: 2rem;
        }
        
        .form-row {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 1rem;
            margin-bottom: 1rem;
        }
        
        .form-group {
            margin-bottom: 1rem;
        }
        
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
            transition: border-color 0.2s, box-shadow 0.2s;
            background: var(--white);
        }
        
        input:focus, select:focus, textarea:focus {
            outline: none;
            border-color: var(--river-600);
            box-shadow: 0 0 0 3px rgba(234, 88, 12, 0.1);
        }
        
        textarea { resize: vertical; min-height: 100px; }
        
        .hint {
            font-size: 0.8rem;
            color: var(--earth-800);
            opacity: 0.6;
            margin-top: 0.3rem;
        }
        
        .file-input {
            border: 2px dashed var(--earth-300);
            padding: 2rem;
            text-align: center;
            border-radius: 12px;
            cursor: pointer;
            transition: all 0.2s;
            background: var(--earth-100);
        }
        .file-input:hover { 
            border-color: var(--river-600); 
            background: var(--white);
        }
        .file-input input { display: none; }
        .file-input-label { color: var(--earth-800); }
        .file-input-label strong { color: var(--river-600); }
        .file-input-icon {
            font-size: 2.5rem;
            margin-bottom: 0.75rem;
        }
        
        .preview-img {
            max-width: 200px;
            max-height: 150px;
            border-radius: 8px;
            margin-top: 1rem;
            display: none;
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
            transition: background 0.2s, transform 0.2s;
        }
        .btn:hover { 
            background: var(--river-700); 
            transform: translateY(-1px);
        }
        
        .actions {
            display: flex;
            gap: 1rem;
            margin-top: 2rem;
            padding-top: 1.5rem;
            border-top: 2px solid var(--earth-200);
        }
        
        .error, .success {
            padding: 1rem 1.25rem;
            border-radius: 10px;
            margin-bottom: 1.5rem;
            display: flex;
            align-items: center;
            gap: 0.75rem;
        }
        .error { 
            background: #fef2f2; 
            border: 1px solid #fecaca; 
            color: var(--red-500); 
        }
        .success { 
            background: #f0fdf4; 
            border: 1px solid #bbf7d0; 
            color: var(--green-600); 
        }
        
        .media-partner-banner {
            background: linear-gradient(135deg, #0c2d4a 0%, #0a1628 100%);
            border-radius: 12px;
            padding: 1rem 1.5rem;
            margin-bottom: 0;
            margin-top: 2rem;
        }

        .media-partner-inner {
            display: flex;
            align-items: center;
            gap: 1rem;
            flex-wrap: wrap;
        }

        .media-partner-label {
            font-size: 0.65rem;
            font-weight: 700;
            letter-spacing: 0.12em;
            text-transform: uppercase;
            color: #5dade2;
            white-space: nowrap;
        }

        .media-partner-logo {
            display: inline-flex;
            align-items: center;
            background: rgba(255,255,255,0.1);
            border-radius: 6px;
            padding: 0.35rem 0.65rem;
            text-decoration: none;
            transition: background 0.2s;
        }

        .media-partner-logo:hover {
            background: rgba(255,255,255,0.18);
        }

        .media-partner-logo img {
            display: block;
        }

        .media-partner-text {
            font-size: 0.85rem;
            color: #b8d4e8;
        }

        .media-partner-text strong {
            color: #ffffff;
        }

        @media (max-width: 640px) {
            .container { padding: 1.5rem 1rem 3rem; }
            .card { padding: 1.5rem; }
            .form-row { grid-template-columns: 1fr; }
            .media-partner-inner { gap: 0.75rem; }
            .media-partner-text { font-size: 0.8rem; }
        }
    </style>
</head>
<body>
    <header class="header">
        <div class="header-inner">
            <a href="<?= BASE_URL ?>" class="logo">
                <span>🌊</span> Mi Valdivia Online
            </a>
            <a href="<?= BASE_URL ?>" class="back">← Volver al directorio</a>
        </div>
    </header>
    
    <div class="container">
        <div class="intro">
            <h1>Agregar tu Emprendimiento</h1>
            <p class="subtitle">Completa la información y lo publicaremos en el directorio.</p>
            <div class="verified-badge">
                ✓ Correo verificado: <?= e($emailVerificado) ?>
            </div>
        </div>
        
        <?php if ($error): ?>
            <div class="error">
                <span>⚠️</span>
                <?= e($error) ?>
            </div>
        <?php endif; ?>
        
        <form method="POST" enctype="multipart/form-data" class="card">
            
            <div class="form-section">
                <h3 class="section-title"><span>📋</span> Información básica</h3>
                
                <div class="form-group">
                    <label>Nombre del emprendimiento <span class="required">*</span></label>
                    <input type="text" name="nombre" value="<?= e($_POST['nombre'] ?? '') ?>" required placeholder="Ej: Café del Volcán">
                </div>
                
                <div class="form-row">
                    <div class="form-group">
                        <label>Categoría <span class="required">*</span></label>
                        <select name="categoria_id" required>
                            <option value="">Selecciona una categoría</option>
                            <?php foreach ($categorias as $cat): ?>
                                <option value="<?= $cat['id'] ?>" <?= ($_POST['categoria_id'] ?? '') == $cat['id'] ? 'selected' : '' ?>>
                                    <?= $cat['icono'] ?> <?= e($cat['nombre']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div class="form-group">
                        <label>Rango de precios</label>
                        <select name="rango_precios">
                            <option value="">No especificar</option>
                            <option value="$" <?= ($_POST['rango_precios'] ?? '') === '$' ? 'selected' : '' ?>>$ — Económico</option>
                            <option value="$$" <?= ($_POST['rango_precios'] ?? '') === '$$' ? 'selected' : '' ?>>$$ — Moderado</option>
                            <option value="$$$" <?= ($_POST['rango_precios'] ?? '') === '$$$' ? 'selected' : '' ?>>$$$ — Premium</option>
                        </select>
                    </div>
                </div>
                
                <div class="form-group">
                    <label>Descripción corta <span class="required">*</span></label>
                    <input type="text" name="descripcion_corta" value="<?= e($_POST['descripcion_corta'] ?? '') ?>" required maxlength="300" placeholder="Una línea que describa tu emprendimiento">
                    <p class="hint">Máximo 300 caracteres. Se muestra en las tarjetas del directorio.</p>
                </div>
                
                <div class="form-group">
                    <label>Descripción completa</label>
                    <textarea name="descripcion" rows="4" placeholder="Cuenta más sobre tu emprendimiento, qué ofreces, qué te hace especial..."><?= e($_POST['descripcion'] ?? '') ?></textarea>
                </div>
            </div>
            
            <div class="form-section">
                <h3 class="section-title"><span>🌊</span> Ubicación y contacto</h3>
                
                <div class="form-group">
                    <label>Dirección</label>
                    <input type="text" name="direccion" value="<?= e($_POST['direccion'] ?? '') ?>" placeholder="Ej: Av. Costanera 456, Valdivia">
                </div>
                
                <div class="form-row">
                    <div class="form-group">
                        <label>Teléfono</label>
                        <input type="tel" name="telefono" value="<?= e($_POST['telefono'] ?? '') ?>" placeholder="+56 45 123 4567">
                    </div>
                    
                    <div class="form-group">
                        <label>WhatsApp</label>
                        <input type="tel" name="whatsapp" value="<?= e($_POST['whatsapp'] ?? '') ?>" placeholder="+56 9 1234 5678">
                    </div>
                </div>
                
                <div class="form-row">
                    <div class="form-group">
                        <label>Email de contacto público</label>
                        <input type="email" name="email_contacto" value="<?= e($_POST['email_contacto'] ?? '') ?>" placeholder="contacto@miemprendimiento.cl">
                        <p class="hint">Opcional. Si lo dejas vacío, usaremos tu email verificado.</p>
                    </div>
                    
                    <div class="form-group">
                        <label>Sitio web</label>
                        <input type="url" name="web" value="<?= e($_POST['web'] ?? '') ?>" placeholder="https://miemprendimiento.cl">
                    </div>
                </div>
            </div>
            
            <div class="form-section">
                <h3 class="section-title"><span>📱</span> Redes sociales</h3>
                
                <div class="form-row">
                    <div class="form-group">
                        <label>Instagram</label>
                        <input type="text" name="instagram" value="<?= e($_POST['instagram'] ?? '') ?>" placeholder="@miemprendimiento">
                    </div>
                    
                    <div class="form-group">
                        <label>Facebook</label>
                        <input type="url" name="facebook" value="<?= e($_POST['facebook'] ?? '') ?>" placeholder="https://facebook.com/miemprendimiento">
                    </div>
                </div>
            </div>
            
            <div class="form-section">
                <h3 class="section-title"><span>🕐</span> Horarios</h3>
                
                <div class="form-group">
                    <label>Horarios de atención</label>
                    <textarea name="horarios" rows="3" placeholder="Ej: Lunes a Viernes: 9:00 - 18:00&#10;Sábados: 10:00 - 14:00&#10;Domingos: Cerrado"><?= e($_POST['horarios'] ?? '') ?></textarea>
                </div>
            </div>
            
            <div class="form-section">
                <h3 class="section-title"><span>📷</span> Imagen principal</h3>
                
                <div class="form-group">
                    <label class="file-input" id="fileInputLabel">
                        <input type="file" name="imagen" accept="image/jpeg,image/png,image/webp,image/gif" id="fileInput">
                        <div class="file-input-icon">📷</div>
                        <p class="file-input-label">
                            <strong>Haz clic para subir</strong> una imagen<br>
                            <span style="font-size: 0.85rem; opacity: 0.7;">JPG, PNG o WebP. Recomendado: 800x500 px</span>
                        </p>
                    </label>
                    <img class="preview-img" id="previewImg" alt="Vista previa">
                </div>
            </div>
            
            <div class="media-partner-banner">
                <div class="media-partner-inner">
                    <span class="media-partner-label">Media Partner</span>
                    <a href="https://www.atvvaldivia.cl" target="_blank" rel="noopener" class="media-partner-logo" title="ATV Valdivia">
                        <img src="<?= BASE_URL ?>/img/atv-valdivia-logo.svg" alt="ATV Valdivia" height="36">
                    </a>
                    <span class="media-partner-text">Tu emprendimiento puede aparecer en <strong>ATV Valdivia</strong></span>
                </div>
            </div>

            <div class="actions">
                <button type="submit" class="btn">🚀 Enviar para revisión</button>
            </div>
        </form>
    </div>
    
    <script>
        const fileInput = document.getElementById('fileInput');
        const preview = document.getElementById('previewImg');
        const label = document.getElementById('fileInputLabel');
        
        fileInput.addEventListener('change', function() {
            if (this.files && this.files[0]) {
                const reader = new FileReader();
                reader.onload = function(e) {
                    preview.src = e.target.result;
                    preview.style.display = 'block';
                }
                reader.readAsDataURL(this.files[0]);
            }
        });
    </script>
</body>
</html>
