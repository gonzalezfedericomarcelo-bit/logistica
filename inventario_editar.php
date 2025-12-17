<?php
// Archivo: inventario_editar.php
session_start();
include 'conexion.php';
include 'funciones_permisos.php';

// Verificar sesión
if (!isset($_SESSION['usuario_id']) || !tiene_permiso('acceso_inventario', $pdo)) {
    header("Location: dashboard.php"); exit();
}

// Obtener ID de forma segura
$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

// Buscar el bien en la base de datos
$stmt = $pdo->prepare("SELECT * FROM inventario_cargos WHERE id_cargo = ?");
$stmt->execute([$id]);
$bien = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$bien) die("ERROR: Bien no encontrado. ID inválido.");

// Cargar listas para los selectores
$lista_destinos = $pdo->query("SELECT id_destino, nombre FROM destinos_internos ORDER BY nombre ASC")->fetchAll(PDO::FETCH_ASSOC);
$estados_db = $pdo->query("SELECT * FROM inventario_estados")->fetchAll(PDO::FETCH_ASSOC);
$tipos_matafuegos = $pdo->query("SELECT * FROM inventario_config_matafuegos")->fetchAll(PDO::FETCH_ASSOC);
$clases_fuego = $pdo->query("SELECT * FROM inventario_config_clases")->fetchAll(PDO::FETCH_ASSOC);

// PROCESAR EL GUARDADO
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    
    try {
        // Función para subir archivos si existen
        function actualizarArchivo($input, $actual) {
            if (isset($_FILES[$input]) && $_FILES[$input]['error'] === UPLOAD_ERR_OK) {
                $ext = pathinfo($_FILES[$input]['name'], PATHINFO_EXTENSION);
                $nombre = 'upd_' . time() . '_' . uniqid() . '.' . $ext;
                if(!file_exists('uploads/documentacion')) mkdir('uploads/documentacion', 0777, true);
                move_uploaded_file($_FILES[$input]['tmp_name'], 'uploads/documentacion/' . $nombre);
                return $nombre;
            }
            return $actual;
        }
        
        $arch_remito = actualizarArchivo('archivo_remito', $bien['archivo_remito']);
        $arch_comp = actualizarArchivo('archivo_comprobante', $bien['archivo_comprobante']);

        // Consulta SQL corregida y segura
        $sql = "UPDATE inventario_cargos SET 
                id_estado_fk = :idest, elemento = :elem, codigo_inventario = :cod, servicio_ubicacion = :serv,
                observaciones = :obs, complementos = :comp, nombre_tecnico = :ntec,
                archivo_remito = :arem, archivo_comprobante = :acomp,
                mat_tipo_carga_id = :mtipo, mat_capacidad = :mcap, mat_clase_id = :mclase, mat_numero_grabado = :mgrab,
                mat_fecha_carga = :mvc, mat_fecha_ph = :mvph, fecha_fabricacion = :mfab, vida_util_limite = :mvida,
                nombre_responsable = :nresp, nombre_jefe_servicio = :njefe
                WHERE id_cargo = :id";
        
        $stmtUpdate = $pdo->prepare($sql);
        
        // Ejecutar con validación de nulos (?? null) para evitar errores
        $resultado = $stmtUpdate->execute([
            ':idest' => !empty($_POST['id_estado']) ? $_POST['id_estado'] : null,
            ':elem' => $_POST['elemento'] ?? '',
            ':cod' => $_POST['codigo_inventario'] ?? '',
            ':serv' => $_POST['servicio_ubicacion'] ?? '',
            ':obs' => $_POST['observaciones'] ?? '',
            ':comp' => $_POST['complementos'] ?? '',
            ':ntec' => $_POST['nombre_tecnico'] ?? '',
            ':arem' => $arch_remito,
            ':acomp' => $arch_comp,
            ':mtipo' => !empty($_POST['mat_tipo_carga_id']) ? $_POST['mat_tipo_carga_id'] : null,
            ':mcap' => !empty($_POST['mat_capacidad']) ? $_POST['mat_capacidad'] : null,
            ':mclase' => !empty($_POST['mat_clase_id']) ? $_POST['mat_clase_id'] : null,
            ':mgrab' => $_POST['mat_numero_grabado'] ?? null,
            ':mvc' => !empty($_POST['mat_fecha_carga']) ? $_POST['mat_fecha_carga'] : null,
            ':mvph' => !empty($_POST['mat_fecha_ph']) ? $_POST['mat_fecha_ph'] : null,
            ':mfab' => !empty($_POST['fecha_fabricacion']) ? $_POST['fecha_fabricacion'] : null,
            ':mvida' => !empty($_POST['vida_util_limite']) ? $_POST['vida_util_limite'] : null,
            ':nresp' => $_POST['nombre_responsable'] ?? '',
            ':njefe' => $_POST['nombre_jefe_servicio'] ?? '',
            ':id' => $id
        ]);
        
        if($resultado) {
            // Redirigir a la lista con mensaje de éxito
            echo "<script>window.location.href='inventario_lista.php?msg=guardado_ok';</script>";
            exit();
        } else {
            $errorInfo = $stmtUpdate->errorInfo();
            die("Error SQL al guardar: " . $errorInfo[2]);
        }

    } catch (Exception $e) {
        die("Excepción: " . $e->getMessage());
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Editar Bien - <?php echo htmlspecialchars($bien['elemento']); ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />
    <link href="https://cdn.jsdelivr.net/npm/select2-bootstrap-5-theme@1.3.0/dist/select2-bootstrap-5-theme.min.css" rel="stylesheet" />
</head>
<body class="bg-light">
    <?php include 'navbar.php'; ?>
    <div class="container mt-4 mb-5">
        
        <form method="POST" action="inventario_editar.php?id=<?php echo $id; ?>" enctype="multipart/form-data">
            
            <div class="card shadow border-0">
                <div class="card-header bg-primary text-white d-flex justify-content-between align-items-center">
                    <h5 class="m-0"><i class="fas fa-edit me-2"></i> Editando: <?php echo htmlspecialchars($bien['elemento']); ?></h5>
                    <a href="inventario_lista.php" class="btn btn-sm btn-light fw-bold text-primary">Volver a la Lista</a>
                </div>
                
                <div class="card-body p-4">
                    
                    <div class="alert alert-info border-info">
                        <i class="fas fa-info-circle me-2"></i>
                        <strong>Importante:</strong> Si cambia el estado a "ACTIVO", asegúrese de actualizar la <u>Fecha de Carga</u> a este año. Si deja una fecha vieja, el sistema lo volverá a marcar como vencido automáticamente.
                    </div>

                    <div class="row g-3 mb-4">
                         <div class="col-md-6">
                            <label class="fw-bold">Estado Actual</label>
                            <select name="id_estado" class="form-select fw-bold border-2 border-primary">
                                <?php foreach($estados_db as $e): ?>
                                    <option value="<?php echo $e['id_estado']; ?>" <?php echo ($bien['id_estado_fk']==$e['id_estado'])?'selected':''; ?>>
                                        <?php echo htmlspecialchars($e['nombre']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>

                    <?php if($bien['mat_tipo_carga_id']): ?>
                    <div class="card bg-light border-danger mb-4">
                        <div class="card-header bg-danger text-white fw-bold">Datos Técnicos del Matafuego</div>
                        <div class="card-body">
                            <div class="row g-3">
                                <div class="col-md-3">
                                    <label class="small fw-bold">Tipo Carga</label>
                                    <select name="mat_tipo_carga_id" class="form-select">
                                        <?php foreach($tipos_matafuegos as $tm): ?>
                                            <option value="<?php echo $tm['id_config']; ?>" <?php echo ($bien['mat_tipo_carga_id']==$tm['id_config'])?'selected':''; ?>>
                                                <?php echo htmlspecialchars($tm['tipo_carga']); ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <div class="col-md-2">
                                    <label class="small fw-bold">Capacidad</label>
                                    <select name="mat_capacidad" class="form-select">
                                        <?php foreach(['1','2.5','3.5','5','10'] as $c): ?>
                                            <option value="<?php echo $c; ?>" <?php echo ($bien['mat_capacidad']==$c)?'selected':''; ?>><?php echo $c; ?> Kg</option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                
                                <div class="col-md-2">
                                    <label class="small fw-bold">Clase</label>
                                    <select name="mat_clase_id" class="form-select">
                                        <?php foreach($clases_fuego as $cf): ?>
                                            <option value="<?php echo $cf['id_clase']; ?>" <?php echo ($bien['mat_clase_id']==$cf['id_clase'])?'selected':''; ?>>
                                                <?php echo htmlspecialchars($cf['nombre']); ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>

                                <div class="col-md-5">
                                    <label class="small fw-bold text-danger">N° Grabado (Fábrica)</label>
                                    <input type="text" name="mat_numero_grabado" class="form-control" value="<?php echo htmlspecialchars($bien['mat_numero_grabado']); ?>">
                                </div>

                                <div class="col-md-3"><label class="small fw-bold">Año Fab.</label><input type="number" name="fecha_fabricacion" class="form-control" value="<?php echo $bien['fecha_fabricacion']; ?>"></div>
                                <div class="col-md-3"><label class="small fw-bold">Vida Util (Años)</label><input type="number" name="vida_util_limite" class="form-control" value="<?php echo $bien['vida_util_limite']; ?>"></div>
                                
                                <div class="col-md-3">
                                    <label class="small fw-bold text-danger">Ultima Carga (Requerido)</label>
                                    <input type="date" name="mat_fecha_carga" class="form-control border-danger" value="<?php echo $bien['mat_fecha_carga']; ?>">
                                </div>
                                <div class="col-md-3">
                                    <label class="small fw-bold">Prueba Hidraulica</label>
                                    <input type="date" name="mat_fecha_ph" class="form-control" value="<?php echo $bien['mat_fecha_ph']; ?>">
                                </div>
                                
                                <div class="col-12"><hr></div>
                                <div class="col-md-6">
                                    <label class="small fw-bold">Remito (Actual: <?php echo $bien['archivo_remito']?'SI':'NO'; ?>)</label>
                                    <input type="file" name="archivo_remito" class="form-control">
                                </div>
                                <div class="col-md-6">
                                    <label class="small fw-bold">Comprobante (Actual: <?php echo $bien['archivo_comprobante']?'SI':'NO'; ?>)</label>
                                    <input type="file" name="archivo_comprobante" class="form-control">
                                </div>
                            </div>
                        </div>
                    </div>
                    <?php endif; ?>

                    <div class="row g-3">
                        <div class="col-md-8"><label class="fw-bold">Descripción / Elemento</label><input type="text" name="elemento" class="form-control fw-bold" value="<?php echo htmlspecialchars($bien['elemento']); ?>" required></div>
                        <div class="col-md-4"><label class="fw-bold text-primary">Código Interno</label><input type="text" name="codigo_inventario" class="form-control" value="<?php echo htmlspecialchars($bien['codigo_inventario']); ?>"></div>
                        
                        <div class="col-md-12">
                            <label class="fw-bold">Ubicación</label>
                            <select name="servicio_ubicacion" id="select_area" class="form-select">
                                <option value="<?php echo $bien['servicio_ubicacion']; ?>" selected><?php echo $bien['servicio_ubicacion']; ?></option>
                                <?php foreach($lista_destinos as $d): ?>
                                    <option value="<?php echo $d['nombre']; ?>"><?php echo htmlspecialchars($d['nombre']); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        
                        <div class="col-md-12"><label class="fw-bold">Observaciones</label><textarea name="observaciones" class="form-control" rows="3"><?php echo htmlspecialchars($bien['observaciones']); ?></textarea></div>
                    </div>
                    
                    <div class="d-grid gap-2 mt-5">
                        <button type="submit" class="btn btn-primary btn-lg fw-bold shadow">GUARDAR CAMBIOS</button>
                    </div>
                </div>
            </div>
        </form>
    </div>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://code.jquery.com/jquery-3.7.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>
    <script>$(document).ready(function() { $('#select_area').select2({ theme: 'bootstrap-5', tags: true }); });</script>
</body>
</html>