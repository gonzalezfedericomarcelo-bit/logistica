<?php
// Archivo: asistencia_detalles_ausencias.php (ACTUALIZADO: FILTRO POR MOTIVO Y RANGO)
session_start();
include 'conexion.php';
include 'funciones_permisos.php'; 

// Capturar parámetros
$user_id = (int)($_GET['user_id'] ?? 0);
$filtro_motivo = $_GET['motivo'] ?? ''; // Nuevo parámetro desde el gráfico
$user_name = htmlspecialchars($_GET['name'] ?? 'Personal');
$fecha_inicio = $_GET['start'] ?? date('Y-m-d', strtotime('-15 days'));
$fecha_fin    = $_GET['end'] ?? date('Y-m-d');

$detalles = [];
$error = '';

try {
    // Construcción dinámica de la consulta
    $sql = "
        SELECT 
            p.id_parte,
            p.fecha,
            p.observaciones_generales,
            d.observacion_individual,
            u.nombre_completo,
            u.grado
        FROM asistencia_detalles d
        JOIN asistencia_partes p ON d.id_parte = p.id_parte
        JOIN usuarios u ON d.id_usuario = u.id_usuario
        WHERE d.presente = 0 
        AND p.fecha BETWEEN :start AND :end
    ";

    $params = [':start' => $fecha_inicio, ':end' => $fecha_fin];

    // Si viene ID de usuario, filtramos por usuario
    if ($user_id > 0) {
        $sql .= " AND d.id_usuario = :user_id ";
        $params[':user_id'] = $user_id;
    }

    // Si viene Motivo (desde el click del gráfico), filtramos por texto del motivo
    if (!empty($filtro_motivo)) {
        $sql .= " AND d.observacion_individual LIKE :motivo ";
        $params[':motivo'] = '%' . $filtro_motivo . '%';
        $titulo_pag = "Reporte de: " . strtoupper($filtro_motivo);
    } else {
        $titulo_pag = "Reporte de Ausencias: " . strtoupper($user_name);
    }

    $sql .= " ORDER BY p.fecha DESC";

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $detalles = $stmt->fetchAll(PDO::FETCH_ASSOC);

} catch (PDOException $e) {
    $error = "Error al cargar detalles: " . $e->getMessage();
}

include 'navbar.php';
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Detalle de Novedades</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">
</head>
<body>
    <div class="container mt-4 mb-5">
        <div class="card shadow border-danger">
            <div class="card-header bg-danger text-white d-flex justify-content-between align-items-center">
                <h5 class="mb-0"><i class="fas fa-folder-open me-2"></i> <?php echo $titulo_pag; ?></h5>
                <span class="badge bg-light text-danger"><?php echo count($detalles); ?> Registros</span>
            </div>
            <div class="card-body">
                
                <a href="asistencia_estadisticas.php" class="btn btn-sm btn-outline-secondary mb-3">
                    <i class="fas fa-arrow-left me-2"></i> Volver al Tablero General
                </a>
                
                <div class="alert alert-light border">
                    Rango de visualización: <strong><?php echo date('d/m/Y', strtotime($fecha_inicio)); ?></strong> al <strong><?php echo date('d/m/Y', strtotime($fecha_fin)); ?></strong>.
                </div>

                <?php if ($error): ?>
                    <div class="alert alert-danger"><?php echo $error; ?></div>
                <?php endif; ?>

                <?php if (count($detalles) > 0): ?>
                    <div class="table-responsive">
                        <table class="table table-striped table-hover align-middle">
                            <thead class="table-secondary">
                                <tr>
                                    <th>Fecha</th>
                                    <th>Grado y Nombre</th>
                                    <th>Motivo Registrado</th>
                                    <th>Observación General del Parte</th>
                                    <th class="text-center">Acción</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($detalles as $detalle): ?>
                                <tr>
                                    <td class="fw-bold text-nowrap"><?php echo date('d/m/Y', strtotime($detalle['fecha'])); ?></td>
                                    <td>
                                        <span class="badge bg-dark me-1"><?php echo htmlspecialchars($detalle['grado']); ?></span>
                                        <?php echo htmlspecialchars($detalle['nombre_completo']); ?>
                                    </td>
                                    <td class="text-danger fw-bold">
                                        <?php echo htmlspecialchars($detalle['observacion_individual']); ?>
                                    </td>
                                    <td class="small text-muted"><?php echo htmlspecialchars($detalle['observaciones_generales']); ?></td>
                                    <td class="text-center">
                                        <a href="asistencia_pdf.php?id=<?php echo $detalle['id_parte']; ?>" target="_blank" class="btn btn-sm btn-outline-danger" title="Ver PDF Original">
                                            <i class="fas fa-file-pdf"></i>
                                        </a>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php else: ?>
                    <div class="alert alert-success text-center">
                        <i class="fas fa-check-circle fa-2x mb-3 d-block"></i>
                        No se encontraron registros para este criterio en el rango seleccionado.
                    </div>
                <?php endif; ?>

            </div>
        </div>
    </div>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <?php include 'footer.php'; ?>
</body>
</html>