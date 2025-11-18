<?php
// Archivo: tareas_lista.php (CON CONTAINER CENTRADO)
// *** MODIFICADO (v2) POR GEMINI PARA MOSTRAR FILTRO ASIGNADO Y CORREGIR LÓGICA DE ROLES ***
// *** MODIFICADO (v3) POR GEMINI PARA CORREGIR ESTILO DE BADGE 'en_reserva' ***
session_start();
include 'conexion.php';

// --- (Código PHP inicial: verificación de sesión, parámetros, filtros, SQL, etc. - SIN CAMBIOS) ---
if (!isset($_SESSION['usuario_id'])) { header("Location: login.php"); exit(); }
$id_usuario = $_SESSION['usuario_id']; $rol_usuario = $_SESSION['usuario_rol'];
$allowed_sort_columns = ['id_tarea', 'titulo', 'categoria', 'prioridad', 'estado', 'fecha_limite', 'fecha_creacion', 'asignado'];
$sort_column = $_GET['sort'] ?? 'fecha_creacion'; $sort_order = $_GET['order'] ?? 'desc';
if (!in_array($sort_column, $allowed_sort_columns)) $sort_column = 'fecha_creacion'; if (!in_array(strtolower($sort_order), ['asc', 'desc'])) $sort_order = 'desc';
$sort_column_sql = match ($sort_column) { 'categoria' => 'c.nombre', 'asignado' => 'asig.nombre_completo', default => 't.' . $sort_column };
$estado_filtro = $_GET['estado'] ?? 'todas'; $categoria_filtro = $_GET['categoria'] ?? 'todas'; $prioridad_filtro = $_GET['prioridad'] ?? 'todas'; $asignado_filtro = $_GET['asignado'] ?? 'todas';
$categorias_list = []; $usuarios_asignables = [];
try {
    $sql_categorias = "SELECT id_categoria, nombre FROM categorias ORDER BY nombre"; $categorias_list = $pdo->query($sql_categorias)->fetchAll(PDO::FETCH_ASSOC);
    
    // --- INICIO MODIFICACIÓN GEMINI (v2): Cargar la lista de usuarios asignables (para el filtro) ---
    // Se usa la misma lógica de tarea_crear.php para que la lista sea coherente
    $sql_usuarios = "SELECT id_usuario, nombre_completo, rol FROM usuarios 
                     WHERE rol IN ('empleado', 'auxiliar', 'encargado') 
                     AND activo = 1 
                     ORDER BY nombre_completo";
    $usuarios_asignables = $pdo->query($sql_usuarios)->fetchAll(PDO::FETCH_ASSOC);
    // --- FIN MODIFICACIÓN GEMINI (v2) ---

} catch (PDOException $e) { error_log("Error listas filtros: " . $e->getMessage()); }
$estados_map = [ 'todas' => 'Todos', 'asignada' => 'Asignada', 'en_proceso' => 'En Proceso', 'finalizada_tecnico' => 'P/Revisión', 'verificada' => 'Verificada', 'modificacion_requerida' => 'Modificación', 'cancelada' => 'Cancelada', 'atrasadas' => 'Atrasadas', 'en_reserva' => 'En Reserva' ]; // <-- Añadido 'En Reserva' al mapa
$prioridades_map = [ 'todas' => 'Todas', 'urgente' => 'Urgente', 'alta' => 'Alta', 'media' => 'Media', 'baja' => 'Baja' ];
$sql = "SELECT t.id_tarea, t.titulo, t.estado, t.fecha_creacion, t.fecha_limite, t.prioridad, c.nombre AS nombre_categoria, asig.nombre_completo AS nombre_asignado FROM tareas t LEFT JOIN categorias c ON t.id_categoria = c.id_categoria LEFT JOIN usuarios asig ON t.id_asignado = asig.id_usuario";
$params = []; $where_clauses = [];

// --- LÓGICA DE FILTRADO DE TAREAS ---
// (Esta lógica es correcta como está: Empleado ve solo lo suyo, los demás ven todo)
if ($rol_usuario === 'empleado') { $where_clauses[] = "t.id_asignado = :id_usuario"; $params[':id_usuario'] = $id_usuario; }
// (Los roles 'admin', 'encargado' y 'auxiliar' no tienen esta restricción, por lo tanto ven todo)

if ($estado_filtro !== 'todas') { if ($estado_filtro === 'atrasadas') { $where_clauses[] = "t.fecha_limite IS NOT NULL AND t.fecha_limite < CURDATE() AND t.estado NOT IN ('verificada', 'cancelada')"; } else { $where_clauses[] = "t.estado = :estado"; $params[':estado'] = $estado_filtro; } }
if ($categoria_filtro !== 'todas') { $where_clauses[] = "t.id_categoria = :id_categoria"; $params[':id_categoria'] = $categoria_filtro; }
if ($prioridad_filtro !== 'todas') { $where_clauses[] = "t.prioridad = :prioridad_filtro"; $params[':prioridad_filtro'] = $prioridad_filtro; }
if ($asignado_filtro !== 'todas') { $where_clauses[] = "t.id_asignado = :id_asignado"; $params[':id_asignado'] = $asignado_filtro; }
if (!empty($where_clauses)) { $sql .= " WHERE " . implode(' AND ', $where_clauses); }
$sql .= " ORDER BY " . $sort_column_sql . " " . strtoupper($sort_order); if ($sort_column != 'fecha_creacion') { $sql .= ", t.fecha_creacion DESC"; }
try { $stmt = $pdo->prepare($sql); $stmt->execute($params); $tareas = $stmt->fetchAll(PDO::FETCH_ASSOC); } catch (PDOException $e) { error_log("Error carga tareas: " . $e->getMessage()); $tareas = []; }
function build_url($current_params, $new_params) { $params = array_merge($current_params, $new_params); return 'tareas_lista.php?' . http_build_query($params); }
$current_url_params = ['estado' => $estado_filtro, 'categoria' => $categoria_filtro, 'prioridad' => $prioridad_filtro, 'asignado' => $asignado_filtro, 'sort' => $sort_column, 'order' => $sort_order];
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
        /* Estilos generales (sin cambios) */
        body { background-color: #f8f9fa; } .table th.sortable a { text-decoration: none; color: inherit; display: block; } .table th.sortable a:hover { color: #FFF; } .table th .sort-icon { margin-left: 5px; color: #adb5bd; } .table th .sort-icon.active { color: #FFF; } .table-hover tbody tr:hover { background-color: rgba(0, 0, 0, 0.05); cursor: pointer; } .badge { font-size: 0.8em; padding: 0.4em 0.6em; } .offcanvas-header { border-bottom: 1px solid #dee2e6; } .filter-group label { font-weight: bold; margin-bottom: 0.5rem; display: block; } .filter-group .list-group-item { padding: 0.5rem 1rem; font-size: 0.9rem; border-radius: 0.25rem; margin-bottom: 0.25rem; text-align: center; } .filter-group .list-group-item.active { font-weight: bold; } .btn-filters-active { border: 2px solid #0d6efd !important; } .offcanvas-body { overflow-y: auto; }

        /* Contenedor Scroll (sin cambios) */
        .table-container-scrollable { display: block; width: 100%; max-height: 75vh; overflow-y: auto; overflow-x: auto; border: 1px solid #dee2e6; border-radius: .375rem; background-color: #fff; margin-bottom: 1rem; -webkit-overflow-scrolling: touch; }
        /* Tabla Interna (sin cambios) */
        .table-container-scrollable .table { min-width: 950px; margin-bottom: 0; }
        /* Barra scroll (sin cambios) */
        .table-container-scrollable::-webkit-scrollbar { height: 12px; width: 10px; background-color: #e9ecef; } .table-container-scrollable::-webkit-scrollbar-track { background: #f1f1f1; border-radius: 6px; border: 1px solid #dee2e6; } .table-container-scrollable::-webkit-scrollbar-thumb { background: #6c757d; border-radius: 6px; border: 2px solid #f1f1f1; } .table-container-scrollable::-webkit-scrollbar-thumb:hover { background: #5a6268; } .table-container-scrollable { scrollbar-width: auto; scrollbar-color: #6c757d #f1f1f1; }

        /* Highlight row (sin cambios) */
        @keyframes intenseFlashRow { 0%, 100% { background-color: transparent; } 50% { background-color: rgba(255, 255, 0, 0.7); } } tr.highlight-row-flash { animation: intenseFlashRow 1.2s ease-in-out; animation-iteration-count: 2; }

        /* Ajuste de columnas (sin cambios) */
        .table th.col-fit, .table td.col-fit { white-space: nowrap; width: auto; padding-left: 0.75rem !important; padding-right: 0.75rem !important; }
        .table .text-center { text-align: center !important; }
        .table .col-actions { min-width: 130px; }
        .table .col-title { } .table .col-category { }
        .table-layout-auto { table-layout: auto !important; }
    </style>
</head>
<body>

    <?php include 'navbar.php'; ?>

    <div class="container mt-4"> <div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-3">
            <h1 class="mb-0 h2 me-auto">Lista de Tareas</h1>
            <div class="d-flex align-items-center flex-wrap gap-2">
                 <div class="input-group" style="max-width: 700px; min-width: 200px;">
                    <span class="input-group-text"><i class="fas fa-search"></i></span>
                    <input type="text" id="searchInputTasks" class="form-control" placeholder="Buscar tarea..."> &nbsp;&nbsp;&nbsp;&nbsp;
                    <?php $filtros_activos = ($estado_filtro !== 'todas' || $categoria_filtro !== 'todas' || $prioridad_filtro !== 'todas' || $asignado_filtro !== 'todas'); ?>
                 <button class="btn btn-primary <?php echo $filtros_activos ? 'btn-filters-active' : ''; ?>" type="button" data-bs-toggle="offcanvas" data-bs-target="#offcanvasFilters"> <i class="fas fa-filter me-1"></i> Filtros <?php echo $filtros_activos ? '<span class="badge bg-warning ms-1">Activos</span>' : ''; ?> </button>
                 <?php if ($filtros_activos): ?> <a href="tareas_lista.php?sort=fecha_creacion&order=desc" class="btn btn-outline-danger" title="Limpiar"><i class="fas fa-times"></i> Limpiar</a> <?php endif; ?>
                 </div>
                 
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
                        <?php render_sortable_header('Límite', 'fecha_limite', $sort_column, $sort_order, $current_url_params, 'col-fit'); ?>
                        <?php render_sortable_header('Creación', 'fecha_creacion', $sort_column, $sort_order, $current_url_params, 'col-fit'); ?>
                        <?php render_sortable_header('Asignado', 'asignado', $sort_column, $sort_order, $current_url_params, 'col-fit'); ?>
                        <th scope="col" class="col-fit col-actions">Acción</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($tareas)): ?> <tr><td colspan="9" class="text-center text-muted py-4">No se encontraron tareas con los filtros actuales.</td></tr>
                    <?php else: ?>
                        <?php foreach ($tareas as $tarea): ?>
                        <tr class="task-row" style="vertical-align: middle;" id="task-row-<?php echo $tarea['id_tarea']; ?>">
                            <td class="col-fit task-id"><?php echo htmlspecialchars($tarea['id_tarea']); ?></td>
                            <td class="col-title task-title"><?php echo htmlspecialchars($tarea['titulo']); ?></td>
                            <td class="col-category task-category"><?php echo htmlspecialchars($tarea['nombre_categoria'] ?? 'N/A'); ?></td>
                            <td class="col-fit text-center"> <?php $p_badge = match($tarea['prioridad']) { 'urgente'=>'danger', 'alta'=>'warning', 'media'=>'info', 'baja'=>'success', default=>'secondary'}; $p_text = match($tarea['prioridad']) { 'alta' => 'text-dark', default => 'text-white'}; ?> <span class="badge bg-<?php echo $p_badge; ?> <?php echo $p_text; ?>"><?php echo htmlspecialchars(ucfirst($tarea['prioridad'])); ?></span> </td>
                            <td class="col-fit text-center"> 
                                <?php 
                                // --- INICIO MODIFICACIÓN GEMINI (v3) ---
                                // Se añade 'en_reserva' => 'dark'
                                $e_badge = match($tarea['estado']) { 
                                    'asignada'=>'info', 
                                    'en_proceso'=>'warning', 
                                    'finalizada_tecnico'=>'primary', 
                                    'verificada'=>'success', 
                                    'modificacion_requerida'=>'danger', 
                                    'cancelada'=>'secondary', 
                                    'en_reserva'=>'dark', // <-- LÍNEA AÑADIDA
                                    default=>'light'
                                }; 
                                // --- FIN MODIFICACIÓN GEMINI (v3) ---
                                $e_text = match($tarea['estado']) { 'en_proceso'=>'text-dark', 'en_reserva'=>'text-white', default=>'text-white'}; 
                                $is_atrasada = ($tarea['fecha_limite'] && $tarea['fecha_limite'] < date('Y-m-d') && !in_array($tarea['estado'], ['verificada', 'cancelada'])); 
                                if ($is_atrasada) { $e_badge = 'danger'; $e_text = 'text-white'; } 
                                ?> 
                                <span class="badge bg-<?php echo $e_badge; ?> <?php echo $e_text; ?>"><?php echo htmlspecialchars($is_atrasada ? 'Atrasada' : ($estados_map[$tarea['estado']] ?? ucfirst($tarea['estado']))); ?></span> 
                            </td>
                            <td class="col-fit"><?php echo $tarea['fecha_limite'] ? date('d/m/Y', strtotime($tarea['fecha_limite'])) : '-'; ?></td>
                            <td class="col-fit"><?php echo date('d/m/y H:i', strtotime($tarea['fecha_creacion'])); ?></td>
                            <td class="col-fit task-assignee"><?php echo htmlspecialchars($tarea['nombre_asignado'] ?? '-'); ?></td>
                            
                            <td class="col-fit col-actions">
                                <?php
                                $ver_url = "tarea_ver.php?id=" . $tarea['id_tarea'];
                                $editar_url = "tarea_editar.php?id="."HAY UN ERROR ACA";
                                
                                // Roles de Mando: Admin y Encargado (pueden gestionar todo)
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
                                // Rol Auxiliar: Solo puede ver
                                elseif ($rol_usuario === 'auxiliar') {
                                    echo '<a href="'.$ver_url.'" class="btn btn-info btn-sm" title="Ver Tarea"><i class="fas fa-eye"></i> Ver</a>';
                                }
                                // Rol Empleado: Solo puede gestionar (botón principal)
                                else { // ($rol_usuario === 'empleado')
                                    echo '<a href="'.$ver_url.'" class="btn btn-success btn-sm" title="Gestionar Tarea"><i class="fas fa-arrow-right"></i> Gestionar</a>';
                                }
                                ?>
                            </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                    <tr id="noResultsRow" style="display: none;"><td colspan="9" class="text-center text-warning fw-bold py-4">No se encontraron tareas que coincidan con la búsqueda.</td></tr>
                </tbody>
            </table>
        </div>

    </div>

    <div class="offcanvas offcanvas-start" tabindex="-1" id="offcanvasFilters"> <div class="offcanvas-header"><h5 class="offcanvas-title"><i class="fas fa-filter me-2"></i> Filtros</h5><button type="button" class="btn-close" data-bs-dismiss="offcanvas"></button></div> <div class="offcanvas-body"> <form action="tareas_lista.php" method="GET"> <input type="hidden" name="sort" value="<?php echo htmlspecialchars($sort_column); ?>"> <input type="hidden" name="order" value="<?php echo htmlspecialchars($sort_order); ?>"> <div class="mb-4 filter-group"><label>Estado:</label><div class="list-group"><?php foreach ($estados_map as $v => $l): ?><a href="<?php echo build_url($current_url_params, ['estado' => $v]); ?>" class="list-group-item list-group-item-action <?php echo $estado_filtro === $v ? 'active' : ''; ?>"><?php echo htmlspecialchars($l); ?></a><?php endforeach; ?></div></div> <div class="mb-4 filter-group"><label>Categoría:</label><div class="list-group"><a href="<?php echo build_url($current_url_params, ['categoria' => 'todas']); ?>" class="list-group-item list-group-item-action <?php echo $categoria_filtro === 'todas' ? 'active' : ''; ?>">Todas</a><?php foreach ($categorias_list as $cat): ?><a href="<?php echo build_url($current_url_params, ['categoria' => $cat['id_categoria']]); ?>" class="list-group-item list-group-item-action <?php echo $categoria_filtro == $cat['id_categoria'] ? 'active' : ''; ?>"><?php echo htmlspecialchars($cat['nombre']); ?></a><?php endforeach; ?></div></div> <div class="mb-4 filter-group"><label>Prioridad:</label><div class="list-group"><?php foreach ($prioridades_map as $v => $l): ?><a href="<?php echo build_url($current_url_params, ['prioridad' => $v]); ?>" class="list-group-item list-group-item-action <?php echo $prioridad_filtro === $v ? 'active' : ''; ?>"><?php echo htmlspecialchars($l); ?></a><?php endforeach; ?></div></div> 
            
            <?php if (in_array($rol_usuario, ['admin', 'encargado', 'auxiliar'])): ?>
                <div class="mb-4 filter-group"><label>Asignado:</label><div class="list-group" style="max-height: 250px; overflow-y: auto;">
                    <a href="<?php echo build_url($current_url_params, ['asignado' => 'todas']); ?>" class="list-group-item list-group-item-action <?php echo $asignado_filtro === 'todas' ? 'active' : ''; ?>">Todos</a>
                    <?php foreach ($usuarios_asignables as $user): ?>
                        <a href="<?php echo build_url($current_url_params, ['asignado' => $user['id_usuario']]); ?>" class="list-group-item list-group-item-action <?php echo $asignado_filtro == $user['id_usuario'] ? 'active' : ''; ?>">
                            <?php echo htmlspecialchars($user['nombre_completo']); ?> (<?php echo htmlspecialchars(ucfirst($user['rol'])); ?>)
                        </a>
                    <?php endforeach; ?>
                </div></div>
            <?php endif; ?>
            <div class="d-grid mt-4"><a href="tareas_lista.php?sort=fecha_creacion&order=desc" class="btn btn-danger"><i class="fas fa-times me-1"></i> Limpiar Filtros</a></div> </form> </div> </div>

    <div class="toast-container position-fixed bottom-0 end-0 p-3" id="notificationToastContainer" style="z-index: 1080;"></div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // --- Highlight Row (sin cambios) ---
            const urlParams = new URLSearchParams(window.location.search); const highlightTaskId = <?php echo json_encode($highlight_task_id); ?>; if (highlightTaskId) { const row = document.getElementById(`task-row-${highlightTaskId}`); if(row) { row.classList.add('highlight-row-flash'); row.scrollIntoView({behavior:'smooth', block:'center'}); setTimeout(()=>{row.classList.remove('highlight-row-flash'); const curl=new URL(window.location); curl.searchParams.delete('highlight_task'); history.replaceState(null,null,curl.pathname+curl.search+curl.hash);}, 2500); } }

            // --- Row Click (sin cambios) ---
            const tableRowsClickable = document.querySelectorAll('tbody tr.task-row'); tableRowsClickable.forEach(row => { row.addEventListener('click', function(event) { if (event.target.closest('a, button')) return; const taskId = this.id.replace('task-row-', ''); if (taskId) window.location.href = `tarea_ver.php?id=${taskId}`; }); });

            // --- SCRIPT BUSCADOR (sin cambios) ---
            const searchInput = document.getElementById('searchInputTasks'); const tasksTable = document.getElementById('tasksTable'); const tableBody = tasksTable ? tasksTable.querySelector('tbody') : null; const taskRows = tableBody ? tableBody.querySelectorAll('tr.task-row') : []; const noResultsRow = document.getElementById('noResultsRow'); if (searchInput && tableBody && taskRows.length > 0 && noResultsRow) { searchInput.addEventListener('input', function() { const searchTerm = this.value.toLowerCase().trim(); let visibleRowCount = 0; taskRows.forEach(row => { const taskId = row.querySelector('.task-id')?.textContent.toLowerCase() || ''; const taskTitle = row.querySelector('.task-title')?.textContent.toLowerCase() || ''; const taskCategory = row.querySelector('.task-category')?.textContent.toLowerCase() || ''; const taskAssignee = row.querySelector('.task-assignee')?.textContent.toLowerCase() || ''; if (taskId.includes(searchTerm) || taskTitle.includes(searchTerm) || taskCategory.includes(searchTerm) || taskAssignee.includes(searchTerm)) { row.style.display = ''; visibleRowCount++; } else { row.style.display = 'none'; } }); if (visibleRowCount === 0) { noResultsRow.style.display = ''; } else { noResultsRow.style.display = 'none'; } }); } else { console.warn("Buscador o tabla no configurados correctamente."); }

        });
    </script>
</body>
</html>