<?php
// Archivo: inventario_guardar.php
session_start();
include 'conexion.php';
include 'funciones_permisos.php'; // Agregado para evitar error 500 por función no definida

if (!isset($_SESSION['usuario_id']) || !tiene_permiso('inventario_nuevo', $pdo)) {
    header("Location: inventario_lista.php"); exit();
}

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    
    // Directorios
    if (!file_exists('uploads/firmas')) mkdir('uploads/firmas', 0777, true);
    
    // FIRMAS
    function guardarFirma($base64, $prefijo) {
        if (empty($base64)) return null; // Validación básica para evitar error en replace
        $base64 = str_replace(['data:image/png;base64,',' '], ['','+'], $base64);
        $data = base64_decode($base64);
        $nombre = 'uploads/firmas/' . $prefijo . '_' . time() . '_' . uniqid() . '.png';
        file_put_contents($nombre, $data);
        return $nombre;
    }
    
    $ruta_resp = isset($_POST['base64_responsable']) ? guardarFirma($_POST['base64_responsable'], 'resp') : null;
    $ruta_jefe = isset($_POST['base64_jefe']) ? guardarFirma($_POST['base64_jefe'], 'jefe') : null;
    
    // Firma Relevador (Copia perfil)
    $id_rel = $_SESSION['usuario_id'];
    $ruta_rel = null;
    $f_perfil = $pdo->query("SELECT firma_imagen_path FROM usuarios WHERE id_usuario=$id_rel")->fetchColumn();
    if($f_perfil && file_exists('uploads/firmas/'.$f_perfil)) {
        $ruta_rel = 'uploads/firmas/rel_'.time().uniqid().'.png';
        copy('uploads/firmas/'.$f_perfil, $ruta_rel);
    }

    try {
        $sql = "INSERT INTO inventario_cargos (
            id_usuario_relevador, id_estado_fk, elemento, codigo_inventario, servicio_ubicacion, 
            observaciones, complementos, 
            mat_tipo_carga_id, mat_capacidad, mat_clase_id, mat_numero_grabado, mat_fecha_carga, mat_fecha_ph, fecha_fabricacion, vida_util_limite,
            nombre_responsable, nombre_jefe_servicio, firma_responsable, firma_relevador, firma_jefe, fecha_creacion
        ) VALUES (
            :id_rel, :id_est, :elem, :cod, :serv, 
            :obs, :comp, 
            :mtipo, :mcap, :mclase, :mgrabado, :mvc, :mvph, :mfab, :mvida,
            :nresp, :njefe, :fresp, :frel, :fjefe, NOW()
        )";

        $stmt = $pdo->prepare($sql);
        $stmt->execute([
            ':id_rel' => $id_rel,
            ':id_est' => $_POST['id_estado'] ?? null, // Previene error si no se envía
            ':elem' => $_POST['elemento'],
            ':cod' => $_POST['codigo_inventario'],
            ':serv' => $_POST['servicio_ubicacion'],
            ':obs' => $_POST['observaciones'],
            ':comp' => $_POST['complementos'] ?? null,
            ':mtipo' => $_POST['mat_tipo_carga_id'] ?? null,
            ':mcap' => $_POST['mat_capacidad'] ?? null,
            ':mclase' => $_POST['mat_clase_id'] ?? null,
            ':mgrabado' => $_POST['mat_numero_grabado'] ?? null,
            ':mvc' => !empty($_POST['mat_fecha_carga']) ? $_POST['mat_fecha_carga'] : null,
            ':mvph' => !empty($_POST['mat_fecha_ph']) ? $_POST['mat_fecha_ph'] : null,
            ':mfab' => !empty($_POST['fecha_fabricacion']) ? $_POST['fecha_fabricacion'] : null,
            ':mvida' => !empty($_POST['vida_util_limite']) ? $_POST['vida_util_limite'] : null,
            ':nresp' => $_POST['nombre_responsable'],
            ':njefe' => $_POST['nombre_jefe_servicio'],
            ':fresp' => $ruta_resp,
            ':frel' => $ruta_rel,
            ':fjefe' => $ruta_jefe
        ]);

        $id = $pdo->lastInsertId();
        header("Location: inventario_pdf.php?id=$id");

    } catch (PDOException $e) {
        die("Error DB: " . $e->getMessage());
    }
}
?>