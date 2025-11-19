<?php
// Archivo: tareas_stats_empleados.php (CORREGIDO: Incluye ID de empleado en JSON)

session_start();
include 'conexion.php';

header('Content-Type: application/json');

// 1. Verificar autenticación y obtener datos de usuario
if (!isset($_SESSION['usuario_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Usuario no autenticado.']);
    exit();
}

$id_usuario_actual = $_SESSION['usuario_id'];
$rol_usuario = $_SESSION['usuario_rol'] ?? 'empleado';

// --- MÉTRICA SOLICITADA ---
$metric = $_GET['metric'] ?? 'completed';
$status_filter_clause = ($metric === 'workload') ? "AND t.estado <> 'cancelada'" : "AND t.estado = 'verificada'";

// --- Lógica de filtrado de usuario ---
$user_filter_clause = "";
$bind_params = [];
$employee_id_filter = $_GET['employee_id'] ?? null;
if (!empty($employee_id_filter) && is_numeric($employee_id_filter) && $employee_id_filter > 0) {
    $user_filter_clause = "AND t.id_asignado = :employee_id_selected";
    $bind_params[':employee_id_selected'] = (int)$employee_id_filter;
}

// --- LÓGICA DEL FILTRO DE FECHAS ---
$time_filter_clause = "";
$start_date = $_GET['start_date'] ?? null;
$end_date = $_GET['end_date'] ?? null;
if (!empty($start_date) && !empty($end_date) &&
    preg_match("/^[0-9]{4}-(0[1-9]|1[0-2])-(0[1-9]|[1-2][0-9]|3[0-1])$/", $start_date) &&
    preg_match("/^[0-9]{4}-(0[1-9]|1[0-2])-(0[1-9]|[1-2][0-9]|3[0-1])$/", $end_date))
{
    $date_column_to_filter = ($metric === 'workload') ? 't.fecha_creacion' : 't.fecha_cierre';
    $time_filter_clause = "AND DATE({$date_column_to_filter}) BETWEEN :start_date AND :end_date";
    $bind_params[':start_date'] = $start_date;
    $bind_params[':end_date'] = $end_date;
} else {
    $quick_filter = $_GET['quick_filter'] ?? null;
    if ($quick_filter === 'today') {
        $date_column_to_filter = ($metric === 'workload') ? 't.fecha_creacion' : 't.fecha_cierre';
        $time_filter_clause = "AND DATE({$date_column_to_filter}) = CURDATE()";
    }
}


try {
    // *** ASEGÚRATE QUE u.id_usuario ESTÉ EN EL SELECT ***
    $column_alias = ($metric === 'workload') ? 'assigned_count' : 'completed_count';
    $sql = "
        SELECT
            u.id_usuario, -- <-- ¡ESTO DEBE ESTAR!
            u.nombre_completo AS employee_name,
            COUNT(t.id_tarea) AS {$column_alias}
        FROM tareas t
        JOIN usuarios u ON t.id_asignado = u.id_usuario
        WHERE
            u.rol = 'empleado'
            {$status_filter_clause}
            {$user_filter_clause}
            {$time_filter_clause}
        GROUP BY
            u.id_usuario, u.nombre_completo -- <-- ¡ESTO DEBE ESTAR!
        HAVING {$column_alias} > 0
        ORDER BY
            {$column_alias} DESC, u.nombre_completo ASC
    ";

    $stmt = $pdo->prepare($sql);
    $stmt->execute($bind_params);
    $results = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $labels = [];
    $data = [];
    $ids = []; // <-- Asegúrate que se inicializa
    $default_colors = ['#0d6efd', '#6f42c1', '#198754', '#ffc107', '#dc3545', '#fd7e14', '#20c997', '#6c757d', '#0dcaf0', '#d63384'];
    $colors = [];
    $color_index = 0;

    foreach ($results as $row) {
        $labels[] = htmlspecialchars($row['employee_name']);
        $data[] = (int)$row[$column_alias];
        $ids[] = (int)$row['id_usuario']; // <-- ¡ASEGÚRATE QUE SE GUARDA EL ID AQUÍ!
        $colors[] = $default_colors[$color_index % count($default_colors)];
        $color_index++;
    }

    // *** ASEGÚRATE QUE 'ids' SE INCLUYA EN json_encode ***
    echo json_encode([
        'success' => true,
        'labels' => $labels,
        'data' => $data,
        'ids' => $ids, // <-- ¡ESTO DEBE ESTAR!
        'colors' => $colors
    ]);

} catch (PDOException $e) {
    http_response_code(500);
    error_log("Error en tareas_stats_empleados.php: " . $e->getMessage() . " | SQL: " . preg_replace('/\s+/', ' ', $sql) . " | Params: " . json_encode($bind_params));
    echo json_encode(['success' => false, 'message' => 'Error de servidor al obtener estadísticas de empleados. Consulte los logs.']);
}
?>