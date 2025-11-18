<?php
// Archivo: asistencia_listado_general.php - Historial de todos los partes
session_start();
include 'conexion.php';
include 'funciones_permisos.php'; 

// Seguridad: Solo autorizados
$u_rol = $_SESSION['usuario_rol'] ?? '';
$u_nombre = $_SESSION['usuario_nombre'] ?? '';
$es_autorizado = ($u_rol === 'admin' || stripos($u_nombre, 'Cañete') !== false || stripos($u_nombre, 'Ezequiel Paz') !== false || stripos($u_nombre, 'Federico') !== false);

if (!$es_autorizado) {
    header("Location: dashboard.php");
    exit();
}

// --- Lógica de Filtros ---
$fecha_inicio = $_POST['fecha_inicio'] ?? date('Y-m-d', strtotime('-30 days'));
$fecha_fin    = $_POST['fecha_fin'] ?? date('Y-m-d');
$filtro_mes   = $_POST['filtro_mes'] ?? '';

$partes = [];
$error = '';
$where_clause = " WHERE p.fecha BETWEEN :start AND :end ";
$params = [':start' => $fecha_inicio, ':end' => $fecha_fin];

// Lógica para filtro Mensual/Anual (sobreescribe el rango si se usa)
if (!empty($filtro_mes)) {
    $parts = explode('-', $filtro_mes); // YYYY-MM
    $year = $parts[0];
    $month = $parts[1];
    
    $fecha_inicio = date("Y-m-01", strtotime($filtro_mes . "-01"));
    $fecha_fin = date("Y-m-t", strtotime($filtro_mes . "-01")); // Último día del mes
    
    $where_clause = " WHERE YEAR(p.fecha) = :year AND MONTH(p.fecha) = :month ";
    $params = [':year' => $year, ':month' => $month];
}

try {
    $sql = "
        SELECT 
            p.id_parte,
            p.fecha,
            p.observaciones_generales,
            p.estado,
            u.nombre_completo as creador
        FROM asistencia_partes p
        JOIN usuarios u ON p.id_creador = u.id_usuario
        $where_clause
        ORDER BY p.fecha DESC, p.id_parte DESC
    ";

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $partes = $stmt->fetchAll(PDO::FETCH_ASSOC);

} catch (PDOException $e) {
    $error = "Error al cargar el listado general: " . $e->getMessage();
}

include 'navbar.php';

// Generar opciones de Mes/Año para el filtro
$meses_disponibles = [];
try {
    $sql_meses = "SELECT DISTINCT DATE_FORMAT(fecha, '%Y-%m') as mes_anio FROM asistencia_partes ORDER BY mes_anio DESC";
    $meses_disponibles = $pdo->query($sql_meses)->fetchAll(PDO::FETCH_COLUMN);
} catch (PDOException $e) { /* Ignorar si falla */ }

?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Historial de Partes</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">
</head>
<body>
    <div class="container mt-4 mb-5">
        <div class="card shadow">
            <div class="card-header bg-dark text-white">
                <h5 class="mb-0"><i class="fas fa-history me-2"></i> HISTORIAL Y LISTADO GENERAL DE PARTES</h5>
            </div>
            <div class="card-body">

                <?php if ($error): ?>
                    <div class="alert alert-danger"><?php echo $error; ?></div>
                <?php endif; ?>

                <!-- Formulario de Filtros RESPONSIVO -->
                <form method="POST" class="row g-3 align-items-end mb-4 bg-light p-3 rounded border">
                    <h6 class="small fw-bold">FILTROS DE BÚSQUEDA</h6>
                    
                    <div class="col-12 col-md-3">
                        <label for="filtro_mes" class="form-label small">Filtrar por Mes/Año</label>
                        <select name="filtro_mes" id="filtro_mes" class="form-select form-select-sm">
                            <option value="">-- Rango de Fecha Personalizado --</option>
                            <?php foreach ($meses_disponibles as $ma): 
                                $label = date("F Y", strtotime($ma . "-01")); 
                                $label_es = strftime("%B %Y", strtotime($ma . "-01")); // Necesita locale PHP en servidor
                                ?>
                                <option value="<?php echo $ma; ?>" <?php echo ($filtro_mes === $ma) ? 'selected' : ''; ?>>
                                    <?php echo ucfirst(strftime("%B %Y", strtotime($ma . "-01"))); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div class="col-12 col-md-3">
                        <label for="fecha_inicio" class="form-label small">Fecha Inicio</label>
                        <input type="date" name="fecha_inicio" id="fecha_inicio" value="<?php echo $fecha_inicio; ?>" class="form-control form-control-sm">
                    </div>
                    
                    <div class="col-12 col-md-3">
                        <label for="fecha_fin" class="form-label small">Fecha Fin</label>
                        <input type="date" name="fecha_fin" id="fecha_fin" value="<?php echo $fecha_fin; ?>" class="form-control form-control-sm">
                    </div>

                    <div class="col-12 col-md-3">
                        <button type="submit" class="btn btn-primary btn-sm w-100">
                            <i class="fas fa-search me-2"></i> Buscar Partes
                        </button>
                    </div>
                    <div class="col-12 text-muted small mt-2">
                        Mostrando partes desde el **<?php echo date('d/m/Y', strtotime($fecha_inicio)); ?>** hasta el **<?php echo date('d/m/Y', strtotime($fecha_fin)); ?>**.
                    </div>
                </form>

                <?php if (count($partes) > 0): ?>
                    <div class="table-responsive">
                        <table class="table table-striped table-hover align-middle">
                            <thead class="table-secondary">
                                <tr>
                                    <th>ID</th>
                                    <th>Fecha</th>
                                    <th>Creado Por</th>
                                    <th>Estado</th>
                                    <th>Observaciones</th>
                                    <th class="text-center">PDF</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($partes as $parte): ?>
                                <tr>
                                    <td class="fw-bold text-muted"><?php echo $parte['id_parte']; ?></td>
                                    <td class="fw-bold"><?php echo date('d/m/Y', strtotime($parte['fecha'])); ?></td>
                                    <td><?php echo htmlspecialchars($parte['creador']); ?></td>
                                    <td>
                                        <?php if ($parte['estado'] == 'aprobado'): ?>
                                            <span class="badge bg-success"><i class="fas fa-check"></i> Aprobado</span>
                                        <?php else: ?>
                                            <span class="badge bg-warning text-dark"><i class="fas fa-clock"></i> Pendiente</span>
                                        <?php endif; ?>
                                    </td>
                                    <td><?php echo htmlspecialchars($parte['observaciones_generales']); ?></td>
                                    <td class="text-center">
                                        <a href="asistencia_pdf.php?id=<?php echo $parte['id_parte']; ?>" target="_blank" class="btn btn-sm btn-danger">
                                            <i class="fas fa-file-pdf"></i>
                                        </a>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php else: ?>
                    <div class="alert alert-warning text-center">No se encontraron partes de novedades en el rango seleccionado.</div>
                <?php endif; ?>

            </div>
        </div>
    </div>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const selectMes = document.getElementById('filtro_mes');
            const inputInicio = document.getElementById('fecha_inicio');
            const inputFin = document.getElementById('fecha_fin');

            function toggleDateInputs() {
                const disabled = selectMes.value !== '';
                inputInicio.disabled = disabled;
                inputFin.disabled = disabled;
                
                // Si selecciona un mes, desactiva el rango personalizado
                if (disabled) {
                    inputInicio.value = '';
                    inputFin.value = '';
                }
            }

            selectMes.addEventListener('change', toggleDateInputs);
            
            // Inicializar al cargar
            toggleDateInputs();
        });
    </script>
</body>
</html>