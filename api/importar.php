<?php
/**
 * API de Importación de Emprendimientos — Mi Valdivia Online
 * 
 * Seguridad:
 * - Switch on/off desde panel admin (clave: api_importacion)
 * - API Key en header Authorization: Bearer <key>
 * - hash_equals para comparación timing-safe
 * - Rate limiting: max 30 requests/minuto por IP
 * - Solo POST, solo JSON
 * - Validación estricta de todos los campos
 * - Imagen via base64 o URL, re-subida a S3
 * - Siempre inserta como 'pendiente'
 * - Log en tabla auditoria
 * 
 * POST /api/importar.php
 * Headers: Authorization: Bearer <API_KEY>
 *          Content-Type: application/json
 * 
 * Body (single):
 *   { "nombre": "...", "categoria_id": 2, "descripcion": "...", ... }
 * 
 * Body (batch):
 *   { "emprendimientos": [ {...}, {...}, ... ] }
 */

define('API_MODE', true);
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/s3.php';

header('Content-Type: application/json; charset=utf-8');

// --- CORS for preflight ---
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(204);
    exit;
}

// --- Only POST ---
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    die(json_encode(['error' => 'Método no permitido']));
}

$db = getDB();

// --- Check API switch ---
$stmt = $db->prepare("SELECT valor FROM configuracion WHERE clave = 'api_importacion'");
$stmt->execute();
if ($stmt->fetchColumn() !== '1') {
    http_response_code(403);
    die(json_encode(['error' => 'API de importación desactivada. Actívala desde el panel admin.']));
}

// --- Rate limiting (30/min per IP, file-based) ---
$ip = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
$rateLimitDir = sys_get_temp_dir() . '/valdivia_api_rate';
@mkdir($rateLimitDir, 0700, true);
$rateLimitFile = $rateLimitDir . '/' . md5($ip) . '.json';
$now = time();
$rateData = file_exists($rateLimitFile) ? (json_decode(file_get_contents($rateLimitFile), true) ?: []) : [];
$rateData = array_values(array_filter($rateData, fn($t) => $t > $now - 60));
if (count($rateData) >= 30) {
    http_response_code(429);
    die(json_encode(['error' => 'Rate limit: max 30 requests/minuto']));
}
$rateData[] = $now;
file_put_contents($rateLimitFile, json_encode($rateData), LOCK_EX);

// --- Authenticate (Bearer token, timing-safe) ---
$authHeader = $_SERVER['HTTP_AUTHORIZATION'] 
    ?? $_SERVER['REDIRECT_HTTP_AUTHORIZATION'] 
    ?? (function_exists('apache_request_headers') ? (apache_request_headers()['Authorization'] ?? '') : '');
if (!preg_match('/^Bearer\s+(.+)$/i', $authHeader, $matches)) {
    http_response_code(401);
    die(json_encode(['error' => 'Header requerido: Authorization: Bearer <API_KEY>']));
}
$providedKey = trim($matches[1]);

$stmt = $db->prepare("SELECT valor FROM configuracion WHERE clave = 'api_key'");
$stmt->execute();
$storedKey = $stmt->fetchColumn();

if (!$storedKey || !hash_equals($storedKey, $providedKey)) {
    logApi($db, $ip, 'auth_failed', null);
    http_response_code(401);
    die(json_encode(['error' => 'API Key inválida']));
}

// --- Parse JSON body (max 10MB) ---
$rawBody = file_get_contents('php://input');
if (strlen($rawBody) > 10 * 1024 * 1024) {
    http_response_code(413);
    die(json_encode(['error' => 'Payload máximo: 10MB']));
}
$body = json_decode($rawBody, true);
if (json_last_error() !== JSON_ERROR_NONE) {
    http_response_code(400);
    die(json_encode(['error' => 'JSON inválido: ' . json_last_error_msg()]));
}

// --- Single or batch ---
$items = [];
if (isset($body['emprendimientos']) && is_array($body['emprendimientos'])) {
    $items = $body['emprendimientos'];
} elseif (isset($body['nombre'])) {
    $items = [$body];
} else {
    http_response_code(400);
    die(json_encode(['error' => 'Enviar {nombre,...} o {emprendimientos:[...]}']));
}
if (count($items) > 50) {
    http_response_code(400);
    die(json_encode(['error' => 'Máximo 50 por request']));
}

// --- Process each ---
$results = [];
$ok = 0;
$errors = 0;

foreach ($items as $idx => $emp) {
    $r = importarEmprendimiento($db, $emp, $idx);
    $results[] = $r;
    $r['status'] === 'ok' ? $ok++ : $errors++;
}

logApi($db, $ip, 'import', "ok:{$ok} errors:{$errors} total:" . count($items));

echo json_encode([
    'success' => true,
    'total' => count($items),
    'insertados' => $ok,
    'errores' => $errors,
    'resultados' => $results,
], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);


// ========== FUNCTIONS ==========

function importarEmprendimiento(PDO $db, array $emp, int $idx): array {
    // -- Nombre (required) --
    $nombre = trim($emp['nombre'] ?? '');
    if (!$nombre || mb_strlen($nombre) < 2 || mb_strlen($nombre) > 150) {
        return err($idx, $nombre, 'nombre requerido (2-150 chars)');
    }

    // -- Categoría (required: id or nombre/slug) --
    $catId = null;
    if (!empty($emp['categoria_id'])) {
        $catId = (int)$emp['categoria_id'];
        $check = $db->prepare("SELECT id FROM categorias WHERE id = ?");
        $check->execute([$catId]);
        if (!$check->fetch()) return err($idx, $nombre, "categoria_id {$catId} no existe");
    } elseif (!empty($emp['categoria'])) {
        $s = slug($emp['categoria']);
        $check = $db->prepare("SELECT id FROM categorias WHERE slug = ? OR LOWER(nombre) = LOWER(?)");
        $check->execute([$s, $emp['categoria']]);
        $catId = $check->fetchColumn();
        if (!$catId) return err($idx, $nombre, "categoría '{$emp['categoria']}' no encontrada");
    } else {
        return err($idx, $nombre, 'categoria_id o categoria requerida');
    }

    // -- Slug único --
    $base = slug($nombre);
    $final = $base;
    $c = 1;
    while (true) {
        $st = $db->prepare("SELECT id FROM emprendimientos WHERE slug = ?");
        $st->execute([$final]);
        if (!$st->fetch()) break;
        $final = $base . '-' . $c++;
        if ($c > 50) return err($idx, $nombre, 'No se pudo generar slug único');
    }

    // -- Sanitize fields --
    $descripcion     = mb_substr(trim($emp['descripcion'] ?? ''), 0, 5000);
    $descripcionCorta = mb_substr(trim($emp['descripcion_corta'] ?? ''), 0, 300);
    if (!$descripcionCorta && $descripcion) {
        $descripcionCorta = mb_substr($descripcion, 0, 247) . (mb_strlen($descripcion) > 247 ? '...' : '');
    }
    $direccion  = mb_substr(trim($emp['direccion'] ?? ''), 0, 255);
    $telefono   = preg_replace('/[^+0-9]/', '', $emp['telefono'] ?? '');
    $whatsapp   = preg_replace('/[^+0-9]/', '', $emp['whatsapp'] ?? $telefono);
    $email      = filter_var($emp['email'] ?? '', FILTER_VALIDATE_EMAIL) ?: '';
    $web        = mb_substr(trim($emp['web'] ?? ''), 0, 255);
    $instagram  = preg_replace('/[^a-zA-Z0-9._]/', '', $emp['instagram'] ?? '');
    $facebook   = mb_substr(trim($emp['facebook'] ?? ''), 0, 255);
    $horarios   = mb_substr(trim($emp['horarios'] ?? ''), 0, 500);
    $rangoPrecio = mb_substr(trim($emp['rango_precios'] ?? ''), 0, 50);
    $lat = isset($emp['latitud']) && is_numeric($emp['latitud']) ? (float)$emp['latitud'] : null;
    $lng = isset($emp['longitud']) && is_numeric($emp['longitud']) ? (float)$emp['longitud'] : null;

    // -- Image (base64 or URL → S3) --
    $imagenUrl = processImage($emp, $final);

    // -- INSERT as pendiente --
    try {
        $sql = "INSERT INTO emprendimientos 
                (categoria_id, nombre, slug, descripcion, descripcion_corta, direccion,
                 telefono, whatsapp, email, web, instagram, facebook, horarios, rango_precios,
                 latitud, longitud, imagen_principal, estado)
                VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,'pendiente')";
        $db->prepare($sql)->execute([
            $catId, $nombre, $final, $descripcion, $descripcionCorta, $direccion,
            $telefono, $whatsapp, $email, $web, $instagram, $facebook, $horarios, $rangoPrecio,
            $lat, $lng, $imagenUrl
        ]);
        return [
            'status' => 'ok', 'idx' => $idx,
            'id' => (int)$db->lastInsertId(),
            'nombre' => $nombre, 'slug' => $final,
            'imagen' => $imagenUrl,
        ];
    } catch (PDOException $e) {
        return err($idx, $nombre, 'Error BD: ' . $e->getMessage());
    }
}

function processImage(array $emp, string $slug): ?string {
    $imgData = null;
    $mime = null;

    // Option A: base64
    if (!empty($emp['imagen_base64'])) {
        $imgData = base64_decode($emp['imagen_base64'], true);
        if ($imgData === false || strlen($imgData) > 5 * 1024 * 1024) return null;
    }
    // Option B: URL
    elseif (!empty($emp['imagen_url'])) {
        $url = filter_var($emp['imagen_url'], FILTER_VALIDATE_URL);
        if (!$url) return null;
        $ctx = stream_context_create(['http' => ['timeout' => 15, 'user_agent' => 'ValdiviaOnline/1.0']]);
        $imgData = @file_get_contents($url, false, $ctx);
        if (!$imgData || strlen($imgData) > 5 * 1024 * 1024) return null;
    }

    if (!$imgData) return null;

    $finfo = new finfo(FILEINFO_MIME_TYPE);
    $mime = $finfo->buffer($imgData);
    $exts = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp'];
    if (!isset($exts[$mime])) return null;

    $s3Key = "emprendimientos/{$slug}.{$exts[$mime]}";
    return s3PutObject($s3Key, $imgData, $mime) ?: null;
}

// slug() already defined in config.php — reuse it

function err(int $idx, string $nombre, string $msg): array {
    return ['status' => 'error', 'idx' => $idx, 'nombre' => $nombre, 'error' => $msg];
}

function logApi(PDO $db, string $ip, string $action, ?string $details): void {
    try {
        $db->prepare("INSERT INTO auditoria (usuario_email, accion, detalles, ip) VALUES ('API',?,?,?)")
           ->execute([$action, $details, $ip]);
    } catch (Exception $e) {}
}
