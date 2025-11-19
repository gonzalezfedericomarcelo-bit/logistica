<?php
// Archivo: pedido_firmar_procesar.php (GUARDA LA FIRMA DIGITAL DEL SOLICITANTE)
session_start();
include 'conexion.php'; 

// Función de respuesta JSON para manejo de errores
function json_die($success, $message, $http_code = 200) {
    if ($http_code != 200) {
        http_response_code($http_code);
    }
    header('Content-Type: application/json');
    die(json_encode(['success' => $success, 'message' => $message]));
}


// 1. Proteger y validar POST
if (!isset($_SESSION['usuario_id']) || $_SERVER['REQUEST_METHOD'] !== 'POST') {
    json_die(false, "Acceso no autorizado o método incorrecto.", 403);
}

$id_pedido = (int)($_POST['id_pedido'] ?? 0);
$firma_base64 = $_POST['firma_base64'] ?? '';
$nombre_solicitante = $_POST['solicitante_nombre'] ?? 'Solicitante_Desconocido';

if ($id_pedido <= 0 || empty($firma_base64)) {
    json_die(false, "Datos incompletos.", 400);
}

// Ruta donde se guardarán las firmas (debes crear esta carpeta: uploads/firmas_pedidos/)
$upload_dir = 'uploads/firmas_pedidos/';

// Asegurarse de que la carpeta exista
if (!is_dir($upload_dir)) {
    if (!mkdir($upload_dir, 0777, true)) {
        json_die(false, "Error al crear directorio de subida de firmas.", 500);
    }
}

// Limpiar y decodificar Base64
$data = explode(',', $firma_base64);
$encoded_image = (count($data) > 1) ? $data[1] : $data[0];
$decoded_image = base64_decode($encoded_image);

if ($decoded_image === false) {
    json_die(false, "Formato de firma inválido.", 400);
}

$pdo->beginTransaction();
$ruta_guardada = null;

try {
    // 1. Generar nombre único para el archivo PNG
    $nombre_archivo_limpio = preg_replace("/[^a-zA-Z0-9]/", "", str_replace(" ", "_", $nombre_solicitante));
    $filename = $nombre_archivo_limpio . '_' . $id_pedido . '_' . time() . '.png';
    $ruta_completa = $upload_dir . $filename;
    
    // 2. Guardar el archivo en el servidor
    if (!file_put_contents($ruta_completa, $decoded_image)) {
        throw new Exception("No se pudo guardar la imagen en disco.");
    }
    
    $ruta_guardada = $ruta_completa;
    
    // 3. Actualizar la tabla pedidos_trabajo con la ruta
    $sql_update = "UPDATE pedidos_trabajo SET 
                    firma_solicitante_path = :ruta
                   WHERE id_pedido = :id_pedido AND firma_solicitante_path IS NULL"; 

    $stmt_update = $pdo->prepare($sql_update);
    $stmt_update->execute([
        ':ruta' => $ruta_guardada,
        ':id_pedido' => $id_pedido
    ]);

    if ($stmt_update->rowCount() > 0) {
        $pdo->commit();
        json_die(true, 'Firma guardada correctamente.'); // ÉXITO
    } else {
        throw new Exception("La base de datos no se actualizó (posiblemente la firma ya existía o el pedido no existe).");
    }

} catch (Exception $e) {
    $pdo->rollBack();
    error_log("Error al procesar firma para pedido #{$id_pedido}: " . $e->getMessage());
    json_die(false, "Error de servidor al guardar: " . $e->getMessage(), 500); // ERROR
}

// Final del script.