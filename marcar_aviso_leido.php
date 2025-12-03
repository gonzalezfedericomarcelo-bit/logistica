<?php
session_start();
include 'conexion.php';
if (isset($_POST['id_aviso']) && isset($_SESSION['usuario_id'])) {
    $id_aviso = (int)$_POST['id_aviso'];
    $id_user = $_SESSION['usuario_id'];
    
    // Insertar ignorando si ya existe (IGNORE)
    $sql = "INSERT IGNORE INTO avisos_lecturas (id_usuario, id_aviso) VALUES (:u, :a)";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([':u' => $id_user, ':a' => $id_aviso]);
}