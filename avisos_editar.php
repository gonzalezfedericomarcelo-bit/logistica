<?php
// Archivo: avisos_editar.php (RESPONSIVE)
session_start();
include 'conexion.php'; 
include 'funciones_permisos.php'; 

if (!isset($_SESSION['usuario_id']) || !tiene_permiso('acceso_avisos_gestionar', $pdo)) {
    header("Location: avisos.php");
    exit();
}

$id_aviso = (int)($_GET['id'] ?? 0);
if ($id_aviso <= 0) { header("Location: avisos_lista.php"); exit(); }

$mensaje = '';
$alerta_tipo = '';
$categorias_blog = ['Institucional', 'Novedades', 'Eventos', 'Urgente', 'Capacitación', 'Sociales'];

// --- FUNCIÓN HELPER PARA MINIATURAS ---
function crear_miniatura($ruta_origen, $ruta_destino, $ancho_max = 200) {
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

// Cargar datos
try {
    $stmt = $pdo->prepare("SELECT * FROM avisos WHERE id_aviso = :id");
    $stmt->execute([':id' => $id_aviso]);
    $aviso_data = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$aviso_data) { header("Location: avisos_lista.php"); exit(); }
} catch (PDOException $e) { die("Error: " . $e->getMessage()); }

// Procesar
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $titulo = trim($_POST['titulo']);
    $contenido = $_POST['contenido'];
    $categoria = $_POST['categoria'];
    $es_activo = isset($_POST['es_activo']) ? 1 : 0;
    $imagen_actual = $aviso_data['imagen_destacada'];

    if (isset($_FILES['imagen_destacada']) && $_FILES['imagen_destacada']['error'] === UPLOAD_ERR_OK) {
        $ext = strtolower(pathinfo($_FILES['imagen_destacada']['name'], PATHINFO_EXTENSION));
        if (in_array($ext, ['jpg','jpeg','png','webp'])) {
            $new_name = 'aviso_' . time() . '_' . uniqid() . '.' . $ext;
            $upload_path = 'uploads/avisos/' . $new_name;
            
            if (move_uploaded_file($_FILES['imagen_destacada']['tmp_name'], $upload_path)) {
                // Borrar vieja y su miniatura
                if ($imagen_actual) {
                    $old_file = 'uploads/avisos/' . $imagen_actual;
                    $old_thumb = 'uploads/avisos/' . pathinfo($imagen_actual, PATHINFO_FILENAME) . '_thumb.' . pathinfo($imagen_actual, PATHINFO_EXTENSION);
                    if (file_exists($old_file)) unlink($old_file);
                    if (file_exists($old_thumb)) unlink($old_thumb);
                }
                
                $imagen_actual = $new_name;
                $thumb_name = pathinfo($new_name, PATHINFO_FILENAME) . '_thumb.' . $ext;
                crear_miniatura($upload_path, 'uploads/avisos/' . $thumb_name);
            }
        }
    }

    if (!empty($titulo)) {
        $sql = "UPDATE avisos SET titulo=:tit, contenido=:cont, categoria=:cat, imagen_destacada=:img, es_activo=:act, fecha_actualizacion=NOW() WHERE id_aviso=:id";
        $pdo->prepare($sql)->execute([':tit'=>$titulo, ':cont'=>$contenido, ':cat'=>$categoria, ':img'=>$imagen_actual, ':act'=>$es_activo, ':id'=>$id_aviso]);
        $mensaje = '¡Actualizado!'; $alerta_tipo = 'success';
        $aviso_data['titulo'] = $titulo; $aviso_data['contenido'] = $contenido; $aviso_data['categoria'] = $categoria; $aviso_data['imagen_destacada'] = $imagen_actual; $aviso_data['es_activo'] = $es_activo;
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0"> <title>Editar Entrada</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">
    <script src="https://cdn.tiny.cloud/1/fsu1zolakhx1ihn2slwt050tc9rgv1jejro3mwbyixxr2coh/tinymce/7/tinymce.min.js" referrerpolicy="origin"></script>
    <style>
        .tox-promotion, .tox-statusbar__branding { display: none !important; visibility: hidden !important; }
        .tox-tinymce { border-radius: 8px!important; border: 1px solid #dee2e6!important; }
        @media (max-width: 768px) {
            .btn-lg { width: 100%; margin-top: 15px; }
            .form-check-input { transform: scale(1.2); }
        }
    </style>
</head>
<body>
    <?php include 'navbar.php'; ?>
    <div class="container mt-4 mb-5">
        <div class="card shadow-lg border-0">
            <div class="card-header bg-dark text-white d-flex justify-content-between align-items-center py-3">
                <h4 class="mb-0 fs-5"><i class="fas fa-edit me-2"></i>Editar</h4>
                <a href="avisos_lista.php" class="btn btn-sm btn-outline-light rounded-pill px-3">Volver</a>
            </div>
            <div class="card-body p-3 p-md-4">
                <?php if($mensaje): ?><div class="alert alert-<?php echo $alerta_tipo; ?> alert-dismissible fade show"><?php echo $mensaje; ?><button type="button" class="btn-close" data-bs-dismiss="alert"></button></div><?php endif; ?>
                <form method="POST" enctype="multipart/form-data">
                    <div class="row mb-4 g-3">
                        <div class="col-12 col-md-6">
                            <label class="form-label fw-bold">TÍTULO</label>
                            <input type="text" name="titulo" class="form-control" value="<?php echo htmlspecialchars($aviso_data['titulo']); ?>" required>
                        </div>
                        <div class="col-12 col-md-3">
                            <label class="form-label fw-bold">CATEGORÍA</label>
                            <select name="categoria" class="form-select">
                                <?php foreach($categorias_blog as $cat): ?>
                                    <option value="<?php echo $cat; ?>" <?php echo ($aviso_data['categoria'] == $cat) ? 'selected' : ''; ?>><?php echo $cat; ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-12 col-md-3">
                            <label class="form-label fw-bold">PORTADA</label>
                            <input type="file" name="imagen_destacada" class="form-control" accept="image/*">
                        </div>
                    </div>
                    <div class="mb-4"><textarea id="editor" name="contenido"><?php echo $aviso_data['contenido']; ?></textarea></div>
                    <div class="d-flex flex-column flex-md-row justify-content-between align-items-center">
                        <div class="form-check form-switch mb-3 mb-md-0 ps-5">
                            <input class="form-check-input" type="checkbox" id="es_activo" name="es_activo" <?php echo ($aviso_data['es_activo']) ? 'checked' : ''; ?> style="transform: scale(1.3);">
                            <label class="form-check-label fw-bold ms-2" for="es_activo">Visible</label>
                        </div>
                        <button type="submit" class="btn btn-primary btn-lg px-5"><i class="fas fa-save me-2"></i>Guardar</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
      tinymce.init({
        selector: '#editor', height: 600, language: 'es', menubar: true,
        plugins: ['advlist', 'autolink', 'lists', 'link', 'image', 'charmap', 'preview', 'searchreplace', 'fullscreen'],
        toolbar: 'undo redo | blocks | bold italic | alignleft | bullist numlist | image fullscreen',
        images_upload_handler: (blobInfo, progress) => new Promise((resolve, reject) => {
            const reader = new FileReader();
            reader.onload = () => resolve(reader.result);
            reader.onerror = () => reject('Error');
            reader.readAsDataURL(blobInfo.blob());
        })
      });
    </script>
    <?php include 'footer.php'; ?>
</body>
</html>