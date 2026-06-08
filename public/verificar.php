<?php
require_once __DIR__ . '/../includes/config.php';

$db = getDB();
$paso = $_GET['paso'] ?? 'email';
$error = '';
$success = '';

// Si ya está verificado, redirigir
if (isEmailVerified() && $paso === 'email') {
    header('Location: ' . BASE_URL . '/nuevo-emprendimiento.php');
    exit;
}

// Limpiar códigos expirados (housekeeping)
$db->exec("DELETE FROM verificacion_codigos WHERE expira_at < NOW() OR usado = 1");

// Reenviar código (mismo email)
if (isset($_GET['reenviar']) && isset($_SESSION['pending_email'])) {
    $email = $_SESSION['pending_email'];
    $codigo = generarCodigo();
    
    // Invalidar códigos anteriores
    $stmt = $db->prepare("UPDATE verificacion_codigos SET usado = 1 WHERE email = ?");
    $stmt->execute([$email]);
    
    // Guardar nuevo código en BD
    $stmt = $db->prepare("INSERT INTO verificacion_codigos (email, codigo, expira_at) VALUES (?, ?, DATE_ADD(NOW(), INTERVAL 10 MINUTE))");
    $stmt->execute([$email, $codigo]);
    
    $htmlBody = '
    <!DOCTYPE html>
    <html>
    <head>
<?php include __DIR__ . "/../includes/analytics.php"; ?><meta charset="UTF-8"></head>
    <body style="font-family: -apple-system, BlinkMacSystemFont, \'Segoe UI\', Roboto, sans-serif; background: #f0f7fc; padding: 40px 20px;">
        <div style="max-width: 400px; margin: 0 auto; background: white; border-radius: 12px; padding: 40px; box-shadow: 0 4px 20px rgba(0,0,0,0.1);">
            <div style="text-align: center; margin-bottom: 30px;">
                <span style="font-size: 2.5rem;">🌊</span>
                <h1 style="margin: 10px 0 0; color: #0c2d4a; font-size: 1.5rem;">Mi Valdivia Online</h1>
            </div>
            <p style="color: #2c3e50; margin-bottom: 20px;">Tu nuevo código de verificación es:</p>
            <div style="background: #dce8f4; border-radius: 8px; padding: 20px; text-align: center; margin-bottom: 20px;">
                <span style="font-size: 2rem; font-weight: bold; letter-spacing: 0.3em; color: #0c2d4a;">' . $codigo . '</span>
            </div>
            <p style="color: #6b7280; font-size: 0.9rem; margin: 0;">Este código expira en 10 minutos.</p>
        </div>
    </body>
    </html>';
    
    if (enviarCorreo($email, 'Tu nuevo código - Mi Valdivia Online', $htmlBody)) {
        $success = 'Código reenviado a ' . $email;
        $paso = 'codigo';
    } else {
        $error = 'No pudimos reenviar el correo. Intenta de nuevo.';
        $paso = 'codigo';
    }
}

// Paso 1: Enviar código
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $paso === 'email') {
    $email = trim($_POST['email'] ?? '');
    
    if (!$email || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = 'Ingresa un correo electrónico válido.';
    } else {
        // Invalidar códigos anteriores
        $stmt = $db->prepare("UPDATE verificacion_codigos SET usado = 1 WHERE email = ?");
        $stmt->execute([$email]);
        
        // Generar y guardar código
        $codigo = generarCodigo();
        $stmt = $db->prepare("INSERT INTO verificacion_codigos (email, codigo, expira_at) VALUES (?, ?, DATE_ADD(NOW(), INTERVAL 10 MINUTE))");
        $stmt->execute([$email, $codigo]);
        
        $_SESSION['pending_email'] = $email;
        
        // Enviar correo
        $htmlBody = '
        <!DOCTYPE html>
        <html>
        <head>
<?php include __DIR__ . "/../includes/analytics.php"; ?>
            <meta charset="UTF-8">
        </head>
        <body style="font-family: -apple-system, BlinkMacSystemFont, \'Segoe UI\', Roboto, sans-serif; background: #f0f7fc; padding: 40px 20px;">
            <div style="max-width: 400px; margin: 0 auto; background: white; border-radius: 12px; padding: 40px; box-shadow: 0 4px 20px rgba(0,0,0,0.1);">
                <div style="text-align: center; margin-bottom: 30px;">
                    <span style="font-size: 2.5rem;">🌊</span>
                    <h1 style="margin: 10px 0 0; color: #0c2d4a; font-size: 1.5rem;">Mi Valdivia Online</h1>
                </div>
                
                <p style="color: #2c3e50; margin-bottom: 20px;">Tu código de verificación es:</p>
                
                <div style="background: #dce8f4; border-radius: 8px; padding: 20px; text-align: center; margin-bottom: 20px;">
                    <span style="font-size: 2rem; font-weight: bold; letter-spacing: 0.3em; color: #0c2d4a;">' . $codigo . '</span>
                </div>
                
                <p style="color: #6b7280; font-size: 0.9rem; margin: 0;">Este código expira en 10 minutos.</p>
            </div>
            
            <p style="text-align: center; color: #9ca3af; font-size: 0.8rem; margin-top: 30px;">
                Si no solicitaste este código, puedes ignorar este mensaje.
            </p>
        </body>
        </html>';
        
        if (enviarCorreo($email, 'Tu código de verificación - Mi Valdivia Online', $htmlBody)) {
            header('Location: ' . BASE_URL . '/verificar.php?paso=codigo');
            exit;
        } else {
            $error = 'No pudimos enviar el correo. Intenta de nuevo.';
        }
    }
}

// Paso 2: Verificar código
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $paso === 'codigo') {
    $codigoIngresado = preg_replace('/\s+/', '', $_POST['codigo'] ?? '');
    $email = $_SESSION['pending_email'] ?? '';
    
    if (!$email) {
        $error = 'Sesión expirada. <a href="' . BASE_URL . '/verificar.php">Vuelve a ingresar tu correo</a>.';
    } else {
        // Buscar código válido en BD
        $stmt = $db->prepare("
            SELECT * FROM verificacion_codigos 
            WHERE email = ? AND usado = 0 AND expira_at > NOW()
            ORDER BY created_at DESC 
            LIMIT 1
        ");
        $stmt->execute([$email]);
        $registro = $stmt->fetch();
        
        if (!$registro) {
            $error = 'El código ha expirado. <a href="' . BASE_URL . '/verificar.php?reenviar=1">Solicita uno nuevo</a>.';
        } elseif ($registro['intentos'] >= 5) {
            $error = 'Demasiados intentos. <a href="' . BASE_URL . '/verificar.php?reenviar=1">Solicita un nuevo código</a>.';
        } elseif ($codigoIngresado != $registro['codigo']) {
            // Incrementar intentos
            $db->prepare("UPDATE verificacion_codigos SET intentos = intentos + 1 WHERE id = ?")->execute([$registro['id']]);
            $intentosRestantes = 5 - ($registro['intentos'] + 1);
            $error = 'Código incorrecto. Te quedan ' . $intentosRestantes . ' intentos.';
        } else {
            // ¡Éxito!
            $db->prepare("UPDATE verificacion_codigos SET usado = 1 WHERE id = ?")->execute([$registro['id']]);
            
            $_SESSION['email_verificado'] = true;
            $_SESSION['verified_email'] = $email;
            unset($_SESSION['pending_email']);
            
            flash('success', '¡Correo verificado! Ahora puedes agregar tu emprendimiento.');
            header('Location: ' . BASE_URL . '/nuevo-emprendimiento.php');
            exit;
        }
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
<?php include __DIR__ . "/../includes/analytics.php"; ?>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Verificar correo — Mi Valdivia Online</title>
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
            --red-500: #dc2626;
            --font-display: 'Fraunces', Georgia, serif;
            --font-body: 'Outfit', system-ui, sans-serif;
        }
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body {
            font-family: var(--font-body);
            background: linear-gradient(135deg, var(--river-900) 0%, var(--deep-900) 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 2rem 1rem;
        }
        .container {
            width: 100%;
            max-width: 850px;
            display: flex;
            flex-direction: row;
            gap: 2.5rem;
            align-items: center;
        }
        .info-column {
            flex: 1;
            max-width: 380px;
        }
        .form-column {
            flex: 1;
            max-width: 420px;
        }
        
        @media (max-width: 768px) {
            .container {
                flex-direction: column;
                max-width: 420px;
                gap: 1.5rem;
            }
            .info-column, .form-column {
                max-width: 100%;
                width: 100%;
            }
        }
        
        /* Hero info section */
        .hero-info {
            text-align: left;
            margin-bottom: 1.5rem;
            color: var(--white);
        }
        @media (max-width: 768px) {
            .hero-info {
                text-align: center;
            }
        }
        .badge-gratis {
            display: inline-block;
            background: var(--earth-100);
            color: var(--earth-900);
            padding: 0.4rem 1rem;
            border-radius: 20px;
            font-size: 0.75rem;
            font-weight: 700;
            margin-bottom: 1rem;
        }
        .hero-info h2 {
            font-family: var(--font-display);
            font-size: 1.8rem;
            font-weight: 600;
            margin-bottom: 0.3rem;
        }
        .hero-info h2 span {
            color: var(--accent-500);
        }
        .hero-info p {
            opacity: 0.9;
            font-size: 0.95rem;
        }
        
        /* Process steps */
        .process-steps {
            background: rgba(255,255,255,0.1);
            border-radius: 12px;
            padding: 1rem;
            margin-bottom: 1.5rem;
        }
        .process-step {
            display: flex;
            align-items: flex-start;
            gap: 0.75rem;
            padding: 0.6rem 0;
        }
        .process-step:not(:last-child) {
            border-bottom: 1px solid rgba(255,255,255,0.1);
        }
        .process-num {
            background: var(--river-600);
            color: white;
            width: 28px;
            height: 28px;
            border-radius: 8px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: 700;
            font-size: 0.85rem;
            flex-shrink: 0;
        }
        .process-step.current .process-num {
            background: var(--accent-500);
        }
        .process-text {
            color: var(--white);
        }
        .process-text strong {
            display: block;
            font-size: 0.95rem;
            margin-bottom: 0.1rem;
        }
        .process-text small {
            opacity: 0.75;
            font-size: 0.8rem;
        }
        
        .time-info {
            text-align: left;
            color: var(--white);
            font-size: 0.85rem;
            opacity: 0.8;
            margin-bottom: 0;
        }
        @media (max-width: 768px) {
            .time-info {
                text-align: center;
                margin-bottom: 1.5rem;
            }
        }
        
        .card {
            background: var(--white);
            border-radius: 16px;
            padding: 2rem;
            width: 100%;
            box-shadow: 0 20px 60px rgba(0,0,0,0.3);
        }
        .logo {
            text-align: center;
            margin-bottom: 1.5rem;
        }
        .logo a {
            font-family: var(--font-display);
            font-size: 1.3rem;
            color: var(--river-700);
            text-decoration: none;
        }
        .logo span { font-size: 1.8rem; }
        
        .steps {
            display: flex;
            justify-content: center;
            gap: 0.5rem;
            margin-bottom: 1.5rem;
        }
        .step {
            width: 36px;
            height: 36px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: 600;
            font-size: 0.85rem;
        }
        .step.active {
            background: var(--river-600);
            color: white;
        }
        .step.done {
            background: #16a34a;
            color: white;
        }
        .step.pending {
            background: var(--earth-200);
            color: var(--earth-800);
        }
        .step-line {
            width: 30px;
            height: 2px;
            background: var(--earth-200);
            align-self: center;
        }
        
        h1 {
            font-family: var(--font-display);
            font-size: 1.5rem;
            text-align: center;
            color: var(--earth-900);
            margin-bottom: 0.5rem;
        }
        .subtitle {
            text-align: center;
            color: var(--earth-800);
            opacity: 0.7;
            margin-bottom: 2rem;
            font-size: 0.95rem;
        }
        .form-group {
            margin-bottom: 1.5rem;
        }
        label {
            display: block;
            font-size: 0.85rem;
            font-weight: 500;
            color: var(--earth-800);
            margin-bottom: 0.4rem;
        }
        input {
            width: 100%;
            padding: 0.875rem 1rem;
            border: 1px solid var(--earth-200);
            border-radius: 8px;
            font-family: var(--font-body);
            font-size: 1rem;
            color: var(--earth-900);
            transition: border-color 0.2s;
        }
        input:focus {
            outline: none;
            border-color: var(--river-600);
        }
        input.codigo-input {
            text-align: center;
            font-size: 1.5rem;
            font-weight: 600;
            letter-spacing: 0.3em;
            padding: 1rem;
        }
        .btn {
            width: 100%;
            padding: 1rem;
            background: var(--river-600);
            color: var(--white);
            border: none;
            border-radius: 8px;
            font-family: var(--font-body);
            font-size: 1rem;
            font-weight: 600;
            cursor: pointer;
            transition: background 0.2s;
        }
        .btn:hover { background: var(--river-700); }
        .error {
            background: #fef2f2;
            border: 1px solid #fecaca;
            color: var(--red-500);
            padding: 0.75rem 1rem;
            border-radius: 8px;
            margin-bottom: 1.5rem;
            font-size: 0.9rem;
        }
        .error a { color: var(--river-600); }
        .success {
            background: #f0fdf4;
            border: 1px solid #bbf7d0;
            color: #16a34a;
            padding: 0.75rem 1rem;
            border-radius: 8px;
            margin-bottom: 1.5rem;
            font-size: 0.9rem;
            text-align: center;
        }
        .links {
            text-align: center;
            margin-top: 1.5rem;
            font-size: 0.9rem;
            color: var(--earth-800);
        }
        .links a { color: var(--river-600); text-decoration: none; }
        .links a:hover { text-decoration: underline; }
        
        .email-sent {
            background: var(--earth-100);
            border-radius: 8px;
            padding: 1rem;
            margin-bottom: 1.5rem;
            text-align: center;
        }
        .email-sent strong {
            color: var(--river-700);
            display: block;
            margin-top: 0.25rem;
        }
        
        .resend {
            text-align: center;
            margin-top: 1rem;
            font-size: 0.85rem;
        }
        .resend a {
            color: var(--river-600);
            text-decoration: none;
        }

        .media-partner-block {
            display: flex;
            align-items: center;
            gap: 0.75rem;
            margin-top: 1.5rem;
            padding-top: 1.25rem;
            border-top: 1px solid rgba(255,255,255,0.12);
        }
        @media (max-width: 768px) {
            .media-partner-block {
                justify-content: center;
            }
        }
        .media-partner-label {
            font-size: 0.65rem;
            font-weight: 700;
            letter-spacing: 0.12em;
            text-transform: uppercase;
            color: var(--deep-400);
            opacity: 0.7;
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
    </style>
</head>
<body>
    <div class="container">
        <!-- Info column -->
        <div class="info-column">
            <div class="hero-info">
                <span class="badge-gratis">🎉 100% GRATIS</span>
                <h2>Sube tu negocio a<br><span>Mivaldivia.online</span></h2>
                <p>Hazte visible para miles de personas<br>en Valdivia y el mundo</p>
            </div>
            
            <div class="process-steps">
                <div class="process-step <?= $paso === 'email' || $paso === 'codigo' ? 'current' : '' ?>">
                    <span class="process-num">1</span>
                    <div class="process-text">
                        <strong>Ingresa tu email</strong>
                        <small>Te enviamos un código de verificación</small>
                    </div>
                </div>
                <div class="process-step">
                    <span class="process-num">2</span>
                    <div class="process-text">
                        <strong>Completa tu perfil</strong>
                        <small>Nombre, descripción, fotos y contacto</small>
                    </div>
                </div>
                <div class="process-step">
                    <span class="process-num">3</span>
                    <div class="process-text">
                        <strong>¡Listo!</strong>
                        <small>Tu negocio visible en el directorio</small>
                    </div>
                </div>
            </div>
            
            <p class="time-info">Sin costo · <strong>Toma 2 minutos</strong></p>

            <div class="media-partner-block">
                <span class="media-partner-label">Media Partner</span>
                <a href="https://www.atvvaldivia.cl" target="_blank" rel="noopener" class="media-partner-logo" title="ATV Valdivia">
                    <img src="<?= BASE_URL ?>/img/atv-valdivia-logo.svg" alt="ATV Valdivia" height="34">
                </a>
            </div>
        </div>
        
        <!-- Form column -->
        <div class="form-column">
        <div class="card">
            <div class="logo">
                <a href="<?= BASE_URL ?>"><span>🌊</span> Mi Valdivia Online</a>
            </div>
            
            <div class="steps">
                <div class="step <?= $paso === 'email' ? 'active' : 'done' ?>">1</div>
                <div class="step-line"></div>
                <div class="step <?= $paso === 'codigo' ? 'active' : 'pending' ?>">2</div>
            </div>
            
            <?php if ($paso === 'email'): ?>
            <h1>Verificar tu correo</h1>
            <p class="subtitle">Te enviaremos un código de 6 dígitos para verificar que el correo es tuyo.</p>
            
            <?php if ($error): ?>
                <div class="error"><?= $error ?></div>
            <?php endif; ?>
            
            <form method="POST">
                <div class="form-group">
                    <label>Correo electrónico</label>
                    <input type="email" name="email" placeholder="tu@correo.com" required autofocus>
                </div>
                
                <button type="submit" class="btn">Enviar código</button>
            </form>
            
            <p class="links">
                <a href="<?= BASE_URL ?>">← Volver al directorio</a>
            </p>
            
        <?php elseif ($paso === 'codigo'): ?>
            <h1>Ingresa el código</h1>
            <p class="subtitle">Revisa tu bandeja de entrada (y spam).</p>
            
            <div class="email-sent">
                Código enviado a:
                <strong><?= e($_SESSION['pending_email'] ?? '') ?></strong>
            </div>
            
            <?php if ($success): ?>
                <div class="success">✓ <?= e($success) ?></div>
            <?php endif; ?>
            
            <?php if ($error): ?>
                <div class="error"><?= $error ?></div>
            <?php endif; ?>
            
            <form method="POST">
                <div class="form-group">
                    <label>Código de verificación</label>
                    <input type="text" name="codigo" class="codigo-input" placeholder="000000" maxlength="6" pattern="[0-9]{6}" required autofocus autocomplete="off">
                </div>
                
                <button type="submit" class="btn">Verificar</button>
            </form>
            
            <p class="resend">
                ¿No llegó? <a href="<?= BASE_URL ?>/verificar.php?reenviar=1">Reenviar código</a>
            </p>
            
            <p class="links">
                <a href="<?= BASE_URL ?>/verificar.php">← Usar otro correo</a>
            </p>
        <?php endif; ?>
        </div>
        </div>
    </div>
</body>
</html>
