<?php
// Archivo: avisos_interaccion.php (SOPORTE PARA RESPUESTAS Y DESTAQUE)
session_start();
include 'conexion.php';

header('Content-Type: application/json');

if (!isset($_SESSION['usuario_id'])) {
    echo json_encode(['success' => false, 'msg' => 'Sesión expirada']);
    exit;
}

$id_usuario = $_SESSION['usuario_id'];
$accion = $_POST['accion'] ?? '';

try {
    // ---------------------------------------------------------
    // ACCIÓN: REACCIONAR
    // ---------------------------------------------------------
    if ($accion === 'reaccionar') {
        $id_aviso = (int)$_POST['id_aviso'];
        $tipo = $_POST['tipo'];

        // Verificar si ya existe
        $stmt = $pdo->prepare("SELECT id_reaccion, tipo_reaccion FROM avisos_reacciones WHERE id_aviso = :aviso AND id_usuario = :user");
        $stmt->execute([':aviso' => $id_aviso, ':user' => $id_usuario]);
        $existe = $stmt->fetch();

        if ($existe) {
            if ($existe['tipo_reaccion'] === $tipo) {
                $del = $pdo->prepare("DELETE FROM avisos_reacciones WHERE id_reaccion = :id");
                $del->execute([':id' => $existe['id_reaccion']]);
                $msg = 'Reacción quitada';
            } else {
                $upd = $pdo->prepare("UPDATE avisos_reacciones SET tipo_reaccion = :tipo, fecha = NOW() WHERE id_reaccion = :id");
                $upd->execute([':tipo' => $tipo, ':id' => $existe['id_reaccion']]);
                $msg = 'Reacción actualizada';
            }
        } else {
            $ins = $pdo->prepare("INSERT INTO avisos_reacciones (id_aviso, id_usuario, tipo_reaccion) VALUES (:aviso, :user, :tipo)");
            $ins->execute([':aviso' => $id_aviso, ':user' => $id_usuario, ':tipo' => $tipo]);
            $msg = 'Reacción agregada';
            
            notificar_interaccion($pdo, $id_aviso, $id_usuario, 'reaccion');
        }

        echo json_encode(['success' => true, 'msg' => $msg]);
    }

    // ---------------------------------------------------------
    // ACCIÓN: COMENTAR (O RESPONDER)
    // ---------------------------------------------------------
    elseif ($accion === 'comentar') {
        $id_aviso = (int)$_POST['id_aviso'];
        $comentario = trim($_POST['comentario']);
        $id_padre = !empty($_POST['id_padre']) ? (int)$_POST['id_padre'] : null;

        if (empty($comentario)) {
            echo json_encode(['success' => false, 'msg' => 'Comentario vacío']);
            exit;
        }

        $ins = $pdo->prepare("INSERT INTO avisos_comentarios (id_aviso, id_usuario, comentario, id_padre) VALUES (:aviso, :user, :txt, :padre)");
        $ins->execute([':aviso' => $id_aviso, ':user' => $id_usuario, ':txt' => $comentario, ':padre' => $id_padre]);
        
        $nuevo_id_comentario = $pdo->lastInsertId();

        // Notificar
        notificar_interaccion($pdo, $id_aviso, $id_usuario, 'comentario', $id_padre, $nuevo_id_comentario);

        echo json_encode(['success' => true, 'msg' => 'Comentario publicado']);
    } 
    else {
        echo json_encode(['success' => false, 'msg' => 'Acción no válida']);
    }

} catch (Exception $e) {
    echo json_encode(['success' => false, 'msg' => 'Error: ' . $e->getMessage()]);
}

// --- FUNCIÓN DE NOTIFICACIÓN INTELIGENTE ---
function notificar_interaccion($pdo, $id_aviso, $id_actor, $tipo_evento, $id_padre = null, $id_nuevo_comentario = null) {
    
    // 1. Datos del actor
    $stmtUser = $pdo->prepare("SELECT nombre_completo FROM usuarios WHERE id_usuario = :id");
    $stmtUser->execute([':id' => $id_actor]);
    $nombre_actor = $stmtUser->fetchColumn();

    // 2. Datos del Aviso y su Creador
    $stmtAviso = $pdo->prepare("SELECT id_creador, titulo FROM avisos WHERE id_aviso = :id");
    $stmtAviso->execute([':id' => $id_aviso]);
    $aviso = $stmtAviso->fetch();
    if (!$aviso) return;

    $id_creador_aviso = $aviso['id_creador'];
    $titulo_corto = mb_substr($aviso['titulo'], 0, 20) . "...";
    
    // URL CON ANCLA PARA SCROLL Y RESALTADO
    // El parámetro 'cid' (Comment ID) será usado por el JS para hacer scroll y highlight
    $url_base = "avisos.php?show_id=" . $id_aviso;
    if ($id_nuevo_comentario) {
        $url_base .= "&cid=" . $id_nuevo_comentario;
    }

    // --- CASO A: NOTIFICAR AL DUEÑO DEL AVISO ---
    if ($id_creador_aviso != $id_actor) {
        $mensaje = ($tipo_evento == 'reaccion') 
            ? "$nombre_actor reaccionó a: $titulo_corto" 
            : "$nombre_actor comentó en: $titulo_corto";
            
        insertar_notificacion($pdo, $id_creador_aviso, "aviso_$tipo_evento", $mensaje, $url_base);
    }

    // --- CASO B: SI ES RESPUESTA, NOTIFICAR AL DUEÑO DEL COMENTARIO ORIGINAL ---
    if ($id_padre) {
        $stmtPadre = $pdo->prepare("SELECT id_usuario FROM avisos_comentarios WHERE id_comentario = :id");
        $stmtPadre->execute([':id' => $id_padre]);
        $id_dueno_comentario = $stmtPadre->fetchColumn();

        // Si el dueño del comentario no es el mismo que escribe la respuesta, Y no es el dueño del post (ya notificado arriba)
        if ($id_dueno_comentario && $id_dueno_comentario != $id_actor && $id_dueno_comentario != $id_creador_aviso) {
            $mensaje_resp = "$nombre_actor respondió tu comentario en: $titulo_corto";
            insertar_notificacion($pdo, $id_dueno_comentario, "aviso_comentario", $mensaje_resp, $url_base);
        }
    }
}

function insertar_notificacion($pdo, $dest, $tipo, $msg, $url) {
    $sql = "INSERT INTO notificaciones (id_usuario_destino, tipo, mensaje, url, leida, fecha_creacion) VALUES (?, ?, ?, ?, 0, NOW())";
    $pdo->prepare($sql)->execute([$dest, $tipo, $msg, $url]);
}
?>