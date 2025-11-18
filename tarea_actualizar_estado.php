<?php
// Archivo: tarea_actualizar_estado.php (VERSIÓN FINAL LIMPIA)
// *** MODIFICADO (v3) POR GEMINI PARA CORREGIR LÓGICA DE PERMISOS DE 'encargado' ***
session_start();
include 'conexion.php';

// Función para detectar si la solicitud es AJAX
function is_ajax_request() { return !empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest'; }

$is_ajax = is_ajax_request();

// 1. Validar parámetros y sesión
if (!isset($_SESSION['usuario_id']) || !isset($_POST['id_tarea']) || !isset($_POST['nuevo_estado'])) {
    if ($is_ajax) { http_response_code(400); header('Content-Type: application/json'); echo json_encode(['success' => false, 'error' => 'Parámetros incompletos o sesión no iniciada.']); }
    else { $id_tarea_redirect = (int)($_POST['id_tarea'] ?? 0); $_SESSION['action_error_message'] = 'Parámetros incompletos o sesión no iniciada.'; header("Location: tarea_ver.php?id={$id_tarea_redirect}"); }
    exit();
}

$id_usuario = $_SESSION['usuario_id'];
$rol_usuario = $_SESSION['usuario_rol'];
$id_tarea = (int)$_POST['id_tarea'];
$nuevo_estado = $_POST['nuevo_estado'];
$nota_admin = trim($_POST['nota_admin'] ?? '');

// URLs de Redirección (solo para peticiones NO AJAX)
$redirect_url_base = "tarea_ver.php?id=" . $id_tarea;

// Mapeo de estados y permisos
$estados_validos = [
    'en_proceso' => 'empleado',
    'verificada' => 'admin',   
    'modificacion_requerida' => 'admin', 
    'reanudar_reserva' => 'empleado'
];

// 2. Validación de estado
if (!array_key_exists($nuevo_estado, $estados_validos)) {
    $error_msg = 'Estado objetivo inválido.';
    if ($is_ajax) { http_response_code(400); header('Content-Type: application/json'); echo json_encode(['success' => false, 'error' => $error_msg]); }
    else { $_SESSION['action_error_message'] = $error_msg; header("Location: " . $redirect_url_base); }
    exit();
}

// --- INICIO DE LA MODIFICACIÓN DE GEMINI (v3) ---
// ESTA ES LA CORRECCIÓN PARA EL ROL 'ENCARGADO'

// 3. Validación de rol (Lógica corregida v3)
$rol_requerido = $estados_validos[$nuevo_estado]; // Obtiene el rol base (ej: 'admin' o 'empleado')
$accion_permitida = false;

if ($rol_requerido === 'admin') {
    // Si la acción requiere 'admin' (Verificar, Modificar), permitimos 'admin' Y 'encargado'
    if ($rol_usuario === 'admin' || $rol_usuario === 'encargado') {
        $accion_permitida = true;
    }
} else if ($rol_requerido === 'empleado') {
    // Si la acción requiere 'empleado' (Iniciar, Reanudar), permitimos:
    // 1. El técnico asignado (empleado/auxiliar)
    // 2. Un manager (admin/encargado) que pueda hacerlo en su nombre
    if (in_array($rol_usuario, ['empleado', 'auxiliar', 'admin', 'encargado'])) {
         $accion_permitida = true;
    }
}

// Fallback de seguridad: si por alguna razón $rol_requerido no es ni 'admin' ni 'empleado',
// solo el admin puede continuar.
if ($rol_requerido !== 'admin' && $rol_requerido !== 'empleado' && $rol_usuario === 'admin') {
     $accion_permitida = true;
}


if (!$accion_permitida) { 
    $error_msg = 'Acción no permitida para su rol.'; // <--- ESTE ES EL ERROR QUE ESTÁS VIENDO
    if ($is_ajax) { http_response_code(403); header('Content-Type: application/json'); echo json_encode(['success' => false, 'error' => $error_msg]); }
    else { $_SESSION['action_error_message'] = $error_msg; header("Location: " . $redirect_url_base); }
    exit();
}
// --- FIN DE LA MODIFICACIÓN DE GEMINI (v3) ---


// 4. Validación adicional para Modificación
if ($nuevo_estado === 'modificacion_requerida' && empty($nota_admin)) {
    $error_msg = 'La solicitud de modificación requiere una nota del administrador.';
    if ($is_ajax) { http_response_code(400); header('Content-Type: application/json'); echo json_encode(['success' => false, 'error' => $error_msg]); }
    else { $_SESSION['action_error_message'] = $error_msg; header("Location: " . $redirect_url_base); }
    exit();
}

try {
    $pdo->beginTransaction();

    // 5. Consulta para actualizar la tarea
    $sql_update = "";
    $params_update = [':id_tarea' => $id_tarea]; 
    $mensaje_exito = "";
    $estado_final_notificacion = $nuevo_estado; 

    if ($nuevo_estado === 'verificada') {
         $sql_update = "UPDATE tareas SET estado = :nuevo_estado, fecha_cierre = NOW() WHERE id_tarea = :id_tarea AND estado = 'finalizada_tecnico'"; 
         $params_update[':nuevo_estado'] = $nuevo_estado;
         $mensaje_exito = "¡Tarea Aprobada! El técnico será notificado.";
    }
    elseif ($nuevo_estado === 'modificacion_requerida') {
         $sql_update = "UPDATE tareas SET estado = :nuevo_estado, nota_final = NULL, fecha_cierre = NULL WHERE id_tarea = :id_tarea AND estado = 'finalizada_tecnico'"; 
         $params_update[':nuevo_estado'] = $nuevo_estado;
    }
    elseif ($nuevo_estado === 'en_proceso') {
         // Esta lógica ahora solo se activa por el botón AJAX "Iniciar Tarea"
         // (Validado por $es_tecnico_asignado en tarea_ver.php)
         $sql_update = "UPDATE tareas SET estado = :nuevo_estado WHERE id_tarea = :id_tarea AND id_asignado = :id_usuario AND estado = 'asignada'";
         $params_update[':nuevo_estado'] = $nuevo_estado;
         $params_update[':id_usuario'] = $id_usuario;
         $mensaje_exito = "Tarea iniciada correctamente.";
    }
    elseif ($nuevo_estado === 'reanudar_reserva') {
         // Esta lógica se activa por el formulario "Reanudar Tarea"
         // (Validado por $es_tecnico_asignado en tarea_ver.php, pero también puede hacerlo un admin/encargado)
         $sql_update = "UPDATE tareas SET estado = 'en_proceso' 
                         WHERE id_tarea = :id_tarea 
                         AND estado = 'en_reserva'";
         
         // Si el que reanuda NO es admin/encargado, nos aseguramos que sea el asignado
         if (!in_array($rol_usuario, ['admin', 'encargado'])) {
             $sql_update .= " AND id_asignado = :id_usuario";
             $params_update[':id_usuario'] = $id_usuario; 
         }
         
         $mensaje_exito = "La tarea ha sido reanudada y está 'En Proceso'.";
         $estado_final_notificacion = 'en_proceso';
    }

    // Ejecutar el UPDATE si se definió un SQL
    $stmt = null;
    $rows_affected = 0;
    if (!empty($sql_update)) {
        $stmt = $pdo->prepare($sql_update);
        $stmt->execute($params_update);
        $rows_affected = $stmt->rowCount();
    } elseif ($nuevo_estado !== 'modificacion_requerida') {
         // Si el estado era 'modificacion_requerida', $rows_affected puede ser 0
         // pero la acción debe continuar para insertar la nota del admin
         throw new Exception("Acción no válida o estado ya aplicado.");
    }

    // 6. Manejo de Errores (Filas Afectadas)
    if ($rows_affected == 0 && !in_array($nuevo_estado, ['modificacion_requerida'])) {
        $error_detalle = " (Rol: $rol_usuario, Estado: $nuevo_estado)";
        
        if ($nuevo_estado === 'reanudar_reserva') {
             throw new Exception("No se pudo reanudar la tarea. Verifique que esté 'En Reserva'" . (in_array($rol_usuario, ['admin', 'encargado']) ? "." : " y asignada a usted."));
        } 
        elseif ($nuevo_estado === 'en_proceso') {
             throw new Exception("No se pudo iniciar la tarea. Verifique que esté 'Asignada' y asignada a usted.");
        }
        elseif ($nuevo_estado === 'verificada') {
             throw new Exception("No se pudo aprobar la tarea (Quizás ya estaba verificada o no estaba 'P/Revisión').");
        }
        else {
             throw new Exception("No se pudo actualizar el estado (¿quizás ya estaba aplicado o no tiene permiso?)." . $error_detalle);
        }
    }

    // 7. Si es Modificación Requerida, insertamos la nota del Admin/Encargado
    if ($nuevo_estado === 'modificacion_requerida' && !empty($nota_admin)) {
        $sql_nota = "INSERT INTO actualizaciones_tarea (id_tarea, id_usuario, contenido, fecha_actualizacion) VALUES (:id_tarea, :id_usuario, :contenido, NOW())";
        $stmt_nota = $pdo->prepare($sql_nota);
        $stmt_nota->execute([':id_tarea' => $id_tarea, ':id_usuario' => $id_usuario, ':contenido' => 'SOLICITUD DE MODIFICACIÓN: ' . $nota_admin]);
    }

    // 8. Notificaciones (SIN CAMBIOS)
    $id_destino = 0; $titulo_tarea = ""; $nombre_usuario_actual = ""; $tipo_notificacion = ""; $mensaje_notificacion = ""; $url_notificacion = "";
    
    if ($rows_affected > 0 || $nuevo_estado === 'modificacion_requerida') {
        $sql_data = "SELECT t.titulo, u.nombre_completo as nombre_tecnico, t.id_asignado, c.nombre_completo as nombre_creador, t.id_creador FROM tareas t LEFT JOIN usuarios u ON t.id_asignado = u.id_usuario LEFT JOIN usuarios c ON t.id_creador = c.id_usuario WHERE t.id_tarea = :id_tarea";
        $stmt_data = $pdo->prepare($sql_data); $stmt_data->execute([':id_tarea' => $id_tarea]); $tarea_data = $stmt_data->fetch(PDO::FETCH_ASSOC);

        if ($tarea_data) {
            $titulo_tarea = $tarea_data['titulo'];
            $protocolo = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? "https" : "http"); $host = $_SERVER['HTTP_HOST']; $ruta_base = rtrim(dirname($_SERVER['PHP_SELF']), '/\\'); $url_base_absoluta = $protocolo . '://' . $host . $ruta_base;
            
            // Acciones de Admin/Encargado
            if ($nuevo_estado === 'verificada' || $nuevo_estado === 'modificacion_requerida') {
                $id_destino = $tarea_data['id_asignado']; 
                $nombre_usuario_actual = $_SESSION['usuario_nombre']; 
                $nombre_tecnico = $tarea_data['nombre_tecnico'] ?? 'el usuario';
                
                if ($nuevo_estado === 'verificada') {
                    $tipo_notificacion = "tarea_verificada"; 
                    $mensaje_notificacion = "El Gestor **{$nombre_usuario_actual}** ha **APROBADO** la tarea #{$id_tarea}: {$titulo_tarea}."; 
                    $url_notificacion = $url_base_absoluta . "/tarea_ver.php?id={$id_tarea}&show_modal=aprobada";
                    $mensaje_exito = "¡Tarea Aprobada! El usuario {$nombre_tecnico} será notificado.";
                } else { // modificacion_requerida
                    $tipo_notificacion = "tarea_modificacion"; 
                    $mensaje_notificacion = "El Gestor **{$nombre_usuario_actual}** ha **SOLICITADO MODIFICACIÓN** en la tarea #{$id_tarea}: {$titulo_tarea}."; 
                    $url_notificacion = $url_base_absoluta . "/tarea_ver.php?id={$id_tarea}&show_modal=modificacion";
                    $mensaje_exito = "Su pedido de modificación ha sido enviado a {$nombre_tecnico}.";
                }
            }
            // Acciones de Empleado/Auxiliar
            else if ($nuevo_estado === 'en_proceso') {
                $id_destino = $tarea_data['id_creador']; $nombre_usuario_actual = $tarea_data['nombre_tecnico']; $tipo_notificacion = "tarea_iniciada"; $mensaje_notificacion = "El técnico {$nombre_usuario_actual} ha **INICIADO** la tarea #{$id_tarea}: {$titulo_tarea}."; $url_notificacion = $url_base_absoluta . "/tarea_ver.php?id={$id_tarea}";
            }
            // NOTIFICACIÓN DE REANUDAR
            else if ($nuevo_estado === 'reanudar_reserva') {
                $id_destino = $tarea_data['id_creador']; $nombre_usuario_actual = $tarea_data['nombre_tecnico']; $tipo_notificacion = "tarea_reanudad"; $mensaje_notificacion = "El técnico {$nombre_usuario_actual} ha **REANUDADO** la tarea #{$id_tarea}: {$titulo_tarea} (estaba 'En Reserva')."; $url_notificacion = $url_base_absoluta . "/tarea_ver.php?id={$id_tarea}";
            }

            // Enviar notificación si corresponde
            if ($id_destino > 0 && $id_destino != $id_usuario) {
                $sql_notif = "INSERT INTO notificaciones (id_usuario_destino, mensaje, url, tipo, leida, fecha_creacion) VALUES (:id_destino, :mensaje, :url, :tipo, 0, NOW())";
                $stmt_notif = $pdo->prepare($sql_notif);
                $stmt_notif->execute([':id_destino' => $id_destino, ':mensaje' => $mensaje_notificacion, ':url' => $url_notificacion, ':tipo' => $tipo_notificacion]);
            }
        }
    }

    $pdo->commit();
    
    if ($is_ajax) { header('Content-Type: application/json'); echo json_encode(['success' => true, 'message' => $mensaje_exito, 'new_state' => $estado_final_notificacion]); }
    else { $_SESSION['action_success_message'] = $mensaje_exito; header("Location: " . $redirect_url_base); }
    exit();

} catch (Exception $e) {
    $pdo->rollBack();
    error_log("Error al actualizar estado de tarea: " . $e->getMessage()); $error_msg = 'Error de servidor: ' . $e->getMessage();
    if ($is_ajax) { http_response_code(500); header('Content-Type: application/json'); echo json_encode(['success' => false, 'error' => $error_msg]); }
    else { $_SESSION['action_error_message'] = $error_msg; header("Location: " . $redirect_url_base); }
    exit();
}
?>