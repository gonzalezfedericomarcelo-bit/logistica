<?php
// Archivo: actualizar_db_avisos.php
// EJECUTAR UNA VEZ PARA ACTUALIZAR LA ESTRUCTURA
session_start();
include 'conexion.php';

echo "<h2>🛠️ Actualizando Base de Datos para Avisos...</h2>";

try {
    // 1. Crear carpeta de subidas
    $upload_dir = __DIR__ . '/uploads/avisos';
    if (!is_dir($upload_dir)) {
        if (mkdir($upload_dir, 0777, true)) {
            echo "<p>✅ Carpeta <code>uploads/avisos</code> creada.</p>";
        } else {
            echo "<p>❌ Error al crear carpeta <code>uploads/avisos</code>. Verifique permisos.</p>";
        }
    } else {
        echo "<p>✅ Carpeta <code>uploads/avisos</code> ya existe.</p>";
    }

    // 2. Agregar columna imagen_destacada
    // Verificamos si ya existe para no dar error
    $columna_existe = false;
    $stmt = $pdo->query("SHOW COLUMNS FROM avisos LIKE 'imagen_destacada'");
    if ($stmt->fetch()) {
        $columna_existe = true;
        echo "<p>✅ La columna <code>imagen_destacada</code> ya existe.</p>";
    } else {
        $sql = "ALTER TABLE avisos ADD COLUMN imagen_destacada VARCHAR(255) DEFAULT NULL AFTER titulo";
        $pdo->exec($sql);
        echo "<p>✅ Columna <code>imagen_destacada</code> agregada correctamente.</p>";
    }
    
    echo "<hr><h3 style='color:green'>¡Listo! Ya puedes borrar este archivo y usar el sistema.</h3>";
    echo "<a href='avisos.php'>Ir a Avisos</a>";

} catch (PDOException $e) {
    echo "<h3 style='color:red'>Error: " . $e->getMessage() . "</h3>";
}
?>