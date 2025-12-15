<?php
// Archivo: inventario_lista.php
// MEJORAS: Layout tipo Container, DataTables, Gráficos con Chart.js y Estadísticas por Servicio.
session_start();
include 'conexion.php';
include 'funciones_permisos.php';

// Validar permiso
if (!isset($_SESSION['usuario_id']) || !tiene_permiso('acceso_inventario', $pdo)) {
    header("Location: dashboard.php");
    exit();
}

// --- LÓGICA DE FILTROS PHP (Se mantiene como respaldo o filtro inicial) ---
$where = "1=1";
$params = [];

$busqueda = isset($_GET['q']) ? trim($_GET['q']) : '';
if (!empty($busqueda)) {
    $where .= " AND (elemento LIKE :q OR codigo_inventario LIKE :q OR nombre_responsable LIKE :q OR nombre_jefe_servicio LIKE :q)";
    $params[':q'] = "%$busqueda%";
}

$filtro_servicio = isset($_GET['servicio']) ? trim($_GET['servicio']) : '';
if (!empty($filtro_servicio)) {
    $where .= " AND servicio_ubicacion = :serv";
    $params[':serv'] = $filtro_servicio;
}

// Consultas de listado
$sql_lista = "SELECT * FROM inventario_cargos WHERE $where ORDER BY fecha_creacion DESC";
$stmt = $pdo->prepare($sql_lista);
$stmt->execute($params);
$inventario = $stmt->fetchAll(PDO::FETCH_ASSOC);

// --- ESTADÍSTICAS AVANZADAS ---
// 1. Totales Generales
$total_bienes = $pdo->query("SELECT COUNT(*) FROM inventario_cargos")->fetchColumn();
$mes_actual = date('Y-m');
$bienes_mes = $pdo->query("SELECT COUNT(*) FROM inventario_cargos WHERE DATE_FORMAT(fecha_creacion, '%Y-%m') = '$mes_actual'")->fetchColumn();

// 2. Datos para el Gráfico (Cantidad por Servicio)
// Obtenemos cuántos items hay en cada servicio para el gráfico
$sql_stats = "SELECT servicio_ubicacion, COUNT(*) as cantidad FROM inventario_cargos GROUP BY servicio_ubicacion ORDER BY cantidad DESC";
$stats_servicios = $pdo->query($sql_stats)->fetchAll(PDO::FETCH_ASSOC);

$labels_grafico = [];
$data_grafico = [];
foreach ($stats_servicios as $row) {
    $labels_grafico[] = $row['servicio_ubicacion'];
    $data_grafico[] = $row['cantidad'];
}

// Lista de servicios para el select (filtro PHP)
$servicios_db = $pdo->query("SELECT DISTINCT servicio_ubicacion FROM inventario_cargos ORDER BY servicio_ubicacion ASC")->fetchAll(PDO::FETCH_COLUMN);
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gestión de Inventario | Logística</title>
    
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="https://cdn.datatables.net/1.13.4/css/dataTables.bootstrap5.min.css">
    <link rel="stylesheet" href="https://cdn.datatables.net/buttons/2.3.6/css/buttons.bootstrap5.min.css">

    <style>
        body { background-color: #f8f9fa; }
        .avatar-circle { width: 30px; height: 30px; border-radius: 50%; display: flex; align-items: center; justify-content: center; font-weight: bold; font-size: 0.8rem; }
        .card-stat { transition: transform 0.2s; }
        .card-stat:hover { transform: translateY(-5px); }
        .table-responsive { border-radius: 8px; overflow: hidden; }
    </style>
</head>
<body>

    <?php include 'navbar.php'; ?>

    <div class="container mt-4 mb-5">
        
        <div class="d-flex justify-content-between align-items-center mb-4">
            <div>
                <h2 class="mb-0 fw-bold text-dark"><i class="fas fa-warehouse me-2 text-primary"></i> Inventario</h2>
                <p class="text-muted small mb-0">Control Patrimonial y Asignaciones</p>
            </div>
            <div>
                <a href="inventario_nuevo.php" class="btn btn-primary shadow-sm">
                    <i class="fas fa-plus-circle me-2"></i> Nuevo Cargo
                </a>
            </div>
        </div>

        <div class="row g-3 mb-4">
            <div class="col-md-3">
                <div class="card border-0 shadow-sm border-start border-4 border-primary h-100 card-stat">
                    <div class="card-body">
                        <div class="d-flex justify-content-between align-items-center">
                            <div>
                                <div class="text-uppercase fw-bold text-primary small mb-1">Total Activos</div>
                                <div class="h3 mb-0 fw-bold"><?php echo $total_bienes; ?></div>
                            </div>
                            <div class="fs-1 text-primary opacity-25"><i class="fas fa-boxes"></i></div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card border-0 shadow-sm border-start border-4 border-success h-100 card-stat">
                    <div class="card-body">
                        <div class="d-flex justify-content-between align-items-center">
                            <div>
                                <div class="text-uppercase fw-bold text-success small mb-1">Altas Mes</div>
                                <div class="h3 mb-0 fw-bold"><?php echo $bienes_mes; ?></div>
                            </div>
                            <div class="fs-1 text-success opacity-25"><i class="fas fa-calendar-check"></i></div>
                        </div>
                    </div>
                </div>
            </div>
            
            <div class="col-md-6">
                <div class="card border-0 shadow-sm h-100">
                    <div class="card-body py-2">
                        <h6 class="fw-bold text-muted small mb-2">Distribución por Servicio (Top 10)</h6>
                        <div style="height: 100px;">
                            <canvas id="chartServicios"></canvas>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="card shadow-sm mb-4 collapsed-card">
            <div class="card-header bg-white py-2" data-bs-toggle="collapse" href="#collapseFiltros" role="button" aria-expanded="false" style="cursor: pointer;">
                <div class="d-flex justify-content-between align-items-center">
                    <small class="fw-bold text-primary"><i class="fas fa-filter me-1"></i> Filtros de Servidor (Clic para expandir)</small>
                    <i class="fas fa-chevron-down text-muted small"></i>
                </div>
            </div>
            <div class="collapse" id="collapseFiltros">
                <div class="card-body bg-light">
                    <form method="GET" action="inventario_lista.php" class="row g-2 align-items-end">
                        <div class="col-md-4">
                            <label class="form-label small text-muted">Búsqueda General</label>
                            <input type="text" name="q" class="form-control form-control-sm" placeholder="Buscar..." value="<?php echo htmlspecialchars($busqueda); ?>">
                        </div>
                        <div class="col-md-3">
                            <label class="form-label small text-muted">Servicio</label>
                            <select name="servicio" class="form-select form-select-sm">
                                <option value="">-- Todos --</option>
                                <?php foreach ($servicios_db as $serv): ?>
                                    <option value="<?php echo htmlspecialchars($serv); ?>" <?php echo ($filtro_servicio == $serv) ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($serv); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-2">
                            <button type="submit" class="btn btn-dark btn-sm w-100">Filtrar</button>
                        </div>
                        <div class="col-md-2">
                             <?php if(!empty($busqueda) || !empty($filtro_servicio)): ?>
                                <a href="inventario_lista.php" class="btn btn-outline-secondary btn-sm w-100">Limpiar</a>
                            <?php endif; ?>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        <div class="card shadow border-0">
            <div class="card-body">
                <div class="table-responsive">
                    <table id="tablaInventario" class="table table-hover align-middle mb-0" style="width:100%">
                        <thead class="bg-light">
                            <tr>
                                <th class="text-nowrap">Fecha Alta</th>
                                <th>Código</th>
                                <th>Elemento</th>
                                <th>Ubicación</th>
                                <th>Responsable</th>
                                <th class="text-center">Acciones</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($inventario as $item): ?>
                                <tr>
                                    <td class="text-muted small" data-order="<?php echo $item['fecha_creacion']; ?>">
                                        <?php echo date('d/m/Y', strtotime($item['fecha_creacion'])); ?>
                                    </td>
                                    <td>
                                        <?php if($item['codigo_inventario']): ?>
                                            <span class="badge bg-light text-dark border font-monospace"><?php echo htmlspecialchars($item['codigo_inventario']); ?></span>
                                        <?php else: ?>
                                            <span class="text-muted small">-</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <div class="fw-bold text-dark"><?php echo htmlspecialchars($item['elemento']); ?></div>
                                    </td>
                                    <td>
                                        <span class="badge bg-info text-dark bg-opacity-10 border border-info">
                                            <?php echo htmlspecialchars($item['servicio_ubicacion']); ?>
                                        </span>
                                    </td>
                                    <td>
                                        <div class="d-flex align-items-center">
                                            <div class="avatar-circle bg-secondary text-white me-2 small">
                                                <?php echo strtoupper(substr($item['nombre_responsable'], 0, 1)); ?>
                                            </div>
                                            <div class="d-flex flex-column" style="line-height: 1.1;">
                                                <span class="small fw-bold"><?php echo htmlspecialchars($item['nombre_responsable']); ?></span>
                                                <span class="text-muted" style="font-size: 0.75rem;"><?php echo htmlspecialchars($item['nombre_jefe_servicio']); ?> (Jefe)</span>
                                            </div>
                                        </div>
                                    </td>
                                    <td class="text-center">
                                        <div class="btn-group btn-group-sm">
                                            <a href="inventario_pdf.php?id=<?php echo $item['id_cargo']; ?>" target="_blank" class="btn btn-outline-danger" title="Descargar PDF">
                                                <i class="fas fa-file-pdf"></i>
                                            </a>
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
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <script src="https://cdn.datatables.net/1.13.4/js/jquery.dataTables.min.js"></script>
    <script src="https://cdn.datatables.net/1.13.4/js/dataTables.bootstrap5.min.js"></script>
    <script src="https://cdn.datatables.net/buttons/2.3.6/js/dataTables.buttons.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jszip/3.1.3/jszip.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/pdfmake/0.1.53/pdfmake.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/pdfmake/0.1.53/vfs_fonts.js"></script>
    <script src="https://cdn.datatables.net/buttons/2.3.6/js/buttons.html5.min.js"></script>
    <script src="https://cdn.datatables.net/buttons/2.3.6/js/buttons.print.min.js"></script>

    <script>
        // 1. Inicialización de DataTables
        $(document.ready).ready(function() {
            var table = $('#tablaInventario').DataTable({
                language: {
                    url: '//cdn.datatables.net/plug-ins/1.13.4/i18n/es-ES.json'
                },
                dom: 'Bfrtip',
                buttons: [
                    { extend: 'excel', text: '<i class="fas fa-file-excel"></i> Excel', className: 'btn-sm btn-success' },
                    { extend: 'pdf', text: '<i class="fas fa-file-pdf"></i> PDF', className: 'btn-sm btn-danger' },
                    { extend: 'print', text: '<i class="fas fa-print"></i> Imprimir', className: 'btn-sm btn-secondary' }
                ],
                pageLength: 10,
                order: [[ 0, "desc" ]] // Ordenar por fecha descendente
            });
        });

        // 2. Gráfico de Servicios (Chart.js)
        const ctx = document.getElementById('chartServicios').getContext('2d');
        const chartServicios = new Chart(ctx, {
            type: 'bar',
            data: {
                labels: <?php echo json_encode(array_slice($labels_grafico, 0, 10)); ?>, // Solo top 10 para no saturar
                datasets: [{
                    label: 'Cantidad de Bienes',
                    data: <?php echo json_encode(array_slice($data_grafico, 0, 10)); ?>,
                    backgroundColor: 'rgba(54, 162, 235, 0.6)',
                    borderColor: 'rgba(54, 162, 235, 1)',
                    borderWidth: 1,
                    borderRadius: 5
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: { display: false }
                },
                scales: {
                    y: { beginAtZero: true }
                }
            }
        });
    </script>
    <?php include 'footer.php'; ?>
</body>
</html>