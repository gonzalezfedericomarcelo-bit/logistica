<?php
// Archivo: admin_roles_guardar.php
session_start();
include 'conexion.php';
include 'funciones_permisos.php';

// 1. PROTEGER LA ACCIÓN
if (!isset($_SESSION['usuario_id']) || !tiene_permiso('admin_roles', $pdo) || $_SERVER['REQUEST_METHOD'] !== 'POST') {
    header("Location: dashboard.php");
    exit();
}

$rol_a_guardar = trim($_POST['rol_a_guardar'] ?? '');
$permisos_seleccionados = $_POST['permisos'] ?? []; // Esto será un array o vacío si no se marcó nada

// 2. VALIDACIÓN BÁSICA
if (empty($rol_a_guardar)) {
    $_SESSION['admin_roles_mensaje'] = "Error: No se especificó el rol a guardar.";
    $_SESSION['admin_roles_alerta'] = 'danger';
    header("Location: admin_roles.php");
    exit();
}

// 3. SEGURIDAD: NO PERMITIR MODIFICAR EL ROL ADMIN
if ($rol_a_guardar === 'admin') {
    $_SESSION['admin_roles_mensaje'] = "Error: El rol 'admin' no puede modificarse. Siempre tendrá acceso total.";
    $_SESSION['admin_roles_alerta'] = 'warning';
    header("Location: admin_roles.php?rol=" . urlencode($rol_a_guardar));
    exit();
}

// 4. PROCESO DE ACTUALIZACIÓN DE PERMISOS (Transacción)
try {
    $pdo->beginTransaction();

    // A) ELIMINAR TODOS los permisos actuales para este rol
    $sql_delete = "DELETE FROM rol_permiso WHERE nombre_rol = :rol";
    $stmt_delete = $pdo->prepare($sql_delete);
    $stmt_delete->bindParam(':rol', $rol_a_guardar);
    $stmt_delete->execute();
    
    $deleted_count = $stmt_delete->rowCount(); // Contamos cuántos se borraron

    $inserted_count = 0;
    
    // B) INSERTAR los nuevos permisos seleccionados
    if (!empty($permisos_seleccionados)) {
        $sql_insert = "INSERT INTO rol_permiso (nombre_rol, clave_permiso) VALUES (:rol, :permiso)";
        $stmt_insert = $pdo->prepare($sql_insert);
        
        foreach ($permisos_seleccionados as $permiso_clave) {
            // Se asume que $permiso_clave ya es seguro (viene de un checkbox con valores de BD)
            $stmt_insert->bindParam(':rol', $rol_a_guardar);
            $stmt_insert->bindParam(':permiso', $permiso_clave);
            
            // Si la inserción es exitosa (ignora si falla por clave inexistente o duplicado, aunque no debería)
            if ($stmt_insert->execute()) {
                $inserted_count++;
            }
        }
    }

    // C) CONFIRMAR LA TRANSACCIÓN
    $pdo->commit();

    $_SESSION['admin_roles_mensaje'] = "Permisos para el rol '{$rol_a_guardar}' actualizados con éxito. ({$inserted_count} asignados)";
    $_SESSION['admin_roles_alerta'] = 'success';

} catch (PDOException $e) {
    $pdo->rollBack();
    error_log("Error al guardar permisos para rol {$rol_a_guardar}: " . $e->getMessage());
    $_SESSION['admin_roles_mensaje'] = "Error de base de datos al guardar los permisos: " . $e->getMessage();
    $_SESSION['admin_roles_alerta'] = 'danger';
}

// 5. REDIRIGIR DE VUELTA a la vista del rol que se acaba de guardar
header("Location: admin_roles.php?rol=" . urlencode($rol_a_guardar));
exit();
?>