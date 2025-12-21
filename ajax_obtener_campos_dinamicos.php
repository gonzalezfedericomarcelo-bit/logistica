<?php
include 'conexion.php';
if (isset($_POST['id_tipo_bien'])) {
    // 1. Obtenemos los campos
    $stmt = $pdo->prepare("SELECT * FROM inventario_campos_dinamicos WHERE id_tipo_bien = ? ORDER BY orden ASC");
    $stmt->execute([$_POST['id_tipo_bien']]);
    $campos = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // 2. Para cada campo, buscamos si tiene opciones predefinidas
    foreach ($campos as &$campo) {
        $stmtOpt = $pdo->prepare("SELECT valor FROM inventario_campos_opciones WHERE id_campo = ? ORDER BY valor ASC");
        $stmtOpt->execute([$campo['id_campo']]);
        // Guardamos las opciones en el mismo array del campo
        $campo['opciones'] = $stmtOpt->fetchAll(PDO::FETCH_COLUMN);
    }
    
    echo json_encode($campos);
}
?>