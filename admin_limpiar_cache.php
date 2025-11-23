<?php
// Archivo: admin_limpiar_cache.php
session_start();

if (!isset($_SESSION['usuario_id']) || $_SESSION['usuario_rol'] !== 'admin') {
    echo json_encode(['success' => false]); exit;
}

$tipo = $_POST['tipo'] ?? '';
$mi_id = $_SESSION['usuario_id'];

try {
    if ($tipo === 'frase') {
        // Opción A: Borrar SOLO la caché del administrador (para que tú veas el cambio)
        $f = __DIR__ . "/frase_cache_{$mi_id}.json";
        if (file_exists($f)) unlink($f);
        
        // Opción B (Opcional): Si quieres que se les cambie a TODOS los empleados ahora mismo, descomenta esto:
        // array_map('unlink', glob(__DIR__ . "/frase_cache_*.json"));
    } 
    elseif ($tipo === 'efemeride') {
        // Borrar caché de efemérides (Global)
        $files = glob(__DIR__ . "/efemeride_cache_*.json");
        foreach ($files as $f) { if (file_exists($f)) unlink($f); }
    }

    echo json_encode(['success' => true]);

} catch (Exception $e) {
    echo json_encode(['success' => false]);
}
?>