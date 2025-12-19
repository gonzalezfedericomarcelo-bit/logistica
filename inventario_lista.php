<?php
// Archivo: inventario_lista.php (TU DISEÑO ORIGINAL RESTAURADO + CHECKBOXES)
session_start();
include 'conexion.php';
include 'funciones_permisos.php';

if (!isset($_SESSION['usuario_id']) || !tiene_permiso('acceso_inventario', $pdo)) {
    header("Location: dashboard.php"); exit();
}

// Lógica de vencimientos automática
try {
    $pdo->exec("UPDATE inventario_cargos SET id_estado_fk = (SELECT id_estado FROM inventario_estados WHERE nombre='Carga Vencida' LIMIT 1) WHERE mat_tipo_carga_id IS NOT NULL AND mat_fecha_carga IS NOT NULL AND DATE_ADD(mat_fecha_carga, INTERVAL 1 YEAR) < CURDATE() AND id_estado_fk NOT IN (SELECT id_estado FROM inventario_estados WHERE nombre IN ('Para Baja', 'Baja', 'Carga Vencida', 'Prueba Vencida'))");
    $pdo->exec("UPDATE inventario_cargos SET id_estado_fk = (SELECT id_estado FROM inventario_estados WHERE nombre='Prueba Vencida' LIMIT 1) WHERE mat_tipo_carga_id IS NOT NULL AND mat_fecha_ph IS NOT NULL AND DATE_ADD(mat_fecha_ph, INTERVAL 1 YEAR) < CURDATE() AND id_estado_fk NOT IN (SELECT id_estado FROM inventario_estados WHERE nombre IN ('Para Baja', 'Baja', 'Prueba Vencida'))");
} catch (Exception $e) { }

// Config
$conf = $pdo->query("SELECT clave, valor FROM inventario_config_general")->fetchAll(PDO::FETCH_KEY_PAIR);
$conf_vida_util_meses = $conf['alerta_vida_util_meses'] ?? 12;
$conf_carga_dias = $conf['alerta_vencimiento_carga_dias'] ?? 30;
$conf_ph_dias = $conf['alerta_vencimiento_ph_dias'] ?? 30;

// Filtros
$where = "1=1"; $params = [];
$q = isset($_GET['q']) ? trim($_GET['q']) : '';
if (!empty($q)) { $where .= " AND (i.elemento LIKE :q OR i.codigo_inventario LIKE :q OR i.mat_numero_grabado LIKE :q OR i.nombre_responsable LIKE :q)"; $params[':q'] = "%$q%"; }
if (!empty($_GET['ubicacion'])) { $where .= " AND i.servicio_ubicacion = :ubi"; $params[':ubi'] = $_GET['ubicacion']; }
if (!empty($_GET['estado'])) { $where .= " AND e.nombre = :est_nom"; $params[':est_nom'] = $_GET['estado']; }
if (!empty($_GET['tipo_bien'])) {
    if ($_GET['tipo_bien'] == 'Matafuegos') $where .= " AND i.mat_tipo_carga_id IS NOT NULL";
    elseif ($_GET['tipo_bien'] == 'General') $where .= " AND i.mat_tipo_carga_id IS NULL";
    else { $where .= " AND (SELECT tipo_carga FROM inventario_config_matafuegos WHERE id_config = i.mat_tipo_carga_id) = :tipo_nom"; $params[':tipo_nom'] = $_GET['tipo_bien']; }
}
$f_kpi = $_GET['kpi'] ?? '';
if ($f_kpi == 'vencidos') $where .= " AND e.nombre IN ('Carga Vencida', 'Prueba Vencida')";
elseif ($f_kpi == 'activos') $where .= " AND e.nombre = 'Activo'";
elseif ($f_kpi == 'prox_carga') { $where .= " AND i.mat_tipo_carga_id IS NOT NULL AND i.mat_fecha_carga IS NOT NULL AND DATEDIFF(DATE_ADD(i.mat_fecha_carga, INTERVAL 1 YEAR), CURDATE()) BETWEEN 0 AND :dias_carga"; $params[':dias_carga'] = $conf_carga_dias; }
elseif ($f_kpi == 'prox_ph') { $where .= " AND i.mat_tipo_carga_id IS NOT NULL AND i.mat_fecha_ph IS NOT NULL AND DATEDIFF(DATE_ADD(i.mat_fecha_ph, INTERVAL 1 YEAR), CURDATE()) BETWEEN 0 AND :dias_ph"; $params[':dias_ph'] = $conf_ph_dias; }
elseif ($f_kpi == 'vida_util') { $where .= " AND i.mat_tipo_carga_id IS NOT NULL AND i.vida_util_limite IS NOT NULL AND (i.vida_util_limite - YEAR(CURDATE())) * 12 <= :meses_alerta AND e.nombre NOT IN ('Para Baja', 'Baja')"; $params[':meses_alerta'] = $conf_vida_util_meses; }

function contar($pdo, $cond, $p = []) { $stmt = $pdo->prepare("SELECT COUNT(*) FROM inventario_cargos i LEFT JOIN inventario_estados e ON i.id_estado_fk=e.id_estado WHERE $cond"); $stmt->execute($p); return $stmt->fetchColumn(); }
$kpi_activos = contar($pdo, "e.nombre = 'Activo'");
$kpi_prox_carga = contar($pdo, "i.mat_tipo_carga_id IS NOT NULL AND i.mat_fecha_carga IS NOT NULL AND DATEDIFF(DATE_ADD(i.mat_fecha_carga, INTERVAL 1 YEAR), CURDATE()) BETWEEN 0 AND ?", [$conf_carga_dias]);
$kpi_prox_ph = contar($pdo, "i.mat_tipo_carga_id IS NOT NULL AND i.mat_fecha_ph IS NOT NULL AND DATEDIFF(DATE_ADD(i.mat_fecha_ph, INTERVAL 1 YEAR), CURDATE()) BETWEEN 0 AND ?", [$conf_ph_dias]);
$kpi_vencidos = contar($pdo, "e.nombre IN ('Carga Vencida', 'Prueba Vencida')");
$kpi_vidautil = contar($pdo, "i.mat_tipo_carga_id IS NOT NULL AND i.vida_util_limite IS NOT NULL AND (i.vida_util_limite - YEAR(CURDATE())) * 12 <= ? AND e.nombre NOT IN ('Para Baja', 'Baja')", [$conf_vida_util_meses]);

// Gráficos
$chart_total_tipo = $pdo->query("SELECT IFNULL(tm.tipo_carga, 'General') as label, COUNT(*) as data FROM inventario_cargos i LEFT JOIN inventario_config_matafuegos tm ON i.mat_tipo_carga_id = tm.id_config GROUP BY label ORDER BY data DESC")->fetchAll(PDO::FETCH_ASSOC);
$sql_historial = "SELECT tipo_movimiento as label, COUNT(*) as data FROM historial_movimientos WHERE tipo_movimiento = 'Mantenimiento' GROUP BY label";
try { $chart_mant_tipo = $pdo->query($sql_historial)->fetchAll(PDO::FETCH_ASSOC); } catch (Exception $e) { $chart_mant_tipo = []; }

// Listado
$sql = "SELECT i.*, e.nombre as nombre_estado, e.color_badge FROM inventario_cargos i LEFT JOIN inventario_estados e ON i.id_estado_fk = e.id_estado WHERE $where ORDER BY i.fecha_creacion DESC LIMIT 1000";
$stmt = $pdo->prepare($sql); $stmt->execute($params); $inventario = $stmt->fetchAll(PDO::FETCH_ASSOC);
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
        .card-kpi-small { border: none; border-radius: 10px; transition: transform 0.2s; overflow: hidden; position: relative; text-decoration: none; color: white !important; padding: 15px; min-height: 100px; display: flex; flex-direction: column; justify-content: center; }
        .card-kpi-small:hover { transform: translateY(-5px); box-shadow: 0 5px 15px rgba(0,0,0,0.2); filter: brightness(1.05); }
        .card-kpi-small .icon-bg { position: absolute; right: 10px; bottom: 10px; font-size: 2.5rem; opacity: 0.2; }
        .card-kpi-small .h3 { font-weight: 800; font-size: 2rem; margin: 0; text-shadow: 1px 1px 2px rgba(0,0,0,0.1); }
        .card-kpi-small .label-text { font-weight: 600; font-size: 0.75rem; text-transform: uppercase; letter-spacing: 0.5px; opacity: 0.95; }
        .chart-container { position: relative; height: 250px; width: 100%; }
        .blink { animation: blinker 1.5s linear infinite; font-weight: bold; }
        @keyframes blinker { 50% { opacity: 0.5; } }
        .btn-icon-action { width: 34px; height: 34px; border-radius: 50%; display: inline-flex; align-items: center; justify-content: center; border: 1px solid transparent; margin: 0 2px; transition: all 0.2s; text-decoration: none; }
        .btn-icon-action:hover { transform: scale(1.1); }
        .btn-pdf { color: #dc3545; background: #fff; border-color: #dc3545; }
        .btn-pdf:hover { background: #dc3545; color: #fff; }
        .btn-edit { color: #0d6efd; background: #fff; border-color: #0d6efd; }
        .btn-edit:hover { background: #0d6efd; color: #fff; }
        .btn-service { color: #fd7e14; background: #fff; border-color: #fd7e14; }
        .btn-service:hover { background: #fd7e14; color: #fff; }
        .btn-del { color: #6c757d; background: #fff; border-color: #6c757d; }
        .btn-del:hover { background: #6c757d; color: #fff; }
        .btn-transfer { color: #ffc107; background: #fff; border-color: #ffc107; }
        .btn-transfer:hover { background: #ffc107; color: #fff; }
        .btn-baja { color: #212529; background: #fff; border-color: #212529; }
        .btn-baja:hover { background: #212529; color: #fff; }
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
                <a href="inventario_movimientos.php" class="btn btn-info text-white shadow-sm"><i class="fas fa-history me-2"></i> Historial</a>
                <a href="inventario_mantenimiento.php" class="btn btn-warning fw-bold text-dark shadow-sm"><i class="fas fa-tools me-2"></i>Solicitar Servicio Técnico</a>
                <a href="inventario_movimientos.php?tipo_movimiento=Transferencia" class="btn btn-outline-dark shadow-sm fw-bold"><i class="fas fa-exchange-alt me-2"></i> Historial Transferencias</a>
                <a href="inventario_nuevo.php" class="btn btn-primary fw-bold shadow-sm"><i class="fas fa-plus-circle me-2"></i> Nuevo</a>
                <a href="importar_datos.php" class="btn btn-dark fw-bold shadow-sm"><i class="fas fa-file-import me-2"></i> Importar Masivo</a>
            </div>
        </div>

        <div class="row row-cols-1 row-cols-sm-2 row-cols-md-5 g-3 mb-4">
            <div class="col"><a href="?kpi=activos" class="card-kpi-small bg-success shadow-sm"><div class="label-text">Total Activos</div><div class="h3"><?php echo $kpi_activos; ?></div><i class="fas fa-check-circle icon-bg"></i></a></div>
            <div class="col"><a href="?kpi=prox_carga" class="card-kpi-small bg-warning text-dark shadow-sm" style="background-color: #ffc107 !important;"><div class="label-text text-dark">Prox. Vencer Carga</div><div class="h3 text-dark"><?php echo $kpi_prox_carga; ?></div><i class="fas fa-battery-half icon-bg text-dark"></i></a></div>
            <div class="col"><a href="?kpi=prox_ph" class="card-kpi-small bg-info text-white shadow-sm"><div class="label-text">Prox. Vencer PH</div><div class="h3"><?php echo $kpi_prox_ph; ?></div><i class="fas fa-flask icon-bg"></i></a></div>
            <div class="col"><a href="?kpi=vencidos" class="card-kpi-small bg-danger shadow-sm"><div class="label-text">Vencidos Total</div><div class="h3"><?php echo $kpi_vencidos; ?></div><i class="fas fa-exclamation-triangle icon-bg"></i></a></div>
            <div class="col"><a href="?kpi=vida_util" class="card-kpi-small bg-dark shadow-sm"><div class="label-text">Alerta Vida Útil</div><div class="h3"><?php echo $kpi_vidautil; ?></div><i class="fas fa-hourglass-end icon-bg"></i></a></div>
        </div>

         <div class="card shadow border-0 rounded-4">
            <div class="card-header bg-white py-3 border-bottom">
                <form method="GET" class="row g-2 align-items-center">
                    <div class="col-md-4"><div class="input-group"><span class="input-group-text bg-light border-end-0"><i class="fas fa-search text-muted"></i></span><input type="text" name="q" class="form-control border-start-0 bg-light" placeholder="Buscar..." value="<?php echo htmlspecialchars($q); ?>"></div></div>
                    <div class="col-md-3"><select name="ubicacion" class="form-select bg-light"><option value="">-- Ubicación --</option><?php foreach($lista_lugares as $u): ?><option value="<?php echo htmlspecialchars($u); ?>" <?php echo (isset($_GET['ubicacion']) && $_GET['ubicacion']==$u)?'selected':''; ?>><?php echo htmlspecialchars($u); ?></option><?php endforeach; ?></select></div>
                    <div class="col-md-3"><select name="estado" class="form-select bg-light"><option value="">-- Estado --</option><?php foreach($lista_estados as $e): ?><option value="<?php echo htmlspecialchars($e); ?>" <?php echo (isset($_GET['estado']) && $_GET['estado']==$e)?'selected':''; ?>><?php echo htmlspecialchars($e); ?></option><?php endforeach; ?></select></div>
                    <div class="col-md-2 d-grid"><button type="submit" class="btn btn-dark fw-bold">Filtrar</button></div>
                    <?php if(!empty($_GET)): ?><div class="col-12 mt-1"><a href="inventario_lista.php" class="text-decoration-none small text-danger"><i class="fas fa-times"></i> Limpiar</a></div><?php endif; ?>
                </form>
            </div>
            
            <div class="card-body p-0">
                <form action="inventario_eliminar_masivo.php" method="POST" id="formMasivo" onsubmit="return confirm('ATENCIÓN: ¿Seguro que deseas eliminar los ítems seleccionados? Esta acción borrará historiales y fichas técnicas definitivamente.');">
                    
                    <div id="toolbarEliminar" class="bg-danger text-white p-2 text-center" style="display:none;">
                        <button type="submit" class="btn btn-light btn-sm fw-bold text-danger">
                            <i class="fas fa-trash-alt me-1"></i> ELIMINAR SELECCIONADOS (<span id="countSel">0</span>)
                        </button>
                    </div>

                    <div class="table-responsive">
                        <table id="tablaInventario" class="table table-hover align-middle mb-0 w-100">
                            <thead class="bg-light small text-uppercase text-muted">
                                <tr>
                                    <th class="text-center" width="40">
                                        <input type="checkbox" id="checkTodos" class="form-check-input" style="cursor:pointer; transform: scale(1.2);">
                                    </th>
                                    <th>Estado</th>
                                    <th>Códigos</th>
                                    <th>Descripción</th>
                                    <th>Destino (Sede)</th> <th>Área / Ubicación</th> <th>Vencimientos</th>
                                    <th class="text-center" style="min-width: 180px;">Acciones</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach($inventario as $i): ?>
                                    <tr>
                                        <td class="text-center">
                                            <input type="checkbox" name="ids[]" value="<?php echo $i['id_cargo']; ?>" class="form-check-input checkItem" style="cursor:pointer; transform: scale(1.2);">
                                        </td>
                                        
                                        <td>
                                            <span class="badge <?php echo $i['color_badge'] ?? 'bg-secondary'; ?> rounded-pill px-3 py-2">
                                                <?php echo htmlspecialchars($i['nombre_estado'] ?? 'S/A'); ?>
                                            </span>
                                        </td>
                                        <td>
                                            <div class="d-flex flex-column">
                                                <?php if($i['codigo_inventario']): ?><div class="text-primary fw-bold small"><i class="fas fa-hashtag me-1 opacity-50"></i><?php echo $i['codigo_inventario']; ?></div><?php endif; ?>
                                                <?php if($i['mat_numero_grabado']): ?><div class="text-danger fw-bold small"><i class="fas fa-fire-extinguisher me-1 opacity-50"></i><?php echo $i['mat_numero_grabado']; ?></div><?php endif; ?>
                                            </div>
                                        </td>
                                        <td>
                                            <div class="fw-bold text-dark fs-6"><?php echo htmlspecialchars($i['elemento']); ?></div>
                                            <?php if($i['observaciones']): ?><small class="text-muted text-truncate d-block mt-1" style="max-width:250px;"><?php echo htmlspecialchars($i['observaciones']); ?></small><?php endif; ?>
                                        </td>
                                        
                                        <td>
                                            <?php if(!empty($i['destino_principal'])): ?>
                                                <div class="fw-bold text-dark"><i class="fas fa-building text-secondary me-1"></i> <?php echo htmlspecialchars($i['destino_principal']); ?></div>
                                            <?php else: ?>
                                                <span class="text-muted small">-</span>
                                            <?php endif; ?>
                                        </td>

                                        <td>
                                            <?php if(!empty($i['servicio_ubicacion'])): ?>
                                                <div class="text-primary"><i class="fas fa-map-marker-alt me-1"></i> <?php echo htmlspecialchars($i['servicio_ubicacion']); ?></div>
                                            <?php else: ?>
                                                <span class="text-muted small">-</span> 
                                            <?php endif; ?>
                                        </td>

                                        <td class="small">
                                            <?php 
                                                $hoy = new DateTime();
                                                if($i['mat_fecha_carga']){
                                                    $v = (new DateTime($i['mat_fecha_carga']))->modify('+1 year');
                                                    $diff = $hoy->diff($v)->days; $is_exp = $v < $hoy; $is_alert = !$is_exp && $diff <= $conf_carga_dias;
                                                    $cls = $is_exp ? 'text-danger fw-bold blink' : ($is_alert ? 'text-warning fw-bold' : 'text-success fw-bold');
                                                    echo "<div class='$cls mb-1'><i class='fas fa-battery-full me-1'></i>Carga: ".$v->format('d/m/Y')."</div>";
                                                }
                                                if($i['mat_fecha_ph']){
                                                    $v = (new DateTime($i['mat_fecha_ph']))->modify('+1 year');
                                                    $diff = $hoy->diff($v)->days; $is_exp = $v < $hoy; $is_alert = !$is_exp && $diff <= $conf_ph_dias;
                                                    $cls = $is_exp ? 'text-danger fw-bold blink' : ($is_alert ? 'text-warning fw-bold' : 'text-success fw-bold');
                                                    echo "<div class='$cls mb-1'><i class='fas fa-flask me-1'></i>PH: ".$v->format('d/m/Y')."</div>";
                                                }
                                            ?>
                                        </td>
                                        <td class="text-center">
                                            <div class="d-flex justify-content-center">
                                                <?php if(tiene_permiso('inventario_reportes', $pdo)): ?><a href="inventario_pdf.php?id=<?php echo $i['id_cargo']; ?>" target="_blank" class="btn-icon-action btn-pdf" title="Ver PDF"><i class="fas fa-file-pdf"></i></a><?php endif; ?>
                                                <?php if(tiene_permiso('inventario_editar', $pdo)): ?><a href="inventario_editar.php?id=<?php echo $i['id_cargo']; ?>" class="btn-icon-action btn-edit" title="Editar"><i class="fas fa-edit"></i></a><?php endif; ?>
                                                <?php if($i['mat_tipo_carga_id'] && tiene_permiso('inventario_mantenimiento', $pdo)): ?><a href="inventario_mantenimiento.php?id=<?php echo $i['id_cargo']; ?>" class="btn-icon-action btn-service" title="Servicio"><i class="fas fa-tools"></i></a><?php endif; ?>
                                                <?php if(tiene_permiso('inventario_transferir', $pdo)): ?><a href="inventario_transferir.php?id=<?php echo $i['id_cargo']; ?>" class="btn-icon-action btn-transfer" title="Transferir"><i class="fas fa-exchange-alt"></i></a><?php endif; ?>
                                                <?php if(tiene_permiso('inventario_baja', $pdo)): ?><a href="inventario_baja.php?id=<?php echo $i['id_cargo']; ?>" class="btn-icon-action btn-baja" title="Dar de Baja"><i class="fas fa-ban"></i></a><?php endif; ?>
                                                <?php if(tiene_permiso('inventario_eliminar', $pdo)): ?><a href="inventario_eliminar.php?id=<?php echo $i['id_cargo']; ?>" onclick="return confirm('¿Seguro? Se borrará definitivamente.')" class="btn-icon-action btn-del" title="Eliminar"><i class="fas fa-trash"></i></a><?php endif; ?>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </form>
            </div>
        </div>           
        
         <div class="row g-4 mb-4">
            <div class="col-md-6">
                <div class="card shadow border-0 h-100 rounded-4">
                    <div class="card-header bg-white border-bottom-0 pt-3 fw-bold text-primary"><i class="fas fa-chart-bar me-2"></i> Cantidad de Bienes por Tipo</div>
                    <div class="card-body"><div class="chart-container"><canvas id="chartTotalTipo"></canvas></div></div>
                </div>
            </div>
            <div class="col-md-6">
                <div class="card shadow border-0 h-100 rounded-4">
                    <div class="card-header bg-white border-bottom-0 pt-3 fw-bold text-danger"><i class="fas fa-history me-2"></i> Servicios Realizados (Histórico)</div>
                    <div class="card-body"><div class="chart-container"><canvas id="chartMantTipo"></canvas></div></div>
                </div>
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
            // Inicializar DataTable manteniendo tu configuración
            var table = $('#tablaInventario').DataTable({ 
                "language": { "url": "//cdn.datatables.net/plug-ins/1.13.4/i18n/es-ES.json" }, 
                "dom": 'rtp',
                "order": [[ 1, "desc" ]] // Ordenar por ID para ver los nuevos primero
            }); 
            
            // Lógica para mostrar botón eliminar solo cuando hay seleccionados
            $('#checkTodos').on('click', function() {
                var checked = this.checked;
                $('.checkItem').prop('checked', checked);
                toggleToolbar();
            });

            $(document).on('change', '.checkItem', function() {
                toggleToolbar();
                // Si desmarca uno, desmarcar el "Todos"
                if(!this.checked) $('#checkTodos').prop('checked', false);
            });

            function toggleToolbar() {
                var count = $('.checkItem:checked').length;
                $('#countSel').text(count);
                if(count > 0) {
                    $('#toolbarEliminar').slideDown();
                } else {
                    $('#toolbarEliminar').slideUp();
                }
            }
        });

        // TUS GRÁFICOS ORIGINALES
        const commonOptions = { responsive: true, maintainAspectRatio: false, plugins: { legend: { position: 'bottom' } } };
        const dataTotalTipo = <?php echo json_encode($chart_total_tipo); ?>;
        const dataMantTipo = <?php echo json_encode($chart_mant_tipo); ?>;
        if(document.getElementById('chartTotalTipo')) { new Chart(document.getElementById('chartTotalTipo'), { type: 'bar', data: { labels: dataTotalTipo.map(d=>d.label), datasets: [{ label: 'Total', data: dataTotalTipo.map(d=>d.data), backgroundColor: '#0d6efd' }] }, options: commonOptions }); }
        if(document.getElementById('chartMantTipo')) { new Chart(document.getElementById('chartMantTipo'), { type: 'doughnut', data: { labels: dataMantTipo.length > 0 ? dataMantTipo.map(d => d.label) : ["Sin datos"], datasets: [{ data: dataMantTipo.length > 0 ? dataMantTipo.map(d => d.data) : [0.001], backgroundColor: ['#20c997', '#ffc107', '#dc3545', '#0d6efd', '#6610f2'] }] }, options: { ...commonOptions, onClick: (e, activeEls) => { if (activeEls.length > 0) { const index = activeEls[0].index; const label = e.chart.data.labels[index]; if (label !== "Sin datos") window.location.href = `inventario_movimientos.php?tipo_movimiento=${encodeURIComponent(label)}`; } } } }); }
    </script>
    <?php include 'footer.php'; ?>
</body>
</html>