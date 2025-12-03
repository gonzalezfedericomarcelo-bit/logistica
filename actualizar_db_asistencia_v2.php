<?php
// Archivo: actualizar_db_asistencia_v2.php
// EJECUTAR UNA VEZ: Agrega soporte para Turno Tarde y Comisiones
session_start();
include 'conexion.php';

echo "<h2>🚀 Actualizando DB para Asistencia Avanzada...</h2>";

try {
    // Agregar columna tipo_asistencia si no existe
    $columna_existe = false;
    $stmt = $pdo->query("SHOW COLUMNS FROM asistencia_detalles LIKE 'tipo_asistencia'");
    if ($stmt->fetch()) {
        echo "<p>✅ Columna <code>tipo_asistencia</code> ya existe.</p>";
    } else {
        // Por defecto 'presente' para mantener compatibilidad
        $sql = "ALTER TABLE asistencia_detalles ADD COLUMN tipo_asistencia ENUM('presente', 'ausente', 'tarde', 'comision') NOT NULL DEFAULT 'presente' AFTER presente";
        $pdo->exec($sql);
        echo "<p>✅ Columna <code>tipo_asistencia</code> agregada correctamente.</p>";
        
        // Migrar datos viejos: Si presente=0 es 'ausente', si presente=1 es 'presente'
        $pdo->exec("UPDATE asistencia_detalles SET tipo_asistencia = 'ausente' WHERE presente = 0");
        $pdo->exec("UPDATE asistencia_detalles SET tipo_asistencia = 'presente' WHERE presente = 1");
        echo "<p>🔄 Datos históricos migrados.</p>";
    }

    echo "<hr><h3 style='color:green'>¡Actualización completa! Borra este archivo.</h3>";
    echo "<a href='asistencia_tomar.php'>Ir a Tomar Asistencia</a>";

} catch (PDOException $e) {
    echo "<h3 style='color:red'>Error: " . $e->getMessage() . "</h3>";
}
?>