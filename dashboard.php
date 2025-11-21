<?php
// Archivo: dashboard.php (VERSIÓN FINAL - DISEÑO AZUL ACERO + ALTURAS IGUALES)
session_start();

// 1. ZONA HORARIA ARGENTINA
date_default_timezone_set('America/Argentina/Buenos_Aires');

include 'conexion.php';
include 'helper_efemerides.php'; 
include 'helper_frases.php';

// 2. SEGURIDAD
if (!isset($_SESSION['usuario_id'])) { header("Location: login.php"); exit(); }
$id_usuario = $_SESSION['usuario_id'];
$rol_usuario = $_SESSION['usuario_rol'];
$nombre_usuario = $_SESSION['usuario_nombre'] ?? 'Usuario';

// --- MODO NAVIDAD ---
$modo_navidad = false;
try {
    $stmt_conf = $pdo->prepare("SELECT valor FROM configuracion_sistema WHERE clave = 'modo_navidad'");
    $stmt_conf->execute();
    $res_conf = $stmt_conf->fetch();
    $modo_navidad = ($res_conf && $res_conf['valor'] == '1');
} catch (Exception $e) {}

// --- DATOS ---
if (!function_exists('obtenerEfemerideHoy')) { 
    function obtenerEfemerideHoy() { return ['titulo'=>'Sin datos', 'descripcion'=>'', 'link'=>'#', 'tipo'=>'militar', 'icono'=>'fas fa-flag']; } 
}
$datos_efemeride = obtenerEfemerideHoy();

$dato_frase = obtenerFraseMotivadora();
$frase_del_dia = $dato_frase['frase'];
$autor_frase = $dato_frase['autor'];

// --- LÓGICA CHAT ---
$mostrar_chat_modal = false;
try {
    $sql_check_column = "ALTER TABLE usuarios ADD COLUMN chat_notificacion_leida BOOLEAN DEFAULT 0";
    @$pdo->exec($sql_check_column); 
    $stmt = $pdo->prepare("SELECT chat_notificacion_leida FROM usuarios WHERE id_usuario = :id");
    $stmt->execute([':id' => $id_usuario]);
    $usuario_data = $stmt->fetch();
    $mostrar_chat_modal = ($usuario_data && $usuario_data['chat_notificacion_leida'] == 0);
} catch (PDOException $e) { $mostrar_chat_modal = false; }

function tiene_imagen($html) { return (strpos($html, 'data:image') !== false); }
function getGreeting() { 
    $h = date('H'); 
    return ($h >= 5 && $h < 12) ? 'Buenos días' : (($h >= 12 && $h < 19) ? 'Buenas tardes' : 'Buenas noches'); 
}
$saludo = getGreeting();

// 5. FILTROS SQL
$sql_filtro_usuario = "";
$params_filtro = [];
if ($rol_usuario === 'empleado') {
    $sql_filtro_usuario = " AND t.id_asignado = :uid ";
    $params_filtro[':uid'] = $id_usuario;
}

// --- DATOS PARA GRÁFICOS ---
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

// C. TENDENCIA
$trend_labels = []; $trend_data = [];
try {
    $sql_trend = "SELECT DATE(fecha_cierre) as fecha, COUNT(*) as total FROM tareas t WHERE t.estado = 'verificada' AND t.fecha_cierre >= DATE_SUB(CURDATE(), INTERVAL 6 DAY) $sql_filtro_usuario GROUP BY DATE(fecha_cierre)";
    $stmt_trend = $pdo->prepare($sql_trend); $stmt_trend->execute($params_filtro);
    $db_data = $stmt_trend->fetchAll(PDO::FETCH_KEY_PAIR); 
    for ($i = 6; $i >= 0; $i--) {
        $date = date('Y-m-d', strtotime("-$i days"));
        $trend_labels[] = date('d/m', strtotime("-$i days"));
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
try { 
    $stmt = $pdo->query("SELECT id_aviso, titulo, contenido, fecha_publicacion, imagen_destacada FROM avisos WHERE es_activo = 1 ORDER BY fecha_publicacion DESC LIMIT 5"); 
    $avisos_recientes = $stmt->fetchAll(PDO::FETCH_ASSOC); 
} catch (Exception $e) {}

// --- OTROS WIDGETS ---
$mi_efectividad = 0;
try {
    $stmt_eff = $pdo->prepare("SELECT COUNT(*) as total, SUM(CASE WHEN estado='verificada' THEN 1 ELSE 0 END) as ok FROM tareas WHERE id_asignado = :uid AND estado != 'cancelada'");
    $stmt_eff->execute([':uid' => $id_usuario]); $row_eff = $stmt_eff->fetch(PDO::FETCH_ASSOC);
    if($row_eff['total'] > 0) $mi_efectividad = round(($row_eff['ok'] / $row_eff['total']) * 100);
} catch(Exception $e) {}

$mis_vencimientos = [];
try {
    $stmt_venc = $pdo->prepare("SELECT id_tarea, titulo, fecha_limite, prioridad FROM tareas WHERE id_asignado = :uid AND fecha_limite BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 7 DAY) AND estado NOT IN ('verificada', 'cancelada') ORDER BY fecha_limite ASC LIMIT 5");
    $stmt_venc->execute([':uid' => $id_usuario]); $mis_vencimientos = $stmt_venc->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {}

$ranking_users = [];
try {
    $sql_rank = "SELECT u.nombre_completo, u.foto_perfil, COUNT(t.id_tarea) as total FROM tareas t JOIN usuarios u ON t.id_asignado = u.id_usuario WHERE t.estado = 'verificada' AND MONTH(t.fecha_cierre) = MONTH(CURRENT_DATE()) GROUP BY t.id_asignado ORDER BY total DESC LIMIT 5";
    $ranking_users = $pdo->query($sql_rank)->fetchAll(PDO::FETCH_ASSOC);
} catch(Exception $e){}

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

$workload_labels = []; $workload_data = []; $workload_ids = [];
$lista_empleados = [];
if (in_array($rol_usuario, ['admin', 'encargado', 'auxiliar'])) {
    try {
        $stmt_wl = $pdo->query("SELECT u.id_usuario, u.nombre_completo, COUNT(t.id_tarea) as total FROM usuarios u LEFT JOIN tareas t ON u.id_usuario = t.id_asignado AND t.estado NOT IN ('verificada','cancelada') WHERE u.rol IN ('empleado','auxiliar') AND u.activo = 1 GROUP BY u.id_usuario ORDER BY total DESC LIMIT 10");
        $res_wl = $stmt_wl->fetchAll(PDO::FETCH_ASSOC);
        foreach($res_wl as $w) { $parts = explode(' ', $w['nombre_completo']); $workload_labels[] = $parts[0]; $workload_data[] = $w['total']; $workload_ids[] = $w['id_usuario']; }
        $lista_empleados = $pdo->query("SELECT id_usuario, nombre_completo FROM usuarios WHERE rol = 'empleado' AND activo = 1 ORDER BY nombre_completo")->fetchAll(PDO::FETCH_ASSOC);
    } catch (Exception $e) {}
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
        body { background-color: #f4f6f9; font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; overflow-x: hidden; }
        #full-loader { position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: #fff; z-index: 9999; display: flex; justify-content: center; align-items: center; transition: opacity 0.5s ease-out, visibility 0.5s ease-out; }
        .card { border: none; border-radius: 12px; box-shadow: 0 2px 15px rgba(0,0,0,0.04); transition: transform 0.2s; }
        .hover-card:hover { transform: translateY(-4px); box-shadow: 0 8px 25px rgba(0,0,0,0.08) !important; }
        .chart-container { position: relative; height: 250px; width: 100%; }
        .cursor-pointer { cursor: pointer; }
        
        /* ESTILO SALUDO Y CLIMA (FUERZA AÉREA - AZUL) */
        .alert-primary {
            background-color: #2c3e50 !important; /* Azul Acero Oscuro */
            color: #ecf0f1 !important; /* Blanco Hielo */
            border: none !important;
            border-left: 5px solid #bdc3c7 !important; /* Borde Plateado */
        }
        .alert-primary h4, .alert-primary p, .alert-primary i {
            color: #ecf0f1 !important;
        }
        .alert-primary .text-primary { color: #bdc3c7 !important; } /* Icono de mano */

        #weatherCard {
            background-color: #2c3e50 !important;
            border: none !important;
            border-left: 5px solid #bdc3c7 !important;
        }
        #weatherCard h6, #weatherCard small, #weatherCard h2, #weatherCard i {
            color: #ecf0f1 !important;
        }
        #weatherCard .text-primary { color: #ecf0f1 !important; } /* Temp en blanco */

        /* ESTILO EFEMÉRIDES (GALA MILITAR - VERDE OSCURO) */
        .alert-militar {
            background-color: #1a2f1a; /* Verde bosque */
            color: #ffffff; 
            border: 1px solid #0f1f0f;
            border-left: 5px solid #d4af37; /* Dorado */
        }
        .alert-militar h5, .alert-militar p, .alert-militar i {
            color: #ffffff !important;
        }
        .alert-militar .text-gold {
            color: #d4af37 !important;
        }
        .btn-militar-light {
            background-color: #d4af37; /* Botón Dorado */
            color: #1a2f1a; /* Letra oscura */
            border: none;
            font-weight: bold;
        }
        .btn-militar-light:hover {
            background-color: #c5a028;
            color: #000;
        }
        
        /* NAVIDAD */
        <?php if($modo_navidad): ?>
        .christmas-lights {
            position: fixed; top: 0; left: 0; width: 100%; height: 60px; z-index: 99999; pointer-events: none;
            background: url('image_747802.png') repeat-x top center; 
            background-size: auto 100%; animation: flicker 2s infinite alternate;
        }
        @keyframes flicker { 0% {opacity:0.9;} 100% {opacity:1;} }
        <?php endif; ?>
    </style>
</head>
<body <?php echo $show_loader ? 'style="overflow: hidden;"' : ''; ?>>

    <?php if ($show_loader): ?>
    <div id="full-loader"><div class="text-center"><div class="spinner-border text-primary mb-3" style="width: 3rem; height: 3rem;"></div><p class="text-muted">Cargando...</p></div></div>
    <?php endif; ?>

    <?php include 'navbar.php'; ?>

    <div class="container py-4 mb-5">
        
        <div class="row align-items-stretch mb-4"> <div class="col-md-8">
                <div class="alert alert-primary shadow-sm mb-0 d-flex justify-content-between align-items-center h-100">
                    <div class="d-flex align-items-center">
                        <div class="me-3 display-6"><i class="fas fa-hand-paper text-primary"></i></div>
                        <div>
                            <h4 class="alert-heading mb-1 fw-bold"><?php echo $saludo; ?>, <?php echo htmlspecialchars($nombre_usuario); ?>.</h4>
                            <p class="mb-0 fst-italic opacity-75">
                                "<?php echo htmlspecialchars($frase_del_dia); ?>" 
                                <span class="small ms-2 fw-bold">- <?php echo htmlspecialchars($autor_frase); ?></span>
                            </p>
                        </div>
                    </div>
                    
                    <?php if($rol_usuario === 'admin'): ?>
                    <button class="btn btn-sm btn-outline-light border-0 shadow-sm rounded-circle ms-3" 
                            style="width: 35px; height: 35px;" 
                            onclick="refrescarDato('frase')" 
                            title="Cambiar Frase">
                        <i class="fas fa-sync-alt"></i>
                    </button>
                    <?php endif; ?>
                </div>
            </div>
            
            <div class="col-md-4 mt-3 mt-md-0 d-none d-md-block">
                <div class="card bg-white shadow-sm h-100 border-0" id="weatherCard">
                    <div class="card-body p-3 d-flex align-items-center justify-content-between h-100">
                        <div><h6 class="mb-0 fw-bold" id="weatherLocation">Ubicación</h6><small id="weatherDesc" class="opacity-75">Cargando...</small></div>
                        <div class="text-end"><h2 class="mb-0 fw-bold text-primary" id="weatherTemp">--°</h2></div>
                        <div id="weatherIcon" style="font-size: 2rem;"></div>
                    </div>
                </div>
            </div>
        </div>

        <div class="row mb-4">
            <div class="col-12">
                <div class="alert alert-militar shadow-lg d-flex align-items-center justify-content-between py-3" role="alert">
                    <div class="d-flex align-items-center overflow-hidden">
                        <div class="me-3 fs-2 flex-shrink-0"><i class="<?php echo $datos_efemeride['icono']; ?> text-gold"></i></div>
                        <div class="overflow-hidden">
                            <h5 class="alert-heading fw-bold mb-0 text-uppercase small text-truncate text-gold">Efemérides Argentina</h5>
                            <p class="mb-0 fw-bold text-truncate" style="font-size: 1.1rem; max-width: 650px;"><?php echo $datos_efemeride['titulo']; ?></p>
                        </div>
                    </div>
                    <div class="d-flex gap-2 align-items-center flex-shrink-0">
                        <button type="button" class="btn btn-sm btn-militar-light shadow-sm px-3" data-bs-toggle="modal" data-bs-target="#modalEfemeride"><i class="fas fa-eye me-1"></i> Saber más</button>
                        <?php if($rol_usuario === 'admin'): ?>
                        <button class="btn btn-sm btn-outline-warning border shadow-sm rounded-circle" style="width: 32px; height: 32px; padding: 0;" onclick="refrescarDato('efemeride')" title="Cambiar Efeméride"><i class="fas fa-sync-alt"></i></button>
                        <button class="btn btn-sm btn-outline-light border shadow-sm rounded-circle" style="width: 32px; height: 32px; padding: 0;" onclick="toggleNavidad()" title="Navidad"><i class="fas fa-snowflake"></i></button>
                        <?php endif; ?>
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
                            $img_src = false;
                            if (!empty($aviso['imagen_destacada'])) {
                                $info = pathinfo($aviso['imagen_destacada']);
                                $thumb_file = 'uploads/avisos/' . $info['filename'] . '_thumb.' . $info['extension'];
                                $orig_file = 'uploads/avisos/' . $aviso['imagen_destacada'];
                                if (file_exists($thumb_file)) { $img_src = $thumb_file . '?v=' . time(); } elseif (file_exists($orig_file)) { $img_src = $orig_file; }
                            }
                            if (!$img_src && tiene_imagen($aviso['contenido'])) { $img_src = 'ver_imagen_aviso.php?id=' . $aviso['id_aviso']; }
                            $colores = ['primary', 'success', 'danger', 'warning', 'info', 'dark']; $bg_color = $colores[$aviso['id_aviso'] % count($colores)];
                        ?>
                        <a href="avisos.php?show_id=<?php echo $aviso['id_aviso'];?>" class="list-group-item list-group-item-action py-3 border-0 border-bottom d-flex align-items-center" style="transition:background 0.2s">
                            <div class="flex-shrink-0 me-3">
                                <?php if($img_src): ?>
                                    <div style="width: 48px; height: 48px; border-radius: 8px; overflow: hidden; border: 1px solid #eee;"><img src="<?php echo $img_src; ?>" style="width: 100%; height: 100%; object-fit: cover;"></div>
                                <?php else: ?>
                                    <div class="bg-<?php echo $bg_color; ?> bg-opacity-10 text-<?php echo $bg_color; ?> d-flex align-items-center justify-content-center" style="width: 48px; height: 48px; border-radius: 8px;"><i class="fas fa-newspaper fa-lg"></i></div>
                                <?php endif; ?>
                            </div>
                            <div class="flex-grow-1 overflow-hidden">
                                <div class="d-flex w-100 justify-content-between align-items-center mb-1"><h6 class="mb-0 fw-bold text-dark text-truncate" style="max-width: 150px;"><?php echo htmlspecialchars($aviso['titulo']); ?></h6><span class="badge bg-light text-secondary border" style="font-size: 0.65rem;"><?php echo date('d/m', strtotime($aviso['fecha_publicacion'])); ?></span></div>
                                <p class="mb-0 text-muted small text-truncate" style="max-width: 100%;">
                                    <?php echo strip_tags($aviso['contenido']); ?>
                                </p>
                            </div>
                        </a>
                        <?php endforeach; else: ?><div class="text-center py-5 text-muted opacity-50"><i class="far fa-folder-open fa-3x mb-2"></i><br>Sin novedades.</div><?php endif; ?>
                     </div> 
                 </div> 
             </div>

             <div class="col-lg-4"> 
                 <div class="card shadow mb-4 border-0"> 
                     <div class="card-header bg-dark text-white py-2 text-center"> <h6 class="m-0 fw-bold small text-uppercase"><i class="fas fa-chart-pie me-2"></i> Por Categoría</h6> </div>
                     <div class="card-body"> <div class="chart-container" style="height: 200px;"> <canvas id="catChart"></canvas> </div> <?php if(empty($cat_data)): ?><div class="alert alert-light text-center mt-3 py-1 small text-muted">Sin datos.</div><?php endif; ?> </div> 
                 </div>
                 <div class="card shadow border-0 bg-white mb-4">
                    <div class="card-body py-3"><div class="d-flex justify-content-between align-items-center mb-2"><h6 class="fw-bold text-primary mb-0"><i class="fas fa-chart-line me-2"></i> Mi Efectividad</h6><span class="h5 fw-bold mb-0 text-primary"><?php echo $mi_efectividad; ?>%</span></div><div class="progress" style="height: 6px;"> <div class="progress-bar bg-primary" role="progressbar" style="width: <?php echo $mi_efectividad; ?>%"></div> </div></div>
                 </div>
                 <div class="card shadow border-0">
                     <div class="card-header bg-white py-2 fw-bold text-success"><i class="fas fa-trophy me-2"></i> Top Productividad (Mes)</div>
                     <ul class="list-group list-group-flush small">
                        <?php if($ranking_users): foreach($ranking_users as $i=>$u): ?>
                        <li class="list-group-item d-flex align-items-center px-3 py-2 border-0"><span class="badge bg-light text-dark border me-2"><?php echo $i+1; ?></span><img src="uploads/perfiles/<?php echo !empty($u['foto_perfil']) ? $u['foto_perfil'] : 'default.png'; ?>" class="rounded-circle me-2" width="25" height="25"><div class="flex-grow-1 text-truncate fw-bold"><?php echo htmlspecialchars($u['nombre_completo']); ?></div><span class="badge bg-success rounded-pill"><?php echo $u['total']; ?></span></li>
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
                        <a href="tarea_ver.php?id=<?php echo $v['id_tarea']; ?>" class="list-group-item list-group-item-action d-flex justify-content-between align-items-center px-3 py-2"><div class="text-truncate small"><span class="fw-bold">#<?php echo $v['id_tarea']; ?></span> <?php echo htmlspecialchars($v['titulo']); ?></div><div class="ms-2"><?php echo $badge; ?></div></a>
                        <?php endforeach; else: ?><div class="p-3 text-center text-muted small">¡Todo al día!</div><?php endif; ?>
                    </div>
                 </div>
                 <div class="card shadow border-0">
                    <div class="card-header bg-white py-2 fw-bold text-dark"><i class="fas fa-calendar-check me-2"></i> <?php echo $att_title; ?></div>
                    <div class="card-body d-flex justify-content-between px-4"><div class="text-center"><h5 class="text-success fw-bold mb-0"><?php echo $att_present; ?></h5><small style="font-size:0.65rem" class="text-uppercase text-muted fw-bold">Presentes</small></div><div style="height: 30px; width: 1px; background: #eee;"></div><div class="text-center"><h5 class="text-danger fw-bold mb-0"><?php echo $att_absent; ?></h5><small style="font-size:0.65rem" class="text-uppercase text-muted fw-bold">Ausentes</small></div></div>
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

    <div class="modal fade" id="modalEfemeride" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content border-0 shadow-lg">
                <div class="modal-header text-white border-0" style="background-color: #1a2f1a;">
                    <h5 class="modal-title fw-bold"><i class="fas fa-landmark text-gold me-2"></i> Historia Militar y Argentina</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body p-4 text-center">
                    <h4 class="fw-bold mb-3 text-dark"><?php echo $datos_efemeride['titulo']; ?></h4>
                    <div class="alert alert-light border text-start mb-4 shadow-sm">
                        <p class="mb-0 text-muted" style="font-size: 1rem; line-height: 1.6;"><?php echo $datos_efemeride['descripcion']; ?></p>
                    </div>
                    <a href="<?php echo $datos_efemeride['link']; ?>" target="_blank" class="btn btn-dark rounded-pill px-4 fw-bold shadow-sm" style="background-color: #d4af37; color: #1a2f1a; border:none;"><i class="fab fa-wikipedia-w me-2"></i> Ver fuente oficial</a>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        document.addEventListener('DOMContentLoaded', () => {
            const showLoader = <?php echo json_encode($show_loader); ?>;
            if (showLoader) { 
                const loader = document.getElementById('full-loader');
                if(loader) {
                     loader.style.opacity = '0';
                     setTimeout(() => { 
                        loader.style.display='none'; 
                        document.body.style.overflow=''; 
                     }, 500); 
                }
            }
            
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

            new Chart(document.getElementById('trendChart'), { type: 'bar', data: { labels: trendLabels, datasets: [{ label: 'Finalizadas', data: trendData, backgroundColor: '#0d6efd', borderRadius: 4 }] }, options: { responsive: true, maintainAspectRatio: false, scales: { y: { beginAtZero: true, ticks: { stepSize: 1 } }, x: { grid: { display: false } } }, plugins: { legend: { display: false } }, onClick: (e, els) => { if (els.length > 0) window.location.href = 'tareas_lista.php?estado=verificada&sort=fecha_cierre&order=desc'; } } });
            
            if(workloadData.length>0) {
                new Chart(document.getElementById('workChart'), { type: 'bar', data: { labels: workloadLabels, datasets: [{ label: 'Tareas', data: workloadData, backgroundColor: '#4e73df', borderRadius: 4 }] }, options: { responsive: true, maintainAspectRatio: false, scales: { y: { beginAtZero: true } }, plugins: { legend: { display: false } }, onClick: (evt, els) => { if (els.length > 0) window.location.href = `tareas_lista.php?asignado=${workloadIds[els[0].index]}`; } } });
                document.getElementById('btnFilter').addEventListener('click', () => { const empId = document.getElementById('empFilter').value; if(empId) window.location.href = `tareas_lista.php?asignado=${empId}`; });
            }
        });
        
        const modalEfemeride = new bootstrap.Modal(document.getElementById('modalEfemeride'));
        function abrirModalEfemeride() { modalEfemeride.show(); }
        
        function toggleNavidad() { 
            fetch('admin_toggle_navidad.php', { method: 'POST' }).then(r => r.json()).then(d => { if(d.success) location.reload(); }); 
        }

        function refrescarDato(tipo) {
            const fd = new FormData();
            fd.append('tipo', tipo); 
            document.body.style.cursor = 'wait'; 
            fetch('admin_limpiar_cache.php', { method: 'POST', body: fd })
                .then(r => r.json())
                .then(d => {
                    if(d.success) {
                        location.reload(); 
                    } else {
                        alert('Error al actualizar.');
                        document.body.style.cursor = 'default';
                    }
                });
        }
    </script>
    
    <?php if ($mostrar_chat_modal): ?>
    <div class="modal fade" id="chatUpdateModal" data-bs-backdrop="static" data-bs-keyboard="false" tabindex="-1">
        <div class="modal-dialog modal-lg modal-dialog-centered">
            <div class="modal-content border-0 shadow-lg" style="border-radius: 1rem;">
                <div class="modal-header text-white" style="background-color: #007bff;"><h5 class="modal-title w-100 text-center"><i class="fas fa-bullhorn me-2"></i> ¡Novedades!</h5></div>
                <div class="modal-body p-4"><p class="text-center">Nuevas funciones de chat habilitadas.</p></div>
                <div class="modal-footer d-flex justify-content-between align-items-center">
                    <div class="form-check"><input class="form-check-input" type="checkbox" id="entendidoCheck"><label class="form-check-label" for="entendidoCheck">Entendido</label></div>
                    <button type="button" class="btn btn-primary" id="closeModalButton" disabled>Cerrar</button>
                </div>
            </div>
        </div>
    </div>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const m = new bootstrap.Modal(document.getElementById('chatUpdateModal')); m.show();
            document.getElementById('entendidoCheck').addEventListener('change', function() { document.getElementById('closeModalButton').disabled = !this.checked; });
            document.getElementById('closeModalButton').addEventListener('click', function() { m.hide(); fetch('actualizar_chat_db.php', {method:'POST',body:'update=true'}); });
        });
    </script>
    <?php endif; ?>
</body>
</html>