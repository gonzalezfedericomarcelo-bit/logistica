<?php
// Archivo: inventario_guardar.php (VERSIÓN ROBUSTA + DIAGNÓSTICO)
// 1. ACTIVAR REPORTE DE ERRORES PARA VER QUÉ PASA
error_reporting(E_ALL);
ini_set('display_errors', 1);

session_start();
try {
    include 'conexion.php';
} catch (Exception $e) {
    die("<h1>Error crítico al conectar BD:</h1> " . $e->getMessage());
}
date_default_timezone_set('America/Argentina/Buenos_Aires');

// Si no es POST, volver
if ($_SERVER['REQUEST_METHOD'] != 'POST') {
    header("Location: inventario_nuevo.php");
    exit();
}

try {
    // 2. VALIDACIÓN DE SESIÓN
    if (!isset($_SESSION['usuario_id'])) {
        throw new Exception("La sesión ha caducado. Por favor inicia sesión nuevamente.");
    }
    $id_usuario = $_SESSION['usuario_id'];

    // 3. RECUPERAR Y SANITIZAR DATOS (Evitar errores por comillas vacías)
    $elemento     = $_POST['elemento'] ?? 'SIN NOMBRE';
    // Si servicio_ubicacion viene disabled (vacío), ponemos 'General' o lo que venga
    $ubicacion    = !empty($_POST['servicio_ubicacion']) ? $_POST['servicio_ubicacion'] : 'General';
    $responsable  = $_POST['nombre_responsable'] ?? '';
    $jefe         = $_POST['nombre_jefe_servicio'] ?? '';
    $codigo       = $_POST['codigo_inventario'] ?? '';
    $obs          = $_POST['observaciones'] ?? '';
    
    // CAMPOS NUMÉRICOS/ID: Convertir '' a NULL para que MySQL no de error
    $id_destino   = !empty($_POST['id_destino']) ? $_POST['id_destino'] : null;
    $id_estado    = !empty($_POST['id_estado']) ? $_POST['id_estado'] : 1;
    $id_tipo_bien = !empty($_POST['id_tipo_bien_seleccionado']) ? $_POST['id_tipo_bien_seleccionado'] : null;

    // 4. PROCESAR FIRMAS (Con verificación de carpeta)
    $firma_resp_path = null;
    $firma_jefe_path = null;
    $ruta_firmas = 'uploads/firmas/';

    // Asegurar que la carpeta existe
    if (!file_exists($ruta_firmas)) {
        if (!mkdir($ruta_firmas, 0777, true)) {
            throw new Exception("No se pudo crear la carpeta '$ruta_firmas'. Revisa permisos.");
        }
    }

    if (!empty($_POST['base64_responsable'])) {
        $data = base64_decode(explode(',', $_POST['base64_responsable'])[1]);
        if(!$data) throw new Exception("Error al decodificar firma responsable.");
        $firma_resp_path = $ruta_firmas . 'resp_' . time() . '_' . uniqid() . '.png';
        file_put_contents($firma_resp_path, $data);
    }
    if (!empty($_POST['base64_jefe'])) {
        $data = base64_decode(explode(',', $_POST['base64_jefe'])[1]);
        if(!$data) throw new Exception("Error al decodificar firma jefe.");
        $firma_jefe_path = $ruta_firmas . 'jefe_' . time() . '_' . uniqid() . '.png';
        file_put_contents($firma_jefe_path, $data);
    }

    // 5. INSERTAR CARGO PRINCIPAL
    // Usamos TRY interno para detectar error SQL específico aquí
    $sql = "INSERT INTO inventario_cargos (
                id_usuario_relevador, id_tipo_bien, id_estado_fk, elemento, 
                servicio_ubicacion, destino_principal, nombre_responsable, 
                nombre_jefe_servicio, firma_responsable_path, firma_jefe_path, 
                codigo_patrimonial, observaciones, fecha_creacion
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())";
    
    $stmt = $pdo->prepare($sql);
    $params = [
        $id_usuario, $id_tipo_bien, $id_estado, $elemento, 
        $ubicacion, $id_destino, $responsable, 
        $jefe, $firma_resp_path, $firma_jefe_path, 
        $codigo, $obs
    ];

    if (!$stmt->execute($params)) {
        throw new Exception("Error al ejecutar INSERT principal.");
    }
    $id_cargo = $pdo->lastInsertId();

    // 6. ACTUALIZAR DATOS TÉCNICOS (MATAFUEGOS)
    // Sanitizamos también estos inputs numéricos
    $mat_fecha_carga    = !empty($_POST['mat_fecha_carga']) ? $_POST['mat_fecha_carga'] : null;
    $mat_fecha_ph       = !empty($_POST['mat_fecha_ph']) ? $_POST['mat_fecha_ph'] : null;
    $mat_numero_grabado = $_POST['mat_numero_grabado'] ?? null;
    $fecha_fabricacion  = !empty($_POST['fecha_fabricacion']) ? $_POST['fecha_fabricacion'] : null;
    $mat_capacidad      = !empty($_POST['mat_capacidad']) ? $_POST['mat_capacidad'] : null;
    $mat_tipo_carga_id  = !empty($_POST['mat_tipo_carga_id']) ? $_POST['mat_tipo_carga_id'] : null;
    $mat_clase_id       = !empty($_POST['mat_clase_id']) ? $_POST['mat_clase_id'] : null;

    if ($mat_fecha_carga || $mat_fecha_ph || $mat_numero_grabado || $fecha_fabricacion || $mat_capacidad) {
        $sqlUpd = "UPDATE inventario_cargos SET 
                    mat_fecha_carga = ?, mat_fecha_ph = ?, mat_numero_grabado = ?, 
                    fecha_fabricacion = ?, mat_capacidad = ?, mat_tipo_carga_id = ?, 
                    mat_clase_id = ?
                   WHERE id_cargo = ?";
        $pdo->prepare($sqlUpd)->execute([
            $mat_fecha_carga, $mat_fecha_ph, $mat_numero_grabado,
            $fecha_fabricacion, $mat_capacidad, $mat_tipo_carga_id, 
            $mat_clase_id, $id_cargo
        ]);
    }

    // 7. GUARDAR CAMPOS DINÁMICOS
    if (isset($_POST['dinamico']) && is_array($_POST['dinamico'])) {
        $sqlDyn = "INSERT INTO inventario_valores_dinamicos (id_cargo, id_campo, valor) VALUES (?, ?, ?)";
        $stmtDyn = $pdo->prepare($sqlDyn);
        foreach ($_POST['dinamico'] as $id_campo => $valor) {
            if (!empty($valor)) {
                $stmtDyn->execute([$id_cargo, $id_campo, $valor]);
            }
        }
    }

    // SI LLEGAMOS ACÁ, TODO SALIÓ BIEN
    header("Location: inventario_lista.php?msg=guardado_ok");
    exit();

} catch (PDOException $e) {
    // ERRORES DE BASE DE DATOS
    echo "<div style='background:black; color:red; padding:20px; font-family:monospace;'>";
    echo "<h1>⛔ ERROR SQL (BASE DE DATOS)</h1>";
    echo "<h3>" . $e->getMessage() . "</h3>";
    echo "<p><strong>Posible causa:</strong> Una columna no coincide o falta en la tabla 'inventario_cargos'.</p>";
    echo "<pre>" . $e->getTraceAsString() . "</pre>";
    echo "</div>";
    exit();

} catch (Exception $e) {
    // ERRORES GENERALES
    echo "<div style='background:black; color:yellow; padding:20px; font-family:monospace;'>";
    echo "<h1>⚠️ ERROR DE SISTEMA</h1>";
    echo "<h3>" . $e->getMessage() . "</h3>";
    echo "<pre>" . $e->getTraceAsString() . "</pre>";
    echo "</div>";
    exit();
}
?>