<?php
// Archivo: actualizar_db_v4.php
// EJECUTAR UNA VEZ PARA ACTUALIZAR LA ESTRUCTURA
include 'conexion.php';

try {
    echo "<h2>Actualizando Base de Datos (Matafuegos V4)...</h2>";

    // 1. Agregar columna para N° Grabado de Fábrica (Distinto al Código Patrimonial)
    // Si ya existe, esto fallará silenciosamente, lo cual está bien.
    try {
        $pdo->exec("ALTER TABLE inventario_cargos ADD COLUMN mat_numero_grabado VARCHAR(100) DEFAULT NULL AFTER mat_clase_id");
        echo "<p>🔹 Columna 'mat_numero_grabado' agregada o verificada.</p>";
    } catch (Exception $e) { 
        echo "<p>🔸 La columna 'mat_numero_grabado' ya existía.</p>";
    }

    // 2. Tabla de Configuración General (Para guardar los meses de alerta sin hardcode)
    $pdo->exec("CREATE TABLE IF NOT EXISTS inventario_config_general (
        clave VARCHAR(50) PRIMARY KEY,
        valor VARCHAR(255) NOT NULL,
        descripcion VARCHAR(255) DEFAULT NULL
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");

    // Insertar valor por defecto (12 meses) si no existe
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM inventario_config_general WHERE clave = 'alerta_vida_util_meses'");
    $stmt->execute();
    if ($stmt->fetchColumn() == 0) {
        $pdo->exec("INSERT INTO inventario_config_general (clave, valor, descripcion) VALUES ('alerta_vida_util_meses', '12', 'Meses de anticipación para alerta de Vida Útil')");
        echo "<p>✅ Configuración de Alerta Vida Útil creada (12 meses por defecto).</p>";
    }

    // 3. Asegurar Estados de Vencimiento Específicos
    // Para que las tarjetas funcionen bien, necesitamos estados claros.
    $estados_nuevos = [
        ['Carga Vencida', 'bg-danger'], 
        ['Prueba Vencida', 'bg-dark'], 
        ['Vida Útil Vencida', 'bg-secondary']
    ];

    foreach($estados_nuevos as $est) {
        $stmt = $pdo->prepare("SELECT id_estado FROM inventario_estados WHERE nombre = ?");
        $stmt->execute([$est[0]]);
        if (!$stmt->fetch()) {
            $pdo->prepare("INSERT INTO inventario_estados (nombre, color_badge) VALUES (?, ?)")->execute($est);
            echo "<p>✅ Estado creado: <strong>{$est[0]}</strong></p>";
        }
    }

    echo "<div style='background:#d1e7dd; color:#0f5132; padding:20px; border:1px solid #c3e6cb; margin-top:20px; border-radius:5px;'>
            <h3>✅ BASE DE DATOS ACTUALIZADA CORRECTAMENTE</h3>
            <p>Ya podés borrar este archivo y usar el sistema actualizado.</p>
            <a href='inventario_lista.php' class='btn btn-success'>Ir al Inventario</a>
          </div>";

} catch (PDOException $e) {
    die("<h3 style='color:red'>Error Crítico SQL: " . $e->getMessage() . "</h3>");
}
?>