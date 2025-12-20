<?php
// Archivo: inventario_guardar.php
session_start();
include 'conexion.php';
date_default_timezone_set('America/Argentina/Buenos_Aires');

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $id_usuario = $_SESSION['usuario_id'];
    $elemento = $_POST['elemento'];
    $ubicacion = $_POST['servicio_ubicacion'];
    $responsable = $_POST['nombre_responsable'];
    $jefe = $_POST['nombre_jefe_servicio'];
    $id_destino = $_POST['id_destino'] ?? null;
    $codigo = $_POST['codigo_inventario'] ?? '';
    $obs = $_POST['observaciones'] ?? '';
    $id_estado = $_POST['id_estado'] ?? 1;

    // Procesar Firmas (Base64 a Archivo)
    $firma_resp_path = null;
    $firma_jefe_path = null;

    if (!empty($_POST['base64_responsable'])) {
        $firma_resp_path = 'uploads/firmas/resp_' . time() . '_' . uniqid() . '.png';
        file_put_contents($firma_resp_path, base64_decode(explode(',', $_POST['base64_responsable'])[1]));
    }
    if (!empty($_POST['base64_jefe'])) {
        $firma_jefe_path = 'uploads/firmas/jefe_' . time() . '_' . uniqid() . '.png';
        file_put_contents($firma_jefe_path, base64_decode(explode(',', $_POST['base64_jefe'])[1]));
    }

    // Insertar Cargo Principal
    $sql = "INSERT INTO inventario_cargos (id_usuario_relevador, id_estado_fk, elemento, servicio_ubicacion, destino_principal, nombre_responsable, nombre_jefe_servicio, firma_responsable_path, firma_jefe_path, codigo_patrimonial, observaciones, fecha_creacion) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())";
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$id_usuario, $id_estado, $elemento, $ubicacion, $id_destino, $responsable, $jefe, $firma_resp_path, $firma_jefe_path, $codigo, $obs]);
    $id_cargo = $pdo->lastInsertId();

    // Variables para actualizar datos técnicos de Matafuego en la tabla principal (si aplica)
    $mat_fecha_carga = $_POST['mat_fecha_carga'] ?? null;
    $mat_fecha_ph = $_POST['mat_fecha_ph'] ?? null;
    $mat_numero_grabado = $_POST['mat_numero_grabado'] ?? null;
    $fecha_fabricacion = $_POST['fecha_fabricacion'] ?? null;
    $mat_capacidad = $_POST['mat_capacidad'] ?? null;
    $mat_tipo_carga_id = $_POST['mat_tipo_carga_id'] ?? null;
    $mat_clase_id = $_POST['mat_clase_id'] ?? null;

    // Guardar Campos Dinámicos
    if (isset($_POST['dinamico']) && is_array($_POST['dinamico'])) {
        foreach ($_POST['dinamico'] as $id_campo => $valor) {
            if (!empty($valor)) {
                $pdo->prepare("INSERT INTO inventario_valores_dinamicos (id_cargo, id_campo, valor) VALUES (?, ?, ?)")
                    ->execute([$id_cargo, $id_campo, $valor]);
            }
        }
    }

    // Actualizar datos de Matafuegos si vinieron en el POST (Panel Técnico)
    if ($mat_fecha_carga || $mat_fecha_ph || $mat_numero_grabado || $fecha_fabricacion || $mat_capacidad) {
        $sqlUpd = "UPDATE inventario_cargos SET 
                    mat_fecha_carga = ?, 
                    mat_fecha_ph = ?, 
                    mat_numero_grabado = ?, 
                    fecha_fabricacion = ?,
                    mat_capacidad = ?,
                    mat_tipo_carga_id = ?,
                    mat_clase_id = ?
                   WHERE id_cargo = ?";
        $pdo->prepare($sqlUpd)->execute([
            !empty($mat_fecha_carga) ? $mat_fecha_carga : null,
            !empty($mat_fecha_ph) ? $mat_fecha_ph : null,
            $mat_numero_grabado,
            $fecha_fabricacion,
            $mat_capacidad,
            !empty($mat_tipo_carga_id) ? $mat_tipo_carga_id : null,
            !empty($mat_clase_id) ? $mat_clase_id : null,
            $id_cargo
        ]);
    }

    header("Location: inventario_lista.php?msg=guardado_ok");
    exit();
}
?>