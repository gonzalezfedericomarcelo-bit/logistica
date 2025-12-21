<?php
// Archivo: inventario_guardar.php (SOPORTE N° IOSFA)
error_reporting(E_ALL); ini_set('display_errors', 1);
session_start();
include 'conexion.php';
date_default_timezone_set('America/Argentina/Buenos_Aires');

if ($_SERVER['REQUEST_METHOD'] != 'POST' || !isset($_SESSION['usuario_id'])) { header("Location: dashboard.php"); exit(); }

try {
    $id_usuario = $_SESSION['usuario_id'];
    $accion     = $_POST['accion'] ?? 'crear';
    $id_cargo   = !empty($_POST['id_cargo']) ? (int)$_POST['id_cargo'] : null;

    // DATOS
    $elemento     = $_POST['elemento'] ?? 'SIN NOMBRE';
    $ubicacion    = !empty($_POST['servicio_ubicacion']) ? $_POST['servicio_ubicacion'] : 'General';
    $responsable  = $_POST['nombre_responsable'] ?? '';
    $jefe         = $_POST['nombre_jefe_servicio'] ?? '';
    $codigo       = $_POST['codigo_inventario'] ?? '';
    $n_iosfa      = $_POST['n_iosfa'] ?? null; // NUEVO CAMPO
    $obs          = $_POST['observaciones'] ?? '';
    $id_destino   = !empty($_POST['id_destino']) ? $_POST['id_destino'] : null;
    $id_estado    = !empty($_POST['id_estado']) ? $_POST['id_estado'] : 1;
    $id_tipo_bien = !empty($_POST['id_tipo_bien_seleccionado']) ? $_POST['id_tipo_bien_seleccionado'] : null;

    // Firmas (Resumido para no ocupar espacio, se mantiene igual)
    $ruta_firmas = 'uploads/firmas/';
    if (!file_exists($ruta_firmas)) mkdir($ruta_firmas, 0777, true);
    $path_resp = null; $path_jefe = null;
    if (!empty($_POST['base64_responsable'])) { $d=base64_decode(explode(',',$_POST['base64_responsable'])[1]); $path_resp=$ruta_firmas.'resp_'.time().uniqid().'.png'; file_put_contents($path_resp,$d); }
    if (!empty($_POST['base64_jefe'])) { $d=base64_decode(explode(',',$_POST['base64_jefe'])[1]); $path_jefe=$ruta_firmas.'jefe_'.time().uniqid().'.png'; file_put_contents($path_jefe,$d); }

    if ($accion === 'editar' && $id_cargo) {
        // UPDATE
        $sql = "UPDATE inventario_cargos SET 
                id_estado_fk=?, elemento=?, servicio_ubicacion=?, destino_principal=?, 
                nombre_responsable=?, nombre_jefe_servicio=?, codigo_patrimonial=?, 
                n_iosfa=?, observaciones=?"; // Agregado n_iosfa
        
        $params = [$id_estado, $elemento, $ubicacion, $id_destino, $responsable, $jefe, $codigo, $n_iosfa, $obs];

        if ($path_resp) { $sql .= ", firma_responsable_path=?"; $params[] = $path_resp; }
        if ($path_jefe) { $sql .= ", firma_jefe_path=?"; $params[] = $path_jefe; }

        $sql .= " WHERE id_cargo=?";
        $params[] = $id_cargo;
        $pdo->prepare($sql)->execute($params);

    } else {
        // INSERT
        $sql = "INSERT INTO inventario_cargos (
                id_usuario_relevador, id_tipo_bien, id_estado_fk, elemento, servicio_ubicacion, 
                destino_principal, nombre_responsable, nombre_jefe_servicio, firma_responsable_path, 
                firma_jefe_path, codigo_patrimonial, n_iosfa, observaciones, fecha_creacion
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())"; // Agregado n_iosfa
        
        $pdo->prepare($sql)->execute([
            $id_usuario, $id_tipo_bien, $id_estado, $elemento, $ubicacion, $id_destino, 
            $responsable, $jefe, $path_resp, $path_jefe, $codigo, $n_iosfa, $obs
        ]);
        $id_cargo = $pdo->lastInsertId();
    }

    // Datos Técnicos (Matafuegos) y Dinámicos (Se mantienen igual)
    // ... [Bloque de matafuegos y dinámicos se mantiene igual al anterior] ...
    // Solo por completitud del archivo, si copias y pegas, asegúrate de mantener esa parte o avísame si la necesitas completa.
    // Para simplificar, aquí está el bloque de matafuegos básico:
    if (!empty($_POST['mat_capacidad'])) {
        $pdo->prepare("UPDATE inventario_cargos SET mat_capacidad=? WHERE id_cargo=?")->execute([$_POST['mat_capacidad'], $id_cargo]);
    }
    // ...

    header("Location: inventario_lista.php?msg=guardado_ok");
    exit();
} catch (Exception $e) { die("Error: " . $e->getMessage()); }
?>