<?php
// Archivo: inventario_lista.php (CORREGIDO: Con estilos y estructura HTML completa)
session_start();
include 'conexion.php';
include 'funciones_permisos.php';

// Validar permiso
if (!isset($_SESSION['usuario_id']) || !tiene_permiso('acceso_inventario', $pdo)) {
    header("Location: dashboard.php");
    exit();
}

// --- LÓGICA DE FILTROS ---
$where = "1=1";
$params = [];

$busqueda = isset($_GET['q']) ? trim($_GET['q']) : '';
if (!empty($busqueda)) {
    $where .= " AND (elemento LIKE :q OR codigo_inventario LIKE :q OR nombre_responsable LIKE :q OR nombre_jefe_servicio LIKE :q)";
    $params[':q'] = "%$busqueda%";
}

$filtro_servicio = isset($_GET['servicio']) ? trim($_GET['servicio']) : '';
if (!empty($filtro_servicio)) {
    $where .= " AND servicio_ubicacion = :serv";
    $params[':serv'] = $filtro_servicio;
}

// Consultas
$sql_lista = "SELECT * FROM inventario_cargos WHERE $where ORDER BY fecha_creacion DESC";
$stmt = $pdo->prepare($sql_lista);
$stmt->execute($params);
$inventario = $stmt->fetchAll(PDO::FETCH_ASSOC);

// KPIs
$total_bienes = $pdo->query("SELECT COUNT(*) FROM inventario_cargos")->fetchColumn();
$mes_actual = date('Y-m');
$bienes_mes = $pdo->query("SELECT COUNT(*) FROM inventario_cargos WHERE DATE_FORMAT(fecha_creacion, '%Y-%m') = '$mes_actual'")->fetchColumn();
$servicios_db = $pdo->query("SELECT DISTINCT servicio_ubicacion FROM inventario_cargos ORDER BY servicio_ubicacion ASC")->fetchAll(PDO::FETCH_COLUMN);
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gestión de Inventario | Logística</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        body { background-color: #f8f9fa; }
        .avatar-circle { width: 30px; height: 30px; border-radius: 50%; display: flex; align-items: center; justify-content: center; font-weight: bold; font-size: 0.8rem; }
    </style>
</head>
<body>

    <?php include 'navbar.php'; ?>

    <div class="container-fluid px-4 mt-4 mb-5">
        
        <div class="d-flex justify-content-between align-items-center mb-4">
            <div>
                <h2 class="mb-0 fw-bold text-dark"><i class="fas fa-warehouse me-2 text-primary"></i> Gestión de Inventario</h2>
                <p class="text-muted small mb-0">Control de Cargos Patrimoniales y Asignaciones</p>
            </div>
            <div>
                <a href="inventario_nuevo.php" class="btn btn-primary shadow-sm">
                    <i class="fas fa-plus-circle me-2"></i> Nuevo Cargo
                </a>
            </div>
        </div>

        <div class="row g-3 mb-4">
            <div class="col-md-4">
                <div class="card border-0 shadow-sm border-start border-4 border-primary h-100">
                    <div class="card-body">
                        <div class="d-flex justify-content-between align-items-center">
                            <div>
                                <div class="text-uppercase fw-bold text-primary small mb-1">Total Bienes</div>
                                <div class="h3 mb-0 fw-bold"><?php echo $total_bienes; ?></div>
                            </div>
                            <div class="fs-1 text-primary opacity-25"><i class="fas fa-boxes"></i></div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-md-4">
                <div class="card border-0 shadow-sm border-start border-4 border-success h-100">
                    <div class="card-body">
                        <div class="d-flex justify-content-between align-items-center">
                            <div>
                                <div class="text-uppercase fw-bold text-success small mb-1">Altas del Mes</div>
                                <div class="h3 mb-0 fw-bold"><?php echo $bienes_mes; ?></div>
                            </div>
                            <div class="fs-1 text-success opacity-25"><i class="fas fa-calendar-check"></i></div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-md-4">
                <div class="card border-0 shadow-sm border-start border-4 border-info h-100">
                    <div class="card-body">
                        <div class="d-flex justify-content-between align-items-center">
                            <div>
                                <div class="text-uppercase fw-bold text-info small mb-1">Ubicaciones</div>
                                <div class="h3 mb-0 fw-bold"><?php echo count($servicios_db); ?></div>
                            </div>
                            <div class="fs-1 text-info opacity-25"><i class="fas fa-map-marker-alt"></i></div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="card shadow-sm mb-4">
            <div class="card-header bg-white py-3">
                <form method="GET" action="inventario_lista.php" class="row g-3 align-items-end">
                    <div class="col-md-4">
                        <label class="form-label fw-bold small text-muted">Buscar</label>
                        <div class="input-group">
                            <span class="input-group-text bg-light border-end-0"><i class="fas fa-search text-muted"></i></span>
                            <input type="text" name="q" class="form-control border-start-0" placeholder="Código, Elemento, Persona..." value="<?php echo htmlspecialchars($busqueda); ?>">
                        </div>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label fw-bold small text-muted">Filtrar Servicio</label>
                        <select name="servicio" class="form-select">
                            <option value="">-- Todos --</option>
                            <?php foreach ($servicios_db as $serv): ?>
                                <option value="<?php echo htmlspecialchars($serv); ?>" <?php echo ($filtro_servicio == $serv) ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($serv); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-2">
                        <button type="submit" class="btn btn-dark w-100"><i class="fas fa-filter me-2"></i> Filtrar</button>
                    </div>
                    <div class="col-md-3 text-end">
                        <?php if(!empty($busqueda) || !empty($filtro_servicio)): ?>
                            <a href="inventario_lista.php" class="btn btn-outline-secondary"><i class="fas fa-times me-2"></i> Limpiar</a>
                        <?php endif; ?>
                        <button type="button" onclick="window.print()" class="btn btn-outline-primary ms-1"><i class="fas fa-print"></i></button>
                    </div>
                </form>
            </div>
        </div>

        <div class="card shadow border-0">
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0">
                        <thead class="bg-light">
                            <tr>
                                <th class="ps-4">Fecha</th>
                                <th>Elemento</th>
                                <th>Ubicación</th>
                                <th>Responsable</th>
                                <th class="text-center">Acciones</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (count($inventario) > 0): ?>
                                <?php foreach ($inventario as $item): ?>
                                    <tr>
                                        <td class="ps-4 text-muted small"><?php echo date('d/m/Y', strtotime($item['fecha_creacion'])); ?></td>
                                        <td>
                                            <div class="fw-bold text-dark"><?php echo htmlspecialchars($item['elemento']); ?></div>
                                            <?php if($item['codigo_inventario']): ?>
                                                <small class="text-muted badge bg-light text-dark border"><?php echo htmlspecialchars($item['codigo_inventario']); ?></small>
                                            <?php endif; ?>
                                        </td>
                                        <td><span class="badge bg-info text-dark bg-opacity-10 border border-info"><?php echo htmlspecialchars($item['servicio_ubicacion']); ?></span></td>
                                        <td>
                                            <div class="d-flex align-items-center">
                                                <div class="avatar-circle bg-secondary text-white me-2">
                                                    <?php echo strtoupper(substr($item['nombre_responsable'], 0, 1)); ?>
                                                </div>
                                                <small><?php echo htmlspecialchars($item['nombre_responsable']); ?></small>
                                            </div>
                                        </td>
                                        <td class="text-center">
                                            <a href="inventario_pdf.php?id=<?php echo $item['id_cargo']; ?>" target="_blank" class="btn btn-sm btn-outline-danger" title="Ver Acta PDF">
                                                <i class="fas fa-file-pdf"></i>
                                            </a>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <tr><td colspan="5" class="text-center py-5 text-muted">Sin registros encontrados.</td></tr>
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