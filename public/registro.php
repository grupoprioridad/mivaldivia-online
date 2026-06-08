<?php
require_once __DIR__ . '/../includes/config.php';

if (isLoggedIn()) {
    header('Location: ' . BASE_URL . '/mi-cuenta.php');
    exit;
}

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $nombre = trim($_POST['nombre'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $telefono = trim($_POST['telefono'] ?? '');
    $password = $_POST['password'] ?? '';
    $password2 = $_POST['password2'] ?? '';
    
    if (!$nombre || !$email || !$password) {
        $error = 'Todos los campos obligatorios deben ser completados.';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = 'El correo electrónico no es válido.';
    } elseif (strlen($password) < 6) {
        $error = 'La contraseña debe tener al menos 6 caracteres.';
    } elseif ($password !== $password2) {
        $error = 'Las contraseñas no coinciden.';
    } else {
        $db = getDB();
        
        // Verificar email único
        $stmt = $db->prepare("SELECT id FROM usuarios WHERE email = ?");
        $stmt->execute([$email]);
        if ($stmt->fetch()) {
            $error = 'Este correo ya está registrado. <a href="' . BASE_URL . '/login.php">¿Iniciar sesión?</a>';
        } else {
            // Crear usuario
            $stmt = $db->prepare("INSERT INTO usuarios (nombre, email, telefono, password, rol) VALUES (?, ?, ?, ?, 'emprendedor')");
            $stmt->execute([
                $nombre,
                $email,
                $telefono,
                password_hash($password, PASSWORD_DEFAULT)
            ]);
            
            // Auto-login
            $_SESSION['usuario_id'] = $db->lastInsertId();
            $_SESSION['usuario_nombre'] = $nombre;
            $_SESSION['rol'] = 'emprendedor';
            
            flash('success', '¡Bienvenido! Ahora puedes registrar tu emprendimiento.');
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
    <title>Registrarse — Mi Valdivia Online</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@400;500;600;700&family=Fraunces:opsz,wght@9..144,400;9..144,600&display=swap" rel="stylesheet">
    <style>
        :root {
            --river-600: #1976d2;
            --river-700: #1565a0;
            --river-900: #0c2d4a;
            --deep-900: #0a1628;
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
        .card {
            background: var(--white);
            border-radius: 16px;
            padding: 2.5rem;
            width: 100%;
            max-width: 420px;
            box-shadow: 0 20px 60px rgba(0,0,0,0.3);
        }
        .logo {
            text-align: center;
            margin-bottom: 2rem;
        }
        .logo a {
            font-family: var(--font-display);
            font-size: 1.5rem;
            color: var(--river-700);
            text-decoration: none;
        }
        .logo span { font-size: 2rem; }
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
            margin-bottom: 1.25rem;
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
        .links {
            text-align: center;
            margin-top: 1.5rem;
            font-size: 0.9rem;
            color: var(--earth-800);
        }
        .links a { color: var(--river-600); text-decoration: none; }
        .links a:hover { text-decoration: underline; }
    </style>
</head>
<body>
    <div class="card">
        <div class="logo">
            <a href="<?= BASE_URL ?>"><span>🌊</span> Mi Valdivia Online</a>
        </div>
        
        <h1>Crear cuenta</h1>
        <p class="subtitle">Registra tu emprendimiento y llega a más visitantes</p>
        
        <?php if ($error): ?>
            <div class="error"><?= $error ?></div>
        <?php endif; ?>
        
        <form method="POST">
            <div class="form-group">
                <label>Nombre completo *</label>
                <input type="text" name="nombre" value="<?= e($_POST['nombre'] ?? '') ?>" required>
            </div>
            
            <div class="form-group">
                <label>Correo electrónico *</label>
                <input type="email" name="email" value="<?= e($_POST['email'] ?? '') ?>" required>
            </div>
            
            <div class="form-group">
                <label>Teléfono / WhatsApp</label>
                <input type="tel" name="telefono" value="<?= e($_POST['telefono'] ?? '') ?>" placeholder="+56 9 1234 5678">
            </div>
            
            <div class="form-group">
                <label>Contraseña *</label>
                <input type="password" name="password" required minlength="6">
            </div>
            
            <div class="form-group">
                <label>Confirmar contraseña *</label>
                <input type="password" name="password2" required>
            </div>
            
            <button type="submit" class="btn">Crear cuenta</button>
        </form>
        
        <p class="links">
            ¿Ya tienes cuenta? <a href="<?= BASE_URL ?>/login.php">Inicia sesión</a>
        </p>
    </div>
</body>
</html>
