<?php
// Archivo: inventario_lista.php (BOTONERA CORREGIDA + ORDENAR TABLERO)
session_start();
include 'conexion.php';
include 'funciones_permisos.php';

if (!isset($_SESSION['usuario_id']) || !tiene_permiso('acceso_inventario', $pdo)) {
    header("Location: dashboard.php"); exit();
}

$id_usuario = $_SESSION['usuario_id'];

// OBTENER CATEGORÍAS (Respetando tu orden y tus colores)
$sqlTipos = "SELECT tb.*, 
            (SELECT COUNT(*) FROM inventario_cargos c WHERE c.id_tipo_bien = tb.id_tipo_bien AND c.id_estado_fk != 0) as total_activos,
            COALESCE(uoc.orden, 999999) as orden_user
            FROM inventario_tipos_bien tb 
            LEFT JOIN inventario_orden_usuarios uoc ON tb.id_tipo_bien = uoc.id_tipo_bien AND uoc.id_usuario = :id_user
            ORDER BY orden_user ASC, tb.nombre ASC";

$stmt = $pdo->prepare($sqlTipos);
$stmt->execute([':id_user' => $id_usuario]);
$tipos_bienes = $stmt->fetchAll(PDO::FETCH_ASSOC);

// MOVIMIENTOS
$sqlMovs = "SELECT h.*, i.elemento, u.nombre_completo as usuario
            FROM historial_movimientos h
            LEFT JOIN inventario_cargos i ON h.id_bien = i.id_cargo
            LEFT JOIN usuarios u ON h.usuario_registro = u.id_usuario
            ORDER BY h.fecha_movimiento DESC LIMIT 10";
$ultimos_movimientos = $pdo->query($sqlMovs)->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Inventario | Logística</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <script src="https://cdnjs.cloudflare.com/ajax/libs/Sortable/1.15.0/Sortable.min.js"></script>
    <style>
        body { background-color: #f4f6f9; }
        .card-hover { transition: transform 0.2s, box-shadow 0.2s; cursor: pointer; border: none; }
        .card-hover:hover { transform: translateY(-5px); box-shadow: 0 .5rem 1rem rgba(0,0,0,.15)!important; }
        
        /* ESTILOS PARA MODO ORDENAR */
        .sortable-ghost { opacity: 0.4; background-color: #e9ecef; }
        
        .dynamic-card { touch-action: auto; } 

        .is-sorting .dynamic-card { 
            cursor: move; 
            touch-action: none; 
            border: 2px dashed #ccc;
            border-radius: 8px;
            background-color: rgba(255,255,255,0.5);
        }
        .is-sorting .dynamic-card:hover { border-color: #0d6efd; }

        .icon-box { width: 60px; height: 60px; display: flex; align-items: center; justify-content: center; border-radius: 50%; }
        
        /* --- ESTILOS DE COLORES --- */
        .border-left-primary { border-left: 4px solid #0d6efd!important; }
        .border-left-secondary { border-left: 4px solid #6c757d!important; }
        .border-left-success { border-left: 4px solid #198754!important; }
        .border-left-info    { border-left: 4px solid #0dcaf0!important; }
        .border-left-warning { border-left: 4px solid #ffc107!important; }
        .border-left-danger  { border-left: 4px solid #dc3545!important; }
        .border-left-dark    { border-left: 4px solid #212529!important; }
        .border-left-indigo  { border-left: 4px solid #6610f2!important; }
        .text-indigo { color: #6610f2 !important; }
        .bg-indigo { background-color: #6610f2 !important; }

        /* Colores Nuevos */
        .border-left-purple { border-left: 4px solid #6f42c1!important; } .text-purple { color: #6f42c1 !important; } .bg-purple { background-color: #6f42c1 !important; }
        .bg-purple.bg-opacity-10 { background-color: rgba(111, 66, 193, 0.1) !important; }

        .border-left-pink { border-left: 4px solid #d63384!important; } .text-pink { color: #d63384 !important; } .bg-pink { background-color: #d63384 !important; }
        .bg-pink.bg-opacity-10 { background-color: rgba(214, 51, 132, 0.1) !important; }

        .border-left-orange { border-left: 4px solid #fd7e14!important; } .text-orange { color: #fd7e14 !important; } .bg-orange { background-color: #fd7e14 !important; }
        .bg-orange.bg-opacity-10 { background-color: rgba(253, 126, 20, 0.1) !important; }

        .border-left-teal { border-left: 4px solid #20c997!important; } .text-teal { color: #20c997 !important; } .bg-teal { background-color: #20c997 !important; }
        .bg-teal.bg-opacity-10 { background-color: rgba(32, 201, 151, 0.1) !important; }

        .border-left-brown { border-left: 4px solid #795548!important; } .text-brown { color: #795548 !important; } .bg-brown { background-color: #795548 !important; }
        .bg-brown.bg-opacity-10 { background-color: rgba(121, 85, 72, 0.1) !important; }

        .border-left-blue-grey { border-left: 4px solid #607d8b!important; } .text-blue-grey { color: #607d8b !important; } .bg-blue-grey { background-color: #607d8b !important; }
        .bg-blue-grey.bg-opacity-10 { background-color: rgba(96, 125, 139, 0.1) !important; }

        .border-left-navy { border-left: 4px solid #001f3f!important; } .text-navy { color: #001f3f !important; } .bg-navy { background-color: #001f3f !important; }
        .bg-navy.bg-opacity-10 { background-color: rgba(0, 31, 63, 0.1) !important; }

        .border-left-olive { border-left: 4px solid #3d9970!important; } .text-olive { color: #3d9970 !important; } .bg-olive { background-color: #3d9970 !important; }
        .bg-olive.bg-opacity-10 { background-color: rgba(61, 153, 112, 0.1) !important; }

        .border-left-maroon { border-left: 4px solid #85144b!important; } .text-maroon { color: #85144b !important; } .bg-maroon { background-color: #85144b !important; }
        .bg-maroon.bg-opacity-10 { background-color: rgba(133, 20, 75, 0.1) !important; }

        .border-left-fuchsia { border-left: 4px solid #f012be!important; } .text-fuchsia { color: #f012be !important; } .bg-fuchsia { background-color: #f012be !important; }
        .bg-fuchsia.bg-opacity-10 { background-color: rgba(240, 18, 190, 0.1) !important; }

        .border-left-royal { border-left: 4px solid #4169e1!important; } .text-royal { color: #4169e1 !important; } .bg-royal { background-color: #4169e1 !important; }
        .bg-royal.bg-opacity-10 { background-color: rgba(65, 105, 225, 0.1) !important; }

        .border-left-crimson { border-left: 4px solid #dc143c!important; } .text-crimson { color: #dc143c !important; } .bg-crimson { background-color: #dc143c !important; }
        .bg-crimson.bg-opacity-10 { background-color: rgba(220, 20, 60, 0.1) !important; }

        .border-left-chocolate { border-left: 4px solid #d2691e!important; } .text-chocolate { color: #d2691e !important; } .bg-chocolate { background-color: #d2691e !important; }
        .bg-chocolate.bg-opacity-10 { background-color: rgba(210, 105, 30, 0.1) !important; }

        .border-left-slate { border-left: 4px solid #708090!important; } .text-slate { color: #708090 !important; } .bg-slate { background-color: #708090 !important; }
        .bg-slate.bg-opacity-10 { background-color: rgba(112, 128, 144, 0.1) !important; }
        
        .bg-indigo.bg-opacity-10 { background-color: rgba(102, 16, 242, 0.1) !important; }
    </style>
</head>
<body>
    <?php include 'navbar.php'; ?>

    <div class="container mt-4 mb-5">
        
        <div class="d-flex flex-wrap justify-content-between align-items-center mb-4 gap-2">
            <h2 class="text-dark fw-bold m-0"><i class="fas fa-boxes me-2"></i>Inventario</h2>
            
            <div class="d-flex flex-wrap gap-2 align-items-center">
                
                <button id="btnToggleSort" class="btn btn-outline-dark shadow-sm">
                    <i class="fas fa-arrows-alt me-1"></i> Ordenar Tablero
                </button>

                <div class="btn-group shadow-sm">
                    <a href="inventario_config.php" class="btn btn-outline-secondary" title="Configuración"><i class="fas fa-cog"></i></a>
                    <a href="inventario_reporte_pdf.php" target="_blank" class="btn btn-outline-danger" title="Reporte PDF"><i class="fas fa-file-pdf"></i> Reporte</a>
                    <a href="inventario_movimientos.php" class="btn btn-outline-primary" title="Historial de Transferencias"><i class="fas fa-exchange-alt"></i> Historial de Transferencias</a>
                </div>
                
                <a href="inventario_mantenimiento.php" class="btn btn-outline-warning text-dark shadow-sm"><i class="fas fa-tools me-1"></i> Servicio Técnico</a>
                <a href="inventario_nuevo.php" class="btn btn-success fw-bold px-4 shadow-sm"><i class="fas fa-plus me-2"></i>NUEVO</a>
            </div>
        </div>

        <div class="row g-4 mb-5" id="sortableCards">
            <?php 
            $coloresDefault = ['primary', 'success', 'info', 'warning', 'danger', 'secondary'];
            $i = 0;

            foreach($tipos_bienes as $tipo): 
                $color = (!empty($tipo['color'])) ? $tipo['color'] : $coloresDefault[$i % count($coloresDefault)];
                $icono = $tipo['icono'] ? $tipo['icono'] : 'fas fa-box';
                $textColor = ($color == 'indigo' || $color == 'dark' || $color == 'navy' || $color == 'maroon') ? $color : $color; 
                $i++;
            ?>
            <div class="col-xl-3 col-md-6 dynamic-card" data-id="<?php echo $tipo['id_tipo_bien']; ?>">
                <div class="card card-hover shadow h-100 py-2 border-left-<?php echo $color; ?>" 
                     onclick="if(!document.getElementById('sortableCards').classList.contains('is-sorting')) window.location.href='inventario_ver_categoria.php?id=<?php echo $tipo['id_tipo_bien']; ?>'">
                    <div class="card-body">
                        <div class="row align-items-center">
                            <div class="col">
                                <div class="text-xs fw-bold text-<?php echo $textColor; ?> text-uppercase mb-1"><?php echo htmlspecialchars($tipo['nombre']); ?></div>
                                <div class="h5 mb-0 fw-bold text-dark"><?php echo $tipo['total_activos']; ?> Activos</div>
                            </div>
                            <div class="col-auto">
                                <div class="icon-box bg-<?php echo $color; ?> bg-opacity-10 text-<?php echo $textColor; ?>">
                                    <i class="<?php echo $icono; ?> fa-2x"></i>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <?php endforeach; ?>
            
            <div class="col-xl-3 col-md-6 static-card">
                <div class="card card-hover shadow h-100 py-2 border-left-secondary" 
                     onclick="window.location.href='inventario_ver_categoria.php?id=todas'">
                    <div class="card-body">
                        <div class="row align-items-center">
                            <div class="col">
                                <div class="text-xs fw-bold text-secondary text-uppercase mb-1">LISTADO COMPLETO</div>
                                <div class="h5 mb-0 fw-bold text-dark">Ver Todo</div>
                            </div>
                            <div class="col-auto"><i class="fas fa-list fa-2x text-gray-300"></i></div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="card shadow mb-4">
            <div class="card-header py-3 bg-white d-flex justify-content-between">
                <h5 class="m-0 fw-bold text-primary"><i class="fas fa-shipping-fast me-2"></i>Últimos Movimientos</h5>
                <a href="inventario_movimientos.php" class="btn btn-sm btn-outline-primary">Ver Todos</a>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-striped align-middle mb-0">
                        <thead class="table-light">
                            <tr><th class="ps-4">Fecha</th><th>Bien</th><th>Acción</th><th>Usuario</th></tr>
                        </thead>
                        <tbody>
                            <?php foreach($ultimos_movimientos as $mov): ?>
                                <tr>
                                    <td class="ps-4"><?php echo date('d/m/Y H:i', strtotime($mov['fecha_movimiento'])); ?></td>
                                    <td><span class="fw-bold text-dark"><?php echo htmlspecialchars($mov['elemento']); ?></span></td>
                                    <td><span class="badge bg-secondary"><?php echo htmlspecialchars($mov['tipo_movimiento']); ?></span></td>
                                    <td><?php echo htmlspecialchars($mov['usuario']); ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
    
    <div class="modal fade" id="modalExito" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content border-success">
                <div class="modal-header bg-success text-white">
                    <h5 class="modal-title">¡Operación Exitosa!</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body text-center py-4">
                    <p class="mb-0 fw-bold">Los cambios se han guardado correctamente.</p>
                </div>
                <div class="modal-footer justify-content-center">
                    <button type="button" class="btn btn-success px-4" data-bs-dismiss="modal">Aceptar</button>
                </div>
            </div>
        </div>
    </div>

    <?php include 'footer.php'; ?>
    <script src="https://code.jquery.com/jquery-3.7.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        document.addEventListener("DOMContentLoaded", function() {
            var el = document.getElementById('sortableCards');
            
            // Inicializar Sortable DESACTIVADO por defecto
            var sortable = Sortable.create(el, {
                animation: 150,
                draggable: ".dynamic-card",
                filter: ".static-card",
                ghostClass: 'sortable-ghost',
                disabled: true, // IMPORTANTE: Empieza desactivado
                onEnd: function (evt) {
                    var orden = [];
                    document.querySelectorAll('.dynamic-card').forEach(function(card) {
                        orden.push(card.getAttribute('data-id'));
                    });
                    $.post('ajax_ordenar_tarjetas.php', {orden: orden}, function(res) { console.log("Orden guardado"); });
                }
            });

            // Lógica del Botón Toggle
            $('#btnToggleSort').click(function() {
                var isDisabled = sortable.option("disabled"); // Estado actual
                
                if (isDisabled) {
                    // ACTIVAR MODO ORDENAR
                    sortable.option("disabled", false);
                    $(this).html('<i class="fas fa-check me-1"></i> Listo').removeClass('btn-outline-dark').addClass('btn-primary');
                    $('#sortableCards').addClass('is-sorting');
                } else {
                    // DESACTIVAR MODO ORDENAR (VOLVER A NORMAL)
                    sortable.option("disabled", true);
                    $(this).html('<i class="fas fa-arrows-alt me-1"></i> Ordenar Tablero').addClass('btn-outline-dark').removeClass('btn-primary');
                    $('#sortableCards').removeClass('is-sorting');
                }
            });

            const urlParams = new URLSearchParams(window.location.search);
            if (urlParams.has('msg') && (urlParams.get('msg') === 'guardado_ok' || urlParams.get('msg') === 'importado_ok' || urlParams.get('msg') === 'estructura_ok')) {
                new bootstrap.Modal(document.getElementById('modalExito')).show();
                window.history.replaceState({}, document.title, window.location.pathname);
            }
        });
    </script>
</body>
</html>