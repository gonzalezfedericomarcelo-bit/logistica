<?php
// Archivo: admin_roles_crear.php
session_start();
include 'conexion.php';
include 'funciones_permisos.php';

// 1. PROTEGER LA ACCIÓN
if (!isset($_SESSION['usuario_id']) || !tiene_permiso('admin_roles', $pdo) || $_SERVER['REQUEST_METHOD'] !== 'POST') {
    header("Location: dashboard.php");
    exit();
}

$nombre_rol = strtolower(trim($_POST['nombre_rol'] ?? ''));
$descripcion_rol = trim($_POST['descripcion_rol'] ?? '');

// 2. VALIDACIÓN
if (empty($nombre_rol) || !preg_match('/^[a-z0-9_]+$/', $nombre_rol)) {
    $_SESSION['admin_roles_mensaje'] = "Error: El nombre del rol es obligatorio y solo puede contener letras minúsculas, números y guiones bajos.";
    $_SESSION['admin_roles_alerta'] = 'danger';
    header("Location: admin_roles.php");
    exit();
}
if (strlen($nombre_rol) > 50) {
    $_SESSION['admin_roles_mensaje'] = "Error: El nombre del rol no puede exceder los 50 caracteres.";
    $_SESSION['admin_roles_alerta'] = 'danger';
    header("Location: admin_roles.php");
    exit();
}


// 3. PROCESO DE CREACIÓN
try {
    $sql = "INSERT INTO roles (nombre_rol, descripcion) VALUES (:nombre, :descripcion)";
    $stmt = $pdo->prepare($sql);
    $stmt->bindParam(':nombre', $nombre_rol);
    $stmt->bindParam(':descripcion', $descripcion_rol);

    if ($stmt->execute()) {
        $_SESSION['admin_roles_mensaje'] = "Rol '{$nombre_rol}' creado con éxito. Ahora puedes asignarle permisos.";
        $_SESSION['admin_roles_alerta'] = 'success';
        
        // Redirigir para que el nuevo rol aparezca seleccionado
        header("Location: admin_roles.php?rol=" . urlencode($nombre_rol));
        exit();
    } else {
        throw new Exception("Error al ejecutar la inserción.");
    }

} catch (PDOException $e) {
    if ($e->getCode() == '23000') {
        $_SESSION['admin_roles_mensaje'] = "Error: El rol '{$nombre_rol}' ya existe.";
    } else {
        $_SESSION['admin_roles_mensaje'] = "Error de base de datos al crear el rol: " . $e->getMessage();
        error_log("Error DB al crear rol: " . $e->getMessage());
    }
    $_SESSION['admin_roles_alerta'] = 'danger';
} catch (Exception $e) {
    $_SESSION['admin_roles_mensaje'] = "Error inesperado: " . $e->getMessage();
    $_SESSION['admin_roles_alerta'] = 'danger';
}

// Redirigir en caso de error
header("Location: admin_roles.php");
exit();
?>