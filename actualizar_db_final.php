<?php
// Archivo: actualizar_db_final.php
// EJECUTAR UNA SOLA VEZ Y LUEGO BORRAR
include 'conexion.php';

try {
    echo "<h2>Iniciando actualización definitiva...</h2>";

    // 1. Crear Tabla de ESTADOS (Para evitar 'Activo'/'Baja' hardcodeado)
    $sql_estados = "CREATE TABLE IF NOT EXISTS inventario_estados (
        id_estado INT AUTO_INCREMENT PRIMARY KEY,
        nombre VARCHAR(50) NOT NULL,
        color_badge VARCHAR(20) DEFAULT 'bg-secondary'
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;";
    $pdo->exec($sql_estados);

    // Insertar estados básicos si la tabla está vacía
    if ($pdo->query("SELECT COUNT(*) FROM inventario_estados")->fetchColumn() == 0) {
        $pdo->exec("INSERT INTO inventario_estados (nombre, color_badge) VALUES 
            ('Activo', 'bg-success'),
            ('En Reparación', 'bg-warning'),
            ('Para Baja', 'bg-danger'),
            ('Vencido', 'bg-dark');");
        echo "<p>✅ Tabla de Estados creada.</p>";
    }

    // 2. Crear Tabla de CONFIGURACIÓN MATAFUEGOS (Para vida útil dinámica)
    $sql_config = "CREATE TABLE IF NOT EXISTS inventario_config_matafuegos (
        id_config INT AUTO_INCREMENT PRIMARY KEY,
        tipo_carga VARCHAR(100) NOT NULL,
        vida_util_anios INT NOT NULL DEFAULT 20
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;";
    $pdo->exec($sql_config);

    // Insertar tipos básicos
    if ($pdo->query("SELECT COUNT(*) FROM inventario_config_matafuegos")->fetchColumn() == 0) {
        $pdo->exec("INSERT INTO inventario_config_matafuegos (tipo_carga, vida_util_anios) VALUES 
            ('Polvo Químico (ABC)', 20),
            ('CO2 (BC)', 30),
            ('Agua (A)', 20),
            ('Haloclean', 20);");
        echo "<p>✅ Tabla de Configuración de Matafuegos creada.</p>";
    }

    // 3. Agregar columnas del EXCEL a la tabla principal
    // Usamos ADD COLUMN IF NOT EXISTS (o try-catch por columna para compatibilidad MySQL vieja)
    $columnas_nuevas = [
        "ADD COLUMN id_estado_fk INT DEFAULT NULL", 
        "ADD COLUMN complementos TEXT DEFAULT NULL",
        "ADD COLUMN archivo_remito VARCHAR(255) DEFAULT NULL",
        "ADD COLUMN archivo_comprobante VARCHAR(255) DEFAULT NULL",
        "ADD COLUMN nombre_tecnico VARCHAR(150) DEFAULT NULL",
        "ADD COLUMN fecha_fabricacion INT DEFAULT NULL", 
        "ADD COLUMN vida_util_limite INT DEFAULT NULL", 
        "ADD COLUMN mat_tipo_carga_id INT DEFAULT NULL", 
        "ADD COLUMN mat_capacidad VARCHAR(50) DEFAULT NULL",
        "ADD COLUMN mat_clase VARCHAR(50) DEFAULT NULL",
        "ADD COLUMN mat_fecha_carga DATE DEFAULT NULL",   
        "ADD COLUMN mat_fecha_ph DATE DEFAULT NULL"      
    ];

    foreach ($columnas_nuevas as $col) {
        try {
            // Intentamos agregar la columna. Si ya existe, dará error y lo capturamos para que siga.
            $pdo->exec("ALTER TABLE inventario_cargos $col");
            echo "<p>🔹 Columna agregada: $col</p>";
        } catch (Exception $e) {
            // Ignoramos el error "Duplicate column name"
        }
    }

    echo "<div style='background:#d4edda; color:#155724; padding:15px; border:1px solid #c3e6cb; margin-top:20px;'>
            <h3>✅ ÉXITO TOTAL</h3>
            <p>La base de datos está lista para usarse con el nuevo formulario de Matafuegos.</p>
            <p><strong>Ya puedes borrar este archivo.</strong></p>
          </div>";

} catch (PDOException $e) {
    die("<h3 style='color:red'>Error Crítico: " . $e->getMessage() . "</h3>");
}
?>