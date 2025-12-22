<?php
// Archivo: ascensor_estadisticas.php
// OBJETIVO: Tabla en el medio, Gráficos al final.
session_start();
require_once 'conexion.php';
require_once 'funciones_permisos.php';

if (!tiene_permiso('admin_ascensores', $pdo)) { header("Location: dashboard.php"); exit; }

// --- PARÁMETROS DE FILTRO ---
$filtro_estado = $_GET['estado'] ?? null;
$filtro_ascensor = $_GET['id_ascensor'] ?? null;

// --- DATOS WIDGETS ---
$total_incidencias = $pdo->query("SELECT COUNT(*) FROM ascensor_incidencias")->fetchColumn();
$total_activas = $pdo->query("SELECT COUNT(*) FROM ascensor_incidencias WHERE estado != 'resuelto'")->fetchColumn();
$total_unidades = $pdo->query("SELECT COUNT(*) FROM ascensores")->fetchColumn();
$total_resueltas = $pdo->query("SELECT COUNT(*) FROM ascensor_incidencias WHERE estado = 'resuelto'")->fetchColumn();

// --- DATOS GRÁFICOS ---
$sql_estado = "SELECT estado, COUNT(*) as cantidad FROM ascensor_incidencias GROUP BY estado";
$data_estado = $pdo->query($sql_estado)->fetchAll(PDO::FETCH_ASSOC);

$sql_asc = "SELECT a.id_ascensor, a.nombre, COUNT(i.id_incidencia) as cantidad 
            FROM ascensores a 
            LEFT JOIN ascensor_incidencias i ON a.id_ascensor = i.id_ascensor 
            GROUP BY a.id_ascensor ORDER BY cantidad DESC LIMIT 10";
$data_asc = $pdo->query($sql_asc)->fetchAll(PDO::FETCH_ASSOC);

// --- DATOS TABLA (CON FILTROS) ---
$where = [];
$params = [];
if ($filtro_estado) { $where[] = "i.estado = ?"; $params[] = $filtro_estado; }
if ($filtro_ascensor) { $where[] = "i.id_ascensor = ?"; $params[] = $filtro_ascensor; }

$sql_tabla = "SELECT i.*, a.nombre as ascensor, e.nombre as empresa 
              FROM ascensor_incidencias i
              JOIN ascensores a ON i.id_ascensor = a.id_ascensor
              LEFT JOIN empresas_mantenimiento e ON i.id_empresa = e.id_empresa";
if (!empty($where)) { $sql_tabla .= " WHERE " . implode(" AND ", $where); }
$sql_tabla .= " ORDER BY i.fecha_reporte DESC";

$stmt_t = $pdo->prepare($sql_tabla);
$stmt_t->execute($params);
$reportes = $stmt_t->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <?php include 'head.php'; ?>
    <title>Analítica de Ascensores</title>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <link rel="stylesheet" href="https://cdn.datatables.net/1.13.4/css/dataTables.bootstrap5.min.css">
    <style>
        .widget-click { cursor: pointer; transition: transform 0.2s; }
        .widget-click:hover { transform: scale(1.02); }
        .chart-container { position: relative; height: 300px; }
    </style>
</head>
<body class="bg-light">
    <?php include 'navbar.php'; ?>
    
    <div class="container mt-4 mb-5">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <div>
                <h2 class="fw-bold text-dark"><i class="fas fa-chart-bar text-danger"></i> Analítica de Fallas</h2>
                <?php if($filtro_estado || $filtro_ascensor): ?>
                    <a href="ascensor_estadisticas.php" class="badge bg-secondary text-decoration-none">
                        <i class="fas fa-times"></i> Quitar Filtros Activos
                    </a>
                <?php else: ?>
                    <p class="text-muted mb-0">Tablero de control operativo.</p>
                <?php endif; ?>
            </div>
            <a href="mantenimiento_ascensores.php" class="btn btn-outline-dark">Volver a Dashboard</a>
        </div>

        <div class="row g-3 mb-4">
            <div class="col-md-3">
                <div class="card widget-click border-start border-4 border-primary shadow-sm h-100" onclick="window.location='ascensor_estadisticas.php'">
                    <div class="card-body">
                        <div class="text-muted small text-uppercase fw-bold">Total Reportes</div>
                        <div class="fs-2 fw-bold text-primary"><?php echo $total_incidencias; ?></div>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card widget-click border-start border-4 border-danger shadow-sm h-100" onclick="window.location='ascensor_estadisticas.php?estado=reportado'">
                    <div class="card-body">
                        <div class="text-muted small text-uppercase fw-bold">Fallas Activas</div>
                        <div class="fs-2 fw-bold text-danger"><?php echo $total_activas; ?></div>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card border-start border-4 border-dark shadow-sm h-100">
                    <div class="card-body">
                        <div class="text-muted small text-uppercase fw-bold">Unidades</div>
                        <div class="fs-2 fw-bold text-dark"><?php echo $total_unidades; ?></div>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card widget-click border-start border-4 border-success shadow-sm h-100" onclick="window.location='ascensor_estadisticas.php?estado=resuelto'">
                    <div class="card-body">
                        <div class="text-muted small text-uppercase fw-bold">Resueltos</div>
                        <div class="fs-2 fw-bold text-success"><?php echo $total_resueltas; ?></div>
                    </div>
                </div>
            </div>
        </div>

        <div class="card shadow-sm mb-4">
            <div class="card-header bg-dark text-white d-flex justify-content-between align-items-center">
                <span><i class="fas fa-list me-2"></i> Detalle de Registros</span>
                <?php if($filtro_estado || $filtro_ascensor): ?>
                    <span class="badge bg-warning text-dark">Filtro Activo</span>
                <?php endif; ?>
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table id="tablaStats" class="table table-hover align-middle table-striped">
                        <thead class="table-light">
                            <tr>
                                <th>Orden #</th>
                                <th>Fecha</th>
                                <th>Equipo</th>
                                <th>Falla</th>
                                <th>Estado</th>
                                <th>Acción</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach($reportes as $r): ?>
                                <tr>
                                    <td><span class="badge bg-secondary"><?php echo str_pad($r['id_incidencia'], 5, '0', STR_PAD_LEFT); ?></span></td>
                                    <td><?php echo date('d/m/Y', strtotime($r['fecha_reporte'])); ?></td>
                                    <td class="fw-bold"><?php echo htmlspecialchars($r['ascensor']); ?></td>
                                    <td><?php echo htmlspecialchars($r['titulo']); ?></td>
                                    <td>
                                        <?php 
                                            $clase = 'bg-secondary';
                                            if($r['estado'] == 'resuelto') $clase = 'bg-success';
                                            elseif($r['estado'] == 'reportado') $clase = 'bg-danger';
                                            elseif($r['estado'] == 'en_proceso') $clase = 'bg-warning text-dark';
                                        ?>
                                        <span class="badge <?php echo $clase; ?>">
                                            <?php echo ucfirst(str_replace('_', ' ', $r['estado'])); ?>
                                        </span>
                                    </td>
                                    <td>
                                        <a href="ascensor_detalle.php?id=<?php echo $r['id_ascensor']; ?>" class="btn btn-sm btn-outline-dark" title="Ver Historial">
                                            <i class="fas fa-eye"></i>
                                        </a>
                                        <a href="ascensor_orden_pdf.php?id=<?php echo $r['id_incidencia']; ?>" target="_blank" class="btn btn-sm btn-danger ms-1" title="PDF">
                                            <i class="fas fa-file-pdf"></i>
                                        </a>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <div class="row">
            <div class="col-md-6 mb-4">
                <div class="card shadow-sm h-100">
                    <div class="card-header bg-white fw-bold border-bottom">Fallas por Estado (Interactiva)</div>
                    <div class="card-body">
                        <div class="chart-container">
                            <canvas id="chartEstados"></canvas>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-md-6 mb-4">
                <div class="card shadow-sm h-100">
                    <div class="card-header bg-white fw-bold border-bottom">Equipos con Más Fallas (Top 10)</div>
                    <div class="card-body">
                        <div class="chart-container">
                            <canvas id="chartEquipos"></canvas>
                        </div>
                    </div>
                </div>
            </div>
        </div>

    </div>

    <script src="https://code.jquery.com/jquery-3.5.1.js"></script>
    <script src="https://cdn.datatables.net/1.13.4/js/jquery.dataTables.min.js"></script>
    <script src="https://cdn.datatables.net/1.13.4/js/dataTables.bootstrap5.min.js"></script>
    
    <script>
        $(document).ready(function () {
            $('#tablaStats').DataTable({
                "language": { "url": "//cdn.datatables.net/plug-ins/1.13.4/i18n/es-ES.json" },
                "order": [[ 1, "desc" ]] // Ordenar por fecha descendente
            });
        });

        const estadosLabels = <?php echo json_encode(array_column($data_estado, 'estado')); ?>;
        const estadosData = <?php echo json_encode(array_column($data_estado, 'cantidad')); ?>;
        
        const equiposLabels = <?php echo json_encode(array_column($data_asc, 'nombre')); ?>;
        const equiposData = <?php echo json_encode(array_column($data_asc, 'cantidad')); ?>;
        const equiposIds = <?php echo json_encode(array_column($data_asc, 'id_ascensor')); ?>;

        const ctx1 = document.getElementById('chartEstados');
        new Chart(ctx1, {
            type: 'bar',
            data: {
                labels: estadosLabels,
                datasets: [{
                    label: 'Cantidad',
                    data: estadosData,
                    backgroundColor: ['#dc3545', '#ffc107', '#198754', '#0d6efd', '#6c757d'],
                    borderRadius: 4
                }]
            },
            options: {
                responsive: true, maintainAspectRatio: false,
                onClick: (e, activeEls) => {
                    if (activeEls.length > 0) window.location.href = `ascensor_estadisticas.php?estado=${estadosLabels[activeEls[0].index]}`;
                },
                plugins: { legend: { display: false } }
            }
        });

        const ctx2 = document.getElementById('chartEquipos');
        new Chart(ctx2, {
            type: 'bar',
            data: {
                labels: equiposLabels,
                datasets: [{
                    label: 'Fallas',
                    data: equiposData,
                    backgroundColor: '#343a40', // Color oscuro (Dark)
                    borderRadius: 4
                }]
            },
            options: {
                indexAxis: 'y', responsive: true, maintainAspectRatio: false,
                onClick: (e, activeEls) => {
                    if (activeEls.length > 0) window.location.href = `ascensor_estadisticas.php?id_ascensor=${equiposIds[activeEls[0].index]}`;
                },
                plugins: { legend: { display: false } }
            }
        });
    </script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>