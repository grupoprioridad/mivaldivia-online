<?php
/**
 * API Widget Destacados - Mi Valdivia Online
 * Solo accesible desde dominios autorizados
 */

// Dominios autorizados
$allowedOrigins = [
    'https://mivaldivia.online',
    'https://www.mivaldivia.online',
    'https://mivaldivia.online',
    'https://www.mivaldivia.online',
    'https://j.prioridad.cl' // dev
];

$origin = $_SERVER['HTTP_ORIGIN'] ?? '';

// Validar origen
if (!in_array($origin, $allowedOrigins)) {
    http_response_code(403);
    header('Content-Type: application/json');
    echo json_encode(['error' => 'Acceso no autorizado']);
    exit;
}

// CORS headers
header("Access-Control-Allow-Origin: $origin");
header('Access-Control-Allow-Methods: GET');
header('Access-Control-Allow-Headers: Content-Type');
header('Content-Type: application/json; charset=utf-8');

// Handle preflight
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    exit;
}

require_once __DIR__ . '/../includes/config.php';

try {
    $db = getDB();
    
    // Solo emprendimientos destacados y aprobados
    $stmt = $db->query("
        SELECT 
            e.nombre,
            e.slug,
            e.descripcion_corta,
            e.imagen_principal,
            c.nombre as categoria,
            c.icono as categoria_icono
        FROM emprendimientos e
        JOIN categorias c ON e.categoria_id = c.id
        WHERE e.estado = 'aprobado' 
          AND e.destacado = 1
        ORDER BY RAND()
    ");
    
    $destacados = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Agregar URL completa a imágenes
    foreach ($destacados as &$emp) {
        if ($emp['imagen_principal']) {
            // Si ya es URL completa, dejar como está
            if (strpos($emp['imagen_principal'], 'http') !== 0) {
                $emp['imagen_principal'] = 'https://mivaldivia.online/uploads/' . $emp['imagen_principal'];
            }
        }
        $emp['url'] = 'https://mivaldivia.online/ver.php?slug=' . $emp['slug'];
    }
    
    echo json_encode([
        'success' => true,
        'total' => count($destacados),
        'emprendimientos' => $destacados,
        'registro_url' => 'https://mivaldivia.online/verificar.php'
    ], JSON_UNESCAPED_UNICODE);
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Error interno']);
}
