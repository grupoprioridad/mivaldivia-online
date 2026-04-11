<?php
require_once __DIR__ . '/../includes/config.php';
?>
<!DOCTYPE html>
<html lang="es">
<head>
<?php include __DIR__ . "/../includes/analytics.php"; ?>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Destaca tu Emprendimiento — Mi Valdivia Online</title>
    <meta name="description" content="Aumenta la visibilidad de tu negocio en Valdivia. Planes desde $29.000/año.">
    
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@400;500;600;700&family=Fraunces:opsz,wght@9..144,400;9..144,600;9..144,700&display=swap" rel="stylesheet">
    
    <style>
        :root {
            --river-600: #1976d2;
            --river-700: #1565a0;
            --river-900: #0c2d4a;
            --deep-900: #0a1628;
            --accent-500: #039be5;
            --accent-400: #4fc3f7;
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
        
        /* Header */
        .header {
            background: linear-gradient(135deg, var(--river-900) 0%, var(--deep-900) 100%);
            padding: 1.5rem;
            text-align: center;
        }
        
        .header a {
            font-family: var(--font-display);
            font-size: 1.5rem;
            color: var(--white);
            text-decoration: none;
        }
        
        /* Hero */
        .hero {
            background: linear-gradient(135deg, var(--river-900) 0%, var(--deep-900) 100%);
            padding: 3rem 1.5rem 4rem;
            text-align: center;
            color: var(--white);
        }
        
        .hero h1 {
            font-family: var(--font-display);
            font-size: clamp(2rem, 5vw, 3rem);
            font-weight: 700;
            margin-bottom: 1rem;
        }
        
        .hero h1 span {
            color: var(--accent-400);
        }
        
        .hero p {
            font-size: 1.1rem;
            opacity: 0.9;
            max-width: 600px;
            margin: 0 auto;
        }
        
        /* Aviso gratis */
        .free-notice {
            background: linear-gradient(135deg, var(--green-600) 0%, #15803d 100%);
            padding: 1rem 1.5rem;
            text-align: center;
            color: var(--white);
        }
        
        .free-notice p {
            font-size: 0.95rem;
            max-width: 700px;
            margin: 0 auto;
        }
        
        .free-notice strong {
            display: block;
            font-size: 1.1rem;
            margin-bottom: 0.25rem;
        }
        
        /* Planes */
        .planes-section {
            max-width: 1100px;
            margin: 0 auto;
            padding: 3rem 1.5rem;
        }
        
        .planes-section h2 {
            font-family: var(--font-display);
            font-size: 2rem;
            text-align: center;
            margin-bottom: 0.5rem;
            color: var(--earth-900);
        }
        
        .planes-section > p {
            text-align: center;
            color: var(--earth-800);
            margin-bottom: 2.5rem;
        }
        
        .planes-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 1.5rem;
        }
        
        .plan-card {
            background: var(--white);
            border-radius: 16px;
            overflow: hidden;
            box-shadow: 0 4px 20px rgba(0,0,0,0.08);
            display: flex;
            flex-direction: column;
            transition: transform 0.2s, box-shadow 0.2s;
        }
        
        .plan-card:hover {
            transform: translateY(-4px);
            box-shadow: 0 8px 30px rgba(0,0,0,0.12);
        }
        
        .plan-card.featured {
            border: 3px solid var(--river-600);
            position: relative;
        }
        
        .plan-card.featured::before {
            content: '⭐ MÁS POPULAR';
            position: absolute;
            top: 0;
            left: 50%;
            transform: translateX(-50%) translateY(-50%);
            background: var(--river-600);
            color: white;
            padding: 0.4rem 1rem;
            border-radius: 100px;
            font-size: 0.75rem;
            font-weight: 700;
        }
        
        .plan-header {
            padding: 2rem 1.5rem 1.5rem;
            text-align: center;
            border-bottom: 1px solid var(--earth-200);
        }
        
        .plan-card.featured .plan-header {
            padding-top: 2.5rem;
        }
        
        .plan-name {
            font-family: var(--font-display);
            font-size: 1.5rem;
            font-weight: 600;
            color: var(--earth-900);
            margin-bottom: 0.5rem;
        }
        
        .plan-price {
            font-size: 2.5rem;
            font-weight: 700;
            color: var(--river-600);
        }
        
        .plan-price span {
            font-size: 1rem;
            font-weight: 400;
            color: var(--earth-800);
        }
        
        .plan-features {
            padding: 1.5rem;
            flex: 1;
        }
        
        .plan-features ul {
            list-style: none;
        }
        
        .plan-features li {
            padding: 0.6rem 0;
            border-bottom: 1px solid var(--earth-100);
            display: flex;
            align-items: flex-start;
            gap: 0.75rem;
            font-size: 0.95rem;
            color: var(--earth-800);
        }
        
        .plan-features li:last-child {
            border-bottom: none;
        }
        
        .plan-features li::before {
            content: '✓';
            color: var(--green-600);
            font-weight: bold;
            flex-shrink: 0;
        }
        
        .plan-features li.highlight {
            color: var(--earth-900);
            font-weight: 500;
        }
        
        .plan-footer {
            padding: 1.5rem;
            border-top: 1px solid var(--earth-200);
        }
        
        .plan-btn {
            display: block;
            width: 100%;
            padding: 1rem;
            background: var(--river-600);
            color: var(--white);
            text-align: center;
            text-decoration: none;
            border-radius: 10px;
            font-weight: 600;
            font-size: 1rem;
            transition: background 0.2s;
        }
        
        .plan-btn:hover {
            background: var(--river-700);
        }
        
        .plan-card.featured .plan-btn {
            background: var(--accent-500);
        }
        
        .plan-card.featured .plan-btn:hover {
            background: var(--accent-400);
        }
        
        /* Beneficios */
        .benefits-section {
            background: var(--white);
            padding: 3rem 1.5rem;
        }
        
        .benefits-section h2 {
            font-family: var(--font-display);
            font-size: 1.75rem;
            text-align: center;
            margin-bottom: 2rem;
            color: var(--earth-900);
        }
        
        .benefits-grid {
            max-width: 900px;
            margin: 0 auto;
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 2rem;
        }
        
        .benefit-item {
            text-align: center;
            padding: 1.5rem;
        }
        
        .benefit-icon {
            font-size: 2.5rem;
            margin-bottom: 1rem;
        }
        
        .benefit-item h3 {
            font-size: 1.1rem;
            color: var(--earth-900);
            margin-bottom: 0.5rem;
        }
        
        .benefit-item p {
            font-size: 0.9rem;
            color: var(--earth-800);
            opacity: 0.8;
        }
        
        /* FAQ */
        .faq-section {
            max-width: 800px;
            margin: 0 auto;
            padding: 3rem 1.5rem;
        }
        
        .faq-section h2 {
            font-family: var(--font-display);
            font-size: 1.75rem;
            text-align: center;
            margin-bottom: 2rem;
            color: var(--earth-900);
        }
        
        .faq-item {
            background: var(--white);
            border-radius: 12px;
            padding: 1.5rem;
            margin-bottom: 1rem;
            box-shadow: 0 2px 8px rgba(0,0,0,0.05);
        }
        
        .faq-item h3 {
            font-size: 1rem;
            color: var(--earth-900);
            margin-bottom: 0.5rem;
        }
        
        .faq-item p {
            font-size: 0.95rem;
            color: var(--earth-800);
            opacity: 0.85;
        }
        
        /* Footer */
        .footer {
            background: var(--river-900);
            color: var(--white);
            padding: 2rem 1.5rem;
            text-align: center;
        }
        
        .footer a {
            color: var(--accent-400);
            text-decoration: none;
        }
        
        .back-link {
            display: inline-block;
            margin-top: 1.5rem;
            color: var(--earth-800);
            text-decoration: none;
            font-size: 0.95rem;
        }
        
        .back-link:hover {
            color: var(--river-600);
        }
    </style>
</head>
<body>
    <header class="header">
        <a href="<?= BASE_URL ?>">🌊 Mi Valdivia Online</a>
    </header>
    
    <section class="hero">
        <h1>Destaca tu <span>Emprendimiento</span></h1>
        <p>Aumenta la visibilidad de tu negocio y llega a más turistas y locales en Valdivia</p>
    </section>
    
    <div class="free-notice">
        <p>
            <strong>💚 Tu emprendimiento siempre estará visible gratis</strong>
            Destacar es completamente opcional. Solo te ofrece mayor visibilidad y beneficios adicionales.
        </p>
    </div>
    
    <section class="planes-section">
        <h2>Elige tu Plan</h2>
        <p>Todos los planes tienen duración de 1 año</p>
        
        <div class="planes-grid">
            <!-- Plan Básico -->
            <div class="plan-card">
                <div class="plan-header">
                    <h3 class="plan-name">Básico</h3>
                    <div class="plan-price">$29.000 <span>/año</span></div>
                </div>
                <div class="plan-features">
                    <ul>
                        <li class="highlight">Etiqueta ⭐ Destacado en tu perfil</li>
                        <li>Apareces primero en las búsquedas</li>
                        <li>Rotación en widget de Mi Valdivia</li>
                    </ul>
                </div>
                <div class="plan-footer">
                    <a href="https://mpago.la/2Mj6wyi" class="plan-btn">Elegir Plan Básico</a>
                </div>
            </div>
            
            <!-- Plan Plus -->
            <div class="plan-card featured">
                <div class="plan-header">
                    <h3 class="plan-name">Plus</h3>
                    <div class="plan-price">$99.000 <span>/año</span></div>
                </div>
                <div class="plan-features">
                    <ul>
                        <li>Etiqueta ⭐ Destacado en tu perfil</li>
                        <li>Apareces primero en las búsquedas</li>
                        <li>Rotación en widget de Mi Valdivia</li>
                        <li class="highlight">📰 Publirreportaje en Mi Valdivia</li>
                    </ul>
                </div>
                <div class="plan-footer">
                    <a href="#" class="plan-btn">Elegir Plan Plus</a>
                </div>
            </div>
            
            <!-- Plan Premium -->
            <div class="plan-card">
                <div class="plan-header">
                    <h3 class="plan-name">Premium</h3>
                    <div class="plan-price">$149.000 <span>/año</span></div>
                </div>
                <div class="plan-features">
                    <ul>
                        <li>Etiqueta ⭐ Destacado en tu perfil</li>
                        <li>Apareces primero en las búsquedas</li>
                        <li>Rotación en widget de Mi Valdivia</li>
                        <li>📰 Publirreportaje en Mi Valdivia</li>
                        <li class="highlight">📱 Publicación en redes sociales de Mi Valdivia</li>
                    </ul>
                </div>
                <div class="plan-footer">
                    <a href="#" class="plan-btn">Elegir Plan Premium</a>
                </div>
            </div>
        </div>
        
        <p style="text-align: center; margin-top: 2rem;">
            <a href="<?= BASE_URL ?>" class="back-link">← Volver al directorio</a>
        </p>
    </section>
    
    <section class="benefits-section">
        <h2>¿Por qué destacar?</h2>
        <div class="benefits-grid">
            <div class="benefit-item">
                <div class="benefit-icon">🔝</div>
                <h3>Primero en búsquedas</h3>
                <p>Tu emprendimiento aparece antes que los demás cuando alguien busca en Mi Valdivia Online</p>
            </div>
            <div class="benefit-item">
                <div class="benefit-icon">📰</div>
                <h3>Presencia en Mi Valdivia</h3>
                <p>Miles de personas ven Mi Valdivia cada día. Tu negocio estará ahí</p>
            </div>
            <div class="benefit-item">
                <div class="benefit-icon">⭐</div>
                <h3>Etiqueta de confianza</h3>
                <p>Los visitantes identifican tu negocio como destacado y confiable</p>
            </div>
        </div>
    </section>
    
    <section class="faq-section">
        <h2>Preguntas frecuentes</h2>
        
        <div class="faq-item">
            <h3>¿Es obligatorio pagar para estar en Mi Valdivia Online?</h3>
            <p>No. Tu emprendimiento siempre estará visible de forma gratuita. Destacar es opcional y solo te ofrece beneficios adicionales de visibilidad.</p>
        </div>
        
        <div class="faq-item">
            <h3>¿Cuánto dura el destacado?</h3>
            <p>Todos los planes tienen una duración de 1 año desde el momento del pago.</p>
        </div>
        
        <div class="faq-item">
            <h3>¿Qué es el publirreportaje?</h3>
            <p>Es un artículo dedicado exclusivamente a tu emprendimiento, publicado en Mi Valdivia. Incluye descripción, fotos y todo lo que quieras contar sobre tu negocio.</p>
        </div>
        
        <div class="faq-item">
            <h3>¿Cómo pago?</h3>
            <p>Aceptamos pagos seguros con Mercado Pago. Puedes pagar con tarjeta de crédito, débito o transferencia.</p>
        </div>
        
        <div class="faq-item">
            <h3>¿Puedo cambiar de plan después?</h3>
            <p>Sí, puedes actualizar tu plan en cualquier momento. Solo pagas la diferencia.</p>
        </div>
    </section>
    
    <footer class="footer">
        <p>🌊 Mi Valdivia Online — Una iniciativa de <a href="https://mivaldivia.online">Mi Valdivia</a></p>
        <p style="margin-top: 0.5rem; opacity: 0.7;">© <?= date('Y') ?> — Todos los derechos reservados</p>
    </footer>
</body>
</html>
