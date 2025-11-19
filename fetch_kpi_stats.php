<?php
// Archivo: fetch_kpi_stats.php
// Objetivo: Devolver contadores clave (KPIs) en formato JSON para el dashboard.

session_start();
include 'conexion.php'; 

header('Content-Type: application/json');

if (!isset($_SESSION['usuario_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Acceso no autorizado.']);
    exit();
}

$id_usuario = $_SESSION['usuario_id'];
$rol_usuario = $_SESSION['usuario_rol'];
$response = ['success' => true, 'stats' => []];

try {
    // ----------------------------------------------------
    // 1. Estadísticas Generales (Visibles para Admin)
    // ----------------------------------------------------
    if ($rol_usuario === 'admin') {
        // Total de Tareas
        $sql_total = "SELECT COUNT(*) FROM tareas";
        $response['stats']['total_tareas'] = $pdo->query($sql_total)->fetchColumn();
        
        // Tareas Pendientes (Estado: 'activa', 'asignada', 'en_curso', 'finalizada_tecnico')
        $sql_pendientes = "SELECT COUNT(*) FROM tareas WHERE estado IN ('activa', 'asignada', 'en_curso', 'finalizada_tecnico')";
        $response['stats']['tareas_pendientes'] = $pdo->query($sql_pendientes)->fetchColumn();

        // Tareas Terminadas/Cerradas (Estado: 'cerrada')
        $sql_cerradas = "SELECT COUNT(*) FROM tareas WHERE estado = 'cerrada'";
        $response['stats']['tareas_cerradas'] = $pdo->query($sql_cerradas)->fetchColumn();

        // Total de Empleados
        $sql_empleados = "SELECT COUNT(*) FROM usuarios WHERE rol = 'empleado'";
        $response['stats']['total_empleados'] = $pdo->query($sql_empleados)->fetchColumn();
    }
    
    // ----------------------------------------------------
    // 2. Estadísticas Específicas del Usuario (Todos los Roles)
    // ----------------------------------------------------
    // Mis Tareas Abiertas (Asignadas a mí, pero no cerradas)
    $sql_mis_abiertas = "SELECT COUNT(*) FROM tareas 
                         WHERE id_asignado = :id_user 
                         AND estado NOT IN ('cerrada')";
    $stmt_mis_abiertas = $pdo->prepare($sql_mis_abiertas);
    $stmt_mis_abiertas->execute([':id_user' => $id_usuario]);
    $response['stats']['mis_tareas_abiertas'] = $stmt_mis_abiertas->fetchColumn();

    // Mis Tareas para Hoy (Fecha límite es hoy)
    $sql_mis_hoy = "SELECT COUNT(*) FROM tareas 
                    WHERE id_asignado = :id_user 
                    AND estado IN ('activa', 'asignada', 'en_curso')
                    AND DATE(fecha_limite) = CURDATE()";
    $stmt_mis_hoy = $pdo->prepare($sql_mis_hoy);
    $stmt_mis_hoy->execute([':id_user' => $id_usuario]);
    $response['stats']['mis_tareas_hoy'] = $stmt_mis_hoy->fetchColumn();

    echo json_encode($response);

} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Error de BD: ' . $e->getMessage()]);
}
?>