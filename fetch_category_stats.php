<?php
// Archivo: fetch_category_stats.php (MODIFICADO: Incluye ID de categoría)
session_start();
include 'conexion.php'; 

header('Content-Type: application/json');

if (!isset($_SESSION['usuario_id'])) {
    // Return 401 Unauthorized if not logged in
    http_response_code(401); 
    echo json_encode(['success' => false, 'message' => 'Usuario no autenticado.']);
    exit();
}

$id_usuario = $_SESSION['usuario_id'];
$rol_usuario = $_SESSION['usuario_rol'];

// Lógica de filtrado por rol (Sin cambios)
$user_filter_clause = "";
$bind_user_id = false;
if ($rol_usuario === 'empleado') {
    $user_filter_clause = "AND t.id_asignado = :id_user";
    $bind_user_id = true;
}

// *** MODIFICACIÓN: Incluir c.id_categoria en SELECT ***
$sql = "
    SELECT 
        c.id_categoria, -- <-- AÑADIDO
        c.nombre AS category_name, 
        COUNT(t.id_tarea) AS task_count
    FROM 
        tareas t
    JOIN 
        categorias c ON t.id_categoria = c.id_categoria
    WHERE 
        t.estado NOT IN ('verificada', 'cancelada')
        {$user_filter_clause}
    GROUP BY 
        c.id_categoria, c.nombre -- <-- AÑADIDO id_categoria al GROUP BY
    ORDER BY 
        task_count DESC
";

try {
    $stmt = $pdo->prepare($sql);
    if ($bind_user_id) {
        $stmt->bindParam(':id_user', $id_usuario, PDO::PARAM_INT); // Especificar tipo INT
    }
    $stmt->execute();
    $results = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $labels = [];
    $data = [];
    $ids = []; // <-- NUEVO: Array para guardar los IDs
    $default_colors = [
        '#0d6efd', '#dc3545', '#ffc107', '#198754', '#6c757d',
        '#0dcaf0', '#6f42c1', '#fd7e14', '#20c997', '#d63384' // Añadidos más colores
    ];
    $colors = [];
    $color_index = 0;

    foreach ($results as $row) {
        $labels[] = htmlspecialchars($row['category_name']);
        $data[] = (int)$row['task_count'];
        $ids[] = (int)$row['id_categoria']; // <-- NUEVO: Guardar el ID
        // Asignar color (asegurarse de tener suficientes colores o que se repitan cíclicamente)
        $colors[] = $default_colors[$color_index % count($default_colors)]; 
        $color_index++;
    }

    echo json_encode([
        'success' => true, 
        'labels' => $labels, 
        'data' => $data, 
        'ids' => $ids, // <-- NUEVO: Enviar los IDs
        'colors' => $colors
    ]);

} catch (PDOException $e) {
    // Return 500 Internal Server Error on DB error
    http_response_code(500); 
    // Log detailed error for debugging
    error_log("Error DB en fetch_category_stats: " . $e->getMessage() . " | SQL: " . preg_replace('/\s+/', ' ', $sql)); 
    echo json_encode(['success' => false, 'message' => 'Error de base de datos al obtener estadísticas de categoría.']);
}
?>