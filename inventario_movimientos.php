<?php
// Archivo: inventario_movimientos.php
session_start();
include 'conexion.php';
include 'funciones_permisos.php';

// Permiso básico de lectura
if (!isset($_SESSION['usuario_id']) || !tiene_permiso('inventario_historial', $pdo)) {
    header("Location: inventario_lista.php"); exit();
}

// Verificar permisos de EDICIÓN y ELIMINACIÓN
$puede_editar = tiene_permiso('inventario_historial_editar', $pdo);
$puede_eliminar = tiene_permiso('inventario_historial_eliminar', $pdo);

// LÓGICA DE ELIMINACIÓN (Solo si tiene permiso)
if (isset($_GET['delete']) && $puede_eliminar) {
    $id_del = $_GET['delete'];
    $stmtDel = $pdo->prepare("DELETE FROM historial_movimientos WHERE id_movimiento = ?");
    $stmtDel->execute([$id_del]);
    header("Location: inventario_movimientos.php?msg=eliminado"); exit();
}

// Filtros
$where = "1=1";
$params = [];

if (!empty($_GET['fecha_desde'])) { $where .= " AND DATE(h.fecha_movimiento) >= ?"; $params[] = $_GET['fecha_desde']; }
if (!empty($_GET['fecha_hasta'])) { $where .= " AND DATE(h.fecha_movimiento) <= ?"; $params[] = $_GET['fecha_hasta']; }
if (!empty($_GET['tipo_movimiento'])) { $where .= " AND h.tipo_movimiento LIKE ?"; $params[] = "%" . $_GET['tipo_movimiento'] . "%"; }
if (!empty($_GET['q'])) {
    $term = "%" . $_GET['q'] . "%";
    $where .= " AND (i.elemento LIKE ? OR u.nombre_completo LIKE ? OR h.observacion_movimiento LIKE ?)";
    $params[] = $term; $params[] = $term; $params[] = $term;
}

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
</head>
<body>
    <?php include 'navbar.php'; ?>
    <div class="container mt-4 mb-5">
        
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h3 class="mb-0"><i class="fas fa-history"></i> Historial de Movimientos</h3>
            <div>
                <?php if($puede_eliminar): ?>
                    <button class="btn btn-danger btn-sm" onclick="alert('Para vaciar todo, por seguridad hacelo registro por registro o pedí un script SQL si son muchos.')"><i class="fas fa-trash-alt me-2"></i> Info Limpieza</button>
                <?php endif; ?>
            </div>
        </div>
        
        <?php if(isset($_GET['msg']) && $_GET['msg']=='eliminado'): ?>
            <div class="alert alert-success py-2">Registro eliminado correctamente.</div>
        <?php endif; ?>

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
                            <?php foreach($hist as $h): ?>
                            <tr>
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
                                <td><?php echo htmlspecialchars($h['usuario'] ?? 'Sistema'); ?></td>
                                <td class="text-end">
                                    <a href="inventario_movimientos_pdf.php?id=<?php echo $h['id_movimiento']; ?>" target="_blank" class="btn btn-sm btn-outline-danger" title="Ver PDF">
                                        <i class="fas fa-file-pdf"></i>
                                    </a>

                                    <?php if($puede_editar): ?>
                                        <a href="inventario_movimientos_editar.php?id=<?php echo $h['id_movimiento']; ?>" class="btn btn-sm btn-outline-primary" title="Editar">
                                            <i class="fas fa-edit"></i>
                                        </a>
                                    <?php endif; ?>

                                    <?php if($puede_eliminar): ?>
                                        <a href="inventario_movimientos.php?delete=<?php echo $h['id_movimiento']; ?>" onclick="return confirm('¿Eliminar este registro del historial? (No deshace el movimiento del bien, solo borra el registro)')" class="btn btn-sm btn-outline-dark" title="Eliminar">
                                            <i class="fas fa-trash"></i>
                                        </a>
                                    <?php endif; ?>
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
    <script>$(document).ready(function(){ $('#tablaHist').DataTable({ order: [[0, 'desc']], language: { url: "//cdn.datatables.net/plug-ins/1.13.4/i18n/es-ES.json" } }); });</script>
</body>
</html>