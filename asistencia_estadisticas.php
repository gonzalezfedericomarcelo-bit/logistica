<?php
// Archivo: asistencia_estadisticas.php (CON TOOLTIPS Y ENLACES A PARTES)
session_start();
include 'conexion.php';
include 'funciones_permisos.php'; 

// Seguridad
$u_nombre = $_SESSION['usuario_nombre'] ?? '';
$u_rol    = $_SESSION['usuario_rol'] ?? '';
$es_autorizado = ($u_rol === 'admin' || stripos($u_nombre, 'Cañete') !== false || stripos($u_nombre, 'Ezequiel Paz') !== false || stripos($u_nombre, 'Federico') !== false);

if (!$es_autorizado) { header("Location: dashboard.php"); exit(); }

// Filtros
$fecha_inicio = $_POST['fecha_inicio'] ?? date('Y-m-d', strtotime('-15 days'));
$fecha_fin    = $_POST['fecha_fin'] ?? date('Y-m-d');
$filtro_usuario_id = $_POST['filtro_usuario_id'] ?? ''; 

// Variables
$resumen_ausencias = [];
$motivos_totales = [];
$detalle_asistencia = []; 
$dias_en_rango = [];      
$empleados_activos = [];  
$stats_campeones = [ 
    'mejor_asistencia' => ['nombre' => '-', 'valor' => -1],
    'mas_faltas' => ['nombre' => '-', 'valor' => -1],
    'mas_enfermo' => ['nombre' => '-', 'valor' => 0],
    'mas_autorizado' => ['nombre' => '-', 'valor' => 0]
];
$error = '';

try {
    $stmt_users = $pdo->query("SELECT id_usuario, nombre_completo FROM usuarios WHERE activo = 1 ORDER BY nombre_completo ASC");
    $todos_usuarios = $stmt_users->fetchAll(PDO::FETCH_ASSOC);

    // SQL: Ahora incluimos p.id_parte para poder hacer el link
    $sql_detalles = "
        SELECT 
            p.fecha,
            p.id_parte,
            u.id_usuario,
            u.nombre_completo,
            u.grado,
            d.presente,
            d.observacion_individual
        FROM asistencia_detalles d
        JOIN asistencia_partes p ON d.id_parte = p.id_parte
        JOIN usuarios u ON d.id_usuario = u.id_usuario
        WHERE p.fecha BETWEEN :inicio AND :fin
        " . ($filtro_usuario_id ? " AND u.id_usuario = :id_user" : "") . "
        ORDER BY u.grado DESC, u.nombre_completo ASC, p.fecha ASC
    ";

    $stmt = $pdo->prepare($sql_detalles);
    $params = [':inicio' => $fecha_inicio, ':fin' => $fecha_fin];
    if ($filtro_usuario_id) $params[':id_user'] = $filtro_usuario_id;
    $stmt->execute($params);
    $resultados = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Procesamiento
    $temp_stats = [];

    foreach ($resultados as $row) {
        $fecha = $row['fecha'];
        $id_user = $row['id_usuario'];
        $nombre_mostrar = $row['grado'] . " " . $row['nombre_completo'];
        $motivo = trim($row['observacion_individual']);
        $presente = (int)$row['presente'];
        $id_parte = $row['id_parte']; // ID para el link

        if (!in_array($fecha, $dias_en_rango)) $dias_en_rango[] = $fecha;
        if (!isset($empleados_activos[$id_user])) $empleados_activos[$id_user] = $nombre_mostrar;

        // Guardamos id_parte junto con el estado
        $detalle_asistencia[$id_user][$fecha] = [
            'presente' => $presente, 
            'obs' => $motivo,
            'id_parte' => $id_parte 
        ];

        // Stats
        if (!isset($temp_stats[$id_user])) {
            $temp_stats[$id_user] = [
                'nombre' => $nombre_mostrar,
                'dias_totales' => 0, 'asistencias' => 0, 'faltas' => 0,
                'enfermo' => 0, 'autorizado' => 0, 'motivos_detalle' => []
            ];
        }
        $temp_stats[$id_user]['dias_totales']++;
        if ($presente == 1) {
            $temp_stats[$id_user]['asistencias']++;
        } else {
            $temp_stats[$id_user]['faltas']++;
            if ($motivo) {
                if (!isset($motivos_totales[$motivo])) $motivos_totales[$motivo] = 0;
                $motivos_totales[$motivo]++;
                if (!isset($temp_stats[$id_user]['motivos_detalle'][$motivo])) $temp_stats[$id_user]['motivos_detalle'][$motivo] = 0;
                $temp_stats[$id_user]['motivos_detalle'][$motivo]++;
                if (stripos($motivo, 'enferm') !== false || stripos($motivo, 'medico') !== false) $temp_stats[$id_user]['enfermo']++;
                if (stripos($motivo, 'autoriz') !== false) $temp_stats[$id_user]['autorizado']++;
            }
        }
    }
    
    sort($dias_en_rango);

    // Calcular Campeones
    $mejor_porcentaje = -1; $mas_faltas = -1; $mas_enfermo = -1; $mas_autorizado = -1;
    foreach ($temp_stats as $uid => $s) {
        $porcentaje = ($s['dias_totales'] > 0) ? ($s['asistencias'] / $s['dias_totales']) * 100 : 0;
        if ($porcentaje > $mejor_porcentaje) { $mejor_porcentaje = $porcentaje; $stats_campeones['mejor_asistencia'] = ['nombre' => $s['nombre'], 'valor' => number_format($porcentaje, 0) . '%']; }
        if ($s['faltas'] > $mas_faltas) { $mas_faltas = $s['faltas']; $stats_campeones['mas_faltas'] = ['nombre' => $s['nombre'], 'valor' => $s['faltas']]; }
        if ($s['enfermo'] > $mas_enfermo) { $mas_enfermo = $s['enfermo']; $stats_campeones['mas_enfermo'] = ['nombre' => $s['nombre'], 'valor' => $s['enfermo']]; }
        if ($s['autorizado'] > $mas_autorizado) { $mas_autorizado = $s['autorizado']; $stats_campeones['mas_autorizado'] = ['nombre' => $s['nombre'], 'valor' => $s['autorizado']]; }
        if ($s['faltas'] > 0) $resumen_ausencias[$uid] = $s;
    }

} catch (PDOException $e) {
    $error = "Error: " . $e->getMessage();
}

include 'navbar.php';
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Tablero de Control</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <style>
        .card-stat { transition: transform 0.2s; border-left: 5px solid #ccc; }
        .card-stat:hover { transform: translateY(-5px); }
        .border-l-success { border-left-color: #198754; }
        .border-l-danger { border-left-color: #dc3545; }
        .border-l-warning { border-left-color: #ffc107; }
        .border-l-info { border-left-color: #0dcaf0; }
        
        .table-attendance th, .table-attendance td { text-align: center; vertical-align: middle; font-size: 0.8rem; padding: 0.3rem; min-width: 45px; }
        .table-attendance th:first-child, .table-attendance td:first-child { text-align: left; min-width: 200px; position: sticky; left: 0; background-color: #f8f9fa; z-index: 10; border-right: 2px solid #dee2e6; }
        .scrollable-table { overflow-x: auto; width: 100%; box-shadow: inset -5px 0 5px -5px rgba(0,0,0,0.2); }
        
        .presente { color: #198754; background-color: #f0fff4; font-size: 1.2rem; } 
        /* Celda Ausente: Cursor mano para indicar click */
        .ausente-celda { background-color: #fff5f5; border: 1px solid #feb2b2; cursor: pointer; transition: background-color 0.2s; }
        .ausente-celda:hover { background-color: #ffe3e3; }
        .x-roja { color: #e53e3e; font-weight: 900; font-size: 1.2rem; line-height: 1; }
        .sin-parte { background-color: #edf2f7; }
    </style>
</head>
<body>
    <div class="container mt-4 mb-5">
        <div class="row mb-4 g-3">
            <div class="col-md-3 col-6"><div class="card card-stat border-l-success shadow-sm h-100"><div class="card-body p-3"><div class="text-muted small fw-bold">ASISTENCIA PERFECTA</div><div class="h5 mb-0 text-success mt-1"><?php echo $stats_campeones['mejor_asistencia']['nombre']; ?></div><div class="small text-secondary"><?php echo $stats_campeones['mejor_asistencia']['valor']; ?></div></div></div></div>
            <div class="col-md-3 col-6"><div class="card card-stat border-l-danger shadow-sm h-100"><div class="card-body p-3"><div class="text-muted small fw-bold">MÁS INASISTENCIAS</div><div class="h5 mb-0 text-danger mt-1"><?php echo $stats_campeones['mas_faltas']['nombre']; ?></div><div class="small text-secondary"><?php echo $stats_campeones['mas_faltas']['valor']; ?> días</div></div></div></div>
            <div class="col-md-3 col-6"><div class="card card-stat border-l-warning shadow-sm h-100"><div class="card-body p-3"><div class="text-muted small fw-bold">MÁS PARTE MÉDICO</div><div class="h5 mb-0 text-dark mt-1"><?php echo $stats_campeones['mas_enfermo']['nombre']; ?></div><div class="small text-secondary"><?php echo $stats_campeones['mas_enfermo']['valor']; ?> veces</div></div></div></div>
            <div class="col-md-3 col-6"><div class="card card-stat border-l-info shadow-sm h-100"><div class="card-body p-3"><div class="text-muted small fw-bold">MÁS AUTORIZADOS</div><div class="h5 mb-0 text-dark mt-1"><?php echo $stats_campeones['mas_autorizado']['nombre']; ?></div><div class="small text-secondary"><?php echo $stats_campeones['mas_autorizado']['valor']; ?> veces</div></div></div></div>
        </div>

        <div class="card shadow">
            <div class="card-header bg-dark text-white d-flex justify-content-between align-items-center">
                <h5 class="mb-0"><i class="fas fa-chart-pie me-2"></i> ESTADÍSTICAS DETALLADAS</h5>
                <button class="btn btn-sm btn-secondary" type="button" data-bs-toggle="collapse" data-bs-target="#filtrosCollapse"><i class="fas fa-filter"></i> Filtros</button>
            </div>
            <div class="card-body">
                <?php if ($error): ?><div class="alert alert-danger"><?php echo $error; ?></div><?php endif; ?>

                <div class="collapse show" id="filtrosCollapse">
                    <form method="POST" class="row g-3 align-items-end mb-4 bg-light p-3 rounded border">
                        <div class="col-12 col-md-3"><label class="small fw-bold">Inicio</label><input type="date" name="fecha_inicio" value="<?php echo $fecha_inicio; ?>" class="form-control form-control-sm"></div>
                        <div class="col-12 col-md-3"><label class="small fw-bold">Fin</label><input type="date" name="fecha_fin" value="<?php echo $fecha_fin; ?>" class="form-control form-control-sm"></div>
                        <div class="col-12 col-md-3"><label class="small fw-bold">Personal</label><select name="filtro_usuario_id" class="form-select form-select-sm"><option value="">-- Todos --</option><?php foreach ($todos_usuarios as $user): ?><option value="<?php echo $user['id_usuario']; ?>" <?php echo ($filtro_usuario_id == $user['id_usuario']) ? 'selected' : ''; ?>><?php echo htmlspecialchars($user['nombre_completo']); ?></option><?php endforeach; ?></select></div>
                        <div class="col-12 col-md-3"><button type="submit" class="btn btn-primary btn-sm w-100"><i class="fas fa-sync-alt me-2"></i> Actualizar</button></div>
                    </form>
                </div>

                <?php if (empty($empleados_activos)): ?>
                    <div class="alert alert-info text-center">No hay datos para mostrar.</div>
                <?php else: ?>
                    
                    <div class="row">
                        <div class="col-lg-7 mb-4">
                            <h6 class="fw-bold text-secondary border-bottom pb-2"><i class="fas fa-list-ol me-2"></i> RANKING (Con Motivos)</h6>
                            <div class="table-responsive" style="max-height: 400px; overflow-y: auto;">
                                <table class="table table-sm table-hover align-middle">
                                    <thead class="table-light sticky-top"><tr><th>Grado y Nombre</th><th class="text-center">Faltas</th><th>Detalle</th></tr></thead>
                                    <tbody>
                                        <?php 
                                        usort($resumen_ausencias, function($a, $b) { return $b['faltas'] <=> $a['faltas']; });
                                        foreach ($resumen_ausencias as $uid => $data): 
                                        ?>
                                        <tr>
                                            <td class="fw-bold small"><?php echo htmlspecialchars($data['nombre']); ?></td>
                                            <td class="text-center"><span class="badge bg-danger rounded-pill"><?php echo $data['faltas']; ?></span></td>
                                            <td class="small lh-1">
                                                <?php foreach ($data['motivos_detalle'] as $mot => $c) echo "<div class='text-muted'>• " . htmlspecialchars($mot) . " <b>($c)</b></div>"; ?>
                                            </td>
                                        </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>

                        <div class="col-lg-5 mb-4">
                            <div class="card shadow-sm border-0 bg-light">
                                <div class="card-body text-center">
                                    <h6 class="fw-bold text-secondary mb-3">Distribución de Motivos</h6>
                                    <div style="position: relative; height:200px; width:100%">
                                        <canvas id="motivosChart"></canvas>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <h6 class="mt-2 mb-3 fw-bold text-secondary border-bottom pb-2"><i class="fas fa-calendar-alt me-2"></i> ASISTENCIA DIARIA (Click en X para ver Parte)</h6>
                    <div class="scrollable-table border rounded shadow-sm mb-5">
                        <table class="table table-bordered table-attendance mb-0">
                            <thead class="bg-light">
                                <tr>
                                    <th class="bg-light">PERSONAL</th>
                                    <?php foreach ($dias_en_rango as $f) echo "<th class='bg-light'>" . date('d/m', strtotime($f)) . "</th>"; ?>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($empleados_activos as $uid => $nom): ?>
                                <tr>
                                    <td class="fw-bold text-dark"><?php echo htmlspecialchars($nom); ?></td>
                                    <?php foreach ($dias_en_rango as $f): 
                                        $d = $detalle_asistencia[$uid][$f] ?? null;
                                        if ($d === null): ?>
                                            <td class="sin-parte"></td>
                                        <?php elseif ($d['presente'] == 1): ?>
                                            <td class="presente"><i class="fas fa-check"></i></td>
                                        <?php else: 
                                            $motivo = htmlspecialchars($d['obs']);
                                            $id_parte_link = $d['id_parte'];
                                        ?>
                                            <td class="ausente-celda" 
                                                data-bs-toggle="tooltip" 
                                                data-bs-placement="top" 
                                                title="<?php echo $motivo ?: 'Sin motivo'; ?>"
                                                onclick="window.open('asistencia_pdf.php?id=<?php echo $id_parte_link; ?>', '_blank')"
                                            >
                                                <div class="x-roja">✕</div>
                                            </td>
                                        <?php endif; ?>
                                    <?php endforeach; ?>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>

                <?php endif; ?>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // Inicializar Tooltips de Bootstrap
            var tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
            var tooltipList = tooltipTriggerList.map(function (tooltipTriggerEl) {
                return new bootstrap.Tooltip(tooltipTriggerEl);
            });

            // Gráfico
            const motivosData = <?php echo json_encode(array_filter($motivos_totales, function($v) { return $v > 0; })); ?>;
            const labels = Object.keys(motivosData);
            const dataValues = Object.values(motivosData);
            const bgColors = ['#dc3545', '#fd7e14', '#ffc107', '#198754', '#0dcaf0', '#6f42c1', '#adb5bd', '#20c997'];
            
            const ctx = document.getElementById('motivosChart');
            if (ctx && labels.length > 0) {
                new Chart(ctx, {
                    type: 'doughnut', 
                    data: {
                        labels: labels,
                        datasets: [{
                            data: dataValues,
                            backgroundColor: bgColors.slice(0, labels.length),
                            borderWidth: 2,
                            hoverOffset: 10
                        }]
                    },
                    options: {
                        responsive: true,
                        maintainAspectRatio: false,
                        plugins: {
                            legend: { position: 'right', labels: { boxWidth: 12, font: { size: 10 } } }
                        }
                    }
                });
            }
        });
    </script>
</body>
</html>