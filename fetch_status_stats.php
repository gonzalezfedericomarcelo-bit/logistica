<?php
// Archivo: fetch_status_stats.php
// Objetivo: Devolver el conteo de tareas agrupadas por estado (pipeline) para el dashboard.

// 1. Iniciar sesión y conexión
session_start();
// Asegúrate de que este include apunte correctamente a tu archivo de conexión
include 'conexion.php'; 

header('Content-Type: application/json');

// 2. Verificar autenticación
if (!isset($_SESSION['usuario_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Usuario no autenticado.']);
    exit();
}

$id_usuario = $_SESSION['usuario_id'];
$rol_usuario = $_SESSION['usuario_rol'] ?? 'empleado';

// 3. Lógica de filtrado por rol
$user_filter_clause = "";
$bind_user_id = false;
if ($rol_usuario === 'empleado') {
    // Si es empleado, solo ve las tareas que le fueron ASIGNADAS (excepto las cerradas)
    $user_filter_clause = "AND id_asignado = :id_user";
    $bind_user_id = true;
}

try {
    // 4. Consulta SQL para obtener la cuenta de tareas agrupadas por estado
    $sql = "
        SELECT 
            estado, 
            COUNT(id_tarea) as count
        FROM tareas
        WHERE 
            1=1 
            {$user_filter_clause}
        GROUP BY estado
        -- Ordenar los estados de manera lógica para el gráfico
        ORDER BY FIELD(estado, 'activa', 'asignada', 'en_curso', 'finalizada_tecnico', 'cerrada', 'verificada_admin')
    ";
    
    $stmt = $pdo->prepare($sql);
    
    // 5. Vincular el parámetro si el usuario es empleado
    if ($bind_user_id) { 
        $stmt->bindParam(':id_user', $id_usuario, PDO::PARAM_INT); 
    }
    
    $stmt->execute();
    $results = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $labels = [];
    $data = [];
    $colors = [];
    
    // 6. Mapeo de estados a nombres amigables y colores
    $status_colors = [
        'activa' => 'rgba(255, 193, 7, 0.8)',         // Amarillo/Warning
        'asignada' => 'rgba(23, 162, 184, 0.8)',     // Cyan/Info
        'en_curso' => 'rgba(13, 110, 253, 0.8)',      // Azul/Primary
        'finalizada_tecnico' => 'rgba(111, 66, 193, 0.8)', // Púrpura/Indigo
        'cerrada' => 'rgba(220, 53, 69, 0.8)',        // Rojo/Danger
        'verificada_admin' => 'rgba(25, 135, 84, 0.8)', // Verde/Success
        'default' => 'rgba(108, 117, 125, 0.8)'      // Gris/Secondary
    ];

    $status_names = [
        'activa' => 'Activa/Nueva',
        'asignada' => 'Asignada',
        'en_curso' => 'En Curso',
        'finalizada_tecnico' => 'Finalizada (Téc.)',
        'cerrada' => 'Cerrada',
        'verificada_admin' => 'Verificada (Admin)'
    ];

    foreach ($results as $row) {
        $estado = strtolower($row['estado']);
        $count = (int)$row['count'];
        
        $labels[] = $status_names[$estado] ?? ucfirst(str_replace('_', ' ', $estado)); // Usa nombre amigable o capitaliza
        $data[] = $count;
        $colors[] = $status_colors[$estado] ?? $status_colors['default'];
    }

    // 7. Devolver el JSON final
    if (empty($labels)) {
        // Respuesta para "No Data" (Manejado en dashboard.php)
        echo json_encode([
            'success' => true, 
            'labels' => [], 
            'data' => [], 
            'colors' => [],
            'message' => 'No hay tareas creadas bajo este filtro.'
        ]);
    } else {
        echo json_encode([
            'success' => true,
            'labels' => $labels,
            'data' => $data,
            'colors' => $colors
        ]);
    }

} catch (PDOException $e) {
    // 8. Manejo de errores de conexión/BD
    // Devolvemos 500 para que el fetch() de JavaScript lo capture como error de conexión/servidor
    http_response_code(500); 
    error_log("Error en fetch_status_stats.php: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Error de base de datos o consulta SQL.']);
} catch (Exception $e) {
    http_response_code(500); 
    error_log("Error general en fetch_status_stats.php: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Error interno del servidor.']);
}

?>