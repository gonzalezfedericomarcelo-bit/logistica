<?php
// Archivo: avisos_crear.php (CORREGIDO: CON FONT AWESOME)
session_start();
include 'conexion.php';
include 'funciones_permisos.php';

if (!isset($_SESSION['usuario_id']) || !tiene_permiso('acceso_avisos_crear', $pdo)) {
    header("Location: avisos.php");
    exit();
}

$mensaje = '';
$alerta_tipo = '';

// Categorías predefinidas
$categorias_blog = ['Institucional', 'Novedades', 'Eventos', 'Urgente', 'Capacitación', 'Sociales'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // ... (Misma lógica PHP que antes) ...
    $titulo = trim($_POST['titulo']);
    $contenido = $_POST['contenido']; 
    $categoria = $_POST['categoria'] ?? 'General';
    $id_user = $_SESSION['usuario_id'];
    $imagen_destacada = null;

    if (isset($_FILES['imagen_destacada']) && $_FILES['imagen_destacada']['error'] === UPLOAD_ERR_OK) {
        $file_tmp = $_FILES['imagen_destacada']['tmp_name'];
        $ext = strtolower(pathinfo($_FILES['imagen_destacada']['name'], PATHINFO_EXTENSION));
        if (in_array($ext, ['jpg', 'jpeg', 'png', 'webp', 'gif'])) {
            $new_name = 'aviso_' . time() . '_' . uniqid() . '.' . $ext;
            if (!is_dir('uploads/avisos')) mkdir('uploads/avisos', 0777, true);
            if (move_uploaded_file($file_tmp, 'uploads/avisos/' . $new_name)) $imagen_destacada = $new_name;
        }
    }

    if (!empty($titulo) && !empty($contenido)) {
        try {
            $sql = "INSERT INTO avisos (id_creador, titulo, imagen_destacada, categoria, contenido, fecha_publicacion, es_activo) 
                    VALUES (:id, :tit, :img, :cat, :cont, NOW(), 1)";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([':id'=>$id_user, ':tit'=>$titulo, ':img'=>$imagen_destacada, ':cat'=>$categoria, ':cont'=>$contenido]);
            header("Location: avisos.php?msg=creado"); exit();
        } catch (PDOException $e) { $mensaje = "Error BD: " . $e->getMessage(); $alerta_tipo = 'danger'; }
    } else { $mensaje = "Faltan datos."; $alerta_tipo = 'warning'; }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Crear Entrada de Blog</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">
    <script src="https://cdn.tiny.cloud/1/fsu1zolakhx1ihn2slwt050tc9rgv1jejro3mwbyixxr2coh/tinymce/7/tinymce.min.js" referrerpolicy="origin"></script>
    <style>
        .tox-promotion, .tox-statusbar__branding { display: none !important; visibility: hidden !important; }
        .tox-tinymce { border-radius: 8px!important; border: 1px solid #dee2e6!important; }
        @media (min-width: 992px) { .container-xl-responsive { max-width: 1200px; } }
    </style>
</head>
<body>
    <?php include 'navbar.php'; ?>
    <div class="container-fluid container-xl-responsive mt-4 mb-5 p-3">
        <div class="card shadow-lg border-0 mx-auto" style="max-width: 1200px;">
            <div class="card-header bg-dark text-white d-flex justify-content-between align-items-center py-3">
                <h4 class="mb-0"><i class="fas fa-pen-fancy me-2"></i>Redactar Entrada</h4>
                <a href="avisos.php" class="btn btn-sm btn-outline-light rounded-pill px-3">Volver al Blog</a>
            </div>
            <div class="card-body p-4 p-md-5">
                <?php if($mensaje): ?><div class="alert alert-<?php echo $alerta_tipo; ?>"><?php echo $mensaje; ?></div><?php endif; ?>
                <form method="POST" enctype="multipart/form-data">
                    <div class="row mb-4">
                        <div class="col-md-6">
                            <label class="form-label fw-bold">TÍTULO</label>
                            <input type="text" name="titulo" class="form-control form-control-lg" required>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label fw-bold">CATEGORÍA</label>
                            <select name="categoria" class="form-select form-select-lg">
                                <?php foreach($categorias_blog as $cat): ?>
                                    <option value="<?php echo $cat; ?>"><?php echo $cat; ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label fw-bold">PORTADA (Opcional)</label>
                            <input type="file" name="imagen_destacada" class="form-control form-control-lg" accept="image/*">
                        </div>
                    </div>
                    <div class="mb-4"><textarea id="editor" name="contenido"></textarea></div>
                    <div class="d-grid gap-2 d-md-flex justify-content-md-end pt-3 border-top">
                        <button type="submit" class="btn btn-primary btn-lg px-5"><i class="fas fa-paper-plane me-2"></i>Publicar</button>
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
        toolbar: 'undo redo | blocks | bold italic | alignleft aligncenter alignright | bullist numlist | image fullscreen',
        images_upload_handler: (blobInfo, progress) => new Promise((resolve, reject) => {
            const reader = new FileReader();
            reader.onload = () => resolve(reader.result);
            reader.onerror = () => reject('Error');
            reader.readAsDataURL(blobInfo.blob());
        })
      });
    </script>
</body>
</html>