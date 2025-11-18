<?php
// Archivo: asistencia_estadisticas.php - Reporte de Ausencias y Faltas (RESPONSIVO Y DETALLADO)
session_start();
include 'conexion.php';
include 'funciones_permisos.php'; 

// Seguridad: Solo permitir a administradores, encargados, o autorizados a ver novedades
$u_nombre = $_SESSION['usuario_nombre'] ?? '';
$u_rol    = $_SESSION['usuario_rol'] ?? '';

$es_autorizado = ($u_rol === 'admin' || stripos($u_nombre, 'Cañete') !== false || stripos($u_nombre, 'Ezequiel Paz') !== false || stripos($u_nombre, 'Federico') !== false);

if (!$es_autorizado) {
    header("Location: dashboard.php");
    exit();
}

// --- Lógica de Filtros ---
// Ajustamos el rango por defecto a la última quincena
$fecha_inicio = $_POST['fecha_inicio'] ?? date('Y-m-d', strtotime('-15 days'));
$fecha_fin    = $_POST['fecha_fin'] ?? date('Y-m-d');
$filtro_usuario_id = $_POST['filtro_usuario_id'] ?? ''; // Nuevo filtro

$resumen_ausencias = [];
$motivos_totales = [];
$detalle_asistencia = []; 
$dias_en_rango = [];      
$empleados_activos = [];  
$error = '';

try {
    // Obtener todos los usuarios activos para el filtro (solo id y nombre)
    $stmt_users = $pdo->query("SELECT id_usuario, nombre_completo FROM usuarios WHERE activo = 1 ORDER BY nombre_completo ASC");
    $todos_usuarios = $stmt_users->fetchAll(PDO::FETCH_ASSOC);

    // 1. OBTENER DETALLES DE ASISTENCIA EN EL RANGO
    $sql_detalles = "
        SELECT 
            p.fecha,
            u.id_usuario,
            u.nombre_completo,
            d.presente,
            d.observacion_individual
        FROM asistencia_detalles d
        JOIN asistencia_partes p ON d.id_parte = p.id_parte
        JOIN usuarios u ON d.id_usuario = u.id_usuario
        WHERE p.fecha BETWEEN :inicio AND :fin
        " . ($filtro_usuario_id ? " AND u.id_usuario = :id_user" : "") . "
        ORDER BY u.nombre_completo ASC, p.fecha ASC
    ";

    $stmt = $pdo->prepare($sql_detalles);
    $params = [':inicio' => $fecha_inicio, ':fin' => $fecha_fin];
    if ($filtro_usuario_id) {
        $params[':id_user'] = $filtro_usuario_id;
    }
    $stmt->execute($params);
    $resultados = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // 2. PROCESAR RESULTADOS PARA RESUMEN Y DETALLE
    foreach ($resultados as $row) {
        $fecha = $row['fecha'];
        $nombre = $row['nombre_completo'];
        $id_user = $row['id_usuario']; // ID del usuario para el link
        $motivo = $row['observacion_individual'];
        $presente = (int)$row['presente'];

        // a) Acumular Fechas y Empleados únicos
        if (!in_array($fecha, $dias_en_rango)) $dias_en_rango[] = $fecha;
        
        // Usamos el nombre como clave y guardamos el ID para el resumen
        if (!isset($empleados_activos[$nombre])) $empleados_activos[$nombre] = $id_user;

        // b) Guardar Detalle Día por Día
        $detalle_asistencia[$nombre][$fecha] = [
            'presente' => $presente,
            'obs' => $motivo
        ];

        // c) Acumular Resumen (Solo si está Ausente)
        if ($presente === 0) {
            // Acumular por persona
            if (!isset($resumen_ausencias[$nombre])) {
                $resumen_ausencias[$nombre] = ['total' => 0, 'id_user' => $id_user, 'motivos' => []];
            }
            $resumen_ausencias[$nombre]['total'] += 1;
            
            // Acumular por motivo (si existe motivo)
            if ($motivo) {
                if (!isset($resumen_ausencias[$nombre]['motivos'][$motivo])) {
                    $resumen_ausencias[$nombre]['motivos'][$motivo] = 0;
                }
                $resumen_ausencias[$nombre]['motivos'][$motivo] += 1;

                // Acumular por motivo (global)
                if (!isset($motivos_totales[$motivo])) {
                    $motivos_totales[$motivo] = 0;
                }
                // Nota: Usamos el nombre del motivo como clave, y $motivos_totales[$motivo] como valor
                $motivos_totales[$motivo] += 1; 
            }
        }
    }
    
    sort($dias_en_rango);
    ksort($empleados_activos); // Ordenar por nombre del empleado para la tabla detallada

} catch (PDOException $e) {
    $error = "Error al cargar estadísticas: " . $e->getMessage();
}

// Inyectamos el ID de usuario a la sesión para que el navbar pueda usarlo si es necesario
if (isset($_SESSION['usuario_id'])) {
    $currentUserId = $_SESSION['usuario_id'];
}

include 'navbar.php';
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Reporte de Ausencias</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <style>
        .table-attendance th, .table-attendance td {
            text-align: center;
            vertical-align: middle;
            font-size: 0.75rem; 
            padding: 0.3rem;
            min-width: 50px; 
        }
        .table-attendance th:first-child, .table-attendance td:first-child {
            text-align: left;
            min-width: 150px; 
            position: sticky;
            left: 0;
            background-color: #f8f9fa; 
            z-index: 10;
        }
        .scrollable-table {
            overflow-x: auto; 
            width: 100%;
        }
        .presente { background-color: #d1e7dd; color: #0f5132; } 
        .ausente { background-color: #f8d7da; color: #842029; } 
        .sin-parte { background-color: #f7f7f7; color: #6c757d; } 
        .clickable-badge {
            cursor: pointer;
            transition: transform 0.2s;
        }
        .clickable-badge:hover {
            transform: scale(1.1);
            box-shadow: 0 0 5px rgba(220, 53, 69, 0.5);
        }
    </style>
</head>
<body>
    <div class="container mt-4 mb-5">
        <div class="card shadow">
            <div class="card-header bg-dark text-white">
                <h5 class="mb-0"><i class="fas fa-chart-bar me-2"></i> REPORTE DE AUSENCIAS Y FALTAS</h5>
            </div>
            <div class="card-body">

                <?php if ($error): ?>
                    <div class="alert alert-danger"><?php echo $error; ?></div>
                <?php endif; ?>

                <!-- Formulario de Filtros RESPONSIVO (con filtro por empleado) -->
                <form method="POST" class="row g-3 align-items-end mb-4 bg-light p-3 rounded border">
                    <div class="col-12 col-md-3">
                        <label for="fecha_inicio" class="form-label small">Fecha Inicio</label>
                        <input type="date" name="fecha_inicio" id="fecha_inicio" value="<?php echo $fecha_inicio; ?>" class="form-control form-control-sm" required>
                    </div>
                    <div class="col-12 col-md-3">
                        <label for="fecha_fin" class="form-label small">Fecha Fin</label>
                        <input type="date" name="fecha_fin" id="fecha_fin" value="<?php echo $fecha_fin; ?>" class="form-control form-control-sm" required>
                    </div>
                    <div class="col-12 col-md-3">
                        <label for="filtro_usuario_id" class="form-label small">Filtrar por Personal</label>
                        <select name="filtro_usuario_id" id="filtro_usuario_id" class="form-select form-select-sm">
                            <option value="">-- Todos --</option>
                            <?php foreach ($todos_usuarios as $user): ?>
                                <option value="<?php echo $user['id_usuario']; ?>" <?php echo ($filtro_usuario_id == $user['id_usuario']) ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($user['nombre_completo']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-12 col-md-3">
                        <button type="submit" class="btn btn-primary btn-sm w-100">
                            <i class="fas fa-filter me-2"></i> Aplicar Filtro
                        </button>
                    </div>
                    <div class="col-12 text-md-end mt-2">
                        <span class="text-muted small">Rango actual: <?php echo date('d/m/Y', strtotime($fecha_inicio)) . " al " . date('d/m/Y', strtotime($fecha_fin)); ?> (<?php echo count($dias_en_rango); ?> días)</span>
                    </div>
                </form>
                
                <hr>
                
                <a href="asistencia_listado_general.php" class="btn btn-sm btn-outline-secondary mb-3">
                    <i class="fas fa-list-ul me-2"></i> Ir a Listado General de Partes
                </a>


                <?php if (empty($empleados_activos) || empty($dias_en_rango)): ?>
                    <div class="alert alert-info text-center">No se encontraron registros de partes en el rango seleccionado.</div>
                <?php else: ?>
                    
                    <!-- TABLA ORDENADA POR FALTAS (LINK A DETALLE) -->
                    <h5 class="mt-4 mb-3 text-dark fw-bold"><i class="fas fa-sort-amount-down me-2"></i> RANKING DE AUSENCIAS POR PERSONAL</h5>
                    <div class="table-responsive">
                        <table class="table table-bordered table-striped table-hover align-middle">
                            <thead class="table-secondary">
                                <tr>
                                    <th>Personal</th>
                                    <th class="text-center">Total Días Ausente</th>
                                    <th>Detalle de Motivos</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php 
                                $ausentes_ordenados = array_filter($resumen_ausencias, function($a) { return $a['total'] > 0; });
                                usort($ausentes_ordenados, function($a, $b) {
                                    return $b['total'] <=> $a['total'];
                                });
                                
                                foreach ($ausentes_ordenados as $nombre => $data): 
                                    $id_user_ausente = $data['id_user'];
                                    $total_ausente = $data['total'];
                                    // Generar el link con los filtros
                                    $link_detalle = "asistencia_detalles_ausencias.php?user_id={$id_user_ausente}&name=" . urlencode($nombre) . "&start={$fecha_inicio}&end={$fecha_fin}";
                                ?>
                                <tr>
                                    <td class="fw-bold"><?php echo htmlspecialchars($nombre); ?></td>
                                    <td class="text-center">
                                        <?php if ($total_ausente > 0): ?>
                                            <!-- LINK AL REPORTE DETALLADO POR AUSENCIA -->
                                            <a href="<?php echo $link_detalle; ?>" class="badge bg-danger fs-6 clickable-badge">
                                                <?php echo $total_ausente; ?>
                                            </a>
                                        <?php else: ?>
                                            <span class="badge bg-success fs-6">0</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?php 
                                        $motivos_list = [];
                                        foreach ($data['motivos'] as $motivo => $count) {
                                            $motivos_list[] = htmlspecialchars($motivo) . " (" . $count . ")";
                                        }
                                        echo implode('<br>', $motivos_list);
                                        ?>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>

                    <hr class="my-4">
                    
                    <!-- RESUMEN GRÁFICOS Y TOTALES (Mantenidos) -->
                    <h5 class="mt-4 mb-3 text-dark fw-bold"><i class="fas fa-calculator me-2"></i> RESUMEN Y ESTADÍSTICAS</h5>
                    <div class="row mb-4">
                        <div class="col-12 col-lg-6 mb-3">
                            <div class="card shadow-sm h-100">
                                <div class="card-body">
                                    <h6 class="card-title text-primary">Distribución de Motivos de Falta</h6>
                                    <canvas id="motivosChart"></canvas>
                                </div>
                            </div>
                        </div>
                        <div class="col-12 col-lg-6 mb-3">
                            <div class="card shadow-sm h-100">
                                <div class="card-body">
                                    <h6 class="card-title text-primary">Total de Ausencias por Causa</h6>
                                    <ul class="list-group list-group-flush">
                                        <?php $total_general = 0; ?>
                                        <?php foreach ($motivos_totales as $motivo => $total): 
                                            if ($total > 0): 
                                                $total_general += $total; ?>
                                                <li class="list-group-item d-flex justify-content-between align-items-center">
                                                    <?php echo htmlspecialchars($motivo); ?>
                                                    <span class="badge bg-danger rounded-pill"><?php echo $total; ?></span>
                                                </li>
                                            <?php endif; 
                                        endforeach; ?>
                                        <li class="list-group-item d-flex justify-content-between align-items-center fw-bold bg-light">
                                            TOTAL GENERAL DE AUSENCIAS REGISTRADAS
                                            <span class="badge bg-dark rounded-pill"><?php echo $total_general; ?></span>
                                        </li>
                                    </ul>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- REPORTE DETALLADO DÍA POR DÍA (Mantenido) -->
                    <h5 class="mt-4 mb-3 text-dark fw-bold"><i class="fas fa-calendar-check me-2"></i> DETALLE DE ASISTENCIA DÍA POR DÍA</h5>
                    <p class="small text-muted">Deslice horizontalmente para ver todas las fechas.</p>
                    <div class="scrollable-table border rounded shadow-sm mb-5">
                        <table class="table table-bordered table-attendance mb-0">
                            <thead class="bg-light sticky-top">
                                <tr>
                                    <th style="min-width: 150px;">PERSONAL</th>
                                    <?php foreach ($dias_en_rango as $fecha): ?>
                                        <th title="<?php echo date('d/m/Y', strtotime($fecha)); ?>">
                                            <?php echo date('d/M', strtotime($fecha)); ?>
                                        </th>
                                    <?php endforeach; ?>
                                </tr>
                            </thead>
                            <tbody>
                                <?php 
                                // Recorremos los empleados activos para mostrar la fila
                                foreach (array_keys($empleados_activos) as $nombre): ?>
                                <tr>
                                    <td class="fw-bold text-nowrap"><?php echo htmlspecialchars($nombre); ?></td>
                                    <?php foreach ($dias_en_rango as $fecha): 
                                        $data = $detalle_asistencia[$nombre][$fecha] ?? null;
                                        if ($data === null): ?>
                                            <td class="sin-parte" title="Sin Parte registrado este día"></td>
                                        <?php elseif ($data['presente'] == 1): ?>
                                            <td class="presente"><i class="fas fa-check"></i></td>
                                        <?php else: ?>
                                            <td class="ausente" title="<?php echo htmlspecialchars($data['obs']); ?>">
                                                <i class="fas fa-times"></i>
                                                <div class="small text-danger" style="line-height: 1;"><?php echo htmlspecialchars($data['obs']); ?></div>
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

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const motivosData = <?php echo json_encode(array_filter($motivos_totales, function($v) { return $v > 0; })); ?>;
            const labels = Object.keys(motivosData);
            const dataValues = Object.values(motivosData);
            
            const backgroundColors = [
                '#dc3545d0', '#fd7e14d0', '#ffc107d0', '#20c997d0', '#0dcaf0d0', '#6f42c1d0', '#adb5bdd0'
            ];

            if (document.getElementById('motivosChart') && labels.length > 0) {
                new Chart(document.getElementById('motivosChart'), {
                    type: 'doughnut', 
                    data: {
                        labels: labels,
                        datasets: [{
                            data: dataValues,
                            backgroundColor: backgroundColors.slice(0, labels.length),
                            hoverOffset: 6
                        }]
                    },
                    options: {
                        responsive: true,
                        aspectRatio: 1, 
                        plugins: {
                            legend: {
                                position: 'right',
                            },
                            title: {
                                display: false,
                            }
                        }
                    }
                });
            }
        });
    </script>
</body>
</html>