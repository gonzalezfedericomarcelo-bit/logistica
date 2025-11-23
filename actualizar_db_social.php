<?php
// Archivo: actualizar_db_blog.php
// EJECUTAR UNA VEZ: Actualiza estructura para Blog, Categorías y Respuestas
session_start();
include 'conexion.php';

echo "<h2>🚀 Actualizando DB para Blog Social v2...</h2>";

try {
    // 1. Agregar columna CATEGORIA a avisos
    $columna_existe = false;
    $stmt = $pdo->query("SHOW COLUMNS FROM avisos LIKE 'categoria'");
    if ($stmt->fetch()) {
        echo "<p>✅ Columna <code>categoria</code> ya existe en avisos.</p>";
    } else {
        $sql = "ALTER TABLE avisos ADD COLUMN categoria VARCHAR(50) NOT NULL DEFAULT 'General' AFTER titulo";
        $pdo->exec($sql);
        echo "<p>✅ Columna <code>categoria</code> agregada.</p>";
    }

    // 2. Agregar columna ID_PADRE a comentarios (para respuestas)
    $columna_existe = false;
    $stmt = $pdo->query("SHOW COLUMNS FROM avisos_comentarios LIKE 'id_padre'");
    if ($stmt->fetch()) {
        echo "<p>✅ Columna <code>id_padre</code> ya existe en comentarios.</p>";
    } else {
        $sql = "ALTER TABLE avisos_comentarios ADD COLUMN id_padre INT DEFAULT NULL AFTER id_aviso";
        $pdo->exec($sql);
        // Agregar FK para integridad (opcional pero recomendado)
        $pdo->exec("ALTER TABLE avisos_comentarios ADD CONSTRAINT fk_comentario_padre FOREIGN KEY (id_padre) REFERENCES avisos_comentarios(id_comentario) ON DELETE CASCADE");
        echo "<p>✅ Columna <code>id_padre</code> agregada para hilos de respuesta.</p>";
    }

    echo "<hr><h3 style='color:green'>¡Actualización completa! Borra este archivo.</h3>";
    echo "<a href='avisos.php'>Ir al Blog</a>";

} catch (PDOException $e) {
    echo "<h3 style='color:red'>Error: " . $e->getMessage() . "</h3>";
}
?>