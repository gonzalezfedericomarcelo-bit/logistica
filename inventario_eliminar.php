<?php
// Archivo: inventario_eliminar.php
session_start();
include 'conexion.php';
include 'funciones_permisos.php';

if (!isset($_SESSION['usuario_id']) || !tiene_permiso('acceso_inventario', $pdo)) {
    header("Location: dashboard.php"); exit();
}

if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['id'])) {
    $id = (int)$_POST['id'];
    
    try {
        // Opcional: Eliminar archivos de firma asociados si se quiere limpiar basura
        // Por ahora solo borramos el registro
        $stmt = $pdo->prepare("DELETE FROM inventario_cargos WHERE id_cargo = ?");
        $stmt->execute([$id]);
        
        header("Location: inventario_lista.php?msg=eliminado");
    } catch (PDOException $e) {
        die("Error al eliminar: " . $e->getMessage());
    }
} else {
    header("Location: inventario_lista.php");
}
?>