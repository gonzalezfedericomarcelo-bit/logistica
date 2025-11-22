<?php
// Archivo: tarea_ver.php (VERSIÓN COMPLETA FUSIONADA - ESTABLE + FUNCIONES DE REMITOS)
// *** MODIFICADO (v3) POR GEMINI PARA CORREGIR LÓGICA DE 'es_tecnico_asignado' Y 'reanudar_reserva' ***
// *** MODIFICADO (v4) POR GEMINI PARA DAR PERMISOS DE GESTIÓN AL 'encargado' ***
session_start();
include 'conexion.php';

// 1. Proteger la página
if (!isset($_SESSION['usuario_id'])) { header("Location: login.php"); exit(); }

$id_usuario = $_SESSION['usuario_id'];
$rol_usuario = $_SESSION['usuario_rol'];
// *** AÑADIDO: Definir el permiso de gestión para Admin O Encargado ***
// (Esta línea es de tu código original y ahora la usaremos)
$es_admin_o_encargado = in_array($rol_usuario, ['admin', 'encargado']);
// *******************************************************************
$id_tarea = (int)($_GET['id'] ?? 0);

// --- Capturar mensajes de acción desde SESIÓN (para modales post-acción) ---
$success_msg = $_SESSION['action_success_message'] ?? null;
$error_msg = $_SESSION['action_error_message'] ?? null;
unset($_SESSION['action_success_message']); // Limpiar
unset($_SESSION['action_error_message']);   // Limpiar
// --- FIN Captura mensajes ---
$numero_orden_display = '';

// URLs Dinámicas
$url_actual = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? "https" : "http") . "://$_SERVER[HTTP_HOST]$_SERVER[REQUEST_URI]";
$partes_url = parse_url($url_actual); $ruta_base = dirname($partes_url['path']);
$action_url_actualizar = $ruta_base . '/tarea_actualizar_procesar.php';
$action_url_finalizar = $ruta_base . '/tarea_finalizar_procesar.php';
$action_url_estado = $ruta_base . '/tarea_actualizar_estado.php';
$action_url_cancelar = $ruta_base . '/tarea_cancelar_procesar.php';
$action_url_recall_request = $ruta_base . '/tarea_recall_request.php';

if ($id_tarea <= 0) { header("Location: tareas_lista.php"); exit(); }

// 2. Obtener datos de la tarea y relacionados
try {
    // (Fusión: Usamos la consulta base de tarea_ver.php)
    $sql = "SELECT t.*, c.nombre AS categoria_nombre, ca.nombre_completo AS creador_nombre, asig.nombre_completo AS asignado_nombre FROM tareas t LEFT JOIN categorias c ON t.id_categoria = c.id_categoria LEFT JOIN usuarios ca ON t.id_creador = ca.id_usuario LEFT JOIN usuarios asig ON t.id_asignado = asig.id_usuario WHERE t.id_tarea = :id_tarea";
    $stmt = $pdo->prepare($sql); $stmt->execute([':id_tarea' => $id_tarea]); $tarea = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$tarea) { header("Location: tareas_lista.php"); exit(); }
    
    // --- INICIO DE LA MODIFICACIÓN DE GEMINI (v3) ---
    // (Esta corrección permite a empleado y auxiliar ver sus tareas)
    $es_tecnico_asignado = ((int)$tarea['id_asignado'] === $id_usuario);
    // --- FIN DE LA MODIFICACIÓN DE GEMINI (v3) ---

    // Protección para que un usuario no-admin/encargado no vea tareas ajenas
    // --- INICIO DE LA MODIFICACIÓN DE GEMINI (v4) ---
    // (Añadido !$es_admin_o_encargado para que el encargado pueda ver tareas aunque no esté asignado)
    if (!$es_admin_o_encargado && !$es_tecnico_asignado && $rol_usuario !== 'auxiliar') { 
    // --- FIN DE LA MODIFICACIÓN DE GEMINI (v5) ---
        header("Location: tareas_lista.php?error=" . urlencode("Sin permiso.")); 
        exit(); 
    }

    // (Fusión: Usamos las consultas de tarea_ver_r.php para la DB correcta)
    // Adjuntos Iniciales
    $sql_adjuntos = "SELECT id_adjunto, nombre_archivo, ruta_archivo FROM adjuntos_tarea WHERE id_tarea = :id_tarea AND tipo_adjunto = 'inicial' ORDER BY fecha_subida ASC";
    $stmt_adjuntos = $pdo->prepare($sql_adjuntos); $stmt_adjuntos->execute([':id_tarea' => $id_tarea]); $adjuntos_iniciales = $stmt_adjuntos->fetchAll(PDO::FETCH_ASSOC);

    // Adjuntos Finales (CON FECHA)
    $sql_adjuntos_finales = "SELECT id_adjunto, nombre_archivo, ruta_archivo, fecha_subida FROM adjuntos_tarea WHERE id_tarea = :id_tarea AND tipo_adjunto = 'final' ORDER BY fecha_subida DESC";
    $stmt_adjuntos_finales = $pdo->prepare($sql_adjuntos_finales); $stmt_adjuntos_finales->execute([':id_tarea' => $id_tarea]); $adjuntos_finales_raw = $stmt_adjuntos_finales->fetchAll(PDO::FETCH_ASSOC);
    $adjuntos_finales_agrupados = []; foreach ($adjuntos_finales_raw as $adj) { $fecha_dia = date('Y-m-d', strtotime($adj['fecha_subida'])); $adjuntos_finales_agrupados[$fecha_dia][] = $adj; }

    // Actualizaciones (Incluyendo 'causo_reserva' de tarea_ver_r.php)
    $sql_actualizaciones = "SELECT a.id_actualizacion, a.id_usuario, a.contenido, a.fecha_actualizacion,
                                   u.nombre_completo AS usuario_nombre, u.rol AS usuario_rol,
                                   a.causo_reserva
                            FROM actualizaciones_tarea a
                            JOIN usuarios u ON a.id_usuario = u.id_usuario
                            WHERE a.id_tarea = :id_tarea
                            ORDER BY a.fecha_actualizacion DESC";
    $stmt_actualizaciones = $pdo->prepare($sql_actualizaciones); $stmt_actualizaciones->execute([':id_tarea' => $id_tarea]); $actualizaciones = $stmt_actualizaciones->fetchAll(PDO::FETCH_ASSOC);

    // Adjuntos de actualizaciones (Usando la lógica de tarea_ver_r.php)
    $sql_adjuntos_updates = "SELECT id_adjunto, id_actualizacion, tipo_adjunto, nombre_archivo, ruta_archivo, estado_conciliacion, descripcion_compra, precio_total, numero_compra FROM adjuntos_tarea WHERE id_tarea = :id_tarea AND tipo_adjunto IN ('actualizacion', 'remito') AND id_actualizacion IS NOT NULL";
    $stmt_adjuntos_updates = $pdo->prepare($sql_adjuntos_updates); $stmt_adjuntos_updates->execute([':id_tarea' => $id_tarea]); $adjuntos_updates_raw = $stmt_adjuntos_updates->fetchAll(PDO::FETCH_ASSOC);

    $adjuntos_por_actualizacion = [];
    foreach ($adjuntos_updates_raw as $adjunto) {
        $adjuntos_por_actualizacion[$adjunto['id_actualizacion']][] = $adjunto; // Agrupa por ID de actualización
    }

    // Notas específicas para Modales y Avisos + Lógica Recall
    $nota_modificacion_admin = ""; $nota_cancelacion_admin = ""; $latest_recall_request = null; $latest_admin_return = null;
    foreach ($actualizaciones as $act) {
        if (!$latest_recall_request && str_starts_with($act['contenido'], '[SOLICITUD TÉCNICO] Corrección solicitada') && $act['id_usuario'] != $id_usuario) { $latest_recall_request = $act; }
        if (!$latest_admin_return && str_starts_with($act['contenido'], 'SOLICITUD DE MODIFICACIÓN:') && $act['id_usuario'] == $id_usuario) { $latest_admin_return = $act; }
        if (!$nota_modificacion_admin && str_starts_with($act['contenido'], 'SOLICITUD DE MODIFICACIÓN:')) { $nota_modificacion_admin = str_replace('SOLICITUD DE MODIFICACIÓN: ', '', $act['contenido']); }
        if (!$nota_cancelacion_admin && str_starts_with($act['contenido'], 'TAREA CANCELADA POR ADMINISTRADOR.')) { $nota_cancelacion_admin = $act['contenido']; }
    }

    // Lógica Recall (sin cambios)
    $show_pending_recall_warning = false; $recall_reason = '';
    if ($tarea['estado'] === 'finalizada_tecnico' && $latest_recall_request) {
        $recall_reason = str_replace('[SOLICITUD TÉCNICO] Corrección solicitada (post-entrega). Motivo: ', '', $latest_recall_request['contenido']);
        if (!$latest_admin_return || strtotime($latest_recall_request['fecha_actualizacion']) > strtotime($latest_admin_return['fecha_actualizacion'])) {
            $show_pending_recall_warning = true;
        }
    }

} catch (PDOException $e) { die("Error al cargar datos: " . $e->getMessage()); }

// Funciones traducir_estado y traducir_prioridad (con 'en_reserva' añadido de tarea_ver_r.php)
function traducir_estado($estado) { $estados = ['asignada' => '<span class="badge bg-info p-2 fs-5 text-uppercase"><i class="fas fa-hand-point-right"></i> Asignada</span>', 'en_proceso' => '<span class="badge bg-warning text-dark p-2 fs-5 text-uppercase"><i class="fas fa-tools"></i> En Proceso</span>', 'finalizada_tecnico' => '<span class="badge bg-primary p-2 fs-5 text-uppercase"><i class="fas fa-search"></i> P/Revisión</span>', 'verificada' => '<span class="badge bg-success p-2 fs-5 text-uppercase"><i class="fas fa-check-double"></i> Cerrada</span>', 'modificacion_requerida' => '<span class="badge bg-danger p-2 fs-5 text-uppercase"><i class="fas fa-undo"></i> Modificación</span>', 'cancelada' => '<span class="badge bg-secondary p-2 fs-5 text-uppercase"><i class="fas fa-ban"></i> Cancelada</span>', 'en_reserva' => '<span class="badge bg-dark p-2 fs-5 text-uppercase"><i class="fas fa-pause-circle"></i> En Reserva</span>']; return $estados[$estado] ?? $estado; }
function traducir_prioridad($prioridad) { $prioridades = ['baja' => '<span class="badge bg-success p-2 fs-5 text-uppercase"><i class="fas fa-angle-double-down"></i> Baja</span>', 'media' => '<span class="badge bg-info p-2 fs-5 text-uppercase"><i class="fas fa-equals"></i> Media</span>', 'alta' => '<span class="badge bg-warning text-dark p-2 fs-5 text-uppercase"><i class="fas fa-angle-double-up"></i> Alta</span>', 'urgente' => '<span class="badge bg-danger p-2 fs-5 text-uppercase"><i class="fas fa-exclamation-triangle"></i> URGENTE</span>']; return $prioridades[$prioridad] ?? $prioridad; }
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Ver Tarea #<?php echo $id_tarea; ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">
    <script src="https://cdn.jsdelivr.net/npm/canvas-confetti@1.9.3/dist/confetti.browser.min.js"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/animate.css/4.1.1/animate.min.css"/>
    <style>
        .fs-large { font-size: 1.15rem; }
        .cabecera-tarea { background-color: #ffffff; border-bottom: 5px solid #0d6efd; padding: 1.5rem; }
        .toast-container { z-index: 1080; }
        @keyframes intenseFlash { 0%, 100% { background-color: transparent; box-shadow: none; } 50% { background-color: rgba(255, 230, 0, 0.85); box-shadow: 0 0 15px 5px rgba(255, 230, 0, 0.6); } }
        .highlight-update-flash, .highlight-section-flash { animation: intenseFlash 1.0s ease-in-out; animation-iteration-count: 2; border-radius: inherit; }
        .animate__animated.animate__pulse { --animate-duration: 1.5s; }
        .animate__animated.animate__flash { --animate-duration: 2s; }
        .preserve-whitespace { white-space: pre-wrap; }

        /* (Fusión: Estilos de tarea_ver_r.php) */
        .list-group-item.update-en-reserva {
            background-color: #fff3e0 !important;
            border-left: 5px solid #fd7e14 !important;
        }
        .list-group-item.update-solicitud {
            background-color: #fff8e1 !important;
            border-left: 5px solid #ffc107 !important;
        }
        .form-label-sm { font-size: 0.875em; margin-bottom: 0.2rem; }
        .form-control-sm { font-size: 0.875rem; padding: 0.25rem 0.5rem; }
        
        /* Estilo base para las actualizaciones NO en hilos (para separarlas) */
        .list-group-item.list-group-item-action {
            margin-bottom: 8px; 
            border-radius: 0.375rem !important;
        }
        
        /* Ítem A: Remito Rechazado (Padre del hilo - Color Rojo) */
        .list-group-item.update-rejected-remito {
            background-color: #ffe0e0 !important; 
            border-left: 5px solid #dc3545 !important;
            margin-bottom: 0px !important; 
            border-radius: 0.375rem 0.375rem 0 0 !important;
        }
        
        /* Ítem B: Corrección (Hijo del hilo - Color Verde con Sangría) */
        .list-group-item.correction-thread {
            background-color: #f0fff0 !important; 
            border-left: 5px solid #198754 !important; 
            border-top: 1px dashed #198754 !important;
            padding-left: 20px; /* Sangría */
            margin-top: 0px; 
            margin-bottom: 8px !important; 
            border-radius: 0 0 0.375rem 0.375rem !important;
        }
        /* (Fin Fusión Estilos) */
    </style>
</head>
<body style="background-color: #f8f9fa;">
    <?php include 'navbar.php'; ?>

    <div class="container mt-4">

        
        
        <div class="card mb-4 shadow-lg cabecera-tarea">
             <h1 class="display-5 fw-bold text-dark mb-2"><i class="fas fa-clipboard-list me-2 text-primary"></i> Tarea #<?php echo $id_tarea; ?>: <?php echo htmlspecialchars($tarea['titulo']); ?></h1>
             <p class="fs-large text-muted mb-4"><span class="me-4"><i class="fas fa-user-tag me-1 text-secondary"></i> Asignado: <strong><?php echo htmlspecialchars($tarea['asignado_nombre'] ?? 'N/A'); ?></strong></span> <span class="me-4"><i class="fas fa-tags me-1 text-secondary"></i> Categoría: <strong><?php echo htmlspecialchars($tarea['categoria_nombre']); ?></strong></span> <span class="me-4"><i class="fas fa-user-circle me-1 text-secondary"></i> Creada por: <?php echo htmlspecialchars($tarea['creador_nombre']); ?></span></p>
             <div class="row g-3">
                 <div class="col-md-6"><div class="p-3 border rounded shadow-sm text-center bg-light"><strong class="text-secondary fs-5 d-block mb-1"><i class="fas fa-tachometer-alt me-1"></i> ESTADO</strong><?php echo traducir_estado($tarea['estado']); ?></div></div>
                 <div class="col-md-6"><div class="p-3 border rounded shadow-sm text-center bg-light"><strong class="text-secondary fs-5 d-block mb-1"><i class="fas fa-exclamation-circle me-1"></i> PRIORIDAD</strong><?php echo traducir_prioridad($tarea['prioridad']); ?></div></div>
             </div>
             
             <?php if ($es_admin_o_encargado && !in_array($tarea['estado'], ['verificada', 'cancelada'])): ?>
             <div class="mt-3 text-end">
                    <a href="tarea_editar.php?id=<?php echo $id_tarea; ?>" class="btn btn-sm btn-outline-primary"><i class="fas fa-edit"></i> Editar</a>
                    <?php if (in_array($tarea['estado'], ['asignada', 'en_proceso', 'modificacion_requerida', 'en_reserva'])): ?>
                        <button type="button" class="btn btn-sm btn-outline-danger ms-2" data-bs-toggle="modal" data-bs-target="#confirmarCancelarModal"><i class="fas fa-times-circle"></i> Cancelar Tarea</button>
                    <?php endif; ?>
                </div>
             <?php endif; ?>
        </div> <?php // <-- FIN DEL DIV DE LA CABECERA ?>

        <?php // <--- PEGA EL CÓDIGO AQUÍ ---> ?>
        <?php
        // Verificar si la tarea está verificada Y si proviene de un pedido (tiene id_pedido_origen)
        if (isset($tarea['estado']) && $tarea['estado'] === 'verificada' && isset($tarea['id_pedido_origen']) && $tarea['id_pedido_origen'] > 0):

            // --- Inicio: Lógica Opcional para obtener numero_orden ---
            $numero_orden_display = $tarea['id_pedido_origen']; // Valor por defecto si no hacemos JOIN
            try {
                // Hacemos una consulta rápida para obtener el numero_orden si no lo tienes ya en $tarea
                if (!isset($tarea['numero_orden_pedido'])) { // Solo si no existe ya en $tarea
                    $sql_orden = "SELECT numero_orden FROM pedidos_trabajo WHERE id_pedido = :id_pedido LIMIT 1";
                    $stmt_orden = $pdo->prepare($sql_orden);
                    $stmt_orden->execute([':id_pedido' => $tarea['id_pedido_origen']]);
                    $num_orden = $stmt_orden->fetchColumn();
                    if ($num_orden) {
                        $numero_orden_display = $num_orden;
                    }
                } elseif (!empty($tarea['numero_orden_pedido'])) {
                    $numero_orden_display = $tarea['numero_orden_pedido'];
                }
            } catch (PDOException $e) {
                error_log("Error al obtener numero_orden para PDF final: " . $e->getMessage());
                // No hacemos nada, se usará el id_pedido_origen
            }
            // --- Fin: Lógica Opcional ---
        ?>
            
        <?php
        endif;
        ?>
        <?php // <--- FIN DEL CÓDIGO PEGADO ---> ?>

        <ul class="nav nav-pills nav-fill mb-3" id="tareaTab" role="tablist">
             <li class="nav-item"><button class="nav-link active" id="detalles-tab" data-bs-toggle="tab" data-bs-target="#detalles" type="button">**DETALLES**</button></li>
             <li class="nav-item"><button class="nav-link" id="archivos-tab" data-bs-toggle="tab" data-bs-target="#archivos" type="button">**DOCUMENTOS** (<?php echo count($adjuntos_iniciales) + count($adjuntos_finales_raw); ?>)</button></li>
             <li class="nav-item"><button class="nav-link" id="actualizaciones-tab" data-bs-toggle="tab" data-bs-target="#actualizaciones" type="button">**ACTUALIZACIONES** (<?php echo count($actualizaciones); ?>)</button></li>
             
             <?php if (($es_tecnico_asignado || $es_admin_o_encargado) && !in_array($tarea['estado'], ['verificada', 'cancelada'])): ?>
             <li class="nav-item">
                    <button class="nav-link bg-danger text-white" id="acciones-tab" data-bs-toggle="tab" data-bs-target="#acciones" type="button">**ACCIONES**</button>
                </li>
             <?php endif; ?>
        </ul>

        <div class="tab-content border rounded p-4 bg-white shadow-sm mb-5">

            <div class="tab-pane fade show active" id="detalles" role="tabpanel">
                <div class="row">
                    <div class="col-md-8">
                        <h4 class="mb-3 text-primary"><i class="fas fa-file-alt me-2"></i> Descripción</h4>
                        <div class="border p-4 rounded bg-light mb-4 fs-large preserve-whitespace"><?php echo htmlspecialchars($tarea['descripcion']); ?></div>
                        <?php if ($tarea['nota_final']): ?>
                            <div class="card border-success shadow-sm">
                                <div class="card-header bg-success text-white fs-5"><i class="fas fa-sticky-note me-2"></i> Reporte Técnico</div>
                                <div class="card-body fs-large">
                                    <p class="lead preserve-whitespace"><?php echo htmlspecialchars($tarea['nota_final']); ?></p>
                                    <?php if (!empty($adjuntos_finales_raw)): ?>
                                        <a href="#archivos" class="btn btn-sm btn-outline-success mt-2" onclick="activateTabByIdAndHighlight('#archivos', '#final-docs-section')"><i class="fas fa-paperclip"></i> Ver Adjuntos Finales</a>
                                    <?php endif; ?>
                                    <?php if ($tarea['fecha_cierre']): ?>
                                        <small class="text-muted d-block mt-2">Finalizada: <?php echo date('d/m/Y H:i', strtotime($tarea['fecha_cierre'])); ?></small>
                                    <?php endif; ?>
                                </div>
                            </div>
                        <?php endif; ?>
                    </div>
                    <div class="col-md-4">
                        <div class="card shadow-sm border-info">
                            <div class="card-header bg-info text-white fs-5"><i class="fas fa-calendar-alt me-2"></i> Control</div>
                            <ul class="list-group list-group-flush small">
                                <li class="list-group-item"><strong>ID:</strong> #<?php echo $id_tarea; ?></li>
                                <li class="list-group-item"><strong>Creación:</strong> <?php echo date('d/m/Y H:i', strtotime($tarea['fecha_creacion'])); ?></li>
                                <li class="list-group-item"><strong>Límite:</strong> <span class="<?php echo ($tarea['fecha_limite'] && $tarea['fecha_limite'] < date('Y-m-d') && !in_array($tarea['estado'], ['verificada', 'cancelada'])) ? 'text-danger fw-bold' : ''; ?>"><?php echo $tarea['fecha_limite'] ? date('d/m/Y', strtotime($tarea['fecha_limite'])) : 'N/A'; ?></span></li>
                                <li class="list-group-item"><strong>Adj. Final:</strong> <?php echo $tarea['adjunto_obligatorio'] ? '<span class="badge bg-danger">OBLIGATORIO</span>' : '<span class="badge bg-success">OPCIONAL</span>'; ?></li>
                            </ul>
                        </div>
                        <div class="my-4 text-center"> 
                            <a href="generar_pedido_pdf.php?id=<?php echo $tarea['id_pedido_origen']; ?>&modo=final"
                            target="_blank"
                            class="btn btn-lg btn-info text-white shadow">
                                <i class="fas fa-file-pdf me-2"></i> Ver Informe PDF
                            </a>
                            <p class="text-muted small mt-2">Generado a partir del Pedido #<?php echo htmlspecialchars($numero_orden_display); ?></p>
                        </div>
                    </div>
                </div>
            </div>

            <div class="tab-pane fade" id="archivos" role="tabpanel">
                 <h4 class="mb-3 text-info"><i class="fas fa-upload me-2"></i> Documentos Iniciales</h4><hr>
                 <?php if (!empty($adjuntos_iniciales)): ?><ul class="list-group list-group-flush mb-4 border rounded"><?php foreach ($adjuntos_iniciales as $adj): $f = urlencode($adj['ruta_archivo']); $fp = "uploads/tareas/{$f}"; $fn = htmlspecialchars($adj['nombre_archivo']); $ext = strtolower(pathinfo($fn, PATHINFO_EXTENSION)); $is_pdf = ($ext === 'pdf'); $is_image = in_array($ext, ['jpg', 'jpeg', 'png', 'gif', 'webp', 'bmp']); $ic = 'fa-file-alt'; $tc = 'text-muted'; if ($is_pdf) $ic = 'fa-file-pdf text-danger'; elseif ($is_image) $ic = 'fa-image text-info'; ?><li class="list-group-item d-flex justify-content-between align-items-center fs-6"><span class="<?php echo $tc; ?>"><i class="fas <?php echo $ic; ?> me-2"></i> <?php echo $fn; ?></span><div><?php if ($is_image): ?><a href="#" class="btn btn-sm btn-outline-info me-2" data-bs-toggle="modal" data-bs-target="#imageModal" data-bs-image-url="<?php echo $fp; ?>"><i class="fas fa-eye"></i> Ver</a><?php elseif ($is_pdf): ?><a href="<?php echo $fp; ?>" target="_blank" class="btn btn-sm btn-outline-info me-2"><i class="fas fa-eye"></i> Ver</a><?php endif; ?><a href="descargar_adjunto.php?id=<?php echo $adj['id_adjunto']; ?>" class="btn btn-sm btn-outline-primary" title="Descargar <?php echo $fn; ?>"><i class="fas fa-download"></i> Descargar</a></div></li><?php endforeach; ?></ul><?php else: ?><p class="alert alert-light border"><i class="fas fa-box-open me-2"></i> No hay documentos iniciales adjuntos.</p><?php endif; ?>
                 <div id="final-docs-section"><h4 class="mb-3 mt-4 text-success"><i class="fas fa-file-invoice me-2"></i> Documentos Finales</h4><hr><?php if (!empty($adjuntos_finales_agrupados)): ?><?php foreach ($adjuntos_finales_agrupados as $fecha_grupo => $adjuntos_grupo): ?><div class="mb-4"><h6 class="text-muted border-bottom pb-1 mb-2"><i class="fas fa-calendar-day me-1"></i> Entrega del <?php echo date('d/m/Y', strtotime($fecha_grupo)); ?></h6><ul class="list-group list-group-flush border rounded"><?php foreach ($adjuntos_grupo as $adj): $f = urlencode($adj['ruta_archivo']); $fp = "uploads/tareas/{$f}"; $fn = htmlspecialchars($adj['nombre_archivo']); $ext = strtolower(pathinfo($fn, PATHINFO_EXTENSION)); $is_pdf = ($ext === 'pdf'); $is_image = in_array($ext, ['jpg', 'jpeg', 'png', 'gif', 'webp', 'bmp']); $ic = 'fa-file-alt'; $tc = 'text-success'; if ($is_pdf) $ic = 'fa-file-pdf text-danger'; elseif ($is_image) $ic = 'fa-image text-info'; ?><li class="list-group-item d-flex justify-content-between align-items-center fs-6 bg-light border-success"><span class="<?php echo $tc; ?>"><i class="fas <?php echo $ic; ?> me-2"></i> <?php echo $fn; ?> <small class="text-muted">(<?php echo date('H:i', strtotime($adj['fecha_subida'])); ?>)</small></span><div><?php if ($is_image): ?><a href="#" class="btn btn-sm btn-outline-info me-2" data-bs-toggle="modal" data-bs-target="#imageModal" data-bs-image-url="<?php echo $fp; ?>"><i class="fas fa-eye"></i> Ver</a><?php elseif ($is_pdf): ?><a href="<?php echo $fp; ?>" target="_blank" class="btn btn-sm btn-outline-info me-2"><i class="fas fa-eye"></i> Ver</a><?php endif; ?><a href="descargar_adjunto.php?id=<?php echo $adj['id_adjunto']; ?>" class="btn btn-sm btn-success" title="Descargar <?php echo $fn; ?>"><i class="fas fa-download"></i> Descargar</a></div></li><?php endforeach; ?></ul></div><?php endforeach; ?><?php else: ?><p class="alert alert-light border"><i class="fas fa-file-excel me-2"></i> No hay documentos finales adjuntos.</p><?php endif; ?></div>
            </div>

            <div class="tab-pane fade" id="actualizaciones" role="tabpanel">
                 <h4 class="mb-4 text-secondary"><i class="fas fa-comment-dots me-2"></i> Historial de Novedades</h4>

                 <?php
                 $esta_en_reserva = ($tarea['estado'] === 'en_reserva');
                 $is_finalized = in_array($tarea['estado'], ['finalizada_tecnico', 'verificada', 'cancelada']);
                 $can_update = ($es_tecnico_asignado && !$is_finalized && !$esta_en_reserva && $tarea['estado'] !== 'asignada');
                 
                 // Variables de control para el threading visual
                 $is_next_correction_thread = false;
                 ?>

                 <?php if ($can_update): // Mostrar formulario de actualización ?>
                     <div class="card mb-4 shadow-sm">
                         <div class="card-header bg-primary text-white">Registrar Novedad</div>
                         <div class="card-body">
                             <form action="<?php echo $action_url_actualizar; ?>" method="POST" enctype="multipart/form-data">
                                 <input type="hidden" name="id_tarea" value="<?php echo $id_tarea; ?>">
                                 <input type="hidden" name="id_remito_corregido" id="id_remito_corregido" value="0">
                                 
                                 <div class="mb-3">
                                     <label for="contenido_actualizacion" class="form-label fw-bold">Contenido de la Novedad (*)</label>
                                     <textarea class="form-control" id="contenido_actualizacion" name="contenido" rows="4" placeholder="Describir el progreso, solicitar materiales, etc..." required></textarea>
                                 </div>

                                 <div class="mb-3">
                                     <label for="adjuntos_actualizacion" class="form-label fw-bold"><i class="fas fa-paperclip"></i> Adjuntar Archivos (Opcional)</label>
                                     <input class="form-control" type="file" id="adjuntos_actualizacion" name="adjuntos_actualizacion[]" multiple>
                                     <small class="text-muted">Adjuntar fotos de progreso, notas, etc.</small>
                                 </div>

                                 <div class="mb-3">
                                     <label for="adjuntos_remito" class="form-label fw-bold text-success"><i class="fas fa-file-invoice-dollar"></i> Adjuntar Remito o Factura (Opcional)</label>
                                     <input class="form-control" type="file" id="adjuntos_remito" name="adjuntos_remito[]" multiple>
                                     <small class="text-muted">Adjuntar solo remitos o facturas. Estos se guardarán por separado.</small>

                                     <div id="remito-details-container" style="display: none;" class="mt-3 p-3 border rounded bg-light">
                                         <p class="fw-bold text-success mb-2"><i class="fas fa-info-circle"></i> Detalles del Remito/Factura (Opcional)</p>
                                         <div class="mb-2">
                                             <label for="remito_descripcion" class="form-label form-label-sm">Descripción Compra:</label>
                                             <input type="text" class="form-control form-control-sm" id="remito_descripcion" name="remito_descripcion" placeholder="Ej: Materiales eléctricos">
                                         </div>
                                         <div class="row gx-2">
                                             <div class="col-sm-6">
                                                 <label for="remito_precio" class="form-label form-label-sm">Precio Total:</label>
                                                 <input type="number" step="0.01" class="form-control form-control-sm" id="remito_precio" name="remito_precio" placeholder="Ej: 1500.50">
                                             </div>
                                             <div class="col-sm-6">
                                                 <label for="remito_numero" class="form-label form-label-sm">Nº Compra/Factura:</label>
                                                 <input type="text" class="form-control form-control-sm" id="remito_numero" name="remito_numero" placeholder="Ej: FC-001-12345">
                                             </div>
                                         </div>
                                         <small class="text-muted d-block mt-2">Complete estos datos si corresponde al archivo adjunto.</small>
                                     </div>
                                 </div>
                                 <div class="form-check form-switch mb-3">
                                     <input class="form-check-input" type="checkbox" role="switch" id="ponerEnReserva" name="poner_en_reserva" value="1">
                                     <label class="form-check-label fw-bold text-danger" for="ponerEnReserva">
                                         <i class="fas fa-pause-circle me-1"></i> Poner Tarea "En Reserva" (Ej: Esperando Materiales)
                                     </label>
                                     <small class="text-muted d-block">Marca esto si la tarea no puede continuar hasta recibir algo externo.</small>
                                 </div>

                                 <button type="submit" class="btn btn-primary"><i class="fas fa-plus-circle"></i> Registrar Novedad</button>
                             </form>
                         </div>
                     </div>
                 <?php elseif ($es_tecnico_asignado && $esta_en_reserva): // Mensaje si está en reserva ?>
                     <div class="alert alert-dark text-center border-dark shadow-sm" role="alert">
                         <h5 class="alert-heading"><i class="fas fa-pause-circle me-2"></i> Tarea "En Reserva"</h5>
                         <p>La tarea está pausada, probablemente esperando materiales o acción externa.</p>
                         <hr>
                         <p class="mb-0">Cuando puedas continuar, ve a la pestaña **ACCIONES** y selecciona **"Quitar Estado de Reserva"**.</p>
                     </div>
                 <?php elseif ($es_tecnico_asignado && $tarea['estado'] === 'asignada'): // Mensaje si está asignada ?>
                      <div class="alert alert-warning text-center" role="alert">
                          <h5 class="alert-heading"><i class="fas fa-exclamation-triangle me-2"></i> Tarea No Iniciada</h5>
                          <p class="mb-0">Debes **Iniciar la Tarea** en la pestaña **"ACCIONES"** antes de poder agregar novedades.</p>
                      </div>
                 <?php elseif ($es_tecnico_asignado && $is_finalized): ?>
                     <div class="alert alert-info text-center"><i class="fas fa-info-circle me-2"></i> Tarea finalizada/cancelada. No se pueden agregar más novedades.</div>
                 
                 <?php elseif (!$es_tecnico_asignado && $es_admin_o_encargado): ?>
                 <div class="alert alert-secondary text-center"><i class="fas fa-user-shield me-2"></i> Solo puedes ver el historial de novedades.</div>
                 <?php endif; ?>

                 <h4 class="mt-4 mb-3">Historial</h4><hr>
                 <?php if (!empty($actualizaciones)): 
                     $updates_indexed = array_values($actualizaciones);
                 ?>
                     <div class="list-group">
                         <?php foreach ($updates_indexed as $index => $actualizacion): ?>
                             <?php
                             // 1. Inicializar banderas
                             $current_user_id = $actualizacion['id_usuario'];
                             $current_update_id = $actualizacion['id_actualizacion'];
                             $is_rejected_remito_current = false;
                             $is_correction_thread_current = false;
                             
                             // Buscar si esta actualización contiene un remito rechazado (para el estilo ROJO)
                             $adjuntos_actuales = $adjuntos_por_actualizacion[$current_update_id] ?? [];
                             $adjuntos_rem = array_filter($adjuntos_actuales, fn($adj) => $adj['tipo_adjunto'] === 'remito');

                             if (!empty($adjuntos_rem)) {
                                 $first_remito = reset($adjuntos_rem);
                                 // Marcamos la actualización actual si su remito fue rechazado
                                 if (isset($first_remito['estado_conciliacion']) && $first_remito['estado_conciliacion'] === 'rechazado') {
                                     $is_rejected_remito_current = true;
                                 }
                             }
                             
                             // 2. Heurística de Threading (Reverse Order: Newest first)
                             // Verificamos si el ÍTEM SIGUIENTE (más antiguo, índice + 1) en la lista fue un rechazo.
                             $next_index = $index + 1;
                             
                             if (isset($updates_indexed[$next_index])) {
                                 $next_update = $updates_indexed[$next_index];
                                 $next_update_user_id = $next_update['id_usuario'];
                                 $next_update_adjuntos = $adjuntos_por_actualizacion[$next_update['id_actualizacion']] ?? [];
                                 $next_update_remitos = array_filter($next_update_adjuntos, fn($adj) => $adj['tipo_adjunto'] === 'remito');
                                 
                                 // Si el ítem más antiguo (índice + 1) es un remito que FUE rechazado
                                 if (!empty($next_update_remitos)) {
                                     $next_remito = reset($next_update_remitos);
                                     if (isset($next_remito['estado_conciliacion']) && $next_remito['estado_conciliacion'] === 'rechazado') {
                                          // Y si la actualización actual (la corrección) fue hecha por el mismo usuario
                                          // Esto crea el enlace visual: [Corrección] -> [Rechazo]
                                          if ($current_user_id === $next_update_user_id) {
                                               $is_correction_thread_current = true;
                                          }
                                     }
                                 }
                             }
                             
                             // 3. Aplicar estilos
                             
                             // Estilos base (causo_reserva/solicitud)
                             $item_class = 'list-group-item-action';
                             if (isset($actualizacion['causo_reserva']) && $actualizacion['causo_reserva'] == 1) {
                                 $item_class = 'update-en-reserva';
                             } elseif (str_starts_with($actualizacion['contenido'], '[SOLICITUD TÉCNICO]') || str_starts_with($actualizacion['contenido'], 'SOLICITUD DE MODIFICACIÓN:')) {
                                 $item_class = 'update-solicitud';
                             }

                             // Aplicar Threading (sobrescribe todo)
                             if ($is_rejected_remito_current) {
                                 $item_class = 'update-rejected-remito'; // ROJO
                             } 
                             // Si es el ítem de corrección, aplica el estilo de hilo (VERDE con sangría)
                             if ($is_correction_thread_current) {
                                 $item_class = 'correction-thread'; // VERDE con sangría
                             }

                             ?>
                             <div class="list-group-item <?php echo $item_class; ?> flex-column align-items-start" id="update-item-<?php echo $current_update_id; ?>">
                                 <div class="d-flex w-100 justify-content-between">
                                     <h5 class="mb-1 text-primary"><i class="fas fa-user-edit"></i> <?php echo htmlspecialchars($actualizacion['usuario_nombre'] ?? 'Desconocido'); ?></h5>
                                     <small class="text-muted"><?php echo date('d/m/Y H:i', strtotime($actualizacion['fecha_actualizacion'])); ?></small>
                                 </div>
                                 <p class="mb-1 preserve-whitespace"><?php echo htmlspecialchars($actualizacion['contenido']); ?></p>

                                 <?php $adjuntos_actuales = $adjuntos_por_actualizacion[$current_update_id] ?? []; ?>
                                 <?php if (!empty($adjuntos_actuales)): ?>
                                     <div class="mt-2">
                                         <?php // Separar adjuntos y remitos
                                         $adjuntos_gen = array_filter($adjuntos_actuales, fn($adj) => $adj['tipo_adjunto'] === 'actualizacion');
                                         $adjuntos_rem = array_filter($adjuntos_actuales, fn($adj) => $adj['tipo_adjunto'] === 'remito');
                                         ?>
                                         <?php if (!empty($adjuntos_gen)): ?>
                                             <small class="text-muted"><i class="fas fa-paperclip"></i> Adjuntos:</small>
                                             <ul class="list-unstyled d-flex flex-wrap gap-2 mt-1">
                                                 <?php foreach ($adjuntos_gen as $adjunto): ?>
                                                 <li>
                                                     <a href="ver_adjunto.php?id=<?php echo $adjunto['id_adjunto']; ?>" target="_blank" 
                                                        class="btn btn-sm btn-outline-info me-1" 
                                                        title="Ver <?php echo htmlspecialchars($adjunto['nombre_archivo']); ?>">
                                                        <i class="fas fa-eye"></i> Ver
                                                     </a>
                                                     <a href="descargar_adjunto.php?id=<?php echo $adjunto['id_adjunto']; ?>" 
                                                        class="btn btn-sm btn-outline-secondary" 
                                                        title="Descargar <?php echo htmlspecialchars($adjunto['nombre_archivo']); ?>">
                                                        <i class="fas fa-file-download"></i>
                                                     </a>
                                                 </li>
                                                 <?php endforeach; ?>
                                             </ul>
                                         <?php endif; ?>
                                         <?php if (!empty($adjuntos_rem)): ?>
                                             <small class="text-success fw-bold"><i class="fas fa-file-invoice-dollar"></i> Remitos/Facturas:</small>
                                             <ul class="list-unstyled d-flex flex-wrap gap-2 mt-1">
                                                 <?php foreach ($adjuntos_rem as $adjunto): ?>
                                                 <li>
                                                      <a href="ver_adjunto.php?id=<?php echo $adjunto['id_adjunto']; ?>" target="_blank" 
                                                        class="btn btn-sm btn-outline-info me-1" 
                                                        title="Ver <?php echo htmlspecialchars($adjunto['nombre_archivo']); ?>">
                                                        <i class="fas fa-eye"></i> Ver
                                                     </a>
                                                     <a href="descargar_adjunto.php?id=<?php echo $adjunto['id_adjunto']; ?>" 
                                                        class="btn btn-sm btn-outline-success" 
                                                        title="Descargar <?php echo htmlspecialchars($adjunto['nombre_archivo']); ?>">
                                                        <i class="fas fa-file-download"></i>
                                                     </a>
                                                 </li>
                                                 <?php endforeach; ?>
                                             </ul>
                                             <?php $first_remito = reset($adjuntos_rem); if ($first_remito && (!empty($first_remito['descripcion_compra']) || !empty($first_remito['precio_total']) || !empty($first_remito['numero_compra']))): ?>
                                                 <div class="alert alert-success p-2 mt-2 small">
                                                     <strong>Detalles:</strong>
                                                     <?php if (!empty($first_remito['descripcion_compra'])) echo '<span class="d-block">Compra: ' . htmlspecialchars($first_remito['descripcion_compra']) . '</span>'; ?>
                                                     <?php if (!empty($first_remito['precio_total'])) echo '<span class="d-block">Precio: $' . number_format((float)$first_remito['precio_total'], 2, ',', '.') . '</span>'; ?>
                                                     <?php if (!empty($first_remito['numero_compra'])) echo '<span class="d-block">Nº Factura: ' . htmlspecialchars($first_remito['numero_compra']) . '</span>'; ?>
                                                 </div>
                                             <?php endif; ?>
                                         <?php endif; ?>
                                     </div>
                                 <?php endif; ?>
                             </div>
                         <?php endforeach; ?>
                     </div>
                 <?php else: ?>
                     <div class="alert alert-light border text-center mt-3">No hay actualizaciones registradas para esta tarea.</div>
                 <?php endif; ?>
            </div>
            <div class="tab-pane fade" id="acciones" role="tabpanel">
                 <h4 class="mb-4 text-danger"><i class="fas fa-gavel me-2"></i> Acciones Disponibles</h4>

                 <?php // --- ACCIONES PARA EL TÉCNICO ASIGNADO --- ?>
                 <?php if ($es_tecnico_asignado): ?>
                     <?php if ($tarea['estado'] === 'asignada'): ?>
                         <div class="alert alert-info text-center">Tarea pendiente de iniciar. <button class="btn btn-warning btn-lg mt-2" id="btnIniciarTarea" data-id-tarea="<?php echo $id_tarea; ?>"><i class="fas fa-play-circle"></i> Iniciar Tarea</button></div>

                     <?php elseif ($tarea['estado'] === 'en_reserva'): ?>
                         <div class="alert alert-dark text-center border-dark shadow-sm" role="alert">
                             <h5 class="alert-heading"><i class="fas fa-play-circle me-2"></i> Reanudar mi Tarea</h5>
                             <p>La tarea está actualmente "En Reserva". Haz clic abajo para continuar con el trabajo.</p>
                             <hr>
                             <form action="<?php echo $action_url_estado; ?>" method="POST" class="d-inline">
                                 <input type="hidden" name="id_tarea" value="<?php echo $id_tarea; ?>">
                                 
                                      <input type="hidden" name="nuevo_estado" value="reanudar_reserva">
                                                                  <button type="submit" class="btn btn-success btn-lg">
                                     <i class="fas fa-play"></i> Quitar Estado de Reserva y Continuar
                                 </button>
                             </form>
                             <p class="mt-3 mb-0 small text-muted">Luego podrás adjuntar remitos o agregar más novedades en la pestaña "Actualizaciones".</p>
                         </div>
                     <?php elseif (in_array($tarea['estado'], ['en_proceso', 'modificacion_requerida'])): ?>
                         <hr class="my-4">
                         <h5 class="mb-3 text-success"><i class="fas fa-check-circle me-2"></i> Finalizar y Enviar Tarea</h5>
                         <p>Completa la nota final detallando el trabajo realizado y adjunta los archivos finales si son necesarios.</p>
                         <?php if ($tarea['estado'] === 'modificacion_requerida'): ?>
                             <div class="alert alert-danger border-danger">
                                 <h6 class="alert-heading fw-bold"><i class="fas fa-exclamation-triangle me-1"></i> Modificación Solicitada por Admin</h6>
                                 <p class="small mb-1">Motivo:</p>
                                 <p class="fst-italic preserve-whitespace">"<?php echo nl2br(htmlspecialchars($nota_modificacion_admin)); ?>"</p>
                                 <p class="mb-0 small">Por favor, corrige lo indicado y vuelve a enviar la tarea.</p>
                             </div>
                         <?php endif; ?>
                         <form action="<?php echo $action_url_finalizar; ?>" method="POST" enctype="multipart/form-data" class="border p-4 rounded bg-light mt-3">
                             <input type="hidden" name="id_tarea" value="<?php echo $id_tarea; ?>">
                             <div class="mb-3">
                                 <label for="nota_final" class="form-label fw-bold">Nota Final (*)</label>
                                 <textarea class="form-control" id="nota_final" name="nota_final" rows="4" placeholder="Describe detalladamente el trabajo realizado..." required></textarea>
                             </div>
                             <div class="mb-4">
                                 <label for="adjunto_final" class="form-label fw-bold">Adjuntos <?php echo $tarea['adjunto_obligatorio'] ? '<span class="text-danger">(*)</span>' : '<span class="text-secondary">(Opc.)</span>'; ?></label>
                                 <input class="form-control" type="file" id="adjunto_final" name="adjunto_final[]" multiple <?php if ($tarea['adjunto_obligatorio']) echo 'required'; ?>>
                                 <?php if ($tarea['adjunto_obligatorio']): ?>
                                     <small class="text-danger d-block mt-1">Es obligatorio adjuntar al menos un archivo para finalizar.</small>
                                 <?php endif; ?>
                             </div>
                             <button type="submit" class="btn btn-success btn-lg w-100">
                                 <i class="fas fa-paper-plane"></i> Enviar a Revisión
                             </button>
                         </form>
                     <?php elseif ($tarea['estado'] === 'finalizada_tecnico'): ?>
                         <div class="alert alert-primary text-center">
                             <i class="fas fa-paper-plane me-2"></i> Tarea enviada y pendiente de revisión por el administrador.
                             <br> Si necesitas corregir algo antes de que la revisen, puedes solicitar que te la devuelvan.
                             <div class="mt-3">
                                 <button type="button" class="btn btn-warning" data-bs-toggle="modal" data-bs-target="#solicitarRecallModal">
                                     <i class="fas fa-undo"></i> Solicitar Corrección
                                 </button>
                             </div>
                         </div>
                     <?php elseif (in_array($tarea['estado'], ['verificada', 'cancelada'])): ?>
                         <div class="alert alert-<?php echo $tarea['estado'] === 'verificada' ? 'success' : 'secondary'; ?> text-center">
                             Tarea <?php echo traducir_estado($tarea['estado']); ?>. No hay más acciones disponibles.
                         </div>
                      <?php else: ?>
                         <div class="alert alert-secondary text-center">No hay acciones disponibles en este estado.</div>
                     <?php endif; ?>

                 <?php // --- ACCIONES PARA EL ADMINISTRADOR/ENCARGADO --- ?>
                 <?php elseif ($es_admin_o_encargado): ?>
                 <?php if ($tarea['estado'] === 'finalizada_tecnico'): ?>
                         <div class="card border-primary shadow-sm">
                             <div class="card-header bg-primary text-white fs-5"><i class="fas fa-check-double me-2"></i> Revisión Pendiente</div>
                             <div class="card-body">
                                 <h5 class="mb-3">Acciones Tarea #<?php echo $id_tarea; ?></h5>
                                 <blockquote class="blockquote border-start border-success border-5 ps-3 py-2 bg-light rounded mb-3">
                                     <p class="mb-1 small fw-bold">Reporte Técnico:</p>
                                     <p class="mb-0 small fst-italic preserve-whitespace"><?php echo htmlspecialchars($tarea['nota_final'] ?? 'Sin nota.'); ?></p>
                                 </blockquote>

                                 <?php if ($show_pending_recall_warning): // (Fusión: Lógica de Recall de tarea_ver_r.php) ?>
                                     <div class="alert alert-warning border-warning fw-bold mt-3 animate__animated animate__pulse animate__infinite" role="alert" style="--animate-duration: 2s;">
                                         <h5 class="alert-heading"><i class="fas fa-exclamation-triangle me-2"></i> ¡Atención! El técnico solicita corregir</h5>
                                         <p class="mb-1"><strong>Motivo indicado:</strong></p>
                                         <p class="fst-italic preserve-whitespace">"<?php echo htmlspecialchars($recall_reason); ?>"</p>
                                         <?php if ($latest_recall_request): ?>
                                         <small class="text-muted">Fecha Solicitud: <?php echo date('d/m/Y H:i', strtotime($latest_recall_request['fecha_actualizacion'])); ?></small>
                                         <?php endif; ?>
                                         <hr>
                                         <p class="mb-0 small text-danger fw-bold">Para aceptar esta solicitud y devolver la tarea, utiliza el formulario <span class="text-decoration-underline">"Devolver Tarea al Técnico"</span> más abajo.</p>
                                     </div>
                                 <?php endif; ?>

                                 <form action="<?php echo $action_url_estado; ?>" method="POST" class="d-grid gap-3 mt-4"
                                       onsubmit="<?php echo $show_pending_recall_warning ? "return confirm('ADVERTENCIA:\\n\\nEl técnico solicitó una corrección para esta tarea.\\n\\n¿Estás seguro de que quieres APROBARLA Y CERRARLA igualmente, ignorando la solicitud del técnico?');" : ""; ?>">
                                     <input type="hidden" name="id_tarea" value="<?php echo $id_tarea; ?>">
                                     <button type="submit" name="nuevo_estado" value="verificada" class="btn btn-success btn-lg w-100 <?php echo $show_pending_recall_warning ? 'opacity-75' : ''; ?>"
                                             <?php echo $show_pending_recall_warning ? 'title="Cuidado: Hay una solicitud de corrección pendiente del técnico."' : ''; ?>>
                                         <i class="fas fa-check"></i> Aprobar y Cerrar Tarea <?php echo $show_pending_recall_warning ? '<span class=\'badge bg-dark ms-1\'>Ignorar Solicitud</span>' : ''; ?>
                                     </button>
                                 </form>

                                 <hr class="my-4">

                                 <h5 class="text-danger"><i class="fas fa-undo me-1"></i> <?php echo $show_pending_recall_warning ? 'Aceptar Solicitud y Devolver Tarea' : 'Solicitar Modificación (Admin)'; ?></h5>
                                 <form action="<?php echo $action_url_estado; ?>" method="POST" class="d-grid gap-3 mt-2 border <?php echo $show_pending_recall_warning ? 'border-danger border-3 shadow' : 'border-secondary'; ?> p-3 rounded bg-light">
                                     <input type="hidden" name="id_tarea" value="<?php echo $id_tarea; ?>">
                                     <div class="mb-1">
                                         <label for="nota_admin" class="form-label fw-bold">Motivo para devolver (*):</label>
                                         <textarea class="form-control" id="nota_admin" name="nota_admin" rows="3" placeholder="Indica qué debe corregir el técnico o confirma la solicitud..." required><?php
                                             echo $show_pending_recall_warning ? "Aceptando solicitud del técnico por el siguiente motivo:\n" . htmlspecialchars($recall_reason) . "\n\n[Comentario adicional si es necesario]:\n" : "";
                                         ?></textarea>
                                          <small class="text-muted">Este motivo se enviará al técnico.</small>
                                     </div>
                                     <button type="submit" name="nuevo_estado" value="modificacion_requerida" class="btn btn-danger w-100">
                                         <i class="fas fa-undo"></i> Devolver Tarea al Técnico
                                     </button>
                                 </form>
                             </div>
                         </div>
                     <?php elseif (in_array($tarea['estado'], ['asignada', 'en_proceso', 'modificacion_requerida', 'en_reserva'])): // (Fusión: Añadido 'en_reserva') ?>
                         <div class="alert alert-info text-center">
                             <i class="fas fa-info-circle me-2"></i> Tarea <?php echo $tarea['estado'] === 'en_reserva' ? 'actualmente "En Reserva"' : 'en curso o pendiente'; ?>.<br> Puedes editar detalles o cancelarla si es necesario.
                             <div class="mt-3">
                                 <a href="tarea_editar.php?id=<?php echo $id_tarea; ?>" class="btn btn-primary"><i class="fas fa-edit me-1"></i> Editar Detalles</a>
                                 <button type="button" class="btn btn-danger ms-2" data-bs-toggle="modal" data-bs-target="#confirmarCancelarModal"><i class="fas fa-times-circle"></i> Cancelar Tarea</button>
                             </div>
                         </div>
                     <?php elseif (in_array($tarea['estado'], ['verificada', 'cancelada'])): ?>
                         <div class="alert alert-secondary text-center">
                             <i class="fas fa-lock me-2"></i> Tarea <?php echo traducir_estado($tarea['estado']); ?>.<br> No hay acciones administrativas disponibles.
                         </div>
                     <?php else: ?>
                         <div class="alert alert-warning text-center">
                             <i class="fas fa-exclamation-triangle me-2"></i> Estado de tarea no reconocido: <?php echo htmlspecialchars($tarea['estado']); ?>
                         </div>
                     <?php endif; ?>
                 <?php endif; // Fin del bloque de acciones ?>
            </div>
        </div>
    </div>
    
    <div class="toast-container position-fixed bottom-0 end-0 p-3" id="notificationToastContainer" style="z-index: 1080;"></div>

    <div class="modal fade" id="imageModal" tabindex="-1"><div class="modal-dialog modal-xl modal-dialog-centered"><div class="modal-content"><div class="modal-header"><h5 class="modal-title">Vista Previa</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div><div class="modal-body text-center"><img id="modalImage" src="" class="img-fluid"></div></div></div></div>
    <div class="modal fade" id="confirmarInicioModal" tabindex="-1"><div class="modal-dialog"><div class="modal-content border-start border-5 border-warning"><div class="modal-header bg-warning text-dark"><h5 class="modal-title"><i class="fas fa-exclamation-triangle"></i> Confirmar</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div><div class="modal-body">¿Iniciar Tarea #<?php echo $id_tarea; ?>?</div><div class="modal-footer"><button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button><button type="button" class="btn btn-warning" id="btnConfirmarInicio">Sí</button></div></div></div></div>
    <div class="modal fade" id="modalAprobada" tabindex="-1"><div class="modal-dialog modal-dialog-centered"><div class="modal-content border-start border-5 border-success"><div class="modal-header bg-success text-white"><h5 class="modal-title"><i class="fas fa-check-double me-2"></i> ¡Aprobada!</h5><button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button></div><div class="modal-body text-center"><h3 class="text-success">¡Felicidades!</h3><p class="lead">Tarea aprobada.</p><img src="https://media1.giphy.com/media/v1.Y2lkPTc5MGI3NjExMzVoOWNsNWtjMm1uMTZtMThrbm82ZDFxNXU1eXp6NHFqbmFrdXZxbSZlcD12MV9pbnRlcm5hbF9naWZfYnlfaWQmY3Q9Zw/2HMUYBYrhg4Gk/giphy.gif" alt="¡Excelente!" class="img-fluid rounded shadow-sm" style="max-height: 250px;"></div><div class="modal-footer"><button type="button" class="btn btn-success" data-bs-dismiss="modal">¡Genial!</button></div></div></div></div>
    <div class="modal fade" id="modalModificacion" tabindex="-1"><div class="modal-dialog modal-dialog-centered modal-lg"><div class="modal-content border-start border-5 border-danger"><div class="modal-header bg-danger text-white"><h5 class="modal-title"><i class="fas fa-undo me-2"></i> Modificación</h5><button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button></div><div class="modal-body"><h4 class="text-danger">Cambios solicitados:</h4><div class="alert alert-warning mt-3"><p class="lead"><strong>Comentario Admin:</strong></p><blockquote class="blockquote"><p id="comentarioAdminModal" class="preserve-whitespace"><?php echo htmlspecialchars($nota_modificacion_admin); ?></p></blockquote></div><p class="mt-3">Revisa y reenvía desde **ACCIONES**.</p></div><div class="modal-footer"><button type="button" class="btn btn-primary" data-bs-dismiss="modal">Entendido</button></div></div></div></div>
    <div class="modal fade" id="confirmarCancelarModal" tabindex="-1"><div class="modal-dialog"><div class="modal-content border-start border-5 border-danger"><div class="modal-header bg-danger text-white"><h5 class="modal-title"><i class="fas fa-exclamation-triangle me-2"></i> Confirmar Cancelación</h5><button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button></div><div class="modal-body"><p class="lead">¿Cancelar tarea #<?php echo $id_tarea; ?>?</p><div class="alert alert-warning small"><i class="fas fa-info-circle me-1"></i> Se notificará al usuario.</div><form action="<?php echo $action_url_cancelar; ?>" method="POST" id="formCancelarTarea"><input type="hidden" name="id_tarea" value="<?php echo $id_tarea; ?>"><div class="mb-3"><label for="motivo_cancelacion" class="form-label fw-bold">Motivo (Opcional):</label><textarea class="form-control" id="motivo_cancelacion" name="motivo_cancelacion" rows="3" placeholder="Ej: Se resolvió por otro medio..."></textarea></div></form></div><div class="modal-footer"><button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Volver</button><button type="submit" form="formCancelarTarea" class="btn btn-danger"><i class="fas fa-ban me-1"></i> Sí, Cancelar</button></div></div></div></div>
    <div class="modal fade" id="modalCanceladaAdmin" tabindex="-1"><div class="modal-dialog modal-dialog-centered"><div class="modal-content border-start border-5 border-secondary"><div class="modal-header bg-secondary text-white"><h5 class="modal-title"><i class="fas fa-ban me-2"></i> Tarea Cancelada</h5><button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button></div><div class="modal-body text-center"><h3 class="text-secondary">¡Importante!</h3><p class="lead">La tarea #<?php echo $id_tarea; ?> fue cancelada.</p><?php if ($nota_cancelacion_admin) : ?><div class="alert alert-warning mt-3"><p class="small fw-bold">Motivo:</p><p class="small fst-italic preserve-whitespace"><?php echo htmlspecialchars(str_replace('TAREA CANCELADA POR ADMINISTRADOR. Motivo: ', '', $nota_cancelacion_admin)); ?></p></div><?php endif; ?><img src="https://media4.giphy.com/media/v1.Y2lkPTc5MGI3NjExemF0YzV1eG9xbWxtMGdleWU5M3I1Z3RodXI1bGM1dWduY3N1MDduaCZlcD12MV9pbnRlcm5hbF9naWZfYnlfaWQmY3Q9Zw/hriThcBWJcmBQy5wfN/giphy.gif" alt="Cancelado" class="img-fluid rounded shadow-sm" style="max-height: 200px;"></div><div class="modal-footer"><button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Entendido</button></div></div></div></div>
    <div class="modal fade" id="solicitarRecallModal" tabindex="-1"><div class="modal-dialog"><div class="modal-content border-start border-5 border-warning"><div class="modal-header bg-warning text-dark"><h5 class="modal-title"><i class="fas fa-undo me-2"></i> Solicitar Corrección</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div><form action="<?php echo $action_url_recall_request; ?>" method="POST" id="formRecallTarea"><div class="modal-body"><p>Solicitar que la tarea #<?php echo $id_tarea; ?> sea devuelta para corrección.</p><input type="hidden" name="id_tarea" value="<?php echo $id_tarea; ?>"><div class="mb-3"><label for="motivo_recall_tecnico" class="form-label fw-bold">Motivo (*):</label><textarea class="form-control" id="motivo_recall_tecnico" name="motivo_recall_tecnico" rows="3" placeholder="Ej: Me equivoqué de adjunto..." required></textarea></div><div class="alert alert-info small"><i class="fas fa-info-circle me-1"></i> El admin será notificado.</div></div><div class="modal-footer"><button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button><button type="submit" class="btn btn-warning"><i class="fas fa-paper-plane me-1"></i> Enviar Solicitud</button></div></form></div></div></div>
    <div class="modal fade" id="actionResultModal" tabindex="-1" aria-hidden="true"><div class="modal-dialog modal-dialog-centered"><div class="modal-content"><div class="modal-header"><h5 class="modal-title" id="actionResultModalLabel"><i class="fas fa-info-circle"></i> Resultado</h5><button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button></div><div class="modal-body"><p id="actionResultModalMessage" class="lead"></p></div><div class="modal-footer"><button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cerrar</button></div></div></div></div>
    <div class="modal fade" id="reassignInfoModal" tabindex="-1" aria-labelledby="reassignInfoModalLabel" aria-hidden="true" data-bs-backdrop="static" data-bs-keyboard="false"><div class="modal-dialog modal-dialog-centered"><div class="modal-content border-start border-5 border-info"><div class="modal-header bg-info text-white"> <h5 class="modal-title" id="reassignInfoModalLabel"><i class="fas fa-random me-2"></i> Información de Tarea</h5> </div><div class="modal-body"><p class="lead" id="reassignModalMessage">Mensaje sobre la reasignación.</p><div class="alert alert-secondary border py-2 px-3 mb-3"><h5 class="mb-1" style="text-align: left;"><strong>DETALLES DE LA TAREA</strong></h5><p class="mb-0 small">ID: <strong id="reassignTaskId">#</strong></p><p class="mb-0 small"><u>Título:</u> <strong id="reassignTaskTitle" class="text-dark"></strong></p><p class="mb-1 mt-0"><u>Descripción (inicio):</u></p><p class="mb-0 preserve-whitespace" id="reassignTaskDescription" style="max-height: 100px; overflow-y: auto;"></p> <hr class="my-2"><p class="mb-0 small"><u>Estado (Previo):</u> <span id="reassignTaskState"></span></p><p class="mb-0 small"><u>Prioridad:</u> <span id="reassignTaskPriority"></span></p><p class="mb-0 small"><u>Categoría:</u> <span id="reassignTaskCategory"></span></p></div><div id="reassignNewAssigneeInfo" style="display: none;" class="alert alert-light border mt-3 py-2 px-3"><p class="mb-1 small">Nuevo responsable:</p><p class="fw-bold fs-5 mb-0" id="reassignNewAssigniName"></p></div> <p class="mt-3 text-muted small" id="reassignModalSubtext">FEDE TAREA_VER.PHP</p></div><div class="modal-footer"> <button type="button" class="btn btn-primary" id="reassignModalConfirmButton" data-bs-dismiss="modal">Entendido</button> </div></div></div></div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // --- INICIO CÓDIGO JS COMPLETO (FUSIONADO) ---

        function showToast(title, message, type = 'success') {
            let i='', c='';
            if(type==='success'){i='<i class="fas fa-check-circle me-2"></i>';c='bg-success text-white';}
            else if(type==='danger'){i='<i class="fas fa-exclamation-triangle me-2"></i>';c='bg-danger text-white';}
            else if(type==='finalizada'){i='<i class="fas fa-clipboard-check me-2"></i>';c='bg-primary text-white';}
            else if(type==='cancelada'){i='<i class="fas fa-ban me-2"></i>';c='bg-secondary text-white';}
            const t=`<div class="toast align-items-center ${c}" role="alert" aria-live="assertive" aria-atomic="true" data-bs-delay="7000"><div class="d-flex"><div class="toast-body"><strong>${i}${title}</strong><br>${message}</div><button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast"></button></div></div>`;
            const tc=document.getElementById('notificationToastContainer');
            if(tc){tc.insertAdjacentHTML('beforeend',t); const nt=tc.lastElementChild; const tb=bootstrap.Toast.getOrCreateInstance(nt); tb.show();}
        }

        function activateTabByIdAndHighlight(tabTargetId, elementToHighlightId) {
            if (!tabTargetId || !elementToHighlightId) return;
            const tabTriggerEl = document.querySelector(`.nav-pills button[data-bs-target="${tabTargetId}"]`);
            const elementToHighlight = document.querySelector(elementToHighlightId);
            if (tabTriggerEl) {
                try {
                    const tab = bootstrap.Tab.getOrCreateInstance(tabTriggerEl);
                    if (tab) {
                        tab.show();
                        console.log(`Pestaña ${tabTargetId} activada.`);
                        setTimeout(() => {
                            if (elementToHighlight) {
                                console.log(`Resaltando ${elementToHighlightId}`);
                                elementToHighlight.classList.add('highlight-section-flash');
                                elementToHighlight.scrollIntoView({ behavior: 'smooth', block: 'center' });
                                setTimeout(() => { elementToHighlight.classList.remove('highlight-section-flash'); }, 2000);
                            } else { console.warn(`Elemento ${elementToHighlightId} no encontrado.`); }
                        }, 300);
                    }
                } catch (e) { console.error("Error al activar pestaña:", e); }
            } else { console.warn(`No botón para target: ${tabTargetId}`); }
        }

        document.addEventListener('DOMContentLoaded', function() {
            const urlParams = new URLSearchParams(window.location.search);
            const idTareaParam = urlParams.get('id');
            const successMsg = <?php echo json_encode($success_msg); ?>;
            const errorMsg = <?php echo json_encode($error_msg); ?>;
            let urlNeedsCleaning = false;

            // --- Lógica Modales Confirmación (Success/Error post-acción) ---
            const actionResultModalEl = document.getElementById('actionResultModal');
            if (actionResultModalEl) {
                const actionResultModal = new bootstrap.Modal(actionResultModalEl);
                const modalTitleEl = actionResultModalEl.querySelector('.modal-title');
                const modalMessageEl = document.getElementById('actionResultModalMessage');
                const modalHeader = actionResultModalEl.querySelector('.modal-header');
                const modalIcon = modalTitleEl ? modalTitleEl.querySelector('i') : null;

                if (successMsg) {
                    if (modalTitleEl && modalIcon && modalMessageEl && modalHeader) {
                        modalTitleEl.innerHTML = '<i class="fas fa-check-circle me-2"></i> Acción Completada'; modalMessageEl.textContent = successMsg; modalHeader.className = 'modal-header bg-success text-white'; actionResultModal.show(); urlNeedsCleaning = true;
                    } else { console.error("Elementos del modal de éxito no encontrados."); }
                } else if (errorMsg) {
                     if (modalTitleEl && modalIcon && modalMessageEl && modalHeader) {
                        modalTitleEl.innerHTML = '<i class="fas fa-exclamation-triangle me-2"></i> Error'; modalMessageEl.textContent = errorMsg; modalHeader.className = 'modal-header bg-danger text-white'; actionResultModal.show(); urlNeedsCleaning = true;
                    } else { console.error("Elementos del modal de error no encontrados."); }
                }

                // Aseguramos que la limpieza de URL se ejecute después de que el modal se oculte
                if (urlNeedsCleaning) {
                    actionResultModalEl.addEventListener('hidden.bs.modal', function () {
                         const currentUrlClean = window.location.pathname + '?id=' + idTareaParam + window.location.hash; history.replaceState(null, null, currentUrlClean); console.log("URL limpiada post-modal.");
                    }, { once: true });
                }
            } else { console.warn("Modal 'actionResultModal' no encontrado.");}


            // --- LÓGICA BOTÓN INICIAR TAREA ---
            const btnIniciarTarea = document.getElementById('btnIniciarTarea'); const confirmarInicioModal = document.getElementById('confirmarInicioModal');
            if (btnIniciarTarea && confirmarInicioModal) {
                const iniciarModal = new bootstrap.Modal(confirmarInicioModal);
                btnIniciarTarea.addEventListener('click', function(e) { e.preventDefault(); console.log("[Iniciar Tarea] Clic."); iniciarModal.show(); });
                document.getElementById('btnConfirmarInicio').addEventListener('click', function() {
                    console.log("[Iniciar Tarea] Confirmado.");
                    iniciarModal.hide();
                    const idTarea = btnIniciarTarea.getAttribute('data-id-tarea');
                    console.log(`[Iniciar Tarea] Fetch ID: ${idTarea} a: <?php echo $action_url_estado; ?>`);
                    fetch('<?php echo $action_url_estado; ?>', {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/x-www-form-urlencoded', 'X-Requested-With': 'XMLHttpRequest' },
                        body: `id_tarea=${idTarea}&nuevo_estado=en_proceso`
                    })
                    .then(response => {
                        console.log("[Iniciar Tarea] Status:", response.status);
                        if (!response.ok) { return response.text().then(text => { throw new Error(`HTTP ${response.status}: ${text}`); }); }
                        return response.json();
                    })
                    .then(data => {
                        console.log("[Iniciar Tarea] Datos:", data);
                        if (data.success && data.message) {
                            const resultModalEl = document.getElementById('actionResultModal');
                            const resultModal = bootstrap.Modal.getOrCreateInstance(resultModalEl);
                            const modalTitleEl = resultModalEl.querySelector('.modal-title');
                            const modalMessageEl = document.getElementById('actionResultModalMessage');
                            const modalHeader = resultModalEl.querySelector('.modal-header');
                            if(modalTitleEl && modalMessageEl && modalHeader) {
                                modalTitleEl.innerHTML = '<i class="fas fa-check-circle me-2"></i> ¡Tarea Iniciada!';
                                modalMessageEl.textContent = data.message;
                                modalHeader.className = 'modal-header bg-success text-white';
                                resultModal.show();
                                resultModalEl.addEventListener('hidden.bs.modal', function () {
                                    window.location.reload();
                                }, { once: true });
                            }
                        } else {
                            showToast('Error', 'Error: ' + (data.error || 'Respuesta inesperada.'), 'danger');
                        }
                    })
                    .catch(error => {
                        console.error('[Iniciar Tarea] Error fetch/json:', error);
                        showToast('Error Conexión', `Error: ${error.message}.`, 'danger');
                    });
                });
            }


            // --- LÓGICA MODALES NOTIFICACIÓN (Aprobada, Modif, Cancelada) ---
            const modalTipo = urlParams.get('show_modal');
            let needsCleanFromModalNotif = false;
            if (modalTipo === 'aprobada') { const m=new bootstrap.Modal(document.getElementById('modalAprobada')); m.show(); const z=1060; var d=3*1000; var aE=Date.now()+d; var df={startVelocity:30,spread:360,ticks:60,zIndex:z+10}; function rIR(min,max){return Math.random()*(max-min)+min;} var i=setInterval(function(){var tL=aE-Date.now(); if(tL<=0){return clearInterval(i);} var pC=50*(tL/d); confetti({...df,particleCount:pC,origin:{x:rIR(0.1,0.3),y:Math.random()-0.2}}); confetti({...df,particleCount:pC,origin:{x:rIR(0.7,0.9),y:Math.random()-0.2}});}, 250); needsCleanFromModalNotif = true; }
            else if (modalTipo === 'modificacion') { const m=new bootstrap.Modal(document.getElementById('modalModificacion')); m.show(); const tE=document.getElementById('acciones-tab'); if(tE){const t=new bootstrap.Tab(tE); t.show();} needsCleanFromModalNotif = true; }
            else if (modalTipo === 'cancelada_admin') { const m=new bootstrap.Modal(document.getElementById('modalCanceladaAdmin')); m.show(); needsCleanFromModalNotif = true; }
            if (needsCleanFromModalNotif) { history.replaceState(null,null,window.location.pathname+'?id=' + idTareaParam + window.location.hash); urlNeedsCleaning = false; }

            // --- LÓGICA MODAL TAREA REASIGNADA (de tarea_ver.php base) ---
            const reasignadaParam = urlParams.get('reasignada_a');
            const newAssigneeNameParam = urlParams.get('new_assignee_name');
            const modalReasignadaEl = document.getElementById('reassignInfoModal');
            let needsCleanFromReassign = false;

            if (modalReasignadaEl && (reasignadaParam || newAssigneeNameParam)) {
                const modal = bootstrap.Modal.getOrCreateInstance(modalReasignadaEl);
                const msgEl = document.getElementById('reassignModalMessage');
                const subEl = document.getElementById('reassignModalSubtext');
                const newAssigneeInfo = document.getElementById('reassignNewAssigneeInfo');
                const newAssigneeNameEl = document.getElementById('reassignNewAssigneeName');
                const confirmBtn = document.getElementById('reassignModalConfirmButton');

                // Llenar campos
                document.getElementById('reassignTaskId').textContent = '#' + (idTareaParam || 'N/A');
                document.getElementById('reassignTaskTitle').textContent = decodeURIComponent((urlParams.get('task_title') || 'No especificado').replace(/\+/g, ' '));
                document.getElementById('reassignTaskDescription').textContent = decodeURIComponent((urlParams.get('task_desc') || '').replace(/\+/g, ' '));
                document.getElementById('reassignTaskCategory').textContent = decodeURIComponent((urlParams.get('task_cat') || 'N/A').replace(/\+/g, ' '));
                document.getElementById('reassignTaskPriority').textContent = decodeURIComponent((urlParams.get('task_prio') || 'N/A').replace(/\+/g, ' '));
                document.getElementById('reassignTaskState').textContent = decodeURIComponent((urlParams.get('task_state') || 'N/A').replace(/\+/g, ' '));

                let finalRedirect = `tarea_ver.php?id=${idTareaParam}`;

                if (reasignadaParam) {
                    msgEl.textContent = "Se te ha asignado esta tarea recientemente.";
                    subEl.textContent = "Haz clic en 'Entendido' para ver los detalles completos y gestionarla.";
                    newAssigneeInfo.style.display = 'none';
                } else if (newAssigneeNameParam) {
                    msgEl.textContent = "Esta tarea ha sido reasignada por el administrador.";
                    subEl.textContent = "Ya no está bajo tu responsabilidad. Haz clic en 'Entendido' para ver tus tareas actuales.";
                    newAssigneeNameEl.textContent = decodeURIComponent(newAssigneeNameParam.replace(/\+/g, ' '));
                    newAssigneeInfo.style.display = 'block';
                    finalRedirect = `tareas_lista.php`;
                }

                confirmBtn.onclick = function() { window.location.href = finalRedirect; };
                modal.show();
                needsCleanFromReassign = true;
                urlNeedsCleaning = false;

                // Limpiar parámetros de reasignación DESPUÉS de mostrar
                 modalReasignadaEl.addEventListener('shown.bs.modal', function () {
                    const currentUrlClean = new URL(window.location);
                    currentUrlClean.searchParams.delete('reasignada_a');
                    currentUrlClean.searchParams.delete('new_assignee_name');
                    currentUrlClean.searchParams.delete('task_title');
                    currentUrlClean.searchParams.delete('task_desc');
                    currentUrlClean.searchParams.delete('task_state');
                    currentUrlClean.searchParams.delete('task_prio');
                    currentUrlClean.searchParams.delete('task_cat');
                    history.replaceState(null, null, currentUrlClean.toString());
                    console.log("Parámetros de reasignación limpiados.");
                 }, { once: true });
            }
            // --- FIN LÓGICA MODAL TAREA REASIGNADA ---


            // --- CÓDIGO ACTIVAR PESTAÑA DESDE HASH ---
            function activateTabByTargetId(tid) { if(!tid||tid==='#')return; const ts=tid.startsWith('#')?tid:`#${tid}`; const tte=document.querySelector(`.nav-pills button[data-bs-target="${ts}"]`); if(tte){console.log(`Activando: ${ts}`); try{const t=bootstrap.Tab.getOrCreateInstance(tte); if(t){t.show();console.log(`Pestaña ${ts} activada.`);}else{console.error(`No instancia Tab.`);}} catch(e){console.error("Error activar:",e);}} else{console.warn(`No botón para: ${ts}`);}} const cH=window.location.hash; if(cH&&cH.length>1){const tpi=cH.replace('-tab',''); console.log(`Hash: ${cH}. Target: ${tpi}`); setTimeout(()=>{activateTabByTargetId(tpi);},250); } else{console.log("No hash.");}

            // --- CÓDIGO RESALTAR ACTUALIZACIÓN POR ID (Highlight Amarillo Temporal) ---
            const highlightUpdateId = urlParams.get('highlight_update');
            const isActualizacionesTabFromHash = window.location.hash === '#actualizaciones';
            let needsCleanFromHighlightUpdate = false;
            if (highlightUpdateId && !isNaN(highlightUpdateId) && isActualizacionesTabFromHash) {
                console.log(`[Highlight Amarillo] ID: ${highlightUpdateId}`);
                setTimeout(() => {
                    const updateElement = document.getElementById(`update-item-${highlightUpdateId}`);
                    if (updateElement) {
                        console.log("[Highlight Amarillo] Elemento.");
                        updateElement.classList.add('highlight-update-flash');
                        updateElement.scrollIntoView({ behavior: 'smooth', block: 'center', inline: 'nearest' });
                        setTimeout(() => {
                            updateElement.classList.remove('highlight-update-flash');
                            console.log("[Highlight Amarillo] Clase quitada.");
                            const cU = new URL(window.location);
                            cU.searchParams.delete('highlight_update');
                            history.replaceState(null, null, cU.pathname + cU.search + cU.hash);
                        }, 2000);
                        urlNeedsCleaning = false;
                    } else {
                        console.warn(`[Highlight Amarillo] No elemento ID: update-item-${highlightUpdateId}`);
                        needsCleanFromHighlightUpdate = true;
                    }
                }, 350);
            } else if (highlightUpdateId) {
                 needsCleanFromHighlightUpdate = true;
            }
            if (needsCleanFromHighlightUpdate && !needsCleanFromModalNotif && !needsCleanFromReassign) {
                const cU = new URL(window.location); cU.searchParams.delete('highlight_update'); history.replaceState(null, null, cU.pathname + cU.search + cU.hash); urlNeedsCleaning = false;
            }


            // --- CÓDIGO RESALTAR SECCIÓN ADJUNTOS FINALES ---
             const highlightFinalDocs = urlParams.has('highlight_final') && urlParams.get('highlight_final') === 'true';
             const isArchivosTabFromHash = window.location.hash === '#archivos';
             let needsCleanFromHighlightFinal = false;
             if (highlightFinalDocs && isArchivosTabFromHash) {
                 console.log("[Highlight Final] Detectado.");
                 setTimeout(() => {
                     const sE = document.getElementById('final-docs-section');
                     if (sE) {
                         console.log("[Highlight Final] Elemento.");
                         sE.classList.add('highlight-section-flash');
                         sE.scrollIntoView({ behavior: 'smooth', block: 'start' });
                         setTimeout(() => {
                             sE.classList.remove('highlight-section-flash');
                             console.log("[Highlight Final] Clase quitada.");
                             const cU = new URL(window.location);
                             cU.searchParams.delete('highlight_final');
                             history.replaceState(null, null, cU.pathname + cU.search + cU.hash);
                         }, 2000);
                         urlNeedsCleaning = false;
                     } else {
                         console.warn("[Highlight Final] No elemento #final-docs-section.");
                         needsCleanFromHighlightFinal = true;
                     }
                 }, 350);
             } else if (highlightFinalDocs) {
                  needsCleanFromHighlightFinal = true;
             }
             if (needsCleanFromHighlightFinal && !needsCleanFromModalNotif && !needsCleanFromReassign && !needsCleanFromHighlightUpdate) {
                  const cU = new URL(window.location); cU.searchParams.delete('highlight_final'); history.replaceState(null, null, cU.pathname + cU.search + cU.hash); urlNeedsCleaning = false;
             }

            // --- LÓGICA LIGHTBOX IMÁGENES ---
            const iM=document.getElementById('imageModal'); if(iM){ iM.addEventListener('show.bs.modal', function(e){ const b=e.relatedTarget; const iu=b.getAttribute('data-bs-image-url'); const mi=iM.querySelector('#modalImage'); mi.src=iu; }); }

            // --- (Fusión: JS DETALLES REMITO de tarea_ver_r.php) ---
            const remitoFileInput = document.getElementById('adjuntos_remito');
            const remitoDetailsContainer = document.getElementById('remito-details-container');
            const remitoDescripcionInput = document.getElementById('remito_descripcion');
            const remitoPrecioInput = document.getElementById('remito_precio');
            const remitoNumeroInput = document.getElementById('remito_numero');

            if (remitoFileInput && remitoDetailsContainer) {
                remitoFileInput.addEventListener('change', function() {
                    if (this.files && this.files.length > 0) {
                        remitoDetailsContainer.style.display = 'block';
                    } else {
                        remitoDetailsContainer.style.display = 'none';
                        if(remitoDescripcionInput) remitoDescripcionInput.value = '';
                        if(remitoPrecioInput) remitoPrecioInput.value = '';
                        if(remitoNumeroInput) remitoNumeroInput.value = '';
                    }
                });
            }
            // --- (Fin Fusión JS) ---


            // --- Limpieza final de URL si quedó marcada y no se manejó específicamente ---
            if (urlNeedsCleaning) {
                const cleanUrl = window.location.pathname + '?id=' + idTareaParam + window.location.hash;
                 if (window.location.search !== `?id=${idTareaParam}`) {
                    history.replaceState(null, null, cleanUrl);
                    console.log("Limpieza final de URL ejecutada.");
                 }
            }

        });
    </script>
    <?php include 'footer.php'; ?>
</body>
</html>