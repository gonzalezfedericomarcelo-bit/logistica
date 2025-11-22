<?php
// Archivo: tareas_lista.php (VERSIÓN FINAL CORREGIDA - ALINEACIÓN Y EDITAR OK)
session_start();
include 'conexion.php';

if (!isset($_SESSION['usuario_id'])) { header("Location: login.php"); exit(); }
$id_usuario = $_SESSION['usuario_id']; $rol_usuario = $_SESSION['usuario_rol'];

// --- 1. CONFIGURACIÓN DE ORDENAMIENTO ---
// Agregamos 'fecha_cierre' para permitir ordenar por finalización
$allowed_sort_columns = ['id_tarea', 'titulo', 'categoria', 'prioridad', 'estado', 'fecha_limite', 'fecha_creacion', 'asignado', 'fecha_cierre'];
$sort_column = $_GET['sort'] ?? 'fecha_creacion'; $sort_order = $_GET['order'] ?? 'desc';
if (!in_array($sort_column, $allowed_sort_columns)) $sort_column = 'fecha_creacion'; 
if (!in_array(strtolower($sort_order), ['asc', 'desc'])) $sort_order = 'desc';
$sort_column_sql = match ($sort_column) { 'categoria' => 'c.nombre', 'asignado' => 'asig.nombre_completo', default => 't.' . $sort_column };

// --- 2. OBTENCIÓN DE PARÁMETROS (AHORA SOPORTAN ARRAYS) ---
$filtro_anio = $_GET['anio'] ?? '';
$filtro_mes = $_GET['mes'] ?? '';

// Arrays para checkboxes (Multi-select)
$estado_filtro = isset($_GET['estado']) ? (is_array($_GET['estado']) ? $_GET['estado'] : [$_GET['estado']]) : [];
$categoria_filtro = isset($_GET['categoria']) ? (is_array($_GET['categoria']) ? $_GET['categoria'] : [$_GET['categoria']]) : [];
$prioridad_filtro = isset($_GET['prioridad']) ? (is_array($_GET['prioridad']) ? $_GET['prioridad'] : [$_GET['prioridad']]) : [];
$asignado_filtro = isset($_GET['asignado']) ? (is_array($_GET['asignado']) ? $_GET['asignado'] : [$_GET['asignado']]) : [];

// Listas para los menús
$categorias_list = []; $usuarios_asignables = [];
try {
    $categorias_list = $pdo->query("SELECT id_categoria, nombre FROM categorias ORDER BY nombre")->fetchAll(PDO::FETCH_ASSOC);
    // MODIFICACIÓN: Mostrar TODOS los usuarios activos sin importar el rol
    $sql_usuarios = "SELECT id_usuario, nombre_completo, rol FROM usuarios WHERE activo = 1 ORDER BY nombre_completo";
    $usuarios_asignables = $pdo->query($sql_usuarios)->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) { error_log("Error listas filtros: " . $e->getMessage()); }

// Mapas de etiquetas
$estados_map = [ 'asignada' => 'Asignada', 'en_proceso' => 'En Proceso', 'finalizada_tecnico' => 'P/Revisión', 'verificada' => 'Verificada', 'modificacion_requerida' => 'Modificación', 'cancelada' => 'Cancelada', 'atrasadas' => 'Atrasadas', 'en_reserva' => 'En Reserva' ];
$prioridades_map = [ 'urgente' => 'Urgente', 'alta' => 'Alta', 'media' => 'Media', 'baja' => 'Baja' ];

// Obtener años disponibles
$lista_anios = [];
try { $lista_anios = $pdo->query("SELECT DISTINCT YEAR(fecha_creacion) FROM tareas ORDER BY fecha_creacion DESC")->fetchAll(PDO::FETCH_COLUMN); } catch (Exception $e) {}
if (empty($lista_anios)) $lista_anios = [date('Y')];
$meses_map = [1=>'Ene', 2=>'Feb', 3=>'Mar', 4=>'Abr', 5=>'May', 6=>'Jun', 7=>'Jul', 8=>'Ago', 9=>'Sep', 10=>'Oct', 11=>'Nov', 12=>'Dic'];

// --- 3. CONSTRUCCIÓN DE SQL ---
// Se agrega t.fecha_cierre al SELECT
$sql = "SELECT t.id_tarea, t.titulo, t.estado, t.fecha_creacion, t.fecha_limite, t.fecha_cierre, t.prioridad, c.nombre AS nombre_categoria, asig.nombre_completo AS nombre_asignado FROM tareas t LEFT JOIN categorias c ON t.id_categoria = c.id_categoria LEFT JOIN usuarios asig ON t.id_asignado = asig.id_usuario";
$params = []; $where_clauses = [];

// Filtro Rol (Empleado ve solo lo suyo)
if ($rol_usuario === 'empleado') { $where_clauses[] = "t.id_asignado = :id_usuario_session"; $params[':id_usuario_session'] = $id_usuario; }

// Filtros Fecha
if ($filtro_anio) { $where_clauses[] = "YEAR(t.fecha_creacion) = :anio"; $params[':anio'] = $filtro_anio; }
if ($filtro_mes) { $where_clauses[] = "MONTH(t.fecha_creacion) = :mes"; $params[':mes'] = $filtro_mes; }

// Filtros Multi-Select
if (!empty($estado_filtro) && !in_array('todas', $estado_filtro)) {
    $placeholders = [];
    $has_atrasadas = false;
    foreach ($estado_filtro as $idx => $est) {
        if ($est === 'atrasadas') { $has_atrasadas = true; } 
        else {
            $ph = ":estado_" . $idx;
            $params[$ph] = $est;
            $placeholders[$idx] = $ph; // Guardamos la referencia del placeholder
        }
    }
    $sub_conds = [];
    if (!empty($placeholders)) {
        // Usamos los placeholders generados
        $in_sql = implode(',', $placeholders); 
        $sub_conds[] = "t.estado IN ($in_sql)";
    }
    if ($has_atrasadas) {
        $sub_conds[] = "(t.fecha_limite IS NOT NULL AND t.fecha_limite < CURDATE() AND t.estado NOT IN ('verificada', 'cancelada'))";
    }
    if (!empty($sub_conds)) {
        $where_clauses[] = "(" . implode(' OR ', $sub_conds) . ")";
    }
}

if (!empty($categoria_filtro) && !in_array('todas', $categoria_filtro)) {
    $in_params = []; foreach ($categoria_filtro as $k => $v) { $key = ":cat_$k"; $in_params[] = $key; $params[$key] = $v; }
    $where_clauses[] = "t.id_categoria IN (" . implode(',', $in_params) . ")";
}

if (!empty($prioridad_filtro) && !in_array('todas', $prioridad_filtro)) {
    $in_params = []; foreach ($prioridad_filtro as $k => $v) { $key = ":prio_$k"; $in_params[] = $key; $params[$key] = $v; }
    $where_clauses[] = "t.prioridad IN (" . implode(',', $in_params) . ")";
}

if (!empty($asignado_filtro) && !in_array('todas', $asignado_filtro)) {
    $in_params = []; foreach ($asignado_filtro as $k => $v) { $key = ":asig_$k"; $in_params[] = $key; $params[$key] = $v; }
    $where_clauses[] = "t.id_asignado IN (" . implode(',', $in_params) . ")";
}

if (!empty($where_clauses)) { $sql .= " WHERE " . implode(' AND ', $where_clauses); }
$sql .= " ORDER BY " . $sort_column_sql . " " . strtoupper($sort_order); if ($sort_column != 'fecha_creacion') { $sql .= ", t.fecha_creacion DESC"; }

try { $stmt = $pdo->prepare($sql); $stmt->execute($params); $tareas = $stmt->fetchAll(PDO::FETCH_ASSOC); } catch (PDOException $e) { error_log("Error carga tareas: " . $e->getMessage()); $tareas = []; }

function build_url($current_params, $new_params) { 
    $base = $_GET; 
    return 'tareas_lista.php?' . http_build_query(array_merge($base, $new_params)); 
}
$highlight_task_id = $_GET['highlight_task'] ?? null;
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Lista de Tareas</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        body { background-color: #f8f9fa; } .table th.sortable a { text-decoration: none; color: inherit; display: block; } .table th.sortable a:hover { color: #FFF; } .table th .sort-icon { margin-left: 5px; color: #adb5bd; } .table th .sort-icon.active { color: #FFF; } .table-hover tbody tr:hover { background-color: rgba(0, 0, 0, 0.05); cursor: pointer; } .badge { font-size: 0.8em; padding: 0.4em 0.6em; } .offcanvas-header { border-bottom: 1px solid #dee2e6; } .table-container-scrollable { display: block; width: 100%; max-height: 75vh; overflow-y: auto; overflow-x: auto; border: 1px solid #dee2e6; border-radius: .375rem; background-color: #fff; margin-bottom: 1rem; -webkit-overflow-scrolling: touch; } .table-container-scrollable .table { min-width: 950px; margin-bottom: 0; } .table-container-scrollable::-webkit-scrollbar { height: 12px; width: 10px; background-color: #e9ecef; } .table-container-scrollable::-webkit-scrollbar-track { background: #f1f1f1; border-radius: 6px; border: 1px solid #dee2e6; } .table-container-scrollable::-webkit-scrollbar-thumb { background: #6c757d; border-radius: 6px; border: 2px solid #f1f1f1; } .table-container-scrollable::-webkit-scrollbar-thumb:hover { background: #5a6268; } .table-container-scrollable { scrollbar-width: auto; scrollbar-color: #6c757d #f1f1f1; }
        @keyframes intenseFlashRow { 0%, 100% { background-color: transparent; } 50% { background-color: rgba(255, 255, 0, 0.7); } } tr.highlight-row-flash { animation: intenseFlashRow 1.2s ease-in-out; animation-iteration-count: 2; }
        .table th.col-fit, .table td.col-fit { white-space: nowrap; width: auto; padding-left: 0.75rem !important; padding-right: 0.75rem !important; }
        .table .text-center { text-align: center !important; }
        .table .col-actions { min-width: 130px; }
        .table-layout-auto { table-layout: auto !important; }
    </style>
</head>
<body>

    <?php include 'navbar.php'; ?>

    <div class="container mt-4"> 
        <div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-3">
            <h1 class="mb-0 h2 me-auto">Lista de Tareas</h1>
            
            <div class="d-flex align-items-center flex-wrap gap-2 bg-white p-2 rounded shadow-sm border">
                 
                 <div class="input-group input-group-sm" style="width: 200px;">
                    <span class="input-group-text bg-light"><i class="fas fa-search text-muted"></i></span>
                    <input type="text" id="searchInputTasks" class="form-control" placeholder="Buscar...">
                 </div>

                 <div class="vr mx-1"></div>

                 <form action="tareas_lista.php" method="GET" class="d-flex align-items-center gap-1 m-0">
                     <?php // Preservar filtros ocultos 
                     foreach(['estado', 'categoria', 'prioridad', 'asignado'] as $fkey) {
                         if (isset($_GET[$fkey]) && is_array($_GET[$fkey])) {
                             foreach($_GET[$fkey] as $val) echo "<input type='hidden' name='{$fkey}[]' value='$val'>";
                         }
                     }
                     ?>
                     <select name="anio" class="form-select form-select-sm border-0 bg-light fw-bold" style="width: auto; cursor:pointer;" onchange="this.form.submit()">
                         <option value="">Año</option>
                         <?php foreach($lista_anios as $a): ?><option value="<?php echo $a; ?>" <?php echo $filtro_anio == $a ? 'selected' : ''; ?>><?php echo $a; ?></option><?php endforeach; ?>
                     </select>
                     <select name="mes" class="form-select form-select-sm border-0 bg-light fw-bold" style="width: auto; cursor:pointer;" onchange="this.form.submit()">
                         <option value="">Mes</option>
                         <?php foreach($meses_map as $m_num=>$m_nom): ?><option value="<?php echo $m_num; ?>" <?php echo $filtro_mes == $m_num ? 'selected' : ''; ?>><?php echo $m_nom; ?></option><?php endforeach; ?>
                     </select>
                 </form>

                 <div class="vr mx-1"></div>

                 <?php $filtros_activos = (!empty($estado_filtro) || !empty($categoria_filtro) || !empty($prioridad_filtro) || !empty($asignado_filtro)); ?>
                 <button class="btn btn-sm <?php echo $filtros_activos ? 'btn-primary' : 'btn-outline-secondary'; ?>" type="button" data-bs-toggle="offcanvas" data-bs-target="#offcanvasFilters">
                     <i class="fas fa-filter"></i> <?php echo $filtros_activos ? 'Filtros Activos' : 'Más Filtros'; ?>
                 </button>
                 
                 <?php if ($filtros_activos || $filtro_anio || $filtro_mes): ?>
                    <a href="tareas_lista.php" class="btn btn-sm btn-danger ms-1" title="Limpiar Todo"><i class="fas fa-times"></i></a>
                 <?php endif; ?>
            </div>
        </div>

        <div class="table-container-scrollable shadow-sm rounded">
            <table class="table table-hover table-bordered table-striped mb-0 table-layout-auto" id="tasksTable">
                <thead class="table-dark" style="position: sticky; top: 0; z-index: 10;">
                    <tr>
                        <th scope="col" class="col-fit">ID</th>
                        <?php function render_sortable_header($label, $col_name, $cur_sort, $cur_order, $cur_params, $extra_class = '') { $is_cur = ($cur_sort === $col_name); $next_order = ($is_cur && $cur_order === 'asc') ? 'desc' : 'asc'; $url = build_url($cur_params, ['sort' => $col_name, 'order' => $next_order]); $icon = 'fas fa-sort sort-icon'; if ($is_cur) $icon = ($cur_order === 'asc') ? 'fas fa-sort-up sort-icon active' : 'fas fa-sort-down sort-icon active'; echo "<th scope='col' class='sortable {$extra_class}'><a href='{$url}'>{$label} <i class='{$icon}'></i></a></th>"; } ?>
                        <?php render_sortable_header('Título', 'titulo', $sort_column, $sort_order, $current_url_params, 'col-title'); ?>
                        <?php render_sortable_header('Categoría', 'categoria', $sort_column, $sort_order, $current_url_params, 'col-category'); ?>
                        <?php render_sortable_header('Prioridad', 'prioridad', $sort_column, $sort_order, $current_url_params, 'col-fit text-center'); ?>
                        <?php render_sortable_header('Estado', 'estado', $sort_column, $sort_order, $current_url_params, 'col-fit text-center'); ?>
                        <?php render_sortable_header('Finalización', 'fecha_cierre', $sort_column, $sort_order, $current_url_params, 'col-fit'); ?>
                        <?php render_sortable_header('Límite', 'fecha_limite', $sort_column, $sort_order, $current_url_params, 'col-fit'); ?>
                        <?php render_sortable_header('Creación', 'fecha_creacion', $sort_column, $sort_order, $current_url_params, 'col-fit'); ?>
                        <?php render_sortable_header('Asignado', 'asignado', $sort_column, $sort_order, $current_url_params, 'col-fit'); ?>
                        <th scope="col" class="col-fit col-actions">Acción</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($tareas)): ?> <tr><td colspan="10" class="text-center text-muted py-4">No se encontraron tareas con los filtros actuales.</td></tr>
                    <?php else: ?>
                        <?php foreach ($tareas as $tarea): ?>
                        <?php
                            // Lógica Atrasada
                            $is_atrasada = ($tarea['fecha_limite'] && $tarea['fecha_limite'] < date('Y-m-d') && !in_array($tarea['estado'], ['verificada', 'cancelada']));
                            $row_class = $is_atrasada ? 'table-danger' : ''; 
                        ?>
                        <tr class="task-row <?php echo $row_class; ?>" style="vertical-align: middle;" id="task-row-<?php echo $tarea['id_tarea']; ?>">
                            <td class="col-fit task-id"><?php echo htmlspecialchars($tarea['id_tarea']); ?></td>
                            <td class="col-title task-title"><?php echo htmlspecialchars($tarea['titulo']); ?></td>
                            <td class="col-category task-category"><?php echo htmlspecialchars($tarea['nombre_categoria'] ?? 'N/A'); ?></td>
                            <td class="col-fit text-center"> <?php $p_badge = match($tarea['prioridad']) { 'urgente'=>'danger', 'alta'=>'warning', 'media'=>'info', 'baja'=>'success', default=>'secondary'}; $p_text = match($tarea['prioridad']) { 'alta' => 'text-dark', default => 'text-white'}; ?> <span class="badge bg-<?php echo $p_badge; ?> <?php echo $p_text; ?>"><?php echo htmlspecialchars(ucfirst($tarea['prioridad'])); ?></span> </td>
                            <td class="col-fit text-center"> 
                                <?php 
                                $e_badge = match($tarea['estado']) { 
                                    'asignada'=>'info', 'en_proceso'=>'warning', 'finalizada_tecnico'=>'primary', 'verificada'=>'success', 'modificacion_requerida'=>'danger', 'cancelada'=>'secondary', 'en_reserva'=>'dark', default=>'light'
                                }; 
                                $e_text = match($tarea['estado']) { 'en_proceso'=>'text-dark', 'en_reserva'=>'text-white', default=>'text-white'}; 
                                ?> 
                                <span class="badge bg-<?php echo $e_badge; ?> <?php echo $e_text; ?>"><?php echo htmlspecialchars($estados_map[$tarea['estado']] ?? ucfirst($tarea['estado'])); ?></span>
                                <?php if ($is_atrasada): ?><br><span class="badge bg-danger text-white mt-1">Atrasada</span><?php endif; ?>
                            </td>
                            <td class="col-fit text-center fw-bold text-success">
                                <?php echo ($tarea['estado'] === 'verificada' && $tarea['fecha_cierre']) ? date('d/m/y H:i', strtotime($tarea['fecha_cierre'])) : '-'; ?>
                            </td>
                            <td class="col-fit"><?php echo $tarea['fecha_limite'] ? date('d/m/Y', strtotime($tarea['fecha_limite'])) : '-'; ?></td>
                            <td class="col-fit"><?php echo date('d/m/y H:i', strtotime($tarea['fecha_creacion'])); ?></td>
                            <td class="col-fit task-assignee"><?php echo htmlspecialchars($tarea['nombre_asignado'] ?? '-'); ?></td>
                            
                            <td class="col-fit col-actions">
                                <?php
                                $ver_url = "tarea_ver.php?id=" . $tarea['id_tarea'];
                                $editar_url = "tarea_editar.php?id=" . $tarea['id_tarea']; // <-- CORREGIDO LINK DE EDITAR
                                
                                if (in_array($rol_usuario, ['admin', 'encargado'])) {
                                    if ($tarea['estado'] === 'finalizada_tecnico') {
                                        echo '<a href="'.$ver_url.'" class="btn btn-warning btn-sm" title="Revisar"><i class="fas fa-search"></i> Revisar</a>';
                                    } elseif (in_array($tarea['estado'], ['verificada', 'cancelada'])) {
                                        echo '<a href="'.$ver_url.'" class="btn btn-outline-secondary btn-sm" title="Ver Historial"><i class="fas fa-eye"></i> Ver</a>';
                                    } else {
                                        echo '<a href="'.$ver_url.'" class="btn btn-outline-info btn-sm me-1" title="Ver Detalles"><i class="fas fa-eye"></i></a>';
                                        echo '<a href="'.$editar_url.'" class="btn btn-primary btn-sm" title="Editar Tarea"><i class="fas fa-edit"></i></a>';
                                    }
                                } 
                                elseif ($rol_usuario === 'auxiliar') {
                                    echo '<a href="'.$ver_url.'" class="btn btn-info btn-sm" title="Ver Tarea"><i class="fas fa-eye"></i> Ver</a>';
                                }
                                else { // Empleado
                                    echo '<a href="'.$ver_url.'" class="btn btn-success btn-sm" title="Gestionar Tarea"><i class="fas fa-arrow-right"></i> Gestionar</a>';
                                }
                                ?>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                    <tr id="noResultsRow" style="display: none;"><td colspan="10" class="text-center text-warning fw-bold py-4">No se encontraron tareas que coincidan con la búsqueda.</td></tr>
                </tbody>
            </table>
        </div>
    </div>

    <div class="offcanvas offcanvas-start" tabindex="-1" id="offcanvasFilters"> 
        <div class="offcanvas-header bg-light">
            <h5 class="offcanvas-title"><i class="fas fa-sliders-h me-2"></i> Filtros Avanzados</h5>
            <button type="button" class="btn-close" data-bs-dismiss="offcanvas"></button>
        </div> 
        <div class="offcanvas-body p-0"> 
            <form action="tareas_lista.php" method="GET" id="advancedFiltersForm" class="d-flex flex-column h-100">
                <input type="hidden" name="anio" value="<?php echo htmlspecialchars($filtro_anio); ?>">
                <input type="hidden" name="mes" value="<?php echo htmlspecialchars($filtro_mes); ?>">
                <input type="hidden" name="sort" value="<?php echo htmlspecialchars($sort_column); ?>">
                <input type="hidden" name="order" value="<?php echo htmlspecialchars($sort_order); ?>">

                <div class="p-3 flex-grow-1 overflow-auto">
                    <div class="mb-4">
                        <h6 class="fw-bold text-secondary text-uppercase small mb-2">Estado</h6>
                        <div class="d-grid gap-2">
                            <?php foreach ($estados_map as $k => $l): $checked = in_array($k, $estado_filtro) ? 'checked' : ''; ?>
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" name="estado[]" value="<?php echo $k; ?>" id="est_<?php echo $k; ?>" <?php echo $checked; ?>>
                                <label class="form-check-label" for="est_<?php echo $k; ?>"><?php echo htmlspecialchars($l); ?></label>
                            </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                    <hr>
                    <div class="mb-4">
                        <h6 class="fw-bold text-secondary text-uppercase small mb-2">Categoría</h6>
                        <div class="d-grid gap-2" style="max-height: 200px; overflow-y: auto;">
                            <?php foreach ($categorias_list as $cat): $checked = in_array($cat['id_categoria'], $categoria_filtro) ? 'checked' : ''; ?>
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" name="categoria[]" value="<?php echo $cat['id_categoria']; ?>" id="cat_<?php echo $cat['id_categoria']; ?>" <?php echo $checked; ?>>
                                <label class="form-check-label" for="cat_<?php echo $cat['id_categoria']; ?>"><?php echo htmlspecialchars($cat['nombre']); ?></label>
                            </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                    <hr>
                    <div class="mb-4">
                        <h6 class="fw-bold text-secondary text-uppercase small mb-2">Prioridad</h6>
                        <div class="d-grid gap-2">
                            <?php foreach ($prioridades_map as $k => $l): $checked = in_array($k, $prioridad_filtro) ? 'checked' : ''; ?>
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" name="prioridad[]" value="<?php echo $k; ?>" id="prio_<?php echo $k; ?>" <?php echo $checked; ?>>
                                <label class="form-check-label" for="prio_<?php echo $k; ?>"><?php echo htmlspecialchars($l); ?></label>
                            </div>
                            <?php endforeach; ?>
                        </div>
                    </div>

                    <?php if (in_array($rol_usuario, ['admin', 'encargado', 'auxiliar'])): ?>
                    <hr>
                    <div class="mb-4">
                        <h6 class="fw-bold text-secondary text-uppercase small mb-2">Asignado a</h6>
                        <div class="d-grid gap-2" style="max-height: 200px; overflow-y: auto;">
                            <?php foreach ($usuarios_asignables as $user): $checked = in_array($user['id_usuario'], $asignado_filtro) ? 'checked' : ''; ?>
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" name="asignado[]" value="<?php echo $user['id_usuario']; ?>" id="asig_<?php echo $user['id_usuario']; ?>" <?php echo $checked; ?>>
                                <label class="form-check-label" for="asig_<?php echo $user['id_usuario']; ?>">
                                    <?php echo htmlspecialchars($user['nombre_completo']); ?> (<?php echo ucfirst($user['rol']); ?>)
                                </label>
                            </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                    <?php endif; ?>
                </div>

                <div class="p-3 bg-light border-top d-grid gap-2">
                    <button type="submit" class="btn btn-primary fw-bold"><i class="fas fa-check me-2"></i> Aplicar Filtros</button>
                    <a href="tareas_lista.php" class="btn btn-outline-danger btn-sm">Limpiar Todo</a>
                </div>
            </form>
        </div> 
    </div>

    <div class="toast-container position-fixed bottom-0 end-0 p-3" id="notificationToastContainer" style="z-index: 1080;"></div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // Highlight Task
            const urlParams = new URLSearchParams(window.location.search); const highlightTaskId = <?php echo json_encode($highlight_task_id); ?>; 
            if (highlightTaskId) { const row = document.getElementById(`task-row-${highlightTaskId}`); if(row) { row.classList.add('highlight-row-flash'); row.scrollIntoView({behavior:'smooth', block:'center'}); setTimeout(()=>{row.classList.remove('highlight-row-flash'); const curl=new URL(window.location); curl.searchParams.delete('highlight_task'); history.replaceState(null,null,curl.pathname+curl.search+curl.hash);}, 2500); } }

            // Row Click
            const tableRowsClickable = document.querySelectorAll('tbody tr.task-row'); tableRowsClickable.forEach(row => { row.addEventListener('click', function(event) { if (event.target.closest('a, button, input')) return; const taskId = this.id.replace('task-row-', ''); if (taskId) window.location.href = `tarea_ver.php?id=${taskId}`; }); });

            // Buscador JS
            const searchInput = document.getElementById('searchInputTasks'); const tasksTable = document.getElementById('tasksTable'); const tableBody = tasksTable ? tasksTable.querySelector('tbody') : null; const taskRows = tableBody ? tableBody.querySelectorAll('tr.task-row') : []; const noResultsRow = document.getElementById('noResultsRow'); 
            if (searchInput && tableBody && taskRows.length > 0 && noResultsRow) { searchInput.addEventListener('input', function() { const searchTerm = this.value.toLowerCase().trim(); let visibleRowCount = 0; taskRows.forEach(row => { 
                const textContent = row.innerText.toLowerCase();
                if (textContent.includes(searchTerm)) { row.style.display = ''; visibleRowCount++; } else { row.style.display = 'none'; } }); if (visibleRowCount === 0) { noResultsRow.style.display = ''; } else { noResultsRow.style.display = 'none'; } }); 
            }
        });
    </script>
    <?php include 'footer.php'; ?>
</body>
</html>