<?php
// Archivo: actualizar_db_bitacora.php
session_start();
include 'conexion.php';

echo "<h2>🛠️ Agregando Bitácora de Movimientos...</h2>";

try {
    $columna_existe = false;
    $stmt = $pdo->query("SHOW COLUMNS FROM asistencia_partes LIKE 'bitacora'");
    if ($stmt->fetch()) {
        echo "<p>✅ La columna <code>bitacora</code> ya existe.</p>";
    } else {
        $sql = "ALTER TABLE asistencia_partes ADD COLUMN bitacora TEXT DEFAULT NULL AFTER observaciones_generales";
        $pdo->exec($sql);
        echo "<p>✅ Columna <code>bitacora</code> agregada con éxito.</p>";
    }
    
    echo "<hr><h3 style='color:green'>¡Listo! Borra este archivo.</h3>";
    echo "<a href='asistencia_listado_general.php'>Ir al Listado</a>";

} catch (PDOException $e) {
    echo "<h3 style='color:red'>Error: " . $e->getMessage() . "</h3>";
}
?>