<?php
// Archivo: inventario_movimientos.php
session_start();
include 'conexion.php';
include 'funciones_permisos.php';

if (!isset($_SESSION['usuario_id']) || !tiene_permiso('inventario_historial', $pdo)) {
    header("Location: inventario_lista.php"); exit();
}

$puede_editar = tiene_permiso('inventario_historial_editar', $pdo);
$puede_eliminar = tiene_permiso('inventario_historial_eliminar', $pdo);

if (isset($_GET['delete']) && $puede_eliminar) {
    $stmtDel = $pdo->prepare("DELETE FROM historial_movimientos WHERE id_movimiento = ?");
    $stmtDel->execute([$_GET['delete']]);
    header("Location: inventario_movimientos.php?msg=eliminado"); exit();
}

if (isset($_GET['cancelar_pendiente']) && $puede_eliminar) {
    $stmtCan = $pdo->prepare("DELETE FROM inventario_transferencias_pendientes WHERE id_token = ? AND estado = 'pendiente'");
    $stmtCan->execute([$_GET['cancelar_pendiente']]);
    header("Location: inventario_movimientos.php?msg=cancelado"); exit();
}

// --- FILTROS ---
$filtro_estado = $_GET['estado'] ?? 'todos';
$filtro_q = $_GET['q'] ?? '';
$whereHist = "1=1";
$wherePend = "t.estado = 'pendiente'"; 
$paramsHist = [];
$paramsPend = [];

if ($filtro_q) {
    $term = "%$filtro_q%";
    $whereHist .= " AND (i.elemento LIKE ? OR u.nombre_completo LIKE ? OR h.observacion_movimiento LIKE ?)";
    $paramsHist = [$term, $term, $term];
    $wherePend .= " AND (i.elemento LIKE ? OR u.nombre_completo LIKE ? OR t.observaciones LIKE ?)";
    $paramsPend = [$term, $term, $term];
}

// 1. HISTORIAL
$historial = [];
if ($filtro_estado !== 'pendientes') {
    $sqlH = "SELECT h.id_movimiento as id_unico, 'finalizado' as tipo_dato,
                    h.fecha_movimiento as fecha, 
                    h.tipo_movimiento as accion, 
                    h.observacion_movimiento as detalle, 
                    h.ubicacion_anterior, h.ubicacion_nueva, h.id_bien,
                    i.elemento, i.servicio_ubicacion, 
                    u.nombre_completo as usuario,
                    NULL as token_hash, NULL as firma_patrimonial, NULL as firma_responsable
             FROM historial_movimientos h 
             LEFT JOIN inventario_cargos i ON h.id_bien = i.id_cargo 
             LEFT JOIN usuarios u ON h.usuario_registro = u.id_usuario 
             WHERE $whereHist
             ORDER BY h.fecha_movimiento DESC LIMIT 300";
    $stmtH = $pdo->prepare($sqlH);
    $stmtH->execute($paramsHist);
    $historial = $stmtH->fetchAll(PDO::FETCH_ASSOC);
}

// 2. PENDIENTES
$pendientes = [];
if ($filtro_estado !== 'finalizados') {
    // Traemos también firma_nuevo_responsable_path para saber si el externo firmó
    $sqlP = "SELECT t.id_token as id_unico, 'pendiente' as tipo_dato,
                    t.fecha_creacion as fecha, 
                    'Solicitud Transferencia' as accion, 
                    t.observaciones as detalle, 
                    i.destino_principal as ubicacion_anterior, 
                    t.nuevo_destino_nombre as ubicacion_nueva,
                    t.id_bien,
                    i.elemento, i.servicio_ubicacion, 
                    u.nombre_completo as usuario,
                    t.token_hash, 
                    t.firma_patrimonial_path as firma_patrimonial,
                    t.firma_nuevo_responsable_path as firma_responsable
             FROM inventario_transferencias_pendientes t
             LEFT JOIN inventario_cargos i ON t.id_bien = i.id_cargo 
             LEFT JOIN usuarios u ON t.creado_por = u.id_usuario 
             WHERE $wherePend
             ORDER BY t.fecha_creacion DESC";
    $stmtP = $pdo->prepare($sqlP);
    $stmtP->execute($paramsPend);
    $pendientes = $stmtP->fetchAll(PDO::FETCH_ASSOC);
}

$todos = array_merge($pendientes, $historial);

$protocol = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on') ? "https://" : "http://";
$base_url = $protocol . $_SERVER['HTTP_HOST'] . dirname($_SERVER['PHP_SELF']) . "/";

function buscar_acta_reciente($id_bien) {
    $patron = __DIR__ . '/pdfs_publicos/inventario_pdf/Acta_Transferencia_' . $id_bien . '_*.pdf';
    $archivos = glob($patron);
    if ($archivos && count($archivos) > 0) {
        usort($archivos, function($a, $b) { return filemtime($b) - filemtime($a); });
        return 'pdfs_publicos/inventario_pdf/' . basename($archivos[0]);
    }
    return false;
}

// Helper para limpiar nombres de ubicación (si vienen IDs)
function limpiar_ubicacion($texto) {
    if (is_numeric($texto)) return "Destino ID: " . $texto; // O podrías poner "Sin nombre"
    return $texto;
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Control de Movimientos</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="https://cdn.datatables.net/1.13.4/css/dataTables.bootstrap5.min.css">
    <style>
        /* ESTÉTICA MEJORADA */
        .fila-pendiente { background-color: #fff9e6 !important; border-left: 4px solid #ffc107; }
        .fila-finalizada { border-left: 4px solid #198754; }
        
        .badge-soft {
            padding: 6px 10px;
            border-radius: 6px;
            font-weight: 600;
            font-size: 0.75rem;
            letter-spacing: 0.5px;
        }
        .badge-soft-success { background-color: #d1e7dd; color: #0f5132; }
        .badge-soft-warning { background-color: #fff3cd; color: #664d03; }
        .badge-soft-danger  { background-color: #f8d7da; color: #842029; }
        .badge-soft-info    { background-color: #cff4fc; color: #055160; }

        /* Iconos de estado en columna */
        .status-icon-ok { color: #198754; font-size: 1.1rem; }
        .status-icon-wait { color: #ffc107; font-size: 1.1rem; }
        
        /* Detalles de pasos */
        .paso-item { font-size: 0.8rem; margin-bottom: 2px; display: flex; align-items: center; gap: 5px; }
    </style>
</head>
<body>
    <?php include 'navbar.php'; ?>
    
    <div class="container-fluid px-4 mt-4 mb-5">
        
        <div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-2">
            <div>
                <h3 class="mb-0 fw-bold text-dark"><i class="fas fa-tasks me-2 text-primary"></i>Centro de Movimientos</h3>
                <small class="text-muted">Gestión integral de transferencias y asignaciones</small>
            </div>
            
            <form class="d-flex gap-2" method="GET">
                <select name="estado" class="form-select w-auto shadow-sm border-0" onchange="this.form.submit()">
                    <option value="todos" <?php echo $filtro_estado=='todos'?'selected':''; ?>>Ver Todo</option>
                    <option value="pendientes" <?php echo $filtro_estado=='pendientes'?'selected':''; ?>>⏳ En Proceso</option>
                    <option value="finalizados" <?php echo $filtro_estado=='finalizados'?'selected':''; ?>>✅ Completados</option>
                </select>
            </form>
        </div>

        <?php if(isset($_GET['msg']) && $_GET['msg']=='cancelado'): ?>
            <div class="alert alert-warning shadow-sm border-0"><i class="fas fa-info-circle me-2"></i>La solicitud ha sido cancelada correctamente.</div>
        <?php endif; ?>

        <div class="card shadow-sm border-0 rounded-3 overflow-hidden">
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table id="tablaUnificada" class="table table-hover align-middle w-100 mb-0">
                        <thead class="bg-light text-uppercase small fw-bold text-secondary">
                            <tr>
                                <th style="width: 180px;">Estado / Progreso</th>
                                <th>Fecha</th>
                                <th>Bien Patrimonial</th>
                                <th>Origen <i class="fas fa-arrow-right mx-1 text-muted"></i> Destino</th>
                                <th>Gestión</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach($todos as $fila): 
                                $es_pendiente = ($fila['tipo_dato'] === 'pendiente');
                                $clase_fila = $es_pendiente ? 'fila-pendiente' : 'fila-finalizada';
                                $link_firma = $es_pendiente ? $base_url . "transferencia_externa.php?token=" . $fila['token_hash'] : '';
                                
                                $ruta_acta = (!$es_pendiente && stripos($fila['accion'], 'Transferencia') !== false) 
                                             ? buscar_acta_reciente($fila['id_bien']) : false;
                                             
                                // Limpieza visual de ubicación (evitar números sueltos)
                                $ubi_ant = limpiar_ubicacion($fila['ubicacion_anterior']);
                                $ubi_nue = limpiar_ubicacion($fila['ubicacion_nueva']);
                            ?>
                            <tr class="<?php echo $clase_fila; ?>">
                                
                                <td>
                                    <?php if($es_pendiente): ?>
                                        <div class="mb-2"><span class="badge-soft badge-soft-warning">EN PROCESO</span></div>
                                        
                                        <div class="d-flex flex-column gap-1">
                                            <div class="paso-item">
                                                <?php if($fila['firma_patrimonial_path']): ?>
                                                    <i class="fas fa-check-circle status-icon-ok"></i> <span class="text-success small fw-bold">Patrimonio</span>
                                                <?php else: ?>
                                                    <i class="fas fa-clock status-icon-wait"></i> <span class="text-muted small">Patrimonio</span>
                                                <?php endif; ?>
                                            </div>
                                            <div class="paso-item">
                                                <?php if(!empty($fila['firma_responsable'])): // Este campo no siempre está en pendiente, depende de si ya firmó pero falta patr ?>
                                                    <i class="fas fa-check-circle status-icon-ok"></i> <span class="text-success small fw-bold">Receptor</span>
                                                <?php else: ?>
                                                    <i class="fas fa-clock status-icon-wait"></i> <span class="text-muted small">Receptor</span>
                                                <?php endif; ?>
                                            </div>
                                        </div>

                                    <?php else: ?>
                                        <span class="badge-soft badge-soft-success"><i class="fas fa-check-double me-1"></i> FINALIZADO</span>
                                    <?php endif; ?>
                                </td>

                                <td class="small text-muted fw-bold">
                                    <?php echo date('d/m/y', strtotime($fila['fecha'])); ?><br>
                                    <span class="fw-normal"><?php echo date('H:i', strtotime($fila['fecha'])); ?></span>
                                </td>
                                
                                <td>
                                    <span class="fw-bold text-dark d-block text-truncate" style="max-width: 250px;"><?php echo htmlspecialchars($fila['elemento']); ?></span>
                                    <small class="text-muted"><i class="fas fa-user-circle me-1"></i> <?php echo htmlspecialchars($fila['usuario'] ?? 'Sistema'); ?></small>
                                </td>
                                
                                <td>
                                    <div class="d-flex flex-column small">
                                        <div class="text-danger mb-1"><i class="fas fa-map-marker-alt me-2" style="width:15px;"></i> <?php echo htmlspecialchars($ubi_ant); ?></div>
                                        <div class="text-success"><i class="fas fa-map-marker me-2" style="width:15px;"></i> <?php echo htmlspecialchars($ubi_nue); ?></div>
                                    </div>
                                </td>

                                <td class="text-end">
                                    <div class="btn-group shadow-sm">
                                        <button class="btn btn-sm btn-light border" onclick="verDetalle(<?php echo htmlspecialchars(json_encode($fila)); ?>)" title="Ver Detalles Completos">
                                            <i class="fas fa-eye text-primary"></i>
                                        </button>

                                        <?php if($es_pendiente): ?>
                                            <button class="btn btn-sm btn-light border" onclick="copiarLink('<?php echo $link_firma; ?>')" title="Copiar Enlace de Firma">
                                                <i class="fas fa-link text-dark"></i>
                                            </button>
                                            
                                            <?php if($puede_eliminar): ?>
                                                <a href="inventario_movimientos.php?cancelar_pendiente=<?php echo $fila['id_unico']; ?>" class="btn btn-sm btn-light border" onclick="return confirm('¿Cancelar y eliminar esta solicitud?')" title="Cancelar">
                                                    <i class="fas fa-trash-alt text-danger"></i>
                                                </a>
                                            <?php endif; ?>

                                        <?php else: ?>
                                            <a href="inventario_movimientos_pdf.php?id=<?php echo $fila['id_unico']; ?>" target="_blank" class="btn btn-sm btn-light border" title="Constancia Anexo 4">
                                                <i class="fas fa-file-pdf text-danger"></i>
                                            </a>

                                            <?php if($ruta_acta): ?>
                                                <a href="<?php echo $ruta_acta; ?>" target="_blank" class="btn btn-sm btn-light border" title="Acta Firmada">
                                                    <i class="fas fa-file-signature text-success"></i>
                                                </a>
                                            <?php endif; ?>
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

    <div class="modal fade" id="modalDetalle" tabindex="-1">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content border-0 shadow">
                <div class="modal-header bg-primary text-white">
                    <h5 class="modal-title fw-bold">Detalle de Operación</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body p-4">
                    
                    <div class="d-flex align-items-center mb-3">
                        <div id="m_estado_badge"></div>
                        <span class="ms-auto text-muted small" id="m_fecha"></span>
                    </div>

                    <h5 class="fw-bold text-dark mb-3" id="m_bien"></h5>

                    <div class="p-3 bg-light rounded border mb-3">
                        <div class="row">
                            <div class="col-6 border-end">
                                <small class="text-uppercase text-muted fw-bold" style="font-size:0.7rem;">Origen</small>
                                <div class="text-danger fw-bold" id="m_origen"></div>
                            </div>
                            <div class="col-6 ps-3">
                                <small class="text-uppercase text-muted fw-bold" style="font-size:0.7rem;">Destino</small>
                                <div class="text-success fw-bold" id="m_destino"></div>
                            </div>
                        </div>
                    </div>

                    <div class="mb-3">
                        <small class="fw-bold text-secondary">Observaciones / Motivo:</small>
                        <p class="mb-0 text-muted fst-italic" id="m_obs"></p>
                    </div>

                    <div id="divLink" class="mt-4 pt-3 border-top" style="display:none;">
                        <label class="fw-bold text-primary small mb-1">Enlace para completar firmas:</label>
                        <div class="input-group">
                            <input type="text" id="inputModalLink" class="form-control form-control-sm bg-white" readonly>
                            <button class="btn btn-dark btn-sm" onclick="copiarLinkModal()"><i class="fas fa-copy"></i></button>
                        </div>
                        <div class="mt-2 small text-muted">
                            <i class="fas fa-info-circle me-1"></i> Envíe este enlace al responsable saliente.
                        </div>
                    </div>

                </div>
                <div class="modal-footer bg-light">
                    <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Cerrar</button>
                </div>
            </div>
        </div>
    </div>

    <script src="https://code.jquery.com/jquery-3.7.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.datatables.net/1.13.4/js/jquery.dataTables.min.js"></script>
    <script src="https://cdn.datatables.net/1.13.4/js/dataTables.bootstrap5.min.js"></script>
    <script>
        $(document).ready(function(){ 
            $('#tablaUnificada').DataTable({ 
                order: [], 
                language: { url: "//cdn.datatables.net/plug-ins/1.13.4/i18n/es-ES.json" },
                pageLength: 25
            });
        });

        function verDetalle(data) {
            document.getElementById('m_fecha').innerText = data.fecha;
            document.getElementById('m_bien').innerText = data.elemento;
            document.getElementById('m_origen').innerText = data.ubicacion_anterior || '-';
            document.getElementById('m_destino').innerText = data.ubicacion_nueva || '-';
            document.getElementById('m_obs').innerText = data.detalle || 'Sin observaciones registradas.';
            
            let divLink = document.getElementById('divLink');
            let badgeDiv = document.getElementById('m_estado_badge');
            
            if(data.tipo_dato === 'pendiente') {
                badgeDiv.innerHTML = '<span class="badge bg-warning text-dark px-3 py-2">PENDIENTE DE FIRMAS</span>';
                divLink.style.display = 'block';
                document.getElementById('inputModalLink').value = "<?php echo $base_url; ?>transferencia_externa.php?token=" + data.token_hash;
            } else {
                badgeDiv.innerHTML = '<span class="badge bg-success px-3 py-2">MOVIMIENTO FINALIZADO</span>';
                divLink.style.display = 'none';
            }
            new bootstrap.Modal(document.getElementById('modalDetalle')).show();
        }

        function copiarLink(url) {
            navigator.clipboard.writeText(url).then(() => { alert('Enlace copiado al portapapeles'); });
        }
        function copiarLinkModal() {
            let input = document.getElementById('inputModalLink');
            input.select(); navigator.clipboard.writeText(input.value).then(() => { alert('Copiado'); });
        }
    </script>
</body>
</html>