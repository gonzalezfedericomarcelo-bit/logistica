<?php
// Archivo: inventario_eliminar_masivo.php
session_start();
include 'conexion.php';
include 'funciones_permisos.php';

// Verificación de seguridad básica
if (!isset($_SESSION['usuario_id']) || !tiene_permiso('acceso_inventario', $pdo)) {
    die("Acceso denegado");
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['ids']) && is_array($_POST['ids'])) {
    $ids = $_POST['ids'];
    
    // Convertimos el array de IDs en un string seguro para la consulta (ej: "1,2,5")
    // Usamos intval para asegurar que sean números y evitar inyecciones
    $ids_seguros = array_map('intval', $ids);
    $lista_ids = implode(',', $ids_seguros);
    
    if (!empty($lista_ids)) {
        try {
            $pdo->beginTransaction();
            
            // 1. Borrar los valores dinámicos asociados (Ficha técnica)
            $sql1 = "DELETE FROM inventario_valores_dinamicos WHERE id_cargo IN ($lista_ids)";
            $pdo->exec($sql1);
            
            // 2. Borrar los cargos (El bien en sí)
            $sql2 = "DELETE FROM inventario_cargos WHERE id_cargo IN ($lista_ids)";
            $pdo->exec($sql2);
            
            $pdo->commit();
            header("Location: inventario_lista.php?msg=eliminados_ok");
        } catch (Exception $e) {
            $pdo->rollBack();
            die("Error al eliminar: " . $e->getMessage());
        }
    } else {
        header("Location: inventario_lista.php");
    }
} else {
    header("Location: inventario_lista.php");
}
?>