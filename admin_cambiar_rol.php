<?php
// Archivo: admin_cambiar_rol.php
session_start();
include 'conexion.php'; 
include 'funciones_permisos.php'; // Asegurar inclusión

// 1. Proteger la acción
// Reemplazando: $_SESSION['usuario_rol'] !== 'admin'
if (!isset($_SESSION['usuario_id']) || !tiene_permiso('admin_usuarios', $pdo) || $_SERVER['REQUEST_METHOD'] !== 'POST') {
    $_SESSION['admin_usuarios_mensaje'] = "Acceso denegado. Se requiere el permiso 'Gestionar Usuarios'.";
    // ...
    header("Location: admin_usuarios.php");
    exit();
}

// 2. Recibir y validar datos
$id_usuario = filter_input(INPUT_POST, 'id_usuario', FILTER_VALIDATE_INT);
$nuevo_rol = trim($_POST['nuevo_rol'] ?? '');

// Roles válidos (deben coincidir con el selector)
$sql_roles_validos = "SELECT nombre_rol FROM roles";
$stmt_roles_validos = $pdo->query($sql_roles_validos);
$roles_validos = $stmt_roles_validos->fetchAll(PDO::FETCH_COLUMN, 0);

if ($id_usuario === false || $id_usuario === null || !in_array($nuevo_rol, $roles_validos)) {
    $_SESSION['admin_usuarios_mensaje'] = "Datos de rol o usuario inválidos.";
    $_SESSION['admin_usuarios_alerta'] = 'danger';
    header("Location: admin_usuarios.php");
    exit();
}

// 3. Prohibir al administrador cambiarse su propio rol
if ($id_usuario == $_SESSION['usuario_id']) {
    $_SESSION['admin_usuarios_mensaje'] = "Error: No puedes cambiar tu propio rol.";
    $_SESSION['admin_usuarios_alerta'] = 'warning';
    header("Location: admin_usuarios.php");
    exit();
}

// 4. Ejecutar la actualización en la base de datos
try {
    // Usar consultas preparadas (PDO)
    $sql = "UPDATE usuarios SET rol = :nuevo_rol WHERE id_usuario = :id_usuario";
    $stmt = $pdo->prepare($sql);
    $stmt->bindParam(':nuevo_rol', $nuevo_rol);
    $stmt->bindParam(':id_usuario', $id_usuario);
    
    if ($stmt->execute()) {
        if ($stmt->rowCount() > 0) {
            $_SESSION['admin_usuarios_mensaje'] = "Rol del usuario ID #{$id_usuario} actualizado a '{$nuevo_rol}' con éxito.";
            $_SESSION['admin_usuarios_alerta'] = 'success';
        } else {
            $_SESSION['admin_usuarios_mensaje'] = "El rol del usuario ID #{$id_usuario} ya es '{$nuevo_rol}'. No se realizó ningún cambio.";
            $_SESSION['admin_usuarios_alerta'] = 'info';
        }
    } else {
        throw new Exception("Error al ejecutar la actualización.");
    }
    

} catch (PDOException $e) {
    // Manejo de errores de base de datos
    $_SESSION['admin_usuarios_mensaje'] = "Error al actualizar el rol: " . $e->getMessage();
    $_SESSION['admin_usuarios_alerta'] = 'danger';
} catch (Exception $e) {
    $_SESSION['admin_usuarios_mensaje'] = $e->getMessage();
    $_SESSION['admin_usuarios_alerta'] = 'danger';
}

// 5. Redirigir de vuelta a la página de administración
header("Location: admin_usuarios.php");
exit();
?>