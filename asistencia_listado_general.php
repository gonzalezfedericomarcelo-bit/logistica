<?php
// Archivo: asistencia_listado_general.php (CORREGIDO: PERMISOS Y FLUJO APROBACION)
session_start();
include 'conexion.php';
include 'funciones_permisos.php'; 

// --- LÓGICA DE SEGURIDAD CORREGIDA ---
$u_rol = $_SESSION['usuario_rol'] ?? '';
$u_nombre = $_SESSION['usuario_nombre'] ?? '';
$u_id = $_SESSION['usuario_id'] ?? 0;

// Lista blanca de nombres permitidos para ver esta página
$acceso_permitido = ($u_rol === 'admin' || 
                     stripos($u_nombre, 'Cañete') !== false || 
                     stripos($u_nombre, 'Ezequiel Paz') !== false || 
                     stripos($u_nombre, 'Federico') !== false);

if (!$acceso_permitido) {
    header("Location: dashboard.php");
    exit();
}

// --- PROCESAR APROBACIÓN (POST) ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['accion']) && $_POST['accion'] === 'aprobar') {
    $id_parte_aprobar = $_POST['id_parte'];
    
    // Solo Cañete o Admin pueden aprobar
    if ($u_rol === 'admin' || stripos($u_nombre, 'Cañete') !== false) {
        try {
            $stmt = $pdo->prepare("UPDATE asistencia_partes SET estado = 'aprobado' WHERE id_parte = :id");
            $stmt->execute([':id' => $id_parte_aprobar]);
            // Redirigir a la misma página para evitar reenvío de form
            header("Location: asistencia_listado_general.php?msg=aprobado");
            exit();
        } catch (Exception $e) {
            $error = "Error al aprobar: " . $e->getMessage();
        }
    }
}

// Filtros
$fecha_inicio = $_GET['fecha_inicio'] ?? date('Y-m-d', strtotime('-30 days'));
$fecha_fin    = $_GET['fecha_fin'] ?? date('Y-m-d');
$filtro_mes   = $_GET['filtro_mes'] ?? '';

$where_clause = " WHERE p.fecha BETWEEN :start AND :end ";
$params = [':start' => $fecha_inicio, ':end' => $fecha_fin];

if (!empty($filtro_mes)) {
    $parts = explode('-', $filtro_mes);
    $fecha_inicio = date("Y-m-01", strtotime($filtro_mes . "-01"));
    $fecha_fin = date("Y-m-t", strtotime($filtro_mes . "-01"));
    $where_clause = " WHERE YEAR(p.fecha) = :year AND MONTH(p.fecha) = :month ";
    $params = [':year' => $parts[0], ':month' => $parts[1]];
}

// Consulta
$sql = "
    SELECT 
        p.id_parte,
        p.fecha,
        p.observaciones_generales,
        p.estado,
        p.id_creador,
        u.nombre_completo as creador,
        u.grado as grado_creador
    FROM asistencia_partes p
    JOIN usuarios u ON p.id_creador = u.id_usuario
    $where_clause
    ORDER BY p.fecha DESC, p.id_parte DESC
";
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$partes = $stmt->fetchAll(PDO::FETCH_ASSOC);

include 'navbar.php';
$meses_disponibles = $pdo->query("SELECT DISTINCT DATE_FORMAT(fecha, '%Y-%m') as mes_anio FROM asistencia_partes ORDER BY mes_anio DESC")->fetchAll(PDO::FETCH_COLUMN);
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
        
        <?php if (isset($_GET['msg']) && $_GET['msg'] == 'aprobado'): ?>
            <div class="alert alert-success alert-dismissible fade show">
                <i class="fas fa-check-circle me-2"></i> Parte aprobado correctamente.
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>

        <div class="card shadow">
            <div class="card-header bg-dark text-white">
                <h5 class="mb-0"><i class="fas fa-history me-2"></i> HISTORIAL DE PARTES</h5>
            </div>
            <div class="card-body">
                
                <!-- FILTROS -->
                <form method="GET" class="row g-3 align-items-end mb-4 bg-light p-3 rounded border">
                    <div class="col-12 col-md-3">
                        <label class="small">Mes</label>
                        <select name="filtro_mes" class="form-select form-select-sm">
                            <option value="">-- Rango --</option>
                            <?php foreach ($meses_disponibles as $ma) echo "<option value='$ma' ".($filtro_mes==$ma?'selected':'').">".date("F Y", strtotime($ma."-01"))."</option>"; ?>
                        </select>
                    </div>
                    <div class="col-6 col-md-3"><label class="small">Inicio</label><input type="date" name="fecha_inicio" value="<?php echo $fecha_inicio; ?>" class="form-control form-control-sm"></div>
                    <div class="col-6 col-md-3"><label class="small">Fin</label><input type="date" name="fecha_fin" value="<?php echo $fecha_fin; ?>" class="form-control form-control-sm"></div>
                    <div class="col-12 col-md-3"><button type="submit" class="btn btn-primary btn-sm w-100"><i class="fas fa-search"></i> Filtrar</button></div>
                </form>

                <div class="table-responsive">
                    <table class="table table-striped table-hover align-middle">
                        <thead class="table-secondary">
                            <tr>
                                <th>Fecha</th>
                                <th>Creado Por</th>
                                <th>Estado</th>
                                <th>Acciones</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($partes as $parte): 
                                $es_pendiente = ($parte['estado'] === 'pendiente');
                                $soy_aprobador = ($u_rol === 'admin' || stripos($u_nombre, 'Cañete') !== false);
                                $creador_soy_yo = ($parte['id_creador'] == $u_id);
                            ?>
                            <tr>
                                <td class="fw-bold"><?php echo date('d/m/Y', strtotime($parte['fecha'])); ?></td>
                                <td>
                                    <!-- AQUI SE MUESTRA EL GRADO + NOMBRE -->
                                    <span class="badge bg-light text-dark border"><?php echo htmlspecialchars($parte['grado_creador']); ?></span>
                                    <?php echo htmlspecialchars($parte['creador']); ?>
                                </td>
                                <td>
                                    <?php if ($parte['estado'] == 'aprobado'): ?>
                                        <span class="badge bg-success"><i class="fas fa-check"></i> Aprobado</span>
                                    <?php else: ?>
                                        <span class="badge bg-warning text-dark"><i class="fas fa-clock"></i> Pendiente</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <div class="d-flex gap-2">
                                        <!-- BOTÓN VER (Siempre visible, abre en nueva pestaña) -->
                                        <a href="asistencia_pdf.php?id=<?php echo $parte['id_parte']; ?>" target="_blank" class="btn btn-sm btn-outline-primary">
                                            <i class="fas fa-eye"></i> Ver
                                        </a>

                                        <!-- BOTÓN APROBAR (Solo visible si pendiente y soy Cañete/Admin) -->
                                        <?php if ($es_pendiente && $soy_aprobador && !$creador_soy_yo): ?>
                                            <form method="POST" onsubmit="return confirm('¿Aprobar y firmar este parte?');">
                                                <input type="hidden" name="accion" value="aprobar">
                                                <input type="hidden" name="id_parte" value="<?php echo $parte['id_parte']; ?>">
                                                <button type="submit" class="btn btn-sm btn-success">
                                                    <i class="fas fa-check-double"></i> Aprobar
                                                </button>
                                            </form>
                                        <?php endif; ?>
                                    </div>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>