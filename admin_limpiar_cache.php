<?php
// Archivo: admin_limpiar_cache.php
session_start();

// Solo admin
if (!isset($_SESSION['usuario_id']) || $_SESSION['usuario_rol'] !== 'admin') {
    echo json_encode(['success' => false]); exit;
}

$tipo = $_POST['tipo'] ?? '';

try {
    if ($tipo === 'frase') {
        // Borrar solo caché de frase
        $f = __DIR__ . "/frase_cache.json";
        if (file_exists($f)) unlink($f);
    } 
    elseif ($tipo === 'efemeride') {
        // Borrar solo caché de efemérides
        $files = glob(__DIR__ . "/efemeride_cache_*.json");
        foreach ($files as $f) { if (file_exists($f)) unlink($f); }
    }

    echo json_encode(['success' => true]);

} catch (Exception $e) {
    echo json_encode(['success' => false]);
}
?>