<?php
// Archivo: avisos.php (¡AHORA CORREGIDO!)
session_start();
include 'conexion.php';
include 'funciones_permisos.php'; // Incluir la función de permisos

// 1. PROTEGER LA PÁGINA (¡USANDO EL PERMISO CORRECTO!)
// (Este permiso 'acceso_avisos' SÍ aparecerá en admin_roles.php)
if (!isset($_SESSION['usuario_id']) || !tiene_permiso('acceso_avisos', $pdo)) {
    $_SESSION['action_error_message'] = "No tiene permiso para ver los avisos.";
    header("Location: dashboard.php");
    exit();
}

// (Variables para la lógica del navbar y permisos de gestión)
$id_usuario_nav = $_SESSION['usuario_id'] ?? 0;
$rol_usuario_nav = $_SESSION['usuario_rol'] ?? 'empleado'; // (Se usa para el botón "Gestionar")
$puede_gestionar_avisos = tiene_permiso('acceso_avisos_gestionar', $pdo);
$puede_crear_avisos = tiene_permiso('acceso_avisos_crear', $pdo);
$error_sql = null;


// Funciones de ayuda: Simplificadas ya que no hay 'prioridad'
function getAvisoPrioridadInfo($prioridad = 'informativo') { 
    return ['class' => 'info', 'icon' => 'info-circle', 'text' => 'Informativo'];
}
function get_first_image_url($html) {
    $pattern = '/<img.*?src=["\'](.*?)["\'].*?>/i';
    if (preg_match($pattern, $html, $matches)) { return $matches[1]; }
    return null; 
}


try {
    // 2. CONSULTA SQL CORREGIDA: Incluye el filtro activo y elimina 'prioridad'.
    $sql_avisos = "
        SELECT 
            a.id_aviso, a.titulo, a.contenido, a.fecha_publicacion, 
            COALESCE(u.nombre_completo, 'Usuario Eliminado') AS creador_nombre
        FROM avisos a
        LEFT JOIN usuarios u ON a.id_creador = u.id_usuario
        WHERE a.es_activo = 1 /* <-- FILTRO CRÍTICO PARA MOSTRAR SÓLO ACTIVOS */
        ORDER BY a.fecha_publicacion DESC
    ";
    $stmt_avisos = $pdo->prepare($sql_avisos);
    $stmt_avisos->execute();
    $avisos_a_mostrar = $stmt_avisos->fetchAll(PDO::FETCH_ASSOC);

} catch (PDOException $e) {
    $avisos_a_mostrar = [];
    $error_sql = "Error de conexión o consulta de datos: " . $e->getMessage();
    error_log("Error CRÍTICO de BD en avisos.php: " . $e->getMessage());
}

// 3. Incluir el Navbar (DESPUÉS de la lógica de permisos)
include 'navbar.php'; 
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Avisos y Comunicación Interna</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">
    <style>
        .aviso-contenido-html img { max-width: 100%; height: auto; border-radius: 5px; margin: 10px 0; }
        .aviso-card { border-left: 5px solid; transition: transform 0.2s ease, box-shadow 0.2s ease; }
        .aviso-card:hover { transform: translateY(-2px); box-shadow: 0 .5rem 1rem rgba(0,0,0,.15)!important; }
        .page-header { border-bottom: 1px solid #dee2e6; padding-bottom: 1rem; margin-bottom: 2rem; }
    </style>
</head>
<body>
    <div class="container mt-4">
        
        <div class="page-header d-flex justify-content-between align-items-center">
            <div>
                <h1 class="h3 mb-1 text-gray-800">
                    <i class="fas fa-bullhorn me-2 text-primary"></i> Avisos y Comunicación Interna
                </h1>
                <p class="text-muted mb-0">Mantente al día con los comunicados oficiales del equipo.</p>
            </div>
            
            <div class="text-end">
                <?php if ($puede_gestionar_avisos): ?>
                    <a href="avisos_lista.php" class="btn btn-primary">
                        <i class="fas fa-edit me-2"></i> Gestionar Avisos
                    </a>
                <?php endif; ?>
                <?php if ($puede_crear_avisos): ?>
                    <a href="avisos_crear.php" class="btn btn-success">
                        <i class="fas fa-plus me-2"></i> Crear Nuevo Aviso
                    </a>
                <?php endif; ?>
            </div>
        </div>
        
        <?php if ($error_sql): ?>
            <div class="alert alert-danger" role="alert">
                <i class="fas fa-exclamation-triangle me-2"></i> **Error Crítico de Base de Datos:** <?php echo $error_sql; ?>
            </div>
        <?php endif; ?>

        <?php if (empty($avisos_a_mostrar)): ?>
            <div class="alert alert-info text-center" role="alert">
                <i class="fas fa-info-circle me-2"></i> Actualmente no hay avisos activos.
            </div>
        <?php else: ?>
            <div class="row g-4">
                <?php foreach ($avisos_a_mostrar as $aviso): 
                    $info = getAvisoPrioridadInfo(); 
                    $border_color = 'border-' . $info['class'];
                    $text_color = 'text-' . $info['class'];
                    
                    $contenido_limpio = strip_tags($aviso['contenido']);
                    $snippet = substr($contenido_limpio, 0, 150);
                    if (strlen($contenido_limpio) > 150) {
                        $snippet .= '...';
                    }
                    $image_url = get_first_image_url($aviso['contenido']);
                ?>
                    <div class="col-12 col-md-6 col-lg-4">
                        <div class="card h-100 shadow-sm aviso-card <?php echo $border_color; ?>" style="border-left-width: 5px;">
                            <div class="card-header bg-light d-flex justify-content-between align-items-center">
                                <span class="badge bg-<?php echo $info['class']; ?> text-uppercase">
                                    <i class="fas fa-<?php echo $info['icon']; ?> me-1"></i> <?php echo $info['text']; ?>
                                </span>
                                <small class="text-muted">Publicado: <?php echo date('d/m/Y H:i', strtotime($aviso['fecha_publicacion'])); ?></small>
                            </div>
                            <div class="card-body">
                                <h5 class="card-title <?php echo $text_color; ?> fw-bold"><?php echo htmlspecialchars($aviso['titulo']); ?></h5>
                                <p class="card-text text-muted small mb-3">Por: <?php echo htmlspecialchars($aviso['creador_nombre']); ?></p>
                                
                                <div class="d-flex align-items-start">
                                    <?php if ($image_url): ?>
                                        <div style="flex-shrink: 0; width: 50px; height: 50px; overflow: hidden; border-radius: 4px; border: 1px solid #dee2e6; margin-right: 10px;">
                                            <img src="<?php echo htmlspecialchars($image_url); ?>" alt="Preview" style="width: 100%; height: 100%; object-fit: cover;">
                                        </div>
                                        <p class="card-text small mb-0" style="flex-grow: 1;"><?php echo htmlspecialchars($snippet); ?></p>
                                    <?php else: ?>
                                        <p class="card-text small mb-0"><?php echo htmlspecialchars($snippet); ?></p>
                                    <?php endif; ?>
                                </div>
                                
                            </div>
                            <div class="card-footer text-end bg-white border-top-0">
                                <button type="button" class="btn btn-sm btn-outline-primary" data-bs-toggle="modal" data-bs-target="#avisoModal-<?php echo $aviso['id_aviso']; ?>">
                                    Ver Detalle
                                </button>
                            </div>
                        </div>
                    </div>
                    
                    <div class="modal fade" id="avisoModal-<?php echo $aviso['id_aviso']; ?>" tabindex="-1" aria-labelledby="avisoModalLabel-<?php echo $aviso['id_aviso']; ?>" aria-hidden="true">
                        <div class="modal-dialog modal-dialog-centered modal-lg">
                            <div class="modal-content">
                                <div class="modal-header bg-<?php echo $info['class']; ?> text-white">
                                    <h5 class="modal-title" id="avisoModalLabel-<?php echo $aviso['id_aviso']; ?>">
                                        <i class="fas fa-<?php echo $info['icon']; ?> me-2"></i> <?php echo htmlspecialchars($aviso['titulo']); ?>
                                    </h5>
                                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                                </div>
                                <div class="modal-body">
                                    <div class="mb-3 small text-muted">
                                        <i class="fas fa-user-edit me-1"></i> Por: <?php echo htmlspecialchars($aviso['creador_nombre']); ?>
                                        <span class="ms-3"><i class="fas fa-calendar-alt me-1"></i> Publicado: <?php echo date('d/m/Y H:i', strtotime($aviso['fecha_publicacion'])); ?></span>
                                    </div>
                                    <hr>
                                    <div class="aviso-contenido-html"><?php echo $aviso['contenido']; ?></div> 
                                </div>
                                <div class="modal-footer">
                                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cerrar</button>
                                </div>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>

    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
    document.addEventListener('DOMContentLoaded', function() {
        const urlParams = new URLSearchParams(window.location.search);
        const avisoId = urlParams.get('show_id');

        if (avisoId) {
            const modalElement = document.getElementById('avisoModal-' + avisoId);

            if (modalElement) {
                const avisoModal = new bootstrap.Modal(modalElement);
                avisoModal.show();
                
                if (window.history.replaceState) {
                    const newUrl = window.location.protocol + "//" + window.location.host + window.location.pathname;
                    window.history.replaceState(null, null, newUrl);
                }
            }
        }
    });
    </script>
    
</body>
</html>