<?php
// Archivo: pedido_crear.php (COMPLETO Y FUNCIONAL v4 - Incluye Título Pedido)
// *** MODIFICADO (v4) PARA FONDO DE FIRMA TRANSPARENTE ***
// *** MODIFICADO (v5) POR GEMINI PARA MOSTRAR MODAL DE ÉXITO EN LUGAR DE REDIRIGIR A PDF ***

include 'acceso_protegido.php'; // Incluye sesión y $pdo

// 1. Verificar Permiso:
include_once 'funciones_permisos.php';
if (!isset($_SESSION['usuario_id']) || !tiene_permiso('acceso_pedidos_crear', $pdo)) {
    $_SESSION['action_error_message'] = "No tiene permiso para crear pedidos.";
    header("Location: dashboard.php");
    exit();
}

// 2. Obtener datos del Usuario logueado (Quien crea el pedido)
$id_usuario_logueado = $_SESSION['usuario_id'];
$nombre_usuario_logueado = $_SESSION['usuario_nombre'];
$firma_usuario_logueado_path = null;

// 3. Inicializar variables
$mensaje = '';
$alerta_tipo = '';
$areas = [];
$destinos = [];
// Variables para repopular en caso de error POST
$titulo_pedido_prev = $_POST['titulo_pedido'] ?? '';
$selected_area = $_POST['id_area'] ?? '';
$selected_destino = $_POST['id_destino_interno'] ?? '';
$selected_prioridad = $_POST['prioridad'] ?? 'rutina';
$fecha_req_prev = $_POST['fecha_requerida'] ?? '';
$desc_sint_prev = $_POST['descripcion_sintomas'] ?? '';
$solic_real_prev = $_POST['solicicitar_real_nombre'] ?? '';
$solic_telefono_prev = $_POST['solicitar_telefono'] ?? '';

// --- INICIO MODIFICACIÓN GEMINI (v5): Capturar variables de éxito de la Sesión ---
$show_success_modal = $_SESSION['pedido_creado_exito'] ?? false;
$nuevo_pedido_id_modal = $_SESSION['pedido_creado_id'] ?? 0;
$nuevo_pedido_numero_modal = $_SESSION['pedido_creado_numero'] ?? 'N/A';
$nuevo_pedido_titulo_modal = $_SESSION['pedido_creado_titulo'] ?? 'Pedido';

// Limpiar las variables de sesión para que el modal no se muestre de nuevo al recargar
if ($show_success_modal) {
    unset($_SESSION['pedido_creado_exito']);
    unset($_SESSION['pedido_creado_id']);
    unset($_SESSION['pedido_creado_numero']);
    unset($_SESSION['pedido_creado_titulo']);
}
// --- FIN MODIFICACIÓN GEMINI (v5) ---


// --- Bloque Try-Catch para operaciones críticas de carga ---
try {
    // 4. Obtener firma del usuario logueado desde la BD
    $stmt_firma = $pdo->prepare("SELECT firma_imagen_path FROM usuarios WHERE id_usuario = :id");
    $stmt_firma->execute([':id' => $id_usuario_logueado]);
    $firma_rel_path = $stmt_firma->fetchColumn();
    if ($firma_rel_path) {
         $ruta_completa_firma = 'uploads/firmas/' . $firma_rel_path;
         if (file_exists($ruta_completa_firma)) {
            $firma_usuario_logueado_path = $firma_rel_path;
         } else {
            error_log("Archivo de firma no encontrado para usuario {$id_usuario_logueado}: {$ruta_completa_firma}");
         }
    }

    // 5. Cargar Áreas y Destinos para los desplegables <select>
    $areas = $pdo->query("SELECT id_area, nombre FROM areas ORDER BY nombre")->fetchAll(PDO::FETCH_ASSOC);
    $destinos = $pdo->query("SELECT id_destino, nombre FROM destinos_internos ORDER BY nombre")->fetchAll(PDO::FETCH_ASSOC);

} catch (PDOException $e) {
    $mensaje = "Error crítico al preparar el formulario: " . $e->getMessage();
    $alerta_tipo = 'danger';
    error_log("Error carga pedido_crear: " . $e->getMessage());
}


// --- 7. Lógica POST para guardar el pedido ---
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    
    $solicitante_real_nombre_post = trim($_POST['solicitante_real_nombre'] ?? '');
    $solicitante_telefono_post = trim($_POST['solicitante_telefono'] ?? '');
    $ruta_firma_solicitante_guardada = null;
    
    // --- INICIO: Procesar Firma Base64 ---
    $firma_base64 = $_POST['firma_solicitante_base64'] ?? '';
    
    if (!empty($firma_base64)) {
        $upload_dir_firmas = 'uploads/firmas_pedidos/';
        if (!is_dir($upload_dir_firmas)) {
            if (!mkdir($upload_dir_firmas, 0777, true)) {
                $mensaje = "Error crítico: No se pudo crear el directorio de firmas.";
                $alerta_tipo = 'danger';
                goto end_post_logic; 
            }
        }

        $data = explode(',', $firma_base64);
        $encoded_image = (count($data) > 1) ? $data[1] : $data[0];
        $decoded_image = base64_decode($encoded_image);

        if ($decoded_image === false) {
            $mensaje = "Error: El formato de la firma enviada es inválido.";
            $alerta_tipo = 'danger';
            goto end_post_logic;
        }

        $nombre_solicitante_limpio = preg_replace("/[^a-zA-Z0-9]/", "", str_replace(" ", "_", $solicitante_real_nombre_post));
        $filename = 'solic_' . $nombre_solicitante_limpio . '_' . time() . '.png';
        $ruta_completa_firma = $upload_dir_firmas . $filename;

        if (file_put_contents($ruta_completa_firma, $decoded_image)) {
            $ruta_firma_solicitante_guardada = $ruta_completa_firma; 
        } else {
            $mensaje = "Error: No se pudo guardar el archivo de la firma en el servidor.";
            $alerta_tipo = 'danger';
            goto end_post_logic;
        }
    } else {
         $mensaje = "Error: La firma del solicitante es obligatoria.";
         $alerta_tipo = 'danger';
         goto end_post_logic;
    }
    // --- FIN: Procesar Firma Base64 ---
    
    
    // Revalidar/recuperar datos del POST
    $titulo_pedido_post = trim($_POST['titulo_pedido'] ?? '');
    $id_area_post = (int)($_POST['id_area'] ?? 0);
    $id_destino_interno_post = (int)($_POST['id_destino_interno'] ?? 0);
    $prioridad_post = trim($_POST['prioridad'] ?? 'rutina');
    $fecha_requerida_post = empty($_POST['fecha_requerida']) ? null : $_POST['fecha_requerida'];
    $descripcion_sintomas_post = trim($_POST['descripcion_sintomas'] ?? '');

    if (empty($titulo_pedido_post) || $id_area_post <= 0 || empty($prioridad_post) || empty($descripcion_sintomas_post) || empty($solicitante_real_nombre_post)) {
        $mensaje = "Error: Faltan campos obligatorios (Título, Área, Prioridad, Descripción, Solicitante).";
        $alerta_tipo = 'danger';
        // Repopular
        $titulo_pedido_prev = $titulo_pedido_post;
        $selected_area = $id_area_post;
        $selected_destino = $id_destino_interno_post;
        $selected_prioridad = $prioridad_post;
        $fecha_req_prev = $fecha_requerida_post;
        $desc_sint_prev = $descripcion_sintomas_post;
        $solic_real_prev = $solicitante_real_nombre_post;
        $solic_telefono_prev = $solicitante_telefono_post;
    } else {
         // ---> OBTENER NOMBRE DEL ÁREA SELECCIONADA
         $nombre_area_seleccionada = 'N/A';
         if (empty($areas)) { 
             try { $areas = $pdo->query("SELECT id_area, nombre FROM areas")->fetchAll(PDO::FETCH_ASSOC); } catch (PDOException $e) {}
         }
         if ($id_area_post > 0 && !empty($areas)) {
             foreach ($areas as $area_lookup) {
                 if ($area_lookup['id_area'] == $id_area_post) {
                     $nombre_area_seleccionada = $area_lookup['nombre'];
                     break;
                 }
             }
         }
         // ---> FIN OBTENER NOMBRE ÁREA <---

        $pdo->beginTransaction();
        try {
            
            $numero_orden_generado = generar_nuevo_numero_orden($pdo);
            
            $sql_insert = "INSERT INTO pedidos_trabajo
                        (numero_orden, titulo_pedido, id_solicitante, id_auxiliar, id_area, area_solicitante, id_destino_interno, prioridad, fecha_requerida, descripcion_sintomas, solicitante_real_nombre, solicitante_telefono, fecha_emision, estado_pedido, firma_solicitante_path)
                    VALUES
                        (:num_orden, :titulo_ped, :id_solic, :id_aux, :id_area, :area_nombre, :id_dest, :prio, :fecha_req, :descrip, :solic_real, :solic_tel, NOW(), 'pendiente_encargado', :firma_solic_path)";

            $stmt_insert = $pdo->prepare($sql_insert);
            
            $stmt_insert->execute([
                ':num_orden' => $numero_orden_generado,
                ':titulo_ped' => $titulo_pedido_post,
                ':id_solic' => $id_usuario_logueado,
                ':id_aux' => $id_usuario_logueado,
                ':id_area' => $id_area_post,
                ':area_nombre' => $nombre_area_seleccionada,
                ':id_dest' => $id_destino_interno_post > 0 ? $id_destino_interno_post : null,
                ':prio' => $prioridad_post,
                ':fecha_req' => $fecha_requerida_post,
                ':descrip' => $descripcion_sintomas_post,
                ':solic_real' => $solicitante_real_nombre_post,
                ':solic_tel' => empty($solicitante_telefono_post) ? null : $solicitante_telefono_post,
                ':firma_solic_path' => $ruta_firma_solicitante_guardada
            ]);

            $id_nuevo_pedido = $pdo->lastInsertId();

            // --- Enviar Notificación al/los Encargado(s) ---
            $sql_encargados = "SELECT id_usuario FROM usuarios WHERE rol = 'encargado' AND activo = 1";
            $stmt_encargados = $pdo->query($sql_encargados);
            $encargados_ids = $stmt_encargados->fetchAll(PDO::FETCH_COLUMN);

            if (!empty($encargados_ids)) {
                $mensaje_notif = "Nuevo pedido ({$numero_orden_generado}: {$titulo_pedido_post}) del área {$nombre_area_seleccionada} requiere aprobación.";
                $url_notif = "encargado_pedidos_lista.php?highlight_pedido={$id_nuevo_pedido}";

                $sql_insert_notif = "INSERT INTO notificaciones (id_usuario_destino, mensaje, url, tipo, leida, fecha_creacion)
                                     VALUES (:id_destino, :mensaje, :url, 'pedido_nuevo', 0, NOW())";
                $stmt_notif = $pdo->prepare($sql_insert_notif);

                foreach ($encargados_ids as $id_encargado) {
                    if ($id_encargado != $id_usuario_logueado) { // Evitar auto-notificación
                        $stmt_notif->execute([
                            ':id_destino' => $id_encargado,
                            ':mensaje' => $mensaje_notif,
                            ':url' => $url_notif
                        ]);
                    }
                }
                 error_log("Notificación de pedido #{$id_nuevo_pedido} enviada a encargados.");
            } else {
                 error_log("Advertencia: No se encontraron encargados activos para notificar el pedido #{$id_nuevo_pedido}.");
            }
            // --- Fin Notificación ---

            $pdo->commit();

            // --- INICIO MODIFICACIÓN GEMINI (v5): Establecer variables de sesión para el modal ---
            $_SESSION['pedido_creado_exito'] = true;
            $_SESSION['pedido_creado_id'] = $id_nuevo_pedido;
            $_SESSION['pedido_creado_numero'] = $numero_orden_generado;
            $_SESSION['pedido_creado_titulo'] = $titulo_pedido_post;

            // Redirigir de vuelta a la misma página para mostrar el modal
            header("Location: pedido_crear.php");
            // --- FIN MODIFICACIÓN GEMINI (v5) ---
            exit();

        } catch (PDOException $e) {
            $pdo->rollBack();
            if ($e->getCode() == '23000') {
                 $mensaje = "Error al guardar: Posible duplicado. Intente recargar la página.";
            } else {
                 $mensaje = "Error de base de datos al guardar el pedido: " . $e->getMessage();
            }
            $alerta_tipo = 'danger';
            error_log("Error DB al guardar pedido: " . $e->getMessage());
             // Repopular
            $titulo_pedido_prev = $titulo_pedido_post;
            $selected_area = $id_area_post;
            $selected_destino = $id_destino_interno_post;
            $selected_prioridad = $prioridad_post;
            $fecha_req_prev = $fecha_requerida_post;
            $desc_sint_prev = $descripcion_sintomas_post;
            $solic_real_prev = $solicitante_real_nombre_post;
            $solic_telefono_prev = $solicitante_telefono_post;
        }
    }
    
    end_post_logic:
    // Esta etiqueta permite saltar aquí si la firma falla
}

// Incluir el encabezado (navbar) después de la lógica principal
include 'navbar.php';
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Crear Pedido de Trabajo</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">
    
    <style>
        .signature-pad-container {
            border: 2px dashed #ccc;
            border-radius: 0.375rem;
            position: relative;
            height: 200px;
            overflow: hidden; 
            background-color: #fff; 
        }
        #signature-canvas {
            width: 100%;
            height: 100%;
            cursor: crosshair; 
        }
        .signature-pad-actions {
           margin-top: 10px;
        }
        .signature-pad-container.disabled {
            opacity: 0.7;
            background-color: #f8f9fa;
            border-style: solid;
        }
    </style>
    
</head>
<body>

<div class="container mt-4 mb-5">
    <h1 class="mb-4"><i class="fas fa-file-signature me-2"></i> Crear Pedido de Trabajo</h1>

    <div class="row justify-content-center">
        <div class="col-lg-10">
            <div class="card shadow-sm">
                <div class="card-body p-4">

                    <?php if ($mensaje): ?>
                        <div class="alert alert-<?php echo $alerta_tipo; ?> alert-dismissible fade show" role="alert">
                            <?php echo htmlspecialchars($mensaje); ?>
                            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                        </div>
                    <?php endif; ?>

                    <?php $disableForm = ($alerta_tipo === 'danger' && empty($_POST) && (empty($areas) || empty($destinos))); ?>
                    
                    <form action="pedido_crear.php" method="POST" id="pedido-form" <?php if ($disableForm) echo ' class="pe-none opacity-50"'; ?>>

                        <div class="row mb-3 bg-light p-3 rounded border">
                            <div class="col-md-6">
                                <label class="form-label fw-bold">N° Orden</label>
                                <input type="text" class="form-control" value="(Se generará al guardar)" readonly>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label fw-bold">Fecha Emisión</label>
                                <input type="text" class="form-control" value="<?php echo date('d/m/Y'); ?>" readonly>
                            </div>
                        </div>

                        <h5 class="text-primary mt-4"><i class="fas fa-map-pin me-1"></i> 1. Ubicación y Prioridad</h5>
                        <hr>
                        <div class="row mb-3">
                             <div class="col-md-6">
                                <label for="id_area" class="form-label fw-bold">Área Solicitante (*)</label>
                                <select class="form-select" id="id_area" name="id_area" required <?php if ($disableForm) echo ' disabled'; ?>>
                                    <option value="">-- Seleccione Área --</option>
                                    <?php if (!empty($areas)): ?>
                                        <?php foreach ($areas as $area): ?>
                                            <option value="<?php echo $area['id_area']; ?>" <?php echo ($selected_area == $area['id_area']) ? 'selected' : ''; ?>>
                                                <?php echo htmlspecialchars($area['nombre']); ?>
                                            </option>
                                        <?php endforeach; ?>
                                    <?php else: ?>
                                        <option value="" disabled>Error al cargar áreas</option>
                                    <?php endif; ?>
                                </select>
                            </div>
                            <div class="col-md-6">
                                <label for="id_destino_interno" class="form-label">Destino Interno Específico (Opcional)</label>
                                <select class="form-select" id="id_destino_interno" name="id_destino_interno" <?php if ($disableForm) echo ' disabled'; ?>>
                                     <option value="">-- Seleccione Destino (si aplica) --</option>
                                     <?php if (!empty($destinos)): ?>
                                         <?php foreach ($destinos as $destino): ?>
                                            <option value="<?php echo $destino['id_destino']; ?>" <?php echo ($selected_destino == $destino['id_destino']) ? 'selected' : ''; ?>>
                                                <?php echo htmlspecialchars($destino['nombre']); ?>
                                            </option>
                                        <?php endforeach; ?>
                                     <?php else: ?>
                                        <option value="" disabled>Error al cargar destinos</option>
                                     <?php endif; ?>
                                </select>
                                <small class="text-muted">Ej: N° Oficina, Interno Telefónico</small>
                            </div>
                        </div>
                         <div class="row mb-3">
                             <div class="col-md-6">
                                <label class="form-label fw-bold">Prioridad (*)</label>
                                <div>
                                    <div class="form-check form-check-inline">
                                        <input class="form-check-input" type="radio" name="prioridad" id="prioridad_rutina" value="rutina" <?php echo ($selected_prioridad == 'rutina') ? 'checked' : ''; ?> <?php if ($disableForm) echo ' disabled'; ?>>
                                        <label class="form-check-label" for="prioridad_rutina">Rutina</label>
                                    </div>
                                    <div class="form-check form-check-inline">
                                        <input class="form-check-input" type="radio" name="prioridad" id="prioridad_importante" value="importante" <?php echo ($selected_prioridad == 'importante') ? 'checked' : ''; ?> <?php if ($disableForm) echo ' disabled'; ?>>
                                        <label class="form-check-label" for="prioridad_importante">Importante</label>
                                    </div>
                                    <div class="form-check form-check-inline">
                                        <input class="form-check-input" type="radio" name="prioridad" id="prioridad_urgente" value="urgente" <?php echo ($selected_prioridad == 'urgente') ? 'checked' : ''; ?> <?php if ($disableForm) echo ' disabled'; ?>>
                                        <label class="form-check-label text-danger fw-bold" for="prioridad_urgente">URGENTE</label>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <label for="fecha_requerida" class="form-label">Fecha Requerida (Opcional)</label>
                                <input type="date" class="form-control" id="fecha_requerida" name="fecha_requerida" value="<?php echo htmlspecialchars($fecha_req_prev); ?>" <?php if ($disableForm) echo ' disabled'; ?>>
                                <small class="text-muted">¿Para cuándo necesita que esté resuelto?</small>
                            </div>
                        </div>


                        <h5 class="text-primary mt-4"><i class="fas fa-pencil-alt me-1"></i> 2. Detalles del Pedido</h5>
                        <hr>
                        <div class="mb-3">
                            <label for="titulo_pedido" class="form-label fw-bold">Título Resumido del Pedido (*)</label>
                            <input type="text" class="form-control" id="titulo_pedido" name="titulo_pedido"
                                   value="<?php echo htmlspecialchars($titulo_pedido_prev); ?>"
                                   placeholder="Ej: Cambiar tomacorriente Laboratorio" required maxlength="255" <?php if ($disableForm) echo ' disabled'; ?>>
                            <small class="text-muted">Un título breve que describa el trabajo principal.</small>
                        </div>
                        <div class="mb-3">
                            <label for="descripcion_sintomas" class="form-label fw-bold">Descripción de Síntomas / Pedido Completa (*)</label>
                            <textarea class="form-control" id="descripcion_sintomas" name="descripcion_sintomas" rows="5"
                                      placeholder="Sea lo más descriptivo posible. Ej: El tomacorriente N° 3 del laboratorio (pared oeste) echa chispas al enchufar equipamiento." required <?php if ($disableForm) echo ' disabled'; ?>><?php echo htmlspecialchars($desc_sint_prev); ?></textarea>
                        </div>
                        
                        <h5 class="text-primary mt-4"><i class="fas fa-signature me-1"></i> 3. Firma del Solicitante Externo</h5>
                        <hr>
                        <div class="row mb-3">
                            <div class="col-12">
                                <label class="form-label fw-bold">Firma de conformidad (*):</label>
                                <div class="signature-pad-container" id="signature-pad-wrapper">
                                    <canvas id="signature-canvas"></canvas>
                                </div>
                                <div class="signature-pad-actions">
                                    <button type="button" class="btn btn-sm btn-outline-danger" id="clear-signature-button" title="Limpiar Firma">
                                        <i class="fas fa-times"></i> Limpiar
                                    </button>
                                    <button type="button" class="btn btn-sm btn-success" id="confirm-signature-button" title="Confirmar Firma">
                                        <i class="fas fa-check"></i> Confirmar Firma
                                    </button>
                                </div>
                                <small class="text-muted">El solicitante debe firmar y luego presionar "Confirmar Firma".</small>
                            </div>
                        </div>
                        
                        <input type="hidden" name="firma_solicitante_base64" id="firma_solicitante_base64">

                        <div id="aclaracion-container" style="display: none;">
                            <h5 class="text-primary mt-4"><i class="fas fa-user-edit me-1"></i> 4. Datos del Solicitante</h5>
                            <hr>
                            <div class="row">
                                <div class="col-md-7">
                                    <label for="solicitante_real_nombre" class="form-label fw-bold">Aclaración Solicitante (*)</label>
                                    <input type="text" class="form-control form-control-lg" id="solicitante_real_nombre" name="solicitante_real_nombre"
                                           value="<?php echo htmlspecialchars($solic_real_prev); ?>"
                                           placeholder="Aclaración de quien firmó" required <?php if ($disableForm) echo ' disabled'; ?>>
                                    <small class="text-muted">Debe coincidir con la persona que firmó.</small>
                                </div>
                                <div class="col-md-5">
                                    <label for="solicitante_telefono" class="form-label fw-bold">Teléfono (WhatsApp) (Opcional)</label>
                                    <input type="tel" class="form-control form-control-lg" id="solicitante_telefono" name="solicitante_telefono"
                                           value="<?php echo htmlspecialchars($solic_telefono_prev); ?>"
                                           placeholder="Ej: 54911..." <?php if ($disableForm) echo ' disabled'; ?>>
                                    <small class="text-muted">Incluir cód. de país (Ej: 54) y área.</small>
                                </div>
                            </div>
                        </div>
                        
                        <div id="registrado-por-container" style="display: none;">
                            <h5 class="text-primary mt-4"><i class="fas fa-user-check me-1"></i> 5. Registrado por (Logística)</h5>
                            <hr>
                             <div class="row align-items-center bg-light p-3 rounded border mb-4">
                                 <div class="col-md-8">
                                     <p class="mb-1"><strong>Usuario Logística:</strong></p>
                                     <h4><?php echo htmlspecialchars($nombre_usuario_logueado); ?></h4>
                                     <small class="text-muted">ID: <?php echo $id_usuario_logueado; ?></small>
                                 </div>
                                 <div class="col-md-4 text-center">
                                     <label class="form-label fw-bold">Firma:</label>
                                     <div style="height: 70px; border-bottom: 1px solid #ccc; display: flex; align-items: center; justify-content: center; background-color: #fff; border-radius: .25rem;">
                                         <?php if ($firma_usuario_logueado_path): ?>
                                             <img src="uploads/firmas/<?php echo htmlspecialchars($firma_usuario_logueado_path); ?>" alt="Firma" style="max-height: 60px; max-width: 100%;">
                                         <?php else: ?>
                                             <span class="text-muted fst-italic">(Sin firma registrada)</span>
                                         <?php endif; ?>
                                     </div>
                                     <p class="small text-muted mt-1">Aclaración</p>
                                 </div>
                             </div>
                         </div>
                        
                        <div id="enviar-container" class="text-end mt-4" style="display: none;">
                            <button type="submit" class="btn btn-primary btn-lg" <?php if ($disableForm) echo ' disabled'; ?>>
                                <i class="fas fa-paper-plane me-2"></i> Enviar Pedido
                            </button>
                        </div>
                    </form>

                </div>
            </div>
        </div>
    </div>
</div>

<div class="modal fade" id="pedidoSuccessModal" tabindex="-1" data-bs-backdrop="static" data-bs-keyboard="false">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content border-success border-5">
      <div class="modal-header bg-success text-white">
        <h5 class="modal-title"><i class="fas fa-check-circle me-2"></i> ¡Solicitud Creada Exitosamente!</h5>
      </div>
      <div class="modal-body">
        <p class="lead">La solicitud de trabajo ha sido registrada con éxito.</p>
        <div class="alert alert-info">
            <strong>N° Orden:</strong> <span class="fw-bold fs-5" id="modalPedidoNumero"></span><br>
            <strong>Título:</strong> <span id="modalPedidoTitulo"></span>
        </div>
        <p>El pedido ha sido enviado al Encargado para su revisión y aprobación.</p>
      </div>
      <div class="modal-footer justify-content-between">
        <a href="dashboard.php" class="btn btn-primary">
            <i class="fas fa-home me-1"></i> Ir al Inicio
        </a>
        <a href="#" id="modalPdfButton" target="_blank" class="btn btn-success">
            <i class="fas fa-file-pdf me-1"></i> Ver Solicitud Creada
        </a>
      </div>
    </div>
  </div>
</div>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<div class="toast-container position-fixed bottom-0 end-0 p-3" id="notificationToastContainer" style="z-index: 1080;"></div>

<script src="https://cdnjs.cloudflare.com/ajax/libs/signature_pad/1.5.3/signature_pad.min.js"></script>

<script>
    document.addEventListener('DOMContentLoaded', function() {
        var canvas = document.getElementById('signature-canvas');
        var wrapper = document.getElementById('signature-pad-wrapper');
        var aclaracionContainer = document.getElementById('aclaracion-container');
        var registradoPorContainer = document.getElementById('registrado-por-container');
        var enviarContainer = document.getElementById('enviar-container');
        var aclaracionInput = document.getElementById('solicitante_real_nombre');
        var clearButton = document.getElementById('clear-signature-button');
        var confirmButton = document.getElementById('confirm-signature-button');
        var hiddenSignatureInput = document.getElementById('firma_solicitante_base64');
        var form = document.getElementById('pedido-form');

        if (canvas && wrapper && clearButton && confirmButton && aclaracionContainer && registradoPorContainer && enviarContainer && hiddenSignatureInput && form) {
            
            var signaturePad = new SignaturePad(canvas, {
                // *** INICIO CORRECCIÓN (v4): Fondo transparente ***
                // backgroundColor: 'rgb(255, 255, 255)' // Omitido para fondo transparente
                // *** FIN CORRECCIÓN (v4) ***
            });

            // (Función resizeCanvas con corrección de Bug)
            function resizeCanvas() {
                var data = null;
                if (!signaturePad.isEmpty()) {
                    data = signaturePad.toDataURL(); 
                }
                var ratio =  Math.max(window.devicePixelRatio || 1, 1);
                canvas.width = canvas.offsetWidth * ratio;
                canvas.height = canvas.offsetHeight * ratio;
                canvas.getContext("2d").scale(ratio, ratio);
                signaturePad.clear(); 
                if (data) {
                    signaturePad.fromDataURL(data); 
                }
            }
            window.addEventListener("resize", resizeCanvas);
            resizeCanvas();

            // (Lógica de Botones y Flujo)
            clearButton.addEventListener('click', function (e) {
                e.preventDefault();
                signaturePad.clear();
            });

            confirmButton.addEventListener('click', function (e) {
                e.preventDefault();
                if (signaturePad.isEmpty()) {
                    alert("Por favor, el solicitante debe firmar para continuar.");
                    return;
                }
                var dataURL = signaturePad.toDataURL(); // PNG Transparente
                hiddenSignatureInput.value = dataURL;
                signaturePad.off();
                wrapper.classList.add('disabled');
                clearButton.style.display = 'none';
                confirmButton.style.display = 'none';
                aclaracionContainer.style.display = 'block';
                registradoPorContainer.style.display = 'block';
                enviarContainer.style.display = 'block';
                aclaracionInput.focus();
            });
            
            form.addEventListener('submit', function (e) {
                if (hiddenSignatureInput.value === '') {
                     alert("Debe 'Confirmar Firma' antes de enviar el pedido.");
                     e.preventDefault();
                     return;
                }
                if (aclaracionInput.value.trim() === '') {
                    alert("Debe ingresar la Aclaración de la firma.");
                    aclaracionInput.focus();
                    e.preventDefault();
                    return;
                }
                // --- MODIFICACIÓN GEMINI (v5): Cambiar texto del botón al enviar ---
                var submitButton = form.querySelector('button[type="submit"]');
                if (submitButton) {
                    submitButton.disabled = true;
                    submitButton.innerHTML = '<i class="fas fa-spinner fa-spin me-2"></i> Procesando...';
                }
                // --- FIN MODIFICACIÓN ---
            });
        }

        // --- INICIO SCRIPT MODAL DE ÉXITO AÑADIDO POR GEMINI (v5) ---
        <?php if ($show_success_modal && $nuevo_pedido_id_modal > 0): ?>
            const successModalEl = document.getElementById('pedidoSuccessModal');
            if (successModalEl) {
                const successModal = new bootstrap.Modal(successModalEl);
                
                // Settear los datos en el modal
                const pdfUrl = `generar_pedido_pdf.php?id=<?php echo $nuevo_pedido_id_modal; ?>`;
                document.getElementById('modalPedidoNumero').textContent = <?php echo json_encode($nuevo_pedido_numero_modal); ?>;
                document.getElementById('modalPedidoTitulo').textContent = <?php echo json_encode($nuevo_pedido_titulo_modal); ?>;
                document.getElementById('modalPdfButton').href = pdfUrl;

                // Limpiar el formulario en el fondo
                const formToReset = document.getElementById('pedido-form');
                if(formToReset) {
                    formToReset.reset();
                    // Resetear el canvas de la firma
                    if(typeof signaturePad !== 'undefined') {
                        var wrapper = document.getElementById('signature-pad-wrapper');
                        var clearButton = document.getElementById('clear-signature-button');
                        var confirmButton = document.getElementById('confirm-signature-button');
                        
                        signaturePad.clear();
                        signaturePad.on();
                        wrapper.classList.remove('disabled');
                        clearButton.style.display = 'inline-block';
                        confirmButton.style.display = 'inline-block';
                        document.getElementById('aclaracion-container').style.display = 'none';
                        document.getElementById('registrado-por-container').style.display = 'none';
                        document.getElementById('enviar-container').style.display = 'none';
                        document.getElementById('firma_solicitante_base64').value = '';
                    }
                }
                
                // Mostrar el modal
                successModal.show();
            }
        <?php endif; ?>
        // --- FIN SCRIPT MODAL ---
    });
</script>
<?php include 'footer.php'; ?>
</body>
</html>