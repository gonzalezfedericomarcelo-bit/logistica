<?php
// Archivo: obtener_contenido_aviso.php (SISTEMA DE HILOS Y REACCIONES)
session_start();
include 'conexion.php';

if (!isset($_SESSION['usuario_id'])) { http_response_code(403); exit; }
$id_usuario_actual = $_SESSION['usuario_id'];
$id_aviso = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if ($id_aviso > 0) {
    try {
        // 1. Contenido
        $stmt = $pdo->prepare("SELECT contenido FROM avisos WHERE id_aviso = :id");
        $stmt->execute([':id' => $id_aviso]);
        $aviso = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$aviso) { echo "<p>No encontrado.</p>"; exit; }

        // 2. Reacciones
        $stmt_reac = $pdo->prepare("SELECT tipo_reaccion, COUNT(*) as total FROM avisos_reacciones WHERE id_aviso = :id GROUP BY tipo_reaccion");
        $stmt_reac->execute([':id' => $id_aviso]);
        $reacciones_raw = $stmt_reac->fetchAll(PDO::FETCH_KEY_PAIR);
        
        $stmt_mi = $pdo->prepare("SELECT tipo_reaccion FROM avisos_reacciones WHERE id_aviso = :id AND id_usuario = :u");
        $stmt_mi->execute([':id' => $id_aviso, ':u' => $id_usuario_actual]);
        $mi_reaccion = $stmt_mi->fetchColumn();

        // 3. Comentarios (Ordenados por fecha)
        $stmt_com = $pdo->prepare("
            SELECT c.*, u.nombre_completo, u.foto_perfil 
            FROM avisos_comentarios c
            JOIN usuarios u ON c.id_usuario = u.id_usuario
            WHERE c.id_aviso = :id
            ORDER BY c.fecha ASC
        ");
        $stmt_com->execute([':id' => $id_aviso]);
        $todos_comentarios = $stmt_com->fetchAll(PDO::FETCH_ASSOC);

        // Organizar comentarios en Hilos (Padres e Hijos)
        $comentarios_padre = [];
        $respuestas = [];
        foreach ($todos_comentarios as $c) {
            if (empty($c['id_padre'])) {
                $comentarios_padre[] = $c;
            } else {
                $respuestas[$c['id_padre']][] = $c;
            }
        }

        // --- RENDERIZADO ---
        
echo '<style>
    .article-content img {
        max-width: 100% !important;
        height: auto !important;
        object-fit: contain !important; /* Evita recortes y deformaciones */
        display: block;
        margin: 10px auto; /* Centra las imágenes */
    }
    /* Opcional: Si en escritorio quieres limitar la altura para que no sean gigantes */
    @media (min-width: 768px) {
        .article-content img {
            max-height: 500px; /* Tope de altura en PC */
        }
    }
</style>';

echo '<div class="article-content mb-5">' . $aviso['contenido'] . '</div><hr>';

        // Reacciones
        $tipos = ['like' => ['👍', 'Me gusta'], 'love' => ['❤️', 'Me encanta'], 'haha' => ['😂', 'Me divierte'], 'wow' => ['😮', 'Me asombra'], 'angry' => ['😡', 'Me enoja']];
        echo '<div class="py-3"><h6 class="fw-bold text-muted mb-3">Reacciones</h6><div class="d-flex gap-2 flex-wrap">';
        foreach ($tipos as $k => $d) {
            $count = $reacciones_raw[$k] ?? 0;
            $cls = ($mi_reaccion === $k) ? 'btn-primary' : 'btn-outline-secondary border-0 bg-light';
            echo "<button onclick=\"enviarReaccion($id_aviso, '$k')\" class=\"btn $cls rounded-pill px-3 py-2 d-flex align-items-center gap-2 transition-btn\"><span class=\"fs-4\">{$d[0]}</span> <span class=\"fw-bold small\">$count</span></button>";
        }
        echo '</div></div><hr>';

        // Comentarios
        echo '<div class="py-3"><h5 class="fw-bold mb-4">Comentarios (' . count($todos_comentarios) . ')</h5>';
        
        // Formulario Principal
        echo '<div class="d-flex gap-2 mb-5">
                <textarea id="txtComentarioMain" class="form-control" rows="2" placeholder="Escribe un comentario..."></textarea>
                <button onclick="enviarComentario('.$id_aviso.', null)" class="btn btn-primary px-4"><i class="fas fa-paper-plane"></i></button>
              </div>';

        echo '<div id="lista-comentarios" class="d-flex flex-column gap-3">';
        
        if (empty($comentarios_padre)) {
            echo '<p class="text-muted fst-italic" id="no-comments-msg">Sé el primero en comentar...</p>';
        } else {
            foreach ($comentarios_padre as $padre) {
                renderizar_comentario($padre, $respuestas[$padre['id_comentario']] ?? [], $id_aviso);
            }
        }
        echo '</div></div>';

    } catch (Exception $e) { echo "Error."; }
}

// Función Recursiva para renderizar
function renderizar_comentario($c, $hijos, $id_aviso) {
    $foto = !empty($c['foto_perfil']) ? "uploads/perfiles/{$c['foto_perfil']}" : "assets/img/default-user.png";
    $fecha = date('d/m H:i', strtotime($c['fecha']));
    $id_com = $c['id_comentario'];
    
    // ID para el scroll y clase para animación
    echo "<div id='comentario-$id_com' class='comment-block bg-white rounded shadow-sm border p-3'>";
    
    echo "<div class='d-flex gap-3'>
            <img src='$foto' class='rounded-circle' width='40' height='40' style='object-fit:cover'>
            <div class='w-100'>
                <div class='d-flex justify-content-between'>
                    <span class='fw-bold text-dark'>" . htmlspecialchars($c['nombre_completo']) . "</span>
                    <small class='text-muted'>$fecha</small>
                </div>
                <p class='mb-1 text-secondary'>" . nl2br(htmlspecialchars($c['comentario'])) . "</p>
                
                <button class='btn btn-link btn-sm p-0 text-decoration-none' onclick='toggleReply($id_com)'>Responder</button>
                
                <div id='reply-form-$id_com' class='mt-2 d-none'>
                    <div class='d-flex gap-2'>
                        <input type='text' id='txtReply-$id_com' class='form-control form-control-sm' placeholder='Tu respuesta...'>
                        <button onclick='enviarComentario($id_aviso, $id_com)' class='btn btn-sm btn-primary'><i class='fas fa-reply'></i></button>
                    </div>
                </div>
            </div>
          </div>";

    // Renderizar Hijos (Indentados)
    if (!empty($hijos)) {
        echo "<div class='ms-5 mt-3 d-flex flex-column gap-2 border-start ps-3'>";
        foreach ($hijos as $hijo) {
            renderizar_comentario($hijo, [], $id_aviso); // Recursividad (aunque solo 1 nivel es común)
        }
        echo "</div>";
    }
    
    echo "</div>";
}
?>