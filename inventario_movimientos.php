<?php
// Archivo: inventario_movimientos.php
// OBJETIVO: Historial unificado (Movimientos cerrados + Transferencias Pendientes con Link)
session_start();
include 'conexion.php';
include 'funciones_permisos.php';

// Permisos
if (!isset($_SESSION['usuario_id']) || !tiene_permiso('inventario_historial', $pdo)) {
    header("Location: inventario_lista.php"); exit();
}

$puede_editar = tiene_permiso('inventario_historial_editar', $pdo);
$puede_eliminar = tiene_permiso('inventario_historial_eliminar', $pdo);

// Lógica eliminar (Solo historial cerrado)
if (isset($_GET['delete']) && $puede_eliminar) {
    $stmtDel = $pdo->prepare("DELETE FROM historial_movimientos WHERE id_movimiento = ?");
    $stmtDel->execute([$_GET['delete']]);
    header("Location: inventario_movimientos.php?msg=eliminado"); exit();
}

// Lógica cancelar pendiente (Eliminar solicitud)
if (isset($_GET['cancelar_pendiente']) && $puede_eliminar) {
    $stmtCan = $pdo->prepare("DELETE FROM inventario_transferencias_pendientes WHERE id_token = ? AND estado = 'pendiente'");
    $stmtCan->execute([$_GET['cancelar_pendiente']]);
    header("Location: inventario_movimientos.php?msg=cancelado"); exit();
}

// --- FILTROS ---
$filtro_estado = $_GET['estado'] ?? 'todos'; // todos, finalizados, pendientes
$filtro_q = $_GET['q'] ?? '';
$whereHist = "1=1";
$wherePend = "t.estado = 'pendiente'"; // Solo traer pendientes reales
$paramsHist = [];
$paramsPend = [];

// Filtro Búsqueda Global
if ($filtro_q) {
    $term = "%$filtro_q%";
    $whereHist .= " AND (i.elemento LIKE ? OR u.nombre_completo LIKE ? OR h.observacion_movimiento LIKE ?)";
    $paramsHist = [$term, $term, $term];
    
    $wherePend .= " AND (i.elemento LIKE ? OR u.nombre_completo LIKE ? OR t.observaciones LIKE ?)";
    $paramsPend = [$term, $term, $term];
}

// --- 1. OBTENER HISTORIAL (FINALIZADOS) ---
$historial = [];
if ($filtro_estado !== 'pendientes') {
    $sqlH = "SELECT h.id_movimiento as id_unico, 'finalizado' as tipo_dato,
                    h.fecha_movimiento as fecha, 
                    h.tipo_movimiento as accion, 
                    h.observacion_movimiento as detalle, 
                    h.ubicacion_anterior, h.ubicacion_nueva, h.id_bien,
                    i.elemento, i.servicio_ubicacion, 
                    u.nombre_completo as usuario,
                    NULL as token_hash, NULL as firma_patrimonial
             FROM historial_movimientos h 
             LEFT JOIN inventario_cargos i ON h.id_bien = i.id_cargo 
             LEFT JOIN usuarios u ON h.usuario_registro = u.id_usuario 
             WHERE $whereHist
             ORDER BY h.fecha_movimiento DESC LIMIT 300";
    $stmtH = $pdo->prepare($sqlH);
    $stmtH->execute($paramsHist);
    $historial = $stmtH->fetchAll(PDO::FETCH_ASSOC);
}

// --- 2. OBTENER PENDIENTES (EN CURSO) ---
$pendientes = [];
if ($filtro_estado !== 'finalizados') {
    $sqlP = "SELECT t.id_token as id_unico, 'pendiente' as tipo_dato,
                    t.fecha_creacion as fecha, 
                    'Solicitud Transferencia' as accion, 
                    t.observaciones as detalle, 
                    i.destino_principal as ubicacion_anterior, 
                    t.nuevo_destino_nombre as ubicacion_nueva,
                    t.id_bien,
                    i.elemento, i.servicio_ubicacion, 
                    u.nombre_completo as usuario,
                    t.token_hash, t.firma_patrimonial_path as firma_patrimonial
             FROM inventario_transferencias_pendientes t
             LEFT JOIN inventario_cargos i ON t.id_bien = i.id_cargo 
             LEFT JOIN usuarios u ON t.creado_por = u.id_usuario 
             WHERE $wherePend
             ORDER BY t.fecha_creacion DESC";
    $stmtP = $pdo->prepare($sqlP);
    $stmtP->execute($paramsPend);
    $pendientes = $stmtP->fetchAll(PDO::FETCH_ASSOC);
}

// --- 3. UNIFICAR LISTAS ---
$todos = array_merge($pendientes, $historial);

// Helper para Link
$protocol = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on') ? "https://" : "http://";
$base_url = $protocol . $_SERVER['HTTP_HOST'] . dirname($_SERVER['PHP_SELF']) . "/";

// Helper para buscar Acta Finalizada
function buscar_acta_reciente($id_bien) {
    $patron = __DIR__ . '/pdfs_publicos/inventario_pdf/Acta_Transferencia_' . $id_bien . '_*.pdf';
    $archivos = glob($patron);
    if ($archivos && count($archivos) > 0) {
        usort($archivos, function($a, $b) { return filemtime($b) - filemtime($a); });
        return 'pdfs_publicos/inventario_pdf/' . basename($archivos[0]);
    }
    return false;
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Historial y Pendientes | Logística</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="https://cdn.datatables.net/1.13.4/css/dataTables.bootstrap5.min.css">
    <style>
        .fila-pendiente { background-color: #fff3cd !important; border-left: 5px solid #ffc107; }
        .fila-finalizada { border-left: 5px solid #198754; }
        .badge-patrimonio-ok { background-color: #198754; font-size: 0.7rem; }
        .badge-patrimonio-wait { background-color: #ffc107; color: #000; font-size: 0.7rem; }
    </style>
</head>
<body>
    <?php include 'navbar.php'; ?>
    
    <div class="container mt-4 mb-5">
        
        <div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-2">
            <h3 class="mb-0"><i class="fas fa-history me-2"></i>Control de Movimientos</h3>
            
            <form class="d-flex gap-2" method="GET">
                <select name="estado" class="form-select w-auto" onchange="this.form.submit()">
                    <option value="todos" <?php echo $filtro_estado=='todos'?'selected':''; ?>>Ver Todo</option>
                    <option value="pendientes" <?php echo $filtro_estado=='pendientes'?'selected':''; ?>>⚠️ Solo Pendientes</option>
                    <option value="finalizados" <?php echo $filtro_estado=='finalizados'?'selected':''; ?>>✅ Solo Finalizados</option>
                </select>
                <?php if(!empty($_GET['q'])): ?>
                    <input type="hidden" name="q" value="<?php echo htmlspecialchars($_GET['q']); ?>">
                <?php endif; ?>
            </form>
        </div>

        <?php if(isset($_GET['msg']) && $_GET['msg']=='cancelado'): ?>
            <div class="alert alert-warning">La solicitud pendiente ha sido cancelada.</div>
        <?php endif; ?>

        <div class="card shadow-sm border-0">
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table id="tablaUnificada" class="table table-hover align-middle w-100 mb-0">
                        <thead class="bg-light text-uppercase small fw-bold">
                            <tr>
                                <th>Estado</th>
                                <th>Fecha</th>
                                <th>Bien</th>
                                <th>Origen <i class="fas fa-arrow-right mx-1"></i> Destino</th>
                                <th>Iniciado Por</th>
                                <th class="text-end">Gestión</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach($todos as $fila): 
                                $es_pendiente = ($fila['tipo_dato'] === 'pendiente');
                                $clase_fila = $es_pendiente ? 'fila-pendiente' : 'fila-finalizada';
                                
                                // Link para firmar (solo pendientes)
                                $link_firma = $es_pendiente ? $base_url . "transferencia_externa.php?token=" . $fila['token_hash'] : '';
                                
                                // Link acta (solo finalizados)
                                $ruta_acta = (!$es_pendiente && stripos($fila['accion'], 'Transferencia') !== false) 
                                             ? buscar_acta_reciente($fila['id_bien']) : false;
                            ?>
                            <tr class="<?php echo $clase_fila; ?>">
                                <td class="text-center">
                                    <?php if($es_pendiente): ?>
                                        <span class="badge bg-warning text-dark mb-1"><i class="fas fa-clock me-1"></i> PENDIENTE</span><br>
                                        
                                        <?php if($fila['firma_patrimonial_path']): ?>
                                            <span class="badge badge-patrimonio-ok"><i class="fas fa-check"></i> Patr.</span>
                                        <?php else: ?>
                                            <span class="badge badge-patrimonio-wait"><i class="fas fa-hourglass"></i> Patr.</span>
                                        <?php endif; ?>
                                    <?php else: ?>
                                        <span class="badge bg-success"><i class="fas fa-check-double me-1"></i> FINALIZADO</span>
                                    <?php endif; ?>
                                </td>

                                <td><?php echo date('d/m/y H:i', strtotime($fila['fecha'])); ?></td>
                                
                                <td class="fw-bold text-primary"><?php echo htmlspecialchars($fila['elemento']); ?></td>
                                
                                <td class="small">
                                    <div class="text-danger"><i class="fas fa-map-marker-alt me-1"></i> <?php echo htmlspecialchars($fila['ubicacion_anterior']); ?></div>
                                    <div class="text-success"><i class="fas fa-map-marker me-1"></i> <?php echo htmlspecialchars($fila['ubicacion_nueva']); ?></div>
                                </td>

                                <td class="small text-muted"><?php echo htmlspecialchars($fila['usuario'] ?? 'Sistema'); ?></td>

                                <td class="text-end">
                                    <div class="btn-group">
                                        <button class="btn btn-sm btn-info text-white" onclick="verDetalle(<?php echo htmlspecialchars(json_encode($fila)); ?>)" title="Ver Detalles">
                                            <i class="fas fa-eye"></i>
                                        </button>

                                        <?php if($es_pendiente): ?>
                                            <button class="btn btn-sm btn-dark" onclick="copiarLink('<?php echo $link_firma; ?>')" title="Copiar Enlace para Firmar">
                                                <i class="fas fa-link"></i>
                                            </button>
                                            
                                            <?php if($puede_eliminar): ?>
                                                <a href="inventario_movimientos.php?cancelar_pendiente=<?php echo $fila['id_unico']; ?>" class="btn btn-sm btn-outline-danger" onclick="return confirm('¿Cancelar esta transferencia pendiente?')" title="Cancelar Solicitud">
                                                    <i class="fas fa-times"></i>
                                                </a>
                                            <?php endif; ?>

                                        <?php else: ?>
                                            <a href="inventario_movimientos_pdf.php?id=<?php echo $fila['id_unico']; ?>" target="_blank" class="btn btn-sm btn-danger" title="Constancia (Anexo 4)">
                                                <i class="fas fa-file-pdf"></i>
                                            </a>

                                            <?php if($ruta_acta): ?>
                                                <a href="<?php echo $ruta_acta; ?>" target="_blank" class="btn btn-sm btn-primary" title="Acta Transferencia">
                                                    <i class="fas fa-file-contract"></i>
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
            <div class="modal-content">
                <div class="modal-header bg-dark text-white">
                    <h5 class="modal-title">Detalle del Movimiento</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <ul class="list-group list-group-flush">
                        <li class="list-group-item"><strong>Estado:</strong> <span id="m_estado"></span></li>
                        <li class="list-group-item"><strong>Elemento:</strong> <span id="m_bien" class="fw-bold text-primary"></span></li>
                        <li class="list-group-item bg-light text-muted small" id="m_obs"></li>
                        <li class="list-group-item">
                            <div class="row text-center">
                                <div class="col-6 border-end">
                                    <small class="text-danger fw-bold">SALIDA</small><br>
                                    <span id="m_origen"></span>
                                </div>
                                <div class="col-6">
                                    <small class="text-success fw-bold">ENTRADA</small><br>
                                    <span id="m_destino"></span>
                                </div>
                            </div>
                        </li>
                    </ul>
                    <div id="divLink" class="mt-3 text-center" style="display:none;">
                        <label class="fw-bold text-primary small">Enlace de Validación:</label>
                        <div class="input-group">
                            <input type="text" id="inputModalLink" class="form-control form-control-sm" readonly>
                            <button class="btn btn-dark btn-sm" onclick="copiarLinkModal()">Copiar</button>
                        </div>
                    </div>
                </div>
                <div class="modal-footer"><button class="btn btn-secondary" data-bs-dismiss="modal">Cerrar</button></div>
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
                order: [[1, 'desc']], // Ordenar por fecha
                language: { url: "//cdn.datatables.net/plug-ins/1.13.4/i18n/es-ES.json" } 
            });
        });

        function verDetalle(data) {
            document.getElementById('m_bien').innerText = data.elemento;
            document.getElementById('m_origen').innerText = data.ubicacion_anterior || '-';
            document.getElementById('m_destino').innerText = data.ubicacion_nueva || '-';
            document.getElementById('m_obs').innerText = data.detalle || 'Sin observaciones';
            
            let estadoHTML = '';
            let divLink = document.getElementById('divLink');
            
            if(data.tipo_dato === 'pendiente') {
                estadoHTML = '<span class="badge bg-warning text-dark">PENDIENTE DE VALIDACIÓN</span>';
                divLink.style.display = 'block';
                document.getElementById('inputModalLink').value = "<?php echo $base_url; ?>transferencia_externa.php?token=" + data.token_hash;
            } else {
                estadoHTML = '<span class="badge bg-success">FINALIZADO</span>';
                divLink.style.display = 'none';
            }
            document.getElementById('m_estado').innerHTML = estadoHTML;
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