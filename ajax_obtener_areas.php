<?php
// Archivo: ajax_obtener_areas.php
require 'conexion.php';

// Verificamos que venga el ID
if (isset($_GET['id_destino'])) {
    $id_destino = (int)$_GET['id_destino'];
    
    try {
        // Buscar áreas que pertenezcan a ese destino
        $stmt = $pdo->prepare("SELECT id_area, nombre FROM areas WHERE id_destino = :id ORDER BY nombre ASC");
        $stmt->execute([':id' => $id_destino]);
        $areas = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Devolver JSON
        header('Content-Type: application/json');
        echo json_encode($areas);
    } catch (PDOException $e) {
        echo json_encode([]);
    }
}
?>