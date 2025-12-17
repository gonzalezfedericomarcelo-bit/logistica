<?php
// Archivo: ajax_obtener_areas.php
include 'conexion.php';

// Validar que recibimos el ID del destino padre
if (isset($_GET['id_destino'])) {
    $id = $_GET['id_destino'];
    
    // Buscamos en la tabla 'areas' las que pertenezcan a este destino
    // IMPORTANTE: Asumo que tu tabla se llama 'areas' y la FK es 'id_destino'
    // Si tu tabla tiene otro nombre (ej: 'destinos_areas'), avísame.
    try {
        $stmt = $pdo->prepare("SELECT * FROM areas WHERE id_destino = ? ORDER BY nombre ASC");
        $stmt->execute([$id]);
        $areas = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Devolvemos JSON limpio
        echo json_encode($areas);
    } catch (Exception $e) {
        echo json_encode([]);
    }
}
?>