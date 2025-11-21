<?php
// Archivo: avisos.php (RESPONSIVE MEJORADO)
session_start();
include 'conexion.php';
include 'funciones_permisos.php'; 

$es_admin_avisos = isset($_SESSION['usuario_id']) && tiene_permiso('acceso_avisos_gestionar', $pdo);
$id_usuario_actual = $_SESSION['usuario_id'] ?? 0;

// 1. Cargar IDs leídos
$leidos_ids = [];
if ($id_usuario_actual > 0) {
    try {
        $stmt = $pdo->prepare("SELECT id_aviso FROM avisos_lecturas WHERE id_usuario = :uid");
        $stmt->execute([':uid' => $id_usuario_actual]);
        $leidos_ids = $stmt->fetchAll(PDO::FETCH_COLUMN, 0);
    } catch (Exception $e) {}
}

// 2. Filtros
$filtro_cat = $_GET['cat'] ?? '';
$filtro_sql = " WHERE a.es_activo = 1 ";
$params = [];
if ($filtro_cat) { $filtro_sql .= " AND a.categoria = :cat "; $params[':cat'] = $filtro_cat; }

// 3. Obtener Avisos (Feed)
try {
    $sql = "SELECT id_aviso, titulo, imagen_destacada, fecha_publicacion, categoria,
            SUBSTRING(contenido, 1, 300) as extracto_crudo, 
            u.nombre_completo AS creador,
            (SELECT COUNT(*) FROM avisos_comentarios WHERE id_aviso = a.id_aviso) as c_com,
            (SELECT COUNT(*) FROM avisos_reacciones WHERE id_aviso = a.id_aviso) as c_reac
            FROM avisos a JOIN usuarios u ON a.id_creador = u.id_usuario
            $filtro_sql ORDER BY a.fecha_publicacion DESC";
    $stmt = $pdo->prepare($sql); $stmt->execute($params);
    $avisos = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) { $avisos = []; }

// 4. Datos para el Sidebar
try {
    $cats = $pdo->query("SELECT categoria, COUNT(*) as total FROM avisos WHERE es_activo=1 GROUP BY categoria ORDER BY total DESC")->fetchAll(PDO::FETCH_ASSOC);
    $populares = $pdo->query("SELECT id_aviso, titulo, (SELECT COUNT(*) FROM avisos_reacciones WHERE id_aviso=a.id_aviso) + (SELECT COUNT(*) FROM avisos_comentarios WHERE id_aviso=a.id_aviso) as score FROM avisos a WHERE es_activo=1 ORDER BY score DESC LIMIT 5")->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) { $cats=[]; $populares=[]; }

function obtener_extracto_limpio($html) {
    $texto = trim(str_replace('&nbsp;', ' ', strip_tags(str_replace(['<br>', '</p>'], ' ', $html))));
    return empty($texto) ? "Ver detalles..." : mb_substr($texto, 0, 120) . '...';
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0"> <title>Blog Institucional</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">
    <style>
        body { background-color: #f8f9fa; font-family: 'Segoe UI', sans-serif; }
        
        /* Estilos Tarjeta */
        .blog-card { border:none; border-radius:12px; background:#fff; overflow:hidden; transition:0.3s; box-shadow:0 2px 10px rgba(0,0,0,0.05); margin-bottom: 30px; cursor: pointer; }
        .blog-card:hover { transform:translateY(-5px); box-shadow:0 15px 30px rgba(0,0,0,0.1); }
        .blog-img { height:200px; width:100%; object-fit:cover; }
        .blog-body { padding:20px; }
        .blog-cat { font-size:0.75rem; font-weight:800; text-transform:uppercase; letter-spacing:1px; color:#0d6efd; }
        .blog-title { font-size:1.4rem; font-weight:bold; margin:10px 0; color:#212529; line-height:1.3; }
        .blog-meta { font-size:0.85rem; color:#6c757d; margin-bottom:15px; }
        
        /* Sidebar */
        .sidebar-widget { background:#fff; padding:20px; border-radius:12px; margin-bottom:30px; box-shadow:0 2px 10px rgba(0,0,0,0.03); }
        .widget-title { font-weight:bold; font-size:1.1rem; margin-bottom:15px; border-bottom:2px solid #f0f2f5; padding-bottom:10px; }
        .cat-link { display:flex; justify-content:space-between; color:#495057; text-decoration:none; padding:8px 0; border-bottom:1px solid #f8f9fa; transition:0.2s; }
        .cat-link:hover { color:#0d6efd; padding-left:5px; }
        .pop-item { display:flex; gap:10px; margin-bottom:15px; cursor:pointer; }
        .pop-title { font-size:0.9rem; font-weight:600; color:#343a40; line-height:1.4; }
        .pop-title:hover { color:#0d6efd; }
        
        /* Highlight Anim */
        @keyframes highlightFade { 0% { background-color: #fff3cd; border: 2px solid #ffc107; } 100% { background-color: #fff; border: 1px solid #dee2e6; } }
        .highlight-comment { animation: highlightFade 3s ease-out forwards; }

        /* RESPONSIVE TWEAKS */
        @media (max-width: 768px) {
            .blog-img { height: 180px; }
            .blog-title { font-size: 1.2rem; }
            .container { padding-left: 15px; padding-right: 15px; }
            .modal-header { padding: 1rem; }
            .modal-body .container { padding: 1rem !important; }
        }
    </style>
</head>
<body>
    <?php include 'navbar.php'; ?>

    <div class="container py-4 py-md-5">
        <div class="row">
            
            <div class="col-lg-8">
                <div class="d-flex flex-column flex-md-row justify-content-between align-items-start align-items-md-center mb-4 gap-3">
                    <h2 class="fw-bold mb-0"><i class="fas fa-rss text-warning me-2"></i> Últimas Publicaciones</h2>
                    <?php if($es_admin_avisos): ?>
                        <a href="avisos_crear.php" class="btn btn-primary rounded-pill fw-bold w-100 w-md-auto"><i class="fas fa-plus me-2"></i>Nueva Entrada</a>
                    <?php endif; ?>
                </div>

                <?php if(empty($avisos)): ?>
                    <div class="text-center py-5 text-muted">No hay publicaciones en esta categoría.</div>
                <?php else: ?>
                    <div class="row">
                    <?php foreach($avisos as $aviso): 
                        $img = !empty($aviso['imagen_destacada']) ? 'uploads/avisos/'.$aviso['imagen_destacada'] : 'assets/img/placeholder_news.jpg';
                        $leido = in_array($aviso['id_aviso'], $leidos_ids);
                    ?>
                        <div class="col-md-6 mb-3"> <div id="card_<?php echo $aviso['id_aviso']; ?>" class="blog-card h-100" onclick="abrirAviso(<?php echo $aviso['id_aviso']; ?>, '<?php echo addslashes($aviso['titulo']); ?>')">
                                <img src="<?php echo $img; ?>" class="blog-img">
                                <div class="blog-body d-flex flex-column h-100">
                                    <div class="d-flex justify-content-between">
                                        <span class="blog-cat"><?php echo htmlspecialchars($aviso['categoria']); ?></span>
                                        <?php if(!$leido): ?><span class="badge bg-danger rounded-pill">NUEVO</span><?php endif; ?>
                                    </div>
                                    <h3 class="blog-title"><?php echo htmlspecialchars($aviso['titulo']); ?></h3>
                                    <div class="blog-meta"><i class="far fa-clock me-1"></i> <?php echo date('d M', strtotime($aviso['fecha_publicacion'])); ?> &bull; <i class="far fa-user me-1"></i> <?php echo explode(' ', $aviso['creador'])[0]; ?></div>
                                    <p class="text-muted small flex-grow-1"><?php echo obtener_extracto_limpio($aviso['extracto_crudo']); ?></p>
                                    <div class="d-flex justify-content-between pt-3 border-top mt-auto">
                                        <span class="text-primary fw-bold small">Leer más <i class="fas fa-arrow-right"></i></span>
                                        <div class="text-muted small">
                                            <i class="far fa-thumbs-up me-1"></i> <?php echo $aviso['c_reac']; ?>
                                            <i class="far fa-comment ms-2 me-1"></i> <?php echo $aviso['c_com']; ?>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>

            <div class="col-lg-4 mt-4 mt-lg-0">
                <div class="sidebar-widget">
                    <h5 class="widget-title">Buscar</h5>
                    <div class="input-group">
                        <input type="text" id="searchInput" class="form-control" placeholder="Palabra clave...">
                        <button class="btn btn-outline-secondary"><i class="fas fa-search"></i></button>
                    </div>
                </div>

                <div class="sidebar-widget">
                    <h5 class="widget-title">Categorías</h5>
                    <div class="d-flex flex-column">
                        <a href="avisos.php" class="cat-link"><span>Todas</span></a>
                        <?php foreach($cats as $c): ?>
                            <a href="avisos.php?cat=<?php echo urlencode($c['categoria']); ?>" class="cat-link">
                                <span><?php echo htmlspecialchars($c['categoria']); ?></span>
                                <span class="badge bg-light text-secondary rounded-pill border"><?php echo $c['total']; ?></span>
                            </a>
                        <?php endforeach; ?>
                    </div>
                </div>

                <div class="sidebar-widget">
                    <h5 class="widget-title">Más Populares</h5>
                    <?php foreach($populares as $p): ?>
                        <div class="pop-item" onclick="abrirAviso(<?php echo $p['id_aviso']; ?>, '<?php echo addslashes($p['titulo']); ?>')">
                            <div class="bg-light rounded d-flex align-items-center justify-content-center text-secondary fw-bold flex-shrink-0" style="width:40px; height:40px; font-size:1.2rem;">#</div>
                            <div class="pop-title text-wrap"><?php echo htmlspecialchars($p['titulo']); ?></div>
                        </div>
                    <?php endforeach; ?>
                </div>
                
                <?php if($es_admin_avisos): ?>
                <div class="d-grid">
                    <a href="avisos_lista.php" class="btn btn-outline-dark"><i class="fas fa-cog me-2"></i>Gestionar Todo</a>
                </div>
                <?php endif; ?>
            </div>

        </div>
    </div>

    <div class="modal fade" id="modalVisor" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-fullscreen">
            <div class="modal-content border-0">
                <div class="modal-header border-bottom px-3 py-2 sticky-top bg-white shadow-sm">
                    <button type="button" class="btn btn-light rounded-circle me-2" data-bs-dismiss="modal"><i class="fas fa-arrow-left"></i></button>
                    <h5 class="modal-title fw-bold text-dark text-truncate flex-grow-1" id="modalTitulo" style="font-size: 1rem;"></h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body bg-light p-0">
                    <div class="container py-4" style="max-width: 900px;">
                        <div class="bg-white p-3 p-md-5 rounded shadow-sm" id="modalContenido"></div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        const modalVisor = new bootstrap.Modal(document.getElementById('modalVisor'));
        let targetCommentId = null;

        function abrirAviso(id, titulo) {
            document.getElementById('modalTitulo').textContent = titulo;
            document.getElementById('modalContenido').innerHTML = '<div class="text-center py-5"><div class="spinner-border text-primary"></div></div>';
            modalVisor.show();

            fetch(`obtener_contenido_aviso.php?id=${id}`)
                .then(r => r.text())
                .then(html => {
                    document.getElementById('modalContenido').innerHTML = html;
                    
                    // ** LÓGICA DE SCROLL Y HIGHLIGHT **
                    if (targetCommentId) {
                        setTimeout(() => {
                            const el = document.getElementById('comentario-' + targetCommentId);
                            if (el) {
                                el.scrollIntoView({ behavior: 'smooth', block: 'center' });
                                el.classList.add('highlight-comment');
                            }
                            targetCommentId = null; // Reset
                        }, 500); 
                    }
                    
                    // Marcar como leído
                    fetch(`marcar_aviso_leido.php`, {method:'POST', headers:{'Content-Type':'application/x-www-form-urlencoded'}, body:'id_aviso='+id});
                });
        }

        function enviarReaccion(id, tipo) {
            const fd = new FormData(); fd.append('accion','reaccionar'); fd.append('id_aviso',id); fd.append('tipo',tipo);
            fetch('avisos_interaccion.php', {method:'POST', body:fd}).then(r=>r.json()).then(d=>{ if(d.success) reloadContent(id); });
        }

        function toggleReply(id) {
            const form = document.getElementById('reply-form-' + id);
            form.classList.toggle('d-none');
        }

        function enviarComentario(idAviso, idPadre) {
            const inputId = idPadre ? 'txtReply-' + idPadre : 'txtComentarioMain';
            const txt = document.getElementById(inputId).value;
            if(!txt.trim()) return;

            const fd = new FormData(); 
            fd.append('accion','comentar'); 
            fd.append('id_aviso', idAviso); 
            fd.append('comentario', txt);
            if(idPadre) fd.append('id_padre', idPadre);

            fetch('avisos_interaccion.php', {method:'POST', body:fd}).then(r=>r.json()).then(d=>{ 
                if(d.success) reloadContent(idAviso); 
                else alert(d.msg);
            });
        }

        function reloadContent(id) {
            fetch(`obtener_contenido_aviso.php?id=${id}`).then(r=>r.text()).then(html => { document.getElementById('modalContenido').innerHTML = html; });
        }

        document.addEventListener('DOMContentLoaded', () => {
            const urlParams = new URLSearchParams(window.location.search);
            const showId = urlParams.get('show_id');
            const cid = urlParams.get('cid');
            
            if (showId) {
                if (cid) targetCommentId = cid;
                const card = document.getElementById('card_' + showId);
                if (card) card.click();
            }
            
            document.getElementById('searchInput').addEventListener('keyup', function() {
                let v = this.value.toLowerCase();
                document.querySelectorAll('.blog-card').forEach(c => {
                    c.parentElement.style.display = c.innerText.toLowerCase().includes(v) ? '' : 'none';
                });
            });
        });
    </script>
</body>
</html>