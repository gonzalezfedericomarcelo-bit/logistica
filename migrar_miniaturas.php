<?php
// Archivo: migrar_miniaturas.php
// EJECUTAR ESTE ARCHIVO UNA SOLA VEZ Y LUEGO BORRARLO SI SE DESEA
session_start();
include 'conexion.php';

echo "<h2>🔄 Generando miniaturas para imágenes existentes...</h2>";

// Función generadora de miniaturas (misma lógica que usaremos en crear/editar)
function crear_miniatura_migracion($ruta_origen, $ruta_destino, $ancho_max = 200) {
    if (!file_exists($ruta_origen)) return false;
    
    list($ancho_orig, $alto_orig, $tipo) = getimagesize($ruta_origen);
    if (!$ancho_orig) return false;

    $ratio = $ancho_orig / $alto_orig;
    $ancho_nuevo = $ancho_max;
    $alto_nuevo = $ancho_max / $ratio;

    $thumb = imagecreatetruecolor($ancho_nuevo, $alto_nuevo);
    $origen = null;

    switch ($tipo) {
        case IMAGETYPE_JPEG: $origen = imagecreatefromjpeg($ruta_origen); break;
        case IMAGETYPE_PNG: 
            $origen = imagecreatefrompng($ruta_origen); 
            imagealphablending($thumb, false); imagesavealpha($thumb, true);
            break;
        case IMAGETYPE_WEBP: $origen = imagecreatefromwebp($ruta_origen); break;
        case IMAGETYPE_GIF: $origen = imagecreatefromgif($ruta_origen); break;
    }

    if ($origen) {
        imagecopyresampled($thumb, $origen, 0, 0, 0, 0, $ancho_nuevo, $alto_nuevo, $ancho_orig, $alto_orig);
        switch ($tipo) {
            case IMAGETYPE_JPEG: imagejpeg($thumb, $ruta_destino, 80); break;
            case IMAGETYPE_PNG: imagepng($thumb, $ruta_destino, 8); break;
            case IMAGETYPE_WEBP: imagewebp($thumb, $ruta_destino, 80); break;
            case IMAGETYPE_GIF: imagegif($thumb, $ruta_destino); break;
        }
        imagedestroy($thumb); imagedestroy($origen);
        return true;
    }
    return false;
}

// Buscar todas las imágenes destacadas
$stmt = $pdo->query("SELECT id_aviso, imagen_destacada FROM avisos WHERE imagen_destacada IS NOT NULL AND imagen_destacada != ''");
$avisos = $stmt->fetchAll(PDO::FETCH_ASSOC);

$contador = 0;
foreach ($avisos as $aviso) {
    $archivo = $aviso['imagen_destacada'];
    $ruta_original = 'uploads/avisos/' . $archivo;
    
    // Definir nombre de la miniatura: nombre_thumb.ext
    $info = pathinfo($archivo);
    $nombre_thumb = $info['filename'] . '_thumb.' . $info['extension'];
    $ruta_thumb = 'uploads/avisos/' . $nombre_thumb;

    if (file_exists($ruta_original)) {
        if (!file_exists($ruta_thumb)) {
            if (crear_miniatura_migracion($ruta_original, $ruta_thumb)) {
                echo "<p style='color:green'>✅ Miniatura creada para: $archivo</p>";
                $contador++;
            } else {
                echo "<p style='color:red'>❌ Error al procesar: $archivo</p>";
            }
        } else {
            echo "<p style='color:gray'>⏭️ Ya existía: $nombre_thumb</p>";
        }
    }
}

echo "<hr><h3>¡Proceso terminado! Se generaron $contador miniaturas.</h3>";
echo "<a href='dashboard.php'>Volver al Dashboard</a>";
?>