<?php
// Archivo: admin_roles_eliminar.php
session_start();
include 'conexion.php';
include 'funciones_permisos.php';

// 1. PROTEGER LA ACCIÓN
if (!isset($_SESSION['usuario_id']) || !tiene_permiso('admin_roles', $pdo) || $_SERVER['REQUEST_METHOD'] !== 'POST') {
    header("Location: dashboard.php");
    exit();
}

$rol_a_eliminar = trim($_POST['rol_a_eliminar'] ?? '');

// 2. VALIDACIÓN Y SEGURIDAD
if (empty($rol_a_eliminar)) {
    $_SESSION['admin_roles_mensaje'] = "Error: No se especificó el rol a eliminar.";
    $_SESSION['admin_roles_alerta'] = 'danger';
    header("Location: admin_roles.php");
    exit();
}

// Proteger roles críticos
$roles_criticos = ['admin', 'empleado', 'auxiliar', 'encargado'];
if (in_array($rol_a_eliminar, $roles_criticos)) {
    $_SESSION['admin_roles_mensaje'] = "Error: No se permite eliminar el rol crítico '{$rol_a_eliminar}'.";
    $_SESSION['admin_roles_alerta'] = 'danger';
    header("Location: admin_roles.php?rol=" . urlencode($rol_a_eliminar));
    exit();
}

// 3. PROCESO DE ELIMINACIÓN (TRANSACCIÓN)
try {
    $pdo->beginTransaction();

    // A) Contar usuarios que tienen este rol (para el mensaje final)
    $sql_count = "SELECT COUNT(*) FROM usuarios WHERE rol = :rol";
    $stmt_count = $pdo->prepare($sql_count);
    $stmt_count->bindParam(':rol', $rol_a_eliminar);
    $stmt_count->execute();
    $usuarios_afectados = $stmt_count->fetchColumn();

    // B) Reasignar usuarios a 'empleado' (o al que definas como fallback)
    $sql_update = "UPDATE usuarios SET rol = 'empleado' WHERE rol = :rol_old";
    $stmt_update = $pdo->prepare($sql_update);
    $stmt_update->bindParam(':rol_old', $rol_a_eliminar);
    $stmt_update->execute();

    // C) Eliminar el rol
    $sql_delete = "DELETE FROM roles WHERE nombre_rol = :rol";
    $stmt_delete = $pdo->prepare($sql_delete);
    $stmt_delete->bindParam(':rol', $rol_a_eliminar);
    $stmt_delete->execute();
    
    if ($stmt_delete->rowCount() > 0) {
        $pdo->commit();
        $_SESSION['admin_roles_mensaje'] = "Rol '{$rol_a_eliminar}' eliminado con éxito. Se reasignaron {$usuarios_afectados} usuarios al rol 'empleado'.";
        $_SESSION['admin_roles_alerta'] = 'success';
    } else {
        $pdo->rollBack();
        $_SESSION['admin_roles_mensaje'] = "Error: No se pudo eliminar el rol '{$rol_a_eliminar}' (posiblemente ya no exista).";
        $_SESSION['admin_roles_alerta'] = 'warning';
    }

} catch (PDOException $e) {
    $pdo->rollBack();
    error_log("Error DB al eliminar rol: " . $e->getMessage());
    $_SESSION['admin_roles_mensaje'] = "Error de base de datos al eliminar el rol: " . $e->getMessage();
    $_SESSION['admin_roles_alerta'] = 'danger';
}

// Redirigir a la vista principal
header("Location: admin_roles.php");
exit();
?>