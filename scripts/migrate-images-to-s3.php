<?php
/**
 * Script de migración de imágenes locales a Linode Object Storage (S3)
 * 
 * Uso:
 *   php migrate-images-to-s3.php          # Solo mostrar qué se migraría (dry-run)
 *   php migrate-images-to-s3.php --run    # Ejecutar migración real
 *   php migrate-images-to-s3.php --run --delete-local   # Migrar y borrar locales
 */

require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/s3.php';

// Colores para consola
function green($text) { return "\033[32m$text\033[0m"; }
function red($text) { return "\033[31m$text\033[0m"; }
function yellow($text) { return "\033[33m$text\033[0m"; }
function cyan($text) { return "\033[36m$text\033[0m"; }

// Parsear argumentos
$dryRun = !in_array('--run', $argv);
$deleteLocal = in_array('--delete-local', $argv);

echo "\n";
echo cyan("╔══════════════════════════════════════════════════════════╗\n");
echo cyan("║") . "   📦 Migración de Imágenes a S3 - Mi Valdivia Online           " . cyan("║\n");
echo cyan("╚══════════════════════════════════════════════════════════╝\n");
echo "\n";

if ($dryRun) {
    echo yellow("⚠️  MODO DRY-RUN: Solo se mostrarán los cambios, nada se ejecutará.\n");
    echo yellow("   Usa --run para ejecutar la migración real.\n\n");
} else {
    echo green("🚀 MODO EJECUCIÓN: Se migrarán las imágenes a S3.\n");
    if ($deleteLocal) {
        echo red("🗑️  Se eliminarán los archivos locales después de migrar.\n");
    }
    echo "\n";
}

$db = getDB();

// Obtener emprendimientos con imágenes locales (no URLs)
$stmt = $db->query("
    SELECT id, nombre, slug, imagen_principal 
    FROM emprendimientos 
    WHERE imagen_principal IS NOT NULL 
      AND imagen_principal != ''
      AND imagen_principal NOT LIKE 'http://%'
      AND imagen_principal NOT LIKE 'https://%'
");
$emprendimientos = $stmt->fetchAll();

$total = count($emprendimientos);
$migrados = 0;
$errores = 0;
$yaExisten = 0;

echo "📊 Encontrados: " . cyan($total) . " emprendimientos con imágenes locales\n\n";

if ($total === 0) {
    echo green("✅ No hay imágenes locales para migrar. ¡Todo está en S3!\n\n");
    exit(0);
}

$uploadsPath = UPLOADS_PATH . '/emprendimientos';

foreach ($emprendimientos as $i => $emp) {
    $num = $i + 1;
    $localFile = $uploadsPath . '/' . $emp['imagen_principal'];
    $s3Key = 'emprendimientos/' . $emp['imagen_principal'];
    
    echo "[$num/$total] " . cyan($emp['nombre']) . "\n";
    echo "        Local: {$emp['imagen_principal']}\n";
    
    // Verificar si existe el archivo local
    if (!file_exists($localFile)) {
        echo "        " . red("❌ Archivo local no existe") . "\n\n";
        $errores++;
        continue;
    }
    
    $fileSize = round(filesize($localFile) / 1024, 1);
    echo "        Tamaño: {$fileSize} KB\n";
    
    if ($dryRun) {
        echo "        " . yellow("→ Se migraría a: " . S3_URL . "/$s3Key") . "\n\n";
        $migrados++;
        continue;
    }
    
    // Ejecutar migración
    $s3Url = s3Upload($localFile, $s3Key);
    
    if ($s3Url) {
        // Actualizar BD
        $updateStmt = $db->prepare("UPDATE emprendimientos SET imagen_principal = ? WHERE id = ?");
        $updateStmt->execute([$s3Url, $emp['id']]);
        
        echo "        " . green("✅ Migrado: $s3Url") . "\n";
        
        if ($deleteLocal) {
            if (unlink($localFile)) {
                echo "        " . green("🗑️  Archivo local eliminado") . "\n";
            } else {
                echo "        " . yellow("⚠️  No se pudo eliminar archivo local") . "\n";
            }
        }
        
        $migrados++;
    } else {
        echo "        " . red("❌ Error al subir a S3") . "\n";
        $errores++;
    }
    
    echo "\n";
}

// Resumen
echo cyan("══════════════════════════════════════════════════════════\n");
echo "📊 RESUMEN\n";
echo cyan("══════════════════════════════════════════════════════════\n");
echo "   Total procesados: $total\n";
echo "   " . green("✅ Migrados: $migrados") . "\n";
if ($errores > 0) {
    echo "   " . red("❌ Errores: $errores") . "\n";
}

if ($dryRun && $migrados > 0) {
    echo "\n" . yellow("👉 Ejecuta con --run para migrar realmente.") . "\n";
    echo yellow("   php migrate-images-to-s3.php --run") . "\n";
    echo yellow("   php migrate-images-to-s3.php --run --delete-local") . "\n";
}

echo "\n";
