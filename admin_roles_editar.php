<?php
// Archivo: admin_roles_editar.php
session_start();
include 'conexion.php';
include 'funciones_permisos.php';

// 1. PROTEGER LA ACCIÓN
if (!isset($_SESSION['usuario_id']) || !tiene_permiso('admin_roles', $pdo) || $_SERVER['REQUEST_METHOD'] !== 'POST') {
    header("Location: dashboard.php");
    exit();
}

$rol_original = trim($_POST['rol_original'] ?? '');
// El nombre_rol_edit viene deshabilitado, solo se usa para validar que coincida
$nombre_rol_edit = trim($_POST['nombre_rol_edit'] ?? ''); 
$descripcion_rol_edit = trim($_POST['descripcion_rol_edit'] ?? '');

// 2. VALIDACIÓN
if (empty($rol_original) || $rol_original !== $nombre_rol_edit) {
    $_SESSION['admin_roles_mensaje'] = "Error: Nombre de rol inválido o alterado.";
    $_SESSION['admin_roles_alerta'] = 'danger';
    header("Location: admin_roles.php");
    exit();
}

// 3. SEGURIDAD: NO PERMITIR MODIFICAR EL ROL ADMIN
if ($rol_original === 'admin') {
    $_SESSION['admin_roles_mensaje'] = "Error: No se permite modificar la descripción del rol 'admin'.";
    $_SESSION['admin_roles_alerta'] = 'warning';
    header("Location: admin_roles.php?rol=" . urlencode($rol_original));
    exit();
}

// 4. PROCESO DE EDICIÓN
try {
    // Solo actualizamos la descripción
    $sql = "UPDATE roles SET descripcion = :descripcion WHERE nombre_rol = :nombre_original";
    $stmt = $pdo->prepare($sql);
    $stmt->bindParam(':descripcion', $descripcion_rol_edit);
    $stmt->bindParam(':nombre_original', $rol_original);

    if ($stmt->execute()) {
        if ($stmt->rowCount() > 0) {
            $_SESSION['admin_roles_mensaje'] = "Descripción del rol '{$rol_original}' actualizada con éxito.";
            $_SESSION['admin_roles_alerta'] = 'success';
        } else {
            $_SESSION['admin_roles_mensaje'] = "No se detectaron cambios en la descripción del rol '{$rol_original}'.";
            $_SESSION['admin_roles_alerta'] = 'info';
        }
    } else {
        throw new Exception("Error al ejecutar la actualización.");
    }

} catch (PDOException $e) {
    $_SESSION['admin_roles_mensaje'] = "Error de base de datos al editar el rol: " . $e->getMessage();
    $_SESSION['admin_roles_alerta'] = 'danger';
} catch (Exception $e) {
    $_SESSION['admin_roles_mensaje'] = "Error inesperado: " . $e->getMessage();
    $_SESSION['admin_roles_alerta'] = 'danger';
}

// Redirigir
header("Location: admin_roles.php?rol=" . urlencode($rol_original));
exit();
?>