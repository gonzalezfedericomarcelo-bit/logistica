<?php
// Archivo: actualizar_db_v3.php
include 'conexion.php';

try {
    echo "<h2>Preparando Estados de Vencimiento...</h2>";

    // Asegurar que existan los estados automáticos
    $estados_necesarios = [
        ['Carga Vencida', 'bg-danger'],
        ['Prueba Vencida', 'bg-dark'],
        ['En Mantenimiento', 'bg-warning']
    ];

    foreach ($estados_necesarios as $est) {
        $nombre = $est[0];
        $color = $est[1];
        
        // Verificar si existe
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM inventario_estados WHERE nombre = ?");
        $stmt->execute([$nombre]);
        if ($stmt->fetchColumn() == 0) {
            $stmtInsert = $pdo->prepare("INSERT INTO inventario_estados (nombre, color_badge) VALUES (?, ?)");
            $stmtInsert->execute([$nombre, $color]);
            echo "<p>✅ Estado creado: <strong>$nombre</strong></p>";
        } else {
            echo "<p>🔹 Estado ya existe: $nombre</p>";
        }
    }
    
    echo "<div style='background:#d4edda; padding:20px; margin-top:20px;'><h3>✅ Listo. Borra este archivo.</h3></div>";

} catch (PDOException $e) {
    die("Error: " . $e->getMessage());
}
?>