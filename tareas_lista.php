<?php
// Archivo: tareas_lista.php (LÓGICA PURA DE PERMISOS - SIN ROLES FIJOS)
session_start();
include 'conexion.php';
include_once 'funciones_permisos.php'; // Vital para que funcionen los permisos

if (!isset($_SESSION['usuario_id'])) { header("Location: login.php"); exit(); }
$id_usuario = $_SESSION['usuario_id'];

// --- 1. CONFIGURACIÓN DE ORDENAMIENTO ---
$allowed_sort_columns = ['id_tarea', 'titulo', 'categoria', 'prioridad', 'estado', 'fecha_limite', 'fecha_creacion', 'asignado', 'fecha_cierre'];
$sort_column = $_GET['sort'] ?? 'fecha_creacion'; 
$sort_order = $_GET['order'] ?? 'desc';
if (!in_array($sort_column, $allowed_sort_columns)) $sort_column = 'fecha_creacion'; 
if (!in_array(strtolower($sort_order), ['asc', 'desc'])) $sort_order = 'desc';
$sort_column_sql = match ($sort_column) { 'categoria' => 'c.nombre', 'asignado' => 'asig.nombre_completo', default => 't.' . $sort_column };

// --- 2. OBTENCIÓN DE PARÁMETROS ---
$filtro_anio = $_GET['anio'] ?? '';
$filtro_mes = $_GET['mes'] ?? '';
$estado_filtro = isset($_GET['estado']) ? (is_array($_GET['estado']) ? $_GET['estado'] : [$_GET['estado']]) : [];
$categoria_filtro = isset($_GET['categoria']) ? (is_array($_GET['categoria']) ? $_GET['categoria'] : [$_GET['categoria']]) : [];
$prioridad_filtro = isset($_GET['prioridad']) ? (is_array($_GET['prioridad']) ? $_GET['prioridad'] : [$_GET['prioridad']]) : [];
$asignado_filtro = isset($_GET['asignado']) ? (is_array($_GET['asignado']) ? $_GET['asignado'] : [$_GET['asignado']]) : [];

// Listas para filtros
$categorias_list = []; $usuarios_asignables = [];
try {
    $categorias_list = $pdo->query("SELECT id_categoria, nombre FROM categorias ORDER BY nombre")->fetchAll(PDO::FETCH_ASSOC);
    $sql_usuarios = "SELECT id_usuario, nombre_completo, rol FROM usuarios WHERE activo = 1 ORDER BY nombre_completo";
    $usuarios_asignables = $pdo->query($sql_usuarios)->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) { error_log("Error listas filtros: " . $e->getMessage()); }

$estados_map = [ 'asignada' => 'Asignada', 'en_proceso' => 'En Proceso', 'finalizada_tecnico' => 'P/Revisión', 'verificada' => 'Verificada', 'modificacion_requerida' => 'Modificación', 'cancelada' => 'Cancelada', 'atrasadas' => 'Atrasadas', 'en_reserva' => 'En Reserva' ];
$lista_anios = [];
try { $lista_anios = $pdo->query("SELECT DISTINCT YEAR(fecha_creacion) FROM tareas ORDER BY fecha_creacion DESC")->fetchAll(PDO::FETCH_COLUMN); } catch (Exception $e) {}
if (empty($lista_anios)) $lista_anios = [date('Y')];
$meses_map = [1=>'Ene', 2=>'Feb', 3=>'Mar', 4=>'Abr', 5=>'May', 6=>'Jun', 7=>'Jul', 8=>'Ago', 9=>'Sep', 10=>'Oct', 11=>'Nov', 12=>'Dic'];

// --- 3. CONSTRUCCIÓN DE SQL ---
$sql = "SELECT t.id_tarea, t.titulo, t.estado, t.fecha_creacion, t.fecha_limite, t.fecha_cierre, t.prioridad, c.nombre AS nombre_categoria, asig.nombre_completo AS nombre_asignado FROM tareas t LEFT JOIN categorias c ON t.id_categoria = c.id_categoria LEFT JOIN usuarios asig ON t.id_asignado = asig.id_usuario";
$params = []; 
$where_clauses = [];

// [LÓGICA CRÍTICA 1] FILTRO DE VISUALIZACIÓN
// Si NO tiene el permiso "tareas_ver_todas" (checkbox en admin), SOLO ve las suyas.
// Ya no importa si se llama "auxiliar" o "empleado".
if (!tiene_permiso('tareas_ver_todas', $pdo)) { 
    $where_clauses[] = "(t.id_asignado = :id_user OR t.id_creador = :id_user)"; 
    $params[':id_user'] = $id_usuario; 
}

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
            $placeholders[] = $ph;
        }
    }
    $sub_conds = [];
    if (!empty($placeholders)) {
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
$sql .= " ORDER BY " . $sort_column_sql . " " . strtoupper($sort_order);
if ($sort_column != 'fecha_creacion') { $sql .= ", t.fecha_creacion DESC"; }

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
        body { background-color: #f8f9fa; }
        .table th.sortable a { text-decoration: none; color: inherit; display: block; }
        .table th.sortable a:hover { color: #FFF; }
        .table-container-scrollable { display: block; width: 100%; max-height: 75vh; overflow-y: auto; border: 1px solid #dee2e6; border-radius: .375rem; background-color: #fff; margin-bottom: 1rem; }
        .table th.col-fit, .table td.col-fit { white-space: nowrap; width: auto; padding: 0.5rem; }
        .col-actions { min-width: 140px; }
        .badge { font-size: 0.8em; }
    </style>
</head>
<body>

    <?php include 'navbar.php'; ?>

    <div class="container mt-4"> 
        <div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-3">
            <h1 class="mb-0 h2 me-auto">Lista de Tareas</h1>
            <div class="d-flex align-items-center flex-wrap gap-2">
                 <div class="input-group input-group-sm" style="width: 200px;">
                    <span class="input-group-text bg-light"><i class="fas fa-search text-muted"></i></span>
                    <input type="text" id="searchInputTasks" class="form-control" placeholder="Buscar...">
                 </div>
                 
                 <div class="vr mx-1"></div>

                 <form action="tareas_lista.php" method="GET" class="d-flex align-items-center gap-1 m-0">
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

                 <button class="btn btn-sm btn-outline-secondary" type="button" data-bs-toggle="offcanvas" data-bs-target="#offcanvasFilters">
                     <i class="fas fa-filter"></i> Filtros
                 </button>
            </div>
        </div>

        <div class="table-container-scrollable shadow-sm rounded">
            <table class="table table-hover table-bordered table-striped mb-0" id="tasksTable">
                <thead class="table-dark" style="position: sticky; top: 0; z-index: 10;">
                    <tr>
                        <th class="col-fit text-center">ID</th>
                        <th>Título</th>
                        <th class="col-fit text-center">Categoría</th>
                        <th class="col-fit text-center">Prioridad</th>
                        <th class="col-fit text-center">Estado</th>
                        <th class="col-fit text-center">Finalización</th>
                        <th class="col-fit">Límite</th>
                        <th class="col-fit">Creada</th>
                        <th class="col-fit">Asignado</th>
                        <th class="col-fit text-center">Acciones</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($tareas)): ?>
                        <tr><td colspan="10" class="text-center py-4">No se encontraron tareas con los filtros actuales.</td></tr>
                    <?php else: ?>
                        <?php foreach ($tareas as $tarea): 
                            $is_atrasada = ($tarea['fecha_limite'] && strtotime($tarea['fecha_limite']) < time() && !in_array($tarea['estado'], ['verificada', 'cancelada']));
                            $row_class = $is_atrasada ? 'table-danger' : '';
                        ?>
                        <tr class="task-row <?php echo $row_class; ?>" id="task-row-<?php echo $tarea['id_tarea']; ?>">
                            <td class="col-fit text-center fw-bold">#<?php echo $tarea['id_tarea']; ?></td>
                            <td><?php echo htmlspecialchars($tarea['titulo']); ?></td>
                            <td class="col-fit text-center"><span class="badge bg-secondary"><?php echo htmlspecialchars($tarea['nombre_categoria']); ?></span></td>
                            <td class="col-fit text-center">
                                <?php $p_badge = match($tarea['prioridad']) { 'urgente'=>'danger', 'alta'=>'warning', 'media'=>'primary', 'baja'=>'success', default=>'secondary' }; ?>
                                <span class='badge bg-<?php echo $p_badge; ?>'><?php echo ucfirst($tarea['prioridad']); ?></span>
                            </td>
                            <td class="col-fit text-center">
                                <?php $e_badge = match($tarea['estado']) { 'verificada'=>'success', 'cancelada'=>'secondary', 'modificacion_requerida'=>'danger', default=>'info' }; ?>
                                <span class='badge bg-<?php echo $e_badge; ?>'><?php echo $estados_map[$tarea['estado']]; ?></span>
                                <?php if($is_atrasada) echo '<br><span class="badge bg-danger mt-1">Atrasada</span>'; ?>
                            </td>
                            <td class="col-fit text-center"><?php echo $tarea['fecha_cierre'] ? date('d/m/y H:i', strtotime($tarea['fecha_cierre'])) : '-'; ?></td>
                            <td class="col-fit"><?php echo $tarea['fecha_limite'] ? date('d/m/y', strtotime($tarea['fecha_limite'])) : '-'; ?></td>
                            <td class="col-fit"><?php echo date('d/m/y', strtotime($tarea['fecha_creacion'])); ?></td>
                            <td class="col-fit"><?php echo htmlspecialchars($tarea['nombre_asignado'] ?? '-'); ?></td>
                            <td class="col-fit text-center col-actions">
                                <?php
                                $ver_url = "tarea_ver.php?id=" . $tarea['id_tarea'];
                                $editar_url = "tarea_editar.php?id=" . $tarea['id_tarea'];

                                // [LÓGICA CRÍTICA 2] BOTONES DE ACCIÓN
                                // Aquí estaba el problema. Ahora usamos PERMISOS EXPLICITOS.
                                
                                if (tiene_permiso('tareas_gestionar', $pdo)) {
                                    // TIENE PERMISO TOTAL (Admin, Encargado, Auxiliar SI TIENE CHECK)
                                    if ($tarea['estado'] === 'finalizada_tecnico') {
                                        echo '<a href="'.$ver_url.'" class="btn btn-warning btn-sm" title="Revisar"><i class="fas fa-search"></i> Revisar</a>';
                                    } elseif (in_array($tarea['estado'], ['verificada', 'cancelada'])) {
                                        echo '<a href="'.$ver_url.'" class="btn btn-outline-secondary btn-sm" title="Ver Historial"><i class="fas fa-eye"></i> Ver</a>';
                                    } else {
                                        echo '<a href="'.$ver_url.'" class="btn btn-outline-info btn-sm me-1" title="Ver Detalles"><i class="fas fa-eye"></i></a>';
                                        echo '<a href="'.$editar_url.'" class="btn btn-primary btn-sm" title="Editar"><i class="fas fa-edit"></i></a>';
                                    }
                                } 
                                // SI NO GESTIONA PERO VE TODO (SOLO MIRAR)
                                elseif (tiene_permiso('tareas_ver_todas', $pdo)) {
                                     echo '<a href="'.$ver_url.'" class="btn btn-info btn-sm" title="Ver Tarea"><i class="fas fa-eye"></i> Ver</a>';
                                }
                                // EMPLEADO / BÁSICO (SOLO GESTIONA LO SUYO)
                                else {
                                    echo '<a href="'.$ver_url.'" class="btn btn-success btn-sm" title="Gestionar"><i class="fas fa-arrow-right"></i> Gestionar</a>';
                                }
                                ?>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
    
    <div class="offcanvas offcanvas-end shadow" tabindex="-1" id="offcanvasFilters" style="top: 110px !important; right: 20px !important; height: auto; max-height: 80vh; border-radius: 15px; overflow-y: auto;">
        <div class="offcanvas-header bg-light">
            <h5 class="offcanvas-title">Filtros Avanzados</h5>
            <button type="button" class="btn-close" data-bs-dismiss="offcanvas"></button>
        </div>
        <div class="offcanvas-body">
            <form method="GET">
                <div class="p-3">
                    <h6 class="fw-bold">Estado</h6>
                    <?php foreach ($estados_map as $k => $l): ?>
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" name="estado[]" value="<?php echo $k; ?>" <?php echo in_array($k, $estado_filtro) ? 'checked' : ''; ?>>
                            <label class="form-check-label"><?php echo $l; ?></label>
                        </div>
                    <?php endforeach; ?>
                    
                    <?php if (tiene_permiso('tareas_ver_todas', $pdo)): ?>
                        <hr>
                        <h6 class="fw-bold">Asignado a</h6>
                        <div style="max-height: 150px; overflow-y: auto;">
                            <?php foreach ($usuarios_asignables as $user): ?>
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" name="asignado[]" value="<?php echo $user['id_usuario']; ?>" <?php echo in_array($user['id_usuario'], $asignado_filtro) ? 'checked' : ''; ?>>
                                    <label class="form-check-label"><?php echo htmlspecialchars($user['nombre_completo']); ?></label>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>
                <div class="d-grid gap-2 p-3">
                    <button type="submit" class="btn btn-primary">Aplicar Filtros</button>
                    <a href="tareas_lista.php" class="btn btn-outline-danger btn-sm">Limpiar</a>
                </div>
            </form>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        document.addEventListener('DOMContentLoaded', () => {
            const rows = document.querySelectorAll('.task-row');
            rows.forEach(row => {
                row.addEventListener('click', (e) => {
                    if (!e.target.closest('a') && !e.target.closest('button') && !e.target.closest('input')) {
                        const id = row.id.replace('task-row-', '');
                        window.location.href = 'tarea_ver.php?id=' + id;
                    }
                });
            });
            const searchInput = document.getElementById('searchInputTasks');
            if(searchInput) {
                searchInput.addEventListener('input', function() {
                    const term = this.value.toLowerCase();
                    document.querySelectorAll('.task-row').forEach(row => {
                        row.style.display = row.innerText.toLowerCase().includes(term) ? '' : 'none';
                    });
                });
            }
        });
    </script>
</body>
</html>