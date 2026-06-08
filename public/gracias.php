<?php
require_once __DIR__ . '/../includes/config.php';

$success = flash('success');
?>
<!DOCTYPE html>
<html lang="es">
<head>
<?php include __DIR__ . "/../includes/analytics.php"; ?>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>¡Enviado! — Mi Valdivia Online</title>
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
            --green-600: #16a34a;
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
            border-radius: 20px;
            padding: 3rem 2.5rem;
            width: 100%;
            max-width: 480px;
            box-shadow: 0 20px 60px rgba(0,0,0,0.3);
            text-align: center;
        }
        .icon {
            width: 80px;
            height: 80px;
            background: linear-gradient(135deg, #dcfce7 0%, #bbf7d0 100%);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 1.5rem;
            font-size: 2.5rem;
        }
        h1 {
            font-family: var(--font-display);
            font-size: 1.75rem;
            color: var(--earth-900);
            margin-bottom: 1rem;
        }
        p {
            color: var(--earth-800);
            line-height: 1.7;
            margin-bottom: 1.5rem;
        }
        .highlight {
            background: var(--earth-100);
            border-radius: 10px;
            padding: 1.25rem;
            margin: 1.5rem 0;
        }
        .highlight h3 {
            font-size: 0.9rem;
            color: var(--river-700);
            margin-bottom: 0.5rem;
        }
        .highlight p {
            margin: 0;
            font-size: 0.9rem;
            color: var(--earth-800);
        }
        .btn {
            display: inline-block;
            padding: 1rem 2rem;
            background: var(--river-600);
            color: var(--white);
            text-decoration: none;
            border-radius: 10px;
            font-weight: 600;
            transition: background 0.2s, transform 0.2s;
        }
        .btn:hover {
            background: var(--river-700);
            transform: translateY(-2px);
        }
        .secondary-link {
            display: block;
            margin-top: 1rem;
            color: var(--river-600);
            text-decoration: none;
            font-size: 0.9rem;
        }
        .secondary-link:hover {
            text-decoration: underline;
        }
    </style>
</head>
<body>
    <div class="card">
        <div class="icon">✓</div>
        
        <h1>¡Emprendimiento enviado!</h1>
        
        <p>Gracias por agregar tu emprendimiento a Mi Valdivia Online. Lo revisaremos pronto.</p>
        
        <div class="highlight">
            <h3>¿Qué sigue?</h3>
            <p>Revisaremos tu información y te enviaremos un correo cuando esté publicado en el directorio.</p>
        </div>
        
        <a href="<?= BASE_URL ?>" class="btn">Volver al directorio</a>
        
        <a href="<?= BASE_URL ?>/verificar.php" class="secondary-link">Agregar otro emprendimiento</a>
    </div>
</body>
</html>
