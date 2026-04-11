<?php
/**
 * Notificaciones por email a propietarios
 */

function notificarAprobacion($emprendimiento, $emailPropietario) {
    $nombre = $emprendimiento['nombre'];
    $slug = $emprendimiento['slug'];
    $urlVer = BASE_URL . '/ver.php?slug=' . $slug;
    
    $htmlBody = '
    <!DOCTYPE html>
    <html>
    <head><meta charset="UTF-8"></head>
    <body style="font-family: -apple-system, BlinkMacSystemFont, \'Segoe UI\', Roboto, sans-serif; background: #f0f7fc; padding: 40px 20px; margin: 0;">
        <div style="max-width: 500px; margin: 0 auto; background: white; border-radius: 16px; overflow: hidden; box-shadow: 0 4px 20px rgba(0,0,0,0.1);">
            
            <div style="background: linear-gradient(135deg, #16a34a 0%, #15803d 100%); padding: 40px; text-align: center;">
                <span style="font-size: 4rem;">✅</span>
                <h1 style="color: white; margin: 20px 0 0; font-size: 1.5rem;">¡Emprendimiento Aprobado!</h1>
            </div>
            
            <div style="padding: 40px;">
                <p style="color: #2c3e50; font-size: 1.1rem; margin: 0 0 20px;">
                    Tu emprendimiento <strong style="color: #16a34a;">' . htmlspecialchars($nombre) . '</strong> ha sido aprobado y ya está visible en Mi Valdivia Online.
                </p>
                
                <p style="color: #666; margin: 0 0 30px;">
                    Los visitantes de Valdivia ya pueden encontrar tu negocio en nuestro directorio.
                </p>
                
                <a href="' . $urlVer . '" style="display: inline-block; background: #16a34a; color: white; padding: 14px 28px; border-radius: 8px; text-decoration: none; font-weight: 600;">
                    Ver mi emprendimiento →
                </a>
            </div>
            
            <div style="background: #f5f5f5; padding: 20px 40px; text-align: center;">
                <p style="color: #888; font-size: 0.85rem; margin: 0;">
                    🌊 <a href="' . BASE_URL . '" style="color: #1976d2; text-decoration: none;">Mi Valdivia Online</a> — Directorio de emprendimientos de Valdivia
                </p>
            </div>
        </div>
    </body>
    </html>';
    
    return enviarCorreo($emailPropietario, '✅ Tu emprendimiento fue aprobado - Mi Valdivia Online', $htmlBody);
}

function notificarRechazo($emprendimiento, $emailPropietario, $razon) {
    $nombre = $emprendimiento['nombre'];
    $slug = $emprendimiento['slug'];
    $urlEditar = BASE_URL . '/editar-emprendimiento.php?slug=' . $slug;
    
    $htmlBody = '
    <!DOCTYPE html>
    <html>
    <head><meta charset="UTF-8"></head>
    <body style="font-family: -apple-system, BlinkMacSystemFont, \'Segoe UI\', Roboto, sans-serif; background: #f0f7fc; padding: 40px 20px; margin: 0;">
        <div style="max-width: 500px; margin: 0 auto; background: white; border-radius: 16px; overflow: hidden; box-shadow: 0 4px 20px rgba(0,0,0,0.1);">
            
            <div style="background: linear-gradient(135deg, #1976d2 0%, #1565a0 100%); padding: 40px; text-align: center;">
                <span style="font-size: 4rem;">📝</span>
                <h1 style="color: white; margin: 20px 0 0; font-size: 1.5rem;">Tu emprendimiento necesita ajustes</h1>
            </div>
            
            <div style="padding: 40px;">
                <p style="color: #2c3e50; font-size: 1.1rem; margin: 0 0 20px;">
                    Revisamos <strong>' . htmlspecialchars($nombre) . '</strong> y necesitamos que hagas algunos cambios antes de publicarlo.
                </p>
                
                <div style="background: #fef3c7; border-left: 4px solid #f59e0b; padding: 16px 20px; margin: 0 0 30px; border-radius: 0 8px 8px 0;">
                    <p style="color: #92400e; margin: 0; font-weight: 600; font-size: 0.9rem;">Motivo:</p>
                    <p style="color: #78350f; margin: 8px 0 0; line-height: 1.5;">' . nl2br(htmlspecialchars($razon)) . '</p>
                </div>
                
                <p style="color: #666; margin: 0 0 30px;">
                    Puedes editar tu emprendimiento y enviarlo nuevamente para revisión.
                </p>
                
                <a href="' . $urlEditar . '" style="display: inline-block; background: #1976d2; color: white; padding: 14px 28px; border-radius: 8px; text-decoration: none; font-weight: 600;">
                    Editar mi emprendimiento →
                </a>
            </div>
            
            <div style="background: #f5f5f5; padding: 20px 40px; text-align: center;">
                <p style="color: #888; font-size: 0.85rem; margin: 0;">
                    🌊 <a href="' . BASE_URL . '" style="color: #1976d2; text-decoration: none;">Mi Valdivia Online</a> — Directorio de emprendimientos de Valdivia
                </p>
            </div>
        </div>
    </body>
    </html>';
    
    return enviarCorreo($emailPropietario, '📝 Tu emprendimiento necesita ajustes - Mi Valdivia Online', $htmlBody);
}
