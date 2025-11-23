<?php
// Archivo: admin_toggle_navidad.php
session_start();
include 'conexion.php';

// Solo admin puede tocar el botón
if (!isset($_SESSION['usuario_id']) || $_SESSION['usuario_rol'] !== 'admin') {
    echo json_encode(['success' => false]); exit();
}

// Alternar valor (Si es 1 pone 0, si es 0 pone 1)
try {
    $stmt = $pdo->query("SELECT valor FROM configuracion_sistema WHERE clave = 'modo_navidad'");
    $actual = $stmt->fetchColumn();
    
    $nuevo_valor = ($actual == '1') ? '0' : '1';
    
    $stmt_upd = $pdo->prepare("UPDATE configuracion_sistema SET valor = :val WHERE clave = 'modo_navidad'");
    $stmt_upd->execute([':val' => $nuevo_valor]);
    
    echo json_encode(['success' => true]);
} catch (Exception $e) {
    echo json_encode(['success' => false]);
}
?>