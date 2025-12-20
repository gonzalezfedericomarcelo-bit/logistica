<?php
// Archivo: inventario_editar.php (100% BASE DE DATOS - SIN TEXTOS FIJOS)
session_start();
include 'conexion.php';
include 'funciones_permisos.php';

if (!isset($_SESSION['usuario_id']) || !tiene_permiso('inventario_editar', $pdo)) {
    header("Location: inventario_lista.php"); exit();
}

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$bien = $pdo->query("SELECT * FROM inventario_cargos WHERE id_cargo = $id")->fetch(PDO::FETCH_ASSOC);
if (!$bien) die("Bien no encontrado.");

// DATOS DINÁMICOS
$stmtDyn = $pdo->prepare("SELECT vd.id_valor, vd.valor, cd.etiqueta, cd.tipo_input FROM inventario_valores_dinamicos vd JOIN inventario_campos_dinamicos cd ON vd.id_campo = cd.id_campo WHERE vd.id_cargo = ? ORDER BY cd.orden ASC");
$stmtDyn->execute([$id]);
$valores_dinamicos = $stmtDyn->fetchAll(PDO::FETCH_ASSOC);

// LISTAS GENERALES
$destinos = $pdo->query("SELECT * FROM destinos_internos ORDER BY nombre ASC")->fetchAll(PDO::FETCH_ASSOC);
$estados = $pdo->query("SELECT * FROM inventario_estados ORDER BY nombre ASC")->fetchAll(PDO::FETCH_ASSOC);

// LISTAS MATAFUEGOS (SOLO DB)
try {
    $tipos_matafuegos = $pdo->query("SELECT * FROM inventario_config_matafuegos ORDER BY tipo_carga ASC")->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) { $tipos_matafuegos = []; }

try {
    $clases_fuego = $pdo->query("SELECT * FROM inventario_config_clases ORDER BY nombre ASC")->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) { $clases_fuego = []; }

// CAPACIDADES (HISTORIAL DB)
// Solo carga las que ya existen en la base de datos
try {
    $capacidades_db = $pdo->query("SELECT DISTINCT mat_capacidad FROM inventario_cargos WHERE mat_capacidad IS NOT NULL AND mat_capacidad != '' ORDER BY mat_capacidad ASC")->fetchAll(PDO::FETCH_COLUMN);
} catch (Exception $e) { $capacidades_db = []; }


// GUARDADO
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $dest_nombre = '';
    if(!empty($_POST['id_destino'])) {
        $r = $pdo->query("SELECT nombre FROM destinos_internos WHERE id_destino = " . $_POST['id_destino'])->fetch();
        $dest_nombre = $r ? $r['nombre'] : '';
    }
    
    // Convertir año a fecha
    $f_fab = (!empty($_POST['fecha_fabricacion']) && $_POST['fecha_fabricacion'] > 1900) ? $_POST['fecha_fabricacion'].'-01-01' : null;

    $sql = "UPDATE inventario_cargos SET 
            elemento=?, codigo_inventario=?, mat_numero_grabado=?, id_estado_fk=?, 
            destino_principal=?, servicio_ubicacion=?, nombre_responsable=?, observaciones=?, 
            mat_tipo_carga_id=?, mat_capacidad=?, mat_clase_id=?, mat_fecha_carga=?, mat_fecha_ph=?, 
            fecha_fabricacion=?, vida_util_limite=? WHERE id_cargo=?";
            
    $pdo->prepare($sql)->execute([
        $_POST['elemento'], $_POST['codigo_inventario'], $_POST['mat_numero_grabado'], $_POST['id_estado'],
        $dest_nombre, $_POST['nombre_area'] ?? '', $_POST['nombre_responsable'], $_POST['observaciones'],
        !empty($_POST['mat_tipo_carga_id']) ? $_POST['mat_tipo_carga_id'] : null,
        $_POST['mat_capacidad'], 
        !empty($_POST['mat_clase_id']) ? $_POST['mat_clase_id'] : null,
        !empty($_POST['mat_fecha_carga']) ? $_POST['mat_fecha_carga'] : null,
        !empty($_POST['mat_fecha_ph']) ? $_POST['mat_fecha_ph'] : null,
        $f_fab, $_POST['vida_util_limite'], $id
    ]);

    if (isset($_POST['dinamico'])) {
        $upd = $pdo->prepare("UPDATE inventario_valores_dinamicos SET valor = ? WHERE id_valor = ?");
        foreach ($_POST['dinamico'] as $vid => $val) $upd->execute([$val, $vid]);
    }
    header("Location: inventario_lista.php?msg=editado_ok"); exit();
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Editar Bien</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />
    <link href="https://cdn.jsdelivr.net/npm/select2-bootstrap-5-theme@1.3.0/dist/select2-bootstrap-5-theme.min.css" rel="stylesheet" />
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>
<body class="bg-light">
    <?php include 'navbar.php'; ?>
    <div class="container mt-4 mb-5">
        <form method="POST">
            <div class="card shadow border-0">
                <div class="card-header bg-primary text-white d-flex justify-content-between">
                    <h5 class="mb-0">Editando: <?php echo htmlspecialchars($bien['elemento']); ?></h5>
                    <a href="inventario_lista.php" class="btn btn-sm btn-light fw-bold">Volver</a>
                </div>
                <div class="card-body p-4">
                    
                    <div class="row g-3 mb-4">
                        <div class="col-md-6"><label class="fw-bold small">Elemento</label><input type="text" name="elemento" class="form-control" value="<?php echo htmlspecialchars($bien['elemento']); ?>" required></div>
                        <div class="col-md-3"><label class="fw-bold small">Código</label><input type="text" name="codigo_inventario" class="form-control" value="<?php echo htmlspecialchars($bien['codigo_inventario']); ?>"></div>
                        <div class="col-md-3"><label class="fw-bold small">N° Serie</label><input type="text" name="mat_numero_grabado" class="form-control" value="<?php echo htmlspecialchars($bien['mat_numero_grabado']); ?>"></div>
                    </div>

                    <div class="row g-3 mb-4">
                        <div class="col-md-4">
                            <label class="fw-bold small">Destino</label>
                            <select name="id_destino" id="id_destino" class="form-select select2" required>
                                <option value="">-- Seleccione --</option>
                                <?php foreach($destinos as $d): $sel = ($d['nombre'] == $bien['destino_principal']) ? 'selected' : ''; ?>
                                    <option value="<?php echo $d['id_destino']; ?>" <?php echo $sel; ?>><?php echo htmlspecialchars($d['nombre']); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-4">
                            <label class="fw-bold small">Área</label>
                            <select name="nombre_area" id="select_area" class="form-select select2">
                                <option value="<?php echo $bien['servicio_ubicacion']; ?>" selected><?php echo $bien['servicio_ubicacion']; ?></option>
                            </select>
                        </div>
                        <div class="col-md-4"><label class="fw-bold small">Responsable</label><input type="text" name="nombre_responsable" class="form-control" value="<?php echo htmlspecialchars($bien['nombre_responsable']); ?>"></div>
                    </div>

                    <?php if (count($valores_dinamicos) > 0): ?>
                        <div class="bg-white p-3 rounded border border-success mb-4">
                            <h6 class="text-success fw-bold border-bottom pb-2">Datos Específicos</h6>
                            <div class="row g-3">
                                <?php foreach ($valores_dinamicos as $din): 
                                    $lbl = mb_strtoupper($din['etiqueta']);
                                    if (strpos($lbl, 'AGENTE') !== false || strpos($lbl, 'CARGA') !== false || strpos($lbl, 'CLASE') !== false || strpos($lbl, 'CAPACIDAD') !== false || strpos($lbl, 'FABRICACION') !== false) continue;
                                ?>
                                    <div class="col-md-4">
                                        <label class="small fw-bold"><?php echo htmlspecialchars($din['etiqueta']); ?></label>
                                        <input type="text" name="dinamico[<?php echo $din['id_valor']; ?>]" class="form-control" value="<?php echo htmlspecialchars($din['valor']); ?>">
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    <?php endif; ?>

                    <div class="bg-white p-3 rounded border border-danger mb-4">
                        <h6 class="text-danger fw-bold border-bottom pb-2">Datos Extintor (Si aplica)</h6>
                        <div class="row g-3">
                            <div class="col-md-3">
                                <label class="small fw-bold">Agente</label>
                                <select name="mat_tipo_carga_id" class="form-select">
                                    <option value="">-- No --</option>
                                    <?php foreach($tipos_matafuegos as $tm): $sel = ($tm['id_config'] == $bien['mat_tipo_carga_id']) ? 'selected' : ''; ?>
                                        <option value="<?php echo $tm['id_config']; ?>" <?php echo $sel; ?>><?php echo htmlspecialchars($tm['tipo_carga']); ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-md-2">
                                <label class="small fw-bold">Capacidad</label>
                                <select name="mat_capacidad" class="form-select">
                                    <option value="">-- Kg --</option>
                                    <?php foreach($capacidades_db as $cap): $sel = ($cap == $bien['mat_capacidad']) ? 'selected' : ''; ?>
                                        <option value="<?php echo $cap; ?>" <?php echo $sel; ?>><?php echo $cap; ?> Kg</option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-md-2">
                                <label class="small fw-bold">Clase</label>
                                <select name="mat_clase_id" class="form-select">
                                    <option value="">--</option>
                                    <?php foreach($clases_fuego as $c): $sel = ($c['id_clase'] == $bien['mat_clase_id']) ? 'selected' : ''; ?>
                                        <option value="<?php echo $c['id_clase']; ?>" <?php echo $sel; ?>><?php echo htmlspecialchars($c['nombre']); ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-md-2">
                                <label class="small fw-bold">Año Fab.</label>
                                <?php 
                                    $anio_fab = '';
                                    if ($bien['fecha_fabricacion'] && $bien['fecha_fabricacion'] != '0000-00-00') {
                                        $anio_fab = date('Y', strtotime($bien['fecha_fabricacion']));
                                    }
                                ?>
                                <input type="number" name="fecha_fabricacion" id="fecha_fabricacion" class="form-control" placeholder="Ej: 2024" value="<?php echo $anio_fab; ?>">
                            </div>
                            <div class="col-md-3">
                                <label class="small fw-bold">Vida Útil</label>
                                <input type="text" id="vida_util_display" class="form-control bg-light fw-bold" readonly value="<?php echo htmlspecialchars($bien['vida_util_limite']); ?>">
                                <input type="hidden" name="vida_util_limite" id="vida_util_limite" value="<?php echo htmlspecialchars($bien['vida_util_limite']); ?>">
                            </div>
                            <div class="col-md-4"><label class="small fw-bold text-success">Venc. Carga</label><input type="date" name="mat_fecha_carga" class="form-control" value="<?php echo $bien['mat_fecha_carga']; ?>"></div>
                            <div class="col-md-4"><label class="small fw-bold text-primary">Venc. PH</label><input type="date" name="mat_fecha_ph" class="form-control" value="<?php echo $bien['mat_fecha_ph']; ?>"></div>
                        </div>
                    </div>

                    <div class="row g-3 mb-4">
                        <div class="col-md-4">
                            <label class="fw-bold small">Estado Actual</label>
                            <select name="id_estado" class="form-select">
                                <?php foreach($estados as $e): $sel = ($e['id_estado'] == $bien['id_estado_fk']) ? 'selected' : ''; ?>
                                    <option value="<?php echo $e['id_estado']; ?>" <?php echo $sel; ?>><?php echo htmlspecialchars($e['nombre']); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-8">
                            <label class="fw-bold small">Observaciones</label>
                            <input type="text" name="observaciones" class="form-control" value="<?php echo htmlspecialchars($bien['observaciones']); ?>">
                        </div>
                    </div>

                    <div class="d-grid"><button type="submit" class="btn btn-primary fw-bold">GUARDAR CAMBIOS</button></div>
                </div>
            </div>
        </form>
    </div>

    <script src="https://code.jquery.com/jquery-3.7.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>
    
    <script>
        $(document).ready(function() {
            $('.select2').select2({ theme: 'bootstrap-5' });

            // AUTO-CÁLCULO VIDA ÚTIL
            $('#fecha_fabricacion').on('input change', function() {
                let anio = parseInt($(this).val());
                if(anio > 1900) {
                    $('#vida_util_display').val((anio + 20) + ' (Vence)');
                    $('#vida_util_limite').val(anio + 20);
                }
            });

            // CARGA DE AREAS
            $('#id_destino').change(function() {
                let id = $(this).val();
                let $area = $('#select_area').empty().append('<option>Cargando...</option>').prop('disabled', true);
                $.getJSON('ajax_obtener_areas.php', { id_destino: id }, function(data) {
                    $area.empty().append('<option value="">Seleccione o Escriba</option>');
                    if(data.length) $.each(data, function(i, item) { $area.append(new Option(item.nombre, item.nombre)); });
                    $area.prop('disabled', false).select2({ theme: 'bootstrap-5', tags: true });
                });
            });
        });
    </script>
</body>
</html>