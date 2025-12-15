<?php
// Archivo: inventario_guardar.php
session_start();
include 'conexion.php';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    
    // Crear carpeta si no existe
    if (!file_exists('uploads/firmas')) {
        mkdir('uploads/firmas', 0777, true);
    }

    // Función para guardar base64 como imagen
    function guardarFirma($base64, $prefijo) {
        $base64 = str_replace('data:image/png;base64,', '', $base64);
        $base64 = str_replace(' ', '+', $base64);
        $data = base64_decode($base64);
        $nombre = 'uploads/firmas/' . $prefijo . '_' . time() . '_' . uniqid() . '.png';
        file_put_contents($nombre, $data);
        return $nombre;
    }

    // Guardar Firmas
    $ruta_resp = guardarFirma($_POST['base64_responsable'], 'resp');
    $ruta_rel = guardarFirma($_POST['base64_relevador'], 'rel');
    $ruta_jefe = guardarFirma($_POST['base64_jefe'], 'jefe');

    // Guardar Datos
    try {
        $sql = "INSERT INTO inventario_cargos 
                (id_usuario_relevador, elemento, codigo_inventario, servicio_ubicacion, observaciones, 
                 nombre_responsable, nombre_jefe_servicio, firma_responsable, firma_relevador, firma_jefe) 
                VALUES (:id_rel, :elem, :cod, :serv, :obs, :nom_resp, :nom_jefe, :f_resp, :f_rel, :f_jefe)";
        
        $stmt = $pdo->prepare($sql);
        $stmt->execute([
            ':id_rel' => $_SESSION['usuario_id'],
            ':elem' => $_POST['elemento'],
            ':cod' => $_POST['codigo_inventario'],
            ':serv' => $_POST['servicio_ubicacion'],
            ':obs' => $_POST['observaciones'],
            ':nom_resp' => $_POST['nombre_responsable'],
            ':nom_jefe' => $_POST['nombre_jefe_servicio'],
            ':f_resp' => $ruta_resp,
            ':f_rel' => $ruta_rel,
            ':f_jefe' => $ruta_jefe
        ]);

        $id_generado = $pdo->lastInsertId();
        
        // Redirigir al PDF
        header("Location: inventario_pdf.php?id=" . $id_generado);
        exit;

    } catch (PDOException $e) {
        die("Error al guardar: " . $e->getMessage());
    }
}
?>