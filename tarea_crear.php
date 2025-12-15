<?php
// Archivo: tarea_crear.php (MODIFICADO: Agregada Selección de Destino/Área Dinámica)
// *** MODIFICADO (v4) PARA CORREGIR NOMBRE DE CREADOR EN PEDIDO FANTASMA ***
// *** MODIFICADO (v5) POR GEMINI PARA APLICAR PERMISO 'crear_tarea_directa' ***
// *** MODIFICADO (v6) POR GEMINI PARA INCLUIR ROLES 'auxiliar' y 'encargado' EN ASIGNACIÓN ***
// *** MODIFICADO (v7) POR GEMINI PARA SOPORTE MULTI-USUARIO ***
session_start();
include 'conexion.php'; 
include 'funciones_permisos.php'; 

// --- INICIO BLOQUE DE SEGURIDAD ---
if (!isset($_SESSION['usuario_id']) || !tiene_permiso('crear_tarea_directa', $pdo)) {
    $_SESSION['action_error_message'] = "Acceso denegado. No tiene permiso para crear tareas directas.";
    header("Location: dashboard.php");
    exit();
}
// --- FIN BLOQUE DE SEGURIDAD ---

function upload_files($file_array) { 
    $upload_dir = 'uploads/tareas/';
    if (!is_dir($upload_dir)) { if (!mkdir($upload_dir, 0777, true)) { error_log("Error: No se pudo crear " . $upload_dir); return []; } }
    $results = []; $file_count = count($file_array['name']);
    for ($i = 0; $i < $file_count; $i++) {
        if (isset($file_array['error'][$i]) && $file_array['error'][$i] === UPLOAD_ERR_OK && !empty($file_array['name'][$i])) {
            $f_info = ['name' => $file_array['name'][$i], 'tmp_name' => $file_array['tmp_name'][$i]];
            $f_orig = basename($f_info['name']); $ext = pathinfo($f_orig, PATHINFO_EXTENSION); $f_serv = uniqid('tarea_', true) . '.' . $ext;
            $ruta_completa = $upload_dir . $f_serv;
            if (move_uploaded_file($f_info['tmp_name'], $ruta_completa)) {
                $results[] = ['nombre_original' => $f_orig, 'nombre_servidor' => $f_serv];
            } else { error_log("Error al mover archivo: " . $f_orig); }
        } elseif (isset($file_array['error'][$i]) && $file_array['error'][$i] !== UPLOAD_ERR_NO_FILE) { error_log("Error de subida #" . $i . ": " . $file_array['error'][$i]); }
    }
    return $results;
}

$id_creador = $_SESSION['usuario_id'];
$mensaje = '';
$alerta_tipo = '';
$show_success_modal = false;
$new_task_id = 0;

// ******** INICIO LÓGICA DE CONVERSIÓN DE PEDIDO ********
$id_pedido_a_convertir = 0;
$titulo_previo = '';
$descripcion_previa = '';
$prioridad_previa = 'baja';
$telefono_previo = '';
$aviso_conversion = '';

if (isset($_GET['convertir_pedido']) && is_numeric($_GET['convertir_pedido'])) {
    $id_pedido_a_convertir = (int)$_GET['convertir_pedido'];

    try {
            $sql_pedido = "SELECT p.*, a.nombre as area_nombre_pedido, p.titulo_pedido, p.solicitante_telefono
               FROM pedidos_trabajo p
               LEFT JOIN areas a ON p.id_area = a.id_area
               WHERE p.id_pedido = :id_pedido AND p.estado_pedido = 'pendiente_encargado'";

        $stmt_pedido = $pdo->prepare($sql_pedido);
        $stmt_pedido->execute([':id_pedido' => $id_pedido_a_convertir]);
        $pedido_data = $stmt_pedido->fetch(PDO::FETCH_ASSOC);

        if ($pedido_data) {
            $titulo_previo = !empty($pedido_data['titulo_pedido'])
                     ? htmlspecialchars($pedido_data['titulo_pedido'])
                     : "Pedido #" . ($pedido_data['numero_orden'] ?? $id_pedido_a_convertir) . " - Área: " . ($pedido_data['area_nombre_pedido'] ?? 'N/A');

            $descripcion_previa = "SOLICITUD ORIGINAL (Pedido #" . ($pedido_data['numero_orden'] ?? $id_pedido_a_convertir) . "):\n" .
                          "--------------------------------------------------\n" .
                          htmlspecialchars($pedido_data['descripcion_sintomas']);

            $prioridad_previa = match($pedido_data['prioridad']) {
                'urgente' => 'urgente',
                'importante' => 'alta',
                'rutina' => 'media',
                default => 'baja'
            };
            
            $telefono_previo = htmlspecialchars($pedido_data['solicitante_telefono'] ?? '');
            
            $aviso_conversion = "Estás convirtiendo el Pedido N° " . $pedido_data['numero_orden'] . ". Asigna un técnico y categoría.";
            $alerta_tipo = 'info';

        } else {
            $id_pedido_a_convertir = 0;
            $aviso_conversion = "Error: El pedido de trabajo (ID: {$_GET['convertir_pedido']}) que intentas convertir no existe o ya fue procesado (Estado incorrecto).";
            $alerta_tipo = 'danger';
        }
    } catch (PDOException $e) {
        $aviso_conversion = "Error de DB al cargar el pedido: " . $e->getMessage();
        $alerta_tipo = 'danger';
    }
}
// ******** FIN LÓGICA DE CONVERSIÓN DE PEDIDO ********


// Lógica POST para guardar la TAREA
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $titulo = trim($_POST['titulo']);
    $descripcion = trim($_POST['descripcion']);
    
    // --- CAMBIO MULTI-USUARIO ---
    $ids_asignados = isset($_POST['ids_asignados']) && is_array($_POST['ids_asignados']) ? $_POST['ids_asignados'] : [];
    $id_asignado_principal = !empty($ids_asignados) ? (int)$ids_asignados[0] : 0; // El primero es el principal para compatibilidad
    // ----------------------------

    $id_categoria = (int)$_POST['id_categoria'];
    $prioridad = $_POST['prioridad'];
    $fecha_limite = empty($_POST['fecha_limite']) ? null : $_POST['fecha_limite'];
    $adjunto_obligatorio = (int)($_POST['adjunto_obligatorio'] ?? 0);
    $solicitante_telefono_post = trim($_POST['solicitante_telefono'] ?? '');
    
    $id_pedido_convertido = (int)($_POST['id_pedido_a_convertir'] ?? 0);
    
    // --- NUEVO: Recibir Ubicación ---
    $id_destino_post = (int)($_POST['id_destino_interno'] ?? 0);
    $id_area_post = (int)($_POST['id_area'] ?? 0);

    if (empty($titulo) || empty($descripcion) || empty($ids_asignados) || $id_categoria <= 0) {
        $mensaje = "Error: Título, Descripción, al menos un Asignado y Categoría son obligatorios.";
        $alerta_tipo = 'danger';
        $titulo_previo = $titulo;
        $descripcion_previa = $descripcion;
        $prioridad_previa = $prioridad;
        $telefono_previo = $solicitante_telefono_post;
    }
    else {
        $pdo->beginTransaction();
        try {
            
            $id_pedido_origen_final = 0;
            
            if ($id_pedido_convertido > 0) {
                // --- MODO CONVERTIR (Ya existe un Pedido) ---
                $sql_update_pedido = "UPDATE pedidos_trabajo 
                                      SET estado_pedido = 'aprobado',
                                          solicitante_telefono = :solic_tel
                                      WHERE id_pedido = :id_pedido 
                                      AND estado_pedido = 'pendiente_encargado'";
                $stmt_update_pedido = $pdo->prepare($sql_update_pedido);
                $stmt_update_pedido->execute([
                    ':id_pedido' => $id_pedido_convertido,
                    ':solic_tel' => empty($solicitante_telefono_post) ? null : $solicitante_telefono_post
                ]);
                
                if ($stmt_update_pedido->rowCount() == 0) {
                    error_log("Advertencia: Pedido #$id_pedido_convertido no estaba 'pendiente_encargado' al convertir.");
                }
                
                $id_pedido_origen_final = $id_pedido_convertido;
            
            } else {
                // --- MODO CREAR DIRECTO (Generar Pedido Fantasma) ---
            
                $sql_cat_nombre = "SELECT nombre FROM categorias WHERE id_categoria = :id_cat";
                $stmt_cat_nombre = $pdo->prepare($sql_cat_nombre);
                $stmt_cat_nombre->execute([':id_cat' => $id_categoria]);
                $nombre_categoria_para_pedido = $stmt_cat_nombre->fetchColumn() ?: 'Categoría Desconocida';

                // Si seleccionó un área, usamos su nombre, si no, usamos la categoría o "General"
                if ($id_area_post > 0) {
                    $stmt_area_name = $pdo->prepare("SELECT nombre FROM areas WHERE id_area = :id");
                    $stmt_area_name->execute([':id' => $id_area_post]);
                    $nombre_real_area = $stmt_area_name->fetchColumn();
                    if ($nombre_real_area) {
                        $nombre_categoria_para_pedido = $nombre_real_area;
                    }
                }

                $stmt_get_creator_name = $pdo->prepare("SELECT nombre_completo FROM usuarios WHERE id_usuario = :id");
                $stmt_get_creator_name->execute([':id' => $id_creador]);
                $nombre_admin_para_pedido = $stmt_get_creator_name->fetchColumn() ?: 'Usuario del Sistema';
                
                $default_area_id_para_pedido = ($id_area_post > 0) ? $id_area_post : 1; 

                $prioridad_pedido_map = match($prioridad) {
                    'urgente' => 'urgente',
                    'alta' => 'importante',
                    'media' => 'rutina',
                    'baja' => 'rutina',
                    default => 'rutina'
                };

                $numero_orden_generado = generar_nuevo_numero_orden($pdo);

                $sql_insert_pedido = "INSERT INTO pedidos_trabajo
                            (numero_orden, titulo_pedido, id_solicitante, id_auxiliar, id_area, area_solicitante, id_destino_interno, prioridad, fecha_requerida, descripcion_sintomas, solicitante_real_nombre, solicitante_telefono, fecha_emision, estado_pedido)
                        VALUES
                            (:num_orden, :titulo_ped, :id_solic, :id_aux, :id_area, :area_nombre, :id_dest, :prio, :fecha_req, :descrip, :solic_real, :solic_tel, NOW(), 'aprobado')";
                
                $stmt_insert_pedido = $pdo->prepare($sql_insert_pedido);
                $stmt_insert_pedido->execute([
                    ':num_orden' => $numero_orden_generado,
                    ':titulo_ped' => $titulo,
                    ':id_solic' => $id_creador,
                    ':id_aux' => $id_creador,
                    ':id_area' => $default_area_id_para_pedido,
                    ':area_nombre' => $nombre_categoria_para_pedido,
                    ':id_dest' => ($id_destino_post > 0) ? $id_destino_post : null, // Guardamos destino si existe
                    ':prio' => $prioridad_pedido_map,
                    ':fecha_req' => $fecha_limite,
                    ':descrip' => $descripcion,
                    ':solic_real' => $nombre_admin_para_pedido,
                    ':solic_tel' => empty($solicitante_telefono_post) ? null : $solicitante_telefono_post,
                ]);
                
                $id_pedido_origen_final = $pdo->lastInsertId();
            }
            
            // 7. Insertar la Tarea (Con el primer asignado como principal)
            $sql = "INSERT INTO tareas (titulo, descripcion, id_creador, id_asignado, id_categoria, prioridad, fecha_limite, adjunto_obligatorio, fecha_creacion, id_pedido_origen)
                    VALUES (:titulo, :descripcion, :id_creador, :id_asignado, :id_categoria, :prioridad, :fecha_limite, :adjunto_obligatorio, NOW(), :id_pedido_origen)";
            
            $stmt = $pdo->prepare($sql);
            $stmt->execute([
                ':titulo' => $titulo,
                ':descripcion' => $descripcion,
                ':id_creador' => $id_creador,
                ':id_asignado' => $id_asignado_principal,
                ':id_categoria' => $id_categoria,
                ':prioridad' => $prioridad,
                ':fecha_limite' => $fecha_limite,
                ':adjunto_obligatorio' => $adjunto_obligatorio,
                ':id_pedido_origen' => $id_pedido_origen_final
            ]);
            
            $new_task_id = $pdo->lastInsertId();

            // --- NUEVO: Guardar asignaciones múltiples en tabla intermedia ---
            $sql_assign = "INSERT INTO tareas_asignaciones (id_tarea, id_usuario) VALUES (:id_tarea, :id_usuario)";
            $stmt_assign = $pdo->prepare($sql_assign);
            foreach ($ids_asignados as $id_user_assign) {
                $stmt_assign->execute([':id_tarea' => $new_task_id, ':id_usuario' => $id_user_assign]);
            }
            
            // 8. Vincular el Pedido a la Tarea
            $sql_update_pedido = "UPDATE pedidos_trabajo 
                                  SET id_tarea_generada = :id_tarea 
                                  WHERE id_pedido = :id_pedido";
            $stmt_update_pedido = $pdo->prepare($sql_update_pedido);
            $stmt_update_pedido->execute([
                ':id_tarea' => $new_task_id,
                ':id_pedido' => $id_pedido_origen_final
            ]);

            // (Lógica de Adjuntos)
            if (isset($_FILES['adjuntos_iniciales']) && !empty($_FILES['adjuntos_iniciales']['name'][0])) {
                $adjuntos_subidos = upload_files($_FILES['adjuntos_iniciales']);
                if (!empty($adjuntos_subidos)) {
                    $sql_adjunto = "INSERT INTO adjuntos_tarea (id_tarea, tipo_adjunto, nombre_archivo, ruta_archivo, id_usuario_subida, fecha_subida) 
                                    VALUES (:id_tarea, 'inicial', :nombre, :ruta, :id_user, NOW())";
                    $stmt_adjunto = $pdo->prepare($sql_adjunto);
                    foreach ($adjuntos_subidos as $adj) {
                        $stmt_adjunto->execute([
                            ':id_tarea' => $new_task_id,
                            ':nombre' => $adj['nombre_original'],
                            ':ruta' => $adj['nombre_servidor'],
                            ':id_user' => $id_creador
                        ]);
                    }
                }
            }

            // Notificación a TODOS los usuarios asignados
            $nombre_admin = $_SESSION['usuario_nombre'];
            $mensaje_notif = "{$nombre_admin} te ha asignado una nueva tarea: {$titulo}";
            $url_notif = "tarea_ver.php?id={$new_task_id}";
            $sql_notif = "INSERT INTO notificaciones (id_usuario_destino, mensaje, url, tipo, leida, fecha_creacion) 
                          VALUES (:id_destino, :mensaje, :url, 'tarea_asignada', 0, NOW())";
            $stmt_notif = $pdo->prepare($sql_notif);
            
            foreach ($ids_asignados as $id_destinatario) {
                $stmt_notif->execute([
                    ':id_destino' => $id_destinatario,
                    ':mensaje' => $mensaje_notif,
                    ':url' => $url_notif
                ]);
            }

            $pdo->commit();
            $show_success_modal = true;
            
            // Limpiar variables
            $titulo_previo = ''; $descripcion_previa = ''; $prioridad_previa = 'baja'; $id_pedido_a_convertir = 0; $telefono_previo = '';

        } catch (PDOException $e) {
            $pdo->rollBack();
            $mensaje = "Error al crear la tarea: " . $e->getMessage();
            $alerta_tipo = 'danger';
        } catch (Exception $e) { 
            $pdo->rollBack();
            $mensaje = "Error crítico: " . $e->getMessage();
            $alerta_tipo = 'danger';
        }
    }
}

// Obtener listas
try {
    $sql_cats = "SELECT id_categoria, nombre FROM categorias ORDER BY nombre";
    $categorias = $pdo->query($sql_cats)->fetchAll();
    
    $sql_users = "SELECT id_usuario, nombre_completo, rol FROM usuarios 
                  WHERE rol IN ('empleado', 'auxiliar', 'encargado') 
                  AND activo = 1 
                  ORDER BY nombre_completo";
    $usuarios_asignables = $pdo->query($sql_users)->fetchAll();
    
    // Obtener Destinos para select (Priorizando Actis)
    $destinos = $pdo->query("SELECT id_destino, nombre FROM destinos_internos 
                             ORDER BY CASE WHEN nombre LIKE '%Actis%' THEN 0 ELSE 1 END, nombre ASC")->fetchAll(PDO::FETCH_ASSOC);

} catch (PDOException $e) {
    $mensaje = "Error al cargar categorías o usuarios: " . $e->getMessage();
    $alerta_tipo = 'danger';
}

include 'navbar.php'; 
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Crear Nueva Tarea</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">
    <link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />
    <link href="https://cdn.jsdelivr.net/npm/select2-bootstrap-5-theme@1.3.0/dist/select2-bootstrap-5-theme.min.css" rel="stylesheet" />
</head>
<body>

    <div class="container mt-4 mb-5">
        <h1 class="mb-4"><i class="fas fa-plus-circle me-2"></i> Crear Nueva Tarea</h1>

        <?php if ($mensaje || $aviso_conversion): ?>
            <div class="alert alert-<?php echo $alerta_tipo; ?>" role="alert">
                <?php echo htmlspecialchars($mensaje . $aviso_conversion); ?>
            </div>
        <?php endif; ?>

        <form action="tarea_crear.php" method="POST" enctype="multipart/form-data">
            
            <input type="hidden" name="id_pedido_a_convertir" value="<?php echo $id_pedido_a_convertir; ?>">
            
            <?php if ($id_pedido_a_convertir == 0): ?>
            <div class="card shadow-sm mb-4 border-info">
                <div class="card-header bg-info text-white"><i class="fas fa-map-marker-alt me-1"></i> Ubicación del Trabajo</div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-6 mb-3 mb-md-0">
                            <label class="form-label fw-bold">1° Destino / Sede</label>
                            <select class="form-select" id="id_destino_interno" name="id_destino_interno">
                                <option value="">-- Seleccione Destino --</option>
                                <?php foreach ($destinos as $dest): ?>
                                    <option value="<?php echo $dest['id_destino']; ?>"><?php echo htmlspecialchars($dest['nombre']); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-bold">2° Área Específica</label>
                            <select class="form-select" id="id_area" name="id_area" disabled>
                                <option value="">-- Primero elija Destino --</option>
                            </select>
                        </div>
                    </div>
                </div>
            </div>
            <?php endif; ?>

            <div class="card shadow-sm mb-4">
                <div class="card-header fs-5"><i class="fas fa-info-circle me-1"></i> Detalles Principales</div>
                <div class="card-body">
                    <div class="mb-3">
                        <label for="titulo" class="form-label fw-bold">Título de la Tarea (*)</label>
                        <input type="text" class="form-control" id="titulo" name="titulo" 
                               value="<?php echo htmlspecialchars($titulo_previo); ?>" required>
                    </div>
                    <div class="mb-3">
                        <label for="descripcion" class="form-label fw-bold">Descripción / Pedido (*)</label>
                        <textarea class="form-control" id="descripcion" name="descripcion" rows="6" required><?php echo htmlspecialchars($descripcion_previa); ?></textarea>
                    </div>
                </div>
            </div>

            <div class="row">
                <div class="col-md-8">
                    <div class="card shadow-sm mb-4">
                        <div class="card-header fs-5"><i class="fas fa-users-cog me-1"></i> Asignación y Prioridad</div>
                        <div class="card-body">
                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <label for="ids_asignados" class="form-label fw-bold">Asignar a Técnicos (*)</label>
                                    <select class="form-select" id="ids_asignados" name="ids_asignados[]" multiple required style="min-height: 150px;">
                                        <?php foreach ($usuarios_asignables as $usuario): ?>
                                            <option value="<?php echo $usuario['id_usuario']; ?>">
                                                <?php echo htmlspecialchars($usuario['nombre_completo']); ?> (<?php echo htmlspecialchars(ucfirst($usuario['rol'])); ?>)
                                            </option>
                                        <?php endforeach; ?>
                                        </select>
                                    <small class="text-muted"><i class="fas fa-info-circle"></i> Mantén presionado Ctrl (o Cmd) para seleccionar varios.</small>
                                </div>
                                <div class="col-md-6 mb-3">
                                    <label for="id_categoria" class="form-label fw-bold">Categoría (*)</label>
                                    <select class="form-select" id="id_categoria" name="id_categoria" required>
                                        <option value="">-- Seleccione categoría --</option>
                                        <?php foreach ($categorias as $cat): ?>
                                            <option value="<?php echo $cat['id_categoria']; ?>"><?php echo htmlspecialchars($cat['nombre']); ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                            </div>
                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <label class="form-label fw-bold">Prioridad (*)</label>
                                    <div>
                                        <?php
                                        $p_baja = ($prioridad_previa === 'baja') ? 'checked' : '';
                                        $p_media = ($prioridad_previa === 'media') ? 'checked' : '';
                                        $p_alta = ($prioridad_previa === 'alta') ? 'checked' : '';
                                        $p_urgente = ($prioridad_previa === 'urgente') ? 'checked' : '';
                                        
                                        if (empty($p_baja) && empty($p_media) && empty($p_alta) && empty($p_urgente)) {
                                            $p_baja = 'checked';
                                        }
                                        ?>
                                        <div class="form-check form-check-inline"><input class="form-check-input" type="radio" name="prioridad" id="prioridad_baja" value="baja" <?php echo $p_baja; ?>><label class="form-check-label" for="prioridad_baja">Baja</label></div>
                                        <div class="form-check form-check-inline"><input class="form-check-input" type="radio" name="prioridad" id="prioridad_media" value="media" <?php echo $p_media; ?>><label class="form-check-label" for="prioridad_media">Media</label></div>
                                        <div class="form-check form-check-inline"><input class="form-check-input" type="radio" name="prioridad" id="prioridad_alta" value="alta" <?php echo $p_alta; ?>><label class="form-check-label" for="prioridad_alta">Alta</label></div>
                                        <div class="form-check form-check-inline"><input class="form-check-input" type="radio" name="prioridad" id="prioridad_urgente" value="urgente" <?php echo $p_urgente; ?>><label class="form-check-label" for="prioridad_urgente">Urgente</label></div>
                                    </div>
                                </div>
                                <div class="col-md-6 mb-3">
                                    <label for="fecha_limite" class="form-label">Fecha Límite (Opcional)</label>
                                    <input type="date" class="form-control" id="fecha_limite" name="fecha_limite">
                                </div>
                            </div>
                            <div class="row">
                                <div class="col-md-12 mb-3">
                                    <label for="solicitante_telefono" class="form-label fw-bold">Teléfono (WhatsApp) (Opcional)</label>
                                    <input type="tel" class="form-control" id="solicitante_telefono" name="solicitante_telefono"
                                           value="<?php echo htmlspecialchars($telefono_previo); ?>"
                                           placeholder="Ej: 54911...">
                                    <small class="text-muted">Teléfono de contacto del solicitante (si se tiene). Incluir cód. de país (Ej: 54).</small>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="card shadow-sm mb-4">
                        <div class="card-header fs-5"><i class="fas fa-paperclip me-1"></i> Adjuntos Iniciales</div>
                        <div class="card-body">
                            <label for="adjuntos_iniciales" class="form-label">Planos, Fotos (Opcional)</label>
                            <input class="form-control" type="file" id="adjuntos_iniciales" name="adjuntos_iniciales[]" multiple>
                            <small class="text-muted">Puede seleccionar múltiples archivos.</small>
                        </div>
                    </div>
                </div>
            </div>
            
            <div class="card shadow-sm mb-4">
                <div class="card-header fs-5"><i class="fas fa-cogs me-1"></i> Configuración Adicional</div>
                <div class="card-body">
                    <label class="form-label d-block"><i class="fas fa-paperclip"></i> ¿Adjunto Final Obligatorio?</label>
                    <div class="form-check form-check-inline">
                        <input class="form-check-input" type="radio" name="adjunto_obligatorio" id="adjunto_obligatorio_si" value="1">
                        <label class="form-check-label" for="adjunto_optimizado_si"><i class="fas fa-check-circle text-success"></i> Sí</label>
                    </div>
                    <div class="form-check form-check-inline">
                        <input class="form-check-input" type="radio" name="adjunto_obligatorio" id="adjunto_obligatorio_no" value="0" checked>
                        <label class="form-check-label" for="adjunto_obligatorio_no"><i class="fas fa-times-circle text-danger"></i> No</label>
                    </div>
                    <small class="text-muted mt-2 d-block">Define si el técnico debe subir un archivo para finalizar.</small>
                </div>
            </div>

            <div class="text-center mb-5">
                <button type="submit" class="btn btn-success btn-lg">
                    <i class="fas fa-save"></i> <?php echo ($id_pedido_a_convertir > 0) ? 'Aprobar y Crear Tarea' : 'Guardar Tarea'; ?>
                </button>
                <a href="<?php echo ($id_pedido_a_convertir > 0) ? 'admin_pedidos_lista.php' : 'tareas_lista.php'; ?>" class="btn btn-outline-secondary ms-2">Cancelar</a>
            </div>
        </form>
    </div>

    <div class="modal fade" id="successModal" tabindex="-1" data-bs-backdrop="static" data-bs-keyboard="false">
      <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-success border-5">
          <div class="modal-header bg-success text-white">
            <h5 class="modal-title"><i class="fas fa-check-circle me-2"></i> ¡Tarea Creada Exitosamente!</h5>
          </div>
          <div class="modal-body">
            <p class="lead">Se ha generado la Tarea N° <strong id="newTaskID"></strong> y se ha notificado al técnico asignado.</p>
            <?php if (isset($_POST['id_pedido_a_convertir']) && (int)$_POST['id_pedido_a_convertir'] > 0): ?>
                <div class="alert alert-info">
                    El Pedido de Trabajo #<?php echo (int)$_POST['id_pedido_a_convertir']; ?> ha sido marcado como "Aprobado".
                </div>
            <?php endif; ?>
          </div>
          <div class="modal-footer">
            <a href="tarea_crear.php" class="btn btn-outline-primary">Crear otra Tarea</a>
            <a href="#" id="viewTaskListButton" class="btn btn-success">
                <i class="fas fa-list-ul me-1"></i> Ir a la Lista de Tareas
            </a>
          </div>
        </div>
      </div>
    </div>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://code.jquery.com/jquery-3.7.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>
    
    <?php if ($show_success_modal && $new_task_id): ?>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const successModal = new bootstrap.Modal(document.getElementById('successModal'));
            document.getElementById('newTaskID').textContent = <?php echo json_encode($new_task_id); ?>;
            const taskListButton = document.getElementById('viewTaskListButton');
            if (taskListButton) {
                taskListButton.href = `<?php echo (isset($_POST['id_pedido_a_convertir']) && (int)$_POST['id_pedido_a_convertir'] > 0) ? 'admin_pedidos_lista.php' : 'tareas_lista.php?highlight_task=' . $new_task_id; ?>`;
            }
            successModal.show();
             
             <?php if ($id_pedido_a_convertir == 0): ?>
                document.querySelector('form').reset(); 
             <?php endif; ?>
        });
    </script>
    <?php endif; ?>
    
    <script>
        $(document).ready(function() {
            // Inicializar Select2 en los nuevos campos (si están visibles)
            if ($('#id_destino_interno').length > 0) {
                $('#id_destino_interno').select2({ theme: 'bootstrap-5', placeholder: 'Buscar Destino...' });
                $('#id_area').select2({ theme: 'bootstrap-5', placeholder: 'Seleccione Área...' });

                // Lógica de carga
                $('#id_destino_interno').on('change', function() {
                    var idDestino = $(this).val();
                    var $selectArea = $('#id_area');
                    $selectArea.empty().append('<option value="">Cargando...</option>').prop('disabled', true);

                    if (idDestino) {
                        $.ajax({
                            url: 'ajax_obtener_areas.php',
                            type: 'GET',
                            data: { id_destino: idDestino },
                            dataType: 'json',
                            success: function(areas) {
                                $selectArea.empty().append('<option value="">-- Seleccione Área --</option>');
                                if (areas.length > 0) {
                                    $.each(areas, function(i, a) {
                                        // Aquí el value es ID_AREA (Importante para tarea_crear.php backend)
                                        $selectArea.append('<option value="' + a.id_area + '">' + a.nombre + '</option>');
                                    });
                                    $selectArea.prop('disabled', false);
                                } else {
                                    $selectArea.append('<option value="">Sin áreas en este destino</option>');
                                }
                            }
                        });
                    } else {
                        $selectArea.empty().append('<option value="">-- Primero elija Destino --</option>');
                    }
                });
            }
            
            // Select2 para asignados (existente)
            $('#ids_asignados').select2({ theme: 'bootstrap-5' });
        });
    </script>

    <div class="toast-container position-fixed bottom-0 end-0 p-3" id="notificationToastContainer" style="z-index: 1080;">
    </div>
    <?php include 'footer.php'; ?>
</body>
</html>