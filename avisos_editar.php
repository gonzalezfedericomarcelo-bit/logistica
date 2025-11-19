<?php
// Archivo: admin_remitos.php
session_start();
include 'conexion.php'; 
// 🛑 PASO CRÍTICO 1: DEBE INCLUIR ESTE ARCHIVO
include 'funciones_permisos.php'; 
// include 'navbar.php'; // Si tienes tu navbar aquí


$id_aviso = (int)($_GET['id'] ?? 0);
if ($id_aviso <= 0) {
    header("Location: avisos_lista.php");
    exit();
}

$mensaje = '';
$alerta_tipo = '';
$aviso_data = null;

// --- Variables de visualización inicializadas a valores seguros ---
$titulo_display = '';
$contenido_html = '';
$es_activo_checked = 'checked'; // Default a activo
$creador_nombre = 'N/A';
$fecha_publicacion = 'N/A';
$fecha_actualizacion_display = false;

// --- 2. Lógica para PROCESAR la EDICIÓN (POST) ---
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $titulo = trim($_POST['titulo'] ?? '');
    $contenido_html = $_POST['contenido_input'] ?? ''; 
    $es_activo = isset($_POST['es_activo']) ? 1 : 0; 
    $fecha_actualizacion = date('Y-m-d H:i:s');

    $contenido_limpio = strip_tags($contenido_html, '<img>'); 
    if (empty($titulo) || empty(trim(str_replace(['<p><br></p>', '<br>'], '', $contenido_limpio)))) {
        $mensaje = 'El título y el contenido del aviso son obligatorios.';
        $alerta_tipo = 'danger';
    } else {
        try {
            // Consulta de actualización
            $sql = "UPDATE avisos SET 
                    titulo = :titulo, 
                    contenido = :contenido, 
                    es_activo = :es_activo,
                    fecha_actualizacion = :fecha_actualizacion
                    WHERE id_aviso = :id_aviso";
            
            $stmt = $pdo->prepare($sql);
            $stmt->execute([
                ':titulo' => $titulo,
                // El campo 'contenido' debe ser LONGTEXT en la DB
                ':contenido' => $contenido_html, 
                ':es_activo' => $es_activo,
                ':fecha_actualizacion' => $fecha_actualizacion,
                ':id_aviso' => $id_aviso
            ]);
            
            $mensaje = '¡Aviso actualizado exitosamente!';
            $alerta_tipo = 'success';
            
        } catch (PDOException $e) {
            $mensaje = 'Error al actualizar el aviso: ' . $e->getMessage();
            $alerta_tipo = 'danger';
            error_log("Error de DB en avisos_editar (update): " . $e->getMessage());
        }
    }
}

// --- 3. Obtener los DATOS DEL AVISO (GET o después del POST) ---
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
        $mensaje = 'Aviso no encontrado. Redirigiendo...'; 
        $alerta_tipo = 'danger';
        header("Location: avisos_lista.php");
        exit();
    }
    
    // Si la carga de DB fue exitosa, populamos las variables de visualización
    
    // Si no es POST (primera carga), o si el POST falló la validación, usamos los datos de la DB
    if ($_SERVER['REQUEST_METHOD'] !== 'POST' || $alerta_tipo === 'success') {
        $contenido_html = $aviso_data['contenido'] ?? '';
        $titulo_display = htmlspecialchars($aviso_data['titulo'] ?? '');
        $es_activo_checked = ($aviso_data['es_activo'] ?? 1) ? 'checked' : '';
    } else {
        // Si el POST falló la validación, usamos los datos del POST
        $titulo_display = htmlspecialchars($_POST['titulo'] ?? '');
        $es_activo_checked = isset($_POST['es_activo']) ? 'checked' : '';
    }

    // Datos de la cabecera
    $creador_nombre = htmlspecialchars($aviso_data['creador_nombre']);
    $fecha_publicacion = date('d/m/Y H:i', strtotime($aviso_data['fecha_publicacion']));
    $fecha_actualizacion_display = $aviso_data['fecha_actualizacion'] ? date('d/m/Y H:i', strtotime($aviso_data['fecha_actualizacion'])) : false;


} catch (PDOException $e) {
    // Si falla aquí, mostramos el error de la DB
    $mensaje = 'Error al cargar los datos del aviso. (Verifique el tipo de columna "contenido" en la DB).';
    $alerta_tipo = 'danger';
    error_log("Error de DB en avisos_editar (fetch): " . $e->getMessage());
}

?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Editar Aviso #<?php echo $id_aviso; ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">
    
    <link href="https://cdn.jsdelivr.net/npm/quill@2.0.2/dist/quill.snow.css" rel="stylesheet" />
    
    <link href="https://cdn.jsdelivr.net/npm/quill-image-resize-module@3.0.0/image-resize.min.css" rel="stylesheet">

    <style>
        /* Estilo para dar altura al editor de Quill */
        #editor-container {
            height: 300px;
        }
    </style>
</head>
<body>
    <?php include 'navbar.php'; ?>

    <div class="container mt-5">
        <h1 class="mb-4"><i class="fas fa-edit"></i> Editar Aviso Interno #<?php echo $id_aviso; ?></h1>

        <?php if ($mensaje): ?>
            <div class="alert alert-<?php echo $alerta_tipo; ?> alert-dismissible fade show" role="alert">
                <?php echo $mensaje; ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
        <?php endif; ?>

        <div class="card shadow-sm">
            <div class="card-header bg-primary text-white">
                Información del Aviso
            </div>
            <div class="card-body">
                <p class="small text-muted mb-3">
                    Creado por: <strong><?php echo $creador_nombre; ?></strong> 
                    el: <?php echo $fecha_publicacion; ?>
                    <?php if ($fecha_actualizacion_display): ?>
                        | Última Edición: <?php echo $fecha_actualizacion_display; ?>
                    <?php endif; ?>
                </p>
                
                <form method="POST" onsubmit="return submitForm()">
                    
                    <div class="mb-3">
                        <label for="titulo" class="form-label">Título del Aviso (*)</label>
                        <input type="text" class="form-control" id="titulo" name="titulo" value="<?php echo $titulo_display; ?>" required maxlength="255">
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Contenido Detallado (*)</label>
                        <div id="editor-container"><?php echo $contenido_html; ?></div>
                        <input type="hidden" name="contenido_input" id="contenido_input" required>
                    </div>
                    
                    <div class="form-check form-switch mb-4">
                        <input class="form-check-input" type="checkbox" id="es_activo" name="es_activo" <?php echo $es_activo_checked; ?>>
                        <label class="form-check-label" for="es_activo">
                            Aviso Activo (Se muestra en el Dashboard)
                        </label>
                    </div>

                    <button type="submit" class="btn btn-success w-100">
                        <i class="fas fa-save"></i> Guardar Cambios
                    </button>
                    
                </form>
            </div>
        </div>
        
        <div class="mt-4 text-center">
            <a href="avisos_lista.php" class="btn btn-outline-secondary"><i class="fas fa-arrow-left"></i> Volver a la Lista de Avisos</a>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    
    <script src="https://cdn.jsdelivr.net/npm/quill@2.0.2/dist/quill.js"></script>

    <script src="https://cdn.jsdelivr.net/npm/quill-image-resize-module@3.0.0/image-resize.min.js"></script>

    <script>
        const quill = new Quill('#editor-container', {
            theme: 'snow',
            modules: {
                toolbar: [
                    [{ 'header': [1, 2, 3, false] }],
                    ['bold', 'italic', 'underline', 'strike'],
                    ['blockquote', 'code-block'],
                    [{ 'list': 'ordered'}, { 'list': 'bullet' }],
                    [{ 'indent': '-1'}, { 'indent': '+1' }],
                    ['link', 'image'],
                    ['clean']
                ],
                // *** MÓDULO DE REDIMENSIÓN AGREGADO ***
                imageResize: {
                    displaySize: true // Muestra el tamaño de la imagen al arrastrar
                }
                // ***************************************
            }
        });

        // Función para mover el contenido del editor al campo oculto antes de enviar
        function submitForm() {
            // Obtener el HTML, incluyendo los estilos de tamaño de imagen agregados por el módulo
            const content = quill.root.innerHTML; 
            document.getElementById('contenido_input').value = content;
            return true;
        }

        // Cargamos el contenido existente en el editor de Quill
        const initialContent = document.getElementById('editor-container').innerHTML;
        if (initialContent.trim() !== '') {
            quill.clipboard.dangerouslyPasteHTML(initialContent);
        }
    </script>
</body>
</html>