<?php
// Archivo: ajax_firmar_cargo.php (SOPORTA FIRMA CANVAS Y FIRMA REGISTRADA)
session_start();
include 'conexion.php';
date_default_timezone_set('America/Argentina/Buenos_Aires');

header('Content-Type: application/json');

if (!isset($_SESSION['usuario_id']) || empty($_POST['id_cargo']) || empty($_POST['rol'])) {
    echo json_encode(['status' => 'error', 'msg' => 'Datos incompletos o sesión inválida.']);
    exit();
}

try {
    $id_usuario = $_SESSION['usuario_id'];
    $id_cargo = (int)$_POST['id_cargo'];
    $rol = $_POST['rol']; // 'responsable' o 'jefe'
    $tipo_origen = $_POST['tipo_origen'] ?? 'canvas'; // 'canvas' o 'registrada'

    // Definir columna y rutas
    $columna_firma = ($rol === 'jefe') ? 'firma_jefe_path' : 'firma_responsable_path';
    $ruta_firmas_destino = 'uploads/firmas/';
    if (!file_exists($ruta_firmas_destino)) mkdir($ruta_firmas_destino, 0777, true);

    $nuevo_nombre = $rol . '_sys_' . time() . '_' . uniqid() . '.png';
    $path_destino = $ruta_firmas_destino . $nuevo_nombre;

    // --- LÓGICA SEGÚN TIPO DE FIRMA ---
    if ($tipo_origen === 'registrada') {
        // 1. Buscar la firma del usuario en la base de datos
        $stmtUser = $pdo->prepare("SELECT firma_digital FROM usuarios WHERE id_usuario = ?");
        $stmtUser->execute([$id_usuario]);
        $firma_origen = $stmtUser->fetchColumn();

        if (empty($firma_origen) || !file_exists($firma_origen)) {
            throw new Exception("No tienes una firma registrada en tu perfil. Por favor, cárgala primero en 'Mi Perfil' o firma manualmente.");
        }

        // 2. Copiar la firma al registro del cargo (Para mantener historial si cambia la del perfil luego)
        if (!copy($firma_origen, $path_destino)) {
            throw new Exception("Error al procesar la firma del sistema.");
        }

    } else {
        // 1. Firma desde Canvas (Base64)
        if (empty($_POST['firma_base64'])) throw new Exception("No se recibió el trazo de la firma.");
        
        $img = $_POST['firma_base64'];
        $parts = explode(',', $img);
        $data = base64_decode(count($parts) > 1 ? $parts[1] : $parts[0]);
        
        if (!file_put_contents($path_destino, $data)) {
            throw new Exception("No se pudo guardar la imagen en el servidor.");
        }
    }

    // --- ACTUALIZACIÓN Y NOTIFICACIÓN (Igual que antes) ---
    
    // Obtenemos info del cargo
    $stmtInfo = $pdo->prepare("SELECT elemento, id_usuario_relevador FROM inventario_cargos WHERE id_cargo = ?");
    $stmtInfo->execute([$id_cargo]);
    $infoCargo = $stmtInfo->fetch(PDO::FETCH_ASSOC);
    
    // Guardamos la ruta en la DB
    $sqlUpdate = "UPDATE inventario_cargos SET $columna_firma = ? WHERE id_cargo = ?";
    $pdo->prepare($sqlUpdate)->execute([$path_destino, $id_cargo]);

    // Notificar
    $nombre_rol_txt = ($rol === 'jefe') ? 'Jefe de Servicio' : 'Responsable';
    $mensaje = "El usuario " . $_SESSION['nombre_usuario'] . " ha firmado como $nombre_rol_txt el cargo: " . $infoCargo['elemento'];
    $link = "inventario_editar.php?id=" . $id_cargo;

    if ($infoCargo['id_usuario_relevador']) {
        $pdo->prepare("INSERT INTO notificaciones (id_usuario_destino, tipo, mensaje, url, fecha_creacion) VALUES (?, 'aviso_firma', ?, ?, NOW())")
            ->execute([$infoCargo['id_usuario_relevador'], $mensaje, $link]);
    }

    // Admins
    $stmtAdmins = $pdo->query("SELECT id_usuario FROM usuarios WHERE rol = 'admin'");
    while ($admin = $stmtAdmins->fetch(PDO::FETCH_ASSOC)) {
        if ($admin['id_usuario'] != $infoCargo['id_usuario_relevador']) {
            $pdo->prepare("INSERT INTO notificaciones (id_usuario_destino, tipo, mensaje, url, fecha_creacion) VALUES (?, 'aviso_firma', ?, ?, NOW())")
                ->execute([$admin['id_usuario'], $mensaje, $link]);
        }
    }

    echo json_encode(['status' => 'success', 'msg' => 'Firma registrada correctamente.', 'path' => $path_destino]);

} catch (Exception $e) {
    echo json_encode(['status' => 'error', 'msg' => $e->getMessage()]);
}
?>