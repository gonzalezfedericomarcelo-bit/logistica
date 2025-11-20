<?php
// Archivo: ver_imagen_aviso.php
// SCRIPT LIGERO PARA SERVIR IMÁGENES BASE64 DESDE LA BD COMO ARCHIVOS
session_start();
include 'conexion.php';

// 1. Validar ID
$id_aviso = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if ($id_aviso <= 0) {
    header("HTTP/1.0 404 Not Found");
    exit;
}

// 2. CACHÉ DEL NAVEGADOR (Para que no la descargue mil veces)
$etag = md5($id_aviso . 'aviso_image'); 
header("Etag: $etag"); 
if (isset($_SERVER['HTTP_IF_NONE_MATCH']) && trim($_SERVER['HTTP_IF_NONE_MATCH']) == $etag) { 
    header("HTTP/1.1 304 Not Modified"); 
    exit; 
}

try {
    // 3. Buscar solo el contenido del aviso
    $stmt = $pdo->prepare("SELECT contenido FROM avisos WHERE id_aviso = :id");
    $stmt->execute([':id' => $id_aviso]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($row && !empty($row['contenido'])) {
        $html = $row['contenido'];

        // 4. Buscar la cadena Base64 con Expresiones Regulares
        // Busca: src="data:image/[TIPO];base64,[DATOS]"
        if (preg_match('/src=["\']data:image\/([^;]*);base64,([^"\']*)["\']/i', $html, $matches)) {
            $tipo_imagen = $matches[1]; // ej: jpeg, png
            $datos_base64 = $matches[2];

            // Decodificar
            $imagen_binaria = base64_decode($datos_base64);

            // 5. Servir la imagen
            header("Content-Type: image/" . $tipo_imagen);
            header("Content-Length: " . strlen($imagen_binaria));
            echo $imagen_binaria;
            exit;
        }
    }

    // Si no hay imagen, devolver 404 o una imagen transparente de 1px
    header("HTTP/1.0 404 Not Found");

} catch (Exception $e) {
    header("HTTP/1.0 500 Internal Server Error");
}
?>