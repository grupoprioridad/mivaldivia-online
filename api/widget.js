/**
 * Mi Valdivia Online - Widget Destacados
 * Carousel rotativo para sitios asociados
 * 
 * Uso: <div id="valdivia-widget"></div>
 *      <script src="https://mivaldivia.online/api/widget.js"></script>
 */

(function() {
    'use strict';
    
    const API_URL = 'https://mivaldivia.online/api/widget-destacados.php';
    const ROTATION_INTERVAL = 5000; // 5 segundos
    
    // Estilos del widget - Diseño minimalista
    const styles = `
        @import url('https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600&display=swap');
        
        .valdivia-widget {
            font-family: 'Inter', system-ui, sans-serif;
            background: #f0f7fc;
            border-radius: 20px;
            overflow: hidden;
            max-width: 340px;
            box-shadow: 0 2px 20px rgba(0, 0, 0, 0.08);
            border: 1px solid rgba(0, 0, 0, 0.04);
        }
        
        .valdivia-widget-header {
            padding: 1rem 1.2rem;
            background: #dce8f4;
            border-bottom: 1px solid rgba(0, 0, 0, 0.04);
        }
        
        .valdivia-widget-logo {
            font-size: 1.25rem;
            font-weight: 700;
            color: #1a1a1a;
            text-decoration: none;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 0.4rem;
        }
        
        .valdivia-widget-logo:hover {
            color: #1565a0;
        }
        
        .valdivia-widget-badge {
            background: #e3f2fd;
            color: #0277bd;
            padding: 0.5rem 1rem;
            text-align: center;
            font-size: 0.7rem;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.08em;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 0.4rem;
        }
        
        .valdivia-widget-carousel {
            position: relative;
        }
        
        .valdivia-widget-slide {
            display: none;
            animation: valdiviaFadeIn 0.4s ease-out;
        }
        
        .valdivia-widget-slide.active {
            display: block;
        }
        
        .valdivia-widget-slide.flip-out {
            animation: valdiviaFadeOut 0.2s ease-in forwards;
        }
        
        @keyframes valdiviaFadeIn {
            from { opacity: 0; transform: translateX(10px); }
            to { opacity: 1; transform: translateX(0); }
        }
        
        @keyframes valdiviaFadeOut {
            from { opacity: 1; transform: translateX(0); }
            to { opacity: 0; transform: translateX(-10px); }
        }
        
        /* Flechas de navegación */
        .valdivia-widget-arrows {
            position: absolute;
            top: 50%;
            left: 0;
            right: 0;
            transform: translateY(-50%);
            display: flex;
            justify-content: space-between;
            padding: 0 0.6rem;
            pointer-events: none;
            z-index: 10;
        }
        
        .valdivia-widget-arrow {
            width: 28px;
            height: 28px;
            border-radius: 50%;
            background: rgba(255, 255, 255, 0.95);
            border: 1px solid rgba(0, 0, 0, 0.08);
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 0.85rem;
            color: #666;
            pointer-events: auto;
            transition: all 0.2s ease;
            box-shadow: 0 1px 4px rgba(0, 0, 0, 0.1);
        }
        
        .valdivia-widget-arrow:hover {
            background: #fff;
            color: #1a1a1a;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.15);
        }
        
        .valdivia-widget-img {
            width: 100%;
            height: 150px;
            object-fit: cover;
            background: #f5f5f5;
        }
        
        .valdivia-widget-img-placeholder {
            width: 100%;
            height: 150px;
            display: flex;
            align-items: center;
            justify-content: center;
            background: #f5f5f5;
            font-size: 2.5rem;
        }
        
        .valdivia-widget-content {
            padding: 1rem 1.2rem;
            background: #fff;
        }
        
        .valdivia-widget-cat {
            font-size: 0.65rem;
            font-weight: 600;
            color: #0277bd;
            text-transform: uppercase;
            letter-spacing: 0.04em;
            margin-bottom: 0.4rem;
        }
        
        .valdivia-widget-title {
            font-size: 1.15rem;
            font-weight: 700;
            color: #1a1a1a;
            margin-bottom: 0.35rem;
            line-height: 1.3;
        }
        
        .valdivia-widget-title a {
            color: inherit;
            text-decoration: none;
        }
        
        .valdivia-widget-title a:hover {
            color: #1565a0;
        }
        
        .valdivia-widget-desc {
            font-size: 0.85rem;
            color: #666;
            line-height: 1.4;
            display: -webkit-box;
            -webkit-line-clamp: 2;
            -webkit-box-orient: vertical;
            overflow: hidden;
        }
        
        .valdivia-widget-dots {
            display: flex;
            justify-content: center;
            gap: 0.35rem;
            padding: 0.8rem;
            background: #fff;
            border-top: 1px solid rgba(0, 0, 0, 0.05);
        }
        
        .valdivia-widget-dot {
            width: 6px;
            height: 6px;
            border-radius: 50%;
            background: #e0e0e0;
            border: none;
            cursor: pointer;
            transition: all 0.2s;
            padding: 0;
        }
        
        .valdivia-widget-dot:hover {
            background: #bbb;
        }
        
        .valdivia-widget-dot.active {
            background: #1565a0;
            width: 16px;
            border-radius: 3px;
        }
        
        .valdivia-widget-footer {
            padding: 1rem 1.2rem;
            background: #dce8f4;
            border-top: 1px solid rgba(0, 0, 0, 0.04);
            text-align: center;
        }
        
        .valdivia-widget-btn {
            display: inline-block;
            background: #1a1a1a;
            color: #ffffff !important;
            padding: 0.65rem 1.5rem;
            border-radius: 10px;
            font-weight: 500;
            font-size: 0.85rem;
            text-decoration: none;
            transition: all 0.2s;
        }
        
        .valdivia-widget-btn:hover {
            background: #333;
            color: #ffffff !important;
            transform: translateY(-1px);
        }
        
        .valdivia-widget-empty {
            padding: 2.5rem 1.5rem;
            text-align: center;
            color: #888;
            background: #fafafa;
        }
        
        .valdivia-widget-empty-icon {
            font-size: 2rem;
            margin-bottom: 0.5rem;
        }
    `;
    
    // Crear el widget
    async function initWidget() {
        const container = document.getElementById('valdivia-widget');
        if (!container) {
            console.warn('Valdivia Widget: No se encontró el contenedor #valdivia-widget');
            return;
        }
        
        // Inyectar estilos
        const styleEl = document.createElement('style');
        styleEl.textContent = styles;
        document.head.appendChild(styleEl);
        
        try {
            const response = await fetch(API_URL);
            const data = await response.json();
            
            if (!data.success || data.emprendimientos.length === 0) {
                renderEmpty(container, data.registro_url);
                return;
            }
            
            renderWidget(container, data.emprendimientos, data.registro_url);
            
        } catch (error) {
            console.error('Valdivia Widget: Error cargando datos', error);
            renderEmpty(container, 'https://mivaldivia.online/verificar.php');
        }
    }
    
    function renderEmpty(container, registroUrl) {
        container.innerHTML = `
            <div class="valdivia-widget">
                <div class="valdivia-widget-header">
                    <a href="https://mivaldivia.online" target="_blank" class="valdivia-widget-logo">
                        🌊 Mi Valdivia Online
                    </a>
                </div>
                <div class="valdivia-widget-badge">
                    ⭐ Destacados
                </div>
                <div class="valdivia-widget-empty">
                    <div class="valdivia-widget-empty-icon">🌊</div>
                    <p>Próximamente emprendimientos destacados</p>
                </div>
                <div class="valdivia-widget-footer">
                    <a href="${registroUrl}" target="_blank" class="valdivia-widget-btn">
                        Agregar mi negocio
                    </a>
                </div>
            </div>
        `;
    }
    
    function renderWidget(container, emprendimientos, registroUrl) {
        let currentIndex = 0;
        let autoRotateTimer = null;
        let isAnimating = false;
        
        // Generar slides HTML
        const slidesHtml = emprendimientos.map((emp, i) => `
            <div class="valdivia-widget-slide ${i === 0 ? 'active' : ''}" data-index="${i}">
                ${emp.imagen_principal 
                    ? `<img class="valdivia-widget-img" src="${emp.imagen_principal}" alt="${escapeHtml(emp.nombre)}">`
                    : `<div class="valdivia-widget-img-placeholder">${emp.categoria_icono || '🌊'}</div>`
                }
                <div class="valdivia-widget-content">
                    <div class="valdivia-widget-cat">${emp.categoria_icono || ''} ${escapeHtml(emp.categoria)}</div>
                    <h4 class="valdivia-widget-title">
                        <a href="${emp.url}" target="_blank">${escapeHtml(emp.nombre)}</a>
                    </h4>
                    <p class="valdivia-widget-desc">${escapeHtml(emp.descripcion_corta || '')}</p>
                </div>
            </div>
        `).join('');
        
        // Generar dots
        const dotsHtml = emprendimientos.map((_, i) => `
            <button class="valdivia-widget-dot ${i === 0 ? 'active' : ''}" data-index="${i}"></button>
        `).join('');
        
        // Flechas (solo si hay más de 1)
        const arrowsHtml = emprendimientos.length > 1 ? `
            <div class="valdivia-widget-arrows">
                <button class="valdivia-widget-arrow valdivia-arrow-prev">‹</button>
                <button class="valdivia-widget-arrow valdivia-arrow-next">›</button>
            </div>
        ` : '';
        
        container.innerHTML = `
            <div class="valdivia-widget">
                <div class="valdivia-widget-header">
                    <a href="https://mivaldivia.online" target="_blank" class="valdivia-widget-logo">
                        🌊 Mi Valdivia Online
                    </a>
                </div>
                <div class="valdivia-widget-badge">
                    ⭐ Destacados
                </div>
                <div class="valdivia-widget-carousel">
                    ${slidesHtml}
                    ${arrowsHtml}
                </div>
                ${emprendimientos.length > 1 ? `<div class="valdivia-widget-dots">${dotsHtml}</div>` : ''}
                <div class="valdivia-widget-footer">
                    <a href="${registroUrl}" target="_blank" class="valdivia-widget-btn">
                        Agregar mi negocio
                    </a>
                </div>
            </div>
        `;
        
        // Carousel logic
        if (emprendimientos.length > 1) {
            const slides = container.querySelectorAll('.valdivia-widget-slide');
            const dots = container.querySelectorAll('.valdivia-widget-dot');
            const prevBtn = container.querySelector('.valdivia-arrow-prev');
            const nextBtn = container.querySelector('.valdivia-arrow-next');
            
            function showSlide(index) {
                if (isAnimating) return;
                
                // Loop around
                if (index < 0) index = emprendimientos.length - 1;
                if (index >= emprendimientos.length) index = 0;
                
                if (index === currentIndex) return;
                
                isAnimating = true;
                
                const currentSlide = slides[currentIndex];
                const nextSlide = slides[index];
                
                // Efecto suave
                currentSlide.classList.add('flip-out');
                
                setTimeout(() => {
                    currentSlide.classList.remove('active', 'flip-out');
                    nextSlide.classList.add('active');
                    
                    // Update dots
                    dots.forEach(d => d.classList.remove('active'));
                    dots[index].classList.add('active');
                    
                    currentIndex = index;
                    isAnimating = false;
                }, 200);
                
                // Reset auto-rotate timer
                resetAutoRotate();
            }
            
            function resetAutoRotate() {
                if (autoRotateTimer) clearInterval(autoRotateTimer);
                autoRotateTimer = setInterval(() => {
                    showSlide(currentIndex + 1);
                }, ROTATION_INTERVAL);
            }
            
            // Click en flechas
            prevBtn.addEventListener('click', () => showSlide(currentIndex - 1));
            nextBtn.addEventListener('click', () => showSlide(currentIndex + 1));
            
            // Click en dots
            dots.forEach(dot => {
                dot.addEventListener('click', () => {
                    showSlide(parseInt(dot.dataset.index));
                });
            });
            
            // Iniciar auto-rotate
            resetAutoRotate();
        }
    }
    
    function escapeHtml(text) {
        if (!text) return '';
        const div = document.createElement('div');
        div.textContent = text;
        return div.innerHTML;
    }
    
    // Iniciar cuando el DOM esté listo
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', initWidget);
    } else {
        initWidget();
    }
})();
