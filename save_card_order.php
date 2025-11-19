<?php
// Archivo: save_card_order.php
// Este script recibe el orden de las tarjetas por AJAX y lo guarda en la base de datos.

session_start();
include 'conexion.php'; // Asegúrese de que este archivo conecta a $pdo

header('Content-Type: application/json');
$response = ['success' => false, 'message' => ''];

// 1. Verificar sesión y método
if (!isset($_SESSION['usuario_id'])) {
    http_response_code(401);
    $response['message'] = 'Acceso no autorizado.';
    echo json_encode($response);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    $response['message'] = 'Método no permitido.';
    echo json_encode($response);
    exit;
}

$id_usuario = $_SESSION['usuario_id'];

// 2. Obtener y validar datos de entrada
$container_id = $_POST['container_id'] ?? '';
$order_json = $_POST['order'] ?? '';

if (empty($container_id) || empty($order_json)) {
    http_response_code(400);
    $response['message'] = 'Datos incompletos.';
    echo json_encode($response);
    exit;
}

// Mapeo de la columna en la DB al ID del contenedor
$db_column = '';
if ($container_id === 'card_order_right') {
    $db_column = 'dashboard_order_right';
} elseif ($container_id === 'card_order_left') {
    $db_column = 'dashboard_order_left';
} else {
    http_response_code(400);
    $response['message'] = 'ID de contenedor inválido.';
    echo json_encode($response);
    exit;
}

// 3. Guardar o actualizar en la base de datos
try {
    // Usar INSERT ... ON DUPLICATE KEY UPDATE para manejar la inserción inicial o la actualización
    $sql = "INSERT INTO preferencias_usuario (id_usuario, {$db_column}) 
            VALUES (:id_user, :order_data)
            ON DUPLICATE KEY UPDATE {$db_column} = :order_data";
            
    $stmt = $pdo->prepare($sql);
    $stmt->bindParam(':id_user', $id_usuario, PDO::PARAM_INT);
    $stmt->bindParam(':order_data', $order_json, PDO::PARAM_STR);
    
    $stmt->execute();
    
    $response['success'] = true;
    $response['message'] = 'Orden de tarjetas guardado exitosamente.';
    echo json_encode($response);
    
} catch (PDOException $e) {
    http_response_code(500);
    error_log("Error de BD al guardar el orden del dashboard: " . $e->getMessage());
    $response['message'] = 'Error de base de datos al guardar la preferencia.';
    echo json_encode($response);
}
?>