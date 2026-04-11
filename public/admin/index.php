<?php
require_once __DIR__ . '/../../includes/config.php';
require_once __DIR__ . '/../../includes/notificaciones.php';
requireAdmin();

$db = getDB();

// Procesar descartar duplicado
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['descartar_duplicado'])) {
    $tipo = $_POST['tipo'];
    $valor = $_POST['valor'];
    $ids = $_POST['ids'];
    
    $stmt = $db->prepare("INSERT IGNORE INTO duplicados_descartados (tipo, valor, ids) VALUES (?, ?, ?)");
    $stmt->execute([$tipo, $valor, $ids]);
    
    flash('success', 'Duplicado descartado. No aparecerá más en la lista.');
    header('Location: ' . BASE_URL . '/admin/');
    exit;
}

// Procesar restaurar duplicados
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['restaurar_duplicados'])) {
    $db->exec("DELETE FROM duplicados_descartados");
    flash('success', 'Todos los duplicados descartados han sido restaurados.');
    header('Location: ' . BASE_URL . '/admin/');
    exit;
}

// Procesar toggle de configuración
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['toggle_config'])) {
    $clave = $_POST['toggle_config'];
    $stmt = $db->prepare("SELECT valor FROM configuracion WHERE clave = ?");
    $stmt->execute([$clave]);
    $valorActual = $stmt->fetchColumn();
    $nuevoValor = $valorActual === '1' ? '0' : '1';
    $db->prepare("UPDATE configuracion SET valor = ? WHERE clave = ?")->execute([$nuevoValor, $clave]);
    flash('success', 'Configuración actualizada.');
    header('Location: ' . BASE_URL . '/admin/');
    exit;
}

// Obtener configuración
$configStmt = $db->query("SELECT clave, valor FROM configuracion");
$config = [];
while ($row = $configStmt->fetch()) {
    $config[$row['clave']] = $row['valor'];
}

// Procesar acciones masivas
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['accion_masiva'])) {
    $ids = $_POST['ids'] ?? [];
    $accion = $_POST['accion_masiva'];
    
    if (!empty($ids)) {
        $placeholders = implode(',', array_fill(0, count($ids), '?'));
        
        if ($accion === 'aprobar_masivo') {
            $meses = 12;
            $hastaFecha = date('Y-m-d', strtotime("+{$meses} months"));
            $db->prepare("UPDATE emprendimientos SET estado = 'aprobado', aprobado_hasta = ? WHERE id IN ($placeholders)")->execute(array_merge([$hastaFecha], $ids));
            flash('success', count($ids) . ' emprendimientos aprobados.');
        } elseif ($accion === 'rechazar_masivo') {
            $db->prepare("UPDATE emprendimientos SET estado = 'rechazado' WHERE id IN ($placeholders)")->execute($ids);
            flash('success', count($ids) . ' emprendimientos rechazados.');
        } elseif ($accion === 'destacar_masivo') {
            $db->prepare("UPDATE emprendimientos SET destacado = 1 WHERE id IN ($placeholders)")->execute($ids);
            flash('success', count($ids) . ' emprendimientos destacados.');
        } elseif ($accion === 'quitar_destacado_masivo') {
            $db->prepare("UPDATE emprendimientos SET destacado = 0 WHERE id IN ($placeholders)")->execute($ids);
            flash('success', 'Destacado removido de ' . count($ids) . ' emprendimientos.');
        }
    }
    
    header('Location: ' . $_SERVER['REQUEST_URI']);
    exit;
}

// Procesar acciones individuales
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['accion'])) {
    $id = (int)$_POST['id'];
    $accion = $_POST['accion'];
    
    if ($accion === 'aprobar') {
        $periodo = $_POST['periodo'] ?? '1';
        $meses = (int)$periodo;
        $hastaFecha = date('Y-m-d', strtotime("+{$meses} months"));
        
        $stmt = $db->prepare("SELECT e.*, ee.email as owner_email FROM emprendimientos e LEFT JOIN emprendimiento_emails ee ON e.id = ee.emprendimiento_id WHERE e.id = ?");
        $stmt->execute([$id]);
        $emp = $stmt->fetch();
        
        $db->prepare("UPDATE emprendimientos SET estado = 'aprobado', aprobado_hasta = ? WHERE id = ?")->execute([$hastaFecha, $id]);
        
        $emailPropietario = $emp['owner_email'] ?: $emp['email'];
        if ($emailPropietario) {
            notificarAprobacion($emp, $emailPropietario);
        }
        
        registrarAuditoria('aprobar', $id, $emp['nombre'], ['periodo_meses' => $meses, 'hasta' => $hastaFecha]);
        flash('success', "Emprendimiento aprobado hasta " . date('d/m/Y', strtotime($hastaFecha)));
    } elseif ($accion === 'rechazar') {
        $razon = trim($_POST['razon_rechazo'] ?? '');
        
        $stmt = $db->prepare("SELECT e.*, ee.email as owner_email FROM emprendimientos e LEFT JOIN emprendimiento_emails ee ON e.id = ee.emprendimiento_id WHERE e.id = ?");
        $stmt->execute([$id]);
        $emp = $stmt->fetch();
        
        $db->prepare("UPDATE emprendimientos SET estado = 'rechazado' WHERE id = ?")->execute([$id]);
        
        $emailPropietario = $emp['owner_email'] ?: $emp['email'];
        if ($emailPropietario && $razon) {
            notificarRechazo($emp, $emailPropietario, $razon);
        }
        
        registrarAuditoria('rechazar', $id, $emp['nombre'], ['razon' => $razon]);
        flash('success', 'Emprendimiento rechazado y propietario notificado.');
    } elseif ($accion === 'destacar') {
        $stmt = $db->prepare("SELECT nombre FROM emprendimientos WHERE id = ?");
        $stmt->execute([$id]);
        $empNombre = $stmt->fetchColumn();
        
        $db->prepare("UPDATE emprendimientos SET destacado = 1 WHERE id = ?")->execute([$id]);
        registrarAuditoria('destacar', $id, $empNombre);
        flash('success', 'Emprendimiento destacado.');
    } elseif ($accion === 'quitar_destacado') {
        $stmt = $db->prepare("SELECT nombre FROM emprendimientos WHERE id = ?");
        $stmt->execute([$id]);
        $empNombre = $stmt->fetchColumn();
        
        $db->prepare("UPDATE emprendimientos SET destacado = 0 WHERE id = ?")->execute([$id]);
        registrarAuditoria('quitar_destacado', $id, $empNombre);
        flash('success', 'Destacado removido.');
    } elseif ($accion === 'eliminar') {
        $stmt = $db->prepare("SELECT nombre, imagen_principal FROM emprendimientos WHERE id = ?");
        $stmt->execute([$id]);
        $emp = $stmt->fetch();
        
        if ($emp) {
            // Eliminar registros relacionados
            $db->prepare("DELETE FROM emprendimiento_emails WHERE emprendimiento_id = ?")->execute([$id]);
            $db->prepare("DELETE FROM emprendimientos WHERE id = ?")->execute([$id]);
            registrarAuditoria('eliminar', $id, $emp['nombre']);
            flash('success', 'Emprendimiento "' . $emp['nombre'] . '" eliminado permanentemente.');
        }
    } elseif ($accion === 'contactar') {
        $mensaje = trim($_POST['mensaje'] ?? '');
        
        $stmt = $db->prepare("SELECT e.*, ee.email as owner_email FROM emprendimientos e LEFT JOIN emprendimiento_emails ee ON e.id = ee.emprendimiento_id WHERE e.id = ?");
        $stmt->execute([$id]);
        $emp = $stmt->fetch();
        
        $emailDestino = $emp['owner_email'] ?: $emp['email'];
        
        if ($emailDestino && $mensaje) {
            $htmlBody = '
            <!DOCTYPE html>
            <html>
            <head><meta charset="UTF-8"></head>
            <body style="font-family: -apple-system, BlinkMacSystemFont, \'Segoe UI\', Roboto, sans-serif; background: #f0f7fc; padding: 40px 20px;">
                <div style="max-width: 500px; margin: 0 auto; background: white; border-radius: 12px; padding: 40px; box-shadow: 0 4px 20px rgba(0,0,0,0.1);">
                    <div style="text-align: center; margin-bottom: 30px;">
                        <span style="font-size: 2.5rem;">🌊</span>
                        <h1 style="margin: 10px 0 0; color: #0c2d4a; font-size: 1.5rem;">Mi Valdivia Online</h1>
                    </div>
                    
                    <p style="color: #2c3e50; margin-bottom: 15px;">Hola,</p>
                    <p style="color: #2c3e50; margin-bottom: 15px;">Te escribimos respecto a tu emprendimiento <strong>' . htmlspecialchars($emp['nombre']) . '</strong>:</p>
                    
                    <div style="background: #dce8f4; border-radius: 8px; padding: 20px; margin: 20px 0; color: #2c3e50; line-height: 1.6;">
                        ' . nl2br(htmlspecialchars($mensaje)) . '
                    </div>
                    
                    <p style="color: #2c3e50; margin-bottom: 15px;">Si necesitas soporte, escríbenos por <a href="https://wa.me/56996422600" style="color: #25D366; text-decoration: none; font-weight: 500;">WhatsApp</a>.</p>
                    
                    <p style="color: #2c3e50; margin-top: 30px;">
                        Saludos,<br>
                        <strong style="color: #0c2d4a;">Equipo Mi Valdivia Online</strong>
                    </p>
                </div>
                <p style="text-align: center; color: #9ca3af; font-size: 0.8rem; margin-top: 30px;">
                    🌊 Mi Valdivia Online — Una iniciativa de El Correo de Valdivia
                </p>
            </body>
            </html>';
            
            if (enviarCorreo($emailDestino, 'Mensaje sobre tu emprendimiento — Mi Valdivia Online', $htmlBody)) {
                registrarAuditoria('contactar', $id, $emp['nombre'], ['mensaje' => substr($mensaje, 0, 100)]);
                flash('success', 'Mensaje enviado a ' . $emailDestino);
            } else {
                flash('error', 'Error al enviar el mensaje');
            }
        }
    }
    
    header('Location: ' . BASE_URL . '/admin/');
    exit;
}

// Estadísticas
$stats = [
    'total' => $db->query("SELECT COUNT(*) FROM emprendimientos")->fetchColumn(),
    'pendientes' => $db->query("SELECT COUNT(*) FROM emprendimientos WHERE estado = 'pendiente'")->fetchColumn(),
    'aprobados' => $db->query("SELECT COUNT(*) FROM emprendimientos WHERE estado = 'aprobado'")->fetchColumn(),
    'rechazados' => $db->query("SELECT COUNT(*) FROM emprendimientos WHERE estado = 'rechazado'")->fetchColumn(),
];

// Obtener duplicados descartados
$descartadosStmt = $db->query("SELECT tipo, valor FROM duplicados_descartados");
$descartados = [];
while ($row = $descartadosStmt->fetch()) {
    $descartados[$row['tipo']][] = $row['valor'];
}
$totalDescartados = $db->query("SELECT COUNT(*) FROM duplicados_descartados")->fetchColumn();

// Detección de duplicados
$duplicados = [
    'nombre' => [],
    'telefono' => [],
    'email' => [],
    'similares' => []
];

// Duplicados por nombre exacto (case-insensitive)
$stmt = $db->query("SELECT MIN(id) as id, MIN(nombre) as nombre, LOWER(nombre) as nombre_lower, COUNT(*) as cantidad, GROUP_CONCAT(id ORDER BY id) as ids 
    FROM emprendimientos GROUP BY LOWER(nombre) HAVING COUNT(*) > 1 ORDER BY cantidad DESC LIMIT 10");
$nombreDups = $stmt->fetchAll();
foreach ($nombreDups as $dup) {
    if (!isset($descartados['nombre']) || !in_array($dup['nombre_lower'], $descartados['nombre'])) {
        $duplicados['nombre'][] = $dup;
    }
}

// Duplicados por teléfono
$stmt = $db->query("SELECT telefono, COUNT(*) as cantidad, GROUP_CONCAT(id ORDER BY id) as ids, GROUP_CONCAT(nombre ORDER BY id SEPARATOR ' | ') as nombres 
    FROM emprendimientos WHERE telefono IS NOT NULL AND telefono != '' GROUP BY telefono HAVING COUNT(*) > 1 ORDER BY cantidad DESC LIMIT 10");
$telDups = $stmt->fetchAll();
foreach ($telDups as $dup) {
    if (!isset($descartados['telefono']) || !in_array($dup['telefono'], $descartados['telefono'])) {
        $duplicados['telefono'][] = $dup;
    }
}

// Duplicados por email
$stmt = $db->query("SELECT MIN(email) as email, LOWER(MIN(email)) as email_lower, COUNT(*) as cantidad, GROUP_CONCAT(id ORDER BY id) as ids, GROUP_CONCAT(nombre ORDER BY id SEPARATOR ' | ') as nombres 
    FROM emprendimientos WHERE email IS NOT NULL AND email != '' GROUP BY LOWER(email) HAVING COUNT(*) > 1 ORDER BY cantidad DESC LIMIT 10");
$emailDups = $stmt->fetchAll();
foreach ($emailDups as $dup) {
    if (!isset($descartados['email']) || !in_array($dup['email_lower'], $descartados['email'])) {
        $duplicados['email'][] = $dup;
    }
}

// Nombres similares (usando SOUNDEX para español)
$stmt = $db->query("SELECT e1.id as id1, e1.nombre as nombre1, e2.id as id2, e2.nombre as nombre2, CONCAT(LEAST(e1.id, e2.id), '-', GREATEST(e1.id, e2.id)) as par_ids
    FROM emprendimientos e1
    JOIN emprendimientos e2 ON e1.id < e2.id 
    WHERE SOUNDEX(e1.nombre) = SOUNDEX(e2.nombre)
    AND e1.nombre != e2.nombre
    LIMIT 10");
$similarDups = $stmt->fetchAll();
foreach ($similarDups as $dup) {
    if (!isset($descartados['similar']) || !in_array($dup['par_ids'], $descartados['similar'])) {
        $duplicados['similares'][] = $dup;
    }
}

$totalDuplicados = count($duplicados['nombre']) + count($duplicados['telefono']) + count($duplicados['email']) + count($duplicados['similares']);

// Obtener categorías para filtro
$categorias = $db->query("SELECT * FROM categorias WHERE activo = 1 ORDER BY nombre")->fetchAll();

// Filtros
$filtro = $_GET['estado'] ?? 'pendiente';
$categoriaFiltro = $_GET['categoria'] ?? '';
$busqueda = trim($_GET['q'] ?? '');
$orden = $_GET['orden'] ?? 'fecha';

// Construir query
$sql = "SELECT e.*, c.nombre as categoria_nombre, c.icono as categoria_icono, 
        COALESCE(u.nombre, ee.email, 'Sin registro') as usuario_nombre, 
        COALESCE(u.email, ee.email, '-') as usuario_email
        FROM emprendimientos e 
        JOIN categorias c ON e.categoria_id = c.id 
        LEFT JOIN usuarios u ON e.usuario_id = u.id
        LEFT JOIN emprendimiento_emails ee ON e.id = ee.emprendimiento_id
        WHERE 1=1";

$params = [];

if ($filtro !== 'todos') {
    $sql .= " AND e.estado = ?";
    $params[] = $filtro;
}

if ($categoriaFiltro) {
    $sql .= " AND c.id = ?";
    $params[] = $categoriaFiltro;
}

if ($busqueda) {
    $sql .= " AND (e.nombre LIKE ? OR e.descripcion LIKE ?)";
    $params[] = "%$busqueda%";
    $params[] = "%$busqueda%";
}

// Ordenamiento
switch ($orden) {
    case 'nombre':
        $sql .= " ORDER BY e.nombre ASC";
        break;
    case 'categoria':
        $sql .= " ORDER BY c.nombre ASC, e.nombre ASC";
        break;
    case 'visitas':
        $sql .= " ORDER BY e.visitas DESC";
        break;
    default:
        $sql .= " ORDER BY e.created_at DESC";
}

$stmt = $db->prepare($sql);
$stmt->execute($params);
$emprendimientos = $stmt->fetchAll();

$success = flash('success');
?>
<!DOCTYPE html>
<html lang="es">
<head>
<?php include __DIR__ . "/../../includes/analytics.php"; ?>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin — Mi Valdivia Online</title>
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@400;500;600;700&display=swap" rel="stylesheet">
    <style>
        :root {
            --river-600: #1976d2;
            --river-700: #1565a0;
            --river-900: #0c2d4a;
            --earth-100: #f0f7fc;
            --earth-200: #dce8f4;
            --earth-800: #2c3e50;
            --earth-900: #1a252f;
            --white: #ffffff;
            --green-600: #16a34a;
            --yellow-500: #f59e0b;
            --red-500: #dc2626;
            --blue-500: #3b82f6;
            --font-body: 'Outfit', system-ui, sans-serif;
        }
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: var(--font-body); background: var(--earth-100); min-height: 100vh; }
        
        .header { background: var(--river-900); padding: 1rem 1.5rem; }
        .header-inner { max-width: 1400px; margin: 0 auto; display: flex; justify-content: space-between; align-items: center; }
        .logo { font-size: 1.25rem; color: var(--white); text-decoration: none; font-weight: 600; }
        .header a { color: var(--earth-200); text-decoration: none; font-size: 0.9rem; }
        
        .container { max-width: 1400px; margin: 0 auto; padding: 2rem 1.5rem; }
        h1 { font-size: 1.75rem; color: var(--earth-900); margin-bottom: 1.5rem; }
        
        .stats { display: grid; grid-template-columns: repeat(auto-fit, minmax(150px, 1fr)); gap: 1rem; margin-bottom: 2rem; }
        .stat {
            background: var(--white);
            padding: 1.25rem;
            border-radius: 10px;
            text-align: center;
            box-shadow: 0 2px 8px rgba(0,0,0,0.05);
        }
        .stat-value { font-size: 2rem; font-weight: 700; color: var(--river-600); }
        .stat-label { font-size: 0.85rem; color: var(--earth-800); }
        .stat.pending .stat-value { color: var(--yellow-500); }
        
        /* Barra de filtros y búsqueda */
        .toolbar {
            background: var(--white);
            padding: 1rem;
            border-radius: 12px;
            margin-bottom: 1rem;
            box-shadow: 0 2px 8px rgba(0,0,0,0.05);
        }
        .toolbar-row {
            display: flex;
            gap: 1rem;
            flex-wrap: wrap;
            align-items: center;
        }
        .toolbar-row:not(:last-child) {
            margin-bottom: 1rem;
            padding-bottom: 1rem;
            border-bottom: 1px solid var(--earth-200);
        }
        
        .search-box {
            flex: 1;
            min-width: 200px;
            display: flex;
            gap: 0.5rem;
        }
        .search-box input {
            flex: 1;
            padding: 0.6rem 1rem;
            border: 1px solid var(--earth-200);
            border-radius: 8px;
            font-family: inherit;
            font-size: 0.9rem;
        }
        .search-box input:focus { outline: none; border-color: var(--river-600); }
        .search-box button {
            padding: 0.6rem 1rem;
            background: var(--river-600);
            color: white;
            border: none;
            border-radius: 8px;
            cursor: pointer;
            font-family: inherit;
        }
        
        .filter-group {
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }
        .filter-group label {
            font-size: 0.85rem;
            color: var(--earth-800);
            font-weight: 500;
        }
        .filter-group select {
            padding: 0.5rem 0.75rem;
            border: 1px solid var(--earth-200);
            border-radius: 8px;
            font-family: inherit;
            font-size: 0.85rem;
            background: white;
        }
        
        .filters { display: flex; gap: 0.5rem; flex-wrap: wrap; }
        .filter-btn {
            padding: 0.5rem 1rem;
            background: var(--earth-100);
            border: 1px solid var(--earth-200);
            border-radius: 100px;
            text-decoration: none;
            color: var(--earth-800);
            font-size: 0.85rem;
            transition: all 0.2s;
        }
        .filter-btn:hover { border-color: var(--river-600); }
        .filter-btn.active { background: var(--river-600); color: var(--white); border-color: var(--river-600); }
        
        /* Barra de acciones masivas */
        .bulk-actions {
            background: var(--blue-500);
            color: white;
            padding: 0.75rem 1rem;
            border-radius: 8px;
            margin-bottom: 1rem;
            display: none;
            align-items: center;
            gap: 1rem;
            animation: slideDown 0.2s ease;
        }
        .bulk-actions.active { display: flex; }
        @keyframes slideDown {
            from { opacity: 0; transform: translateY(-10px); }
            to { opacity: 1; transform: translateY(0); }
        }
        .bulk-count { font-weight: 600; }
        .bulk-buttons { display: flex; gap: 0.5rem; margin-left: auto; }
        .bulk-btn {
            padding: 0.4rem 0.8rem;
            border: none;
            border-radius: 6px;
            font-size: 0.8rem;
            cursor: pointer;
            font-family: inherit;
            font-weight: 500;
        }
        .bulk-btn-approve { background: var(--green-600); color: white; }
        .bulk-btn-reject { background: var(--red-500); color: white; }
        .bulk-btn-star { background: var(--yellow-500); color: white; }
        .bulk-btn-unstar { background: rgba(255,255,255,0.2); color: white; }
        .bulk-btn-cancel { background: transparent; color: white; text-decoration: underline; }
        
        .success { background: #f0fdf4; border: 1px solid #bbf7d0; color: var(--green-600); padding: 1rem; border-radius: 8px; margin-bottom: 1rem; }
        
        .table-wrap { background: var(--white); border-radius: 12px; overflow: hidden; box-shadow: 0 2px 10px rgba(0,0,0,0.06); }
        table { width: 100%; border-collapse: collapse; }
        th { background: var(--earth-100); text-align: left; padding: 0.75rem 1rem; font-size: 0.8rem; text-transform: uppercase; color: var(--earth-800); }
        td { padding: 0.75rem 1rem; border-top: 1px solid var(--earth-200); vertical-align: middle; }
        tr:hover { background: #fafafa; }
        
        .checkbox-cell { width: 40px; text-align: center; }
        .checkbox-cell input { width: 18px; height: 18px; cursor: pointer; }
        
        .emp-name { font-weight: 600; color: var(--earth-900); }
        .emp-user { font-size: 0.8rem; color: var(--earth-800); opacity: 0.7; }
        .emp-cat { font-size: 0.85rem; color: var(--river-600); }
        
        .badge { padding: 0.25rem 0.6rem; border-radius: 100px; font-size: 0.75rem; font-weight: 600; }
        .badge-pending { background: #fef3c7; color: #92400e; }
        .badge-approved { background: #dcfce7; color: #166534; }
        .badge-rejected { background: #fee2e2; color: #991b1b; }
        
        .actions { display: flex; gap: 0.4rem; flex-wrap: wrap; }
        .btn-sm {
            padding: 0.35rem 0.6rem;
            border: none;
            border-radius: 6px;
            font-size: 0.75rem;
            cursor: pointer;
            font-family: inherit;
            white-space: nowrap;
        }
        .btn-approve { background: var(--green-600); color: white; }
        .btn-reject { background: var(--red-500); color: white; }
        .btn-star { background: var(--yellow-500); color: white; }
        .btn-unstar { background: var(--earth-200); color: var(--earth-800); }
        .btn-view { background: #8b5cf6; color: white; text-decoration: none; }
        .btn-edit { background: #3b82f6; color: white; text-decoration: none; }
        .btn-delete { background: #dc2626; color: white; }
        .btn-contact { background: #0891b2; color: white; }
        .periodo-select { padding: 0.25rem 0.4rem; border-radius: 4px; border: 1px solid #ddd; font-size: 0.7rem; }
        .badge-expira { font-size: 0.7rem; display: block; margin-top: 0.25rem; color: #666; }
        .badge-expirado { background: #fef3c7; color: #92400e; }
        
        .empty { padding: 3rem; text-align: center; color: var(--earth-800); }
        
        .results-info {
            font-size: 0.85rem;
            color: var(--earth-800);
            margin-bottom: 0.5rem;
        }
        
        /* Modal */
        .modal {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            z-index: 1000;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        .modal-backdrop {
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0,0,0,0.5);
        }
        .modal-content {
            position: relative;
            background: white;
            border-radius: 16px;
            padding: 2rem;
            width: 90%;
            max-width: 500px;
            box-shadow: 0 20px 60px rgba(0,0,0,0.3);
        }
        .modal-content h3 {
            margin: 0 0 0.5rem;
            color: #dc2626;
            font-size: 1.25rem;
        }
        .modal-emp-name {
            color: #666;
            margin: 0 0 1.5rem;
            padding-bottom: 1rem;
            border-bottom: 1px solid #eee;
        }
        .modal-content .form-group { margin-bottom: 1rem; }
        .modal-content label { 
            display: block; 
            margin-bottom: 0.5rem; 
            font-weight: 500;
            color: #333;
        }
        .modal-content textarea {
            width: 100%;
            padding: 0.75rem;
            border: 1px solid #ddd;
            border-radius: 8px;
            font-family: inherit;
            font-size: 0.95rem;
            resize: vertical;
        }
        .modal-content textarea:focus {
            outline: none;
            border-color: #1976d2;
        }
        .modal-content .hint {
            font-size: 0.8rem;
            color: #888;
            margin-top: 0.5rem;
        }
        .modal-actions {
            display: flex;
            gap: 1rem;
            margin-top: 1.5rem;
        }
        .btn-modal {
            flex: 1;
            padding: 0.75rem 1rem;
            border: none;
            border-radius: 8px;
            font-family: inherit;
            font-size: 0.95rem;
            font-weight: 600;
            cursor: pointer;
        }
        .btn-cancel {
            background: #f3f4f6;
            color: #374151;
        }
        .btn-confirm {
            background: #dc2626;
            color: white;
        }
        
        /* Config Panel */
        .config-panel {
            background: var(--white);
            border-radius: 12px;
            padding: 1rem 1.25rem;
            margin-bottom: 1.5rem;
            box-shadow: 0 2px 8px rgba(0,0,0,0.05);
            display: flex;
            align-items: center;
            gap: 1.5rem;
            flex-wrap: wrap;
        }
        .config-title {
            font-weight: 600;
            color: var(--earth-800);
            font-size: 0.9rem;
        }
        .config-toggles {
            display: flex;
            gap: 1rem;
            flex-wrap: wrap;
        }
        .toggle-form {
            display: inline-block;
        }
        .toggle-btn {
            display: flex;
            align-items: center;
            gap: 0.6rem;
            padding: 0.5rem 1rem;
            background: var(--earth-100);
            border: 1px solid var(--earth-200);
            border-radius: 8px;
            cursor: pointer;
            font-family: inherit;
            font-size: 0.85rem;
            color: var(--earth-800);
            transition: all 0.2s;
        }
        .toggle-btn:hover {
            border-color: var(--river-600);
        }
        .toggle-btn.active {
            background: #dcfce7;
            border-color: #16a34a;
            color: #166534;
        }
        .toggle-indicator {
            width: 36px;
            height: 20px;
            background: #ccc;
            border-radius: 10px;
            position: relative;
            transition: background 0.2s;
        }
        .toggle-indicator::after {
            content: '';
            position: absolute;
            width: 16px;
            height: 16px;
            background: white;
            border-radius: 50%;
            top: 2px;
            left: 2px;
            transition: transform 0.2s;
            box-shadow: 0 1px 3px rgba(0,0,0,0.2);
        }
        .toggle-btn.active .toggle-indicator {
            background: #16a34a;
        }
        .toggle-btn.active .toggle-indicator::after {
            transform: translateX(16px);
        }
        
        /* Duplicates Panel */
        .duplicates-panel {
            background: #fef3c7;
            border: 1px solid #f59e0b;
            border-radius: 12px;
            margin-bottom: 1.5rem;
            overflow: hidden;
        }
        .dup-header {
            display: flex;
            align-items: center;
            gap: 1rem;
            padding: 1rem 1.25rem;
        }
        .dup-icon {
            font-size: 1.5rem;
        }
        .dup-header h3 {
            margin: 0;
            font-size: 1rem;
            color: #92400e;
        }
        .dup-header p {
            margin: 0.25rem 0 0;
            font-size: 0.85rem;
            color: #a16207;
        }
        .dup-toggle {
            margin-left: auto;
            padding: 0.5rem 1rem;
            background: #f59e0b;
            color: white;
            border: none;
            border-radius: 6px;
            cursor: pointer;
            font-family: inherit;
            font-size: 0.85rem;
            font-weight: 500;
        }
        .dup-details {
            background: white;
            border-top: 1px solid #fcd34d;
            padding: 1rem 1.25rem;
        }
        .dup-section {
            margin-bottom: 1rem;
        }
        .dup-section:last-child {
            margin-bottom: 0;
        }
        .dup-section h4 {
            font-size: 0.9rem;
            color: #78350f;
            margin: 0 0 0.5rem;
        }
        .dup-item {
            background: #fffbeb;
            padding: 0.75rem 1rem;
            border-radius: 8px;
            margin-bottom: 0.5rem;
            font-size: 0.9rem;
            color: #78350f;
        }
        .dup-item:last-child {
            margin-bottom: 0;
        }
        .dup-ids {
            display: inline-block;
            background: #fef3c7;
            padding: 0.2rem 0.5rem;
            border-radius: 4px;
            font-size: 0.75rem;
            margin-left: 0.5rem;
        }
        .dup-link {
            color: #d97706;
            text-decoration: none;
            font-weight: 500;
            margin-left: 0.5rem;
        }
        .dup-link:hover {
            text-decoration: underline;
        }
        .dup-actions {
            margin-top: 0.5rem;
        }
        .no-duplicates {
            background: #dcfce7;
            border: 1px solid #86efac;
            color: #166534;
            padding: 0.75rem 1.25rem;
            border-radius: 12px;
            margin-bottom: 1.5rem;
            font-size: 0.9rem;
        }
        .no-duplicates span {
            margin-right: 0.5rem;
        }
        .dup-item-header {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            gap: 1rem;
        }
        .dup-dismiss-form {
            flex-shrink: 0;
        }
        .dup-dismiss-btn {
            padding: 0.3rem 0.6rem;
            background: #16a34a;
            color: white;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            font-size: 0.75rem;
            font-weight: 500;
        }
        .dup-dismiss-btn:hover {
            background: #15803d;
        }
        .dup-footer {
            padding: 0.75rem 1rem;
            border-top: 1px solid #fcd34d;
            background: #fffbeb;
            display: flex;
            justify-content: space-between;
            align-items: center;
            font-size: 0.85rem;
        }
        .dup-restore-btn {
            padding: 0.4rem 0.8rem;
            background: transparent;
            border: 1px solid #d97706;
            color: #d97706;
            border-radius: 6px;
            cursor: pointer;
            font-size: 0.8rem;
        }
        .dup-restore-btn:hover {
            background: #fef3c7;
        }
        
        @media (max-width: 768px) {
            .toolbar-row { flex-direction: column; align-items: stretch; }
            .search-box { min-width: 100%; }
            .bulk-actions { flex-direction: column; text-align: center; }
            .bulk-buttons { margin-left: 0; justify-content: center; flex-wrap: wrap; }
            td { font-size: 0.85rem; padding: 0.5rem; }
            .actions { flex-direction: column; }
            .config-panel { flex-direction: column; align-items: flex-start; }
        }
    </style>
</head>
<body>
    <header class="header">
        <div class="header-inner">
            <a href="<?= BASE_URL ?>/admin/" class="logo">🌊 Admin — Mi Valdivia Online</a>
            <a href="<?= BASE_URL ?>">← Ver sitio</a>
        </div>
    </header>
    
    <div class="container">
        <h1>Panel de Administración</h1>
        
        <?php if ($success): ?>
            <div class="success"><?= e($success) ?></div>
        <?php endif; ?>
        
        <div class="stats">
            <div class="stat">
                <div class="stat-value"><?= $stats['total'] ?></div>
                <div class="stat-label">Total emprendimientos</div>
            </div>
            <div class="stat pending">
                <div class="stat-value"><?= $stats['pendientes'] ?></div>
                <div class="stat-label">Pendientes</div>
            </div>
            <div class="stat">
                <div class="stat-value"><?= $stats['aprobados'] ?></div>
                <div class="stat-label">Publicados</div>
            </div>
            <div class="stat">
                <div class="stat-value"><?= $stats['rechazados'] ?></div>
                <div class="stat-label">Rechazados</div>
            </div>
        </div>
        
        <!-- Panel de Duplicados -->
        <?php if ($totalDuplicados > 0): ?>
        <div class="duplicates-panel">
            <div class="dup-header">
                <span class="dup-icon">⚠️</span>
                <div>
                    <h3>Posibles Duplicados Detectados</h3>
                    <p><?= $totalDuplicados ?> coincidencia(s) encontrada(s)</p>
                </div>
                <button type="button" class="dup-toggle" onclick="toggleDuplicates()">Ver detalles</button>
            </div>
            <div id="duplicatesDetail" class="dup-details" style="display: none;">
                <?php if (!empty($duplicados['nombre'])): ?>
                <div class="dup-section">
                    <h4>🔤 Nombres duplicados</h4>
                    <?php foreach ($duplicados['nombre'] as $dup): ?>
                    <div class="dup-item">
                        <div class="dup-item-header">
                            <div><strong><?= e($dup['nombre']) ?></strong> — <?= $dup['cantidad'] ?> registros</div>
                            <form method="POST" class="dup-dismiss-form">
                                <input type="hidden" name="descartar_duplicado" value="1">
                                <input type="hidden" name="tipo" value="nombre">
                                <input type="hidden" name="valor" value="<?= e($dup['nombre_lower']) ?>">
                                <input type="hidden" name="ids" value="<?= e($dup['ids']) ?>">
                                <button type="submit" class="dup-dismiss-btn" title="No es duplicado">✓ OK</button>
                            </form>
                        </div>
                        <div class="dup-actions">
                            <?php foreach (explode(',', $dup['ids']) as $dupId): ?>
                            <a href="<?= BASE_URL ?>/admin/ver.php?id=<?= trim($dupId) ?>" class="dup-link">Ver #<?= trim($dupId) ?></a>
                            <?php endforeach; ?>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
                <?php endif; ?>
                
                <?php if (!empty($duplicados['telefono'])): ?>
                <div class="dup-section">
                    <h4>📞 Mismo teléfono</h4>
                    <?php foreach ($duplicados['telefono'] as $dup): ?>
                    <div class="dup-item">
                        <div class="dup-item-header">
                            <div>
                                <strong><?= e($dup['telefono']) ?></strong> — <?= $dup['cantidad'] ?> registros
                                <br><small><?= e($dup['nombres']) ?></small>
                            </div>
                            <form method="POST" class="dup-dismiss-form">
                                <input type="hidden" name="descartar_duplicado" value="1">
                                <input type="hidden" name="tipo" value="telefono">
                                <input type="hidden" name="valor" value="<?= e($dup['telefono']) ?>">
                                <input type="hidden" name="ids" value="<?= e($dup['ids']) ?>">
                                <button type="submit" class="dup-dismiss-btn" title="No es duplicado">✓ OK</button>
                            </form>
                        </div>
                        <div class="dup-actions">
                            <?php foreach (explode(',', $dup['ids']) as $dupId): ?>
                            <a href="<?= BASE_URL ?>/admin/ver.php?id=<?= trim($dupId) ?>" class="dup-link">Ver #<?= trim($dupId) ?></a>
                            <?php endforeach; ?>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
                <?php endif; ?>
                
                <?php if (!empty($duplicados['email'])): ?>
                <div class="dup-section">
                    <h4>📧 Mismo email</h4>
                    <?php foreach ($duplicados['email'] as $dup): ?>
                    <div class="dup-item">
                        <div class="dup-item-header">
                            <div>
                                <strong><?= e($dup['email']) ?></strong> — <?= $dup['cantidad'] ?> registros
                                <br><small><?= e($dup['nombres']) ?></small>
                            </div>
                            <form method="POST" class="dup-dismiss-form">
                                <input type="hidden" name="descartar_duplicado" value="1">
                                <input type="hidden" name="tipo" value="email">
                                <input type="hidden" name="valor" value="<?= e($dup['email_lower']) ?>">
                                <input type="hidden" name="ids" value="<?= e($dup['ids']) ?>">
                                <button type="submit" class="dup-dismiss-btn" title="No es duplicado">✓ OK</button>
                            </form>
                        </div>
                        <div class="dup-actions">
                            <?php foreach (explode(',', $dup['ids']) as $dupId): ?>
                            <a href="<?= BASE_URL ?>/admin/ver.php?id=<?= trim($dupId) ?>" class="dup-link">Ver #<?= trim($dupId) ?></a>
                            <?php endforeach; ?>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
                <?php endif; ?>
                
                <?php if (!empty($duplicados['similares'])): ?>
                <div class="dup-section">
                    <h4>🔍 Nombres similares</h4>
                    <?php foreach ($duplicados['similares'] as $dup): ?>
                    <div class="dup-item">
                        <div class="dup-item-header">
                            <div>
                                <strong><?= e($dup['nombre1']) ?></strong> (ID: <?= $dup['id1'] ?>) 
                                ↔ 
                                <strong><?= e($dup['nombre2']) ?></strong> (ID: <?= $dup['id2'] ?>)
                            </div>
                            <form method="POST" class="dup-dismiss-form">
                                <input type="hidden" name="descartar_duplicado" value="1">
                                <input type="hidden" name="tipo" value="similar">
                                <input type="hidden" name="valor" value="<?= e($dup['par_ids']) ?>">
                                <input type="hidden" name="ids" value="<?= $dup['id1'] ?>,<?= $dup['id2'] ?>">
                                <button type="submit" class="dup-dismiss-btn" title="No es duplicado">✓ OK</button>
                            </form>
                        </div>
                        <div class="dup-actions">
                            <a href="<?= BASE_URL ?>/admin/ver.php?id=<?= $dup['id1'] ?>" class="dup-link">Ver #<?= $dup['id1'] ?></a>
                            <a href="<?= BASE_URL ?>/admin/ver.php?id=<?= $dup['id2'] ?>" class="dup-link">Ver #<?= $dup['id2'] ?></a>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
                <?php endif; ?>
            </div>
            <?php if ($totalDescartados > 0): ?>
            <div class="dup-footer">
                <span><?= $totalDescartados ?> duplicado(s) descartado(s) anteriormente</span>
                <form method="POST" style="margin: 0;">
                    <input type="hidden" name="restaurar_duplicados" value="1">
                    <button type="submit" class="dup-restore-btn">Restaurar todos</button>
                </form>
            </div>
            <?php endif; ?>
        </div>
        <?php elseif ($totalDescartados > 0): ?>
        <div class="no-duplicates" style="display: flex; justify-content: space-between; align-items: center;">
            <span><span>✅</span> Sin duplicados detectados (<?= $totalDescartados ?> descartado(s))</span>
            <form method="POST" style="margin: 0;">
                <input type="hidden" name="restaurar_duplicados" value="1">
                <button type="submit" class="dup-restore-btn" style="border-color: #16a34a; color: #16a34a;">Restaurar</button>
            </form>
        </div>
        <?php else: ?>
        <div class="no-duplicates">
            <span>✅</span> Sin duplicados detectados
        </div>
        <?php endif; ?>
        
        <!-- Configuración rápida -->
        <div class="config-panel">
            <span class="config-title">⚙️ Configuración</span>
            <div class="config-toggles">
                <form method="POST" class="toggle-form">
                    <input type="hidden" name="toggle_config" value="banner_destacados">
                    <button type="submit" class="toggle-btn <?= ($config['banner_destacados'] ?? '0') === '1' ? 'active' : '' ?>">
                        <span class="toggle-indicator"></span>
                        <span class="toggle-label">Banner "Destacar tu emprendimiento"</span>
                    </button>
                </form>
                <form method="POST" class="toggle-form">
                    <input type="hidden" name="toggle_config" value="api_importacion">
                    <button type="submit" class="toggle-btn <?= ($config['api_importacion'] ?? '0') === '1' ? 'active' : '' ?>">
                        <span class="toggle-indicator"></span>
                        <span class="toggle-label">🔌 API de Importación</span>
                    </button>
                </form>
            </div>
        </div>
        
        <!-- Toolbar -->
        <div class="toolbar">
            <div class="toolbar-row">
                <!-- Búsqueda -->
                <form class="search-box" method="GET">
                    <input type="hidden" name="estado" value="<?= e($filtro) ?>">
                    <input type="hidden" name="categoria" value="<?= e($categoriaFiltro) ?>">
                    <input type="hidden" name="orden" value="<?= e($orden) ?>">
                    <input type="text" name="q" value="<?= e($busqueda) ?>" placeholder="Buscar emprendimiento...">
                    <button type="submit">🔍 Buscar</button>
                </form>
                
                <!-- Filtro categoría -->
                <div class="filter-group">
                    <label>Categoría:</label>
                    <select onchange="aplicarFiltro('categoria', this.value)">
                        <option value="">Todas</option>
                        <?php foreach ($categorias as $cat): ?>
                            <option value="<?= $cat['id'] ?>" <?= $categoriaFiltro == $cat['id'] ? 'selected' : '' ?>>
                                <?= $cat['icono'] ?> <?= e($cat['nombre']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <!-- Ordenar -->
                <div class="filter-group">
                    <label>Ordenar:</label>
                    <select onchange="aplicarFiltro('orden', this.value)">
                        <option value="fecha" <?= $orden === 'fecha' ? 'selected' : '' ?>>Más recientes</option>
                        <option value="nombre" <?= $orden === 'nombre' ? 'selected' : '' ?>>Nombre A-Z</option>
                        <option value="categoria" <?= $orden === 'categoria' ? 'selected' : '' ?>>Por categoría</option>
                        <option value="visitas" <?= $orden === 'visitas' ? 'selected' : '' ?>>Más visitados</option>
                    </select>
                </div>
            </div>
            
            <div class="toolbar-row">
                <!-- Filtros de estado -->
                <div class="filters">
                    <a href="?estado=pendiente<?= $categoriaFiltro ? '&categoria='.$categoriaFiltro : '' ?><?= $busqueda ? '&q='.urlencode($busqueda) : '' ?>" class="filter-btn <?= $filtro === 'pendiente' ? 'active' : '' ?>">⏳ Pendientes</a>
                    <a href="?estado=aprobado<?= $categoriaFiltro ? '&categoria='.$categoriaFiltro : '' ?><?= $busqueda ? '&q='.urlencode($busqueda) : '' ?>" class="filter-btn <?= $filtro === 'aprobado' ? 'active' : '' ?>">✅ Aprobados</a>
                    <a href="?estado=rechazado<?= $categoriaFiltro ? '&categoria='.$categoriaFiltro : '' ?><?= $busqueda ? '&q='.urlencode($busqueda) : '' ?>" class="filter-btn <?= $filtro === 'rechazado' ? 'active' : '' ?>">❌ Rechazados</a>
                    <a href="?estado=todos<?= $categoriaFiltro ? '&categoria='.$categoriaFiltro : '' ?><?= $busqueda ? '&q='.urlencode($busqueda) : '' ?>" class="filter-btn <?= $filtro === 'todos' ? 'active' : '' ?>">📋 Todos</a>
                </div>
            </div>
        </div>
        
        <!-- Barra de acciones masivas -->
        <form id="bulkForm" method="POST">
            <div id="bulkActions" class="bulk-actions">
                <span class="bulk-count"><span id="selectedCount">0</span> seleccionados</span>
                <div class="bulk-buttons">
                    <button type="submit" name="accion_masiva" value="aprobar_masivo" class="bulk-btn bulk-btn-approve">✓ Aprobar (1 año)</button>
                    <button type="submit" name="accion_masiva" value="rechazar_masivo" class="bulk-btn bulk-btn-reject">✗ Rechazar</button>
                    <button type="submit" name="accion_masiva" value="destacar_masivo" class="bulk-btn bulk-btn-star">⭐ Destacar</button>
                    <button type="submit" name="accion_masiva" value="quitar_destacado_masivo" class="bulk-btn bulk-btn-unstar">Quitar ⭐</button>
                    <button type="button" onclick="deselectAll()" class="bulk-btn bulk-btn-cancel">Cancelar</button>
                </div>
            </div>
        
            <p class="results-info">
                Mostrando <strong><?= count($emprendimientos) ?></strong> emprendimientos
                <?php if ($busqueda): ?> para "<?= e($busqueda) ?>"<?php endif; ?>
            </p>
            
            <div class="table-wrap">
                <?php if (empty($emprendimientos)): ?>
                    <div class="empty">No hay emprendimientos con estos filtros.</div>
                <?php else: ?>
                    <table>
                        <thead>
                            <tr>
                                <th class="checkbox-cell">
                                    <input type="checkbox" id="selectAll" onchange="toggleSelectAll()">
                                </th>
                                <th>Emprendimiento</th>
                                <th>Categoría</th>
                                <th>Estado</th>
                                <th>Visitas</th>
                                <th>Fecha</th>
                                <th>Acciones</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($emprendimientos as $emp): ?>
                                <tr>
                                    <td class="checkbox-cell">
                                        <input type="checkbox" name="ids[]" value="<?= $emp['id'] ?>" class="row-checkbox" onchange="updateBulkBar()">
                                    </td>
                                    <td>
                                        <div class="emp-name"><?= e($emp['nombre']) ?></div>
                                        <div class="emp-user"><?= e($emp['usuario_email']) ?></div>
                                    </td>
                                    <td class="emp-cat"><?= $emp['categoria_icono'] ?> <?= e($emp['categoria_nombre']) ?></td>
                                    <td>
                                        <?php if ($emp['estado'] === 'pendiente'): ?>
                                            <span class="badge badge-pending">Pendiente</span>
                                        <?php elseif ($emp['estado'] === 'aprobado'): ?>
                                            <span class="badge badge-approved">Aprobado</span>
                                            <?php if ($emp['aprobado_hasta']): ?>
                                                <span class="badge-expira">hasta <?= date('d/m/Y', strtotime($emp['aprobado_hasta'])) ?></span>
                                            <?php endif; ?>
                                        <?php elseif ($emp['estado'] === 'expirado'): ?>
                                            <span class="badge badge-expirado">Expirado</span>
                                        <?php else: ?>
                                            <span class="badge badge-rejected">Rechazado</span>
                                        <?php endif; ?>
                                        <?php if ($emp['destacado']): ?>
                                            <span class="badge" style="background: #fef3c7;">⭐</span>
                                        <?php endif; ?>
                                    </td>
                                    <td style="text-align: center; font-size: 0.85rem; color: var(--earth-800);">
                                        <?= number_format($emp['visitas']) ?>
                                    </td>
                                    <td style="font-size: 0.85rem; color: var(--earth-800);">
                                        <?= date('d/m/Y', strtotime($emp['created_at'])) ?>
                                    </td>
                                    <td>
                                        <div class="actions">
                                            <a href="<?= BASE_URL ?>/admin/ver.php?id=<?= $emp['id'] ?>" class="btn-sm btn-view">👁️</a>
                                            <a href="<?= BASE_URL ?>/admin/editar.php?id=<?= $emp['id'] ?>" class="btn-sm btn-edit">✏️</a>
                                            <button type="button" class="btn-sm btn-contact" onclick="abrirContactar(<?= $emp['id'] ?>, '<?= e(addslashes($emp['nombre'])) ?>', '<?= e($emp['usuario_email']) ?>')">✉️</button>
                                            <button type="button" class="btn-sm btn-delete" onclick="confirmarEliminar(<?= $emp['id'] ?>, '<?= e(addslashes($emp['nombre'])) ?>')">🗑️</button>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php endif; ?>
            </div>
        </form>
    </div>
    
    <!-- Modal de Rechazo -->
    <div id="modalRechazo" class="modal" style="display: none;">
        <div class="modal-backdrop" onclick="cerrarModalRechazo()"></div>
        <div class="modal-content">
            <h3>✗ Rechazar Emprendimiento</h3>
            <p class="modal-emp-name" id="modalEmpNombre"></p>
            
            <form method="POST" id="formRechazo">
                <input type="hidden" name="id" id="modalEmpId">
                <input type="hidden" name="accion" value="rechazar">
                
                <div class="form-group">
                    <label>Motivo del rechazo <span style="color: #dc2626;">*</span></label>
                    <textarea name="razon_rechazo" id="razonRechazo" rows="4" placeholder="Explica al propietario qué debe corregir..." required></textarea>
                    <p class="hint">Este mensaje se enviará por email al propietario.</p>
                </div>
                
                <div class="modal-actions">
                    <button type="button" class="btn-modal btn-cancel" onclick="cerrarModalRechazo()">Cancelar</button>
                    <button type="submit" class="btn-modal btn-confirm">Rechazar y notificar</button>
                </div>
            </form>
        </div>
    </div>
    
    <script>
        function toggleDuplicates() {
            const detail = document.getElementById('duplicatesDetail');
            const btn = document.querySelector('.dup-toggle');
            if (detail.style.display === 'none') {
                detail.style.display = 'block';
                btn.textContent = 'Ocultar';
            } else {
                detail.style.display = 'none';
                btn.textContent = 'Ver detalles';
            }
        }
        
        function aplicarFiltro(param, value) {
            const url = new URL(window.location);
            if (value) {
                url.searchParams.set(param, value);
            } else {
                url.searchParams.delete(param);
            }
            window.location = url;
        }
        
        function toggleSelectAll() {
            const checked = document.getElementById('selectAll').checked;
            document.querySelectorAll('.row-checkbox').forEach(cb => cb.checked = checked);
            updateBulkBar();
        }
        
        function updateBulkBar() {
            const checked = document.querySelectorAll('.row-checkbox:checked');
            const bulkBar = document.getElementById('bulkActions');
            const count = document.getElementById('selectedCount');
            
            count.textContent = checked.length;
            
            if (checked.length > 0) {
                bulkBar.classList.add('active');
            } else {
                bulkBar.classList.remove('active');
            }
        }
        
        function deselectAll() {
            document.getElementById('selectAll').checked = false;
            document.querySelectorAll('.row-checkbox').forEach(cb => cb.checked = false);
            updateBulkBar();
        }
        
        function abrirModalRechazo(id, nombre) {
            document.getElementById('modalEmpId').value = id;
            document.getElementById('modalEmpNombre').textContent = nombre;
            document.getElementById('razonRechazo').value = '';
            document.getElementById('modalRechazo').style.display = 'flex';
        }
        
        function cerrarModalRechazo() {
            document.getElementById('modalRechazo').style.display = 'none';
        }
        
        function confirmarEliminar(id, nombre) {
            document.getElementById('deleteEmpId').value = id;
            document.getElementById('deleteEmpNombre').textContent = nombre;
            document.getElementById('modalEliminar').style.display = 'flex';
        }
        
        function cerrarModalEliminar() {
            document.getElementById('modalEliminar').style.display = 'none';
        }
        
        function abrirContactar(id, nombre, email) {
            document.getElementById('contactEmpId').value = id;
            document.getElementById('contactEmpNombre').textContent = nombre;
            document.getElementById('contactEmpEmail').textContent = email;
            document.getElementById('contactMensaje').value = '';
            document.getElementById('modalContactar').style.display = 'flex';
        }
        
        function cerrarModalContactar() {
            document.getElementById('modalContactar').style.display = 'none';
        }
        
        document.addEventListener('keydown', function(e) {
            if (e.key === 'Escape') {
                cerrarModalRechazo();
                cerrarModalEliminar();
                cerrarModalContactar();
            }
        });
    </script>
    
    <!-- Modal de Eliminación -->
    <div id="modalEliminar" class="modal" style="display: none;">
        <div class="modal-backdrop" onclick="cerrarModalEliminar()"></div>
        <div class="modal-content">
            <h3 style="color: #dc2626;">🗑️ Eliminar Emprendimiento</h3>
            <p class="modal-emp-name" id="deleteEmpNombre"></p>
            
            <div style="background: #fef2f2; border: 1px solid #fecaca; border-radius: 8px; padding: 1rem; margin: 1rem 0;">
                <p style="color: #991b1b; font-size: 0.9rem; margin: 0;">
                    <strong>⚠️ Esta acción es permanente.</strong><br>
                    El emprendimiento será eliminado completamente y no se podrá recuperar.
                </p>
            </div>
            
            <form method="POST" id="formEliminar">
                <input type="hidden" name="id" id="deleteEmpId">
                <input type="hidden" name="accion" value="eliminar">
                
                <div class="modal-actions">
                    <button type="button" class="btn-modal btn-cancel" onclick="cerrarModalEliminar()">Cancelar</button>
                    <button type="submit" class="btn-modal btn-confirm">Eliminar permanentemente</button>
                </div>
            </form>
        </div>
    </div>
    
    <!-- Modal de Contactar -->
    <div id="modalContactar" class="modal" style="display: none;">
        <div class="modal-backdrop" onclick="cerrarModalContactar()"></div>
        <div class="modal-content">
            <h3 style="color: #0891b2;">✉️ Contactar Emprendimiento</h3>
            <p class="modal-emp-name" id="contactEmpNombre"></p>
            <p style="color: #666; font-size: 0.85rem; margin-top: -0.5rem;">📧 <span id="contactEmpEmail"></span></p>
            
            <form method="POST" id="formContactar">
                <input type="hidden" name="id" id="contactEmpId">
                <input type="hidden" name="accion" value="contactar">
                
                <div class="form-group" style="margin: 1rem 0;">
                    <label style="display: block; margin-bottom: 0.5rem; font-weight: 500; color: #333;">Mensaje</label>
                    <textarea name="mensaje" id="contactMensaje" rows="5" placeholder="Escribe tu mensaje aquí... Por ejemplo, sugerencias de mejora para su perfil, información faltante, etc." required style="width: 100%; padding: 0.75rem; border: 1px solid #ddd; border-radius: 8px; font-family: inherit; font-size: 0.95rem; resize: vertical;"></textarea>
                    <p style="font-size: 0.8rem; color: #888; margin-top: 0.5rem;">Este mensaje se enviará por email con el formato de Mi Valdivia Online.</p>
                </div>
                
                <div class="modal-actions">
                    <button type="button" class="btn-modal btn-cancel" onclick="cerrarModalContactar()">Cancelar</button>
                    <button type="submit" class="btn-modal" style="background: #0891b2; color: white;">Enviar mensaje</button>
                </div>
            </form>
        </div>
    </div>
</body>
</html>
