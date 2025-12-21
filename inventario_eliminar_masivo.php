<?php
// Archivo: inventario_eliminar_masivo.php
session_start();
include 'conexion.php';
include 'funciones_permisos.php';

if (!isset($_SESSION['usuario_id']) || !tiene_permiso('acceso_inventario', $pdo)) {
    die("Acceso denegado");
}

$accion = $_POST['accion'] ?? '';

try {
    $pdo->beginTransaction();

    // 1. ELIMINAR SELECCIONADOS (Checkbox)
    if ($accion === 'eliminar_seleccionados' && isset($_POST['ids']) && is_array($_POST['ids'])) {
        $ids = array_map('intval', $_POST['ids']);
        if (!empty($ids)) {
            $lista = implode(',', $ids);
            $pdo->exec("DELETE FROM inventario_valores_dinamicos WHERE id_cargo IN ($lista)");
            $pdo->exec("DELETE FROM inventario_cargos WHERE id_cargo IN ($lista)");
        }
        $msg = "eliminados_ok";
    }

    // 2. VACIAR CATEGORÍA COMPLETA (Botón Rojo)
    elseif ($accion === 'vaciar_categoria' && isset($_POST['id_tipo_bien'])) {
        $id_tipo = $_POST['id_tipo_bien'];
        if ($id_tipo === 'todas') {
            $pdo->exec("DELETE FROM inventario_valores_dinamicos");
            $pdo->exec("DELETE FROM inventario_cargos");
        } else {
            // Primero buscamos los IDs para borrar sus hijos
            $stmt = $pdo->prepare("SELECT id_cargo FROM inventario_cargos WHERE id_tipo_bien = ?");
            $stmt->execute([$id_tipo]);
            $ids = $stmt->fetchAll(PDO::FETCH_COLUMN);
            if (!empty($ids)) {
                $lista = implode(',', $ids);
                $pdo->exec("DELETE FROM inventario_valores_dinamicos WHERE id_cargo IN ($lista)");
                $pdo->exec("DELETE FROM inventario_cargos WHERE id_tipo_bien = '$id_tipo'");
            }
        }
        $msg = "eliminados_ok";
    }

    $pdo->commit();
    header("Location: inventario_lista.php?msg=$msg");

} catch (Exception $e) {
    $pdo->rollBack();
    die("Error: " . $e->getMessage());
}
?>