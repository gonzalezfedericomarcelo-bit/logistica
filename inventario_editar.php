<?php
// Archivo: inventario_editar.php (VERSIÓN COMPLETA RESTAURADA)
session_start();
include 'conexion.php';
include 'funciones_permisos.php';

if (!isset($_SESSION['usuario_id']) || !tiene_permiso('inventario_editar', $pdo)) {
    header("Location: inventario_lista.php"); exit();
}

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
// Traemos TODOS los datos del bien
$bien = $pdo->query("SELECT * FROM inventario_cargos WHERE id_cargo = $id")->fetch(PDO::FETCH_ASSOC);

if (!$bien) die("Bien no encontrado.");

// Cargar Listas
$lista_destinos = $pdo->query("SELECT * FROM destinos_internos ORDER BY nombre ASC")->fetchAll(PDO::FETCH_ASSOC);
$estados_db = $pdo->query("SELECT * FROM inventario_estados")->fetchAll(PDO::FETCH_ASSOC);
$tipos_matafuegos = $pdo->query("SELECT * FROM inventario_config_matafuegos")->fetchAll(PDO::FETCH_ASSOC);
$clases_fuego = $pdo->query("SELECT * FROM inventario_config_clases")->fetchAll(PDO::FETCH_ASSOC);

// Procesar Guardado
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    
    // Obtener nombre del destino para guardar texto (backup visual)
    $dest_nombre = '';
    if(!empty($_POST['id_destino'])) {
        $stmtD = $pdo->prepare("SELECT nombre FROM destinos_internos WHERE id_destino = ?");
        $stmtD->execute([$_POST['id_destino']]);
        $rowD = $stmtD->fetch();
        $dest_nombre = $rowD ? $rowD['nombre'] : '';
    }

    // Nombre del Área (Si es "General" o vacío, lo manejamos)
    $nombre_area = $_POST['nombre_area'];
    if($nombre_area == 'General' || $nombre_area == '') { 
        // Si querés que se guarde vacío en lugar de 'General', descomentá la siguiente línea:
        // $nombre_area = ''; 
    }

    $sql = "UPDATE inventario_cargos SET 
            elemento = ?, 
            codigo_inventario = ?, 
            mat_numero_grabado = ?, 
            id_estado_fk = ?, 
            destino_principal = ?,  
            servicio_ubicacion = ?, 
            nombre_responsable = ?,
            nombre_tecnico = ?,
            observaciones = ?,
            
            -- Campos Matafuegos
            mat_tipo_carga_id = ?,
            mat_capacidad = ?,
            mat_clase_id = ?,
            mat_fecha_carga = ?,
            mat_fecha_ph = ?,
            fecha_fabricacion = ?,
            vida_util_limite = ?
            
            WHERE id_cargo = ?";
            
    $stmt = $pdo->prepare($sql);
    
    // Manejo de nulos para fechas vacías
    $f_carga = !empty($_POST['mat_fecha_carga']) ? $_POST['mat_fecha_carga'] : null;
    $f_ph = !empty($_POST['mat_fecha_ph']) ? $_POST['mat_fecha_ph'] : null;
    $f_fab = !empty($_POST['fecha_fabricacion']) ? $_POST['fecha_fabricacion'] : null;
    $vida_util = !empty($_POST['vida_util_limite']) ? $_POST['vida_util_limite'] : null;
    $tipo_carga = !empty($_POST['mat_tipo_carga_id']) ? $_POST['mat_tipo_carga_id'] : null;
    $clase_id = !empty($_POST['mat_clase_id']) ? $_POST['mat_clase_id'] : null;

    $stmt->execute([
        $_POST['elemento'],
        $_POST['codigo_inventario'],
        $_POST['mat_numero_grabado'],
        $_POST['id_estado'],
        $dest_nombre,
        $nombre_area,
        $_POST['nombre_responsable'],
        $_POST['nombre_tecnico'],
        $_POST['observaciones'],
        $tipo_carga,
        $_POST['mat_capacidad'],
        $clase_id,
        $f_carga,
        $f_ph,
        $f_fab,
        $vida_util,
        $id
    ]);

    header("Location: inventario_lista.php"); exit();
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Editar Bien Completo</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />
    <link href="https://cdn.jsdelivr.net/npm/select2-bootstrap-5-theme@1.3.0/dist/select2-bootstrap-5-theme.min.css" rel="stylesheet" />
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        .bg-gradient-header { background: linear-gradient(90deg, #0d6efd 0%, #0a58ca 100%); color: white; }
        .section-title { font-size: 0.9rem; font-weight: bold; color: #6c757d; text-transform: uppercase; border-bottom: 2px solid #dee2e6; padding-bottom: 5px; margin-bottom: 15px; margin-top: 20px; }
    </style>
</head>
<body class="bg-light">
    <?php include 'navbar.php'; ?>
    
    <div class="container mt-4 mb-5">
        <form method="POST">
            <div class="card shadow-lg border-0 rounded-3">
                <div class="card-header bg-gradient-header py-3 d-flex justify-content-between align-items-center">
                    <h5 class="mb-0 fw-bold"><i class="fas fa-edit me-2"></i> Editando: <?php echo htmlspecialchars($bien['elemento']); ?></h5>
                    <a href="inventario_lista.php" class="btn btn-sm btn-light text-primary fw-bold"><i class="fas fa-times me-1"></i> Cancelar</a>
                </div>
                
                <div class="card-body p-4">
                    
                    <div class="section-title"><i class="fas fa-info-circle me-2"></i> Información Básica</div>
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label fw-bold small">Nombre / Descripción</label>
                            <input type="text" name="elemento" class="form-control" value="<?php echo htmlspecialchars($bien['elemento']); ?>" required>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label fw-bold small">Código Interno</label>
                            <input type="text" name="codigo_inventario" class="form-control" value="<?php echo htmlspecialchars($bien['codigo_inventario']); ?>">
                        </div>
                        <div class="col-md-3">
                            <label class="form-label fw-bold small">N° Grabado / Serie</label>
                            <input type="text" name="mat_numero_grabado" class="form-control" value="<?php echo htmlspecialchars($bien['mat_numero_grabado']); ?>">
                        </div>
                    </div>

                    <div class="section-title"><i class="fas fa-map-marker-alt me-2"></i> Ubicación y Responsable</div>
                    <div class="row g-3">
                        <div class="col-md-4">
                            <label class="form-label fw-bold small">Destino (Sede/Edificio)</label>
                            <select name="id_destino" id="select_destino" class="form-select" required>
                                <option value="">-- Seleccione --</option>
                                <?php foreach($lista_destinos as $d): 
                                    // Intentamos matchear por nombre si existe, sino dejamos elegir
                                    $selected = ($d['nombre'] == $bien['destino_principal']) ? 'selected' : '';
                                ?>
                                    <option value="<?php echo $d['id_destino']; ?>" <?php echo $selected; ?>>
                                        <?php echo htmlspecialchars($d['nombre']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label fw-bold small">Área Específica</label>
                            <select name="nombre_area" id="select_area" class="form-select" required>
                                <option value="<?php echo $bien['servicio_ubicacion']; ?>" selected><?php echo $bien['servicio_ubicacion']; ?></option>
                            </select>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label fw-bold small">Nombre Responsable</label>
                            <input type="text" name="nombre_responsable" class="form-control" value="<?php echo htmlspecialchars($bien['nombre_responsable']); ?>">
                        </div>
                    </div>

                    <div class="section-title"><i class="fas fa-fire-extinguisher me-2"></i> Datos Técnicos (Solo Matafuegos)</div>
                    <div class="bg-light p-3 rounded border">
                        <div class="row g-3">
                            <div class="col-md-3">
                                <label class="form-label fw-bold small">Tipo de Agente</label>
                                <select name="mat_tipo_carga_id" class="form-select">
                                    <option value="">-- No es Matafuego --</option>
                                    <?php foreach($tipos_matafuegos as $tm): ?>
                                        <option value="<?php echo $tm['id_config']; ?>" <?php if($tm['id_config']==$bien['mat_tipo_carga_id']) echo 'selected'; ?>>
                                            <?php echo htmlspecialchars($tm['tipo_carga']); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-md-2">
                                <label class="form-label fw-bold small">Capacidad (Kg)</label>
                                <input type="text" name="mat_capacidad" class="form-control" value="<?php echo htmlspecialchars($bien['mat_capacidad']); ?>">
                            </div>
                            <div class="col-md-2">
                                <label class="form-label fw-bold small">Clase Fuego</label>
                                <select name="mat_clase_id" class="form-select">
                                    <option value="">-</option>
                                    <?php foreach($clases_fuego as $c): ?>
                                        <option value="<?php echo $c['id_clase']; ?>" <?php if($c['id_clase']==$bien['mat_clase_id']) echo 'selected'; ?>>
                                            <?php echo htmlspecialchars($c['nombre']); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-md-2">
                                <label class="form-label fw-bold small">Fecha Fabricación</label>
                                <input type="number" name="fecha_fabricacion" class="form-control" placeholder="Año (Ej: 2024)" value="<?php echo htmlspecialchars($bien['fecha_fabricacion']); ?>">
                            </div>
                            <div class="col-md-3">
                                <label class="form-label fw-bold small">Fin Vida Útil (Año)</label>
                                <input type="number" name="vida_util_limite" class="form-control" value="<?php echo htmlspecialchars($bien['vida_util_limite']); ?>">
                            </div>
                        </div>

                        <div class="row g-3 mt-2">
                            <div class="col-md-4">
                                <label class="form-label fw-bold small text-success">Última Carga</label>
                                <input type="date" name="mat_fecha_carga" class="form-control border-success" value="<?php echo $bien['mat_fecha_carga']; ?>">
                            </div>
                            <div class="col-md-4">
                                <label class="form-label fw-bold small text-primary">Última Prueba Hidráulica (PH)</label>
                                <input type="date" name="mat_fecha_ph" class="form-control border-primary" value="<?php echo $bien['mat_fecha_ph']; ?>">
                            </div>
                            <div class="col-md-4">
                                <label class="form-label fw-bold small">Técnico Interviniente</label>
                                <input type="text" name="nombre_tecnico" class="form-control" value="<?php echo htmlspecialchars($bien['nombre_tecnico']); ?>">
                            </div>
                        </div>
                    </div>

                    <div class="section-title"><i class="fas fa-clipboard-check me-2"></i> Estado General</div>
                    <div class="row g-3">
                        <div class="col-md-4">
                            <label class="form-label fw-bold small">Estado Actual</label>
                            <select name="id_estado" class="form-select">
                                <?php foreach($estados_db as $e): ?>
                                    <option value="<?php echo $e['id_estado']; ?>" <?php if($e['id_estado']==$bien['id_estado_fk']) echo 'selected'; ?>>
                                        <?php echo htmlspecialchars($e['nombre']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-8">
                            <label class="form-label fw-bold small">Observaciones / Notas</label>
                            <textarea name="observaciones" class="form-control" rows="2"><?php echo htmlspecialchars($bien['observaciones']); ?></textarea>
                        </div>
                    </div>

                    <div class="d-grid mt-5">
                        <button type="submit" class="btn btn-primary btn-lg fw-bold shadow-sm"><i class="fas fa-save me-2"></i> GUARDAR CAMBIOS</button>
                    </div>

                </div>
            </div>
        </form>
    </div>

    <script src="https://code.jquery.com/jquery-3.7.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>
    
    <script>
        $(document).ready(function() {
            $('#select_destino').select2({ theme: 'bootstrap-5' });
            $('#select_area').select2({ theme: 'bootstrap-5', tags: true });

            // LÓGICA DE DESTINO EN CASCADA (Misma que en transferir, pero para edición)
            $('#select_destino').on('change', function() {
                var idDestino = $(this).val();
                var currentArea = "<?php echo $bien['servicio_ubicacion']; ?>";
                
                // Deshabilitar mientras carga
                $('#select_area').empty().append('<option value="">Cargando...</option>').prop('disabled', true);

                $.getJSON('ajax_obtener_areas.php', { id_destino: idDestino }, function(data) {
                    $('#select_area').empty().prop('disabled', false); // Habilitar siempre
                    
                    if(data.length > 0) {
                        $('#select_area').append('<option value="">-- Seleccione Área --</option>');
                        $.each(data, function(i, item) {
                            var isSel = (item.nombre === currentArea) ? 'selected' : '';
                            $('#select_area').append(`<option value="${item.nombre}" ${isSel}>${item.nombre}</option>`);
                        });
                    } else {
                        // Si no tiene áreas, ponemos la opción por defecto para que no trabe
                        $('#select_area').append('<option value="" selected>(Sin áreas - Opcional)</option>');
                    }
                }).fail(function() {
                    // Fallback
                    $('#select_area').empty().append('<option value="" selected>General</option>').prop('disabled', false);
                });
            });
        });
    </script>
</body>
</html>