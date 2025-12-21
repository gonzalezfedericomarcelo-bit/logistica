<?php
// Archivo: inventario_ver_categoria.php (COLUMNAS SEPARADAS: ID, IOSFA, PATRIMONIAL, SERIE)
error_reporting(E_ALL); ini_set('display_errors', 0); 
session_start();
include 'conexion.php'; include 'funciones_permisos.php';
if (!isset($_SESSION['usuario_id'])) header("Location: dashboard.php");

$id_tipo = $_GET['id'] ?? null;
$filtro_sql = ($id_tipo === 'todas') ? "1=1" : "i.id_tipo_bien = ?";
$params = ($id_tipo === 'todas') ? [] : [$id_tipo];

// Categoría
if ($id_tipo !== 'todas') {
    $cat = $pdo->prepare("SELECT * FROM inventario_tipos_bien WHERE id_tipo_bien=?");
    $cat->execute([$id_tipo]);
    $categoria = $cat->fetch(PDO::FETCH_ASSOC);
} else {
    $categoria = ['nombre'=>'Listado Completo', 'icono'=>'fas fa-list'];
}
$es_matafuego = (stripos($categoria['nombre'], 'matafuego') !== false);

// Stats
$stats = ['total' => 0, 'vencidos_carga' => 0];
$stmt = $pdo->prepare("SELECT COUNT(*) FROM inventario_cargos i WHERE $filtro_sql AND i.id_estado_fk != 0");
$stmt->execute($params);
$stats['total'] = $stmt->fetchColumn();

if ($es_matafuego) {
    $sqlVC = "SELECT COUNT(*) FROM inventario_cargos i WHERE $filtro_sql AND i.mat_fecha_carga < DATE_SUB(NOW(), INTERVAL 1 YEAR) AND i.id_estado_fk != 0";
    $stmt = $pdo->prepare($sqlVC); $stmt->execute($params);
    $stats['vencidos_carga'] = $stmt->fetchColumn();
}

// Listado
$sql = "SELECT i.*, e.nombre as nombre_estado, d.nombre as nombre_destino 
        FROM inventario_cargos i 
        LEFT JOIN inventario_estados e ON i.id_estado_fk = e.id_estado 
        LEFT JOIN destinos_internos d ON i.destino_principal = d.id_destino 
        WHERE $filtro_sql AND i.id_estado_fk != 0 ORDER BY i.id_cargo DESC";
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$bienes = $stmt->fetchAll(PDO::FETCH_ASSOC);

$f_estados = array_unique(array_column($bienes, 'nombre_estado'));
$f_destinos = array_unique(array_column($bienes, 'nombre_destino'));
sort($f_estados); sort($f_destinos);
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
    <style>
        .col-id { width: 50px; font-weight: bold; color: #555; }
        .badge-iosfa { background-color: #e0f2fe; color: #0369a1; border: 1px solid #bae6fd; }
        .badge-pat { background-color: #f3f4f6; color: #374151; border: 1px solid #d1d5db; }
        .badge-serie { background-color: #fef3c7; color: #92400e; border: 1px solid #fcd34d; }
    </style>
</head>
<body class="bg-light">
    <?php include 'navbar.php'; ?>
    <div class="container mt-4 mb-5">
        <div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
            <div>
                <a href="inventario_lista.php" class="text-decoration-none text-muted small"><i class="fas fa-arrow-left"></i> Volver</a>
                <h2 class="fw-bold text-primary mt-1"><i class="<?php echo $categoria['icono']; ?> me-2"></i><?php echo $categoria['nombre']; ?></h2>
            </div>
            <div>
                <button class="btn btn-warning fw-bold shadow-sm" onclick="submitMasivo('eliminar_seleccionados')">Borrar Selección</button>
                <button class="btn btn-danger fw-bold shadow-sm ms-1" onclick="confirmarVaciado()">Borrar Todo</button>
                <a href="inventario_nuevo.php" class="btn btn-success fw-bold shadow-sm ms-2">+ Nuevo</a>
            </div>
        </div>

        <div class="row g-4 mb-4">
            <div class="col-md-3">
                <div class="card bg-primary text-white h-100 p-3 shadow-sm border-0">
                    <div class="d-flex justify-content-between align-items-center">
                        <div><h6 class="opacity-75">Total Ítems</h6><h2 class="mb-0 fw-bold"><?php echo $stats['total']; ?></h2></div>
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

        <div class="card p-3 mb-3 border-0 shadow-sm">
            <div class="row g-2">
                <div class="col-md-4"><input type="text" id="search" class="form-control" placeholder="Buscar..."></div>
                <div class="col-md-3">
                    <select id="f_estado" class="form-select"><option value="">Estado: Todos</option>
                        <?php foreach($f_estados as $e) echo "<option value='$e'>$e</option>"; ?>
                    </select>
                </div>
                <div class="col-md-3">
                    <select id="f_destino" class="form-select"><option value="">Ubicación: Todas</option>
                        <?php foreach($f_destinos as $d) echo "<option value='$d'>$d</option>"; ?>
                    </select>
                </div>
                <div class="col-md-2"><button class="btn btn-outline-secondary w-100" onclick="limpiarFiltros()">Limpiar</button></div>
            </div>
        </div>

        <div class="card shadow-sm border-0">
            <div class="card-body p-0">
                <form id="formMasivo" method="POST" action="inventario_eliminar_masivo.php">
                    <input type="hidden" name="accion" id="accionMasiva">
                    <input type="hidden" name="id_tipo_bien" value="<?php echo $id_tipo; ?>">
                    
                    <div class="table-responsive">
                    <div class="table-responsive">
                        <table id="tablaBienes" class="table table-hover align-middle w-100 mb-0">
                            <thead class="bg-light text-secondary">
                                <tr>
                                    <th style="width: 40px;" class="text-center"><input type="checkbox" id="checkAll" class="form-check-input"></th>
                                    <th>ID</th>
                                    <th>Estado</th>
                                    <th>Elemento</th>
                                    <th>N° IOSFA</th>
                                    <th>N° Patrimonial</th>
                                    <th>N° Serie</th>
                                    <th>Ubicación</th>
                                    <th class="text-end pe-4" style="min-width: 180px;">Acciones</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach($bienes as $b): 
                                    // 1. Buscamos datos extra para el Modal
                                    $datos_extra = [];
                                    // 2. Buscamos el N° de SERIE (Fábrica) para mostrar en la columna
                                    $serie_vis = '-';
                                    try {
                                        $stmtDyn = $pdo->prepare("SELECT valor, etiqueta FROM inventario_valores_dinamicos v JOIN inventario_campos_dinamicos c ON v.id_campo = c.id_campo WHERE v.id_cargo = ?");
                                        $stmtDyn->execute([$b['id_cargo']]);
                                        $datos_extra = $stmtDyn->fetchAll(PDO::FETCH_ASSOC);
                                        
                                        foreach($datos_extra as $dx) {
                                            if(strpos(strtoupper($dx['etiqueta']), 'SERIE') !== false || strpos(strtoupper($dx['etiqueta']), 'FABRICA') !== false) {
                                                $serie_vis = $dx['valor'];
                                            }
                                        }
                                    } catch (Exception $e) {}
                                ?>
                                <tr>
                                    <td class="text-center"><input type="checkbox" name="ids[]" value="<?php echo $b['id_cargo']; ?>" class="form-check-input check-item"></td>
                                    
                                    <td class="fw-bold text-muted small">#<?php echo $b['id_cargo']; ?></td>

                                    <td><span class="badge bg-secondary"><?php echo htmlspecialchars($b['nombre_estado'] ?? 'S/E'); ?></span></td>
                                    
                                    <td>
                                        <div class="fw-bold text-dark"><?php echo htmlspecialchars($b['elemento'] ?? 'Sin Nombre'); ?></div>
                                        <?php if($b['observaciones']): ?>
                                            <small class="text-muted d-block text-truncate" style="max-width: 200px;"><i class="fas fa-comment-alt me-1"></i><?php echo htmlspecialchars($b['observaciones']); ?></small>
                                        <?php endif; ?>
                                    </td>

                                    <td>
                                        <?php if(!empty($b['n_iosfa']) && $b['n_iosfa']!='-'): ?>
                                            <span class="badge bg-primary bg-opacity-10 text-primary border border-primary"><?php echo htmlspecialchars($b['n_iosfa']); ?></span>
                                        <?php else: echo '-'; endif; ?>
                                    </td>
                                    <td>
                                        <?php if(!empty($b['codigo_patrimonial']) && $b['codigo_patrimonial']!='-'): ?>
                                            <span class="badge bg-light text-dark border"><?php echo htmlspecialchars($b['codigo_patrimonial']); ?></span>
                                        <?php else: echo '-'; endif; ?>
                                    </td>
                                    <td>
                                        <?php if($serie_vis != '-'): ?>
                                            <span class="badge bg-warning bg-opacity-10 text-dark border border-warning"><?php echo htmlspecialchars($serie_vis); ?></span>
                                        <?php else: echo '-'; endif; ?>
                                    </td>

                                    <td>
                                        <div class="fw-bold text-dark"><?php echo htmlspecialchars($b['nombre_destino'] ?? '-'); ?></div>
                                        <div class="small text-muted"><?php echo htmlspecialchars($b['servicio_ubicacion'] ?? ''); ?></div>
                                    </td>

                                    <td class="text-end pe-4">
                                        <div class="btn-group shadow-sm">
                                            <a href="inventario_transferir.php?id=<?= $b['id_cargo'] ?>" class="btn btn-sm btn-info text-white" title="Transferir" data-bs-toggle="tooltip">
                                                <i class="fas fa-exchange-alt"></i>
                                            </a>
                                            
                                            <button type="button" class="btn btn-sm btn-dark" data-bs-toggle="modal" data-bs-target="#modalDetalle<?php echo $b['id_cargo']; ?>" title="Ver Ficha">
                                                <i class="fas fa-eye"></i>
                                            </button>
                                            
                                            <a href="inventario_pdf.php?id=<?php echo $b['id_cargo']; ?>" target="_blank" class="btn btn-sm btn-outline-danger" title="PDF">
                                                <i class="fas fa-file-pdf"></i>
                                            </a>
                                            
                                            <a href="inventario_editar.php?id=<?php echo $b['id_cargo']; ?>" class="btn btn-sm btn-outline-primary" title="Editar">
                                                <i class="fas fa-edit"></i>
                                            </a>
                                            
                                            <a href="inventario_eliminar.php?id=<?php echo $b['id_cargo']; ?>" class="btn btn-sm btn-outline-danger" onclick="return confirm('¿Eliminar?');" title="Eliminar">
                                                <i class="fas fa-trash"></i>
                                            </a>
                                        </div>

                                        <div class="modal fade text-start" id="modalDetalle<?php echo $b['id_cargo']; ?>" tabindex="-1">
                                            <div class="modal-dialog modal-lg">
                                                <div class="modal-content">
                                                    <div class="modal-header bg-dark text-white">
                                                        <h5 class="modal-title"><i class="fas fa-info-circle me-2"></i>Ficha: <?php echo htmlspecialchars($b['elemento']); ?></h5>
                                                        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                                                    </div>
                                                    <div class="modal-body">
                                                        <div class="row g-3">
                                                            <div class="col-12 text-center mb-3">
                                                                <h4 class="fw-bold mb-0"><?php echo htmlspecialchars($b['elemento']); ?></h4>
                                                                <span class="badge bg-secondary"><?php echo htmlspecialchars($b['nombre_estado']); ?></span>
                                                            </div>
                                                            
                                                            <div class="col-md-4 border-end">
                                                                <label class="small text-muted fw-bold">N° IOSFA</label>
                                                                <div class="h5 text-primary"><?php echo $b['n_iosfa'] ? $b['n_iosfa'] : '-'; ?></div>
                                                            </div>
                                                            <div class="col-md-4 border-end text-center">
                                                                <label class="small text-muted fw-bold">N° PATRIMONIAL</label>
                                                                <div class="h5"><?php echo $b['codigo_patrimonial'] ? $b['codigo_patrimonial'] : '-'; ?></div>
                                                            </div>
                                                            <div class="col-md-4 text-center">
                                                                <label class="small text-muted fw-bold">N° SERIE</label>
                                                                <div class="h5"><?php echo $serie_vis; ?></div>
                                                            </div>
                                                            
                                                            <hr class="my-2">

                                                            <div class="col-md-6">
                                                                <h6 class="text-primary fw-bold mb-3"><i class="fas fa-map-marker-alt me-1"></i> Ubicación y Responsable</h6>
                                                                <p class="mb-1"><strong>Destino:</strong> <?php echo htmlspecialchars($b['nombre_destino']); ?></p>
                                                                <p class="mb-1"><strong>Área:</strong> <?php echo htmlspecialchars($b['servicio_ubicacion']); ?></p>
                                                                <p class="mb-1"><strong>Responsable:</strong> <?php echo htmlspecialchars($b['nombre_responsable']); ?></p>
                                                                <p class="mb-1"><strong>Jefe:</strong> <?php echo htmlspecialchars($b['nombre_jefe_servicio']); ?></p>
                                                            </div>
                                                            <div class="col-md-6">
                                                                <h6 class="text-primary fw-bold mb-3"><i class="fas fa-cogs me-1"></i> Especificaciones</h6>
                                                                <?php foreach($datos_extra as $d): ?>
                                                                    <div class="d-flex justify-content-between border-bottom py-1">
                                                                        <span class="small text-uppercase fw-bold"><?php echo htmlspecialchars($d['etiqueta']); ?></span>
                                                                        <span><?php echo htmlspecialchars($d['valor']); ?></span>
                                                                    </div>
                                                                <?php endforeach; ?>
                                                                
                                                                <?php if($es_matafuego): ?>
                                                                    <div class="d-flex justify-content-between border-bottom py-1 text-danger">
                                                                        <span class="small fw-bold">VENCE CARGA</span>
                                                                        <span><?php echo $b['mat_fecha_carga']; ?></span>
                                                                    </div>
                                                                <?php endif; ?>
                                                            </div>
                                                        </div>
                                                    </div>
                                                    <div class="modal-footer bg-light">
                                                        <a href="inventario_transferir.php?id=<?= $b['id_cargo'] ?>" class="btn btn-info text-white"><i class="fas fa-exchange-alt me-2"></i>Transferir</a>
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
                </form>
            </div>
        </div>
    </div>

    <div class="modal fade" id="modalVaciar" tabindex="-1"><div class="modal-dialog"><div class="modal-content border-danger"><div class="modal-header bg-danger text-white"><h5 class="modal-title">¿Borrar Todo?</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div><div class="modal-body text-center"><p>Se eliminarán TODOS los registros.</p></div><div class="modal-footer"><button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button><button type="button" class="btn btn-danger" onclick="ejecutarVaciado()">BORRAR</button></div></div></div></div>

    <script src="https://code.jquery.com/jquery-3.7.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.datatables.net/1.13.4/js/jquery.dataTables.min.js"></script>
    <script src="https://cdn.datatables.net/1.13.4/js/dataTables.bootstrap5.min.js"></script>
    <script>
        $(document).ready(function() {
            var t = $('#tabla').DataTable({ dom:'rtip', pageLength:25, order:[[1, "desc"]] }); // Ordena por ID
            $('#search').on('keyup', function(){ t.search(this.value).draw(); });
            $('#f_estado').on('change', function(){ t.column(2).search(this.value).draw(); });
            $('#f_destino').on('change', function(){ t.column(7).search(this.value).draw(); });
            $('#checkAll').click(function(){ $('.chk').prop('checked', this.checked); });
        });
        function limpiarFiltros() { $('#search').val(''); $('#f_estado').val(''); $('#f_destino').val(''); $('#tabla').DataTable().search('').columns().search('').draw(); }
        function submitMasivo(act) { if($('.chk:checked').length==0) return alert('Seleccione ítems'); if(confirm('¿Seguro?')) { $('#accionMasiva').val(act); $('#formMasivo').submit(); } }
        function confirmarVaciado() { new bootstrap.Modal(document.getElementById('modalVaciar')).show(); }
        function ejecutarVaciado() { $('#accionMasiva').val('vaciar_categoria'); $('#formMasivo').submit(); }
    </script>
</body>
</html>