<?php
// Archivo: admin_remitos_conciliar.php (CORREGIDO - PROCESA CONCILIACIÓN AJAX)
session_start();
include 'conexion.php';

header('Content-Type: application/json');
$response = ['success' => false, 'message' => ''];

// 1. Proteger (solo Admin y POST)
if (!isset($_SESSION['usuario_id']) || $_SESSION['usuario_rol'] !== 'admin' || $_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(403); 
    $response['message'] = 'Acceso denegado.';
    echo json_encode($response);
    exit();
}

// 2. Obtener y validar datos
$ids = $_POST['ids'] ?? [];
$nuevo_estado = $_POST['estado'] ?? ''; // 'verificado' o 'pendiente'

// Asegurar que IDs es un array (incluso si se envía solo uno)
if (!is_array($ids)) {
    // Si viene como string '1,2,3', intentar convertir
    $ids = explode(',', $ids);
}

if (empty($ids) || !in_array($nuevo_estado, ['verificado', 'pendiente'])) {
    http_response_code(400);
    $response['message'] = 'Datos de entrada inválidos o estado no permitido.';
    echo json_encode($response);
    exit();
}

// 3. Crear placeholders para la consulta (ej: ?, ?, ?) y asegurar que los IDs sean INT
$valid_ids = array_map('intval', array_filter($ids));

if (empty($valid_ids)) {
    http_response_code(400);
    $response['message'] = 'No se encontraron IDs válidos para conciliar.';
    echo json_encode($response);
    exit();
}

$placeholders = implode(',', array_fill(0, count($valid_ids), '?'));


try {
    $pdo->beginTransaction();

    // 4. Construir y Ejecutar la actualización del estado de conciliación
    $sql = "UPDATE adjuntos_tarea 
            SET estado_conciliacion = ? 
            WHERE id_adjunto IN ({$placeholders}) 
            AND tipo_adjunto = 'remito'"; // Solo aplica a remitos

    $stmt = $pdo->prepare($sql);
    
    // El primer parámetro es el nuevo estado, seguido por todos los IDs
    $params = array_merge([$nuevo_estado], $valid_ids);
    
    $stmt->execute($params);
    $count = $stmt->rowCount();

    $pdo->commit();
    
    $response['success'] = true;
    $response['message'] = "Se actualizaron {$count} remitos/facturas a '{$nuevo_estado}' correctamente.";
    echo json_encode($response);

} catch (PDOException $e) {
    $pdo->rollBack();
    http_response_code(500);
    error_log("Error DB al conciliar remitos: " . $e->getMessage());
    $response['message'] = 'Error de base de datos al procesar la conciliación. (Consulte logs para detalle).';
    echo json_encode($response);
}

?>