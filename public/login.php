<?php
require_once __DIR__ . '/../includes/config.php';

if (isLoggedIn()) {
    header('Location: ' . BASE_URL . '/mi-cuenta.php');
    exit;
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    
    if (!$email || !$password) {
        $error = 'Ingresa tu correo y contraseña.';
    } else {
        $db = getDB();
        $stmt = $db->prepare("SELECT * FROM usuarios WHERE email = ? AND activo = 1");
        $stmt->execute([$email]);
        $usuario = $stmt->fetch();
        
        if ($usuario && password_verify($password, $usuario['password'])) {
            $_SESSION['usuario_id'] = $usuario['id'];
            $_SESSION['usuario_nombre'] = $usuario['nombre'];
            $_SESSION['usuario_email'] = $usuario['email'];
            $_SESSION['rol'] = $usuario['rol'];
            
            if ($usuario['rol'] === 'admin') {
                header('Location: ' . BASE_URL . '/admin/');
            } else {
                header('Location: ' . BASE_URL . '/mi-cuenta.php');
            }
            exit;
        } else {
            $error = 'Correo o contraseña incorrectos.';
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
    <title>Iniciar sesión — Mi Valdivia Online</title>
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
            max-width: 400px;
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
            margin-bottom: 2rem;
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
        .links {
            text-align: center;
            margin-top: 1.5rem;
            font-size: 0.9rem;
            color: var(--earth-800);
        }
        .links a { color: var(--river-600); text-decoration: none; }
    </style>
</head>
<body>
    <div class="card">
        <div class="logo">
            <a href="<?= BASE_URL ?>"><span>🌊</span> Mi Valdivia Online</a>
        </div>
        
        <h1>Iniciar sesión</h1>
        
        <?php if ($error): ?>
            <div class="error"><?= e($error) ?></div>
        <?php endif; ?>
        
        <form method="POST">
            <div class="form-group">
                <label>Correo electrónico</label>
                <input type="email" name="email" value="<?= e($_POST['email'] ?? '') ?>" required autofocus>
            </div>
            
            <div class="form-group">
                <label>Contraseña</label>
                <input type="password" name="password" required>
            </div>
            
            <button type="submit" class="btn">Ingresar</button>
        </form>
        
        <p class="links">
            ¿No tienes cuenta? <a href="<?= BASE_URL ?>/registro.php">Regístrate aquí</a>
        </p>
    </div>
</body>
</html>
