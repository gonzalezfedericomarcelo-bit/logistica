<?php
session_start();
include 'conexion.php';

$accion = $_POST['accion'] ?? '';

if ($accion == 'guardar') {
    $id_campo = $_POST['id_campo'];
    $valor = trim($_POST['valor']);
    if (!empty($valor)) {
        $stmt = $pdo->prepare("INSERT INTO inventario_campos_opciones (id_campo, valor) VALUES (?, ?)");
        $stmt->execute([$id_campo, $valor]);
        echo json_encode(['status' => 'ok', 'id' => $pdo->lastInsertId(), 'valor' => $valor]);
    }
}

if ($accion == 'eliminar') {
    $id_opcion = $_POST['id_opcion'];
    $stmt = $pdo->prepare("DELETE FROM inventario_campos_opciones WHERE id_opcion = ?");
    $stmt->execute([$id_opcion]);
    echo json_encode(['status' => 'ok']);
}
if ($accion == 'editar') {
    $id_opcion = $_POST['id_opcion'];
    $valor = trim($_POST['valor']);
    if (!empty($valor)) {
        $stmt = $pdo->prepare("UPDATE inventario_campos_opciones SET valor = ? WHERE id_opcion = ?");
        $stmt->execute([$valor, $id_opcion]);
        echo json_encode(['status' => 'ok']);
    }
}

if ($accion == 'listar') {
    $id_campo = $_POST['id_campo'];
    $stmt = $pdo->prepare("SELECT * FROM inventario_campos_opciones WHERE id_campo = ? ORDER BY valor ASC");
    $stmt->execute([$id_campo]);
    echo json_encode($stmt->fetchAll(PDO::FETCH_ASSOC));
}
?>