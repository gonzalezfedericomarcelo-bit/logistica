<?php
include 'conexion.php';
if (isset($_POST['id_tipo_bien'])) {
    $stmt = $pdo->prepare("SELECT * FROM inventario_campos_dinamicos WHERE id_tipo_bien = ? ORDER BY orden ASC");
    $stmt->execute([$_POST['id_tipo_bien']]);
    echo json_encode($stmt->fetchAll(PDO::FETCH_ASSOC));
}
?>