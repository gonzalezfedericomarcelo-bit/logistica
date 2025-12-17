<?php
// Archivo: actualizar_estructura_destinos.php
include 'conexion.php';

try {
    // 1. Agregar columna 'destino_principal' al inventario real
    $pdo->exec("ALTER TABLE inventario_cargos ADD COLUMN destino_principal VARCHAR(255) AFTER elemento");
    echo "Columna 'destino_principal' agregada a inventario_cargos.<br>";

    // 2. Agregar columna 'nuevo_destino_nombre' a la tabla temporal de transferencias
    $pdo->exec("ALTER TABLE inventario_transferencias_pendientes ADD COLUMN nuevo_destino_nombre VARCHAR(255) AFTER nuevo_destino_id");
    echo "Columna 'nuevo_destino_nombre' agregada a transferencias pendientes.<br>";

    echo "<h3>Estructura actualizada correctamente. Podes borrar este archivo.</h3>";

} catch (Exception $e) {
    echo "Nota: " . $e->getMessage();
}
?>