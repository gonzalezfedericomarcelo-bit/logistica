<?php
// Archivo: dashboard.php (VERSIÓN FINAL INTEGRA: ESTRUCTURA CORREGIDA + NOVEDADES VISUALES + WIDGETS SÓLIDOS)
session_start();
include 'conexion.php';

// 1. SEGURIDAD
if (!isset($_SESSION['usuario_id'])) { header("Location: login.php"); exit(); }
$id_usuario = $_SESSION['usuario_id'];
$rol_usuario = $_SESSION['usuario_rol'];
$nombre_usuario = $_SESSION['usuario_nombre'] ?? 'Usuario';

// 2. HELPER IMAGEN
function get_first_image_url($html) {
    if (preg_match('/<img.*?src=["\'](.*?)["\'].*?>/i', $html, $matches)) { return $matches[1]; }
    return null;
}

// 3. HELPER SALUDO
function getGreeting() { 
    $h = date('H'); return ($h >= 5 && $h < 12) ? 'Buenos días' : (($h >= 12 && $h < 19) ? 'Buenas tardes' : 'Buenas noches'); 
}
$saludo = getGreeting();

// 4. FRASE DEL DÍA
$frases_motivadoras = [
    "¡El éxito es la suma de pequeños esfuerzos repetidos día tras día!",
    "La logística no es solo mover cosas, es mover el futuro.",
    "La calidad no es un acto, es un hábito.",
    "Si te cansas, aprende a descansar, no a renunciar.",
    "La disciplina es el puente entre las metas y el logro.",
    "No cuentes los días, haz que los días cuenten.",
    "La excelencia es hacer cosas comunes de manera poco común.",
    "La optimización es la clave para la eficiencia en cada paso."
];
if (!isset($_SESSION['frase_del_dia'])) { $_SESSION['frase_del_dia'] = $frases_motivadoras[array_rand($frases_motivadoras)]; }
$frase_del_dia = $_SESSION['frase_del_dia'];

// 5. FILTROS SQL
$sql_filtro_usuario = "";
$params_filtro = [];
if ($rol_usuario === 'empleado') {
    $sql_filtro_usuario = " AND t.id_asignado = :uid ";
    $params_filtro[':uid'] = $id_usuario;
}

// --- DATOS PARA GRÁFICOS (PHP) ---

// A. CATEGORÍA
$cat_labels = []; $cat_data = []; $cat_ids = []; $cat_colors = [];
try {
    $sql_cat = "SELECT c.id_categoria, c.nombre, COUNT(t.id_tarea) as total 
                FROM tareas t 
                LEFT JOIN categorias c ON t.id_categoria = c.id_categoria 
                WHERE t.estado NOT IN ('cancelada', 'verificada') $sql_filtro_usuario 
                GROUP BY c.id_categoria, c.nombre";
    $stmt_cat = $pdo->prepare($sql_cat); $stmt_cat->execute($params_filtro); $res_cat = $stmt_cat->fetchAll(PDO::FETCH_ASSOC);
    $colores_base = ['#4e73df', '#1cc88a', '#36b9cc', '#f6c23e', '#e74a3b', '#858796'];
    foreach($res_cat as $i => $row) { 
        $cat_labels[] = $row['nombre'] ?? 'Sin Categoría'; $cat_data[] = $row['total']; $cat_ids[] = $row['id_categoria'] ?? 0; $cat_colors[] = $colores_base[$i%count($colores_base)]; 
    }
} catch (Exception $e) {}

// B. PRIORIDAD
$prio_labels = []; $prio_data = []; $prio_colors = [];
try {
    $sql_prio = "SELECT t.prioridad, COUNT(t.id_tarea) as total FROM tareas t WHERE t.estado NOT IN ('cancelada', 'verificada') $sql_filtro_usuario GROUP BY t.prioridad ORDER BY FIELD(t.prioridad, 'urgente', 'alta', 'media', 'baja')";
    $stmt_prio = $pdo->prepare($sql_prio); $stmt_prio->execute($params_filtro); $res_prio = $stmt_prio->fetchAll(PDO::FETCH_ASSOC);
    $map_colores = ['urgente'=>'#dc3545', 'alta'=>'#fd7e14', 'media'=>'#ffc107', 'baja'=>'#198754'];
    foreach($res_prio as $row) { $p=strtolower($row['prioridad']); $prio_labels[]=ucfirst($p); $prio_data[]=$row['total']; $prio_colors[]=$map_colores[$p]??'#6c757d'; }
} catch (Exception $e) {}

// C. TENDENCIA FINALIZADAS (7 Días)
$trend_labels = []; $trend_data = [];
try {
    $sql_trend = "SELECT DATE(fecha_cierre) as fecha, COUNT(*) as total FROM tareas t WHERE t.estado = 'verificada' AND t.fecha_cierre >= DATE_SUB(CURDATE(), INTERVAL 6 DAY) $sql_filtro_usuario GROUP BY DATE(fecha_cierre)";
    $stmt_trend = $pdo->prepare($sql_trend); $stmt_trend->execute($params_filtro);
    $db_data = $stmt_trend->fetchAll(PDO::FETCH_KEY_PAIR); 
    for ($i = 6; $i >= 0; $i--) {
        $date = date('Y-m-d', strtotime("-$i days"));
        $label = date('d/m', strtotime("-$i days"));
        $trend_labels[] = $label;
        $trend_data[] = isset($db_data[$date]) ? (int)$db_data[$date] : 0;
    }
} catch (Exception $e) {}

// --- KPIs ---
$counts = ['activas'=>0, 'asignadas'=>0, 'proceso'=>0, 'revision'=>0, 'verificadas'=>0, 'atrasadas'=>0];
try {
    $sql_c = "SELECT 
        COUNT(CASE WHEN estado NOT IN ('verificada','cancelada') THEN 1 END) as activas,
        COUNT(CASE WHEN estado = 'asignada' THEN 1 END) as asignadas,
        COUNT(CASE WHEN estado = 'en_proceso' THEN 1 END) as proceso,
        COUNT(CASE WHEN estado = 'finalizada_tecnico' THEN 1 END) as revision,
        COUNT(CASE WHEN estado = 'verificada' THEN 1 END) as verificadas,
        COUNT(CASE WHEN fecha_limite < CURDATE() AND estado NOT IN ('verificada','cancelada') THEN 1 END) as atrasadas
    FROM tareas t WHERE 1=1 $sql_filtro_usuario";
    $stmt_c = $pdo->prepare($sql_c); $stmt_c->execute($params_filtro); $counts = $stmt_c->fetch(PDO::FETCH_ASSOC);
} catch (Exception $e) {}

// --- AVISOS ---
$avisos_recientes = [];
try { $stmt = $pdo->query("SELECT id_aviso, titulo, contenido, fecha_publicacion FROM avisos WHERE es_activo = 1 ORDER BY fecha_publicacion DESC LIMIT 5"); $avisos_recientes = $stmt->fetchAll(PDO::FETCH_ASSOC); } catch (Exception $e) {}

// --- EFECTIVIDAD ---
$mi_efectividad = 0;
try {
    $stmt_eff = $pdo->prepare("SELECT COUNT(*) as total, SUM(CASE WHEN estado='verificada' THEN 1 ELSE 0 END) as ok FROM tareas WHERE id_asignado = :uid AND estado != 'cancelada'");
    $stmt_eff->execute([':uid' => $id_usuario]); $row_eff = $stmt_eff->fetch(PDO::FETCH_ASSOC);
    if($row_eff['total'] > 0) $mi_efectividad = round(($row_eff['ok'] / $row_eff['total']) * 100);
} catch(Exception $e) {}

// --- VENCIMIENTOS ---
$mis_vencimientos = [];
try {
    $stmt_venc = $pdo->prepare("SELECT id_tarea, titulo, fecha_limite, prioridad FROM tareas WHERE id_asignado = :uid AND fecha_limite BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 7 DAY) AND estado NOT IN ('verificada', 'cancelada') ORDER BY fecha_limite ASC LIMIT 5");
    $stmt_venc->execute([':uid' => $id_usuario]); $mis_vencimientos = $stmt_venc->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {}

// --- RANKING ---
$ranking_users = [];
try {
    $sql_rank = "SELECT u.nombre_completo, u.foto_perfil, COUNT(t.id_tarea) as total FROM tareas t JOIN usuarios u ON t.id_asignado = u.id_usuario WHERE t.estado = 'verificada' AND MONTH(t.fecha_cierre) = MONTH(CURRENT_DATE()) GROUP BY t.id_asignado ORDER BY total DESC LIMIT 5";
    $ranking_users = $pdo->query($sql_rank)->fetchAll(PDO::FETCH_ASSOC);
} catch(Exception $e){}

// --- ASISTENCIA ---
$att_present = 0; $att_absent = 0; $att_title = "";
try {
    if ($rol_usuario === 'admin' || $rol_usuario === 'encargado') {
        $att_title = "Asistencia (Hoy)";
        $stmt_att = $pdo->query("SELECT SUM(d.presente) as pres, COUNT(d.id_detalle) - SUM(d.presente) as aus FROM asistencia_detalles d JOIN asistencia_partes p ON d.id_parte = p.id_parte WHERE p.fecha = CURDATE()");
    } else {
        $att_title = "Mi Asistencia (Mes)";
        $stmt_att = $pdo->prepare("SELECT SUM(d.presente) as pres, COUNT(d.id_detalle) - SUM(d.presente) as aus FROM asistencia_detalles d JOIN asistencia_partes p ON d.id_parte = p.id_parte WHERE d.id_usuario = :uid AND MONTH(p.fecha) = MONTH(CURRENT_DATE())");
        $stmt_att->execute([':uid' => $id_usuario]);
    }
    $res_att = $stmt_att->fetch(PDO::FETCH_ASSOC);
    $att_present = (int)($res_att['pres']??0); $att_absent = (int)($res_att['aus']??0);
} catch (Exception $e) {}

// --- WORKLOAD ---
$workload_labels = []; $workload_data = []; $workload_ids = [];
if (in_array($rol_usuario, ['admin', 'encargado', 'auxiliar'])) {
    try {
        $stmt_wl = $pdo->query("SELECT u.id_usuario, u.nombre_completo, COUNT(t.id_tarea) as total FROM usuarios u LEFT JOIN tareas t ON u.id_usuario = t.id_asignado AND t.estado NOT IN ('verificada','cancelada') WHERE u.rol IN ('empleado','auxiliar') AND u.activo = 1 GROUP BY u.id_usuario ORDER BY total DESC LIMIT 10");
        $res_wl = $stmt_wl->fetchAll(PDO::FETCH_ASSOC);
        foreach($res_wl as $w) { $parts = explode(' ', $w['nombre_completo']); $workload_labels[] = $parts[0]; $workload_data[] = $w['total']; $workload_ids[] = $w['id_usuario']; }
    } catch (Exception $e) {}
}
$lista_empleados = [];
if (in_array($rol_usuario, ['admin', 'encargado', 'auxiliar'])) {
    try { $lista_empleados = $pdo->query("SELECT id_usuario, nombre_completo FROM usuarios WHERE rol = 'empleado' AND activo = 1 ORDER BY nombre_completo")->fetchAll(PDO::FETCH_ASSOC); } catch(Exception $e){}
}

$show_loader = !isset($_SESSION['dashboard_loaded_once']);
$_SESSION['dashboard_loaded_once'] = true;
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard Logística</title>
    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.3/dist/chart.umd.min.js"></script>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">
    <style>
        body { background-color: #f4f6f9; font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; }
        #full-loader { position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: #fff; z-index: 9999; display: flex; justify-content: center; align-items: center; transition: opacity 0.5s ease-out, visibility 0.5s ease-out; }
        
        /* Cards */
        .card { border: none; border-radius: 12px; box-shadow: 0 2px 15px rgba(0,0,0,0.04); transition: transform 0.2s; }
        .hover-card:hover { transform: translateY(-4px); box-shadow: 0 8px 25px rgba(0,0,0,0.08) !important; }
        
        /* Gráficos */
        .chart-container { position: relative; height: 250px; width: 100%; }
        #categoryDoughnutChart, #priorityDoughnutChart, #trendChart { cursor: pointer; }
    </style>
</head>
<body <?php echo $show_loader ? 'style="overflow: hidden;"' : ''; ?>>

    <?php if ($show_loader): ?>
    <div id="full-loader"><div class="text-center"><div class="spinner-border text-primary mb-3" style="width: 3rem; height: 3rem;"></div><p class="text-muted">Cargando...</p></div></div>
    <?php endif; ?>

    <?php include 'navbar.php'; ?>

    <div class="container py-4 mb-5">
        
        <div class="row align-items-center mb-4">
            <div class="col-md-8">
                <div class="alert alert-primary shadow-sm mb-0 border-0">
                    <div class="d-flex align-items-center">
                        <div class="me-3 display-6"><i class="fas fa-hand-paper"></i></div>
                        <div>
                            <h4 class="alert-heading mb-1 fw-bold"><?php echo $saludo; ?>, <?php echo htmlspecialchars($nombre_usuario); ?>.</h4>
                            <p class="mb-0 fst-italic opacity-75">"<?php echo htmlspecialchars($frase_del_dia); ?>"</p>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-md-4 mt-3 mt-md-0">
                <div class="card bg-white shadow-sm h-100 border-0" id="weatherCard" style="display:none;">
                    <div class="card-body p-3 d-flex align-items-center justify-content-between">
                        <div><h6 class="mb-0 fw-bold text-dark" id="weatherLocation">Ubicación</h6><small id="weatherDesc" class="text-muted">Cargando...</small></div>
                        <div class="text-end"><h2 class="mb-0 fw-bold text-primary" id="weatherTemp">--°</h2></div>
                        <div id="weatherIcon" style="font-size: 2rem;"></div>
                    </div>
                </div>
            </div>
        </div>

        <h6 class="text-uppercase text-muted fw-bold small mb-3 ps-1">Resumen Operativo</h6>
        <div class="row row-cols-1 row-cols-md-3 row-cols-lg-6 g-4 mb-5">
            <div class="col"><div class="card bg-primary text-white h-100 shadow-sm hover-card"><div class="card-body"><div class="d-flex justify-content-between align-items-center"><div><h6 class="text-uppercase mb-2" style="font-size:0.75rem;">Total Activas</h6><h2 class="display-5 fw-bold mb-0"><?php echo $counts['activas']; ?></h2></div><i class="fas fa-list-check fa-2x opacity-50"></i></div><hr class="mt-2 mb-2 opacity-25"><a href="tareas_lista.php?estado=todas" class="text-white small fw-bold text-decoration-none stretched-link">Ver Activas <i class="fas fa-arrow-circle-right ms-1"></i></a></div></div></div>
            <div class="col"><div class="card bg-info text-white h-100 shadow-sm hover-card"><div class="card-body"><div class="d-flex justify-content-between align-items-center"><div><h6 class="text-uppercase mb-2" style="font-size:0.75rem;">Pendientes</h6><h2 class="display-5 fw-bold mb-0"><?php echo $counts['asignadas']; ?></h2></div><i class="fas fa-user-tag fa-2x opacity-50"></i></div><hr class="mt-2 mb-2 opacity-25"><a href="tareas_lista.php?estado=asignada" class="text-white small fw-bold text-decoration-none stretched-link">Ver Pendientes <i class="fas fa-arrow-circle-right ms-1"></i></a></div></div></div>
            <div class="col"><div class="card bg-warning text-dark h-100 shadow-sm hover-card"><div class="card-body"><div class="d-flex justify-content-between align-items-center"><div><h6 class="text-uppercase mb-2" style="font-size:0.75rem;">En Curso</h6><h2 class="display-5 fw-bold mb-0"><?php echo $counts['proceso']; ?></h2></div><i class="fas fa-tools fa-2x opacity-50"></i></div><hr class="mt-2 mb-2 opacity-25"><a href="tareas_lista.php?estado=en_proceso" class="text-dark small fw-bold text-decoration-none stretched-link">Ver en Curso <i class="fas fa-arrow-circle-right ms-1"></i></a></div></div></div>
            <div class="col"><div class="card bg-secondary text-white h-100 shadow-sm hover-card"><div class="card-body"><div class="d-flex justify-content-between align-items-center"><div><h6 class="text-uppercase mb-2" style="font-size:0.75rem;">Revisión</h6><h2 class="display-5 fw-bold mb-0"><?php echo $counts['revision']; ?></h2></div><i class="fas fa-search-plus fa-2x opacity-50"></i></div><hr class="mt-2 mb-2 opacity-25"><a href="tareas_lista.php?estado=finalizada_tecnico" class="text-white small fw-bold text-decoration-none stretched-link">Ver Revisión <i class="fas fa-arrow-circle-right ms-1"></i></a></div></div></div>
            <div class="col"><div class="card bg-danger text-white h-100 shadow-sm hover-card"><div class="card-body"><div class="d-flex justify-content-between align-items-center"><div><h6 class="text-uppercase mb-2" style="font-size:0.75rem;">Atrasadas</h6><h2 class="display-5 fw-bold mb-0"><?php echo $counts['atrasadas']; ?></h2></div><i class="fas fa-clock fa-2x opacity-50"></i></div><hr class="mt-2 mb-2 opacity-25"><a href="tareas_lista.php?estado=atrasadas" class="text-white small fw-bold text-decoration-none stretched-link">Ver Atrasadas <i class="fas fa-arrow-circle-right ms-1"></i></a></div></div></div>
            <div class="col"><div class="card bg-success text-white h-100 shadow-sm hover-card"><div class="card-body"><div class="d-flex justify-content-between align-items-center"><div><h6 class="text-uppercase mb-2" style="font-size:0.75rem;">Cerradas</h6><h2 class="display-5 fw-bold mb-0"><?php echo $counts['verificadas']; ?></h2></div><i class="fas fa-calendar-check fa-2x opacity-50"></i></div><hr class="mt-2 mb-2 opacity-25"><a href="tareas_lista.php?estado=verificada" class="text-white small fw-bold text-decoration-none stretched-link">Historial <i class="fas fa-arrow-circle-right ms-1"></i></a></div></div></div>
        </div>

        <h6 class="text-uppercase text-muted fw-bold small mb-3 ps-1">Análisis y Novedades</h6>
        <div class="row g-4 mb-4">
             
             <div class="col-lg-4"> 
                 <div class="card shadow h-100 border-0"> 
                     <div class="card-header bg-dark text-white d-flex justify-content-between align-items-center py-3"> 
                        <h6 class="m-0 fw-bold"><i class="fas fa-bullhorn text-warning me-2"></i> NOVEDADES</h6>
                        <a href="avisos.php" class="btn btn-sm btn-outline-light rounded-pill px-3" style="font-size: 0.7rem;">VER TODO</a>
                     </div> 
                     
                     <div class="list-group list-group-flush">
                        <?php if(!empty($avisos_recientes)): foreach($avisos_recientes as $aviso): 
                            // Lógica Visual
                            $img_src = get_first_image_url($aviso['contenido']);
                            $colores = ['primary', 'success', 'danger', 'warning', 'info', 'dark'];
                            $bg_color = $colores[$aviso['id_aviso'] % count($colores)];
                        ?>
                        <a href="avisos.php?show_id=<?php echo $aviso['id_aviso'];?>" class="list-group-item list-group-item-action py-3 border-0 border-bottom d-flex align-items-center" style="transition:background 0.2s">
                            <div class="flex-shrink-0 me-3">
                                <?php if($img_src): ?>
                                    <div style="width: 48px; height: 48px; border-radius: 8px; overflow: hidden; border: 1px solid #eee;">
                                        <img src="<?php echo htmlspecialchars($img_src); ?>" style="width: 100%; height: 100%; object-fit: cover;">
                                    </div>
                                <?php else: ?>
                                    <div class="bg-<?php echo $bg_color; ?> bg-opacity-10 text-<?php echo $bg_color; ?> d-flex align-items-center justify-content-center" style="width: 48px; height: 48px; border-radius: 8px;">
                                        <i class="fas fa-newspaper fa-lg"></i>
                                    </div>
                                <?php endif; ?>
                            </div>
                            <div class="flex-grow-1 overflow-hidden">
                                <div class="d-flex w-100 justify-content-between align-items-center mb-1">
                                    <h6 class="mb-0 fw-bold text-dark text-truncate" style="max-width: 150px;"><?php echo htmlspecialchars($aviso['titulo']); ?></h6>
                                    <span class="badge bg-light text-secondary border" style="font-size: 0.65rem;"><?php echo date('d/m', strtotime($aviso['fecha_publicacion'])); ?></span>
                                </div>
                                <p class="mb-0 text-muted small text-truncate" style="max-width: 100%;">
                                    <?php echo strip_tags($aviso['contenido']); ?>
                                </p>
                            </div>
                        </a>
                        <?php endforeach; else: ?>
                            <div class="text-center py-5 text-muted opacity-50"><i class="far fa-folder-open fa-3x mb-2"></i><br>Sin novedades.</div>
                        <?php endif; ?>
                     </div> 
                 </div> 
             </div>

             <div class="col-lg-4"> 
                 <div class="card shadow mb-4 border-0"> 
                     <div class="card-header bg-dark text-white py-2 text-center"> <h6 class="m-0 fw-bold small text-uppercase"><i class="fas fa-chart-pie me-2"></i> Por Categoría</h6> </div>
                     <div class="card-body"> <div class="chart-container" style="height: 200px;"> <canvas id="catChart"></canvas> </div> <?php if(empty($cat_data)): ?><div class="alert alert-light text-center mt-3 py-1 small text-muted">Sin datos.</div><?php endif; ?> </div> 
                 </div>
                 <div class="card shadow border-0 bg-white mb-4">
                    <div class="card-body py-3">
                        <div class="d-flex justify-content-between align-items-center mb-2">
                            <h6 class="fw-bold text-primary mb-0"><i class="fas fa-chart-line me-2"></i> Mi Efectividad</h6>
                            <span class="h5 fw-bold mb-0 text-primary"><?php echo $mi_efectividad; ?>%</span>
                        </div>
                        <div class="progress" style="height: 6px;"> <div class="progress-bar bg-primary" role="progressbar" style="width: <?php echo $mi_efectividad; ?>%"></div> </div>
                    </div>
                 </div>
                 <div class="card shadow border-0">
                     <div class="card-header bg-white py-2 fw-bold text-success"><i class="fas fa-trophy me-2"></i> Top Productividad (Mes)</div>
                     <ul class="list-group list-group-flush small">
                        <?php if($ranking_users): foreach($ranking_users as $i=>$u): ?>
                        <li class="list-group-item d-flex align-items-center px-3 py-2 border-0">
                            <span class="badge bg-light text-dark border me-2"><?php echo $i+1; ?></span>
                            <img src="uploads/perfiles/<?php echo !empty($u['foto_perfil']) ? $u['foto_perfil'] : 'default.png'; ?>" class="rounded-circle me-2" width="25" height="25">
                            <div class="flex-grow-1 text-truncate fw-bold"><?php echo htmlspecialchars($u['nombre_completo']); ?></div>
                            <span class="badge bg-success rounded-pill"><?php echo $u['total']; ?></span>
                        </li>
                        <?php endforeach; else: echo "<li class='list-group-item text-center text-muted'>Sin datos.</li>"; endif; ?>
                     </ul>
                 </div>
             </div>

             <div class="col-lg-4"> 
                 <div class="card shadow mb-4 border-0"> 
                     <div class="card-header bg-dark text-white py-2 text-center"> <h6 class="m-0 fw-bold small text-uppercase"><i class="fas fa-exclamation-triangle me-2"></i> Por Prioridad</h6> </div>
                     <div class="card-body"> <div class="chart-container" style="height: 200px;"> <canvas id="prioChart"></canvas> </div> <?php if(empty($prio_data)): ?><div class="alert alert-light text-center mt-3 py-1 small text-muted">Sin datos.</div><?php endif; ?> </div> 
                 </div> 
                 <div class="card shadow border-0 mb-4">
                    <div class="card-header bg-white border-bottom py-2"><h6 class="m-0 fw-bold text-danger small"><i class="fas fa-hourglass-half me-2"></i> VENCIMIENTOS (7 DÍAS)</h6></div>
                    <div class="list-group list-group-flush">
                        <?php if($mis_vencimientos): foreach($mis_vencimientos as $v): 
                             $days = (strtotime($v['fecha_limite']) - time()) / 86400;
                             $badge = $days < 0 ? '<span class="badge bg-danger py-1" style="font-size:0.6rem">Vencida</span>' : '<span class="badge bg-warning text-dark py-1" style="font-size:0.6rem">'.ceil($days).'d</span>';
                        ?>
                        <a href="tarea_ver.php?id=<?php echo $v['id_tarea']; ?>" class="list-group-item list-group-item-action d-flex justify-content-between align-items-center px-3 py-2">
                            <div class="text-truncate small"><span class="fw-bold">#<?php echo $v['id_tarea']; ?></span> <?php echo htmlspecialchars($v['titulo']); ?></div>
                            <div class="ms-2"><?php echo $badge; ?></div>
                        </a>
                        <?php endforeach; else: ?><div class="p-3 text-center text-muted small">¡Todo al día!</div><?php endif; ?>
                    </div>
                 </div>
                 <div class="card shadow border-0">
                    <div class="card-header bg-white py-2 fw-bold text-dark"><i class="fas fa-calendar-check me-2"></i> <?php echo $att_title; ?></div>
                    <div class="card-body d-flex justify-content-between px-4">
                        <div class="text-center"><h5 class="text-success fw-bold mb-0"><?php echo $att_present; ?></h5><small style="font-size:0.65rem" class="text-uppercase text-muted fw-bold">Presentes</small></div>
                        <div style="height: 30px; width: 1px; background: #eee;"></div>
                        <div class="text-center"><h5 class="text-danger fw-bold mb-0"><?php echo $att_absent; ?></h5><small style="font-size:0.65rem" class="text-uppercase text-muted fw-bold">Ausentes</small></div>
                    </div>
                 </div>
             </div>
        </div>
        
        <div class="row mb-4">
            <div class="col-12">
                <div class="card shadow border-0">
                    <div class="card-header bg-white py-3"><h6 class="m-0 fw-bold text-secondary"><i class="fas fa-chart-bar me-2"></i> Tareas Finalizadas (Últimos 7 días)</h6></div>
                    <div class="card-body">
                         <div style="height: 250px;"><canvas id="trendChart"></canvas></div>
                    </div>
                </div>
            </div>
        </div>

        <?php if(in_array($rol_usuario, ['admin', 'encargado', 'auxiliar'])): ?>
        <div class="row mb-4">
             <div class="col-12">
                 <div class="card shadow mb-4">
                     <div class="card-header bg-dark text-white d-flex justify-content-between"> 
                         <span><i class="fas fa-users-cog me-2"></i> Carga de Trabajo Global</span>
                         <div class="d-flex gap-2">
                            <select class="form-select form-select-sm" id="empFilter" style="max-width: 200px;"><option value="">Todos</option><?php foreach($lista_empleados as $e): ?><option value="<?php echo $e['id_usuario']; ?>"><?php echo htmlspecialchars($e['nombre_completo']); ?></option><?php endforeach; ?></select>
                            <button class="btn btn-sm btn-primary" id="btnFilter">Ver</button>
                         </div>
                     </div>
                     <div class="card-body"><div class="chart-container" style="height: 300px;"><canvas id="workChart"></canvas></div></div>
                 </div>
             </div>
        </div>
        <?php endif; ?>

    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        document.addEventListener('DOMContentLoaded', () => {
            // Loader
            const showLoader = <?php echo json_encode($show_loader); ?>;
            if (showLoader) { setTimeout(() => { document.getElementById('full-loader').style.display='none'; document.body.style.overflow=''; }, 1500); }
            
            // Clima
            if (navigator.geolocation) {
                navigator.geolocation.getCurrentPosition(pos => {
                    fetch(`fetch_weather.php?lat=${pos.coords.latitude}&lon=${pos.coords.longitude}`).then(r => r.json()).then(d => {
                        if(d.success) {
                            document.getElementById('weatherTemp').textContent = d.temp;
                            document.getElementById('weatherLocation').innerHTML = `<i class="fas fa-map-marker-alt me-1"></i> ${d.location}`;
                            document.getElementById('weatherDesc').textContent = d.desc;
                            document.getElementById('weatherIcon').className = d.icon;
                            document.getElementById('weatherCard').style.display = 'block';
                        }
                    });
                });
            }

            const commonOpts = { responsive: true, maintainAspectRatio: false, plugins: { legend: { position: 'bottom' } } };
            
            const catLabels=<?php echo json_encode($cat_labels);?>, catData=<?php echo json_encode($cat_data);?>, catColors=<?php echo json_encode($cat_colors);?>, catIds=<?php echo json_encode($cat_ids);?>;
            const prioLabels=<?php echo json_encode($prio_labels);?>, prioData=<?php echo json_encode($prio_data);?>, prioColors=<?php echo json_encode($prio_colors);?>;
            const trendLabels=<?php echo json_encode($trend_labels);?>, trendData=<?php echo json_encode($trend_data);?>;
            const workloadLabels=<?php echo json_encode($workload_labels??[]);?>, workloadData=<?php echo json_encode($workload_data??[]);?>, workloadIds=<?php echo json_encode($workload_ids??[]);?>;

            if(catData.length>0) new Chart(document.getElementById('catChart'), { type: 'doughnut', data: { labels: catLabels, datasets: [{ data: catData, backgroundColor: catColors, hoverOffset: 4 }] }, options: { ...commonOpts, onClick: (evt, els) => { if (els.length > 0) window.location.href = `tareas_lista.php?categoria=${catIds[els[0].index]}`; } } });

            if(prioData.length>0) new Chart(document.getElementById('prioChart'), { type: 'pie', data: { labels: prioLabels, datasets: [{ data: prioData, backgroundColor: prioColors, hoverOffset: 4 }] }, options: { ...commonOpts, onClick: (evt, els) => { if (els.length > 0) window.location.href = `tareas_lista.php?prioridad=${prioLabels[els[0].index].toLowerCase()}`; } } });

            // TENDENCIA (Clickable)
            new Chart(document.getElementById('trendChart'), {
                type: 'bar',
                data: { labels: trendLabels, datasets: [{ label: 'Finalizadas', data: trendData, backgroundColor: '#0d6efd', borderRadius: 4 }] },
                options: { responsive: true, maintainAspectRatio: false, scales: { y: { beginAtZero: true, ticks: { stepSize: 1 } }, x: { grid: { display: false } } }, plugins: { legend: { display: false } }, onClick: (e, els) => { if (els.length > 0) window.location.href = 'tareas_lista.php?estado=verificada&sort=fecha_cierre&order=desc'; } }
            });
            
            if(workloadData.length>0) {
                const workChart = new Chart(document.getElementById('workChart'), {
                    type: 'bar',
                    data: { labels: workloadLabels, datasets: [{ label: 'Tareas', data: workloadData, backgroundColor: '#4e73df', borderRadius: 4 }] },
                    options: { responsive: true, maintainAspectRatio: false, scales: { y: { beginAtZero: true } }, plugins: { legend: { display: false } }, onClick: (evt, els) => { if (els.length > 0) window.location.href = `tareas_lista.php?asignado=${workloadIds[els[0].index]}`; } }
                });
                document.getElementById('btnFilter').addEventListener('click', () => {
                    const empId = document.getElementById('empFilter').value;
                    if(empId) window.location.href = `tareas_lista.php?asignado=${empId}`;
                });
            }
        });
    </script>
    
    <?php
    $popup = null;
    if(isset($_SESSION['usuario_id'])){
        try{ $s = $pdo->prepare("SELECT * FROM avisos WHERE es_activo=1 AND fecha_publicacion > '2025-11-19 23:59:59' AND id_aviso NOT IN (SELECT id_aviso FROM avisos_lecturas WHERE id_usuario=:u) ORDER BY fecha_publicacion DESC LIMIT 1"); $s->execute([':u'=>$_SESSION['usuario_id']]); $popup = $s->fetch(PDO::FETCH_ASSOC); } catch(Exception $e){}
    }
    if($popup): ?>
    <div class="modal fade" id="modalPop" tabindex="-1"><div class="modal-dialog"><div class="modal-content"><div class="modal-header bg-primary text-white"><h5 class="modal-title">Aviso</h5><button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" onclick="read(<?php echo $popup['id_aviso'];?>)"></button></div><div class="modal-body"><h4><?php echo htmlspecialchars($popup['titulo']);?></h4><div><?php echo $popup['contenido'];?></div></div><div class="modal-footer"><button class="btn btn-primary" onclick="read(<?php echo $popup['id_aviso'];?>)" data-bs-dismiss="modal">Entendido</button></div></div></div></div>
    <script>setTimeout(()=>{new bootstrap.Modal(document.getElementById('modalPop')).show();},1000); function read(id){fetch('marcar_aviso_leido.php',{method:'POST',headers:{'Content-Type':'application/x-www-form-urlencoded'},body:'id_aviso='+id});}</script>
    <?php endif; ?>
</body>
</html>