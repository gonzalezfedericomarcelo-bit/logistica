<?php
session_start();
include 'conexion.php';

if(!isset($_SESSION['usuario_id']) || !isset($_POST['orden'])) {
    exit;
}

$id_user = $_SESSION['usuario_id'];
$orden = $_POST['orden']; // Array con los IDs ordenados

try {
    $pdo->beginTransaction();
    // Preparamos la sentencia para insertar o actualizar el orden
    // Usamos REPLACE o INSERT ON DUPLICATE KEY UPDATE según tu motor, aquí usaremos DELETE/INSERT por compatibilidad simple o REPLACE
    $stmt = $pdo->prepare("REPLACE INTO inventario_orden_usuarios (id_usuario, id_tipo_bien, orden) VALUES (?, ?, ?)");
    
    foreach($orden as $posicion => $id_tipo) {
        if(is_numeric($id_tipo)) {
            $stmt->execute([$id_user, $id_tipo, $posicion]);
        }
    }
    $pdo->commit();
    echo json_encode(['status' => 'ok']);
} catch (Exception $e) {
    $pdo->rollBack();
    echo json_encode(['status' => 'error']);
}
?>