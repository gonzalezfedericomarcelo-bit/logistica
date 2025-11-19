<?php
// Archivo: fetch_advanced_stats.php (VERSIÓN FINAL Y CORREGIDA)
session_start();
include 'conexion.php'; 

header('Content-Type: application/json');

if (!isset($_SESSION['usuario_id'])) {
    echo json_encode(['success' => false, 'message' => 'Usuario no autenticado.']);
    exit();
}

$id_usuario = $_SESSION['usuario_id'];
$rol_usuario = $_SESSION['usuario_rol'];

// 1. Obtener y validar parámetros de la URL
$groupBy = $_GET['groupBy'] ?? 'week';
$startDate = $_GET['startDate'] ?? date('Y-m-d', strtotime('-3 months'));
$endDate = $_GET['endDate'] ?? date('Y-m-d');

// Seguridad: Asegurar que los parámetros sean válidos
if (!in_array($groupBy, ['day', 'week', 'month'])) {
    $groupBy = 'week';
}

// 2. Determinar la función SQL de agrupación.
$date_column_created = 'DATE(fecha_creacion)';
$date_column_closed = 'DATE(fecha_cierre)'; // Usamos esta por defecto
$order_by = 'date_period'; 

switch ($groupBy) {
    case 'day':
        $date_column_created = 'DATE(fecha_creacion)';
        $date_column_closed = 'DATE(fecha_cierre)';
        break;
    case 'week':
        $date_column_created = 'YEAR(fecha_creacion) * 100 + WEEK(fecha_creacion, 3)';
        $date_column_closed = 'YEAR(fecha_cierre) * 100 + WEEK(fecha_cierre, 3)';
        break;
    case 'month':
        $date_column_created = 'DATE_FORMAT(fecha_creacion, "%Y-%m")';
        $date_column_closed = 'DATE_FORMAT(fecha_cierre, "%Y-%m")';
        break;
}

// 3. Lógica de Filtrado por Rol
$user_filter_clause = "";
$bind_user_id = false;
if ($rol_usuario === 'empleado') {
    $user_filter_clause = "AND t.id_asignado = :id_user";
    $bind_user_id = true;
}

// 4. Consulta SQL para INGRESO DE TAREAS ACTIVAS (dataTotal)
$sql_active_inflow = "
    SELECT 
        {$date_column_created} AS date_period,
        COUNT(t.id_tarea) AS count
    FROM 
        tareas t
    WHERE 
        t.estado NOT IN ('cerrada', 'verificada_admin')
        AND DATE(t.fecha_creacion) >= :start_date_created 
        AND DATE(t.fecha_creacion) <= :end_date_created
        {$user_filter_clause}
    GROUP BY 
        date_period
    ORDER BY 
        date_period ASC
";

// 5. Consulta SQL para TASA DE CIERRE/VERIFICACIÓN (dataCerradas)
// *** LÓGICA CORREGIDA: COALESCE para manejar fechas de cierre NULL ***
// Usamos COALESCE para que si 'fecha_cierre' es NULL, use 'fecha_creacion'
$sql_closed_rate = "
    SELECT 
        COALESCE({$date_column_closed}, {$date_column_created}) AS date_period,
        COUNT(t.id_tarea) AS count
    FROM 
        tareas t
    WHERE 
        t.estado IN ('cerrada', 'verificada_admin')
        AND (DATE(t.fecha_cierre) >= :start_date_closed AND DATE(t.fecha_cierre) <= :end_date_closed
             OR (t.fecha_cierre IS NULL AND DATE(t.fecha_creacion) >= :start_date_closed AND DATE(t.fecha_creacion) <= :end_date_closed))
        {$user_filter_clause}
    GROUP BY 
        date_period
    ORDER BY 
        date_period ASC
";


try {
    $data_created = [];
    $data_closed = [];
    $all_periods = [];

    // --- Ejecutar Ingreso de Tareas Activas ---
    $stmt_created = $pdo->prepare($sql_active_inflow);
    $stmt_created->bindParam(':start_date_created', $startDate);
    $stmt_created->bindParam(':end_date_created', $endDate);
    if ($bind_user_id) { $stmt_created->bindParam(':id_user', $id_usuario); }
    $stmt_created->execute();
    $results_created = $stmt_created->fetchAll(PDO::FETCH_ASSOC);

    foreach ($results_created as $row) {
        $period = $row['date_period'];
        $data_created[$period] = (int)$row['count'];
        $all_periods[$period] = true;
    }

    // --- Ejecutar Tasa de Cierre ---
    $stmt_closed = $pdo->prepare($sql_closed_rate);
    $stmt_closed->bindParam(':start_date_closed', $startDate);
    $stmt_closed->bindParam(':end_date_closed', $endDate);
    if ($bind_user_id) { $stmt_closed->bindParam(':id_user', $id_usuario); }
    $stmt_closed->execute();
    $results_closed = $stmt_closed->fetchAll(PDO::FETCH_ASSOC);

    foreach ($results_closed as $row) {
        $period = $row['date_period'];
        $data_closed[$period] = (int)$row['count'];
        $all_periods[$period] = true;
    }

    // 6. Preparar los datos finales, ordenando y rellenando con ceros
    ksort($all_periods); 

    $labels = [];
    $final_data_created = [];
    $final_data_closed = [];

    foreach (array_keys($all_periods) as $period) {
        $labels[] = $period; 
        $final_data_created[] = $data_created[$period] ?? 0;
        $final_data_closed[] = $data_closed[$period] ?? 0;
    }

    echo json_encode([
        'success' => true, 
        'labels' => $labels, 
        'dataTotal' => $final_data_created, 
        'dataCerradas' => $final_data_closed
    ]);

} catch (PDOException $e) {
    error_log("Error en fetch_advanced_stats: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Error de base de datos. Detalles: ' . $e->getMessage()]);
}
?>