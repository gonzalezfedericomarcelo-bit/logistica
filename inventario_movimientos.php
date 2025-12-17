<?php
// Archivo: inventario_movimientos.php (HISTORIAL)
session_start();
include 'conexion.php';
include 'funciones_permisos.php';

if (!isset($_SESSION['usuario_id'])) { header("Location: dashboard.php"); exit(); }

$hist = [];
$error_msg = "";

try {
    $sql = "SELECT h.*, i.elemento, u.nombre_completo as usuario 
            FROM historial_movimientos h 
            LEFT JOIN inventario_cargos i ON h.id_bien = i.id_cargo 
            LEFT JOIN usuarios u ON h.usuario_registro = u.id_usuario 
            ORDER BY h.fecha_movimiento DESC LIMIT 200";
    
    $stmt = $pdo->query($sql);
    if ($stmt) {
        $hist = $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
} catch (Exception $e) {
    $error_msg = "Error al cargar historial: " . $e->getMessage();
}

?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Historial Movimientos</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="https://cdn.datatables.net/1.13.4/css/dataTables.bootstrap5.min.css">
</head>
<body>
    <?php include 'navbar.php'; ?>
    <div class="container mt-4 mb-5">
        <div class="d-flex justify-content-between mb-3 align-items-center">
            <h3 class="m-0"><i class="fas fa-history text-primary"></i> Historial de Movimientos</h3>
            <a href="inventario_lista.php" class="btn btn-outline-secondary"><i class="fas fa-arrow-left me-2"></i> Volver</a>
        </div>

        <?php if($error_msg): ?>
            <div class="alert alert-danger"><?php echo htmlspecialchars($error_msg); ?></div>
        <?php endif; ?>

        <div class="card shadow border-0">
            <div class="card-body">
                <div class="table-responsive">
                    <table id="tablaHist" class="table table-striped table-hover align-middle">
                        <thead class="bg-light">
                            <tr>
                                <th>Fecha</th>
                                <th>Bien</th>
                                <th>Acción</th>
                                <th>Detalle / Observación</th>
                                <th>Usuario</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach($hist as $h): ?>
                            <tr>
                                <td class="text-nowrap"><?php echo date('d/m/y H:i', strtotime($h['fecha_movimiento'])); ?></td>
                                <td class="fw-bold"><?php echo htmlspecialchars($h['elemento'] ?? 'Bien Eliminado/Desconocido'); ?></td>
                                <td>
                                    <?php 
                                        $tipo = $h['tipo_movimiento'];
                                        $bg = 'bg-secondary';
                                        if($tipo == 'Alta') $bg = 'bg-success';
                                        if($tipo == 'Baja') $bg = 'bg-danger';
                                        if($tipo == 'Transferencia') $bg = 'bg-info text-dark';
                                        if($tipo == 'Edicion') $bg = 'bg-warning text-dark';
                                    ?>
                                    <span class="badge <?php echo $bg; ?>"><?php echo htmlspecialchars($tipo); ?></span>
                                </td>
                                <td>
                                    <?php if($h['tipo_movimiento']=='Transferencia'): ?>
                                        <small class="d-block text-muted">Desde: <?php echo htmlspecialchars($h['ubicacion_anterior'] ?? '-'); ?></small>
                                        <div class="fw-bold text-success"><i class="fas fa-arrow-right me-1"></i> <?php echo htmlspecialchars($h['ubicacion_nueva'] ?? '-'); ?></div>
                                    <?php else: ?>
                                        <?php echo htmlspecialchars($h['observacion_movimiento']); ?>
                                    <?php endif; ?>
                                </td>
                                <td><small><?php echo htmlspecialchars($h['usuario']); ?></small></td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
    <script src="https://code.jquery.com/jquery-3.7.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.datatables.net/1.13.4/js/jquery.dataTables.min.js"></script>
    <script src="https://cdn.datatables.net/1.13.4/js/dataTables.bootstrap5.min.js"></script>
    <script>$(document).ready(function(){$('#tablaHist').DataTable({"order":[[0,"desc"]], "language": { "url": "//cdn.datatables.net/plug-ins/1.13.4/i18n/es-ES.json" }});});</script>
    <?php include 'footer.php'; ?>
</body>
</html>