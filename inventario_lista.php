<?php
// Archivo: inventario_lista.php
session_start();
include 'conexion.php';
include 'funciones_permisos.php';

if (!isset($_SESSION['usuario_id']) || !tiene_permiso('acceso_inventario', $pdo)) {
    header("Location: dashboard.php"); exit();
}

// --- LOGICA AUTOMATICA DE VENCIMIENTOS ---
// Si la fecha de carga venció (asumimos 1 año) o PH venció, cambiar estado.
// Simplificado: Si la fecha guardada en BD es MENOR a HOY, significa que la fecha "de vencimiento" ya pasó? 
// NOTA: Generalmente se guarda la fecha REALIZADA. Vence al año.
// Logica aplicada: Fecha_Realizada + 1 Año < Hoy => Vencido.

try {
    // 1. Detectar Cargas Vencidas (Fecha Carga + 1 año < Hoy)
    $sql_venc_carga = "UPDATE inventario_cargos 
                       SET id_estado_fk = (SELECT id_estado FROM inventario_estados WHERE nombre='Carga Vencida' LIMIT 1)
                       WHERE mat_tipo_carga_id IS NOT NULL 
                       AND mat_fecha_carga IS NOT NULL 
                       AND DATE_ADD(mat_fecha_carga, INTERVAL 1 YEAR) < CURDATE()
                       AND id_estado_fk != (SELECT id_estado FROM inventario_estados WHERE nombre='Para Baja' LIMIT 1)";
    $pdo->exec($sql_venc_carga);

    // 2. Detectar PH Vencidas (Fecha PH + 1 año < Hoy? O 5? Usaremos 1 año para alerta por defecto si no se configura)
    // Para simplificar, asumimos que si la fecha ingresada ya pasó hace un año, alerta.
    $sql_venc_ph = "UPDATE inventario_cargos 
                    SET id_estado_fk = (SELECT id_estado FROM inventario_estados WHERE nombre='Prueba Vencida' LIMIT 1)
                    WHERE mat_tipo_carga_id IS NOT NULL 
                    AND mat_fecha_ph IS NOT NULL 
                    AND DATE_ADD(mat_fecha_ph, INTERVAL 1 YEAR) < CURDATE()
                    AND id_estado_fk != (SELECT id_estado FROM inventario_estados WHERE nombre='Para Baja' LIMIT 1)";
    $pdo->exec($sql_venc_ph);

} catch (Exception $e) { 
    // Silencio en prod, log error
}

// --- FILTROS Y CONSULTA (Código Existente Adaptado) ---
$where = "1=1";
$params = [];

$busqueda = isset($_GET['q']) ? trim($_GET['q']) : '';
if (!empty($busqueda)) {
    $where .= " AND (elemento LIKE :q OR codigo_inventario LIKE :q OR nombre_responsable LIKE :q)";
    $params[':q'] = "%$busqueda%";
}
$ubicacion_filtro = isset($_GET['ubicacion']) ? $_GET['ubicacion'] : '';
if (!empty($ubicacion_filtro)) { $where .= " AND servicio_ubicacion = :serv"; $params[':serv'] = $ubicacion_filtro; }
$estado_filtro = isset($_GET['estado']) ? $_GET['estado'] : '';
if (!empty($estado_filtro)) { $where .= " AND e.nombre = :estado"; $params[':estado'] = $estado_filtro; }

// Consulta Principal con JOINs
$sql = "SELECT i.*, e.nombre as nombre_estado, e.color_badge 
        FROM inventario_cargos i 
        LEFT JOIN inventario_estados e ON i.id_estado_fk = e.id_estado 
        WHERE $where ORDER BY i.fecha_creacion DESC LIMIT 1000";
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$inventario = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Listas para filtros
$lista_ubicaciones = $pdo->query("SELECT DISTINCT servicio_ubicacion FROM inventario_cargos ORDER BY servicio_ubicacion")->fetchAll(PDO::FETCH_COLUMN);
$lista_estados = $pdo->query("SELECT DISTINCT nombre FROM inventario_estados")->fetchAll(PDO::FETCH_COLUMN);

// KPIs
function getCount($pdo, $condicion) { 
    return $pdo->query("SELECT COUNT(*) FROM inventario_cargos i LEFT JOIN inventario_estados e ON i.id_estado_fk=e.id_estado WHERE $condicion")->fetchColumn(); 
}
$total_activos = getCount($pdo, "e.nombre = 'Activo'");
$total_baja = getCount($pdo, "e.nombre = 'Para Baja'");
$total_vencidos = getCount($pdo, "e.nombre LIKE '%Vencida%'");
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Inventario | Logística</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="https://cdn.datatables.net/1.13.4/css/dataTables.bootstrap5.min.css">
</head>
<body class="bg-light">
    <?php include 'navbar.php'; ?>

    <div class="container mt-4 mb-5">
        
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h2 class="fw-bold m-0"><i class="fas fa-boxes text-primary"></i> Control de Inventario</h2>
            <div class="d-flex gap-2">
                <a href="inventario_reporte_pdf.php" target="_blank" class="btn btn-danger"><i class="fas fa-file-pdf me-2"></i> Reporte</a>
                <a href="inventario_mantenimiento.php" class="btn btn-warning fw-bold"><i class="fas fa-tools me-2"></i> Mantenimiento/Servicio</a>
                <a href="inventario_nuevo.php" class="btn btn-primary"><i class="fas fa-plus-circle me-2"></i> Nuevo Bien</a>
            </div>
        </div>

        <div class="row g-3 mb-4">
            <div class="col-md-4">
                <div class="card bg-success text-white shadow-sm h-100">
                    <div class="card-body d-flex justify-content-between align-items-center">
                        <div><div class="h3 fw-bold mb-0"><?php echo $total_activos; ?></div><small>Activos</small></div>
                        <i class="fas fa-check-circle fs-1 opacity-50"></i>
                    </div>
                </div>
            </div>
            <div class="col-md-4">
                <div class="card bg-danger text-white shadow-sm h-100">
                    <div class="card-body d-flex justify-content-between align-items-center">
                        <div><div class="h3 fw-bold mb-0"><?php echo $total_vencidos; ?></div><small>Vencidos (Carga/PH)</small></div>
                        <i class="fas fa-exclamation-triangle fs-1 opacity-50"></i>
                    </div>
                </div>
            </div>
            <div class="col-md-4">
                <div class="card bg-dark text-white shadow-sm h-100">
                    <div class="card-body d-flex justify-content-between align-items-center">
                        <div><div class="h3 fw-bold mb-0"><?php echo $total_baja; ?></div><small>Para Baja</small></div>
                        <i class="fas fa-trash fs-1 opacity-50"></i>
                    </div>
                </div>
            </div>
        </div>

        <div class="card shadow border-0">
            <div class="card-header bg-white py-3">
                <form method="GET" class="row g-2">
                    <div class="col-md-4">
                        <input type="text" name="q" class="form-control form-control-sm" placeholder="Buscar..." value="<?php echo htmlspecialchars($busqueda); ?>">
                    </div>
                    <div class="col-md-3">
                        <select name="ubicacion" class="form-select form-select-sm">
                            <option value="">-- Ubicación --</option>
                            <?php foreach($lista_ubicaciones as $u) echo "<option value='$u' ".($ubicacion_filtro==$u?'selected':'').">$u</option>"; ?>
                        </select>
                    </div>
                    <div class="col-md-3">
                        <select name="estado" class="form-select form-select-sm">
                            <option value="">-- Estado --</option>
                            <?php foreach($lista_estados as $e) echo "<option value='$e' ".($estado_filtro==$e?'selected':'').">$e</option>"; ?>
                        </select>
                    </div>
                    <div class="col-md-2 d-grid"><button class="btn btn-dark btn-sm">Filtrar</button></div>
                </form>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table id="tablaInv" class="table table-hover align-middle mb-0">
                        <thead class="bg-light">
                            <tr>
                                <th>Estado</th>
                                <th>Elemento</th>
                                <th>Vencimientos (Si aplica)</th>
                                <th>Ubicación</th>
                                <th class="text-center">Acciones</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach($inventario as $i): ?>
                                <tr>
                                    <td>
                                        <span class="badge <?php echo $i['color_badge'] ?? 'bg-secondary'; ?>">
                                            <?php echo htmlspecialchars($i['nombre_estado'] ?? 'Sin Asignar'); ?>
                                        </span>
                                    </td>
                                    <td>
                                        <div class="fw-bold"><?php echo htmlspecialchars($i['elemento']); ?></div>
                                        <small class="text-muted"><?php echo htmlspecialchars($i['codigo_inventario']); ?></small>
                                    </td>
                                    <td>
                                        <?php if($i['mat_tipo_carga_id']): ?>
                                            <?php 
                                                // Calcular visualmente
                                                $hoy = new DateTime();
                                                $vtoCarga = $i['mat_fecha_carga'] ? (new DateTime($i['mat_fecha_carga']))->modify('+1 year') : null;
                                                $vtoPH = $i['mat_fecha_ph'] ? (new DateTime($i['mat_fecha_ph']))->modify('+1 year') : null; // Ajustar lógica años si es necesario
                                                
                                                if($vtoCarga) {
                                                    $color = $vtoCarga < $hoy ? 'text-danger fw-bold' : 'text-success';
                                                    echo "<div class='$color small'>Carga: ".$vtoCarga->format('d/m/Y')."</div>";
                                                }
                                                if($vtoPH) {
                                                    $color = $vtoPH < $hoy ? 'text-danger fw-bold' : 'text-success';
                                                    echo "<div class='$color small'>PH: ".$vtoPH->format('d/m/Y')."</div>";
                                                }
                                            ?>
                                        <?php else: ?>
                                            <span class="text-muted small">-</span>
                                        <?php endif; ?>
                                    </td>
                                    <td><?php echo htmlspecialchars($i['servicio_ubicacion']); ?></td>
                                    <td class="text-center">
                                        <div class="dropdown">
                                            <button class="btn btn-sm btn-light border dropdown-toggle" data-bs-toggle="dropdown"><i class="fas fa-ellipsis-v"></i></button>
                                            <ul class="dropdown-menu dropdown-menu-end shadow">
                                                <li><a class="dropdown-item" href="inventario_pdf.php?id=<?php echo $i['id_cargo']; ?>" target="_blank">Ver PDF</a></li>
                                                <li><a class="dropdown-item" href="inventario_editar.php?id=<?php echo $i['id_cargo']; ?>">Editar</a></li>
                                                <?php if($i['mat_tipo_carga_id']): ?>
                                                    <li><hr class="dropdown-divider"></li>
                                                    <li><a class="dropdown-item text-primary" href="inventario_mantenimiento.php?buscar_codigo=<?php echo $i['codigo_inventario']; ?>">Registrar Servicio</a></li>
                                                <?php endif; ?>
                                            </ul>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
    
    <script src="https://code.jquery.com/jquery-3.7.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.datatables.net/1.13.4/js/jquery.dataTables.min.js"></script>
    <script src="https://cdn.datatables.net/1.13.4/js/dataTables.bootstrap5.min.js"></script>
    <script>$(document).ready(function(){ $('#tablaInv').DataTable({language:{url:'//cdn.datatables.net/plug-ins/1.13.4/i18n/es-ES.json'}, order:[[0,'asc']]}); });</script>
</body>
</html>