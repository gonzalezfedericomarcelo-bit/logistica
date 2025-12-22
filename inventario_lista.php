<?php
// Archivo: inventario_lista.php (CON BOTONES DE PDF Y VISUALIZADOR)
session_start();
include 'conexion.php';
include 'funciones_permisos.php';

if (!isset($_SESSION['usuario_id']) || !tiene_permiso('acceso_inventario', $pdo)) {
    header("Location: dashboard.php"); exit();
}

$id_usuario = $_SESSION['usuario_id'];

// Función Helper para buscar el Acta Firmada más reciente de un bien
function buscar_acta_reciente($id_bien) {
    $patron = __DIR__ . '/pdfs_publicos/inventario_pdf/Acta_Transferencia_' . $id_bien . '_*.pdf';
    $archivos = glob($patron);
    if ($archivos && count($archivos) > 0) {
        // Ordenar por fecha de modificación (más nuevo primero)
        usort($archivos, function($a, $b) { return filemtime($b) - filemtime($a); });
        return 'pdfs_publicos/inventario_pdf/' . basename($archivos[0]);
    }
    return false;
}

// OBTENER CATEGORÍAS
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
$sqlMovs = "SELECT h.*, i.elemento, u.nombre_completo as usuario, i.id_cargo
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
        .sortable-ghost { opacity: 0.4; background-color: #e9ecef; }
        .dynamic-card { touch-action: auto; } 
        .is-sorting .dynamic-card { cursor: move; touch-action: none; border: 2px dashed #ccc; border-radius: 8px; background-color: rgba(255,255,255,0.5); }
        .is-sorting .dynamic-card:hover { border-color: #0d6efd; }
        .icon-box { width: 60px; height: 60px; display: flex; align-items: center; justify-content: center; border-radius: 50%; }
        
        /* Colores */
        .border-left-primary { border-left: 4px solid #0d6efd!important; }
        .border-left-success { border-left: 4px solid #198754!important; }
        .border-left-info { border-left: 4px solid #0dcaf0!important; }
        .border-left-warning { border-left: 4px solid #ffc107!important; }
        .border-left-danger { border-left: 4px solid #dc3545!important; }
        .border-left-secondary { border-left: 4px solid #6c757d!important; }
        /* (Tus otros estilos de colores se mantienen igual...) */
    </style>
</head>
<body>
    <?php include 'navbar.php'; ?>

    <div class="container mt-4 mb-5">
        
        <div class="d-flex flex-wrap justify-content-between align-items-center mb-4 gap-2">
            <h2 class="text-dark fw-bold m-0"><i class="fas fa-boxes me-2"></i>Inventario</h2>
            <div class="d-flex flex-wrap gap-2 align-items-center">
                <button id="btnToggleSort" class="btn btn-outline-dark shadow-sm"><i class="fas fa-arrows-alt me-1"></i> Ordenar Tablero</button>
                <div class="btn-group shadow-sm">
                    <a href="inventario_config.php" class="btn btn-outline-secondary" title="Configuración"><i class="fas fa-cog"></i></a>
                    <a href="inventario_reporte_pdf.php" target="_blank" class="btn btn-outline-danger" title="Reporte PDF"><i class="fas fa-file-pdf"></i> Reporte</a>
                    <a href="inventario_movimientos.php" class="btn btn-outline-primary" title="Historial"><i class="fas fa-exchange-alt"></i> Historial</a>
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
                <div class="card card-hover shadow h-100 py-2 border-left-secondary" onclick="window.location.href='inventario_ver_categoria.php?id=todas'">
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
            <div class="card-header py-3 bg-white d-flex justify-content-between align-items-center">
                <h5 class="m-0 fw-bold text-primary"><i class="fas fa-shipping-fast me-2"></i>Últimos Movimientos</h5>
                <a href="inventario_movimientos.php" class="btn btn-sm btn-outline-primary">Ver Historial Completo</a>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-striped align-middle mb-0 table-hover">
                        <thead class="table-light">
                            <tr>
                                <th class="ps-4">Fecha</th>
                                <th>Bien</th>
                                <th>Acción</th>
                                <th>Usuario</th>
                                <th class="text-end pe-4">Acciones</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach($ultimos_movimientos as $mov): 
                                $es_transferencia = (stripos($mov['tipo_movimiento'], 'Transferencia') !== false);
                                $ruta_acta = $es_transferencia ? buscar_acta_reciente($mov['id_cargo']) : false;
                            ?>
                                <tr>
                                    <td class="ps-4"><?php echo date('d/m/Y H:i', strtotime($mov['fecha_movimiento'])); ?></td>
                                    <td><span class="fw-bold text-dark"><?php echo htmlspecialchars($mov['elemento']); ?></span></td>
                                    <td><span class="badge bg-secondary"><?php echo htmlspecialchars($mov['tipo_movimiento']); ?></span></td>
                                    <td><?php echo htmlspecialchars($mov['usuario']); ?></td>
                                    <td class="text-end pe-4">
                                        <div class="btn-group">
                                            <button type="button" class="btn btn-sm btn-info text-white" title="Ver Detalles"
                                                    onclick="verDetalleMovimiento(<?php echo htmlspecialchars(json_encode($mov)); ?>)">
                                                <i class="fas fa-eye"></i>
                                            </button>

                                            <a href="inventario_movimientos_pdf.php?id=<?php echo $mov['id_movimiento']; ?>" target="_blank" class="btn btn-sm btn-danger" title="Constancia de Movimiento">
                                                <i class="fas fa-file-pdf"></i>
                                            </a>

                                            <?php if($ruta_acta): ?>
                                                <a href="<?php echo $ruta_acta; ?>" target="_blank" class="btn btn-sm btn-primary" title="Acta de Transferencia Firmada">
                                                    <i class="fas fa-file-contract"></i>
                                                </a>
                                            <?php else: ?>
                                                <button class="btn btn-sm btn-secondary" disabled title="No hay acta firmada"><i class="fas fa-file-contract"></i></button>
                                            <?php endif; ?>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
    
    <div class="modal fade" id="modalDetalleMov" tabindex="-1">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header bg-primary text-white">
                    <h5 class="modal-title"><i class="fas fa-info-circle me-2"></i>Detalle del Movimiento</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <ul class="list-group list-group-flush">
                        <li class="list-group-item"><strong>Fecha:</strong> <span id="det_fecha"></span></li>
                        <li class="list-group-item"><strong>Bien:</strong> <span id="det_bien" class="fw-bold"></span></li>
                        <li class="list-group-item"><strong>Acción:</strong> <span id="det_accion" class="badge bg-secondary"></span></li>
                        <li class="list-group-item"><strong>Usuario:</strong> <span id="det_usuario"></span></li>
                        <li class="list-group-item bg-light">
                            <strong>Detalles / Observación:</strong><br>
                            <p class="mb-0 mt-1 text-muted" id="det_obs"></p>
                        </li>
                        <li class="list-group-item">
                            <div class="row">
                                <div class="col-6 border-end">
                                    <small class="text-muted d-block">Origen:</small>
                                    <span id="det_origen" class="fw-bold text-danger"></span>
                                </div>
                                <div class="col-6">
                                    <small class="text-muted d-block">Destino:</small>
                                    <span id="det_destino" class="fw-bold text-success"></span>
                                </div>
                            </div>
                        </li>
                    </ul>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cerrar</button>
                </div>
            </div>
        </div>
    </div>

    <div class="modal fade" id="modalExito" tabindex="-1">
        <div class="modal-dialog"><div class="modal-content border-success"><div class="modal-header bg-success text-white"><h5 class="modal-title">¡Operación Exitosa!</h5><button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button></div><div class="modal-body text-center py-4"><p class="mb-0 fw-bold">Los cambios se han guardado correctamente.</p></div><div class="modal-footer justify-content-center"><button type="button" class="btn btn-success px-4" data-bs-dismiss="modal">Aceptar</button></div></div></div>
    </div>

    <?php include 'footer.php'; ?>
    <script src="https://code.jquery.com/jquery-3.7.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        document.addEventListener("DOMContentLoaded", function() {
            var el = document.getElementById('sortableCards');
            var sortable = Sortable.create(el, {
                animation: 150, draggable: ".dynamic-card", filter: ".static-card", ghostClass: 'sortable-ghost', disabled: true,
                onEnd: function (evt) {
                    var orden = [];
                    document.querySelectorAll('.dynamic-card').forEach(function(card) { orden.push(card.getAttribute('data-id')); });
                    $.post('ajax_ordenar_tarjetas.php', {orden: orden});
                }
            });
            $('#btnToggleSort').click(function() {
                var isDisabled = sortable.option("disabled");
                if (isDisabled) {
                    sortable.option("disabled", false);
                    $(this).html('<i class="fas fa-check me-1"></i> Listo').removeClass('btn-outline-dark').addClass('btn-primary');
                    $('#sortableCards').addClass('is-sorting');
                } else {
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

        // FUNCION PARA VER DETALLE
        function verDetalleMovimiento(mov) {
            document.getElementById('det_fecha').innerText = new Date(mov.fecha_movimiento).toLocaleString();
            document.getElementById('det_bien').innerText = mov.elemento;
            document.getElementById('det_accion').innerText = mov.tipo_movimiento;
            document.getElementById('det_usuario').innerText = mov.usuario;
            document.getElementById('det_obs').innerText = mov.observacion_movimiento || '-';
            document.getElementById('det_origen').innerText = mov.ubicacion_anterior || '-';
            document.getElementById('det_destino').innerText = mov.ubicacion_nueva || '-';
            
            new bootstrap.Modal(document.getElementById('modalDetalleMov')).show();
        }
    </script>
</body>
</html>