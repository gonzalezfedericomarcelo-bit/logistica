<?php
// Archivo: instalar_historial.php
include 'conexion.php';

try {
    $sql = "CREATE TABLE IF NOT EXISTS historial_movimientos (
        id_movimiento INT(11) AUTO_INCREMENT PRIMARY KEY,
        id_bien INT(11) NOT NULL,
        tipo_movimiento VARCHAR(50) NOT NULL,
        usuario_registro INT(11) NOT NULL,
        fecha_movimiento DATETIME DEFAULT CURRENT_TIMESTAMP,
        ubicacion_anterior VARCHAR(255) DEFAULT NULL,
        ubicacion_nueva VARCHAR(255) DEFAULT NULL,
        observacion_movimiento TEXT DEFAULT NULL
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;";
    
    $pdo->exec($sql);
    echo "<div style='font-family:sans-serif; padding:20px; background:#d4edda; color:#155724; border:1px solid #c3e6cb; border-radius:5px; margin:20px;'>
            <h3>✅ Tabla Historial Instalada Correctamente</h3>
            <p>Ya puedes borrar este archivo y usar el sistema de inventario.</p>
            <a href='inventario_lista.php' style='text-decoration:none; background:#155724; color:white; padding:10px 20px; border-radius:5px;'>Ir al Inventario</a>
          </div>";
} catch (PDOException $e) {
    die("Error creando tabla: " . $e->getMessage());
}
?>