<?php
// Archivo: inventario_guardar.php (VERSIÓN FINAL COMPLETA - CORRIGE GUARDADO DINÁMICO)
error_reporting(E_ALL); ini_set('display_errors', 1);
session_start();
include 'conexion.php';
date_default_timezone_set('America/Argentina/Buenos_Aires');

// 1. Validaciones básicas
if ($_SERVER['REQUEST_METHOD'] != 'POST' || !isset($_SESSION['usuario_id'])) { 
    header("Location: dashboard.php"); 
    exit(); 
}

try {
    $id_usuario = $_SESSION['usuario_id'];
    $accion     = $_POST['accion'] ?? 'crear';
    $id_cargo   = !empty($_POST['id_cargo']) ? (int)$_POST['id_cargo'] : null;

    // 2. Datos Generales (Base)
    $elemento     = $_POST['elemento'] ?? 'SIN NOMBRE';
    $ubicacion    = !empty($_POST['servicio_ubicacion']) ? $_POST['servicio_ubicacion'] : 'General';
    $responsable  = $_POST['nombre_responsable'] ?? '';
    $jefe         = $_POST['nombre_jefe_servicio'] ?? '';
    $codigo       = $_POST['codigo_inventario'] ?? '';
    $n_iosfa      = $_POST['n_iosfa'] ?? null;
    $obs          = $_POST['observaciones'] ?? '';
    $id_destino   = !empty($_POST['id_destino']) ? $_POST['id_destino'] : null;
    $id_estado    = !empty($_POST['id_estado']) ? $_POST['id_estado'] : 1;
    $id_tipo_bien = !empty($_POST['id_tipo_bien_seleccionado']) ? $_POST['id_tipo_bien_seleccionado'] : null;

    // 3. Procesamiento de Firmas (Base64 a Archivo)
    $ruta_firmas = 'uploads/firmas/';
    if (!file_exists($ruta_firmas)) mkdir($ruta_firmas, 0777, true);
    
    $path_resp = null; 
    $path_jefe = null;
    
    // Firma Responsable
    if (!empty($_POST['base64_responsable'])) { 
        $parts = explode(',', $_POST['base64_responsable']);
        $d = base64_decode(count($parts) > 1 ? $parts[1] : $parts[0]);
        $path_resp = $ruta_firmas . 'resp_' . time() . uniqid() . '.png'; 
        file_put_contents($path_resp, $d); 
    }
    // Firma Jefe
    if (!empty($_POST['base64_jefe'])) { 
        $parts = explode(',', $_POST['base64_jefe']);
        $d = base64_decode(count($parts) > 1 ? $parts[1] : $parts[0]);
        $path_jefe = $ruta_firmas . 'jefe_' . time() . uniqid() . '.png'; 
        file_put_contents($path_jefe, $d); 
    }

    // 4. Inserción o Actualización en Tabla Principal (inventario_cargos)
    if ($accion === 'editar' && $id_cargo) {
        // --- MODO EDICIÓN ---
        $sql = "UPDATE inventario_cargos SET 
                id_estado_fk=?, elemento=?, servicio_ubicacion=?, destino_principal=?, 
                nombre_responsable=?, nombre_jefe_servicio=?, codigo_patrimonial=?, 
                n_iosfa=?, observaciones=?";
        
        $params = [$id_estado, $elemento, $ubicacion, $id_destino, $responsable, $jefe, $codigo, $n_iosfa, $obs];

        // Solo actualizamos rutas de firma si se envió una nueva
        if ($path_resp) { $sql .= ", firma_responsable_path=?"; $params[] = $path_resp; }
        if ($path_jefe) { $sql .= ", firma_jefe_path=?"; $params[] = $path_jefe; }

        $sql .= " WHERE id_cargo=?";
        $params[] = $id_cargo;
        $pdo->prepare($sql)->execute($params);

    } else {
        // --- MODO CREACIÓN ---
        $sql = "INSERT INTO inventario_cargos (
                id_usuario_relevador, id_tipo_bien, id_estado_fk, elemento, servicio_ubicacion, 
                destino_principal, nombre_responsable, nombre_jefe_servicio, firma_responsable_path, 
                firma_jefe_path, codigo_patrimonial, n_iosfa, observaciones, fecha_creacion
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())";
        
        $pdo->prepare($sql)->execute([
            $id_usuario, $id_tipo_bien, $id_estado, $elemento, $ubicacion, $id_destino, 
            $responsable, $jefe, $path_resp, $path_jefe, $codigo, $n_iosfa, $obs
        ]);
        $id_cargo = $pdo->lastInsertId();
    }

    // 5. GUARDADO DE CAMPOS DINÁMICOS (CRÍTICO: ESTO FALTABA)
    // Aquí se guardan Marca, Modelo, Serie, IP, etc.
    if (isset($_POST['dinamico']) && is_array($_POST['dinamico'])) {
        foreach ($_POST['dinamico'] as $id_campo => $valor) {
            $valor = trim($valor);
            
            // Verificamos si ya existe un valor para este campo y cargo
            $stmtCheck = $pdo->prepare("SELECT id_valor FROM inventario_valores_dinamicos WHERE id_cargo = ? AND id_campo = ?");
            $stmtCheck->execute([$id_cargo, $id_campo]);
            $id_valor_existente = $stmtCheck->fetchColumn();

            if ($id_valor_existente) {
                // Update
                $pdo->prepare("UPDATE inventario_valores_dinamicos SET valor = ? WHERE id_valor = ?")
                    ->execute([$valor, $id_valor_existente]);
            } else {
                // Insert
                $pdo->prepare("INSERT INTO inventario_valores_dinamicos (id_cargo, id_campo, valor) VALUES (?, ?, ?)")
                    ->execute([$id_cargo, $id_campo, $valor]);
            }
        }
    }

    // 6. GUARDADO DE DATOS ESPECÍFICOS DE MATAFUEGOS
    // Se actualizan en la tabla principal si vienen en el POST
    $mat_tipo  = $_POST['mat_tipo_carga_id'] ?? null;
    $mat_cap   = $_POST['mat_capacidad'] ?? null;
    $mat_clase = $_POST['mat_clase_id'] ?? null;
    $mat_fab   = $_POST['fecha_fabricacion'] ?? null;
    $mat_carga = !empty($_POST['mat_fecha_carga']) ? $_POST['mat_fecha_carga'] : null;
    $mat_ph    = !empty($_POST['mat_fecha_ph']) ? $_POST['mat_fecha_ph'] : null;
    $mat_grab  = $_POST['mat_numero_grabado'] ?? null;

    // Si alguno de estos datos existe, ejecutamos el update específico
    if ($mat_tipo || $mat_cap || $mat_clase || $mat_fab || $mat_carga || $mat_ph || $mat_grab) {
        $sqlMat = "UPDATE inventario_cargos SET 
                   mat_tipo_carga_id=?, mat_capacidad=?, mat_clase_id=?, 
                   fecha_fabricacion=?, mat_fecha_carga=?, mat_fecha_ph=?, 
                   mat_numero_grabado=? 
                   WHERE id_cargo=?";
        $pdo->prepare($sqlMat)->execute([
            $mat_tipo, $mat_cap, $mat_clase, $mat_fab, $mat_carga, $mat_ph, $mat_grab, $id_cargo
        ]);
    }

    // 7. Redirección final
    header("Location: inventario_lista.php?msg=guardado_ok");
    exit();

} catch (Exception $e) { 
    die("Error crítico al guardar: " . $e->getMessage()); 
}
?>