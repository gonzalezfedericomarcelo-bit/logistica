<?php
// Archivo: inventario_ver_categoria.php (CORREGIDO: Sin error de columna color)
error_reporting(E_ALL);
ini_set('display_errors', 0); 

session_start();
include 'conexion.php';
include 'funciones_permisos.php';

if (!isset($_SESSION['usuario_id']) || !tiene_permiso('acceso_inventario', $pdo)) {
    header("Location: dashboard.php"); exit();
}

// 1. OBTENER CATEGORÍA
$id_tipo = $_GET['id'] ?? null;
$es_todo = ($id_tipo === 'todas');

if ($es_todo) {
    $categoria = ['nombre' => 'Listado Completo', 'icono' => 'fas fa-list', 'id_tipo_bien' => 'todas'];
    $filtro_sql = "1=1"; 
    $params = [];
} else {
    $stmt = $pdo->prepare("SELECT * FROM inventario_tipos_bien WHERE id_tipo_bien = ?");
    $stmt->execute([$id_tipo]);
    $categoria = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$categoria) die("Categoría no encontrada.");
    $filtro_sql = "i.id_tipo_bien = ?";
    $params = [$id_tipo];
}
$es_matafuego = (stripos($categoria['nombre'], 'matafuego') !== false);

// 2. ESTADÍSTICAS
$stats = ['total' => 0, 'vencidos_carga' => 0];
try {
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM inventario_cargos i WHERE $filtro_sql AND i.id_estado_fk != 0");
    $stmt->execute($params);
    $stats['total'] = $stmt->fetchColumn();

    if ($es_matafuego) {
        $sqlVC = "SELECT COUNT(*) FROM inventario_cargos i WHERE $filtro_sql AND i.mat_fecha_carga < DATE_SUB(NOW(), INTERVAL 1 YEAR) AND i.id_estado_fk != 0";
        $stmt = $pdo->prepare($sqlVC); $stmt->execute($params);
        $stats['vencidos_carga'] = $stmt->fetchColumn();
    }
} catch (Exception $e) {}

// 3. OBTENER LISTADO (SIN PEDIR COLOR A LA BD)
$sqlBienes = "SELECT i.*, 
              COALESCE(e.nombre, 'Sin Estado') as nombre_estado, 
              COALESCE(d.nombre, 'Sin Ubicación') as nombre_destino
              FROM inventario_cargos i
              LEFT JOIN inventario_estados e ON i.id_estado_fk = e.id_estado
              LEFT JOIN destinos_internos d ON i.destino_principal = d.id_destino
              WHERE $filtro_sql AND i.id_estado_fk != 0
              ORDER BY i.id_cargo DESC";
$stmt = $pdo->prepare($sqlBienes);
$stmt->execute($params);
$bienes = $stmt->fetchAll(PDO::FETCH_ASSOC);

?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($categoria['nombre']); ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="https://cdn.datatables.net/1.13.4/css/dataTables.bootstrap5.min.css">
</head>
<body class="bg-light">
    <?php include 'navbar.php'; ?>

    <div class="container mt-4 mb-5">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <div>
                <a href="inventario_lista.php" class="text-decoration-none text-muted small"><i class="fas fa-arrow-left"></i> Volver</a>
                <h2 class="fw-bold mt-1">
                    <i class="<?php echo $categoria['icono'] ?? 'fas fa-box'; ?> me-2 text-primary"></i>
                    <?php echo htmlspecialchars($categoria['nombre']); ?>
                </h2>
            </div>
            <a href="inventario_nuevo.php" class="btn btn-success shadow-sm"><i class="fas fa-plus"></i> Nuevo</a>
        </div>

        <div class="row g-4 mb-4">
            <div class="col-md-3">
                <div class="card bg-primary text-white h-100 p-3 shadow-sm border-0">
                    <div class="d-flex justify-content-between align-items-center">
                        <div><h6 class="opacity-75">Total Items</h6><h2 class="mb-0 fw-bold"><?php echo $stats['total']; ?></h2></div>
                        <i class="fas fa-cubes fa-2x opacity-50"></i>
                    </div>
                </div>
            </div>
            <?php if($es_matafuego): ?>
            <div class="col-md-3">
                <div class="card bg-danger text-white h-100 p-3 shadow-sm border-0">
                    <div class="d-flex justify-content-between align-items-center">
                        <div><h6 class="opacity-75">Vencidos</h6><h2 class="mb-0 fw-bold"><?php echo $stats['vencidos_carga']; ?></h2></div>
                        <i class="fas fa-fire-extinguisher fa-2x opacity-50"></i>
                    </div>
                </div>
            </div>
            <?php endif; ?>
        </div>

        <div class="card shadow border-0">
            <div class="card-body">
                <div class="table-responsive">
                    <table id="tablaBienes" class="table table-hover align-middle w-100">
                        <thead class="bg-light">
                            <tr>
                                <th>Estado</th>
                                <th>Elemento</th>
                                <th>Código</th>
                                <th>Ubicación</th>
                                <th class="text-end">Acciones</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach($bienes as $b): 
                                // Definimos un color por defecto ya que no existe en BD
                                $color_estado = '#6c757d'; 
                                
                                // Recuperar datos dinámicos (Protegido con try/catch)
                                $datos_extra = [];
                                try {
                                    $stmtDyn = $pdo->prepare("SELECT valor, etiqueta FROM inventario_valores_dinamicos v JOIN inventario_campos_dinamicos c ON v.id_campo = c.id_campo WHERE v.id_cargo = ?");
                                    $stmtDyn->execute([$b['id_cargo']]);
                                    $datos_extra = $stmtDyn->fetchAll(PDO::FETCH_ASSOC);
                                } catch (Exception $e) { /* Nada */ }
                            ?>
                            <tr>
                                <td><span class="badge" style="background-color:<?php echo $color_estado; ?>"><?php echo htmlspecialchars($b['nombre_estado'] ?? 'S/E'); ?></span></td>
                                <td>
                                    <div class="fw-bold"><?php echo htmlspecialchars($b['elemento'] ?? 'Sin Nombre'); ?></div>
                                    <small class="text-muted"><?php echo htmlspecialchars($b['observaciones'] ?? ''); ?></small>
                                </td>
                                <td><span class="badge bg-light text-dark border"><?php echo htmlspecialchars($b['codigo_patrimonial'] ?? 'S/N'); ?></span></td>
                                <td><?php echo htmlspecialchars($b['nombre_destino'] ?? '-'); ?></td>
                                <td class="text-end">
                                    <div class="btn-group">
                                        <button type="button" class="btn btn-sm btn-info text-white" data-bs-toggle="modal" data-bs-target="#modalDetalle<?php echo $b['id_cargo']; ?>" title="Ver Ficha Completa">
                                            <i class="fas fa-eye"></i>
                                        </button>
                                        <a href="inventario_pdf.php?id=<?php echo $b['id_cargo']; ?>" target="_blank" class="btn btn-sm btn-outline-danger" title="Descargar PDF">
                                            <i class="fas fa-file-pdf"></i>
                                        </a>
                                        
                                        <a href="inventario_editar.php?id=<?php echo $b['id_cargo']; ?>" class="btn btn-sm btn-outline-primary" title="Editar"><i class="fas fa-edit"></i></a>
                                        <a href="inventario_eliminar.php?id=<?php echo $b['id_cargo']; ?>" class="btn btn-sm btn-outline-danger" onclick="return confirm('¿Eliminar?');"><i class="fas fa-trash"></i></a>
                                    </div>

                                    <div class="modal fade text-start" id="modalDetalle<?php echo $b['id_cargo']; ?>" tabindex="-1">
                                        <div class="modal-dialog modal-lg">
                                            <div class="modal-content">
                                                <div class="modal-header bg-dark text-white">
                                                    <h5 class="modal-title"><i class="fas fa-info-circle me-2"></i>Ficha: <?php echo htmlspecialchars($b['elemento'] ?? ''); ?></h5>
                                                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                                                </div>
                                                <div class="modal-body">
    <div class="row">
        <div class="col-md-6 mb-3">
            <label class="fw-bold text-muted small">CÓDIGO PATRIMONIAL</label>
            <div class="h5 text-dark"><?php echo htmlspecialchars($b['codigo_patrimonial'] ?? 'S/N'); ?></div>
        </div>
        <div class="col-md-6 mb-3">
            <label class="fw-bold text-muted small">ESTADO</label>
            <div><span class="badge" style="background-color:<?php echo $b['color_estado'] ?? '#666'; ?>"><?php echo htmlspecialchars($b['nombre_estado'] ?? 'N/A'); ?></span></div>
        </div>
        <div class="col-md-6 mb-3">
            <label class="fw-bold text-muted small">UBICACIÓN</label>
            <div class="text-dark fw-bold"><?php echo htmlspecialchars($b['nombre_destino'] ?? '-'); ?></div>
            <div class="small text-muted"><?php echo htmlspecialchars($b['servicio_ubicacion'] ?? '-'); ?></div>
        </div>
        <div class="col-md-6 mb-3">
            <label class="fw-bold text-muted small">RESPONSABLE</label>
            <div class="text-dark"><?php echo htmlspecialchars($b['nombre_responsable'] ?? '-'); ?></div>
        </div>
    </div>

    <hr>
    <h6 class="text-primary fw-bold"><i class="fas fa-cogs me-1"></i> Especificaciones Técnicas</h6>
    <div class="row mt-3">
        
        <?php foreach($datos_extra as $d): ?>
            <div class="col-md-6 mb-3">
                <label class="fw-bold text-muted small text-uppercase"><?php echo htmlspecialchars($d['etiqueta']); ?></label>
                <div class="border-bottom pb-1"><?php echo htmlspecialchars($d['valor']); ?></div>
            </div>
        <?php endforeach; ?>

        <?php if($es_matafuego): ?>
            <div class="col-md-4 mb-3">
                <label class="fw-bold text-muted small">CAPACIDAD</label>
                <div class="border-bottom pb-1"><?php echo $b['mat_capacidad'] ? $b['mat_capacidad'].' Kg' : '-'; ?></div>
            </div>
            <div class="col-md-4 mb-3">
                <label class="fw-bold text-muted small">N° GRABADO / SERIE</label>
                <div class="border-bottom pb-1"><?php echo $b['mat_numero_grabado'] ? $b['mat_numero_grabado'] : '-'; ?></div>
            </div>
            <div class="col-md-4 mb-3">
                <label class="fw-bold text-muted small">AÑO FABRICACIÓN</label>
                <div class="border-bottom pb-1"><?php echo $b['fecha_fabricacion'] ? date('Y', strtotime($b['fecha_fabricacion'])) : '-'; ?></div>
            </div>
            <div class="col-md-6 mb-3">
                <label class="fw-bold text-muted small text-danger">VENCIMIENTO CARGA</label>
                <div class="h5 text-danger fw-bold">
                    <?php echo $b['mat_fecha_carga'] ? date('d/m/Y', strtotime($b['mat_fecha_carga'].' +1 year')) : '-'; ?>
                </div>
            </div>
            <div class="col-md-6 mb-3">
                <label class="fw-bold text-muted small text-danger">VENCIMIENTO PH</label>
                <div class="h5 text-dark">
                    <?php echo $b['mat_fecha_ph'] ? date('d/m/Y', strtotime($b['mat_fecha_ph'].' +5 years')) : '-'; ?>
                </div>
            </div>
        <?php endif; ?>

        <?php if(empty($datos_extra) && !$es_matafuego): ?>
            <div class="col-12 text-center text-muted py-3">
                <i class="fas fa-info-circle me-1"></i> No hay especificaciones técnicas adicionales cargadas para este ítem.
            </div>
        <?php endif; ?>
    </div>
    
    <?php if(!empty($b['observaciones'])): ?>
    <div class="mt-3 p-2 bg-light rounded border">
        <label class="fw-bold small text-muted">OBSERVACIONES</label>
        <div class="fst-italic"><?php echo nl2br(htmlspecialchars($b['observaciones'])); ?></div>
    </div>
    <?php endif; ?>
</div>
                                                <div class="modal-footer bg-light">
                                                    <a href="inventario_pdf.php?id=<?php echo $b['id_cargo']; ?>" target="_blank" class="btn btn-danger">
                                                        <i class="fas fa-file-pdf me-2"></i>Descargar PDF
                                                    </a>
                                                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cerrar</button>
                                                </div>
                                            </div>
                                        </div>
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

    <script src="https://code.jquery.com/jquery-3.7.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.datatables.net/1.13.4/js/jquery.dataTables.min.js"></script>
    <script src="https://cdn.datatables.net/1.13.4/js/dataTables.bootstrap5.min.js"></script>
    <script> $(document).ready(function() { $('#tablaBienes').DataTable({ language: { url: "//cdn.datatables.net/plug-ins/1.13.4/i18n/es-ES.json" } }); }); </script>
</body>
</html>