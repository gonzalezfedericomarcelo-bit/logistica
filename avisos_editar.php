<?php
// Archivo: avisos_editar.php (CORREGIDO: TinyMCE igual al Crear + Sin errores al final)
session_start();
include 'conexion.php'; 
include 'funciones_permisos.php'; 

// 1. Validar ID
$id_aviso = (int)($_GET['id'] ?? 0);
if ($id_aviso <= 0) {
    header("Location: avisos_lista.php");
    exit();
}

$mensaje = '';
$alerta_tipo = '';
$aviso_data = null;

// Variables por defecto
$titulo_display = '';
$contenido_html = '';
$es_activo_checked = 'checked';
$creador_nombre = 'N/A';
$fecha_publicacion = 'N/A';
$fecha_actualizacion_display = false;

// 2. PROCESAR EDICIÓN (POST)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $titulo = trim($_POST['titulo'] ?? '');
    // TinyMCE envía el contenido en el textarea con name="contenido"
    $contenido_html = $_POST['contenido'] ?? ''; 
    $es_activo = isset($_POST['es_activo']) ? 1 : 0; 
    $fecha_actualizacion = date('Y-m-d H:i:s');

    // Limpieza básica para validar que no esté vacío
    $contenido_limpio = strip_tags($contenido_html, '<img>'); 
    
    if (empty($titulo) || empty(trim(str_replace(['&nbsp;', ' '], '', $contenido_limpio)))) {
        $mensaje = 'El título y el contenido son obligatorios.';
        $alerta_tipo = 'danger';
    } else {
        try {
            $sql = "UPDATE avisos SET 
                    titulo = :titulo, 
                    contenido = :contenido, 
                    es_activo = :es_activo,
                    fecha_actualizacion = :fecha_actualizacion
                    WHERE id_aviso = :id_aviso";
            
            $stmt = $pdo->prepare($sql);
            $stmt->execute([
                ':titulo' => $titulo,
                ':contenido' => $contenido_html, 
                ':es_activo' => $es_activo,
                ':fecha_actualizacion' => $fecha_actualizacion,
                ':id_aviso' => $id_aviso
            ]);
            
            $mensaje = '¡Aviso actualizado correctamente!';
            $alerta_tipo = 'success';
            
        } catch (PDOException $e) {
            $mensaje = 'Error en base de datos: ' . $e->getMessage();
            $alerta_tipo = 'danger';
        }
    }
}

// 3. OBTENER DATOS (GET)
try {
    $sql_aviso = "
        SELECT a.*, u.nombre_completo AS creador_nombre 
        FROM avisos a
        JOIN usuarios u ON a.id_creador = u.id_usuario
        WHERE a.id_aviso = :id_aviso
    ";
    $stmt_aviso = $pdo->prepare($sql_aviso);
    $stmt_aviso->execute([':id_aviso' => $id_aviso]);
    $aviso_data = $stmt_aviso->fetch(PDO::FETCH_ASSOC);

    if (!$aviso_data) {
        header("Location: avisos_lista.php");
        exit();
    }
    
    // Cargar datos si no es un POST exitoso o si es la primera carga
    if ($_SERVER['REQUEST_METHOD'] !== 'POST' || $alerta_tipo === 'success') {
        $contenido_html = $aviso_data['contenido'] ?? '';
        $titulo_display = htmlspecialchars($aviso_data['titulo'] ?? '');
        $es_activo_checked = ($aviso_data['es_activo'] ?? 1) ? 'checked' : '';
    } else {
        // Si hubo error en POST, mantener lo que escribió el usuario
        $titulo_display = htmlspecialchars($_POST['titulo'] ?? '');
        $es_activo_checked = isset($_POST['es_activo']) ? 'checked' : '';
    }

    $creador_nombre = htmlspecialchars($aviso_data['creador_nombre']);
    $fecha_publicacion = date('d/m/Y H:i', strtotime($aviso_data['fecha_publicacion']));
    $fecha_actualizacion_display = $aviso_data['fecha_actualizacion'] ? date('d/m/Y H:i', strtotime($aviso_data['fecha_actualizacion'])) : false;

} catch (PDOException $e) {
    $mensaje = 'Error al cargar el aviso.';
    $alerta_tipo = 'danger';
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Editar Aviso #<?php echo $id_aviso; ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">
    
    <script src="https://cdn.tiny.cloud/1/fsu1zolakhx1ihn2slwt050tc9rgv1jejro3mwbyixxr2coh/tinymce/7/tinymce.min.js" referrerpolicy="origin"></script>
    
    <style>
        /* Estilos copiados de avisos_crear.php para que se vea igual */
        .tox-tinymce { border-radius: 8px!important; border: 1px solid #dee2e6!important; }
        .tox-promotion, .tox-statusbar__branding { display: none!important; }
    </style>
</head>
<body>
    <?php include 'navbar.php'; ?>

    <div class="container mt-4">
        <div class="card shadow-lg border-0">
            <div class="card-header bg-dark text-white d-flex justify-content-between align-items-center py-3">
                <h4 class="mb-0">Editar Aviso #<?php echo $id_aviso; ?></h4>
                <a href="avisos_lista.php" class="btn btn-sm btn-outline-light rounded-pill px-3">Volver</a>
            </div>
            
            <div class="card-body p-4">
                <?php if ($mensaje): ?>
                    <div class="alert alert-<?php echo $alerta_tipo; ?> alert-dismissible fade show" role="alert">
                        <?php echo $mensaje; ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                    </div>
                <?php endif; ?>

                <div class="alert alert-light border mb-4">
                    <small class="text-muted">
                        <i class="fas fa-user me-1"></i> Creado por: <strong><?php echo $creador_nombre; ?></strong> 
                        <i class="fas fa-calendar-alt ms-3 me-1"></i> <?php echo $fecha_publicacion; ?>
                        <?php if ($fecha_actualizacion_display): ?>
                            <i class="fas fa-edit ms-3 me-1"></i> Editado: <?php echo $fecha_actualizacion_display; ?>
                        <?php endif; ?>
                    </small>
                </div>

                <form method="POST">
                    <div class="mb-4">
                        <label class="form-label fw-bold">TÍTULO</label>
                        <input type="text" name="titulo" class="form-control form-control-lg" value="<?php echo $titulo_display; ?>" required>
                    </div>
                    
                    <div class="mb-4">
                        <textarea id="editor" name="contenido"><?php echo $contenido_html; ?></textarea>
                    </div>
                    
                    <div class="form-check form-switch mb-4 ps-5">
                        <input class="form-check-input" type="checkbox" id="es_activo" name="es_activo" <?php echo $es_activo_checked; ?> style="transform: scale(1.3);">
                        <label class="form-check-label fw-bold ms-2" for="es_activo">
                            Mostrar aviso en Dashboard
                        </label>
                    </div>

                    <div class="d-grid gap-2 d-md-flex justify-content-md-end pt-3 border-top">
                        <button type="submit" class="btn btn-primary btn-lg px-5">Guardar Cambios</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    
    <script>
      // Configuración IDÉNTICA a avisos_crear.php
      tinymce.init({
        selector: '#editor', 
        height: 600, 
        language: 'es', 
        menubar: true,
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