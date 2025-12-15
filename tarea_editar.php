<?php
// Archivo: tarea_editar.php (CON ADJUNTO INICIAL Y NOTIFICACIÓN CORREGIDA)
// *** MODIFICADO (v2) POR GEMINI PARA PERMITIR EDITAR AL 'encargado' ***
// *** MODIFICADO (v3) POR GEMINI PARA MOSTRAR MODAL DE ÉXITO ***
// *** MODIFICADO (v4) POR GEMINI PARA SOPORTE MULTI-USUARIO ***
session_start();
include 'conexion.php';
include_once 'funciones_permisos.php';

// 1. Proteger la página
if (!isset($_SESSION['usuario_id']) || (!tiene_permiso('tareas_gestionar', $pdo) && $_SESSION['usuario_rol'] !== 'admin')) {
    header("Location: dashboard.php");
    exit();
}

$id_creador = $_SESSION['usuario_id'];
$id_tarea = (int)($_GET['id'] ?? 0);
$mensaje = '';
$alerta_tipo = '';
$show_edit_success_modal = false;

if ($id_tarea <= 0) {
    header("Location: tareas_lista.php");
    exit();
}

function upload_files_edit($file_array) {
    $upload_dir = 'uploads/tareas/';
    if (!is_dir($upload_dir)) { if (!mkdir($upload_dir, 0777, true)) { error_log("Error: No se pudo crear " . $upload_dir); return []; } }
    $results = []; $file_count = count($file_array['name']);
    for ($i = 0; $i < $file_count; $i++) {
        if (isset($file_array['error'][$i]) && $file_array['error'][$i] === UPLOAD_ERR_OK && !empty($file_array['name'][$i])) {
            $f_info = ['name' => $file_array['name'][$i], 'tmp_name' => $file_array['tmp_name'][$i]];
            $f_orig = basename($f_info['name']); $ext = pathinfo($f_orig, PATHINFO_EXTENSION); $f_serv = uniqid('tarea_', true) . '.' . $ext; $ruta = $upload_dir . $f_serv;
            if (move_uploaded_file($f_info['tmp_name'], $ruta)) { $results[] = ['nombre_original' => $f_orig, 'nombre_servidor' => $f_serv]; } else { error_log("Error mover: " . $f_orig); }
        } elseif (isset($file_array['error'][$i]) && $file_array['error'][$i] !== UPLOAD_ERR_NO_FILE) { error_log("Error subida #" . $i . ": " . $file_array['error'][$i]); }
    } return $results;
}

// --- Obtener datos de la tarea ANTES de cualquier cambio ---
$tarea_original = null; 
$ids_asignados_originales = []; // Array de IDs
$id_asignado_original_principal = 0; // Para la tabla legacy

try {
    $sql_orig = "SELECT t.*, u.nombre_completo AS asignado_nombre_orig, c.nombre AS categoria_nombre_orig FROM tareas t LEFT JOIN usuarios u ON t.id_asignado = u.id_usuario LEFT JOIN categorias c ON t.id_categoria = c.id_categoria WHERE t.id_tarea = :id_tarea";
    $stmt_orig = $pdo->prepare($sql_orig);
    $stmt_orig->execute([':id_tarea' => $id_tarea]);
    $tarea_original = $stmt_orig->fetch(PDO::FETCH_ASSOC);
    
    if ($tarea_original) {
        $id_asignado_original_principal = (int)$tarea_original['id_asignado'];
        
        // --- MULTI-USUARIO: Obtener todos los asignados ---
        $stmt_asig = $pdo->prepare("SELECT id_usuario FROM tareas_asignaciones WHERE id_tarea = :id");
        $stmt_asig->execute([':id' => $id_tarea]);
        $ids_asignados_originales = $stmt_asig->fetchAll(PDO::FETCH_COLUMN);
        
        // Fallback si la tabla nueva está vacía
        if (empty($ids_asignados_originales) && $id_asignado_original_principal > 0) {
            $ids_asignados_originales[] = $id_asignado_original_principal;
        }
    } else {
        header("Location: tareas_lista.php?error=" . urlencode("Tarea no encontrada para editar."));
        exit();
    }
} catch (PDOException $e) {
    error_log("Error al obtener datos originales en tarea_editar.php: " . $e->getMessage());
}

// Variables para llenar el formulario
$titulo = $tarea_original['titulo'] ?? '';
$descripcion = $tarea_original['descripcion'] ?? '';
$fecha_limite = $tarea_original['fecha_limite'] ?? '';
$prioridad = $tarea_original['prioridad'] ?? '';
$ids_asignados_seleccionados = $ids_asignados_originales; // Array para el select
$id_categoria_seleccionada = $tarea_original['id_categoria'] ?? '';
$adjunto_obligatorio_seleccionado = isset($tarea_original['adjunto_obligatorio']) ? (int)$tarea_original['adjunto_obligatorio'] : 1;

// 2. Manejar el POST (Guardar Cambios)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $titulo_nuevo = trim($_POST['titulo'] ?? ''); 
    $descripcion_nueva = trim($_POST['descripcion'] ?? ''); 
    
    // --- CAMBIO MULTI-USUARIO ---
    $ids_asignados_nuevos = isset($_POST['ids_asignados']) && is_array($_POST['ids_asignados']) ? $_POST['ids_asignados'] : [];
    $id_asignado_nuevo_principal = !empty($ids_asignados_nuevos) ? (int)$ids_asignados_nuevos[0] : 0;
    // ----------------------------

    $id_categoria_nueva = (int)($_POST['id_categoria'] ?? 0); 
    $fecha_limite_nueva = $_POST['fecha_limite'] ?? ''; 
    $prioridad_nueva = $_POST['prioridad'] ?? ''; 
    $adjunto_obligatorio_nuevo = isset($_POST['adjunto_obligatorio']) && $_POST['adjunto_obligatorio'] == '0' ? 0 : 1;
    $estado_actual = $tarea_original['estado'] ?? 'asignada';

    $prioridades_validas = ['urgente', 'alta', 'media', 'baja'];
    $prioridad_nueva_lower = strtolower($prioridad_nueva); 

    if (empty($titulo_nuevo) || empty($descripcion_nueva) || empty($ids_asignados_nuevos) || $id_categoria_nueva <= 0 || empty($fecha_limite_nueva) || !in_array($prioridad_nueva_lower, $prioridades_validas)) {
        $mensaje = "Error: Faltan campos obligatorios o la prioridad no es válida."; 
        $alerta_tipo = 'danger';
        $titulo = $titulo_nuevo; $descripcion = $descripcion_nueva; $ids_asignados_seleccionados = $ids_asignados_nuevos; $id_categoria_seleccionada = $id_categoria_nueva; $fecha_limite = $fecha_limite_nueva; $prioridad = $prioridad_nueva; $adjunto_obligatorio_seleccionado = $adjunto_obligatorio_nuevo;
    } else {
        $pdo->beginTransaction();
        $adjuntos_exitosos = [];
        try {
            // 2.3 ACTUALIZACIÓN
            // A. Tabla Principal
            $sql_update = "UPDATE tareas SET titulo = :titulo, descripcion = :descripcion, id_asignado = :id_asignado, id_categoria = :id_categoria, fecha_limite = :fecha_limite, prioridad = :prioridad, adjunto_obligatorio = :adjunto_obligatorio WHERE id_tarea = :id_tarea";
            $stmt_update = $pdo->prepare($sql_update);
            $stmt_update->execute([
                ':titulo' => $titulo_nuevo, 
                ':descripcion' => $descripcion_nueva, 
                ':id_asignado' => $id_asignado_nuevo_principal, 
                ':id_categoria' => $id_categoria_nueva, 
                ':fecha_limite' => $fecha_limite_nueva, 
                ':prioridad' => $prioridad_nueva_lower, 
                ':adjunto_obligatorio' => $adjunto_obligatorio_nuevo, 
                ':id_tarea' => $id_tarea
            ]);

            // B. Tabla Asignaciones (Borrar y Reinsertar)
            $pdo->prepare("DELETE FROM tareas_asignaciones WHERE id_tarea = :id")->execute([':id' => $id_tarea]);
            $stmt_ins = $pdo->prepare("INSERT INTO tareas_asignaciones (id_tarea, id_usuario) VALUES (:id, :user)");
            foreach ($ids_asignados_nuevos as $uid) {
                $stmt_ins->execute([':id' => $id_tarea, ':user' => $uid]);
            }

            $mensaje = "¡Tarea #{$id_tarea} actualizada con éxito!"; 
            $alerta_tipo = 'success';
            
            // Subida de adjuntos (sin cambios)
            if (isset($_FILES['adjuntos']) && $_FILES['adjuntos']['error'][0] !== UPLOAD_ERR_NO_FILE) {
                $adjuntos_exitosos = upload_files_edit($_FILES['adjuntos']);
                if (!empty($adjuntos_exitosos)) {
                    $sql_adjunto = "INSERT INTO adjuntos_tarea (id_tarea, nombre_archivo, ruta_archivo, tipo_adjunto, id_usuario_subida, fecha_subida) VALUES (:id_tarea, :nombre, :ruta, 'inicial', :id_user, NOW())";
                    $stmt_adjunto = $pdo->prepare($sql_adjunto);
                    foreach ($adjuntos_exitosos as $adj) {
                        $stmt_adjunto->execute([
                            ':id_tarea' => $id_tarea, 
                            ':nombre' => $adj['nombre_original'], 
                            ':ruta' => $adj['nombre_servidor'], 
                            ':id_user' => $id_creador
                        ]);
                    }
                    $mensaje .= " Se agregaron " . count($adjuntos_exitosos) . " adjunto(s) inicial(es).";
                }
            }

            // ===============================================================
            // ========= NOTIFICACIÓN MULTI-USUARIO INTELIGENTE =========
            // ===============================================================
            $usuarios_nuevos = array_diff($ids_asignados_nuevos, $ids_asignados_originales);
            $usuarios_removidos = array_diff($ids_asignados_originales, $ids_asignados_nuevos);

            if (!empty($usuarios_nuevos) || !empty($usuarios_removidos)) {
                
                $protocolo = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? "https" : "http"); 
                $host = $_SERVER['HTTP_HOST']; 
                $ruta_base = rtrim(dirname($_SERVER['PHP_SELF']), '/\\'); 
                $url_base_absoluta = $protocolo . '://' . $host . $ruta_base;
                
                $sql_cat_nombre = "SELECT nombre FROM categorias WHERE id_categoria = :id_cat";
                $stmt_cat_nombre = $pdo->prepare($sql_cat_nombre);
                $stmt_cat_nombre->execute([':id_cat' => $id_categoria_nueva]);
                $nombre_categoria_actual = $stmt_cat_nombre->fetchColumn() ?: 'N/A';

                $task_params = http_build_query([
                    'task_title' => $titulo_nuevo,
                    'task_desc' => substr($descripcion_nueva, 0, 150) . (strlen($descripcion_nueva) > 150 ? '...' : ''), 
                    'task_state' => ucfirst(str_replace('_', ' ', $estado_actual)), 
                    'task_prio' => ucfirst($prioridad_nueva_lower),
                    'task_cat' => $nombre_categoria_actual,
                ]);

                $url_tarea_ver = $url_base_absoluta . "/tarea_ver.php?id={$id_tarea}";
                $sql_notif = "INSERT INTO notificaciones (id_usuario_destino, mensaje, url, tipo, leida, fecha_creacion) VALUES (:id_destino, :mensaje, :url, :tipo, 0, NOW())";
                $stmt_notif = $pdo->prepare($sql_notif);

                // A) Notificar a los NUEVOS
                foreach ($usuarios_nuevos as $id_nuevo) {
                    $mensaje_notif = "Se te ha asignado la tarea #{$id_tarea}: {$titulo_nuevo}.";
                    $url_final = $url_tarea_ver . "&reasignada_a={$id_nuevo}&{$task_params}";
                    $stmt_notif->execute([':id_destino' => $id_nuevo, ':mensaje' => $mensaje_notif, ':url' => $url_final, ':tipo' => 'tarea_reasignada_nueva']);
                }

                // B) Notificar a los REMOVIDOS (Opcional, pero útil)
                foreach ($usuarios_removidos as $id_removido) {
                    $mensaje_notif = "Has sido desasignado de la tarea #{$id_tarea}: {$titulo_nuevo}.";
                    $url_final = "tareas_lista.php"; // Ya no la puede ver, así que ir a lista
                    $stmt_notif->execute([':id_destino' => $id_removido, ':mensaje' => $mensaje_notif, ':url' => $url_final, ':tipo' => 'tarea_reasignada_anterior']);
                }
            }
            
            $pdo->commit();
            $show_edit_success_modal = true;

            $titulo = $titulo_nuevo; $descripcion = $descripcion_nueva; $ids_asignados_seleccionados = $ids_asignados_nuevos; $id_categoria_seleccionada = $id_categoria_nueva; $fecha_limite = $fecha_limite_nueva; $prioridad = $prioridad_nueva; $adjunto_obligatorio_seleccionado = $adjunto_obligatorio_nuevo;

        } catch (PDOException $e) { 
            $pdo->rollBack(); 
            foreach ($adjuntos_exitosos as $adj) { @unlink('uploads/tareas/' . $adj['nombre_servidor']); }
            $mensaje = "Error de base de datos al actualizar: " . $e->getMessage(); 
            $alerta_tipo = 'danger'; 
            error_log("Error DB en tarea_editar.php (POST): " . $e->getMessage()); 
            
            $titulo = $titulo_nuevo; $descripcion = $descripcion_nueva; $ids_asignados_seleccionados = $ids_asignados_nuevos; $id_categoria_seleccionada = $id_categoria_nueva; $fecha_limite = $fecha_limite_nueva; $prioridad = $prioridad_nueva; $adjunto_obligatorio_seleccionado = $adjunto_obligatorio_nuevo; 
        }
    }
}

// 3. Cargar listados necesarios
try { 
    $stmt_cats = $pdo->query("SELECT id_categoria, nombre FROM categorias ORDER BY nombre"); 
    $categorias = $stmt_cats->fetchAll(); 
    $stmt_users = $pdo->query("SELECT id_usuario, nombre_completo, rol FROM usuarios WHERE rol IN ('empleado', 'admin') AND activo = 1 ORDER BY nombre_completo"); 
    $empleados = $stmt_users->fetchAll(); 
} catch (PDOException $e) { 
    die("Error al cargar datos necesarios para editar: " . $e->getMessage()); 
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8"> <meta name="viewport" content="width=device-width, initial-scale=1.0"> <title>Editar Tarea #<?php echo htmlspecialchars($id_tarea); ?></title> <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet"> <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>
<body>
    <?php include 'navbar.php'; ?>
    <div class="container mt-4">
        <div class="d-flex justify-content-between align-items-center mb-4"><h1>Editar Tarea <span class="badge bg-secondary">#<?php echo htmlspecialchars($id_tarea); ?></span></h1><a href="tarea_ver.php?id=<?php echo htmlspecialchars($id_tarea); ?>" class="btn btn-outline-primary"><i class="fas fa-eye me-1"></i> Ver Tarea</a></div>
        
        <?php if (!empty($mensaje) && !$show_edit_success_modal): ?>
            <div class="alert alert-<?php echo $alerta_tipo; ?> alert-dismissible fade show" role="alert"><?php echo $mensaje; ?><button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button></div>
        <?php endif; ?>

        <form method="POST" action="tarea_editar.php?id=<?php echo htmlspecialchars($id_tarea); ?>" enctype="multipart/form-data">
            <div class="card mb-4 shadow-sm"><div class="card-header bg-primary text-white"><i class="fas fa-info-circle me-1"></i> Detalles Principales</div><div class="card-body"><div class="mb-3"><label for="titulo" class="form-label"><i class="fas fa-heading"></i> Título (*)</label><input type="text" class="form-control" id="titulo" name="titulo" value="<?php echo htmlspecialchars($titulo); ?>" required maxlength="255"></div><div class="mb-3"><label for="descripcion" class="form-label"><i class="fas fa-align-left"></i> Descripción (*)</label><textarea class="form-control" id="descripcion" name="descripcion" rows="5" required><?php echo htmlspecialchars($descripcion); ?></textarea></div><div class="row"><div class="col-md-6 mb-3"><label for="ids_asignados" class="form-label"><i class="fas fa-user-tag"></i> Asignados (*)</label><select class="form-select" id="ids_asignados" name="ids_asignados[]" multiple required style="min-height: 120px;"><?php foreach ($empleados as $empleado): ?><option value="<?php echo $empleado['id_usuario']; ?>"<?php echo (in_array($empleado['id_usuario'], $ids_asignados_seleccionados)) ? ' selected' : ''; ?>><?php echo htmlspecialchars($empleado['nombre_completo']) . " (".ucfirst($empleado['rol']).")"; ?></option><?php endforeach; ?></select><small class="text-muted">Ctrl+Clic para seleccionar varios.</small></div><div class="col-md-6 mb-3"><label for="id_categoria" class="form-label"><i class="fas fa-boxes"></i> Categoría (*)</label><select class="form-select" id="id_categoria" name="id_categoria" required><option value="">Seleccione Categoría</option><?php foreach ($categorias as $cat): ?><option value="<?php echo $cat['id_categoria']; ?>"<?php echo ($id_categoria_seleccionada == $cat['id_categoria']) ? ' selected' : ''; ?>><?php echo htmlspecialchars($cat['nombre']); ?></option><?php endforeach; ?></select></div></div><div class="row"><div class="col-md-6 mb-3"><label for="fecha_limite" class="form-label"><i class="fas fa-calendar-alt"></i> Fecha Límite (*)</label><input type="date" class="form-control" id="fecha_limite" name="fecha_limite" value="<?php echo htmlspecialchars($fecha_limite); ?>" required></div><div class="col-md-6 mb-3"><label for="prioridad" class="form-label"><i class="fas fa-exclamation-triangle"></i> Prioridad (*)</label><select class="form-select" id="prioridad" name="prioridad" required><option value="">Seleccione Prioridad</option><?php $prioridades_lista = ['urgente', 'alta', 'media', 'baja']; ?><?php foreach ($prioridades_lista as $prio): ?><option value="<?php echo $prio; ?>"<?php echo (strtolower($prioridad) === $prio) ? ' selected' : ''; ?>><?php echo htmlspecialchars(ucfirst($prio)); ?></option><?php endforeach; ?></select></div></div></div></div>
            
            <div class="card mb-4 shadow-sm">
                <div class="card-header bg-warning text-dark"><i class="fas fa-paperclip me-1"></i> Adjuntar Archivos Iniciales</div>
                <div class="card-body">
                    <div class="mb-3">
                        <label for="adjuntos" class="form-label">Adjuntos (Opcional)</label>
                        <input class="form-control" type="file" id="adjuntos" name="adjuntos[]" multiple>
                        <small class="text-muted d-block">Utilice esto para agregar nuevos archivos a la tarea.</small>
                    </div>
                </div>
            </div>
            <div class="card mb-4 shadow-sm"><div class="card-header bg-secondary text-white"><i class="fas fa-cog me-1"></i> Configuración Adicional</div><div class="card-body"><label class="form-label d-block"><i class="fas fa-paperclip"></i> ¿Adjunto Final Obligatorio?</label><div class="form-check form-check-inline"><input class="form-check-input" type="radio" name="adjunto_obligatorio" id="adjunto_obligatorio_si" value="1"<?php echo ($adjunto_obligatorio_seleccionado == 1) ? ' checked' : ''; ?>><label class="form-check-label" for="adjunto_obligatorio_si"><i class="fas fa-check-circle text-success"></i> Sí</label></div><div class="form-check form-check-inline"><input class="form-check-input" type="radio" name="adjunto_obligatorio" id="adjunto_obligatorio_no" value="0"<?php echo ($adjunto_obligatorio_seleccionado == 0) ? ' checked' : ''; ?>><label class="form-check-label" for="adjunto_obligatorio_no"><i class="fas fa-times-circle text-danger"></i> No</label></div><small class="text-muted mt-2 d-block">Define si el técnico debe subir un archivo para finalizar.</small></div></div>
            <div class="text-center mb-5"><button type="submit" class="btn btn-success btn-lg"><i class="fas fa-save"></i> Guardar Cambios</button><a href="tareas_lista.php" class="btn btn-outline-secondary ms-2">Cancelar</a></div>
        </form>
    </div>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <div class="toast-container position-fixed bottom-0 end-0 p-3" id="notificationToastContainer" style="z-index: 1080;"></div>

    <div class="modal fade" id="editSuccessModal" tabindex="-1" data-bs-backdrop="static" data-bs-keyboard="false">
      <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-success border-5">
          <div class="modal-header bg-success text-white">
            <h5 class="modal-title"><i class="fas fa-check-circle me-2"></i> ¡Tarea Editada Exitosamente!</h5>
            </div>
          <div class="modal-body">
            <p class="lead">La Tarea N° <strong>#<?php echo htmlspecialchars($id_tarea); ?></strong> ha sido actualizada.</p>
            <?php if (!empty($mensaje) && $alerta_tipo === 'success'): ?>
                <div class="alert alert-info">
                    <strong>Detalles:</strong> <?php echo htmlspecialchars($mensaje); ?>
                </div>
            <?php endif; ?>
          </div>
          <div class="modal-footer justify-content-between">
            <a href="dashboard.php" class="btn btn-primary">
                <i class="fas fa-home me-1"></i> Ir al Inicio
            </a>
            <a href="tarea_ver.php?id=<?php echo htmlspecialchars($id_tarea); ?>" class="btn btn-success">
                <i class="fas fa-eye me-1"></i> Ver Tarea Editada
            </a>
          </div>
        </div>
      </div>
    </div>
    
    <?php if ($show_edit_success_modal): ?>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const successModal = new bootstrap.Modal(document.getElementById('editSuccessModal'));
            successModal.show();
        });
    </script>
    <?php endif; ?>
    <?php include 'footer.php'; ?>
    </body>
</html>