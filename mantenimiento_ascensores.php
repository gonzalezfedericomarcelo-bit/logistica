<?php
// Archivo: mantenimiento_ascensores.php
// OBJETIVO: Diseño con paleta del sistema (Dark/Red/Primary) y más info visual.
session_start();
require_once 'conexion.php';
require_once 'funciones_permisos.php';

if (!isset($_SESSION['usuario_id']) || !tiene_permiso('acceso_ascensores', $pdo)) {
    header("Location: dashboard.php");
    exit;
}

$sql = "SELECT a.*, 
        e.nombre as nombre_empresa,
        (SELECT COUNT(*) FROM ascensor_incidencias WHERE id_ascensor = a.id_ascensor AND estado != 'resuelto') as fallas_activas,
        (SELECT MAX(fecha_reporte) FROM ascensor_incidencias WHERE id_ascensor = a.id_ascensor) as ultima_falla
        FROM ascensores a 
        LEFT JOIN empresas_mantenimiento e ON a.id_empresa = e.id_empresa 
        ORDER BY a.nombre ASC";
$ascensores = $pdo->query($sql)->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <?php include 'head.php'; ?>
    <title>Gestión de Ascensores</title>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <style>
        .card-ascensor {
            border: none;
            border-radius: 10px; /* Bordes menos redondeados, más serio */
            box-shadow: 0 4px 12px rgba(0,0,0,0.05);
            transition: transform 0.2s;
            overflow: hidden;
        }
        .card-ascensor:hover {
            transform: translateY(-3px);
            box-shadow: 0 8px 20px rgba(0,0,0,0.1);
        }
        /* Header oscuro estilo sistema */
        .card-header-custom {
            background-color: #343a40; /* bg-dark */
            color: white;
            padding: 15px;
            display: flex; justify-content: space-between; align-items: center;
        }
        .info-row {
            display: flex; justify-content: space-between;
            padding: 10px 0; border-bottom: 1px solid #eee;
            font-size: 0.9rem;
        }
        .info-row:last-child { border-bottom: none; }
        .label-custom { font-weight: 600; color: #6c757d; }
        
        .btn-action { width: 100%; border-radius: 6px; font-weight: 500; }
    </style>
</head>
<body class="bg-light">
    <?php include 'navbar.php'; ?>
    
    <div class="container py-5">
        <div class="d-flex justify-content-between align-items-center mb-5">
            <div>
                <h2 class="fw-bold text-dark mb-1"><i class="fas fa-elevator text-danger"></i> Parque de Ascensores</h2>
                <p class="text-muted mb-0">Gestión de unidades y mantenimiento técnico.</p>
            </div>
            <div class="d-flex gap-2">
                <a href="ascensor_estadisticas.php" class="btn btn-dark shadow-sm">
                    <i class="fas fa-chart-bar me-2"></i> Reportes
                </a>
                <?php if (tiene_permiso('admin_ascensores', $pdo)): ?>
                    <a href="admin_ascensores.php" class="btn btn-outline-secondary" title="Configuración"><i class="fas fa-cog"></i></a>
                <?php endif; ?>
            </div>
        </div>

        <?php if (isset($_SESSION['mensaje'])): ?>
            <script>
                Swal.fire({
                    icon: '<?php echo $_SESSION['tipo_mensaje'] == "success" ? "success" : "warning"; ?>',
                    title: '<?php echo $_SESSION['tipo_mensaje'] == "success" ? "Operación Exitosa" : "Atención"; ?>',
                    html: '<?php echo $_SESSION['mensaje']; ?>',
                    confirmButtonColor: '#dc3545'
                });
            </script>
            <?php unset($_SESSION['mensaje'], $_SESSION['tipo_mensaje']); ?>
        <?php endif; ?>

        <div class="row g-4">
            <?php if (empty($ascensores)): ?>
                <div class="col-12 text-center py-5">
                    <div class="alert alert-light border shadow-sm d-inline-block px-5">
                        <i class="fas fa-info-circle fa-2x mb-2 text-primary"></i>
                        <p class="mb-0">No hay ascensores cargados.</p>
                    </div>
                </div>
            <?php else: ?>
                <?php foreach($ascensores as $asc): ?>
                    <div class="col-md-6 col-lg-4">
                        <div class="card card-ascensor h-100">
                            <div class="card-header-custom">
                                <h5 class="mb-0 fw-bold text-truncate pe-2">
                                    <i class="fas fa-elevator me-2 text-white-50"></i><?php echo htmlspecialchars($asc['nombre']); ?>
                                </h5>
                                <?php if($asc['fallas_activas'] > 0): ?>
                                    <span class="badge bg-danger rounded-pill shadow-sm">
                                        <i class="fas fa-exclamation-circle"></i> <?php echo $asc['fallas_activas']; ?>
                                    </span>
                                <?php else: ?>
                                    <span class="badge bg-success rounded-pill shadow-sm">
                                        <i class="fas fa-check"></i> OK
                                    </span>
                                <?php endif; ?>
                            </div>

                            <div class="card-body">
                                <div class="info-row">
                                    <span class="label-custom">Estado Operativo</span>
                                    <span class="badge <?php echo $asc['estado']=='activo'?'bg-success':($asc['estado']=='inactivo'?'bg-danger':'bg-warning text-dark'); ?>">
                                        <?php echo strtoupper($asc['estado']); ?>
                                    </span>
                                </div>
                                <div class="info-row">
                                    <span class="label-custom">Ubicación</span>
                                    <span class="text-dark fw-bold"><?php echo htmlspecialchars($asc['ubicacion']); ?></span>
                                </div>
                                <div class="info-row">
                                    <span class="label-custom">Empresa</span>
                                    <span class="text-secondary text-truncate" style="max-width: 150px;">
                                        <?php echo $asc['nombre_empresa'] ? htmlspecialchars($asc['nombre_empresa']) : '<span class="text-muted fw-normal">--</span>'; ?>
                                    </span>
                                </div>
                                <div class="info-row">
                                    <span class="label-custom">Último Incidente</span>
                                    <span class="text-dark">
                                        <?php echo $asc['ultima_falla'] ? date('d/m/y', strtotime($asc['ultima_falla'])) : 'Sin registros'; ?>
                                    </span>
                                </div>

                                <div class="mt-4 row g-2">
                                    <div class="col-7">
                                        <button onclick="abrirModalReporte(<?php echo $asc['id_ascensor']; ?>, '<?php echo htmlspecialchars($asc['nombre']); ?>')" 
                                                class="btn btn-danger btn-action shadow-sm">
                                            <i class="fas fa-exclamation-triangle"></i> Reportar Falla
                                        </button>
                                    </div>
                                    <div class="col-5">
                                        <a href="ascensor_detalle.php?id=<?php echo $asc['id_ascensor']; ?>" class="btn btn-outline-dark btn-action">
                                            <i class="fas fa-history"></i> Bitácora
                                        </a>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </div>

    <div class="modal fade" id="modalReporte" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content border-0 shadow-lg">
                <div class="modal-header bg-dark text-white">
                    <h5 class="modal-title fw-bold"><i class="fas fa-file-alt text-danger"></i> Generar Reporte</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <form action="ascensor_crear_incidencia.php" method="POST">
                    <div class="modal-body p-4">
                        <input type="hidden" name="id_ascensor" id="modal_id_ascensor">
                        <div class="alert alert-light border mb-3">
                            <strong>Equipo:</strong> <span id="modal_nombre_ascensor">...</span>
                        </div>
                        <div class="form-floating mb-3">
                            <input type="text" name="titulo" class="form-control" id="fInput" placeholder="Título" required>
                            <label for="fInput">Título del Problema</label>
                        </div>
                        <div class="form-floating mb-3">
                            <textarea name="descripcion" class="form-control" id="fText" placeholder="Desc" style="height: 100px" required></textarea>
                            <label for="fText">Descripción Detallada</label>
                        </div>
                        <div class="mb-3">
                            <label class="form-label small fw-bold text-muted">NIVEL DE PRIORIDAD</label>
                            <select name="prioridad" class="form-select">
                                <option value="baja">Baja</option>
                                <option value="media" selected>Media</option>
                                <option value="alta">Alta</option>
                                <option value="emergencia">Emergencia</option>
                            </select>
                        </div>
                    </div>
                    <div class="modal-footer bg-light">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                        <button type="submit" class="btn btn-danger px-4">Confirmar Reporte</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        function abrirModalReporte(id, nombre) {
            document.getElementById('modal_id_ascensor').value = id;
            document.getElementById('modal_nombre_ascensor').textContent = nombre;
            new bootstrap.Modal(document.getElementById('modalReporte')).show();
        }
    </script>
</body>
</html>