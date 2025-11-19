<?php
// Archivo: encargado_pedidos_lista.php (o admin_pedidos_lista.php)

// 1. PRIMERO: Incluir acceso_protegido (maneja sesión y conexión)
include 'acceso_protegido.php';

// 2. SEGUNDO: Verificar el permiso específico
// (acceso_protegido.php ya incluyó $pdo y session_start())
include_once 'funciones_permisos.php'; 
if (!isset($_SESSION['usuario_id']) || !tiene_permiso('acceso_pedidos_lista_encargado', $pdo)) {
    header("Location: dashboard.php?error=Acceso denegado");
    exit(); // Importante salir después de header()
}
// 3. TERCERO: Incluir navbar (Genera HTML)
include 'navbar.php';

// --- A partir de aquí SÍ puedes tener HTML y lógica PHP que haga echo ---
$pedidos_pendientes = [];
$error_msg = '';
$highlight_pedido = (int)($_GET['highlight_pedido'] ?? 0); // Para resaltar

try {
    // 2. Consultar pedidos pendientes ('pendiente_encargado'), uniendo con nombre del auxiliar y área
    $sql = "SELECT p.*,
                   u.nombre_completo AS nombre_auxiliar,
                   a.nombre AS nombre_area
            FROM pedidos_trabajo p
            JOIN usuarios u ON p.id_auxiliar = u.id_usuario
            LEFT JOIN areas a ON p.id_area = a.id_area
            WHERE p.estado_pedido = 'pendiente_encargado' -- Estado clave
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
    $error_msg = "Error al cargar la lista de pedidos pendientes: " . $e->getMessage();
    error_log("Error DB encargado_pedidos_lista: " . $e->getMessage());
}

// Función para traducir prioridad (puedes moverla a un archivo de helpers si la usas mucho)
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
    <title>Bandeja de Pedidos Pendientes</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">
    <style>
        /* Estilo para resaltar fila */
        .highlight-row {
            background-color: #fff3cd !important; /* Amarillo claro */
            border: 2px solid #ffc107;
        }
        .table th, .table td { vertical-align: middle; } /* Mejor alineación vertical */
    </style>
</head>
<body>

<div class="container mt-4">
    <h1 class="mb-4"><i class="fas fa-inbox me-2 text-primary"></i> Bandeja de Pedidos Pendientes</h1>
    <p class="text-muted">Revise los pedidos solicitados y conviértalos en tareas asignables o rechácelos.</p>

    <?php if ($error_msg): ?>
        <div class="alert alert-danger"><?php echo htmlspecialchars($error_msg); ?></div>
    <?php endif; ?>

    <?php // Manejo de mensajes de éxito/error desde otras páginas (ej. rechazar) ?>
    <?php if (isset($_SESSION['encargado_pedido_mensaje'])): ?>
        <div class="alert alert-<?php echo $_SESSION['encargado_pedido_alerta_tipo'] ?? 'info'; ?> alert-dismissible fade show" role="alert">
            <?php echo htmlspecialchars($_SESSION['encargado_pedido_mensaje']); ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
        <?php
            unset($_SESSION['encargado_pedido_mensaje']);
            unset($_SESSION['encargado_pedido_alerta_tipo']);
        ?>
    <?php endif; ?>

    <div class="card shadow-sm">
        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-hover align-middle">
                    <thead class="table-dark">
                        <tr>
                            <th>N° Orden</th>
                            <th>Fecha Sol.</th>
                            <th>Área</th>
                            <th>Solicitante Ext.</th>
                            <th>Registrado Por</th>
                            <th>Prioridad</th>
                            <th>Descripción</th>
                            <th class="text-center">Acciones</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($pedidos_pendientes)): ?>
                            <tr>
                                <td colspan="8" class="text-center text-muted p-4">
                                    <i class="fas fa-check-circle fa-2x text-success mb-2"></i><br>
                                    ¡No hay pedidos pendientes de aprobación!
                                </td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($pedidos_pendientes as $pedido): ?>
                                <tr class="<?php echo ($pedido['id_pedido'] == $highlight_pedido) ? 'highlight-row' : ''; ?>">
                                    <td class="fw-bold"><?php echo htmlspecialchars($pedido['numero_orden']); ?></td>
                                    <td class="text-nowrap"><?php echo date('d/m/Y H:i', strtotime($pedido['fecha_emision'])); ?></td>
                                    <td><?php echo htmlspecialchars($pedido['nombre_area'] ?? 'N/A'); ?></td>
                                    <td><?php echo htmlspecialchars($pedido['solicitante_real_nombre']); ?></td>
                                    <td><?php echo htmlspecialchars($pedido['nombre_auxiliar']); ?></td>
                                    <td><?php echo traducir_prioridad_pedido($pedido['prioridad']); ?></td>
                                    <td style="max-width: 250px; white-space: normal;"><?php echo htmlspecialchars($pedido['descripcion_sintomas']); ?></td>
                                    <td class="text-center text-nowrap">
                                        
                                        <a href="generar_pedido_pdf.php?id=<?php echo $pedido['id_pedido']; ?>" target="_blank" class="btn btn-secondary btn-sm" title="Ver PDF Inicial">
                                            <i class="fas fa-file-pdf"></i>
                                        </a>

                                        <a href="tarea_crear.php?convertir_pedido=<?php echo $pedido['id_pedido']; ?>" class="btn btn-success btn-sm" title="Aprobar y Convertir en Tarea">
                                            <i class="fas fa-check"></i> Convertir
                                        </a>

                                        <?php // Formulario para Rechazar (necesita un script procesador) ?>
                                        <button type="button" class="btn btn-danger btn-sm"
                                                data-bs-toggle="modal" data-bs-target="#rejectPedidoModal"
                                                data-id="<?php echo $pedido['id_pedido']; ?>"
                                                data-orden="<?php echo htmlspecialchars($pedido['numero_orden']); ?>"
                                                onclick="prepareRejectModal(this)"
                                                title="Rechazar Pedido">
                                            <i class="fas fa-times"></i>
                                        </button>
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

<div class="modal fade" id="rejectPedidoModal" tabindex="-1" aria-labelledby="rejectPedidoModalLabel" aria-hidden="true">
  <div class="modal-dialog">
    <div class="modal-content">
      <form action="encargado_pedidos_procesar.php" method="POST"> <?php // Script a crear ?>
        <input type="hidden" name="action" value="rechazar">
        <input type="hidden" name="id_pedido_reject" id="id_pedido_reject">
        <div class="modal-header bg-danger text-white">
          <h5 class="modal-title" id="rejectPedidoModalLabel"><i class="fas fa-times-circle me-1"></i> Rechazar Pedido N° <span id="orden_pedido_reject"></span></h5>
          <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
        </div>
        <div class="modal-body">
          <p>¿Está seguro de que desea rechazar este pedido? Esta acción cambiará su estado y notificará (opcionalmente) al auxiliar que lo creó.</p>
          <div class="mb-3">
            <label for="motivo_rechazo" class="form-label">Motivo del Rechazo (Opcional, se incluirá en notificación)</label>
            <textarea class="form-control" id="motivo_rechazo" name="motivo_rechazo" rows="3"></textarea>
          </div>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
          <button type="submit" class="btn btn-danger"><i class="fas fa-times"></i> Confirmar Rechazo</button>
        </div>
      </form>
    </div>
  </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script>
    function prepareRejectModal(button) {
        const idPedido = button.getAttribute('data-id');
        const numOrden = button.getAttribute('data-orden');
        document.getElementById('id_pedido_reject').value = idPedido;
        document.getElementById('orden_pedido_reject').textContent = numOrden;
        document.getElementById('motivo_rechazo').value = ''; // Limpiar motivo
    }

     // Quitar el highlight de la URL después de un segundo si existe
    document.addEventListener('DOMContentLoaded', function() {
        const urlParams = new URLSearchParams(window.location.search);
        if (urlParams.has('highlight_pedido')) {
            setTimeout(() => {
                const currentUrl = new URL(window.location);
                currentUrl.searchParams.delete('highlight_pedido');
                window.history.replaceState({}, '', currentUrl);
            }, 1500); // 1.5 segundos
        }
    });
</script>
<div class="toast-container position-fixed bottom-0 end-0 p-3" id="notificationToastContainer" style="z-index: 1080;"></div>
</body>
</html>