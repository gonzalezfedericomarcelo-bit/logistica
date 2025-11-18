<?php
// Archivo: admin_usuarios.php
session_start();
include 'conexion.php';
// Asegúrate de que esta línea esté presente
include 'funciones_permisos.php'; 

// 1. Proteger la página (solo con permiso)
if (!isset($_SESSION['usuario_id']) || !tiene_permiso('acceso_avisos_crear', $pdo)) {
    header("Location: avisos.php?error=Acceso denegado");
    exit();
}
$mensaje = '';
$alerta_tipo = '';

$id_creador = $_SESSION['usuario_id'];
$mensaje = '';
$alerta_tipo = '';
$titulo_previo = '';
$contenido_previo = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $titulo = trim($_POST['titulo'] ?? '');
    // El contenido viene del campo oculto rellenado por JS
    $contenido_html = $_POST['contenido_input'] ?? '';
    $es_activo = isset($_POST['es_activo']) ? 1 : 0;
    $fecha_publicacion = date('Y-m-d H:i:s');

    // Guardar valores para repopular el formulario en caso de error
    $titulo_previo = htmlspecialchars($titulo);
    $contenido_previo = $contenido_html;
    
    // Validación: Contenido limpio (quita etiquetas, pero permite la imagen)
    // Quito <p><br></p> que es lo que Quill inserta cuando está vacío
    $contenido_limpio = strip_tags($contenido_html, '<img>');
    
    if (empty($titulo) || empty(trim(str_replace(['<p><br></p>', '<br>'], '', $contenido_limpio)))) {
        $mensaje = 'El título y el contenido del aviso son obligatorios.';
        $alerta_tipo = 'danger';
    } else {
        try {
            $sql = "INSERT INTO avisos (id_creador, titulo, contenido, es_activo, fecha_publicacion) 
                    VALUES (:id_creador, :titulo, :contenido, :es_activo, :fecha_publicacion)";
            
            $stmt = $pdo->prepare($sql);
            $stmt->execute([
                ':id_creador' => $id_creador,
                ':titulo' => $titulo,
                // El campo 'contenido' debe ser LONGTEXT en la DB
                ':contenido' => $contenido_html, 
                ':es_activo' => $es_activo,
                ':fecha_publicacion' => $fecha_publicacion
            ]);
            
            $mensaje = '¡Aviso creado exitosamente!';
            $alerta_tipo = 'success';
            
            // Limpiar formulario después de éxito
            $titulo_previo = '';
            $contenido_previo = '';
            
        } catch (PDOException $e) {
            $mensaje = 'Error al guardar el aviso: ' . $e->getMessage();
            $alerta_tipo = 'danger';
            error_log("Error de DB en avisos_crear: " . $e->getMessage());
        }
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Crear Nuevo Aviso</title>
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
        <h1 class="mb-4"><i class="fas fa-bullhorn"></i> Crear Nuevo Aviso Interno</h1>

        <?php if ($mensaje): ?>
            <div class="alert alert-<?php echo $alerta_tipo; ?> alert-dismissible fade show" role="alert">
                <?php echo $mensaje; ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
        <?php endif; ?>

        <div class="card shadow-sm">
            <div class="card-body">
                <form method="POST" onsubmit="return submitForm()">
                    
                    <div class="mb-3">
                        <label for="titulo" class="form-label">Título del Aviso (*)</label>
                        <input type="text" class="form-control" id="titulo" name="titulo" value="<?php echo $titulo_previo; ?>" required maxlength="255">
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Contenido Detallado (*)</label>
                        <div id="editor-container"><?php echo $contenido_previo; ?></div>
                        <input type="hidden" name="contenido_input" id="contenido_input" required>
                    </div>
                    
                    <div class="form-check form-switch mb-4">
                        <input class="form-check-input" type="checkbox" id="es_activo" name="es_activo" checked>
                        <label class="form-check-label" for="es_activo">
                            Aviso Activo (Se muestra en el Dashboard)
                        </label>
                    </div>

                    <button type="submit" class="btn btn-primary w-100">
                        <i class="fas fa-paper-plane"></i> Publicar Aviso
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
        
        // Cargar contenido previo en caso de error de formulario
        const initialContent = document.getElementById('editor-container').innerHTML;
        if (initialContent.trim() !== '') {
            quill.clipboard.dangerouslyPasteHTML(initialContent);
        }
    </script>
</body>
</html>