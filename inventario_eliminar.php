<?php
// Archivo: inventario_eliminar.php
session_start();
include 'conexion.php';
include 'funciones_permisos.php';

// Verificación estricta de sesión
if (!isset($_SESSION['usuario_id']) || !tiene_permiso('acceso_inventario', $pdo)) {
    header("Location: dashboard.php"); 
    exit();
}

// Aceptamos GET para que funcione el click directo desde la lista
if (isset($_GET['id'])) {
    $id = (int)$_GET['id'];
    
    try {
        // Verificar si existe antes de borrar
        $check = $pdo->prepare("SELECT id_cargo FROM inventario_cargos WHERE id_cargo = ?");
        $check->execute([$id]);
        
        if ($check->rowCount() > 0) {
            // Borrar historial asociado primero para mantener limpieza (opcional pero recomendado)
            $pdo->prepare("DELETE FROM historial_movimientos WHERE id_bien = ?")->execute([$id]);
            
            // Borrar el bien
            $stmt = $pdo->prepare("DELETE FROM inventario_cargos WHERE id_cargo = ?");
            $stmt->execute([$id]);
            
            // Redirección con éxito
            header("Location: inventario_lista.php?msg=eliminado");
            exit();
        } else {
            // El ID no existe
            header("Location: inventario_lista.php?err=no_existe");
            exit();
        }
    } catch (PDOException $e) {
        // Mostrar error en pantalla si falla SQL
        die("Error crítico al eliminar: " . $e->getMessage());
    }
} else {
    // Si entran sin ID, volver a la lista
    header("Location: inventario_lista.php");
    exit();
}
?>