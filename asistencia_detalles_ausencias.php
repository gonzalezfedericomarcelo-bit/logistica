<?php
// Archivo: asistencia_detalles_ausencias.php - Muestra los partes donde una persona faltó
session_start();
include 'conexion.php';
include 'funciones_permisos.php'; 

$user_id = (int)($_GET['user_id'] ?? 0);
$user_name = htmlspecialchars($_GET['name'] ?? 'Personal');
$fecha_inicio = $_GET['start'] ?? date('Y-m-d', strtotime('-15 days'));
$fecha_fin    = $_GET['end'] ?? date('Y-m-d');

$detalles = [];
$error = '';

if ($user_id > 0) {
    try {
        $sql = "
            SELECT 
                p.id_parte,
                p.fecha,
                p.observaciones_generales,
                d.observacion_individual
            FROM asistencia_detalles d
            JOIN asistencia_partes p ON d.id_parte = p.id_parte
            WHERE d.id_usuario = :user_id 
            AND d.presente = 0 -- SOLO AUSENCIAS
            AND p.fecha BETWEEN :start AND :end
            ORDER BY p.fecha DESC
        ";

        $stmt = $pdo->prepare($sql);
        $stmt->execute([
            ':user_id' => $user_id,
            ':start' => $fecha_inicio,
            ':end' => $fecha_fin
        ]);
        $detalles = $stmt->fetchAll(PDO::FETCH_ASSOC);

    } catch (PDOException $e) {
        $error = "Error al cargar detalles de partes: " . $e->getMessage();
    }
}

include 'navbar.php';
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Detalle de Ausencias - <?php echo $user_name; ?></title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">
</head>
<body>
    <div class="container mt-4 mb-5">
        <div class="card shadow">
            <div class="card-header bg-danger text-white">
                <h5 class="mb-0"><i class="fas fa-exclamation-triangle me-2"></i> REGISTRO DE FALTAS: <?php echo strtoupper($user_name); ?></h5>
            </div>
            <div class="card-body">
                
                <a href="asistencia_estadisticas.php" class="btn btn-sm btn-outline-secondary mb-3">
                    <i class="fas fa-arrow-left me-2"></i> Volver al Reporte General
                </a>
                
                <div class="alert alert-info">
                    Mostrando **<?php echo count($detalles); ?>** ausencias registradas entre el **<?php echo date('d/m/Y', strtotime($fecha_inicio)); ?>** y el **<?php echo date('d/m/Y', strtotime($fecha_fin)); ?>**.
                </div>

                <?php if ($error): ?>
                    <div class="alert alert-danger"><?php echo $error; ?></div>
                <?php endif; ?>

                <?php if (count($detalles) > 0): ?>
                    <div class="table-responsive">
                        <table class="table table-striped table-hover align-middle">
                            <thead class="table-danger">
                                <tr>
                                    <th>Fecha</th>
                                    <th>Motivo Individual</th>
                                    <th>Observación General del Parte</th>
                                    <th>Ver Parte Completo</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($detalles as $detalle): ?>
                                <tr>
                                    <td class="fw-bold"><?php echo date('d/m/Y', strtotime($detalle['fecha'])); ?></td>
                                    <td class="text-danger fw-semibold"><?php echo htmlspecialchars($detalle['observacion_individual']); ?></td>
                                    <td><?php echo htmlspecialchars($detalle['observaciones_generales']); ?></td>
                                    <td>
                                        <a href="asistencia_pdf.php?id=<?php echo $detalle['id_parte']; ?>" target="_blank" class="btn btn-sm btn-outline-danger">
                                            <i class="fas fa-file-pdf"></i> Ver Parte #<?php echo $detalle['id_parte']; ?>
                                        </a>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php else: ?>
                    <div class="alert alert-success text-center">¡No se encontraron faltas de **<?php echo $user_name; ?>** en este rango!</div>
                <?php endif; ?>

            </div>
        </div>
    </div>
</body>
</html>