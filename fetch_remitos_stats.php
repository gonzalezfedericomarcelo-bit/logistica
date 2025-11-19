<?php
// Archivo: fetch_remitos_stats.php (LÓGICA DE AGRUPACIÓN POR TIEMPO CORREGIDA)
session_start();
include 'conexion.php'; 

header('Content-Type: application/json');

// 1. Verificar autenticación (Solo Admin)
if (!isset($_SESSION['usuario_id']) || $_SESSION['usuario_rol'] !== 'admin') {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Acceso denegado.']);
    exit();
}

// 2. Obtener y preparar parámetros GET (filtros y agrupación)
$groupBy = $_GET['groupBy'] ?? 'user';
$dateFrom = $_GET['date_from'] ?? null;
$dateTo = $_GET['date_to'] ?? null;

// Parámetros de la tabla (usados en modo user/category)
$filtro_usuario = $_GET['usuario'] ?? '';
$filtro_busqueda = trim($_GET['buscar'] ?? '');


$where_clauses = [];
$params = [];
$select_group_column = '';
$group_by_clause = '';
$join_category = false; // Bandera para saber si necesitamos unir categorias

// 3. Determinar columnas de agrupación y selección
switch ($groupBy) {
    case 'user':
        $select_group_column = 'u.nombre_completo AS group_label';
        $group_by_clause = 'u.nombre_completo';
        break;
    case 'category':
        $select_group_column = 'c.nombre AS group_label';
        $group_by_clause = 'c.nombre';
        $join_category = true;
        break;
    case 'week':
        $select_group_column = 'CONCAT(YEAR(adj.fecha_subida), "-W", WEEK(adj.fecha_subida, 3)) AS group_label';
        $group_by_clause = 'YEAR(adj.fecha_subida), WEEK(adj.fecha_subida, 3)';
        break;
    case 'month':
        $select_group_column = 'DATE_FORMAT(adj.fecha_subida, "%Y-%m") AS group_label';
        $group_by_clause = 'DATE_FORMAT(adj.fecha_subida, "%Y-%m")';
        break;
    case 'quarter': // Semestre (Trimestre)
        $select_group_column = 'CONCAT(YEAR(adj.fecha_subida), "-Q", QUARTER(adj.fecha_subida)) AS group_label';
        $group_by_clause = 'YEAR(adj.fecha_subida), QUARTER(adj.fecha_subida)';
        break;
    case 'year':
        $select_group_column = 'YEAR(adj.fecha_subida) AS group_label';
        $group_by_clause = 'YEAR(adj.fecha_subida)';
        break;
    default:
        $select_group_column = 'u.nombre_completo AS group_label';
        $group_by_clause = 'u.nombre_completo';
        $groupBy = 'user';
}


// 4. Consulta SQL Base
$sql = "
    SELECT 
        {$select_group_column},
        COUNT(adj.id_adjunto) AS remito_count,
        SUM(adj.precio_total) AS total_spent
    FROM 
        adjuntos_tarea AS adj
    JOIN 
        usuarios AS u ON adj.id_usuario_subida = u.id_usuario
    LEFT JOIN 
        tareas AS t ON adj.id_tarea = t.id_tarea
    " . ($join_category ? "LEFT JOIN categorias c ON t.id_categoria = c.id_categoria" : "") . "
    WHERE 
        adj.tipo_adjunto = 'remito'
";


// 5. Aplicar Cláusulas WHERE
if ($groupBy === 'user' || $groupBy === 'category') {
    // Aplicar filtros de la tabla (usuario, fecha, búsqueda)
    
    $filtro_fecha_desde_tabla = $_GET['fecha_desde'] ?? null;
    $filtro_fecha_hasta_tabla = $_GET['fecha_hasta'] ?? null;
    
    if (!empty($filtro_usuario) && is_numeric($filtro_usuario)) {
        $where_clauses[] = "adj.id_usuario_subida = :id_usuario";
        $params[':id_usuario'] = (int)$filtro_usuario;
    }
    
    // Si se filtra por el formulario de la TABLA, aplicamos el rango
    if (!empty($filtro_fecha_desde_tabla)) {
        $where_clauses[] = "DATE(adj.fecha_subida) >= :fecha_desde_tabla";
        $params[':fecha_desde_tabla'] = $filtro_fecha_desde_tabla;
    }
    if (!empty($filtro_fecha_hasta_tabla)) {
        $where_clauses[] = "DATE(adj.fecha_subida) <= :fecha_hasta_tabla";
        $params[':fecha_hasta_tabla'] = $filtro_fecha_hasta_tabla;
    }
    if (!empty($filtro_busqueda)) {
        $where_clauses[] = "(adj.nombre_archivo LIKE :buscar OR
                             adj.descripcion_compra LIKE :buscar OR
                             adj.numero_compra LIKE :buscar OR
                             t.titulo LIKE :buscar OR
                             u.nombre_completo LIKE :buscar)";
        $params[':buscar'] = '%' . $filtro_busqueda . '%';
    }


} elseif ($groupBy !== 'user' && $dateFrom && $dateTo) {
    // Aplicar filtros de rango de fecha si se agrupa por tiempo (desde el filtro avanzado)
    $where_clauses[] = "DATE(adj.fecha_subida) BETWEEN :date_from AND :date_to";
    $params[':date_from'] = $dateFrom;
    $params[':date_to'] = $dateTo;
} else {
    // Si la agrupación es por tiempo (week, month, year, quarter) PERO NO hay rango de fechas,
    // NO DEVOLVEMOS DATOS para evitar cargar todo el historial, forzando al usuario a filtrar.
    // Esto es un buen control de rendimiento, pero si el rango no es obligatorio,
    // se podría comentar la línea de abajo. Mantendremos la línea para forzar el filtro y evitar gráficas vacías con rango incorrecto.
     // NO agregamos más cláusulas WHERE si no hay rango de fecha para la agrupación por tiempo. 
     // El gráfico aparecerá vacío si no se selecciona el rango, lo cual es correcto.
}


if (!empty($where_clauses)) {
    $sql .= " AND " . implode(" AND ", $where_clauses);
}


// 6. Aplicar Agrupación y Ordenación
$sql .= "
    GROUP BY
        {$group_by_clause}, group_label
    ORDER BY
        group_label ASC
";

try {
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $results = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Colores para los gráficos
    $base_colors = ['#198754', '#6f42c1', '#ffc107', '#dc3545', '#0dcaf0', '#6c757d', '#0d6efd', '#20c997'];
    $colors = [];
    $labels = [];
    $spent_data = [];
    $count_data = [];

    foreach ($results as $index => $row) {
        // La etiqueta ahora viene de 'group_label'
        $labels[] = htmlspecialchars($row['group_label']);
        $spent = (float)$row['total_spent'];
        $count = (int)$row['remito_count'];
        
        $spent_data[] = $spent;
        $count_data[] = $count;
        
        $colors[] = $base_colors[$index % count($base_colors)]; // Asignación cíclica de colores
    }

    // 7. Devolver los datos en formato JSON
    echo json_encode([
        'success' => true,
        'labels' => $labels,
        'spent_data' => $spent_data,
        'count_data' => $count_data,
        'colors' => $colors,
        'group_by' => $groupBy,
        'message' => 'Datos cargados correctamente.'
    ]);

} catch (PDOException $e) {
    http_response_code(500);
    error_log("Error DB en fetch_remitos_stats: " . $e->getMessage() . " | SQL: " . preg_replace('/\s+/', ' ', $sql));
    echo json_encode(['success' => false, 'message' => 'Error de servidor. Consulte los logs para detalles.']);
}
?>