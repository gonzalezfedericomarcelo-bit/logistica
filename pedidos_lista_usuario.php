<?php
// Archivo: pedidos_lista_usuario.php (NUEVO)
// Muestra al usuario logueado el historial de sus propios pedidos

include 'acceso_protegido.php'; 
include 'navbar.php'; 

$id_solicitante = $_SESSION['usuario_id'];
$pedidos = [];
$highlight_pedido = (int)($_GET['highlight_pedido'] ?? 0);

try {
    // Buscamos todos los pedidos de este usuario
    $sql = "SELECT id_pedido, area_solicitante, prioridad, descripcion_sintomas, fecha_emision, estado_pedido, id_tarea_generada 
            FROM pedidos_trabajo 
            WHERE id_solicitante = :id_solicitante 
            ORDER BY fecha_emision DESC";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([':id_solicitante' => $id_solicitante]);
    $pedidos = $stmt->fetchAll(PDO::FETCH_ASSOC);

} catch (PDOException $e) {
    $error_msg = "Error al cargar el historial de pedidos: " . $e->getMessage();
}

// Función simple para traducir el estado del pedido
function traducir_estado_pedido($estado) {
    switch ($estado) {
        case 'pendiente_aprobacion': return '<span class="badge bg-warning text-dark">Pendiente Aprobación</span>';
        case 'aprobado': return '<span class="badge bg-success">Aprobado</span>';
        case 'rechazado': return '<span class="badge bg-danger">Rechazado</span>';
        default: return '<span class="badge bg-secondary">' . htmlspecialchars($estado) . '</span>';
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Mis Pedidos de Trabajo</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">
    <style>
        .highlight-row {
            background-color: #fff3cd !important; /* Amarillo claro de Bootstrap */
            border: 2px solid #ffc107;
        }
    </style>
</head>
<body>

<div class="container mt-4">
    <h1 class="mb-4"><i class="fas fa-history me-2"></i> Mis Pedidos de Trabajo</h1>
    <p class="text-muted">Aquí puede ver el historial de los pedidos que ha solicitado a Logística y su estado actual.</p>

    <?php if (isset($error_msg)): ?>
        <div class="alert alert-danger"><?php echo htmlspecialchars($error_msg); ?></div>
    <?php endif; ?>

    <div class="card shadow-sm">
        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-hover align-middle">
                    <thead class="table-dark">
                        <tr>
                            <th>ID Pedido</th>
                            <th>Área Solicitante</th>
                            <th>Prioridad</th>
                            <th>Descripción (Resumen)</th>
                            <th>Fecha Emisión</th>
                            <th>Estado</th>
                            <th>ID Tarea (Si aplica)</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($pedidos)): ?>
                            <tr>
                                <td colspan="7" class="text-center text-muted p-4">
                                    <i class="fas fa-folder-open fa-2x mb-2"></i><br>
                                    No ha realizado ningún pedido de trabajo todavía.
                                </td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($pedidos as $pedido): ?>
                                <tr class="<?php echo ($pedido['id_pedido'] == $highlight_pedido) ? 'highlight-row' : ''; ?>">
                                    <td class="fw-bold">#<?php echo $pedido['id_pedido']; ?></td>
                                    <td><?php echo htmlspecialchars($pedido['area_solicitante']); ?></td>
                                    <td><?php echo ucfirst(htmlspecialchars($pedido['prioridad'])); ?></td>
                                    <td><?php echo htmlspecialchars(substr($pedido['descripcion_sintomas'], 0, 70)); ?>...</td>
                                    <td><?php echo date('d/m/Y H:i', strtotime($pedido['fecha_emision'])); ?></td>
                                    <td><?php echo traducir_estado_pedido($pedido['estado_pedido']); ?></td>
                                    <td>
                                        <?php if ($pedido['id_tarea_generada']): ?>
                                            <a href="tarea_ver.php?id=<?php echo $pedido['id_tarea_generada']; ?>" title="Ver Tarea Generada" class="btn btn-sm btn-outline-primary">
                                                Ver Tarea #<?php echo $pedido['id_tarea_generada']; ?>
                                            </a>
                                        <?php else: ?>
                                            N/A
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<div class="toast-container position-fixed bottom-0 end-0 p-3" id="notificationToastContainer" style="z-index: 1080;"></div>
</body>
</html>