<?php
// Archivo: avisos_crear.php (VERSIÓN MÍNIMA ESTABLE DE TINYMCE)
session_start();
include 'conexion.php';
include 'funciones_permisos.php';

if (!isset($_SESSION['usuario_id']) || !tiene_permiso('crear_aviso', $pdo)) {
    header("Location: avisos.php");
    exit();
}

$mensaje = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $titulo = trim($_POST['titulo']);
    $contenido = $_POST['contenido']; 
    $id_user = $_SESSION['usuario_id'];

    if (!empty($titulo) && !empty($contenido)) {
        try {
            // Lógica de inserción original
            $sql = "INSERT INTO avisos (id_creador, titulo, contenido, fecha_publicacion, es_activo) VALUES (:id_user, :titulo, :cont, NOW(), 1)";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([':id_user' => $id_user, ':titulo' => $titulo, ':cont' => $contenido]);
            
            // Eliminada la lógica de notificación global
            
            header("Location: avisos.php?msg=creado");
            exit();
        } catch (PDOException $e) {
            $mensaje = "Error: " . $e->getMessage();
        }
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Crear Aviso</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <script src="https://cdn.tiny.cloud/1/fsu1zolakhx1ihn2slwt050tc9rgv1jejro3mwbyixxr2coh/tinymce/7/tinymce.min.js" referrerpolicy="origin"></script>
    <style>
        .tox-tinymce { border-radius: 8px!important; border: 1px solid #dee2e6!important; }
        .tox-promotion, .tox-statusbar__branding { display: none!important; }
        /* AÑADIDO: Estilo para que el container se extienda un poco más en pantallas grandes pero siga siendo responsivo */
        @media (min-width: 992px) {
            .container-xl-responsive {
                max-width: 1200px;
            }
        }
    </style>
</head>
<body>
    <?php include 'navbar.php'; ?>
    <div class="container-fluid container-xl-responsive mt-4 mb-5 p-3">
        <div class="card shadow-lg border-0 mx-auto" style="max-width: 1200px;">
            <div class="card-header bg-dark text-white d-flex flex-column flex-sm-row justify-content-between align-items-sm-center py-3">
                <h4 class="mb-2 mb-sm-0 text-center text-sm-start">Redactar Aviso</h4>
                <a href="avisos.php" class="btn btn-sm btn-outline-light rounded-pill px-3 w-100 w-sm-auto">Volver</a>
            </div>
            <div class="card-body p-4 p-md-5">
                <?php if($mensaje): ?><div class="alert alert-danger"><?php echo $mensaje; ?></div><?php endif; ?>
                <form method="POST">
                    <div class="mb-4">
                        <label class="form-label fw-bold">TÍTULO</label>
                        <input type="text" name="titulo" class="form-control form-control-lg mb-3" required>
                    </div>
                    <div class="mb-4">
                        <textarea id="editor" name="contenido"></textarea>
                    </div>
                    <div class="d-grid gap-2 d-md-flex justify-content-md-end pt-3 border-top">
                        <button type="submit" class="btn btn-primary btn-lg px-5 w-100 w-md-auto">Publicar</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
      // Configuración Original: Editor visible, menú visible, sin Premium extra.
      tinymce.init({
        selector: '#editor', height: 600, language: 'es', menubar: true,
        plugins: ['advlist', 'autolink', 'lists', 'link', 'image', 'charmap', 'preview', 'searchreplace', 'table', 'wordcount', 'media', 'fullscreen'],
        toolbar: 'undo redo | blocks | bold italic backcolor | alignleft aligncenter alignright | bullist numlist | removeformat | image media | fullscreen',
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
