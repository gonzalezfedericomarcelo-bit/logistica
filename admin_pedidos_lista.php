<?php
// Archivo: encargado_pedidos_lista.php (o admin_pedidos_lista.php)

// 1. PRIMERO: Incluir acceso_protegido (maneja sesión y conexión)
include 'acceso_protegido.php';

// 2. SEGUNDO: Verificar el rol específico (USA header() SI ES NECESARIO ANTES DE HTML)
if (!isset($_SESSION['usuario_rol']) || !in_array($_SESSION['usuario_rol'], ['admin', 'encargado'])) { // Ajusta roles según el archivo
    header("Location: dashboard.php?error=Acceso denegado");
    exit(); // Importante salir después de header()
}

// 3. TERCERO: Incluir navbar (Genera HTML)
include 'navbar.php';

// --- A partir de aquí SÍ puedes tener HTML y lógica PHP que haga echo ---
$pedidos_pendientes = [];
$error_msg = '';

try {
    // 2. Consultar pedidos pendientes, uniendo con el nombre del solicitante
    $sql = "SELECT p.*, u.nombre_completo AS nombre_solicitante
            FROM pedidos_trabajo p
            JOIN usuarios u ON p.id_solicitante = u.id_usuario
            WHERE p.estado_pedido = 'pendiente_aprobacion'
            ORDER BY 
                CASE p.prioridad
                    WHEN 'urgente' THEN 1
                    WHEN 'importante' THEN 2
                    WHEN 'rutina' THEN 3
                    ELSE 4
                END ASC, 
                p.fecha_emision ASC";
            
    $stmt = $pdo->query($sql);
    $pedidos_pendientes = $stmt->fetchAll(PDO::FETCH_ASSOC);

} catch (PDOException $e) {
    $error_msg = "Error al cargar la lista de pedidos: " . $e->getMessage();
}

// Función simple para traducir la prioridad (basada en tu PDF)
function traducir_prioridad_pedido($prioridad) {
    switch ($prioridad) {
        case 'urgente': return '<span class="badge bg-danger">URGENTE</span>';
        case 'importante': return '<span class="badge bg-warning text-dark">Importante</span>';
        case 'rutina': return '<span class="badge bg-info">Rutina</span>';
        default: return '<span class="badge bg-secondary">' . htmlspecialchars($prioridad) . '</span>';
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Bandeja de Pedidos de Trabajo</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">
</head>
<body>

<div class="container mt-4">
    <h1 class="mb-4"><i class="fas fa-inbox me-2"></i> Bandeja de Pedidos de Trabajo</h1>
    <p class="text-muted">Revise, apruebe (convierta en tarea) o rechace los pedidos de trabajo solicitados por los usuarios.</p>

    <?php if (isset($error_msg) && $error_msg): ?>
        <div class="alert alert-danger"><?php echo htmlspecialchars($error_msg); ?></div>
    <?php endif; ?>
    
    <?php if (isset($_SESSION['pedido_admin_mensaje'])): ?>
        <div class="alert alert-<?php echo $_SESSION['pedido_admin_alerta_tipo'] ?? 'info'; ?> alert-dismissible fade show" role="alert">
            <?php echo htmlspecialchars($_SESSION['pedido_admin_mensaje']); ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
        <?php 
            unset($_SESSION['pedido_admin_mensaje']);
            unset($_SESSION['pedido_admin_alerta_tipo']);
        ?>
    <?php endif; ?>

    <div class="card shadow-sm">
        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-hover align-middle">
                    <thead class="table-dark">
                        <tr>
                            <th>ID</th>
                            <th>Fecha Solicitado</th>
                            <th>Solicitante</th>
                            <th>Área</th>
                            <th>Prioridad</th>
                            <th>Descripción del Pedido</th>
                            <th class="text-center">Acciones</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($pedidos_pendientes)): ?>
                            <tr>
                                <td colspan="7" class="text-center text-muted p-4">
                                    <i class="fas fa-check-circle fa-2x text-success mb-2"></i><br>
                                    ¡Excelente! No hay pedidos de trabajo pendientes de aprobación.
                                </td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($pedidos_pendientes as $pedido): ?>
                                <tr>
                                    <td class="fw-bold">#<?php echo $pedido['id_pedido']; ?></td>
                                    <td class="text-nowrap"><?php echo date('d/m/Y H:i', strtotime($pedido['fecha_emision'])); ?></td>
                                    <td><?php echo htmlspecialchars($pedido['nombre_solicitante']); ?></td>
                                    <td><?php echo htmlspecialchars($pedido['area_solicitante']); ?></td>
                                    <td><?php echo traducir_prioridad_pedido($pedido['prioridad']); ?></td>
                                    <td style="max-width: 300px;"><?php echo htmlspecialchars($pedido['descripcion_sintomas']); ?></td>
                                    <td class="text-center text-nowrap">
                                        
                                        <a href="tarea_crear.php?convertir_pedido=<?php echo $pedido['id_pedido']; ?>" class="btn btn-success btn-sm" title="Aprobar y Convertir en Tarea">
                                            <i class="fas fa-check"></i> Aprobar
                                        </a>
                                        
                                        <form action="admin_pedidos_procesar.php" method="POST" class="d-inline" onsubmit="return confirm('¿Está seguro de que desea RECHAZAR este pedido? Esta acción notificará al solicitante.');">
                                            <input type="hidden" name="action" value="rechazar">
                                            <input type="hidden" name="id_pedido" value="<?php echo $pedido['id_pedido']; ?>">
                                            <button type="submit" class="btn btn-danger btn-sm" title="Rechazar Pedido">
                                                <i class="fas fa-times"></i>
                                            </button>
                                        </form>
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
<?php include 'footer.php'; ?>
</body>
</html>