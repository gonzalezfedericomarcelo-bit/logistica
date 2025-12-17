<?php
// Archivo: inventario_lista.php
session_start();
include 'conexion.php';
include 'funciones_permisos.php';

if (!isset($_SESSION['usuario_id']) || !tiene_permiso('acceso_inventario', $pdo)) {
    header("Location: dashboard.php"); exit();
}

// --- 1. LÓGICA AUTOMÁTICA DE VENCIMIENTOS ---
try {
    // A. Carga Vencida (> 1 año)
    $pdo->exec("UPDATE inventario_cargos 
                SET id_estado_fk = (SELECT id_estado FROM inventario_estados WHERE nombre='Carga Vencida' LIMIT 1)
                WHERE mat_tipo_carga_id IS NOT NULL 
                AND mat_fecha_carga IS NOT NULL 
                AND DATE_ADD(mat_fecha_carga, INTERVAL 1 YEAR) < CURDATE()
                AND id_estado_fk NOT IN (SELECT id_estado FROM inventario_estados WHERE nombre IN ('Para Baja', 'Baja', 'Carga Vencida', 'Prueba Vencida'))");

    // B. Prueba Hidráulica Vencida
    $pdo->exec("UPDATE inventario_cargos 
                SET id_estado_fk = (SELECT id_estado FROM inventario_estados WHERE nombre='Prueba Vencida' LIMIT 1)
                WHERE mat_tipo_carga_id IS NOT NULL 
                AND mat_fecha_ph IS NOT NULL 
                AND DATE_ADD(mat_fecha_ph, INTERVAL 1 YEAR) < CURDATE()
                AND id_estado_fk NOT IN (SELECT id_estado FROM inventario_estados WHERE nombre IN ('Para Baja', 'Baja', 'Prueba Vencida'))");

} catch (Exception $e) { /* Log error */ }

$conf_meses = $pdo->query("SELECT valor FROM inventario_config_general WHERE clave='alerta_vida_util_meses'")->fetchColumn() ?: 12;


// --- 2. FILTROS ---
$where = "1=1";
$params = [];

$q = isset($_GET['q']) ? trim($_GET['q']) : '';
if (!empty($q)) {
    $where .= " AND (i.elemento LIKE :q OR i.codigo_inventario LIKE :q OR i.mat_numero_grabado LIKE :q OR i.nombre_responsable LIKE :q)";
    $params[':q'] = "%$q%";
}

$f_ubicacion = isset($_GET['ubicacion']) ? $_GET['ubicacion'] : '';
if (!empty($f_ubicacion)) {
    $where .= " AND i.servicio_ubicacion = :ubi";
    $params[':ubi'] = $f_ubicacion;
}

$f_estado = isset($_GET['estado']) ? $_GET['estado'] : '';
if (!empty($f_estado)) {
    $where .= " AND e.nombre = :est_nom";
    $params[':est_nom'] = $f_estado;
}

// Filtros KPI
$f_kpi = isset($_GET['kpi']) ? $_GET['kpi'] : '';
if ($f_kpi == 'vencidos') {
    $where .= " AND e.nombre IN ('Carga Vencida', 'Prueba Vencida')";
} elseif ($f_kpi == 'mantenimiento') {
    $where .= " AND e.nombre IN ('En Reparación', 'En Mantenimiento')";
} elseif ($f_kpi == 'activos') {
    $where .= " AND e.nombre = 'Activo'";
} elseif ($f_kpi == 'vida_util') {
    $where .= " AND i.mat_tipo_carga_id IS NOT NULL 
                AND i.vida_util_limite IS NOT NULL 
                AND (i.vida_util_limite - YEAR(CURDATE())) * 12 <= :meses_alerta
                AND e.nombre NOT IN ('Para Baja', 'Baja')";
    $params[':meses_alerta'] = $conf_meses;
}

// Filtro Tipo
$f_tipo = isset($_GET['tipo_bien']) ? $_GET['tipo_bien'] : '';
if ($f_tipo == 'Matafuegos') {
    $where .= " AND i.mat_tipo_carga_id IS NOT NULL";
} elseif ($f_tipo == 'General') {
    $where .= " AND i.mat_tipo_carga_id IS NULL";
}


// --- 3. CONSULTAS ---
$sql = "SELECT i.*, e.nombre as nombre_estado, e.color_badge 
        FROM inventario_cargos i 
        LEFT JOIN inventario_estados e ON i.id_estado_fk = e.id_estado 
        WHERE $where ORDER BY i.fecha_creacion DESC LIMIT 1000";
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$inventario = $stmt->fetchAll(PDO::FETCH_ASSOC);

function contar($pdo, $cond) { return $pdo->query("SELECT COUNT(*) FROM inventario_cargos i LEFT JOIN inventario_estados e ON i.id_estado_fk=e.id_estado WHERE $cond")->fetchColumn(); }

$kpi_activos = contar($pdo, "e.nombre = 'Activo'");
$kpi_taller  = contar($pdo, "e.nombre IN ('En Reparación', 'En Mantenimiento')");
$kpi_vencidos = contar($pdo, "e.nombre IN ('Carga Vencida', 'Prueba Vencida')");
$kpi_vidautil = $pdo->query("SELECT COUNT(*) FROM inventario_cargos i LEFT JOIN inventario_estados e ON i.id_estado_fk=e.id_estado 
                             WHERE i.mat_tipo_carga_id IS NOT NULL AND i.vida_util_limite IS NOT NULL 
                             AND (i.vida_util_limite - YEAR(CURDATE())) * 12 <= $conf_meses
                             AND e.nombre NOT IN ('Para Baja', 'Baja')")->fetchColumn();

// Gráficos
$chart_ubi = $pdo->query("SELECT servicio_ubicacion as label, COUNT(*) as data FROM inventario_cargos GROUP BY servicio_ubicacion ORDER BY data DESC LIMIT 10")->fetchAll(PDO::FETCH_ASSOC);
$chart_est = $pdo->query("SELECT e.nombre as label, COUNT(*) as data FROM inventario_cargos i LEFT JOIN inventario_estados e ON i.id_estado_fk=e.id_estado GROUP BY e.nombre")->fetchAll(PDO::FETCH_ASSOC);
$chart_tip = $pdo->query("SELECT IF(mat_tipo_carga_id IS NOT NULL, 'Matafuegos', 'General') as label, COUNT(*) as data FROM inventario_cargos GROUP BY label")->fetchAll(PDO::FETCH_ASSOC);

$lista_lugares = $pdo->query("SELECT DISTINCT servicio_ubicacion FROM inventario_cargos ORDER BY servicio_ubicacion")->fetchAll(PDO::FETCH_COLUMN);
$lista_estados = $pdo->query("SELECT DISTINCT nombre FROM inventario_estados ORDER BY nombre")->fetchAll(PDO::FETCH_COLUMN);

?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Inventario | Logística</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="https://cdn.datatables.net/1.13.4/css/dataTables.bootstrap5.min.css">
    
    <style>
        body { background-color: #f0f2f5; font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; }
        
        .card-kpi {
            border: none; border-radius: 12px; transition: all 0.3s ease;
            overflow: hidden; position: relative; text-decoration: none; color: white !important;
        }
        .card-kpi:hover { transform: translateY(-7px); box-shadow: 0 14px 28px rgba(0,0,0,0.25); filter: brightness(1.05); }
        .card-kpi .icon-bg { position: absolute; right: 15px; top: 50%; transform: translateY(-50%); font-size: 4rem; opacity: 0.2; }
        .card-kpi .h2 { font-weight: 800; font-size: 2.5rem; text-shadow: 1px 1px 2px rgba(0,0,0,0.2); }
        .card-kpi .label-text { font-weight: 600; text-transform: uppercase; letter-spacing: 1px; font-size: 0.85rem; opacity: 0.9; }

        .chart-container { position: relative; height: 250px; width: 100%; }
        .blink { animation: blinker 1.5s linear infinite; font-weight: bold; }
        @keyframes blinker { 50% { opacity: 0.5; } }

        /* Botones Acción (Círculos) */
        .btn-icon-action {
            width: 34px; height: 34px; border-radius: 50%;
            display: inline-flex; align-items: center; justify-content: center;
            border: 1px solid transparent; margin: 0 2px; transition: all 0.2s; text-decoration: none;
        }
        .btn-icon-action:hover { transform: scale(1.1); }
        .btn-pdf { color: #dc3545; background: #fff; border-color: #dc3545; }
        .btn-pdf:hover { background: #dc3545; color: #fff; }
        .btn-edit { color: #0d6efd; background: #fff; border-color: #0d6efd; }
        .btn-edit:hover { background: #0d6efd; color: #fff; }
        .btn-service { color: #fd7e14; background: #fff; border-color: #fd7e14; }
        .btn-service:hover { background: #fd7e14; color: #fff; }
        .btn-del { color: #6c757d; background: #fff; border-color: #6c757d; }
        .btn-del:hover { background: #6c757d; color: #fff; }

        .table-responsive { overflow-x: auto; padding-bottom: 20px; }
    </style>
</head>
<body>
    <?php include 'navbar.php'; ?>

    <div class="container mt-4 mb-5">
        
        <div class="d-flex flex-column flex-md-row justify-content-between align-items-center mb-4 gap-3">
            <div>
                <h2 class="fw-bold m-0 text-dark"><i class="fas fa-boxes text-primary"></i> Tablero de Inventario</h2>
                <small class="text-muted">Gestión integral de bienes y estado del parque</small>
            </div>
            <div class="d-flex flex-wrap gap-2">
                <a href="inventario_config.php" class="btn btn-secondary fw-bold shadow-sm"><i class="fas fa-cogs me-2"></i> Configuración</a>
                <a href="inventario_reporte_pdf.php?<?php echo http_build_query($_GET); ?>" target="_blank" class="btn btn-danger shadow-sm"><i class="fas fa-file-pdf me-2"></i> Reporte</a>
                <a href="inventario_mantenimiento.php" class="btn btn-warning fw-bold text-dark shadow-sm"><i class="fas fa-tools me-2"></i> Servicio</a>
                <a href="inventario_nuevo.php" class="btn btn-primary fw-bold shadow-sm"><i class="fas fa-plus-circle me-2"></i> Nuevo</a>
            </div>
        </div>

        <div class="row g-4 mb-5">
            <div class="col-12 col-sm-6 col-xl-3">
                <a href="?kpi=activos" class="card-kpi bg-success shadow h-100 p-4 d-flex flex-column justify-content-center">
                    <div class="label-text">Total Activos</div>
                    <div class="h2 mb-0"><?php echo $kpi_activos; ?></div>
                    <i class="fas fa-check-circle icon-bg text-white"></i>
                </a>
            </div>
            <div class="col-12 col-sm-6 col-xl-3">
                <a href="?kpi=mantenimiento" class="card-kpi bg-warning text-dark shadow h-100 p-4 d-flex flex-column justify-content-center">
                    <div class="label-text text-dark">En Taller</div>
                    <div class="h2 mb-0 text-dark"><?php echo $kpi_taller; ?></div>
                    <i class="fas fa-wrench icon-bg text-dark"></i>
                </a>
            </div>
            <div class="col-12 col-sm-6 col-xl-3">
                <a href="?kpi=vencidos" class="card-kpi bg-danger shadow h-100 p-4 d-flex flex-column justify-content-center">
                    <div class="label-text">Vencidos</div>
                    <div class="h2 mb-0"><?php echo $kpi_vencidos; ?></div>
                    <i class="fas fa-exclamation-triangle icon-bg text-white"></i>
                </a>
            </div>
            <div class="col-12 col-sm-6 col-xl-3">
                <a href="?kpi=vida_util" class="card-kpi bg-primary shadow h-100 p-4 d-flex flex-column justify-content-center">
                    <div class="label-text">Alerta V.Útil (<?php echo $conf_meses; ?>m)</div>
                    <div class="h2 mb-0"><?php echo $kpi_vidautil; ?></div>
                    <i class="fas fa-hourglass-half icon-bg text-white"></i>
                </a>
            </div>
        </div>

        <div class="row g-3 mb-4">
            <div class="col-md-4">
                <div class="card shadow border-0 h-100 rounded-4">
                    <div class="card-header bg-white border-bottom-0 pt-3 fw-bold small text-muted text-uppercase">Ubicaciones (Top)</div>
                    <div class="card-body"><div class="chart-container"><canvas id="chartUbicacion"></canvas></div></div>
                </div>
            </div>
            <div class="col-md-4">
                <div class="card shadow border-0 h-100 rounded-4">
                    <div class="card-header bg-white border-bottom-0 pt-3 fw-bold small text-muted text-uppercase">Estado del Parque</div>
                    <div class="card-body"><div class="chart-container"><canvas id="chartEstado"></canvas></div></div>
                </div>
            </div>
            <div class="col-md-4">
                <div class="card shadow border-0 h-100 rounded-4">
                    <div class="card-header bg-white border-bottom-0 pt-3 fw-bold small text-muted text-uppercase">Tipos de Bienes</div>
                    <div class="card-body"><div class="chart-container"><canvas id="chartTipo"></canvas></div></div>
                </div>
            </div>
        </div>

        <div class="card shadow border-0 rounded-4">
            <div class="card-header bg-white py-3 border-bottom">
                <form method="GET" class="row g-2 align-items-center">
                    <div class="col-md-4">
                        <div class="input-group">
                            <span class="input-group-text bg-light border-end-0"><i class="fas fa-search text-muted"></i></span>
                            <input type="text" name="q" class="form-control border-start-0 bg-light" placeholder="Buscar por código, serie o nombre..." value="<?php echo htmlspecialchars($q); ?>">
                        </div>
                    </div>
                    <div class="col-md-3">
                        <select name="ubicacion" class="form-select bg-light">
                            <option value="">-- Ubicación --</option>
                            <?php foreach($lista_lugares as $u): ?>
                                <option value="<?php echo htmlspecialchars($u); ?>" <?php echo $f_ubicacion==$u?'selected':''; ?>><?php echo htmlspecialchars($u); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-3">
                        <select name="estado" class="form-select bg-light">
                            <option value="">-- Estado --</option>
                            <?php foreach($lista_estados as $e): ?>
                                <option value="<?php echo htmlspecialchars($e); ?>" <?php echo $f_estado==$e?'selected':''; ?>><?php echo htmlspecialchars($e); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-2 d-grid">
                        <button type="submit" class="btn btn-dark fw-bold">Filtrar</button>
                    </div>
                    <?php if(!empty($_GET)): ?>
                    <div class="col-12 mt-1">
                        <a href="inventario_lista.php" class="text-decoration-none small text-danger"><i class="fas fa-times"></i> Limpiar filtros</a>
                    </div>
                    <?php endif; ?>
                </form>
            </div>
            
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table id="tablaInventario" class="table table-hover align-middle mb-0 w-100">
                        <thead class="bg-light small text-uppercase text-muted">
                            <tr>
                                <th>Estado</th>
                                <th>Códigos</th>
                                <th>Descripción</th>
                                <th>Ubicación</th>
                                <th>Vencimientos</th>
                                <th class="text-center" style="min-width: 160px;">Acciones</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach($inventario as $i): ?>
                                <tr>
                                    <td>
                                        <?php if($i['nombre_estado']): ?>
                                            <span class="badge <?php echo $i['color_badge'] ?? 'bg-secondary'; ?> rounded-pill px-3 py-2">
                                                <?php echo htmlspecialchars($i['nombre_estado']); ?>
                                            </span>
                                        <?php else: ?>
                                            <span class="badge bg-secondary rounded-pill px-3 py-2">Sin Asignar</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <div class="d-flex flex-column">
                                            <?php if($i['codigo_inventario']): ?>
                                                <div class="text-primary fw-bold" style="font-size: 0.9rem;" title="Interno"><i class="fas fa-hashtag me-1 opacity-50"></i><?php echo $i['codigo_inventario']; ?></div>
                                            <?php endif; ?>
                                            <?php if($i['mat_numero_grabado']): ?>
                                                <div class="text-danger fw-bold" style="font-size: 0.85rem;" title="Grabado"><i class="fas fa-fire-extinguisher me-1 opacity-50"></i><?php echo $i['mat_numero_grabado']; ?></div>
                                            <?php endif; ?>
                                        </div>
                                    </td>
                                    <td>
                                        <div class="fw-bold text-dark fs-6"><?php echo htmlspecialchars($i['elemento']); ?></div>
                                        <?php if($i['observaciones']): ?>
                                            <small class="text-muted text-truncate d-block mt-1" style="max-width:250px;">
                                                <i class="far fa-comment-alt me-1"></i> <?php echo htmlspecialchars($i['observaciones']); ?>
                                            </small>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <div class="text-truncate" style="max-width: 180px;">
                                            <i class="fas fa-map-marker-alt text-muted me-1"></i> <?php echo htmlspecialchars($i['servicio_ubicacion']); ?>
                                        </div>
                                    </td>
                                    <td class="small">
                                        <?php if($i['mat_tipo_carga_id']): ?>
                                            <?php 
                                                $hoy = new DateTime();
                                                if($i['mat_fecha_carga']){
                                                    $v = (new DateTime($i['mat_fecha_carga']))->modify('+1 year');
                                                    $cls = ($v < $hoy) ? 'text-danger fw-bold blink' : 'text-success fw-bold';
                                                    echo "<div class='$cls mb-1'><i class='fas fa-battery-full me-1'></i>Carga: ".$v->format('d/m/Y')."</div>";
                                                }
                                                if($i['mat_fecha_ph']){
                                                    $v = (new DateTime($i['mat_fecha_ph']))->modify('+1 year'); 
                                                    $cls = ($v < $hoy) ? 'text-danger fw-bold blink' : 'text-success fw-bold';
                                                    echo "<div class='$cls mb-1'><i class='fas fa-flask me-1'></i>PH: ".$v->format('d/m/Y')."</div>";
                                                }
                                                if($i['vida_util_limite']){
                                                    $anioActual = (int)date('Y');
                                                    $restante = $i['vida_util_limite'] - $anioActual;
                                                    if($restante <= 1) echo "<div class='text-danger fw-bold blink'><i class='fas fa-skull-crossbones me-1'></i>Fin Vida: ".$i['vida_util_limite']."</div>";
                                                }
                                            ?>
                                        <?php else: ?>
                                            <span class="text-muted">-</span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="text-center">
                                        <div class="d-flex justify-content-center">
                                            <a href="inventario_pdf.php?id=<?php echo $i['id_cargo']; ?>" target="_blank" class="btn-icon-action btn-pdf" title="Ver PDF">
                                                <i class="fas fa-file-pdf"></i>
                                            </a>
                                            <a href="inventario_editar.php?id=<?php echo $i['id_cargo']; ?>" class="btn-icon-action btn-edit" title="Editar">
                                                <i class="fas fa-edit"></i>
                                            </a>
                                            <?php if($i['mat_tipo_carga_id']): ?>
                                                <a href="inventario_mantenimiento.php?buscar_codigo=<?php echo $i['codigo_inventario']; ?>" class="btn-icon-action btn-service" title="Servicio Técnico">
                                                    <i class="fas fa-tools"></i>
                                                </a>
                                            <?php endif; ?>
                                            
                                            <a href="inventario_eliminar.php?id=<?php echo $i['id_cargo']; ?>" onclick="return confirm('ATENCIÓN: ¿Seguro que deseas ELIMINAR este bien? Esta acción es irreversible.')" class="btn-icon-action btn-del" title="Eliminar">
                                                <i class="fas fa-trash"></i>
                                            </a>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                <?php if(empty($inventario)): ?>
                    <div class="p-5 text-center text-muted">
                        <i class="fas fa-search fs-1 mb-3 opacity-25"></i>
                        <h5>No se encontraron resultados</h5>
                        <p>Intenta ajustar los filtros de búsqueda.</p>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <script src="https://code.jquery.com/jquery-3.7.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.datatables.net/1.13.4/js/jquery.dataTables.min.js"></script>
    <script src="https://cdn.datatables.net/1.13.4/js/dataTables.bootstrap5.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

    <script>
        $(document).ready(function() {
            // Inicializar DataTable
            $('#tablaInventario').DataTable({
                "language": { "url": "//cdn.datatables.net/plug-ins/1.13.4/i18n/es-ES.json" },
                "pageLength": 25,
                "order": [],
                "dom": 'rtp' // Oculta buscador interno
            });
        });

        // Configuración Gráficos (Protegida)
        const commonOptions = { responsive: true, maintainAspectRatio: false, plugins: { legend: { position: 'bottom' } } };

        // Función segura para click en gráfico
        function handleChartClick(e, els, dataArray, paramName) {
            if(els.length > 0) {
                const idx = els[0].index;
                if(dataArray[idx]) {
                    window.location.href = `?${paramName}=${encodeURIComponent(dataArray[idx].label)}`;
                }
            }
        }

        // Datos PHP a JS
        const dataUbi = <?php echo json_encode($chart_ubi); ?>;
        const dataEst = <?php echo json_encode($chart_est); ?>;
        const dataTip = <?php echo json_encode($chart_tip); ?>;

        // 1. Ubicación
        const ctxUbi = document.getElementById('chartUbicacion');
        if(ctxUbi && dataUbi.length > 0) {
            new Chart(ctxUbi, {
                type: 'bar', indexAxis: 'y',
                data: {
                    labels: dataUbi.map(d => d.label),
                    datasets: [{ label: 'Total', data: dataUbi.map(d => d.data), backgroundColor: '#0d6efd', borderRadius: 5 }]
                },
                options: { ...commonOptions, onClick: (e, els) => handleChartClick(e, els, dataUbi, 'ubicacion') }
            });
        }

        // 2. Estado
        const ctxEst = document.getElementById('chartEstado');
        if(ctxEst && dataEst.length > 0) {
            new Chart(ctxEst, {
                type: 'doughnut',
                data: {
                    labels: dataEst.map(d => d.label),
                    datasets: [{
                        data: dataEst.map(d => d.data),
                        backgroundColor: ['#198754', '#ffc107', '#dc3545', '#0d6efd', '#212529'],
                        hoverOffset: 4
                    }]
                },
                options: { ...commonOptions, onClick: (e, els) => handleChartClick(e, els, dataEst, 'estado') }
            });
        }

        // 3. Tipo
        const ctxTip = document.getElementById('chartTipo');
        if(ctxTip && dataTip.length > 0) {
            new Chart(ctxTip, {
                type: 'bar',
                data: {
                    labels: dataTip.map(d => d.label),
                    datasets: [{ label: 'Total', data: dataTip.map(d => d.data), backgroundColor: ['#dc3545', '#6c757d'], borderRadius: 5 }]
                },
                options: { ...commonOptions, onClick: (e, els) => handleChartClick(e, els, dataTip, 'tipo_bien') }
            });
        }
    </script>
    <?php include 'footer.php'; ?>
</body>
</html>