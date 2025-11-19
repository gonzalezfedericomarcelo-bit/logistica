<?php
// Archivo: tarea_finalizar_procesar.php (VERSIÓN FINAL Y ROBUSTA CON REDIRECCIÓN Y NOTIFICACIÓN VERIFICADA)
// *** MODIFICADO (v2) POR GEMINI PARA PERMITIR FINALIZAR A ROLES 'auxiliar' y 'encargado' ***
session_start();
include 'conexion.php';

// --- FUNCIÓN DE AYUDA PARA LA SUBIDA DE ARCHIVOS ---
function upload_files($file_array, $upload_dir) {
    if (!is_dir($upload_dir)) { mkdir($upload_dir, 0777, true); }
    $results = []; $file_count = count($file_array['name']);
    for ($i = 0; $i < $file_count; $i++) {
        if ($file_array['error'][$i] === UPLOAD_ERR_OK && !empty($file_array['name'][$i])) {
            $file_info = ['name' => $file_array['name'][$i], 'tmp_name' => $file_array['tmp_name'][$i]];
            $nombre_original = basename($file_info['name']); $extension = pathinfo($nombre_original, PATHINFO_EXTENSION);
            $nombre_servidor = uniqid('final_', true) . '.' . $extension; $ruta_completa = $upload_dir . $nombre_servidor;
            if (move_uploaded_file($file_info['tmp_name'], $ruta_completa)) {
                $results[] = ['nombre_original' => $nombre_original, 'nombre_servidor' => $nombre_servidor];
            }
        }
    } return $results;
}

// 1. Proteger la página
// --- INICIO MODIFICACIÓN GEMINI (v2) ---
// Se permite finalizar a los tres roles que pueden recibir tareas
if (!isset($_SESSION['usuario_id']) || !in_array($_SESSION['usuario_rol'], ['empleado', 'auxiliar', 'encargado']) || $_SERVER['REQUEST_METHOD'] !== 'POST') {
// --- FIN MODIFICACIÓN GEMINI (v2) ---
    header("Location: dashboard.php"); exit();
}

$id_usuario_actual = $_SESSION['usuario_id'];
$id_tarea = (int)($_POST['id_tarea'] ?? 0);
$nota_final = trim($_POST['nota_final'] ?? '');
$nuevo_estado = 'finalizada_tecnico';

$redirect_url = "tarea_ver.php?id={$id_tarea}";

if ($id_tarea <= 0 || empty($nota_final)) {
    $_SESSION['action_error_message'] = "Datos incompletos para finalizar la tarea.";
    header("Location: " . $redirect_url); exit();
}

try {
    $pdo->beginTransaction();

    // 2. Obtener datos de la tarea para validación
    $sql_tarea = "SELECT t.id_creador, t.titulo, t.adjunto_obligatorio, u.nombre_completo as nombre_tecnico
                  FROM tareas t
                  LEFT JOIN usuarios u ON t.id_asignado = u.id_usuario
                  WHERE t.id_tarea = :id_tarea AND t.id_asignado = :id_asignado";
    $stmt_tarea = $pdo->prepare($sql_tarea);
    $stmt_tarea->execute([':id_tarea' => $id_tarea, ':id_asignado' => $id_usuario_actual]);
    $tarea_data = $stmt_tarea->fetch(PDO::FETCH_ASSOC);

    if (!$tarea_data) { throw new Exception("Tarea no encontrada o no asignada a este usuario."); }

    $titulo_tarea = $tarea_data['titulo'];
    $adjunto_obligatorio = (int)$tarea_data['adjunto_obligatorio'];
    $nombre_tecnico = $tarea_data['nombre_tecnico'] ?? $_SESSION['usuario_nombre']; // Usar nombre de sesión como fallback
    $id_administrador = (int)$tarea_data['id_creador'];

    // 3. Procesar Subida de Adjuntos Finales
    $adjuntos_finales_subidos = []; $upload_dir = __DIR__ . '/uploads/tareas/';
    if (isset($_FILES['adjunto_final']) && !empty($_FILES['adjunto_final']['name'][0])) {
        $adjuntos_finales_subidos = upload_files($_FILES['adjunto_final'], $upload_dir);
    }

    // 4. Validación Obligatoria
    if ($adjunto_obligatorio === 1 && empty($adjuntos_finales_subidos)) {
        throw new Exception("La tarea requiere adjuntar documentos finales.");
    }

    // 5. Actualizar estado y nota en la tabla tareas
    $sql_update = "UPDATE tareas SET estado = :nuevo_estado, nota_final = :nota_final, fecha_cierre = NOW()
                   WHERE id_tarea = :id_tarea AND id_asignado = :id_asignado";
    $stmt_update = $pdo->prepare($sql_update);
    $stmt_update->execute([':nuevo_estado' => $nuevo_estado, ':nota_final' => $nota_final, ':id_tarea' => $id_tarea, ':id_asignado' => $id_usuario_actual]);

    // 6. Insertar adjuntos finales en adjuntos_tarea
    if (!empty($adjuntos_finales_subidos)) {
        $sql_insert_adj = "INSERT INTO adjuntos_tarea (id_tarea, nombre_archivo, ruta_archivo, tipo_adjunto, id_usuario_subida, fecha_subida)
                           VALUES (:id_tarea, :nombre, :ruta, 'final', :id_usuario_subida, NOW())";
        $stmt_insert_adj = $pdo->prepare($sql_insert_adj);
        foreach ($adjuntos_finales_subidos as $adj) {
            $stmt_insert_adj->execute([':id_tarea' => $id_tarea, ':nombre' => $adj['nombre_original'], ':ruta' => $adj['nombre_servidor'], ':id_usuario_subida' => $id_usuario_actual]);
        }
    }

    // 6.5. CÁLCULO DE RUTA BASE
    $protocolo = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? "https" : "http");
    $host = $_SERVER['HTTP_HOST']; $ruta_base = rtrim(dirname($_SERVER['PHP_SELF']), '/\\');
    $url_base_absoluta = $protocolo . '://' . $host . $ruta_base;

    // 7. Insertar NOTIFICACIÓN para el ADMINISTRADOR (BLOQUE VERIFICADO)
    if ($id_administrador > 0) { // Solo si hay un creador válido
        $mensaje_notificacion = "El técnico {$nombre_tecnico} ha finalizado la tarea #{$id_tarea}: {$titulo_tarea}. Requiere su verificación.";
        $url_notificacion = $url_base_absoluta . "/tarea_ver.php?id={$id_tarea}#acciones";
        $tipo_notificacion = "tarea_terminada";

        $sql_notif = "INSERT INTO notificaciones (id_usuario_destino, mensaje, url, tipo, leida, fecha_creacion)
                      VALUES (:id_destino, :mensaje, :url, :tipo, 0, NOW())";
        $stmt_notif = $pdo->prepare($sql_notif);
        $stmt_notif->execute([
            ':id_destino' => $id_administrador,
            ':mensaje' => $mensaje_notificacion,
            ':url' => $url_notificacion,
            ':tipo' => $tipo_notificacion
        ]);
    } else {
        error_log("No se encontró ID de creador para notificar finalización de tarea #{$id_tarea}");
    }

    $pdo->commit();

    $_SESSION['action_success_message'] = "¡Tarea completada! Ha sido enviada a verificación. Muchas gracias.";
    header("Location: " . $redirect_url . "&highlight_final=true#archivos"); // Resaltar sección adjuntos finales
    exit();

} catch (Exception $e) {
    $pdo->rollBack();
    error_log("Error al finalizar tarea #{$id_tarea}: " . $e->getMessage());
    $_SESSION['action_error_message'] = "Error al finalizar: " . $e->getMessage();
    header("Location: " . $redirect_url);
    exit();
}
?>