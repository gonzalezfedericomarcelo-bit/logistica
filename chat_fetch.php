<?php
// chat_fetch.php
error_reporting(0);
ini_set('display_errors', 0);
session_start();
include 'conexion.php';
if (ob_get_length()) ob_clean();
header('Content-Type: application/json');

$uid = $_SESSION['usuario_id'] ?? 0;
if($uid == 0) { echo json_encode([]); exit; }

$chat_id = isset($_GET['chat_id']) ? intval($_GET['chat_id']) : 0; 
$last_id = isset($_GET['last_id']) ? intval($_GET['last_id']) : 0;
$limit = ($last_id == 0) ? "LIMIT 50" : "";

$where = "AND c.id_chat > :last";
if ($chat_id == 0) {
    $sql = "SELECT c.*, u.nombre_completo, u.foto_perfil FROM chat c JOIN usuarios u ON c.id_usuario = u.id_usuario WHERE c.id_destino = 0 $where ORDER BY c.fecha ASC $limit";
} else {
    $sql = "SELECT c.*, u.nombre_completo, u.foto_perfil FROM chat c JOIN usuarios u ON c.id_usuario = u.id_usuario WHERE ((c.id_usuario = :me AND c.id_destino = :him) OR (c.id_usuario = :him AND c.id_destino = :me)) $where ORDER BY c.fecha ASC $limit";
}

try {
    $stmt = $pdo->prepare($sql);
    $params = [':last' => $last_id];
    if ($chat_id != 0) { $params[':me'] = $uid; $params[':him'] = $chat_id; }
    $stmt->execute($params);

    $data = [];
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        // Links
        $msg = nl2br(htmlspecialchars($row['mensaje']));
        $msg = preg_replace('/#T(\d+)/i', '<a href="tarea_ver.php?id=$1" class="badge bg-warning text-dark text-decoration-none" target="_blank">#Tarea $1</a>', $msg);
        $msg = preg_replace('/#P(\d+)/i', '<a href="generar_pedido_pdf.php?id=$1" class="badge bg-info text-dark text-decoration-none" target="_blank">#Pedido $1</a>', $msg);

        // Media HTML
        $media = '';
        if($row['tipo_mensaje'] == 'audio') {
            $media = '<div class="mt-1"><audio controls preload="none" src="uploads/chat/'.$row['archivo_url'].'" style="height:32px; width:240px;"></audio></div>';
        } elseif($row['tipo_mensaje'] == 'archivo') {
            $ext = strtolower(pathinfo($row['archivo_nombre'], PATHINFO_EXTENSION));
            $ruta = 'uploads/chat/' . $row['archivo_url'];
            if(in_array($ext, ['jpg','jpeg','png','gif','webp'])) {
                 $media = '<div class="mt-1"><a href="'.$ruta.'" target="_blank"><img src="'.$ruta.'" class="img-fluid rounded border" style="max-height:250px; width:auto; object-fit:cover;"></a></div>';
            } else {
                 $media = '<div class="mt-1"><a href="'.$ruta.'" target="_blank" class="btn btn-light border btn-sm w-100 text-start d-flex align-items-center"><i class="fas fa-file me-2"></i> <span class="text-truncate">'.$row['archivo_nombre'].'</span></a></div>';
            }
        }

        $data[] = [
            'id' => $row['id_chat'],
            'es_mio' => ($row['id_usuario'] == $uid),
            'nombre' => explode(' ', $row['nombre_completo'])[0],
            'mensaje' => $msg,
            'media_html' => $media,
            'hora' => date('H:i', strtotime($row['fecha'])),
            'remitente_id' => $row['id_usuario'],
            'remitente_nombre' => $row['nombre_completo'],
            'remitente_foto' => !empty($row['foto_perfil']) ? 'uploads/perfiles/'.$row['foto_perfil'] : 'assets/default.png'
        ];
    }
    echo json_encode($data);
} catch (Exception $e) { echo json_encode([]); }
?>