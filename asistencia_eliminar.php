<?php
// Archivo: asistencia_eliminar.php (BORRADO SEGURO CON PASSWORD)
session_start();
include 'conexion.php';

header('Content-Type: application/json');

if (!isset($_SESSION['usuario_id'])) {
    echo json_encode(['success' => false, 'msg' => 'Sesión expirada']);
    exit;
}

$data = json_decode(file_get_contents('php://input'), true);
$id_parte = $data['id_parte'] ?? 0;
$password = $data['password'] ?? '';
$id_usuario = $_SESSION['usuario_id'];

if ($id_parte <= 0 || empty($password)) {
    echo json_encode(['success' => false, 'msg' => 'Datos incompletos.']);
    exit;
}

try {
    // 1. Verificar la contraseña del usuario actual
    $stmt = $pdo->prepare("SELECT password FROM usuarios WHERE id_usuario = :id");
    $stmt->execute([':id' => $id_usuario]);
    $hash_real = $stmt->fetchColumn();

    if (!password_verify($password, $hash_real)) {
        echo json_encode(['success' => false, 'msg' => 'CONTRASEÑA INCORRECTA. No se eliminó nada.']);
        exit;
    }

    // 2. Eliminar el parte (Las FK en la DB deberían borrar los detalles en cascada, pero lo forzamos por seguridad)
    $pdo->beginTransaction();
    
    $pdo->prepare("DELETE FROM asistencia_detalles WHERE id_parte = :id")->execute([':id' => $id_parte]);
    $pdo->prepare("DELETE FROM asistencia_partes WHERE id_parte = :id")->execute([':id' => $id_parte]);

    $pdo->commit();

    echo json_encode(['success' => true, 'msg' => 'Parte eliminado correctamente.']);

} catch (Exception $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    echo json_encode(['success' => false, 'msg' => 'Error de BD: ' . $e->getMessage()]);
}
?>