<?php
// chat_enviar.php - BLINDADO Y LIMPIO
error_reporting(0);
ini_set('display_errors', 0);
ob_start();
session_start();
include 'conexion.php';
if (ob_get_length()) ob_clean();
header('Content-Type: application/json; charset=utf-8');

$response = ['status' => 'error', 'msg' => 'Error desconocido'];

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_SESSION['usuario_id'])) {
    $uid = $_SESSION['usuario_id'];
    // Recibimos el HTML crudo del editor
    $mensaje = trim($_POST['mensaje'] ?? '');
    $destino_id = isset($_POST['destino_id']) ? intval($_POST['destino_id']) : 0;
    $nombre_remitente = $_SESSION['usuario_nombre'] ?? 'Usuario';

    $tipo = 'texto';
    $archivo_url = null;
    $archivo_nombre = null;

    $dir = 'uploads/chat/';
    if (!is_dir($dir)) @mkdir($dir, 0777, true);

    // Audio
    if (isset($_FILES['audio']) && $_FILES['audio']['size'] > 0) {
        $filename = 'voice_' . time() . '_' . $uid . '.webm';
        if (move_uploaded_file($_FILES['audio']['tmp_name'], $dir . $filename)) {
            $tipo = 'audio';
            $archivo_url = $filename;
            $archivo_nombre = 'Nota de voz';
            $mensaje = ''; 
        }
    } 
    // Archivo
    elseif (isset($_FILES['adjunto']) && $_FILES['adjunto']['error'] === 0) {
        $original = $_FILES['adjunto']['name'];
        $clean_name = preg_replace("/[^a-zA-Z0-9.]/", "_", $original); 
        $filename = time() . '_' . $clean_name;
        if (move_uploaded_file($_FILES['adjunto']['tmp_name'], $dir . $filename)) {
            $tipo = 'archivo';
            $archivo_url = $filename;
            $archivo_nombre = $original;
            if(empty($mensaje)) $mensaje = "📎 Archivo adjunto";
        }
    }

    if (!empty($mensaje) || $archivo_url) {
        try {
            $pdo->beginTransaction();
            
            // 1. Guardamos el mensaje con HTML en el chat para que se vea bonito
            $sql = "INSERT INTO chat (id_usuario, id_destino, mensaje, tipo_mensaje, archivo_url, archivo_nombre, fecha, leido) 
                    VALUES (:uid, :dest, :msg, :tipo, :url, :nom, NOW(), 0)";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([':uid'=>$uid, ':dest'=>$destino_id, ':msg'=>$mensaje, ':tipo'=>$tipo, ':url'=>$archivo_url, ':nom'=>$archivo_nombre]);
            
            $msg_id = $pdo->lastInsertId();

            // 2. Creamos la notificación (limpiando el HTML para que no rompa la alerta)
            if ($destino_id > 0) {
                $mensaje_limpio = strip_tags(html_entity_decode($mensaje)); // Solo texto plano
                
                $txt_notif = "💬 De " . $nombre_remitente . ": " . (strlen($mensaje_limpio) > 30 ? substr($mensaje_limpio,0,30)."..." : $mensaje_limpio);
                if($tipo == 'audio') $txt_notif = "🎤 Audio de " . $nombre_remitente;
                if($tipo == 'archivo') $txt_notif = "📎 Archivo de " . $nombre_remitente;
                
                // Link inteligente con ID de mensaje
                $link_chat = "chat.php?chat_id=" . $uid . "&msg_id=" . $msg_id;

                // Usamos id_usuario_destino para compatibilidad
                $sql_n = "INSERT INTO notificaciones (id_usuario_destino, mensaje, fecha_creacion, tipo, url) VALUES (:dest, :txt, NOW(), 'chat', :link)";
                $stmt_n = $pdo->prepare($sql_n);
                $stmt_n->execute([':dest'=>$destino_id, ':txt'=>$txt_notif, ':link'=>$link_chat]);
            }
            $pdo->commit();
            $response = ['status' => 'success'];
        } catch (Exception $e) {
            $pdo->rollBack();
            $response = ['status' => 'error', 'msg' => 'Error BD: ' . $e->getMessage()];
        }
    } else {
        $response = ['status' => 'error', 'msg' => 'Mensaje vacío'];
    }
}
echo json_encode($response);
exit;
?>