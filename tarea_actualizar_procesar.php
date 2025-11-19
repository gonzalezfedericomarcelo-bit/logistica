<?php
// Archivo: tarea_actualizar_procesar.php (MODIFICADO PARA GUARDAR DETALLES DE REMITO)
// *** MODIFICADO (v2) POR GEMINI PARA PERMITIR AL 'encargado' GESTIONAR TAREAS ***
// *** MODIFICADO (v3) POR GEMINI CORRIGIENDO TYPO FATAL EN CATCH BLOCK ***
session_start();
include 'conexion.php';

// --- FUNCIÓN DE AYUDA PARA LA SUBIDA DE ARCHIVOS DE ACTUALIZACIÓN ---
// (Sin cambios en la función en sí)
function upload_files_actualizacion($file_array) {
    $upload_dir = __DIR__ . '/uploads/actualizaciones/';
    if (!is_dir($upload_dir)) {
        if (!mkdir($upload_dir, 0777, true)) {
            error_log("Error: No se pudo crear el directorio " . $upload_dir);
            return ['success' => false, 'files' => [], 'error' => 'Error interno al crear directorio de subida.'];
        }
    }
    $results = []; $file_count = count($file_array['name']);
    for ($i = 0; $i < $file_count; $i++) {
        if (isset($file_array['error'][$i]) && $file_array['error'][$i] === UPLOAD_ERR_OK && !empty($file_array['name'][$i])) {
            $file_info = ['name' => $file_array['name'][$i], 'tmp_name' => $file_array['tmp_name'][$i]];
            $nombre_original = basename($file_info['name']); $extension = pathinfo($nombre_original, PATHINFO_EXTENSION);
            $nombre_servidor = uniqid('update_' . date('Ymd') . '_', true) . '.' . $extension; $ruta_completa = $upload_dir . $nombre_servidor;
            if (move_uploaded_file($file_info['tmp_name'], $ruta_completa)) {
                $results[] = ['nombre_original' => $nombre_original, 'nombre_servidor' => $nombre_servidor];
            } else { error_log("Error al mover archivo de actualización: " . $nombre_original); }
        } elseif (isset($file_array['error'][$i]) && $file_array['error'][$i] !== UPLOAD_ERR_NO_FILE) { error_log("Error de subida (Actualización) #" . $i . ": " . $file_array['error'][$i]); }
    } return ['success' => true, 'files' => $results, 'error' => null];
}
// --- FIN FUNCIÓN DE AYUDA ---


// 1. Proteger la página
if (!isset($_SESSION['usuario_id']) || $_SERVER['REQUEST_METHOD'] !== 'POST') {
    $_SESSION['action_error_message'] = 'Acceso no autorizado.';
    header("Location: dashboard.php");
    exit();
}

$id_usuario_actual = (int)$_SESSION['usuario_id'];
$rol_usuario_actual = $_SESSION['usuario_rol'] ?? ''; // <-- AÑADIDO POR GEMINI (v2)
$id_tarea = (int)($_POST['id_tarea'] ?? 0);
$contenido_actualizacion = trim($_POST['contenido'] ?? '');
$poner_en_reserva = isset($_POST['poner_en_reserva']) && $_POST['poner_en_reserva'] === '1';

// *** NUEVO: Capturar datos del remito ***
$remito_descripcion = trim($_POST['remito_descripcion'] ?? '');
$remito_precio = $_POST['remito_precio'] ?? null; // Mantener como null si está vacío o no es válido
$remito_numero = trim($_POST['remito_numero'] ?? '');

// Convertir precio a float o null
if ($remito_precio !== null && $remito_precio !== '' && is_numeric($remito_precio)) {
    $remito_precio = (float)$remito_precio;
} else {
    $remito_precio = null; // Guardar NULL si no es un número válido
}


$redirect_url = "tarea_ver.php?id=" . $id_tarea;

// 2. Validar datos principales
if ($id_tarea <= 0) {
    $_SESSION['action_error_message'] = "ID de tarea inválido.";
    header("Location: tareas_lista.php");
    exit();
}
if (empty($contenido_actualizacion)) {
    $_SESSION['action_error_message'] = "El contenido de la actualización no puede estar vacío.";
    header("Location: {$redirect_url}#actualizaciones");
    exit();
}

$pdo->beginTransaction();
$adjuntos_subidos = []; // Para adjuntos generales
$remitos_subidos = []; // Para remitos/facturas

try {
    // 3. Verificar permisos y estado de la tarea (sin cambios)
    $sql_check = "SELECT id_asignado, id_creador, titulo, estado FROM tareas WHERE id_tarea = :id_tarea";
    $stmt_check = $pdo->prepare($sql_check);
    $stmt_check->execute([':id_tarea' => $id_tarea]);
    $tarea_info = $stmt_check->fetch(PDO::FETCH_ASSOC);

    if (!$tarea_info) { throw new Exception("Tarea no encontrada."); }
    
    // --- INICIO MODIFICACIÓN GEMINI (v2) ---
    // Permite al 'admin' Y al 'encargado' actualizar cualquier tarea.
    // Los demás (empleado, auxiliar) solo pueden actualizar si están asignados.
    if ($tarea_info['id_asignado'] != $id_usuario_actual && !in_array($rol_usuario_actual, ['admin', 'encargado'])) { 
        throw new Exception("No tiene permisos para actualizar esta tarea."); 
    }
    // --- FIN MODIFICACIÓN GEMINI (v2) ---

    if (in_array($tarea_info['estado'], ['finalizada_tecnico', 'verificada', 'cancelada', 'asignada'])) {
        throw new Exception("No se pueden agregar actualizaciones a una tarea que no está 'En Proceso', 'En Modificación' o 'En Reserva'. Debe iniciarla primero.");
    }

    // 4. Insertar la actualización de texto (con causo_reserva corregido)
    $sql_insert_update = "INSERT INTO actualizaciones_tarea (id_tarea, id_usuario, contenido, fecha_actualizacion, causo_reserva)
                          VALUES (:id_tarea, :id_usuario, :contenido, NOW(), :causo_reserva)";
    $stmt_insert_update = $pdo->prepare($sql_insert_update);
    $causo_reserva_flag = $poner_en_reserva ? 1 : 0;
    $stmt_insert_update->execute([
        ':id_tarea' => $id_tarea,
        ':id_usuario' => $id_usuario_actual,
        ':contenido' => $contenido_actualizacion,
        ':causo_reserva' => $causo_reserva_flag
    ]);
    $id_actualizacion_nueva = $pdo->lastInsertId();

    // 5. CAMBIAR ESTADO DE LA TAREA (si aplica - sin cambios)
    if ($poner_en_reserva) {
        if ($tarea_info['estado'] !== 'en_reserva') {
            // --- INICIO MODIFICACIÓN GEMINI (v2) ---
            $sql_update_estado = "UPDATE tareas SET estado = 'en_reserva' 
                                  WHERE id_tarea = :id_tarea 
                                  AND (id_asignado = :id_usuario OR :rol_usuario IN ('admin', 'encargado'))";
            $stmt_update_estado = $pdo->prepare($sql_update_estado);
            $stmt_update_estado->execute([':id_tarea' => $id_tarea, ':id_usuario' => $id_usuario_actual, ':rol_usuario' => $rol_usuario_actual]);
            // --- FIN MODIFICACIÓN GEMINI (v2) ---
            
            if ($stmt_update_estado->rowCount() > 0) {
                 $_SESSION['action_success_message'] = "Actualización registrada y tarea puesta 'En Reserva'.";
            } else {
                 $_SESSION['action_success_message'] = "Actualización registrada (no se cambió el estado 'En Reserva').";
            }
        } else {
             $_SESSION['action_success_message'] = "Actualización registrada (la tarea ya estaba 'En Reserva').";
        }
    } else {
         if ($tarea_info['estado'] === 'en_reserva') {
             // --- INICIO MODIFICACIÓN GEMINI (v2) ---
             $sql_update_estado = "UPDATE tareas SET estado = 'en_proceso' 
                                   WHERE id_tarea = :id_tarea 
                                   AND (id_asignado = :id_usuario OR :rol_usuario IN ('admin', 'encargado'))";
             $stmt_update_estado = $pdo->prepare($sql_update_estado);
             $stmt_update_estado->execute([':id_tarea' => $id_tarea, ':id_usuario' => $id_usuario_actual, ':rol_usuario' => $rol_usuario_actual]);
             // --- FIN MODIFICACIÓN GEMINI (v2) ---
             
             $_SESSION['action_success_message'] = "Actualización registrada. La tarea ha sido quitada del estado 'En Reserva'.";
         } else {
            $_SESSION['action_success_message'] = "Actualización registrada correctamente.";
         }
    }

    // 6. Procesar y guardar ADJUNTOS GENERALES (sin cambios)
    if (isset($_FILES['adjuntos_actualizacion']) && !empty($_FILES['adjuntos_actualizacion']['name'][0])) {
        $upload_result = upload_files_actualizacion($_FILES['adjuntos_actualizacion']);
        if (!$upload_result['success']) { throw new Exception($upload_result['error'] ?? 'Error desconocido al subir adjuntos.'); }
        $adjuntos_subidos = $upload_result['files'];
        if (!empty($adjuntos_subidos)) {
            $sql_adjunto = "INSERT INTO adjuntos_tarea (id_tarea, id_actualizacion, tipo_adjunto, nombre_archivo, ruta_archivo, id_usuario_subida, fecha_subida) VALUES (:id_tarea, :id_actualizacion, 'actualizacion', :nombre, :ruta, :id_user, NOW())";
            $stmt_adjunto = $pdo->prepare($sql_adjunto);
            foreach ($adjuntos_subidos as $adj) {
                $stmt_adjunto->execute([':id_tarea' => $id_tarea, ':id_actualizacion' => $id_actualizacion_nueva, ':nombre' => $adj['nombre_original'], ':ruta' => $adj['nombre_servidor'], ':id_user' => $id_usuario_actual ]);
            }
        }
    }

    // *** 7. MODIFICADO: Procesar y guardar REMITOS/FACTURAS CON DETALLES ***
    if (isset($_FILES['adjuntos_remito']) && !empty($_FILES['adjuntos_remito']['name'][0])) {
        $upload_result_remitos = upload_files_actualizacion($_FILES['adjuntos_remito']);
        if (!$upload_result_remitos['success']) { throw new Exception($upload_result_remitos['error'] ?? 'Error desconocido al subir remitos.'); }
        $remitos_subidos = $upload_result_remitos['files'];

        if (!empty($remitos_subidos)) {
            // Preparar la inserción con los nuevos campos
            $sql_remito = "INSERT INTO adjuntos_tarea (
                                id_tarea, id_actualizacion, tipo_adjunto,
                                nombre_archivo, ruta_archivo,
                                descripcion_compra, precio_total, numero_compra, -- Nuevos campos
                                id_usuario_subida, fecha_subida
                           ) VALUES (
                                :id_tarea, :id_actualizacion, 'remito',
                                :nombre, :ruta,
                                :desc_compra, :precio, :num_compra, -- Nuevos placeholders
                                :id_user, NOW()
                           )";
            $stmt_remito = $pdo->prepare($sql_remito);

            // Asumimos que los detalles corresponden al primer archivo si se suben varios
            // Una lógica más avanzada requeriría manejar un array de detalles.
            $current_remito_descripcion = !empty($remito_descripcion) ? $remito_descripcion : null;
            $current_remito_precio = $remito_precio; // Ya es null si no es válido
            $current_remito_numero = !empty($remito_numero) ? $remito_numero : null;

            foreach ($remitos_subidos as $index => $rem) {
                // Solo aplicamos los detalles al primer archivo subido en este lote
                if ($index === 0) {
                     $desc = $current_remito_descripcion;
                     $precio = $current_remito_precio;
                     $num = $current_remito_numero;
                } else {
                    // Para los siguientes archivos, guardamos NULL
                     $desc = null;
                     $precio = null;
                     $num = null;
                }

                $stmt_remito->execute([
                    ':id_tarea' => $id_tarea,
                    ':id_actualizacion' => $id_actualizacion_nueva,
                    ':nombre' => $rem['nombre_original'],
                    ':ruta' => $rem['nombre_servidor'],
                    ':desc_compra' => $desc,
                    ':precio' => $precio,
                    ':num_compra' => $num,
                    ':id_user' => $id_usuario_actual
                ]);
            }
        }
    }
    // *** FIN MODIFICACIÓN REMITOS ***

    // 8. Notificar al administrador (sin cambios)
    $id_administrador = $tarea_info['id_creador'];
    if ($id_administrador && $id_administrador != $id_usuario_actual) {
        $nombre_tecnico = $_SESSION['usuario_nombre'] ?? 'El técnico'; $titulo_tarea = $tarea_info['titulo'];
        $mensaje_notif = "{$nombre_tecnico} agregó una novedad a la tarea #{$id_tarea}: {$titulo_tarea}.";
        if ($poner_en_reserva) { $mensaje_notif .= " Marcó la opción 'En Reserva'."; }
        if (!empty($remitos_subidos)) { $mensaje_notif .= " Se adjuntaron remitos/facturas."; } // Notifica si subió remito

        $protocolo = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? "https" : "http"); $host = $_SERVER['HTTP_HOST']; $ruta_base = rtrim(dirname($_SERVER['PHP_SELF']), '/\\'); $url_base_absoluta = $protocolo . '://' . $host . $ruta_base;
        $url_notif = $url_base_absoluta . "/tarea_ver.php?id={$id_tarea}&highlight_update={$id_actualizacion_nueva}#actualizaciones";

        $sql_notif = "INSERT INTO notificaciones (id_usuario_destino, mensaje, url, tipo, leida, fecha_creacion) VALUES (:id_destino, :mensaje, :url, 'tarea_actualizacion', 0, NOW())";
        $stmt_notif = $pdo->prepare($sql_notif);
        $stmt_notif->execute([':id_destino' => $id_administrador, ':mensaje' => $mensaje_notif, ':url' => $url_notif ]);
    }

    $pdo->commit();
    header("Location: {$redirect_url}&highlight_update={$id_actualizacion_nueva}#actualizaciones");
    exit();

} catch (Exception $e) {
    $pdo->rollBack();
    
    // --- INICIO MODIFICACIÓN GEMINI (v3): CORRECCIÓN DEL TYPO ---
    // Limpiar archivos subidos si la BD falló
    if (isset($adjuntos_subidos)) { foreach ($adjuntos_subidos as $adj) { @unlink(__DIR__ . '/uploads/actualizaciones/' . $adj['nombre_servidor']); } }
    if (isset($remitos_subidos)) { foreach ($remitos_subidos as $rem) { @unlink(__DIR__ . '/uploads/actualizaciones/' . $rem['nombre_servidor']); } }
    // --- FIN MODIFICACIÓN GEMINI (v3) ---

    error_log("Error al agregar actualización Tarea #" . $id_tarea . ": " . $e->getMessage());
    $_SESSION['action_error_message'] = "Error al procesar la actualización: " . $e->getMessage();
    header("Location: {$redirect_url}#actualizaciones");
    exit();
}
?>