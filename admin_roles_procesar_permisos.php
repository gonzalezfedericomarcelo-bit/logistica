<?php
// Archivo: admin_roles_procesar_permisos.php (Mínima Lógica de Guardado)
session_start();
include 'conexion.php'; 
include 'funciones_permisos.php';

// 1. Proteger la página
if (!isset($_SESSION['usuario_id']) || !tiene_permiso('admin_roles', $pdo) || $_SERVER['REQUEST_METHOD'] !== 'POST') {
    // Redirigir silenciosamente en caso de acceso inválido
    header("Location: dashboard.php");
    exit();
}

$rol_a_actualizar = trim($_POST['rol_nombre'] ?? '');
$permisos_seleccionados = $_POST['permisos_seleccionados'] ?? []; 

if (empty($rol_a_actualizar)) {
    header("Location: admin_roles.php");
    exit();
}

// 2. Transacción de la Base de Datos
$pdo->beginTransaction();

try {
    // A. ELIMINAR todos los permisos actuales del rol.
    $sql_delete = "DELETE FROM rol_permiso WHERE nombre_rol = :rol";
    $stmt_delete = $pdo->prepare($sql_delete);
    $stmt_delete->bindParam(':rol', $rol_a_actualizar);
    $stmt_delete->execute();
    
    // B. INSERTAR los nuevos permisos seleccionados.
    if (!empty($permisos_seleccionados)) {
        $sql_insert = "INSERT INTO rol_permiso (nombre_rol, clave_permiso) VALUES (:rol, :permiso)";
        $stmt_insert = $pdo->prepare($sql_insert);
        $stmt_insert->bindParam(':rol', $rol_a_actualizar);
        
        foreach ($permisos_seleccionados as $clave_permiso) {
            $clave_limpia = trim($clave_permiso); 
            if (!empty($clave_limpia)) {
                $stmt_insert->bindParam(':permiso', $clave_limpia);
                $stmt_insert->execute();
            }
        }
    }
    
    $pdo->commit();
    // Redirige de vuelta a la página de roles con el rol seleccionado
    header("Location: admin_roles.php?rol=" . urlencode($rol_a_actualizar));
    exit();

} catch (PDOException $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    error_log("Error al actualizar permisos: " . $e->getMessage());
    // Se puede añadir un manejo de error de sesión aquí, pero la clave es redirigir:
    header("Location: admin_roles.php?error=db");
    exit();
}
?>