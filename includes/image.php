<?php
/**
 * Procesamiento de imágenes con GD + S3 upload
 */

require_once __DIR__ . '/s3.php';

/**
 * Redimensiona y optimiza una imagen
 * @param string $sourcePath Ruta de la imagen original
 * @param string $destPath Ruta de destino
 * @param int $maxWidth Ancho máximo (default 1200px)
 * @param int $quality Calidad JPEG (default 85)
 * @return bool
 */
function processImage($sourcePath, $destPath, $maxWidth = 1200, $quality = 85) {
    // Obtener info de la imagen
    $info = getimagesize($sourcePath);
    if (!$info) {
        return false;
    }
    
    $mime = $info['mime'];
    $width = $info[0];
    $height = $info[1];
    
    // Cargar imagen según tipo
    switch ($mime) {
        case 'image/jpeg':
            $source = imagecreatefromjpeg($sourcePath);
            break;
        case 'image/png':
            $source = imagecreatefrompng($sourcePath);
            break;
        case 'image/webp':
            $source = imagecreatefromwebp($sourcePath);
            break;
        case 'image/gif':
            $source = imagecreatefromgif($sourcePath);
            break;
        default:
            return false;
    }
    
    if (!$source) {
        return false;
    }
    
    // Calcular nuevas dimensiones
    $newWidth = $width;
    $newHeight = $height;
    
    if ($width > $maxWidth) {
        $ratio = $maxWidth / $width;
        $newWidth = $maxWidth;
        $newHeight = (int)($height * $ratio);
    }
    
    // Crear imagen redimensionada
    $dest = imagecreatetruecolor($newWidth, $newHeight);
    
    // Preservar transparencia para PNG y GIF
    if ($mime === 'image/png' || $mime === 'image/gif') {
        imagealphablending($dest, false);
        imagesavealpha($dest, true);
        $transparent = imagecolorallocatealpha($dest, 255, 255, 255, 127);
        imagefilledrectangle($dest, 0, 0, $newWidth, $newHeight, $transparent);
    }
    
    // Redimensionar
    imagecopyresampled($dest, $source, 0, 0, 0, 0, $newWidth, $newHeight, $width, $height);
    
    // Guardar según extensión de destino
    $ext = strtolower(pathinfo($destPath, PATHINFO_EXTENSION));
    
    switch ($ext) {
        case 'jpg':
        case 'jpeg':
            $result = imagejpeg($dest, $destPath, $quality);
            break;
        case 'png':
            // PNG: compression 0-9 (6 es buen balance)
            $result = imagepng($dest, $destPath, 6);
            break;
        case 'webp':
            $result = imagewebp($dest, $destPath, $quality);
            break;
        case 'gif':
            $result = imagegif($dest, $destPath);
            break;
        default:
            $result = imagejpeg($dest, $destPath, $quality);
    }
    
    // Liberar memoria
    imagedestroy($source);
    imagedestroy($dest);
    
    return $result;
}

/**
 * Procesa y guarda imagen subida
 * @param array $file $_FILES['imagen']
 * @param string $filename Nombre de archivo destino
 * @param string $uploadDir Directorio de uploads
 * @param int $maxWidth Ancho máximo
 * @return string|false Nombre del archivo guardado o false si falla
 */
function handleImageUpload($file, $filename, $uploadDir, $maxWidth = 1200) {
    if ($file['error'] !== UPLOAD_ERR_OK || $file['size'] === 0) {
        return false;
    }
    
    // Validar MIME
    $mime = mime_content_type($file['tmp_name']);
    $allowedMime = ['image/jpeg', 'image/png', 'image/webp', 'image/gif'];
    
    if (!in_array($mime, $allowedMime)) {
        return false;
    }
    
    // Determinar extensión según MIME
    $extMap = [
        'image/jpeg' => 'jpg',
        'image/png' => 'png',
        'image/webp' => 'webp',
        'image/gif' => 'gif'
    ];
    $ext = $extMap[$mime] ?? 'jpg';
    
    // Crear directorio si no existe
    if (!is_dir($uploadDir)) {
        mkdir($uploadDir, 0755, true);
    }
    
    // Nombre final
    $finalName = $filename . '.' . $ext;
    $destPath = $uploadDir . '/' . $finalName;
    
    // Procesar imagen localmente primero
    if (!processImage($file['tmp_name'], $destPath, $maxWidth)) {
        return false;
    }
    
    // Subir a S3
    $s3Key = 'emprendimientos/' . $finalName;
    $s3Url = s3Upload($destPath, $s3Key);
    
    if ($s3Url) {
        // Eliminar archivo local después de subir
        @unlink($destPath);
        return $s3Url;
    }
    
    // Fallback: devolver nombre local si S3 falla
    error_log("S3 upload failed for $finalName, keeping local file");
    return $finalName;
}
