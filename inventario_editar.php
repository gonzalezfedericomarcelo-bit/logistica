<?php
// Archivo: inventario_editar.php
session_start();
include 'conexion.php';
include 'funciones_permisos.php';

if (!isset($_SESSION['usuario_id']) || !tiene_permiso('acceso_inventario', $pdo)) {
    header("Location: dashboard.php"); exit();
}

$id = $_GET['id'] ?? 0;
$stmt = $pdo->prepare("SELECT * FROM inventario_cargos WHERE id_cargo = ?");
$stmt->execute([$id]);
$bien = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$bien) die("Bien no encontrado");

// Listas
$lista_destinos = $pdo->query("SELECT id_destino, nombre FROM destinos_internos ORDER BY nombre ASC")->fetchAll(PDO::FETCH_ASSOC);
$estados_db = $pdo->query("SELECT * FROM inventario_estados")->fetchAll(PDO::FETCH_ASSOC);
$tipos_matafuegos = $pdo->query("SELECT * FROM inventario_config_matafuegos")->fetchAll(PDO::FETCH_ASSOC);
$clases_fuego = $pdo->query("SELECT * FROM inventario_config_clases")->fetchAll(PDO::FETCH_ASSOC);

// Procesar Guardado
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    // Manejo de Archivos (solo si suben nuevos)
    function actualizarArchivo($input, $actual) {
        if (isset($_FILES[$input]) && $_FILES[$input]['error'] === UPLOAD_ERR_OK) {
            $ext = pathinfo($_FILES[$input]['name'], PATHINFO_EXTENSION);
            $nombre = 'upd_' . time() . '_' . uniqid() . '.' . $ext;
            move_uploaded_file($_FILES[$input]['tmp_name'], 'uploads/documentacion/' . $nombre);
            return $nombre;
        }
        return $actual;
    }
    
    $arch_remito = actualizarArchivo('archivo_remito', $bien['archivo_remito']);
    $arch_comp = actualizarArchivo('archivo_comprobante', $bien['archivo_comprobante']);

    $sql = "UPDATE inventario_cargos SET 
            id_estado_fk = :idest, elemento = :elem, codigo_inventario = :cod, servicio_ubicacion = :serv,
            observaciones = :obs, complementos = :comp, nombre_tecnico = :ntec,
            archivo_remito = :arem, archivo_comprobante = :acomp,
            mat_tipo_carga_id = :mtipo, mat_capacidad = :mcap, mat_clase_id = :mclase,
            mat_fecha_carga = :mvc, mat_fecha_ph = :mvph, fecha_fabricacion = :mfab, vida_util_limite = :mvida,
            nombre_responsable = :nresp, nombre_jefe_servicio = :njefe
            WHERE id_cargo = :id";
    
    $pdo->prepare($sql)->execute([
        ':idest' => $_POST['id_estado'],
        ':elem' => $_POST['elemento'],
        ':cod' => $_POST['codigo_inventario'],
        ':serv' => $_POST['servicio_ubicacion'],
        ':obs' => $_POST['observaciones'],
        ':comp' => $_POST['complementos'],
        ':ntec' => $_POST['nombre_tecnico'],
        ':arem' => $arch_remito,
        ':acomp' => $arch_comp,
        ':mtipo' => $_POST['mat_tipo_carga_id'] ?: null,
        ':mcap' => $_POST['mat_capacidad'],
        ':mclase' => $_POST['mat_clase_id'] ?: null,
        ':mvc' => $_POST['mat_fecha_carga'] ?: null,
        ':mvph' => $_POST['mat_fecha_ph'] ?: null,
        ':mfab' => $_POST['fecha_fabricacion'] ?: null,
        ':mvida' => $_POST['vida_util_limite'] ?: null,
        ':nresp' => $_POST['nombre_responsable'],
        ':njefe' => $_POST['nombre_jefe_servicio'],
        ':id' => $id
    ]);
    
    header("Location: inventario_lista.php"); exit();
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
</head>
<body class="bg-light">
    <?php include 'navbar.php'; ?>
    <div class="container mt-4 mb-5">
        <form method="POST" enctype="multipart/form-data">
            <div class="card shadow">
                <div class="card-header bg-primary text-white">Editar Bien: <?php echo htmlspecialchars($bien['elemento']); ?></div>
                <div class="card-body">
                    
                    <div class="row g-3 mb-4">
                         <div class="col-md-6">
                            <label class="form-label fw-bold">Estado</label>
                            <select name="id_estado" class="form-select">
                                <?php foreach($estados_db as $e): ?>
                                    <option value="<?php echo $e['id_estado']; ?>" <?php echo ($bien['id_estado_fk']==$e['id_estado'])?'selected':''; ?>>
                                        <?php echo htmlspecialchars($e['nombre']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>

                    <div class="card bg-light border-danger mb-4">
                        <div class="card-header bg-danger text-white">Datos Matafuego (Si aplica)</div>
                        <div class="card-body">
                            <div class="row g-3">
                                <div class="col-md-3">
                                    <label class="form-label small fw-bold">Tipo Carga</label>
                                    <select name="mat_tipo_carga_id" class="form-select">
                                        <option value="">-- No es Matafuego --</option>
                                        <?php foreach($tipos_matafuegos as $tm): ?>
                                            <option value="<?php echo $tm['id_config']; ?>" <?php echo ($bien['mat_tipo_carga_id']==$tm['id_config'])?'selected':''; ?>>
                                                <?php echo htmlspecialchars($tm['tipo_carga']); ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <div class="col-md-2">
                                    <label class="form-label small fw-bold">Capacidad</label>
                                    <select name="mat_capacidad" class="form-select">
                                        <?php foreach(['1','2.5','3.5','5','10'] as $c): ?>
                                            <option value="<?php echo $c; ?>" <?php echo ($bien['mat_capacidad']==$c)?'selected':''; ?>><?php echo $c; ?> Kg</option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                
                                <div class="col-md-2">
                                    <label class="small fw-bold">Clase</label>
                                    <select name="mat_clase_id" class="form-select">
                                        <option value="">--</option>
                                        <?php foreach($clases_fuego as $cf): ?>
                                            <option value="<?php echo $cf['id_clase']; ?>" <?php echo ($bien['mat_clase_id']==$cf['id_clase'])?'selected':''; ?>>
                                                <?php echo htmlspecialchars($cf['nombre']); ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>

                                <div class="col-md-2"><label class="small fw-bold">Año Fab.</label><input type="number" name="fecha_fabricacion" class="form-control" value="<?php echo $bien['fecha_fabricacion']; ?>"></div>
                                <div class="col-md-3"><label class="small fw-bold">Vida Util Limite (Año)</label><input type="number" name="vida_util_limite" class="form-control" value="<?php echo $bien['vida_util_limite']; ?>"></div>
                                
                                <div class="col-md-3"><label class="small fw-bold">Ultima Carga</label><input type="date" name="mat_fecha_carga" class="form-control" value="<?php echo $bien['mat_fecha_carga']; ?>"></div>
                                <div class="col-md-3"><label class="small fw-bold">Prueba Hidraulica</label><input type="date" name="mat_fecha_ph" class="form-control" value="<?php echo $bien['mat_fecha_ph']; ?>"></div>
                                <div class="col-md-6"><label class="small fw-bold">Complementos</label><input type="text" name="complementos" class="form-control" value="<?php echo $bien['complementos']; ?>"></div>

                                <div class="col-12"><hr></div>
                                <div class="col-md-6">
                                    <label class="small fw-bold">Remito (Actual: <?php echo $bien['archivo_remito']?'SI':'NO'; ?>)</label>
                                    <input type="file" name="archivo_remito" class="form-control">
                                </div>
                                <div class="col-md-6">
                                    <label class="small fw-bold">Comprobante (Actual: <?php echo $bien['archivo_comprobante']?'SI':'NO'; ?>)</label>
                                    <input type="file" name="archivo_comprobante" class="form-control">
                                </div>
                                <div class="col-12"><label class="small fw-bold">Técnico</label><input type="text" name="nombre_tecnico" class="form-control" value="<?php echo $bien['nombre_tecnico']; ?>"></div>
                            </div>
                        </div>
                    </div>

                    <div class="row g-3">
                        <div class="col-md-8"><label class="fw-bold">Elemento</label><input type="text" name="elemento" class="form-control" value="<?php echo $bien['elemento']; ?>" required></div>
                        <div class="col-md-4"><label class="fw-bold">Código</label><input type="text" name="codigo_inventario" class="form-control" value="<?php echo $bien['codigo_inventario']; ?>"></div>
                        <div class="col-md-12"><label class="fw-bold">Ubicación</label>
                            <select name="servicio_ubicacion" id="select_area" class="form-select">
                                <option value="<?php echo $bien['servicio_ubicacion']; ?>" selected><?php echo $bien['servicio_ubicacion']; ?></option>
                            </select>
                        </div>
                        <div class="col-md-12"><label class="fw-bold">Observaciones</label><textarea name="observaciones" class="form-control"><?php echo $bien['observaciones']; ?></textarea></div>
                        <div class="col-md-6"><label class="fw-bold">Responsable</label><input type="text" name="nombre_responsable" class="form-control" value="<?php echo $bien['nombre_responsable']; ?>"></div>
                        <div class="col-md-6"><label class="fw-bold">Jefe</label><input type="text" name="nombre_jefe_servicio" class="form-control" value="<?php echo $bien['nombre_jefe_servicio']; ?>"></div>
                    </div>
                    
                    <button type="submit" class="btn btn-primary mt-4">Guardar Cambios</button>
                    <a href="inventario_lista.php" class="btn btn-secondary mt-4">Cancelar</a>
                </div>
            </div>
        </form>
    </div>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://code.jquery.com/jquery-3.7.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>
    <script>$(document).ready(function() { $('#select_area').select2({ theme: 'bootstrap-5' }); });</script>
</body>
</html>