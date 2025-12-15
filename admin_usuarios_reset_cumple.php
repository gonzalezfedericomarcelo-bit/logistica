<?php
// Archivo: admin_usuarios_reset_cumple.php
session_start();
include 'conexion.php';
include 'funciones_permisos.php';

header('Content-Type: application/json');

// 1. Seguridad: Solo admin puede hacer esto
if (!isset($_SESSION['usuario_id']) || !tiene_permiso('admin_usuarios', $pdo)) {
    echo json_encode(['success' => false, 'message' => 'Acceso denegado']);
    exit();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $id_usuario = (int)$_POST['id_usuario'];

    try {
        // Ponemos ultimo_saludo_cumple en 0 para que el sistema crea que nunca se le saludó
        $sql = "UPDATE usuarios SET ultimo_saludo_cumple = 0 WHERE id_usuario = :id";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([':id' => $id_usuario]);

        // SI el admin se está reseteando a SÍ MISMO, también limpiamos la variable de sesión
        // para que pueda probar inmediatamente sin cerrar el navegador.
        if ($id_usuario == $_SESSION['usuario_id']) {
            unset($_SESSION['cumple_pospuesto']);
        }

        echo json_encode(['success' => true, 'message' => 'Saludo de cumpleaños reseteado.']);
    } catch (PDOException $e) {
        echo json_encode(['success' => false, 'message' => 'Error BD: ' . $e->getMessage()]);
    }
}
?>