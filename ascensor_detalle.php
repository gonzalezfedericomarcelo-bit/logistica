<?php
// Archivo: ascensor_detalle.php
// OBJETIVO: Bitácora visual con colores del sistema (Gris Oscuro, Rojo, Azul)
session_start();
require_once 'conexion.php';
require_once 'funciones_permisos.php';

if (!isset($_SESSION['usuario_id']) || !tiene_permiso('acceso_ascensores', $pdo)) {
    header("Location: dashboard.php");
    exit;
}

$id_ascensor = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
if (!$id_ascensor) { header("Location: mantenimiento_ascensores.php"); exit; }

$sql_asc = "SELECT a.*, e.nombre as nombre_empresa 
            FROM ascensores a 
            LEFT JOIN empresas_mantenimiento e ON a.id_empresa = e.id_empresa 
            WHERE a.id_ascensor = ?";
$stmt = $pdo->prepare($sql_asc);
$stmt->execute([$id_ascensor]);
$asc = $stmt->fetch(PDO::FETCH_ASSOC);
if (!$asc) die("Ascensor no encontrado.");

$sql_hist = "SELECT i.*, u.nombre_completo as usuario_reporta 
             FROM ascensor_incidencias i 
             LEFT JOIN usuarios u ON i.id_usuario_reporta = u.id_usuario 
             WHERE i.id_ascensor = ? 
             ORDER BY i.fecha_reporte DESC";
$stmt_h = $pdo->prepare($sql_hist);
$stmt_h->execute([$id_ascensor]);
$historial = $stmt_h->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <?php include 'head.php'; ?>
    <title>Bitácora - <?php echo htmlspecialchars($asc['nombre']); ?></title>
    <style>
        .timeline-container { padding-left: 20px; border-left: 3px solid #343a40; /* Gris oscuro sistema */ }
        .timeline-item { position: relative; margin-bottom: 25px; }
        .timeline-marker {
            position: absolute; left: -29px; top: 5px;
            width: 16px; height: 16px; border-radius: 50%;
            border: 3px solid #fff; box-shadow: 0 0 5px rgba(0,0,0,0.3);
        }
        .marker-resuelto { background-color: #198754; } /* Verde éxito */
        .marker-reportado { background-color: #dc3545; } /* Rojo sistema */
        
        .card-bitacora {
            border: 1px solid #dee2e6;
            border-left: 5px solid #6c757d; /* Borde gris por defecto */
            transition: all 0.2s; cursor: pointer;
        }
        .card-bitacora:hover { background-color: #f8f9fa; transform: translateX(3px); }
        
        .border-resuelto { border-left-color: #198754; }
        .border-reportado { border-left-color: #dc3545; }
    </style>
</head>
<body class="bg-light">
    <?php include 'navbar.php'; ?>
    
    <div class="container py-5">
        <div class="row mb-4">
            <div class="col-12">
                <nav aria-label="breadcrumb">
                    <ol class="breadcrumb mb-2">
                        <li class="breadcrumb-item"><a href="mantenimiento_ascensores.php" class="text-decoration-none text-danger">Ascensores</a></li>
                        <li class="breadcrumb-item active">Bitácora</li>
                    </ol>
                </nav>
                <div class="d-flex justify-content-between align-items-center bg-white p-4 rounded shadow-sm border">
                    <div>
                        <h2 class="fw-bold text-dark mb-0"><?php echo htmlspecialchars($asc['nombre']); ?></h2>
                        <p class="text-muted mb-0"><i class="fas fa-map-pin text-danger"></i> <?php echo htmlspecialchars($asc['ubicacion']); ?></p>
                    </div>
                    <div class="text-end d-none d-md-block">
                        <span class="badge bg-dark p-2">
                            <i class="fas fa-tools"></i> <?php echo htmlspecialchars($asc['nombre_empresa'] ?? 'Sin Empresa'); ?>
                        </span>
                    </div>
                </div>
            </div>
        </div>

        <div class="row">
            <div class="col-md-10 mx-auto">
                <h5 class="mb-4 text-uppercase fw-bold text-secondary"><i class="fas fa-history"></i> Historial de Incidencias</h5>
                
                <div class="timeline-container">
                    <?php if (empty($historial)): ?>
                        <div class="alert alert-light border">No hay registros en la bitácora de este equipo.</div>
                    <?php else: ?>
                        <?php foreach($historial as $h): ?>
                            <?php 
                                $isResuelto = ($h['estado'] == 'resuelto'); 
                                $bordeClase = $isResuelto ? 'border-resuelto' : 'border-reportado';
                                $markerClase = $isResuelto ? 'marker-resuelto' : 'marker-reportado';
                            ?>
                            <div class="timeline-item">
                                <div class="timeline-marker <?php echo $markerClase; ?>"></div>
                                <div class="text-secondary small fw-bold mb-1">
                                    <?php echo date('d/m/Y H:i', strtotime($h['fecha_reporte'])); ?>
                                </div>
                                
                                <div class="card card-bitacora shadow-sm <?php echo $bordeClase; ?> p-3" onclick='verDetalle(<?php echo json_encode($h); ?>)'>
                                    <div class="d-flex justify-content-between align-items-start">
                                        <div>
                                            <h6 class="fw-bold mb-1 text-dark"><?php echo htmlspecialchars($h['titulo']); ?></h6>
                                            <span class="badge <?php echo $isResuelto?'bg-success':'bg-danger'; ?>">
                                                <?php echo strtoupper($h['estado']); ?>
                                            </span>
                                        </div>
                                        <i class="fas fa-chevron-right text-muted"></i>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <div class="modal fade" id="modalDetalle" tabindex="-1">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content border-0">
                <div class="modal-header bg-dark text-white">
                    <h5 class="modal-title"><i class="fas fa-info-circle text-danger"></i> Detalle Incidencia #<span id="d_id"></span></h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body p-4">
                    <h5 class="fw-bold text-dark" id="d_titulo"></h5>
                    <p class="text-muted border-start border-3 border-danger ps-3 py-2 bg-light" id="d_desc"></p>
                    
                    <div class="row mt-4 g-3">
                        <div class="col-6">
                            <small class="text-uppercase text-secondary fw-bold">Fecha</small>
                            <div id="d_fecha" class="fw-bold text-dark"></div>
                        </div>
                        <div class="col-6">
                            <small class="text-uppercase text-secondary fw-bold">Prioridad</small>
                            <div id="d_prio" class="fw-bold text-danger"></div>
                        </div>
                        <div class="col-12">
                            <small class="text-uppercase text-secondary fw-bold">Reportado Por</small>
                            <div id="d_user" class="fw-bold text-dark"></div>
                        </div>
                    </div>
                </div>
                <div class="modal-footer bg-light">
                    <a href="#" id="btn_pdf" target="_blank" class="btn btn-danger w-100 shadow-sm">
                        <i class="fas fa-file-pdf"></i> Descargar PDF Oficial
                    </a>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        function verDetalle(data) {
            document.getElementById('d_id').textContent = data.id_incidencia;
            document.getElementById('d_titulo').textContent = data.titulo;
            document.getElementById('d_desc').textContent = data.descripcion_problema;
            document.getElementById('d_fecha').textContent = data.fecha_reporte;
            document.getElementById('d_prio').textContent = data.prioridad.toUpperCase();
            document.getElementById('d_user').textContent = data.usuario_reporta || 'Desconocido';
            document.getElementById('btn_pdf').href = 'ascensor_orden_pdf.php?id=' + data.id_incidencia;
            new bootstrap.Modal(document.getElementById('modalDetalle')).show();
        }
    </script>
</body>
</html>