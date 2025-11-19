<?php
// Archivo: avisos.php (DISEÑO PREMIUM: BUSCADOR + ESTADOS)
session_start();
include 'conexion.php';
include 'funciones_permisos.php'; 

// Detectar si hay permiso de admin para mostrar botón de "Gestionar"
$es_admin_avisos = isset($_SESSION['usuario_id']) && (
    $_SESSION['usuario_rol'] === 'admin' || 
    tiene_permiso('acceso_avisos_lista', $pdo) || 
    tiene_permiso('acceso_avisos_admin', $pdo)
);

$id_usuario_actual = $_SESSION['usuario_id'] ?? 0;

// Helper para extraer la primera imagen del HTML
function get_first_image_url($html) {
    if (preg_match('/<img.+src=[\'"](?P<src>.+?)[\'"].*?>/i', $html, $image)) {
        return $image['src'];
    }
    return null; 
}

// 1. Cargar IDs de avisos LEÍDOS por este usuario para saber cuáles son nuevos
$leidos_ids = [];
if ($id_usuario_actual > 0) {
    try {
        $stmt_leidos = $pdo->prepare("SELECT id_aviso FROM avisos_lecturas WHERE id_usuario = :uid");
        $stmt_leidos->execute([':uid' => $id_usuario_actual]);
        $leidos_ids = $stmt_leidos->fetchAll(PDO::FETCH_COLUMN, 0);
    } catch (PDOException $e) {
        // Si la tabla no existe aún, no falla, solo no muestra leídos
    }
}

// 2. Obtener avisos activos
try {
    $sql = "SELECT a.*, u.nombre_completo AS creador 
            FROM avisos a
            JOIN usuarios u ON a.id_creador = u.id_usuario
            WHERE a.es_activo = 1
            ORDER BY a.fecha_publicacion DESC";
    $stmt = $pdo->query($sql);
    $avisos = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $avisos = [];
    error_log("Error cargando avisos: " . $e->getMessage());
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Avisos y Novedades</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">
    <style>
        body { background-color: #f0f2f5; font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; }
        
        /* Tarjeta */
        .news-card {
            border: none;
            border-radius: 16px;
            background: #fff;
            overflow: hidden;
            height: 100%;
            display: flex;
            flex-direction: column;
            transition: transform 0.3s cubic-bezier(0.175, 0.885, 0.32, 1.275), box-shadow 0.3s ease;
            box-shadow: 0 4px 6px rgba(0,0,0,0.05);
        }
        .news-card:hover {
            transform: translateY(-8px);
            box-shadow: 0 15px 30px rgba(0,0,0,0.1) !important;
        }
        
        /* Imagen */
        .card-img-wrapper {
            height: 220px;
            overflow: hidden;
            background-color: #e9ecef;
            position: relative;
        }
        .card-img-wrapper img {
            width: 100%;
            height: 100%;
            object-fit: cover;
            transition: transform 0.6s ease;
        }
        .news-card:hover .card-img-wrapper img { transform: scale(1.1); }
        
        /* Icono cuando no hay imagen */
        .card-icon-placeholder {
            height: 100%;
            display: flex;
            align-items: center;
            justify-content: center;
            color: #dee2e6;
            font-size: 5rem;
            background: linear-gradient(45deg, #f8f9fa, #e9ecef);
        }

        /* Badges (Etiquetas) */
        .status-badge {
            position: absolute;
            top: 15px;
            right: 15px;
            z-index: 10;
            padding: 6px 12px;
            border-radius: 20px;
            font-size: 0.75rem;
            font-weight: 800;
            letter-spacing: 0.5px;
            box-shadow: 0 4px 10px rgba(0,0,0,0.15);
            text-transform: uppercase;
        }
        .badge-new {
            background: #ff4757;
            color: white;
            animation: pulse-red 2s infinite;
        }
        .badge-read {
            background: rgba(255,255,255,0.9);
            color: #2ed573;
            backdrop-filter: blur(4px);
        }

        @keyframes pulse-red {
            0% { box-shadow: 0 0 0 0 rgba(255, 71, 87, 0.7); }
            70% { box-shadow: 0 0 0 10px rgba(255, 71, 87, 0); }
            100% { box-shadow: 0 0 0 0 rgba(255, 71, 87, 0); }
        }

        /* Contenido Tarjeta */
        .card-body { padding: 1.5rem; flex: 1; display: flex; flex-direction: column; }
        .news-meta { font-size: 0.8rem; color: #adb5bd; margin-bottom: 0.8rem; font-weight: 600; text-transform: uppercase; letter-spacing: 0.5px; }
        .news-title { font-size: 1.35rem; font-weight: 800; margin-bottom: 0.8rem; line-height: 1.3; color: #2d3436; }
        .news-excerpt { color: #636e72; font-size: 0.95rem; line-height: 1.6; display: -webkit-box; -webkit-line-clamp: 3; -webkit-box-orient: vertical; overflow: hidden; margin-bottom: 1.5rem; }
        
        /* Botón Leer más */
        .read-more-btn {
            margin-top: auto;
            color: #0984e3;
            font-weight: 700;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            transition: transform 0.2s;
        }
        .read-more-btn:hover { transform: translateX(5px); color: #0769b5; }

        /* Buscador */
        .search-container { position: relative; max-width: 400px; }
        .search-input {
            width: 100%;
            padding: 12px 20px 12px 45px;
            border-radius: 30px;
            border: 2px solid transparent;
            background: #fff;
            box-shadow: 0 4px 15px rgba(0,0,0,0.05);
            transition: all 0.3s;
            font-size: 1rem;
        }
        .search-input:focus { outline: none; border-color: #0984e3; box-shadow: 0 4px 20px rgba(9, 132, 227, 0.15); }
        .search-icon { position: absolute; left: 18px; top: 50%; transform: translateY(-50%); color: #b2bec3; pointer-events: none; }

        /* Modal Fullscreen Styles (Mismo de antes, ajustado) */
        .modal-article-container { max-width: 800px; margin: 0 auto; padding: 3rem 1.5rem; background: #fff; min-height: 100%; box-shadow: 0 0 50px rgba(0,0,0,0.05); }
        .article-title { font-size: 2.5rem; font-weight: 900; color: #2d3436; margin-bottom: 0.5rem; line-height: 1.2; }
        .article-content img { max-width: 100% !important; height: auto !important; border-radius: 12px; margin: 1.5rem 0; box-shadow: 0 8px 20px rgba(0,0,0,0.1); }
        
        /* Animación de entrada */
        .fade-in-up { animation: fadeInUp 0.6s ease backwards; }
        @keyframes fadeInUp { from { opacity: 0; transform: translateY(20px); } to { opacity: 1; transform: translateY(0); } }
    </style>
</head>
<body>
    <?php include 'navbar.php'; ?>

    <div class="container py-5">
        <div class="row align-items-end mb-5 g-3">
            <div class="col-md-6">
                <h1 class="fw-bold text-dark display-6 mb-1"><i class="fas fa-layer-group text-primary me-2"></i>Cartelera</h1>
                <p class="text-muted lead mb-0">Noticias y actualizaciones del equipo.</p>
            </div>
            <div class="col-md-6 d-flex flex-column flex-md-row justify-content-md-end align-items-md-center gap-3">
                
                <div class="search-container flex-grow-1 flex-md-grow-0" style="min-width: 250px;">
                    <i class="fas fa-search search-icon"></i>
                    <input type="text" id="searchInput" class="search-input" placeholder="Buscar aviso...">
                </div>

                <?php if ($es_admin_avisos): ?>
                    <div class="btn-group">
                        <a href="avisos_lista.php" class="btn btn-outline-dark rounded-pill px-3">
                            <i class="fas fa-cog"></i>
                        </a>
                        <a href="avisos_crear.php" class="btn btn-primary rounded-pill px-4 fw-bold">
                            <i class="fas fa-plus me-2"></i>Crear
                        </a>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <div class="row g-4" id="avisosGrid">
            <?php if (empty($avisos)): ?>
                <div class="col-12 text-center py-5">
                    <div class="text-muted opacity-25 mb-3"><i class="far fa-folder-open fa-5x"></i></div>
                    <h4 class="text-muted">No hay avisos publicados.</h4>
                </div>
            <?php else: ?>
                <?php 
                $delay = 0;
                foreach ($avisos as $aviso): 
                    $imagen_portada = get_first_image_url($aviso['contenido']);
                    $texto_plano = strip_tags($aviso['contenido']);
                    $fecha = date('d/m/Y', strtotime($aviso['fecha_publicacion']));
                    
                    // Determinar estado (Nuevo / Leído)
                    $es_leido = in_array($aviso['id_aviso'], $leidos_ids);
                    $badge_html = '';
                    if (!$es_leido) {
                        $badge_html = '<div class="status-badge badge-new"><i class="fas fa-star me-1"></i> Nuevo</div>';
                    } else {
                        $badge_html = '<div class="status-badge badge-read"><i class="fas fa-check me-1"></i> Leído</div>';
                    }
                    
                    // Incrementamos delay para animación escalonada
                    $delay += 0.1;
                ?>
                <div class="col-md-6 col-lg-4 aviso-item fade-in-up" style="animation-delay: <?php echo $delay; ?>s;">
                    <div class="news-card" role="button" data-bs-toggle="modal" data-bs-target="#modalFull<?php echo $aviso['id_aviso']; ?>">
                        <div class="card-img-wrapper">
                            <?php echo $badge_html; ?>
                            <?php if($imagen_portada): ?>
                                <img src="<?php echo $imagen_portada; ?>" alt="Portada">
                            <?php else: ?>
                                <div class="card-icon-placeholder">
                                    <i class="fas fa-newspaper"></i>
                                </div>
                            <?php endif; ?>
                        </div>
                        <div class="card-body">
                            <div class="news-meta">
                                <i class="far fa-calendar me-1"></i> <?php echo $fecha; ?> &bull; <?php echo htmlspecialchars($aviso['creador']); ?>
                            </div>
                            <h3 class="news-title searchable-title"><?php echo htmlspecialchars($aviso['titulo']); ?></h3>
                            <p class="news-excerpt searchable-text">
                                <?php echo mb_substr($texto_plano, 0, 110) . '...'; ?>
                            </p>
                            <span class="read-more-btn">Leer artículo <i class="fas fa-arrow-right ms-2"></i></span>
                        </div>
                    </div>
                </div>

                <div class="modal fade" id="modalFull<?php echo $aviso['id_aviso']; ?>" tabindex="-1" aria-hidden="true">
                    <div class="modal-dialog modal-fullscreen">
                        <div class="modal-content border-0">
                            <div class="modal-header border-bottom px-4 py-3 bg-white sticky-top shadow-sm">
                                <div class="d-flex align-items-center">
                                    <button type="button" class="btn btn-light rounded-circle me-3 shadow-sm" data-bs-dismiss="modal"><i class="fas fa-arrow-left"></i></button>
                                    <h5 class="modal-title text-truncate text-muted small text-uppercase fw-bold" style="max-width: 300px;">
                                        <?php echo htmlspecialchars($aviso['titulo']); ?>
                                    </h5>
                                </div>
                                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                            </div>

                            <div class="modal-body bg-light p-0">
                                <div class="modal-article-container">
                                    <div class="text-center mb-5">
                                        <?php if (!$es_leido): ?>
                                            <span class="badge bg-danger mb-3 rounded-pill px-3">NOVEDAD</span>
                                        <?php endif; ?>
                                        <h1 class="article-title"><?php echo htmlspecialchars($aviso['titulo']); ?></h1>
                                        <div class="text-muted mt-3">
                                            Por <strong><?php echo htmlspecialchars($aviso['creador']); ?></strong> el <?php echo date('d F Y', strtotime($aviso['fecha_publicacion'])); ?>
                                        </div>
                                    </div>
                                    
                                    <div class="article-content fs-5 text-dark">
                                        <?php echo $aviso['contenido']; ?>
                                    </div>

                                    <div class="mt-5 pt-5 text-center border-top">
                                        <button type="button" class="btn btn-secondary rounded-pill px-5 py-2" data-bs-dismiss="modal">Cerrar</button>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
        
        <div id="noResults" class="text-center py-5 d-none">
            <div class="text-muted opacity-50 mb-3"><i class="fas fa-search fa-3x"></i></div>
            <h5 class="text-muted">No se encontraron avisos con ese criterio.</h5>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // 1. BUSCADOR EN TIEMPO REAL
        document.getElementById('searchInput').addEventListener('keyup', function() {
            let filter = this.value.toLowerCase();
            let items = document.querySelectorAll('.aviso-item');
            let visibleCount = 0;

            items.forEach(function(item) {
                let title = item.querySelector('.searchable-title').textContent.toLowerCase();
                let text = item.querySelector('.searchable-text').textContent.toLowerCase();

                if (title.includes(filter) || text.includes(filter)) {
                    item.classList.remove('d-none');
                    // Reiniciar animación
                    item.classList.remove('fade-in-up');
                    void item.offsetWidth; // Trigger reflow
                    item.classList.add('fade-in-up');
                    visibleCount++;
                } else {
                    item.classList.add('d-none');
                }
            });

            // Mostrar mensaje si no hay resultados
            let noRes = document.getElementById('noResults');
            if(visibleCount === 0 && items.length > 0) noRes.classList.remove('d-none');
            else noRes.classList.add('d-none');
        });

        // 2. MARCAR COMO LEÍDO AL ABRIR
        const modals = document.querySelectorAll('.modal');
        modals.forEach(modal => {
            modal.addEventListener('shown.bs.modal', event => {
                const avisoId = modal.id.replace('modalFull', '');
                if(avisoId) {
                    // AJAX silencioso
                    fetch('marcar_aviso_leido.php', {
                        method: 'POST',
                        headers: {'Content-Type': 'application/x-www-form-urlencoded'},
                        body: 'id_aviso=' + avisoId
                    }).then(() => {
                        // Opcional: Cambiar badge visualmente sin recargar (para detallistas)
                        const card = document.querySelector(`[data-bs-target="#modalFull${avisoId}"]`);
                        if(card) {
                            const badge = card.querySelector('.badge-new');
                            if(badge) {
                                badge.className = 'status-badge badge-read';
                                badge.innerHTML = '<i class="fas fa-check me-1"></i> Leído';
                            }
                        }
                    });
                }
            })
        });
    </script>
</body>
</html>