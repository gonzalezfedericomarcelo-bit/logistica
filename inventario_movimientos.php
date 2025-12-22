<?php
// Archivo: inventario_movimientos.php
session_start();
include 'conexion.php';
include 'funciones_permisos.php';

// Permiso básico
if (!isset($_SESSION['usuario_id']) || !tiene_permiso('inventario_historial', $pdo)) {
    header("Location: inventario_lista.php"); exit();
}

$puede_editar = tiene_permiso('inventario_historial_editar', $pdo);
$puede_eliminar = tiene_permiso('inventario_historial_eliminar', $pdo);

// Lógica eliminar
if (isset($_GET['delete']) && $puede_eliminar) {
    $stmtDel = $pdo->prepare("DELETE FROM historial_movimientos WHERE id_movimiento = ?");
    $stmtDel->execute([$_GET['delete']]);
    header("Location: inventario_movimientos.php?msg=eliminado"); exit();
}

// Filtros
$where = "1=1";
$params = [];
// ... (mismo código de filtros que tenías) ...
if (!empty($_GET['q'])) {
    $term = "%" . $_GET['q'] . "%";
    $where .= " AND (i.elemento LIKE ? OR u.nombre_completo LIKE ? OR h.observacion_movimiento LIKE ?)";
    $params[] = $term; $params[] = $term; $params[] = $term;
}

// ID para resaltar (Viene de la notificación)
$highlight_id = isset($_GET['highlight_id']) ? (int)$_GET['highlight_id'] : 0;

// Consulta
$sql = "SELECT h.id_movimiento, h.fecha_movimiento, h.tipo_movimiento, h.observacion_movimiento, 
               h.ubicacion_anterior, h.ubicacion_nueva,
               i.elemento, i.servicio_ubicacion, 
               u.nombre_completo as usuario 
        FROM historial_movimientos h 
        LEFT JOIN inventario_cargos i ON h.id_bien = i.id_cargo 
        LEFT JOIN usuarios u ON h.usuario_registro = u.id_usuario 
        WHERE $where
        ORDER BY h.fecha_movimiento DESC LIMIT 500";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$hist = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Historial de Movimientos</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="https://cdn.datatables.net/1.13.4/css/dataTables.bootstrap5.min.css">
    <style>
        /* Estilo para fila resaltada */
        .fila-resaltada {
            background-color: #d1e7dd !important; /* Verde suave */
            border: 2px solid #198754 !important;
            transition: background-color 2s ease-out;
        }
    </style>
</head>
<body>
    <?php include 'navbar.php'; ?>
    <div class="container mt-4 mb-5">
        
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h3 class="mb-0"><i class="fas fa-history"></i> Historial de Movimientos</h3>
            <?php if($puede_eliminar): ?>
                <button class="btn btn-danger btn-sm"><i class="fas fa-trash-alt me-2"></i> Info Limpieza</button>
            <?php endif; ?>
        </div>
        
        <div class="card shadow-sm border-0">
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table id="tablaHist" class="table table-hover align-middle w-100">
                        <thead class="bg-light">
                            <tr>
                                <th>Fecha</th>
                                <th>Bien</th>
                                <th>Acción</th>
                                <th>Detalle (De -> A)</th>
                                <th>Usuario</th>
                                <th class="text-end">Acciones</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach($hist as $h): 
                                // Determinar si esta fila se resalta
                                $clase_extra = ($h['id_movimiento'] == $highlight_id) ? 'fila-resaltada' : '';
                            ?>
                            <tr class="<?php echo $clase_extra; ?>" id="row_<?php echo $h['id_movimiento']; ?>">
                                <td><?php echo date('d/m/Y H:i', strtotime($h['fecha_movimiento'])); ?></td>
                                <td><?php echo htmlspecialchars($h['elemento']); ?></td>
                                <td><span class="badge bg-secondary"><?php echo htmlspecialchars($h['tipo_movimiento']); ?></span></td>
                                <td>
                                    <?php if($h['ubicacion_anterior'] || $h['ubicacion_nueva']): ?>
                                        <div class="small">
                                            <span class="text-danger fw-bold">De:</span> <?php echo htmlspecialchars($h['ubicacion_anterior']); ?><br>
                                            <span class="text-success fw-bold">A:</span> <?php echo htmlspecialchars($h['ubicacion_nueva']); ?>
                                        </div>
                                    <?php else: ?>
                                        <span class="text-muted"><?php echo htmlspecialchars($h['observacion_movimiento']); ?></span>
                                    <?php endif; ?>
                                </td>
                                <td><?php echo htmlspecialchars($h['usuario'] ?? 'Sistema / Externo'); ?></td>
                                <td class="text-end">
                                    <a href="inventario_movimientos_pdf.php?id=<?php echo $h['id_movimiento']; ?>" target="_blank" class="btn btn-sm btn-outline-danger" title="Ver PDF">
                                        <i class="fas fa-file-pdf"></i>
                                    </a>
                                </td>
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
    <script>
        $(document).ready(function(){ 
            $('#tablaHist').DataTable({ 
                order: [[0, 'desc']], 
                language: { url: "//cdn.datatables.net/plug-ins/1.13.4/i18n/es-ES.json" } 
            });
            
            // Scroll automático si hay highlight
            <?php if($highlight_id): ?>
            setTimeout(function() {
                var row = document.getElementById('row_<?php echo $highlight_id; ?>');
                if(row) row.scrollIntoView({behavior: "smooth", block: "center"});
            }, 500);
            <?php endif; ?>
        });
    </script>
</body>
</html>