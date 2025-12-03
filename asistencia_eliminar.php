<?php
// Archivo: asistencia_eliminar.php (PROTEGIDO CON PERMISOS Y PASSWORD)
session_start();
include 'conexion.php';
include_once 'funciones_permisos.php'; // <--- Importante incluir esto

header('Content-Type: application/json');

if (!isset($_SESSION['usuario_id'])) {
    echo json_encode(['success' => false, 'msg' => 'Sesión expirada']);
    exit;
}

// 1. NUEVA SEGURIDAD: Verificar el permiso específico en el Backend
if (!tiene_permiso('asistencia_eliminar', $pdo)) {
    echo json_encode(['success' => false, 'msg' => 'ACCESO DENEGADO: No tienes permiso para eliminar partes.']);
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
    // 2. Verificar la contraseña del usuario actual (Doble factor de seguridad)
    $stmt = $pdo->prepare("SELECT password FROM usuarios WHERE id_usuario = :id");
    $stmt->execute([':id' => $id_usuario]);
    $hash_real = $stmt->fetchColumn();

    if (!password_verify($password, $hash_real)) {
        echo json_encode(['success' => false, 'msg' => 'CONTRASEÑA INCORRECTA. No se eliminó nada.']);
        exit;
    }

    // 3. Eliminar el parte
    $pdo->beginTransaction();
    
    // Eliminar detalles primero
    $pdo->prepare("DELETE FROM asistencia_detalles WHERE id_parte = :id")->execute([':id' => $id_parte]);
    // Eliminar cabecera
    $pdo->prepare("DELETE FROM asistencia_partes WHERE id_parte = :id")->execute([':id' => $id_parte]);

    $pdo->commit();

    echo json_encode(['success' => true, 'msg' => 'Parte eliminado correctamente.']);

} catch (Exception $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    echo json_encode(['success' => false, 'msg' => 'Error de BD: ' . $e->getMessage()]);
}
?>