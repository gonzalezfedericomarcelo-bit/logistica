<?php
// Archivo: fix_permisos_transferencia.php
session_start();
include 'conexion.php';

if (!isset($_SESSION['usuario_id'])) die("Debes ser admin.");

echo "<h3>Corrigiendo Permisos...</h3>";

try {
    // 1. Crear el permiso ESPECÍFICO para Transferencias
    $sql = "INSERT INTO permisos (clave_permiso, nombre_mostrar) 
            VALUES ('inventario_ver_transferencias', 'Inventario: Ver Historial de Transferencias')
            ON DUPLICATE KEY UPDATE nombre_mostrar = VALUES(nombre_mostrar)";
    $pdo->exec($sql);
    echo "✅ Permiso 'Inventario: Ver Historial de Transferencias' CREADO.<br>";

    // 2. Renombrar el historial general para que no confunda
    $sql2 = "UPDATE permisos SET nombre_mostrar = 'Inventario: Ver Historial General (Movimientos)' 
             WHERE clave_permiso = 'inventario_historial'";
    $pdo->exec($sql2);
    echo "✅ Permiso general renombrado a 'Inventario: Ver Historial General (Movimientos)'.<br>";

    // 3. Asignar el nuevo permiso al admin para que no te quedes afuera
    $pdo->exec("INSERT IGNORE INTO rol_permiso (nombre_rol, clave_permiso) VALUES ('admin', 'inventario_ver_transferencias')");
    echo "✅ Permiso asignado al Admin.<br>";

    echo "<hr><h4 style='color:green'>LISTO. Ahora andá a Admin Roles y vas a ver el interruptor nuevo.</h4>";
    echo "<a href='admin_roles.php'>Ir a Admin Roles</a>";

} catch (PDOException $e) {
    echo "Error: " . $e->getMessage();
}
?>