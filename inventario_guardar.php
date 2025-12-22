<?php
// Archivo: inventario_guardar.php (CON LÓGICA DE SISTEMA/REMOTO)
error_reporting(E_ALL); ini_set('display_errors', 1);
session_start();
include 'conexion.php';
date_default_timezone_set('America/Argentina/Buenos_Aires');

if ($_SERVER['REQUEST_METHOD'] != 'POST' || !isset($_SESSION['usuario_id'])) { 
    header("Location: dashboard.php"); exit(); 
}

try {
    $id_usuario = $_SESSION['usuario_id'];
    $accion     = $_POST['accion'] ?? 'crear';
    $id_cargo   = !empty($_POST['id_cargo']) ? (int)$_POST['id_cargo'] : null;

    // --- PROCESAR RESPONSABLE ---
    $modo_resp = $_POST['modo_responsable'] ?? 'manual';
    $nombre_resp = $_POST['nombre_responsable'] ?? '';
    $id_resp = null;

    if ($modo_resp === 'sistema' && !empty($_POST['id_responsable_sistema'])) {
        $id_resp = $_POST['id_responsable_sistema'];
        $nombre_resp = $pdo->query("SELECT nombre_completo FROM usuarios WHERE id_usuario = $id_resp")->fetchColumn();
    } elseif ($modo_resp === 'remoto') {
        $nombre_resp = 'PENDIENTE FIRMA REMOTA';
    }

    // --- PROCESAR JEFE ---
    $modo_jefe = $_POST['modo_jefe'] ?? 'manual';
    $nombre_jefe = $_POST['nombre_jefe_servicio'] ?? '';
    $id_jefe = null;

    if ($modo_jefe === 'sistema' && !empty($_POST['id_jefe_sistema'])) {
        $id_jefe = $_POST['id_jefe_sistema'];
        $nombre_jefe = $pdo->query("SELECT nombre_completo FROM usuarios WHERE id_usuario = $id_jefe")->fetchColumn();
    } elseif ($modo_jefe === 'remoto') {
        $nombre_jefe = 'PENDIENTE FIRMA REMOTA';
    }

    // Datos generales
    $elemento     = $_POST['elemento'] ?? 'SIN NOMBRE';
    $ubicacion    = !empty($_POST['servicio_ubicacion']) ? $_POST['servicio_ubicacion'] : 'General';
    $codigo       = $_POST['codigo_inventario'] ?? '';
    $n_iosfa      = $_POST['n_iosfa'] ?? null;
    $obs          = $_POST['observaciones'] ?? '';
    $id_destino   = !empty($_POST['id_destino']) ? $_POST['id_destino'] : null;
    $id_estado    = !empty($_POST['id_estado']) ? $_POST['id_estado'] : 1;
    $id_tipo_bien = !empty($_POST['id_tipo_bien_seleccionado']) ? $_POST['id_tipo_bien_seleccionado'] : null;

    // Guardado de firmas manuales
    $ruta_firmas = 'uploads/firmas/';
    if (!file_exists($ruta_firmas)) mkdir($ruta_firmas, 0777, true);
    
    $path_resp = null; $path_jefe = null;
    if (!empty($_POST['base64_responsable']) && $modo_resp === 'manual') { 
        $parts = explode(',', $_POST['base64_responsable']);
        $d = base64_decode(count($parts) > 1 ? $parts[1] : $parts[0]);
        $path_resp = $ruta_firmas . 'resp_' . time() . uniqid() . '.png'; file_put_contents($path_resp, $d); 
    }
    if (!empty($_POST['base64_jefe']) && $modo_jefe === 'manual') { 
        $parts = explode(',', $_POST['base64_jefe']);
        $d = base64_decode(count($parts) > 1 ? $parts[1] : $parts[0]);
        $path_jefe = $ruta_firmas . 'jefe_' . time() . uniqid() . '.png'; file_put_contents($path_jefe, $d); 
    }

    // INSERT / UPDATE
    if ($accion === 'editar' && $id_cargo) {
        $sql = "UPDATE inventario_cargos SET id_estado_fk=?, elemento=?, servicio_ubicacion=?, destino_principal=?, nombre_responsable=?, nombre_jefe_servicio=?, codigo_patrimonial=?, n_iosfa=?, observaciones=?, id_responsable=?, id_jefe=?";
        $params = [$id_estado, $elemento, $ubicacion, $id_destino, $nombre_resp, $nombre_jefe, $codigo, $n_iosfa, $obs, $id_resp, $id_jefe];
        if ($path_resp) { $sql .= ", firma_responsable_path=?"; $params[] = $path_resp; }
        if ($path_jefe) { $sql .= ", firma_jefe_path=?"; $params[] = $path_jefe; }
        $sql .= " WHERE id_cargo=?"; $params[] = $id_cargo;
        $pdo->prepare($sql)->execute($params);
    } else {
        $sql = "INSERT INTO inventario_cargos (id_usuario_relevador, id_tipo_bien, id_estado_fk, elemento, servicio_ubicacion, destino_principal, nombre_responsable, nombre_jefe_servicio, firma_responsable_path, firma_jefe_path, codigo_patrimonial, n_iosfa, observaciones, id_responsable, id_jefe, fecha_creacion) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())";
        $pdo->prepare($sql)->execute([$id_usuario, $id_tipo_bien, $id_estado, $elemento, $ubicacion, $id_destino, $nombre_resp, $nombre_jefe, $path_resp, $path_jefe, $codigo, $n_iosfa, $obs, $id_resp, $id_jefe]);
        $id_cargo = $pdo->lastInsertId();
    }

    // Campos dinámicos
    if (isset($_POST['dinamico']) && is_array($_POST['dinamico'])) {
        foreach ($_POST['dinamico'] as $id_campo => $valor) {
            $valor = trim($valor);
            $stmtCheck = $pdo->prepare("SELECT id_valor FROM inventario_valores_dinamicos WHERE id_cargo = ? AND id_campo = ?");
            $stmtCheck->execute([$id_cargo, $id_campo]);
            if ($stmtCheck->fetchColumn()) { $pdo->prepare("UPDATE inventario_valores_dinamicos SET valor = ? WHERE id_cargo = ? AND id_campo = ?")->execute([$valor, $id_cargo, $id_campo]); } 
            else { $pdo->prepare("INSERT INTO inventario_valores_dinamicos (id_cargo, id_campo, valor) VALUES (?, ?, ?)")->execute([$id_cargo, $id_campo, $valor]); }
        }
    }
    
    // Matafuegos
    if (!empty($_POST['mat_capacidad']) || !empty($_POST['mat_numero_grabado'])) {
        $sqlMat = "UPDATE inventario_cargos SET mat_tipo_carga_id=?, mat_capacidad=?, mat_clase_id=?, fecha_fabricacion=?, mat_fecha_carga=?, mat_fecha_ph=?, mat_numero_grabado=? WHERE id_cargo=?";
        $pdo->prepare($sqlMat)->execute([$_POST['mat_tipo_carga_id']??null, $_POST['mat_capacidad']??null, $_POST['mat_clase_id']??null, $_POST['fecha_fabricacion']??null, $_POST['mat_fecha_carga']??null, $_POST['mat_fecha_ph']??null, $_POST['mat_numero_grabado']??null, $id_cargo]);
    }

    // Notificación Sistema
    if ($id_resp && $modo_resp === 'sistema') {
        $pdo->prepare("INSERT INTO notificaciones (id_usuario_destino, tipo, mensaje, url, fecha_creacion) VALUES (?, 'inventario_firma', ?, ?, NOW())")->execute([$id_resp, "Asignación de: $elemento", "inventario_editar.php?id=$id_cargo"]);
    }
    if ($id_jefe && $modo_jefe === 'sistema') {
        $pdo->prepare("INSERT INTO notificaciones (id_usuario_destino, tipo, mensaje, url, fecha_creacion) VALUES (?, 'inventario_firma', ?, ?, NOW())")->execute([$id_jefe, "Jefatura de: $elemento", "inventario_editar.php?id=$id_cargo"]);
    }

    // REDIRECCIÓN PARA REMOTO (Para poder generar el link inmediatamente)
    if ($modo_resp === 'remoto' || $modo_jefe === 'remoto') {
        header("Location: inventario_editar.php?id=$id_cargo&msg=generar_links");
        exit();
    }

    header("Location: inventario_lista.php?msg=guardado_ok");
    exit();

} catch (Exception $e) { die("Error crítico: " . $e->getMessage()); }
?>