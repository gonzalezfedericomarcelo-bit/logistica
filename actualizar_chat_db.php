<?php
// Script de actualización automática para la tabla de Chat
session_start();
include 'conexion.php';

echo "<h3>Iniciando actualización de base de datos para el Chat...</h3>";

try {
    // 1. Verificar si la tabla 'chat' existe
    $checkTable = $pdo->query("SHOW TABLES LIKE 'chat'");
    if ($checkTable->rowCount() == 0) {
        // Si no existe, la creamos desde cero
        $sql = "CREATE TABLE chat (
            id_chat INT(11) AUTO_INCREMENT PRIMARY KEY,
            id_usuario INT(11) NOT NULL,
            mensaje TEXT,
            fecha DATETIME DEFAULT CURRENT_TIMESTAMP,
            tipo_mensaje VARCHAR(20) DEFAULT 'texto',
            archivo_url VARCHAR(255) DEFAULT NULL,
            archivo_nombre VARCHAR(255) DEFAULT NULL
        )";
        $pdo->exec($sql);
        echo "<div style='color:green'>✅ Tabla 'chat' creada correctamente.</div>";
    } else {
        echo "<div>ℹ️ La tabla 'chat' ya existe. Verificando columnas...</div>";
    }

    // 2. Verificar y agregar columnas faltantes una por una
    $columnas_necesarias = [
        'tipo_mensaje' => "VARCHAR(20) DEFAULT 'texto'",
        'archivo_url' => "VARCHAR(255) DEFAULT NULL",
        'archivo_nombre' => "VARCHAR(255) DEFAULT NULL"
    ];

    foreach ($columnas_necesarias as $columna => $definicion) {
        // Verificar si la columna existe
        $stmt = $pdo->prepare("SHOW COLUMNS FROM chat LIKE :col");
        $stmt->execute([':col' => $columna]);
        
        if ($stmt->rowCount() == 0) {
            // No existe, agregarla
            $sql = "ALTER TABLE chat ADD COLUMN $columna $definicion";
            $pdo->exec($sql);
            echo "<div style='color:green'>✅ Columna '$columna' agregada.</div>";
        } else {
            echo "<div style='color:gray'>ℹ️ Columna '$columna' ya existe.</div>";
        }
    }
    
    echo "<hr><h4>¡Listo! La base de datos está actualizada. Ya puedes usar el nuevo chat.</h4>";
    echo "<a href='chat.php'>Ir al Chat</a>";

} catch (PDOException $e) {
    echo "<div style='color:red'>❌ Error: " . $e->getMessage() . "</div>";
}
?>