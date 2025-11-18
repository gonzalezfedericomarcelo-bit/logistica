<?php
// Archivo: fetch_priority_stats.php
session_start();
include 'conexion.php'; 

header('Content-Type: application/json');

// Verificar si el usuario está logueado
if (!isset($_SESSION['usuario_id'])) {
    echo json_encode(['success' => false, 'message' => 'Usuario no autenticado.']);
    exit();
}

$id_usuario = $_SESSION['usuario_id'];
$rol_usuario = $_SESSION['usuario_rol'];

// *** LÓGICA DE FILTRADO POR ROL ***
$user_filter_clause = "";
$bind_user_id = false;
if ($rol_usuario === 'empleado') {
    $user_filter_clause = "AND id_asignado = :id_user";
    $bind_user_id = true;
}

try {
    // Consulta para obtener la cuenta de tareas ACTIVAS agrupadas por prioridad
    // Las tareas activas son aquellas que NO están en estado final (cerrada, verificada_admin)
    $sql = "
        SELECT 
            prioridad, 
            COUNT(id_tarea) as count
        FROM tareas
        WHERE 
            estado NOT IN ('cerrada', 'verificada_admin') 
            {$user_filter_clause}
        GROUP BY prioridad
        ORDER BY FIELD(prioridad, 'urgente', 'alta', 'media', 'baja')
    ";
    
    $stmt = $pdo->prepare($sql);
    if ($bind_user_id) { 
        $stmt->bindParam(':id_user', $id_usuario); 
    }
    $stmt->execute();
    $results = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $labels = [];
    $data = [];
    $colors = [];
    
    // Definición de colores estándar para prioridades
    $priority_colors = [
        'urgente' => 'rgba(220, 53, 69, 0.8)', // Rojo (danger)
        'alta' => 'rgba(255, 193, 7, 0.8)', // Amarillo (warning)
        'media' => 'rgba(23, 162, 184, 0.8)', // Azul/Cyan (info)
        'baja' => 'rgba(40, 167, 69, 0.8)', // Verde (success)
        'default' => 'rgba(108, 117, 125, 0.8)' // Gris (secondary)
    ];

    foreach ($results as $row) {
        $prioridad = strtolower($row['prioridad']);
        $labels[] = ucwords($prioridad);
        $data[] = $row['count'];
        $colors[] = $priority_colors[$prioridad] ?? $priority_colors['default'];
    }

    echo json_encode([
        'success' => true,
        'labels' => $labels,
        'data' => $data,
        'colors' => $colors
    ]);

} catch (PDOException $e) {
    error_log("Error al cargar estadísticas de prioridad: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Error de base de datos.']);
}

?>