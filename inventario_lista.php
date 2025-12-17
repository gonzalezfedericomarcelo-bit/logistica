<?php
// Archivo: inventario_lista.php
session_start();
include 'conexion.php';
include 'funciones_permisos.php';

// Verificación de sesión y permisos
if (!isset($_SESSION['usuario_id']) || !tiene_permiso('acceso_inventario', $pdo)) {
    header("Location: dashboard.php"); 
    exit();
}

// --- 1. FILTROS ---
$where = "1=1";
$params = [];

// Filtro General
$busqueda = isset($_GET['q']) ? trim($_GET['q']) : '';
if (!empty($busqueda)) {
    $where .= " AND (elemento LIKE :q OR codigo_inventario LIKE :q OR nombre_responsable LIKE :q OR servicio_ubicacion LIKE :q)";
    $params[':q'] = "%$busqueda%";
}

// Filtro Ubicación
$ubicacion_filtro = isset($_GET['ubicacion']) ? $_GET['ubicacion'] : '';
if (!empty($ubicacion_filtro)) {
    $where .= " AND servicio_ubicacion = :serv";
    $params[':serv'] = $ubicacion_filtro;
}

// Filtro Tipo (Lógica Inteligente)
$tipo_filtro = isset($_GET['tipo']) ? $_GET['tipo'] : '';
if (!empty($tipo_filtro)) {
    if ($tipo_filtro == 'INFORMATICA') {
        $where .= " AND (elemento LIKE 'CPU%' OR elemento LIKE 'MONITOR%' OR elemento LIKE 'NOTEBOOK%' OR elemento LIKE 'IMPRESORA%' OR elemento LIKE 'UPS%')";
    } elseif ($tipo_filtro == 'MATAFUEGOS') {
        $where .= " AND elemento LIKE 'MATAFUEGO%'";
    } elseif ($tipo_filtro == 'CAMARAS') {
        $where .= " AND elemento LIKE 'CAMARA%'";
    } elseif ($tipo_filtro == 'TELEFONIA') {
        $where .= " AND elemento LIKE 'TELEFONO%'";
    } else {
        $where .= " AND elemento LIKE :tipo_p";
        $params[':tipo_p'] = $tipo_filtro . '%';
    }
}

// Filtro Estado
$estado_filtro = isset($_GET['estado']) ? $_GET['estado'] : '';
if (!empty($estado_filtro)) {
    $where .= " AND estado = :estado";
    $params[':estado'] = $estado_filtro;
}

// Filtro Fecha (KPI)
if (isset($_GET['filtro_fecha']) && $_GET['filtro_fecha'] == 'mes_actual') {
    $where .= " AND DATE_FORMAT(fecha_creacion, '%Y-%m') = :mes_act";
    $params[':mes_act'] = date('Y-m');
}

// --- 2. CONSULTAS SEGURAS (Evita Error 500) ---

// Consulta Principal
$inventario = [];
$total_filtrado = 0;
try {
    $sql = "SELECT * FROM inventario_cargos WHERE $where ORDER BY fecha_creacion DESC LIMIT 1000";
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $inventario = $stmt->fetchAll(PDO::FETCH_ASSOC);
    $total_filtrado = count($inventario);
} catch (Exception $e) {
    // Si falla la tabla principal, no rompemos todo, simplemente queda vacía
    error_log("Error cargando inventario: " . $e->getMessage());
}

// Helper para consultas simples seguras
function consultaSegura($pdo, $sql, $metodo = 'fetchAll', $modo = PDO::FETCH_ASSOC) {
    try {
        $stmt = $pdo->query($sql);
        if ($stmt) {
            if ($metodo == 'fetchColumn') return $stmt->fetchColumn();
            return $stmt->fetchAll($modo);
        }
    } catch (Exception $e) {
        return ($metodo == 'fetchAll') ? [] : 0;
    }
    return ($metodo == 'fetchAll') ? [] : 0;
}

// Lista de Ubicaciones
$lista_ubicaciones = consultaSegura($pdo, "SELECT DISTINCT servicio_ubicacion FROM inventario_cargos ORDER BY servicio_ubicacion", 'fetchAll', PDO::FETCH_COLUMN);

// KPIs
$total_activos = consultaSegura($pdo, "SELECT COUNT(*) FROM inventario_cargos WHERE estado = 'Activo'", 'fetchColumn');
$total_bajas = consultaSegura($pdo, "SELECT COUNT(*) FROM inventario_cargos WHERE estado = 'Baja'", 'fetchColumn');
$altas_mes = consultaSegura($pdo, "SELECT COUNT(*) FROM inventario_cargos WHERE DATE_FORMAT(fecha_creacion, '%Y-%m') = '" . date('Y-m') . "'", 'fetchColumn');

// Datos Gráficos
$data_serv = consultaSegura($pdo, "SELECT servicio_ubicacion, COUNT(*) as c FROM inventario_cargos GROUP BY servicio_ubicacion ORDER BY c DESC LIMIT 8");
$data_est = consultaSegura($pdo, "SELECT estado, COUNT(*) as c FROM inventario_cargos GROUP BY estado");
$data_tipo = consultaSegura($pdo, "SELECT SUBSTRING_INDEX(elemento, ' ', 1) as tipo, COUNT(*) as c FROM inventario_cargos GROUP BY tipo ORDER BY c DESC LIMIT 8");

// Asegurar arrays si fallaron las consultas
if (!$lista_ubicaciones) $lista_ubicaciones = [];
if (!$data_serv) $data_serv = [];
if (!$data_est) $data_est = [];
if (!$data_tipo) $data_tipo = [];

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
        body { background-color: #f8f9fa; }
        .card-kpi { cursor: pointer; transition: transform 0.2s; }
        .card-kpi:hover { transform: translateY(-5px); }
        .chart-container { position: relative; height: 200px; width: 100%; }
    </style>
</head>
<body>
    <?php include 'navbar.php'; ?>

    <div class="container mt-4 mb-5">
        
        <div class="d-flex flex-column flex-md-row justify-content-between align-items-center mb-4 gap-3">
            <h2 class="fw-bold m-0"><i class="fas fa-boxes text-primary"></i> Control de Inventario</h2>
            <div class="d-flex flex-wrap gap-2">
                <a href="inventario_reporte_pdf.php?<?php echo http_build_query($_GET); ?>" target="_blank" class="btn btn-danger shadow-sm">
                    <i class="fas fa-file-pdf me-2"></i> Reporte Global
                </a>
                <a href="inventario_movimientos.php" class="btn btn-outline-dark shadow-sm">
                    <i class="fas fa-history me-2"></i> Historial
                </a>
                <a href="inventario_nuevo.php" class="btn btn-primary shadow">
                    <i class="fas fa-plus-circle me-2"></i> Nuevo Bien
                </a>
            </div>
        </div>

        <div class="row g-3 mb-4">
            <div class="col-12 col-md-4">
                <div class="card card-kpi border-start border-4 border-primary shadow-sm h-100" onclick="window.location.href='?estado=Activo'">
                    <div class="card-body d-flex justify-content-between align-items-center">
                        <div>
                            <div class="text-primary small fw-bold text-uppercase">Activos</div>
                            <div class="h2 fw-bold mb-0"><?php echo $total_activos ? $total_activos : 0; ?></div>
                        </div>
                        <i class="fas fa-check-circle fs-1 text-primary opacity-25"></i>
                    </div>
                </div>
            </div>
            <div class="col-12 col-md-4">
                <div class="card card-kpi border-start border-4 border-success shadow-sm h-100" onclick="window.location.href='?filtro_fecha=mes_actual'">
                    <div class="card-body d-flex justify-content-between align-items-center">
                        <div>
                            <div class="text-success small fw-bold text-uppercase">Altas Mes</div>
                            <div class="h2 fw-bold mb-0"><?php echo $altas_mes ? $altas_mes : 0; ?></div>
                        </div>
                        <i class="fas fa-calendar-plus fs-1 text-success opacity-25"></i>
                    </div>
                </div>
            </div>
            <div class="col-12 col-md-4">
                <div class="card card-kpi border-start border-4 border-danger shadow-sm h-100" onclick="window.location.href='?estado=Baja'">
                    <div class="card-body d-flex justify-content-between align-items-center">
                        <div>
                            <div class="text-danger small fw-bold text-uppercase">Bajas</div>
                            <div class="h2 fw-bold mb-0"><?php echo $total_bajas ? $total_bajas : 0; ?></div>
                        </div>
                        <i class="fas fa-trash-alt fs-1 text-danger opacity-25"></i>
                    </div>
                </div>
            </div>
        </div>

        <div class="row g-3 mb-4">
            <div class="col-12 col-md-4">
                <div class="card shadow-sm h-100">
                    <div class="card-header bg-white fw-bold small">Ubicaciones (Top)</div>
                    <div class="card-body">
                        <div class="chart-container"><canvas id="chartServicios"></canvas></div>
                    </div>
                </div>
            </div>
            <div class="col-12 col-md-4">
                <div class="card shadow-sm h-100">
                    <div class="card-header bg-white fw-bold small">Tipos de Bienes</div>
                    <div class="card-body">
                        <div class="chart-container"><canvas id="chartTipos"></canvas></div>
                    </div>
                </div>
            </div>
            <div class="col-12 col-md-4">
                <div class="card shadow-sm h-100">
                    <div class="card-header bg-white fw-bold small">Estado del Parque</div>
                    <div class="card-body">
                        <div class="chart-container"><canvas id="chartEstado"></canvas></div>
                    </div>
                </div>
            </div>
        </div>

        <div class="card shadow border-0">
            <div class="card-header bg-light py-3">
                <form method="GET" class="row g-2 align-items-end">
                    <div class="col-12 col-md-3">
                        <label class="form-label small fw-bold text-muted">Búsqueda</label>
                        <input type="text" name="q" class="form-control form-control-sm" placeholder="Buscar..." value="<?php echo htmlspecialchars($busqueda); ?>">
                    </div>
                    <div class="col-12 col-md-3">
                        <label class="form-label small fw-bold text-muted">Ubicación</label>
                        <select name="ubicacion" class="form-select form-select-sm">
                            <option value="">-- Todas --</option>
                            <?php foreach($lista_ubicaciones as $u): ?>
                                <option value="<?php echo htmlspecialchars($u); ?>" <?php echo ($ubicacion_filtro == $u) ? 'selected' : ''; ?>><?php echo htmlspecialchars($u); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-12 col-md-2">
                        <label class="form-label small fw-bold text-muted">Tipo</label>
                        <select name="tipo" class="form-select form-select-sm">
                            <option value="">-- Todos --</option>
                            <option value="INFORMATICA" <?php if($tipo_filtro=='INFORMATICA') echo 'selected'; ?>>Informática</option>
                            <option value="MATAFUEGOS" <?php if($tipo_filtro=='MATAFUEGOS') echo 'selected'; ?>>Matafuegos</option>
                            <option value="CAMARAS" <?php if($tipo_filtro=='CAMARAS') echo 'selected'; ?>>Cámaras</option>
                            <option value="TELEFONIA" <?php if($tipo_filtro=='TELEFONIA') echo 'selected'; ?>>Telefonía</option>
                        </select>
                    </div>
                    <div class="col-12 col-md-2">
                        <label class="form-label small fw-bold text-muted">Estado</label>
                        <select name="estado" class="form-select form-select-sm">
                            <option value="">-- Todos --</option>
                            <option value="Activo" <?php if($estado_filtro=='Activo') echo 'selected'; ?>>Activo</option>
                            <option value="Baja" <?php if($estado_filtro=='Baja') echo 'selected'; ?>>Baja</option>
                        </select>
                    </div>
                    <div class="col-12 col-md-2 d-grid">
                        <button type="submit" class="btn btn-dark btn-sm"><i class="fas fa-filter"></i> Filtrar</button>
                    </div>
                </form>
            </div>
            
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table id="tablaInventario" class="table table-hover align-middle mb-0 w-100">
                        <thead class="bg-light text-secondary small text-uppercase">
                            <tr>
                                <th>Estado</th>
                                <th>Elemento</th>
                                <th>Ubicación</th>
                                <th>Responsable</th>
                                <th class="text-center">Acciones</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (!empty($inventario)): ?>
                                <?php foreach ($inventario as $item): ?>
                                    <?php $badge = ($item['estado']=='Activo') ? 'bg-success' : (($item['estado']=='Baja') ? 'bg-danger' : 'bg-warning'); ?>
                                    <tr>
                                        <td><span class="badge <?php echo $badge; ?>"><?php echo $item['estado']; ?></span></td>
                                        <td>
                                            <div class="fw-bold text-dark"><?php echo htmlspecialchars($item['elemento']); ?></div>
                                            <small class="text-muted font-monospace"><?php echo htmlspecialchars($item['codigo_inventario']); ?></small>
                                        </td>
                                        <td><?php echo htmlspecialchars($item['servicio_ubicacion']); ?></td>
                                        <td><small class="fw-bold"><?php echo htmlspecialchars($item['nombre_responsable']); ?></small></td>
                                        <td class="text-center">
                                            <div class="dropdown">
                                                <button class="btn btn-sm btn-light border dropdown-toggle" type="button" data-bs-toggle="dropdown"><i class="fas fa-ellipsis-v"></i></button>
                                                <ul class="dropdown-menu dropdown-menu-end shadow">
                                                    <li><a class="dropdown-item" href="inventario_pdf.php?id=<?php echo $item['id_cargo']; ?>" target="_blank"><i class="fas fa-file-pdf text-danger me-2"></i> PDF Individual</a></li>
                                                    <li><a class="dropdown-item" href="inventario_editar.php?id=<?php echo $item['id_cargo']; ?>"><i class="fas fa-edit text-primary me-2"></i> Editar</a></li>
                                                    <li><a class="dropdown-item" href="inventario_transferir.php?id=<?php echo $item['id_cargo']; ?>"><i class="fas fa-exchange-alt text-info me-2"></i> Transferir</a></li>
                                                    <li><hr class="dropdown-divider"></li>
                                                    <li><a class="dropdown-item text-danger" href="inventario_baja.php?id=<?php echo $item['id_cargo']; ?>"><i class="fas fa-trash-alt me-2"></i> Dar de Baja</a></li>
                                                </ul>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <tr><td colspan="5" class="text-center text-muted p-4">No se encontraron bienes en el inventario.</td></tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <script src="https://code.jquery.com/jquery-3.7.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <script src="https://cdn.datatables.net/1.13.4/js/jquery.dataTables.min.js"></script>
    <script src="https://cdn.datatables.net/1.13.4/js/dataTables.bootstrap5.min.js"></script>

    <script>
        $(document).ready(function() {
            $('#tablaInventario').DataTable({
                "language": { "url": "//cdn.datatables.net/plug-ins/1.13.4/i18n/es-ES.json" },
                "pageLength": 25, "order": [[ 1, "asc" ]]
            });
        });

        // Configuración Gráficos Interactivos
        const commonOptions = {
            responsive: true, maintainAspectRatio: false,
            plugins: { legend: { display: false } },
            onClick: (e, activeEls, chart) => {
                if (activeEls.length > 0) {
                    const idx = activeEls[0].index;
                    const label = chart.data.labels[idx];
                    // CORRECCIÓN: Se agregaron las backticks (`) y comillas para que el JS sea válido
                    if (chart.canvas.id === 'chartServicios') window.location.href = `?ubicacion=${encodeURIComponent(label)}`;
                    if (chart.canvas.id === 'chartTipos') window.location.href = `?tipo=${encodeURIComponent(label)}`;
                    if (chart.canvas.id === 'chartEstado') window.location.href = `?estado=${encodeURIComponent(label)}`;
                }
            }
        };

        new Chart(document.getElementById('chartServicios'), {
            type: 'bar',
            data: {
                labels: <?php echo json_encode(array_column($data_serv, 'servicio_ubicacion')); ?>,
                datasets: [{ label: 'Total', data: <?php echo json_encode(array_column($data_serv, 'c')); ?>, backgroundColor: '#0d6efd', borderRadius: 4 }]
            }, options: commonOptions
        });

        new Chart(document.getElementById('chartTipos'), {
            type: 'doughnut',
            data: {
                labels: <?php echo json_encode(array_column($data_tipo, 'tipo')); ?>,
                datasets: [{ data: <?php echo json_encode(array_column($data_tipo, 'c')); ?>, backgroundColor: ['#6610f2','#6f42c1','#d63384','#dc3545','#fd7e14','#ffc107','#198754'] }]
            }, options: { ...commonOptions, plugins: { legend: { position: 'right' } } }
        });

        new Chart(document.getElementById('chartEstado'), {
            type: 'pie',
            data: {
                labels: <?php echo json_encode(array_column($data_est, 'estado')); ?>,
                datasets: [{ data: <?php echo json_encode(array_column($data_est, 'c')); ?>, backgroundColor: ['#198754','#dc3545','#ffc107'] }]
            }, options: { ...commonOptions, plugins: { legend: { position: 'right' } } }
        });
    </script>
    <?php include 'footer.php'; ?>
</body>
</html>