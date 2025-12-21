<?php
// Archivo: inventario_editar.php (CON IOSFA Y ETIQUETAS CORRECTAS)
session_start();
include 'conexion.php';
include 'funciones_permisos.php';

if (!isset($_SESSION['usuario_id']) || !tiene_permiso('acceso_inventario', $pdo)) {
    header("Location: dashboard.php"); exit();
}

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

$stmt = $pdo->prepare("SELECT i.*, t.nombre as nombre_tipo FROM inventario_cargos i LEFT JOIN inventario_tipos_bien t ON i.id_tipo_bien = t.id_tipo_bien WHERE i.id_cargo = ?");
$stmt->execute([$id]);
$bien = $stmt->fetch(PDO::FETCH_ASSOC);
if (!$bien) die("Bien no encontrado.");

$es_matafuego = (stripos($bien['nombre_tipo'], 'matafuego') !== false);
$destinos = $pdo->query("SELECT * FROM destinos_internos ORDER BY nombre ASC")->fetchAll(PDO::FETCH_ASSOC);

$stmtEstados = $pdo->prepare("SELECT * FROM inventario_estados WHERE id_tipo_bien = ? OR id_tipo_bien IS NULL OR id_estado = ? ORDER BY nombre ASC");
$stmtEstados->execute([$bien['id_tipo_bien'], $bien['id_estado_fk']]);
$estados = $stmtEstados->fetchAll(PDO::FETCH_ASSOC);

$sqlDyn = "SELECT c.id_campo, c.etiqueta, c.tipo_input, v.valor FROM inventario_campos_dinamicos c LEFT JOIN inventario_valores_dinamicos v ON c.id_campo = v.id_campo AND v.id_cargo = ? WHERE c.id_tipo_bien = ? ORDER BY c.orden ASC";
$stmtDyn = $pdo->prepare($sqlDyn);
$stmtDyn->execute([$id, $bien['id_tipo_bien']]);
$campos_dinamicos = $stmtDyn->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Editar Bien | Inventario</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>
<body class="bg-light">
    <?php include 'navbar.php'; ?>

    <div class="container mt-4 mb-5">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h2><i class="fas fa-edit text-primary"></i> Editar: <?php echo htmlspecialchars($bien['elemento']); ?></h2>
            <a href="inventario_lista.php" class="btn btn-secondary"><i class="fas fa-arrow-left"></i> Volver</a>
        </div>

        <form action="inventario_guardar.php" method="POST" enctype="multipart/form-data">
            <input type="hidden" name="id_cargo" value="<?php echo $id; ?>">
            <input type="hidden" name="accion" value="editar"> 
            <input type="hidden" name="id_tipo_bien_seleccionado" value="<?php echo $bien['id_tipo_bien']; ?>">

            <div class="row g-4">
                <div class="col-md-6">
                    <div class="card shadow h-100">
                        <div class="card-header bg-dark text-white fw-bold">Datos Generales</div>
                        <div class="card-body">
                            <div class="mb-3">
                                <label class="form-label fw-bold">Nombre del Elemento / Bien</label>
                                <input type="text" name="elemento" class="form-control" value="<?php echo htmlspecialchars($bien['elemento']); ?>" required>
                            </div>
                            
                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <label class="form-label fw-bold small text-muted">N° CARGO PATRIMONIAL</label>
                                    <input type="text" name="codigo_inventario" class="form-control" value="<?php echo htmlspecialchars($bien['codigo_patrimonial']); ?>">
                                </div>
                                <div class="col-md-6 mb-3">
                                    <label class="form-label fw-bold small text-primary">N° IOSFA SISTEMAS</label>
                                    <input type="text" name="n_iosfa" class="form-control border-primary" value="<?php echo htmlspecialchars($bien['n_iosfa']); ?>">
                                </div>
                            </div>

                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <label class="form-label fw-bold">Estado</label>
                                    <select name="id_estado" class="form-select">
                                        <?php foreach($estados as $e): ?>
                                            <option value="<?php echo $e['id_estado']; ?>" <?php if($bien['id_estado_fk'] == $e['id_estado']) echo 'selected'; ?>>
                                                <?php echo htmlspecialchars($e['nombre']); ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <div class="col-md-6 mb-3">
                                    <label class="form-label fw-bold">Ubicación Principal</label>
                                    <select name="id_destino" class="form-select">
                                        <?php foreach($destinos as $d): ?>
                                            <option value="<?php echo $d['id_destino']; ?>" <?php if($bien['destino_principal'] == $d['id_destino']) echo 'selected'; ?>>
                                                <?php echo htmlspecialchars($d['nombre']); ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                            </div>

                            <div class="mb-3">
                                <label class="form-label fw-bold">Area / Servicio (Detalle)</label>
                                <input type="text" name="servicio_ubicacion" class="form-control" value="<?php echo htmlspecialchars($bien['servicio_ubicacion']); ?>">
                            </div>

                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <label class="form-label">Responsable</label>
                                    <input type="text" name="nombre_responsable" class="form-control" value="<?php echo htmlspecialchars($bien['nombre_responsable']); ?>">
                                </div>
                                <div class="col-md-6 mb-3">
                                    <label class="form-label">Jefe Servicio</label>
                                    <input type="text" name="nombre_jefe_servicio" class="form-control" value="<?php echo htmlspecialchars($bien['nombre_jefe_servicio']); ?>">
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="col-md-6">
                    <div class="card shadow h-100">
                        <div class="card-header bg-primary text-white fw-bold">
                            Ficha Técnica: <?php echo htmlspecialchars($bien['nombre_tipo'] ?? 'General'); ?>
                        </div>
                        <div class="card-body">
                            
                            <?php if(!empty($campos_dinamicos)): ?>
                                <h6 class="text-muted text-uppercase mb-3 small fw-bold border-bottom pb-1">Especificaciones Específicas</h6>
                                <?php foreach($campos_dinamicos as $cd): 
                                    // Ocultamos los redundantes
                                    $label = strtoupper($cd['etiqueta']);
                                    if(strpos($label, 'IOSFA') !== false || strpos($label, 'PATRIMONIAL') !== false) continue;
                                ?>
                                    <div class="mb-3">
                                        <label class="form-label fw-bold small"><?php echo htmlspecialchars($cd['etiqueta']); ?></label>
                                        <input type="text" name="dinamico[<?php echo $cd['id_campo']; ?>]" class="form-control" value="<?php echo htmlspecialchars($cd['valor'] ?? ''); ?>">
                                    </div>
                                <?php endforeach; ?>
                            <?php endif; ?>

                            <?php if ($es_matafuego): ?>
                                <div class="alert alert-danger mt-4">
                                    <i class="fas fa-fire-extinguisher me-2"></i><strong>Datos de Matafuegos</strong>
                                </div>
                                <div class="row">
                                    <div class="col-md-6 mb-3">
                                        <label class="form-label small">Capacidad (Kg)</label>
                                        <input type="text" name="mat_capacidad" class="form-control" value="<?php echo $bien['mat_capacidad']; ?>">
                                    </div>
                                    <div class="col-md-6 mb-3">
                                        <label class="form-label small">N° Grabado</label>
                                        <input type="text" name="mat_numero_grabado" class="form-control" value="<?php echo $bien['mat_numero_grabado']; ?>">
                                    </div>
                                    <div class="col-md-6 mb-3">
                                        <label class="form-label small">Fecha Carga</label>
                                        <input type="date" name="mat_fecha_carga" class="form-control" value="<?php echo $bien['mat_fecha_carga']; ?>">
                                    </div>
                                    <div class="col-md-6 mb-3">
                                        <label class="form-label small">Fecha PH</label>
                                        <input type="date" name="mat_fecha_ph" class="form-control" value="<?php echo $bien['mat_fecha_ph']; ?>">
                                    </div>
                                </div>
                            <?php endif; ?>
                            
                            <div class="mt-4">
                                <label class="form-label fw-bold">Observaciones Generales</label>
                                <textarea name="observaciones" class="form-control" rows="3"><?php echo htmlspecialchars($bien['observaciones']); ?></textarea>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="row mt-4 mb-5">
                <div class="col-12 text-center">
                    <button type="submit" class="btn btn-success btn-lg px-5 shadow"><i class="fas fa-save me-2"></i>Guardar Cambios</button>
                </div>
            </div>
        </form>
    </div>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>