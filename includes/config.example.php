<?php
/**
 * Configuración Directorio Mi Valdivia Online - mivaldivia.online
 */

// Base de datos
define('DB_HOST', 'localhost');
define('DB_NAME', 'valdivia_online');
define('DB_USER', 'TU_USUARIO');
define('DB_PASS', 'TU_PASSWORD');

// URLs
// define('BASE_URL', 'https://j.prioridad.cl/valdivia'); // Dev
define('BASE_URL', 'https://mivaldivia.online'); // Producción

// Paths
define('ROOT_PATH', dirname(__DIR__));
define('UPLOADS_PATH', ROOT_PATH . '/uploads');
define('UPLOADS_URL', BASE_URL . '/uploads');

// Linode Object Storage (S3)
define('S3_ENDPOINT', 'https://us-mia-1.linodeobjects.com');
define('S3_REGION', 'us-mia-1');
define('S3_BUCKET', 'valdivia-online');
define('S3_KEY', 'TU_ACCESS_KEY');
define('S3_SECRET', 'TU_SECRET_KEY');
define('S3_URL', 'https://valdivia-online.us-mia-1.linodeobjects.com');

// SMTP
define('SMTP_HOST', 'smtp.tuservidor.com');
define('SMTP_PORT', 587);
define('SMTP_USER', 'tu@email.com');
define('SMTP_PASS', 'TU_PASSWORD_SMTP');
define('SMTP_FROM_EMAIL', 'tu@email.com');
define('SMTP_FROM_NAME', 'Mi Valdivia Online');

// Sesión (skip in API mode)
if (!defined('API_MODE')) {
    session_start();
}

// Conexión PDO
function getDB() {
    static $pdo = null;
    if ($pdo === null) {
        try {
            $pdo = new PDO(
                'mysql:host=' . DB_HOST . ';dbname=' . DB_NAME . ';charset=utf8mb4',
                DB_USER,
                DB_PASS,
                [
                    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                    PDO::ATTR_EMULATE_PREPARES => false
                ]
            );
        } catch (PDOException $e) {
            die('Error de conexión: ' . $e->getMessage());
        }
    }
    return $pdo;
}

// Helpers
function e($str) {
    return htmlspecialchars($str ?? '', ENT_QUOTES, 'UTF-8');
}

function slug($str) {
    $str = strtolower(trim($str));
    $str = preg_replace('/[áàäâ]/u', 'a', $str);
    $str = preg_replace('/[éèëê]/u', 'e', $str);
    $str = preg_replace('/[íìïî]/u', 'i', $str);
    $str = preg_replace('/[óòöô]/u', 'o', $str);
    $str = preg_replace('/[úùüû]/u', 'u', $str);
    $str = preg_replace('/[ñ]/u', 'n', $str);
    $str = preg_replace('/[^a-z0-9-]/', '-', $str);
    $str = preg_replace('/-+/', '-', $str);
    return trim($str, '-');
}

function isLoggedIn() {
    return isset($_SESSION['usuario_id']);
}

function isAdmin() {
    return isset($_SESSION['rol']) && $_SESSION['rol'] === 'admin';
}

function isEmailVerified() {
    return isset($_SESSION['email_verificado']) && $_SESSION['email_verificado'];
}

function getVerifiedEmail() {
    return $_SESSION['verified_email'] ?? null;
}

function requireLogin() {
    if (!isLoggedIn()) {
        header('Location: ' . BASE_URL . '/login.php');
        exit;
    }
}

function requireAdmin() {
    if (!isLoggedIn()) {
        header('Location: ' . BASE_URL . '/login.php');
        exit;
    }
    if (!isAdmin()) {
        header('Location: ' . BASE_URL);
        exit;
    }
}

function requireEmailVerified() {
    if (!isEmailVerified()) {
        header('Location: ' . BASE_URL . '/verificar.php');
        exit;
    }
}

function flash($key, $message = null) {
    if ($message !== null) {
        $_SESSION['flash'][$key] = $message;
    } else {
        $msg = $_SESSION['flash'][$key] ?? null;
        unset($_SESSION['flash'][$key]);
        return $msg;
    }
}

function generarCodigo() {
    return str_pad(random_int(0, 999999), 6, '0', STR_PAD_LEFT);
}

function registrarAuditoria($accion, $emprendimientoId = null, $emprendimientoNombre = null, $detalles = null) {
    $db = getDB();
    $usuarioId = $_SESSION['usuario_id'] ?? null;
    $usuarioEmail = $_SESSION['usuario_email'] ?? null;
    $ip = $_SERVER['REMOTE_ADDR'] ?? null;
    
    $stmt = $db->prepare("
        INSERT INTO auditoria (usuario_id, usuario_email, emprendimiento_id, emprendimiento_nombre, accion, detalles, ip)
        VALUES (?, ?, ?, ?, ?, ?, ?)
    ");
    $stmt->execute([
        $usuarioId,
        $usuarioEmail,
        $emprendimientoId,
        $emprendimientoNombre,
        $accion,
        is_array($detalles) ? json_encode($detalles) : $detalles,
        $ip
    ]);
}

function enviarCorreo($to, $subject, $htmlBody) {
    require_once ROOT_PATH . '/includes/smtp.php';
    return sendEmail($to, $subject, $htmlBody);
}

function getImageUrl($imagen) {
    if (!$imagen) {
        return null;
    }
    // Si ya es una URL completa (S3), devolverla
    if (str_starts_with($imagen, 'http://') || str_starts_with($imagen, 'https://')) {
        return $imagen;
    }
    // Si es un nombre de archivo local
    return UPLOADS_URL . '/emprendimientos/' . $imagen;
}
