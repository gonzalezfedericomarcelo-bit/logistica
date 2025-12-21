<?php
// Archivo: inventario_lista.php (LIMPIO: Sin botón importar)
session_start();
include 'conexion.php';
include 'funciones_permisos.php';

if (!isset($_SESSION['usuario_id']) || !tiene_permiso('acceso_inventario', $pdo)) {
    header("Location: dashboard.php"); exit();
}

// OBTENER CATEGORÍAS
$sqlTipos = "SELECT tb.*, 
            (SELECT COUNT(*) FROM inventario_cargos c WHERE c.id_tipo_bien = tb.id_tipo_bien AND c.id_estado_fk != 0) as total_activos
            FROM inventario_tipos_bien tb 
            ORDER BY tb.nombre ASC";
$tipos_bienes = $pdo->query($sqlTipos)->fetchAll(PDO::FETCH_ASSOC);

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
    <style>
        body { background-color: #f4f6f9; }
        .card-hover { transition: transform 0.2s, box-shadow 0.2s; cursor: pointer; border: none; }
        .card-hover:hover { transform: translateY(-5px); box-shadow: 0 .5rem 1rem rgba(0,0,0,.15)!important; }
        .icon-box { width: 60px; height: 60px; display: flex; align-items: center; justify-content: center; border-radius: 50%; }
        .border-left-primary { border-left: 4px solid #4e73df!important; }
        .border-left-success { border-left: 4px solid #1cc88a!important; }
        .border-left-info    { border-left: 4px solid #36b9cc!important; }
        .border-left-warning { border-left: 4px solid #f6c23e!important; }
        .border-left-danger  { border-left: 4px solid #e74a3b!important; }
        .border-left-secondary { border-left: 4px solid #858796!important; }
    </style>
</head>
<body>
    <?php include 'navbar.php'; ?>

    <div class="container mt-4 mb-5">
        
        <div class="d-flex flex-wrap justify-content-between align-items-center mb-4 gap-2">
            <h2 class="text-dark fw-bold m-0"><i class="fas fa-boxes me-2"></i>Inventario</h2>
            
            <div class="d-flex flex-wrap gap-2">
                <div class="btn-group shadow-sm">
                    <a href="inventario_config.php" class="btn btn-outline-secondary" title="Configuración"><i class="fas fa-cog"></i></a>
                    <a href="inventario_reporte_pdf.php" target="_blank" class="btn btn-outline-danger" title="Reporte PDF"><i class="fas fa-file-pdf"></i> Reporte</a>
                    
                </div>
                <a href="inventario_mantenimiento.php" class="btn btn-outline-warning text-dark shadow-sm"><i class="fas fa-tools me-1"></i> Servicio Técnico</a>
                <a href="inventario_movimientos.php" class="btn btn-outline-info text-dark shadow-sm"><i class="fas fa-exchange-alt me-1"></i> Historial de Transferencias</a>
                
                <a href="inventario_nuevo.php" class="btn btn-success fw-bold px-4 shadow-sm"><i class="fas fa-plus me-2"></i>NUEVO</a>
            </div>
        </div>

        <div class="row g-4 mb-5">
            <?php 
            $colores = ['primary', 'success', 'info', 'warning', 'danger', 'secondary'];
            $i = 0;
            foreach($tipos_bienes as $tipo): 
                $color = $colores[$i % count($colores)];
                $icono = $tipo['icono'] ? $tipo['icono'] : 'fas fa-box';
                $i++;
            ?>
            <div class="col-xl-3 col-md-6">
                <div class="card card-hover shadow h-100 py-2 border-left-<?php echo $color; ?>" 
                     onclick="window.location.href='inventario_ver_categoria.php?id=<?php echo $tipo['id_tipo_bien']; ?>'">
                    <div class="card-body">
                        <div class="row align-items-center">
                            <div class="col">
                                <div class="text-xs fw-bold text-<?php echo $color; ?> text-uppercase mb-1"><?php echo htmlspecialchars($tipo['nombre']); ?></div>
                                <div class="h5 mb-0 fw-bold text-dark"><?php echo $tipo['total_activos']; ?> Activos</div>
                            </div>
                            <div class="col-auto">
                                <div class="icon-box bg-<?php echo $color; ?> bg-opacity-10 text-<?php echo $color; ?>">
                                    <i class="<?php echo $icono; ?> fa-2x"></i>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <?php endforeach; ?>
            
            <div class="col-xl-3 col-md-6">
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
            const urlParams = new URLSearchParams(window.location.search);
            if (urlParams.has('msg') && (urlParams.get('msg') === 'guardado_ok' || urlParams.get('msg') === 'importado_ok' || urlParams.get('msg') === 'estructura_ok')) {
                new bootstrap.Modal(document.getElementById('modalExito')).show();
                window.history.replaceState({}, document.title, window.location.pathname);
            }
        });
    </script>
</body>
</html>