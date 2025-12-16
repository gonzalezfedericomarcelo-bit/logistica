<?php
// Archivo: inventario_movimientos.php (HISTORIAL)
session_start();
include 'conexion.php';
include 'funciones_permisos.php';

if (!isset($_SESSION['usuario_id'])) { header("Location: dashboard.php"); exit(); }

$sql = "SELECT h.*, i.elemento, u.nombre_completo as usuario 
        FROM historial_movimientos h 
        LEFT JOIN inventario_cargos i ON h.id_bien = i.id_cargo 
        LEFT JOIN usuarios u ON h.usuario_registro = u.id_usuario 
        ORDER BY h.fecha_movimiento DESC LIMIT 200";
$hist = $pdo->query($sql)->fetchAll(PDO::FETCH_ASSOC);
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
    <div class="container mt-4">
        <div class="d-flex justify-content-between mb-3">
            <h3><i class="fas fa-history text-primary"></i> Historial de Movimientos</h3>
            <a href="inventario_lista.php" class="btn btn-outline-secondary">Volver</a>
        </div>
        <div class="card shadow">
            <div class="card-body">
                <div class="table-responsive">
                    <table id="tablaHist" class="table table-striped">
                        <thead><tr><th>Fecha</th><th>Bien</th><th>Acción</th><th>Detalle</th><th>Usuario</th></tr></thead>
                        <tbody>
                            <?php foreach($hist as $h): ?>
                            <tr>
                                <td><?php echo date('d/m/y H:i', strtotime($h['fecha_movimiento'])); ?></td>
                                <td><?php echo htmlspecialchars($h['elemento'] ?? 'Bien Eliminado'); ?></td>
                                <td><span class="badge bg-info text-dark"><?php echo $h['tipo_movimiento']; ?></span></td>
                                <td>
                                    <?php if($h['tipo_movimiento']=='Transferencia'): ?>
                                        <small>De: <?php echo $h['ubicacion_anterior']; ?> <br> A: <strong><?php echo $h['ubicacion_nueva']; ?></strong></small>
                                    <?php else: ?>
                                        <?php echo htmlspecialchars($h['observacion_movimiento']); ?>
                                    <?php endif; ?>
                                </td>
                                <td><?php echo htmlspecialchars($h['usuario']); ?></td>
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
    <script>$(document).ready(function(){$('#tablaHist').DataTable({"order":[[0,"desc"]]});});</script>
    <?php include 'footer.php'; ?>
</body>
</html>