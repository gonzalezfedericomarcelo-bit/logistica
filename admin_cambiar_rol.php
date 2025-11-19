<?php
// Archivo: admin_cambiar_rol.php (CORREGIDO: Validación dinámica de roles)
session_start();
include 'conexion.php';
include 'funciones_permisos.php';

// 1. PROTEGER LA ACCIÓN
if (!isset($_SESSION['usuario_id']) || !tiene_permiso('admin_usuarios', $pdo)) {
    header("Location: dashboard.php");
    exit();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['cambiar_rol'])) {
    $id_usuario = intval($_POST['id_usuario']);
    $nuevo_rol = trim($_POST['nuevo_rol']);

    // Seguridad: No permitir cambiarse el rol a uno mismo para evitar bloqueos accidentales
    if ($id_usuario === $_SESSION['usuario_id']) {
         $_SESSION['admin_usuarios_mensaje'] = "Por seguridad, no puedes cambiar tu propio rol.";
         $_SESSION['admin_usuarios_alerta'] = "warning";
         header("Location: admin_usuarios.php");
         exit();
    }

    try {
        // 2. VALIDACIÓN DINÁMICA (Aquí estaba el problema)
        // Antes validaba contra una lista fija. Ahora consultamos si el rol existe en la BD.
        $stmt_check = $pdo->prepare("SELECT COUNT(*) FROM roles WHERE nombre_rol = :rol");
        $stmt_check->execute([':rol' => $nuevo_rol]);
        $existe_rol = $stmt_check->fetchColumn();

        if ($existe_rol > 0) {
            // 3. ACTUALIZAR SI EL ROL ES VÁLIDO
            $sql = "UPDATE usuarios SET rol = :rol WHERE id_usuario = :id";
            $stmt = $pdo->prepare($sql);
            if ($stmt->execute([':rol' => $nuevo_rol, ':id' => $id_usuario])) {
                $_SESSION['admin_usuarios_mensaje'] = "Rol actualizado correctamente a: " . htmlspecialchars($nuevo_rol);
                $_SESSION['admin_usuarios_alerta'] = "success";
            } else {
                $_SESSION['admin_usuarios_mensaje'] = "No se pudo actualizar el rol.";
                $_SESSION['admin_usuarios_alerta'] = "danger";
            }
        } else {
            // El rol no existe en la base de datos
            $_SESSION['admin_usuarios_mensaje'] = "Error: El rol '" . htmlspecialchars($nuevo_rol) . "' no es válido.";
            $_SESSION['admin_usuarios_alerta'] = "danger";
        }

    } catch (PDOException $e) {
        $_SESSION['admin_usuarios_mensaje'] = "Error de Base de Datos: " . $e->getMessage();
        $_SESSION['admin_usuarios_alerta'] = "danger";
    }
} else {
    // Si intentan entrar directo sin POST
    header("Location: admin_usuarios.php");
    exit();
}

// Volver a la lista
header("Location: admin_usuarios.php");
exit();
?>