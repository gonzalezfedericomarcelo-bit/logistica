<?php
// Archivo: reparar_sistema.php
// EJECUTAR UNA VEZ PARA ARREGLAR ESTADOS FALTANTES
include 'conexion.php';

try {
    echo "<h2>🔧 Reparando Sistema...</h2>";

    // 1. Asegurar Tabla Estados
    $pdo->exec("CREATE TABLE IF NOT EXISTS inventario_estados (
        id_estado INT AUTO_INCREMENT PRIMARY KEY,
        nombre VARCHAR(50) NOT NULL,
        color_badge VARCHAR(20) DEFAULT 'bg-secondary'
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");

    // 2. Insertar Estados Críticos (Si faltan)
    $estados = [
        ['Activo', 'bg-success'],
        ['En Reparación', 'bg-warning'],
        ['Para Baja', 'bg-dark'],
        ['Carga Vencida', 'bg-danger'],        // ESTE FALTABA SEGURO
        ['Prueba Vencida', 'bg-danger'],       // ESTE TAMBIEN
        ['Vida Útil Vencida', 'bg-secondary']  // Y ESTE
    ];

    foreach ($estados as $est) {
        $stmt = $pdo->prepare("SELECT id_estado FROM inventario_estados WHERE nombre = ?");
        $stmt->execute([$est[0]]);
        if (!$stmt->fetch()) {
            $pdo->prepare("INSERT INTO inventario_estados (nombre, color_badge) VALUES (?, ?)")->execute($est);
            echo "<p>✅ Estado creado: <strong>{$est[0]}</strong></p>";
        } else {
            echo "<p>🔹 Estado OK: {$est[0]}</p>";
        }
    }

    echo "<div style='background:#d4edda; padding:20px; margin-top:20px; border:1px solid green;'>
            <h3>✅ REPARACIÓN COMPLETADA</h3>
            <p>Ahora los estados de vencimiento existen. La lista ya no pondrá 'Sin Asignar' por error.</p>
            <a href='inventario_lista.php' class='btn btn-success'>Ir al Inventario</a>
          </div>";

} catch (Exception $e) {
    die("Error: " . $e->getMessage());
}
?>