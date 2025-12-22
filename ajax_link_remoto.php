<?php
// Archivo: ajax_link_remoto.php
session_start();
include 'conexion.php';
date_default_timezone_set('America/Argentina/Buenos_Aires');
header('Content-Type: application/json');

if (!isset($_SESSION['usuario_id'])) { echo json_encode(['status'=>'error', 'msg'=>'Sesión expirada']); exit; }

$accion = $_POST['accion'] ?? '';
$id_cargo = $_POST['id_cargo'] ?? 0;
$rol = $_POST['rol'] ?? ''; // 'responsable' o 'jefe'

try {
    if ($accion == 'generar') {
        // Generar Token Único
        $token = bin2hex(random_bytes(32));
        
        // Invalidar tokens anteriores para este cargo/rol
        $pdo->prepare("UPDATE inventario_firmas_remotas SET estado='anulado' WHERE id_cargo=? AND rol=?")->execute([$id_cargo, $rol]);
        
        // Insertar nuevo
        $stmt = $pdo->prepare("INSERT INTO inventario_firmas_remotas (id_cargo, rol, token, estado, fecha_creacion) VALUES (?, ?, ?, 'pendiente', NOW())");
        $stmt->execute([$id_cargo, $rol, $token]);
        
        // Construir Link
        // DETECTAR HTTPS
        $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off' || $_SERVER['SERVER_PORT'] == 443) ? "https://" : "http://";
        $domain = $_SERVER['HTTP_HOST'];
        $path = dirname($_SERVER['PHP_SELF']);
        $link = $protocol . $domain . $path . "/firmar_cargo_externo.php?t=" . $token;
        
        echo json_encode(['status'=>'success', 'link'=>$link]);
    }
} catch (Exception $e) {
    echo json_encode(['status'=>'error', 'msg'=>$e->getMessage()]);
}
?>