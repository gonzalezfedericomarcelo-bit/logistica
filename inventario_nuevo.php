<?php
// Archivo: inventario_nuevo.php
// OBJETIVO: Permitir selección de usuarios de sistema O firma remota para generar token después.
session_start();
include 'conexion.php';
include 'funciones_permisos.php'; 

if (!isset($_SESSION['usuario_id']) || !tiene_permiso('acceso_inventario', $pdo)) {
    header("Location: dashboard.php"); exit();
}

// Cargas de datos
$tipos_bien = $pdo->query("SELECT * FROM inventario_tipos_bien ORDER BY id_tipo_bien ASC")->fetchAll(PDO::FETCH_ASSOC);
$destinos = $pdo->query("SELECT * FROM destinos_internos ORDER BY nombre ASC")->fetchAll(PDO::FETCH_ASSOC);
$relevador = $pdo->query("SELECT nombre_completo FROM usuarios WHERE id_usuario = {$_SESSION['usuario_id']}")->fetch(PDO::FETCH_ASSOC);
// Lista de usuarios para el select
$usuarios_sistema = $pdo->query("SELECT id_usuario, nombre_completo FROM usuarios ORDER BY nombre_completo ASC")->fetchAll(PDO::FETCH_ASSOC);

$mapa_tipos = [];
foreach($tipos_bien as $tb) {
    $nombre = mb_strtolower($tb['nombre']);
    if (strpos($nombre, 'matafuego') !== false) $mapa_tipos['matafuego'] = $tb['id_tipo_bien'];
    if (strpos($nombre, 'cámara') !== false || strpos($nombre, 'camara') !== false) $mapa_tipos['camara'] = $tb['id_tipo_bien'];
    if (strpos($nombre, 'teléfono') !== false || strpos($nombre, 'telefono') !== false) $mapa_tipos['telefono'] = $tb['id_tipo_bien'];
    if (strpos($nombre, 'informática') !== false || strpos($nombre, 'informatica') !== false) $mapa_tipos['informatica'] = $tb['id_tipo_bien'];
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Nuevo Item | Inventario</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />
    <link href="https://cdn.jsdelivr.net/npm/select2-bootstrap-5-theme@1.3.0/dist/select2-bootstrap-5-theme.min.css" rel="stylesheet" />
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        .step-container { display: none; }
        .step-container.active { display: block; animation: fadeIn 0.3s; }
        .selection-card { cursor: pointer; transition: all 0.2s; border: 2px solid #dee2e6; height: 100%; }
        .selection-card:hover { transform: translateY(-5px); border-color: #0d6efd; background-color: #f8f9fa; }
        .preview-firma { height: 100px; border: 2px dashed #ccc; display: flex; align-items: center; justify-content: center; cursor: pointer; background: #fff; transition: 0.2s; }
        .preview-firma:hover { border-color: #0d6efd; background-color: #f0f8ff; }
        .preview-firma img { max-height: 100%; max-width: 100%; }
        .firma-disabled { background-color: #e9ecef; cursor: not-allowed; border-color: #adb5bd; color: #6c757d; }
        
        #canvasContainer { 
            width: 95%; height: 70vh; background: #fff; margin: auto; 
            border: 2px solid #ccc; box-shadow: 0 10px 25px rgba(0,0,0,0.1); 
            position: relative; border-radius: 8px;
        }
        .firma-linea { position: absolute; top: 70%; left: 10%; right: 10%; border-bottom: 2px solid #333; z-index: 1; pointer-events: none; opacity: 0.5; }
        .sign-instruction { position: absolute; top: 75%; width: 100%; text-align: center; color: #777; font-weight: bold; font-size: 0.9rem; pointer-events: none; text-transform: uppercase; letter-spacing: 2px; }
        @keyframes fadeIn { from { opacity: 0; } to { opacity: 1; } }
    </style>
</head>
<body class="bg-light">
    <?php include 'navbar.php'; ?>

    <div class="container mt-4 mb-5">
        <form id="formInventario" method="POST" action="inventario_guardar.php">
            <input type="hidden" name="id_tipo_bien_seleccionado" id="id_tipo_bien_seleccionado">

            <div id="step-1" class="step-container active">
                <div class="text-center mb-5">
                    <h3 class="fw-bold text-dark">Seleccione Categoría</h3>
                    <p class="text-muted">¿Qué tipo de bien deseas registrar?</p>
                </div>
                <div class="row justify-content-center g-4">
                    <?php foreach($tipos_bien as $tipo): ?>
                        <div class="col-6 col-md-3">
                            <div class="card p-4 text-center selection-card shadow-sm" 
                                 onclick="selectType(<?php echo $tipo['id_tipo_bien']; ?>, '<?php echo htmlspecialchars($tipo['nombre']); ?>')">
                                <div class="mb-3"><i class="<?php echo $tipo['icono'] ? $tipo['icono'] : 'fas fa-box'; ?> fa-3x text-primary"></i></div>
                                <h6 class="fw-bold text-dark"><?php echo htmlspecialchars($tipo['nombre']); ?></h6>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>

            <div id="step-2" class="step-container">
                <div class="card shadow border-0 rounded-3">
                    <div class="card-header bg-white py-3 d-flex justify-content-between align-items-center">
                        <h5 class="mb-0 text-primary"><i class="fas fa-edit me-2"></i>Alta: <span id="lblTipoSeleccionado" class="fw-bold text-dark">Bien</span></h5>
                        <button type="button" class="btn btn-outline-secondary btn-sm" onclick="resetWizard()"><i class="fas fa-arrow-left"></i> Volver</button>
                    </div>
                    <div class="card-body p-4">

                        <div class="row g-3 mb-4">
                            <div class="col-md-6">
                                <label class="fw-bold small text-muted">DESTINO / EDIFICIO</label>
                                <select name="id_destino" id="id_destino" class="form-select select2" required>
                                    <option value="">-- Seleccionar --</option>
                                    <?php foreach($destinos as $d): ?>
                                        <option value="<?php echo $d['id_destino']; ?>"><?php echo htmlspecialchars($d['nombre']); ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-md-6">
                                <label class="fw-bold small text-muted">ÁREA / SERVICIO</label>
                                <select name="servicio_ubicacion" id="servicio_ubicacion" class="form-select select2" disabled required>
                                    <option value="">(Seleccione Destino)</option>
                                </select>
                            </div>
                        </div>
                        
                        <div id="panel-matafuegos" style="display:none;" class="bg-light p-3 rounded border border-danger mb-4">
                            <h6 class="text-danger fw-bold mb-3"><i class="fas fa-fire-extinguisher"></i> Datos Técnicos Matafuego</h6>
                            <div class="row g-3">
                                <div class="col-md-4"><label class="small fw-bold">Tipo Agente</label><select name="mat_tipo_carga_id" id="mat_tipo_carga_id" class="form-select"><option value="">Cargando...</option></select></div>
                                <div class="col-md-4"><label class="small fw-bold">Capacidad</label><select name="mat_capacidad" id="mat_capacidad" class="form-select"><option value="">Cargando...</option></select></div>
                                <div class="col-md-4"><label class="small fw-bold">Clase</label><select name="mat_clase_id" id="mat_clase_id" class="form-select"><option value="">Cargando...</option></select></div>
                                <div class="col-md-4"><label class="small fw-bold">Año Fabricación</label><input type="number" name="fecha_fabricacion" id="fecha_fabricacion" class="form-control" placeholder="Ej: 2024"></div>
                                <div class="col-md-4"><label class="small fw-bold text-danger">Vencimiento (Año)</label><input type="text" id="vida_util_display" class="form-control bg-white fw-bold text-danger" readonly></div>
                                <div class="col-md-3"><label class="small fw-bold text-success">Última Carga</label><input type="date" name="mat_fecha_carga" id="mat_fecha_carga" class="form-control"></div>
                                <div class="col-md-3"><label class="small fw-bold text-success">Vence Carga (+1)</label><input type="text" id="venc_carga_display" class="form-control bg-white" readonly></div>
                                <div class="col-md-3"><label class="small fw-bold text-primary">Última PH</label><input type="date" name="mat_fecha_ph" id="mat_fecha_ph" class="form-control"></div>
                                <div class="col-md-3"><label class="small fw-bold text-primary">Vence PH (+5)</label><input type="text" id="venc_ph_display" class="form-control bg-white" readonly></div>
                                <div class="col-12"><label class="small fw-bold">N° Puesto / Grabado</label><input type="text" name="mat_numero_grabado" class="form-control"></div>
                            </div>
                        </div>

                        <div id="render-campos" class="row g-3 mb-4 bg-light p-3 rounded border" style="display:none;"></div>                
                        
                        <div class="row g-3 mb-4">
                            <div class="col-md-8"><label class="fw-bold small text-muted">NOMBRE / DESCRIPCIÓN (Auto)</label><input type="text" name="elemento" id="elemento" class="form-control fw-bold" required></div>
                            <div class="col-md-4"><label class="fw-bold small text-muted">ESTADO OPERATIVO</label><select name="id_estado" id="id_estado" class="form-select"><option>Cargando estados...</option></select></div>
                            <div class="col-md-6"><label class="fw-bold small text-muted">N° CARGO PATRIMONIAL</label><input type="text" name="codigo_inventario" class="form-control" placeholder="Ej: 43361"></div>
                            <div class="col-md-6"><label class="fw-bold small text-primary">N° IOSFA SISTEMAS</label><input type="text" name="n_iosfa" class="form-control border-primary" placeholder="Ej: 12972"></div>
                            <div class="col-12"><label class="fw-bold small text-muted">OBSERVACIONES</label><input type="text" name="observaciones" class="form-control"></div>
                        </div>

                        <div class="border-top pt-3 bg-light p-3 rounded">
                            <h6 class="text-primary fw-bold mb-3"><i class="fas fa-users me-2"></i>Asignación de Responsables</h6>
                            
                            <div class="row g-3 align-items-center mb-3">
                                <div class="col-md-2"><label class="fw-bold small">Responsable:</label></div>
                                <div class="col-md-4">
                                    <div class="btn-group w-100" role="group">
                                        <input type="radio" class="btn-check" name="modo_responsable" id="resp_manual" value="manual" checked onchange="toggleMode('responsable', 'manual')">
                                        <label class="btn btn-outline-secondary btn-sm" for="resp_manual">Manual</label>
                                        
                                        <input type="radio" class="btn-check" name="modo_responsable" id="resp_sistema" value="sistema" onchange="toggleMode('responsable', 'sistema')">
                                        <label class="btn btn-outline-secondary btn-sm" for="resp_sistema">Usuario Sistema</label>

                                        <input type="radio" class="btn-check" name="modo_responsable" id="resp_remoto" value="remoto" onchange="toggleMode('responsable', 'remoto')">
                                        <label class="btn btn-outline-secondary btn-sm" for="resp_remoto">Firma Remota</label>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <input type="text" name="nombre_responsable" id="input_nombre_responsable" class="form-control" placeholder="Nombre Apellido (Firma ahora)">
                                    <div id="select_responsable_wrapper" style="display:none;">
                                        <select name="id_responsable_sistema" id="id_responsable_sistema" class="form-select select2">
                                            <option value="">-- Buscar Usuario --</option>
                                            <?php foreach($usuarios_sistema as $u): ?>
                                                <option value="<?php echo $u['id_usuario']; ?>"><?php echo htmlspecialchars($u['nombre_completo']); ?></option>
                                            <?php endforeach; ?>
                                        </select>
                                        <small class="text-info"><i class="fas fa-bell"></i> Se enviará notificación para firmar.</small>
                                    </div>
                                    <div id="msg_responsable_remoto" style="display:none;" class="text-warning small">
                                        <i class="fas fa-link"></i> Se generará un link para firmar externamente.
                                    </div>
                                </div>
                            </div>

                            <div class="row g-3 align-items-center mb-3">
                                <div class="col-md-2"><label class="fw-bold small">Jefe Servicio:</label></div>
                                <div class="col-md-4">
                                    <div class="btn-group w-100" role="group">
                                        <input type="radio" class="btn-check" name="modo_jefe" id="jefe_manual" value="manual" checked onchange="toggleMode('jefe', 'manual')">
                                        <label class="btn btn-outline-secondary btn-sm" for="jefe_manual">Manual</label>
                                        
                                        <input type="radio" class="btn-check" name="modo_jefe" id="jefe_sistema" value="sistema" onchange="toggleMode('jefe', 'sistema')">
                                        <label class="btn btn-outline-secondary btn-sm" for="jefe_sistema">Usuario Sistema</label>

                                        <input type="radio" class="btn-check" name="modo_jefe" id="jefe_remoto" value="remoto" onchange="toggleMode('jefe', 'remoto')">
                                        <label class="btn btn-outline-secondary btn-sm" for="jefe_remoto">Firma Remota</label>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <input type="text" name="nombre_jefe_servicio" id="input_nombre_jefe" class="form-control" placeholder="Nombre Apellido (Firma ahora)">
                                    <div id="select_jefe_wrapper" style="display:none;">
                                        <select name="id_jefe_sistema" id="id_jefe_sistema" class="form-select select2">
                                            <option value="">-- Buscar Jefe --</option>
                                            <?php foreach($usuarios_sistema as $u): ?>
                                                <option value="<?php echo $u['id_usuario']; ?>"><?php echo htmlspecialchars($u['nombre_completo']); ?></option>
                                            <?php endforeach; ?>
                                        </select>
                                        <small class="text-info"><i class="fas fa-bell"></i> Se enviará notificación para firmar.</small>
                                    </div>
                                    <div id="msg_jefe_remoto" style="display:none;" class="text-warning small">
                                        <i class="fas fa-link"></i> Se generará un link para firmar externamente.
                                    </div>
                                </div>
                            </div>

                            <div class="row mt-4 text-center">
                                <div class="col-md-4">
                                    <div class="card h-100">
                                        <div class="card-header py-1 small">Firma Responsable</div>
                                        <div class="card-body d-flex justify-content-center align-items-center p-2">
                                            <div class="preview-firma w-100" id="preview_responsable" onclick="abrirFirma('responsable', 'Responsable')">
                                                <small class="text-muted">Click para firmar</small>
                                            </div>
                                            <input type="hidden" name="base64_responsable" id="base64_responsable">
                                        </div>
                                    </div>
                                </div>
                                <div class="col-md-4">
                                    <div class="card h-100">
                                        <div class="card-header py-1 small">Firma Jefe</div>
                                        <div class="card-body d-flex justify-content-center align-items-center p-2">
                                            <div class="preview-firma w-100" id="preview_jefe" onclick="abrirFirma('jefe', 'Jefe Servicio')">
                                                <small class="text-muted">Click para firmar</small>
                                            </div>
                                            <input type="hidden" name="base64_jefe" id="base64_jefe">
                                        </div>
                                    </div>
                                </div>
                                <div class="col-md-4 d-flex align-items-center justify-content-center">
                                    <div class="text-muted small">Relevador: <strong><?php echo $relevador['nombre_completo']; ?></strong><br>Fecha: <?php echo date('d/m/Y'); ?></div>
                                </div>
                            </div>
                        </div>

                        <div class="d-grid mt-4"><button type="submit" class="btn btn-success btn-lg fw-bold">GUARDAR FICHA</button></div>
                    </div>
                </div>
            </div>
        </form>
    </div>

    <div class="modal fade" id="modalFirma" tabindex="-1" data-bs-backdrop="static">
        <div class="modal-dialog modal-fullscreen"> 
            <div class="modal-content"> 
                <div class="modal-header bg-dark text-white py-2 shadow">
                    <h6 class="modal-title">Firmar: <span id="lblRolFirma"></span></h6>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body bg-light d-flex align-items-center justify-content-center p-0">
                    <div id="canvasContainer">
                        <canvas id="signaturePad" style="width:100%; height:100%; display:block; touch-action: none;"></canvas>
                        <div class="firma-linea"></div>
                        <div class="sign-instruction">FIRME SOBRE LA LÍNEA</div>
                    </div>
                </div>
                <div class="modal-footer justify-content-center bg-white">
                    <button class="btn btn-outline-danger px-4 me-3 rounded-pill" onclick="limpiarFirma()">
                        <i class="fas fa-eraser me-2"></i>Borrar
                    </button>
                    <button class="btn btn-success px-5 fw-bold rounded-pill shadow" onclick="guardarFirma()">
                        <i class="fas fa-check me-2"></i>ACEPTAR
                    </button>
                </div>
            </div>
        </div>
    </div>

    <script src="https://code.jquery.com/jquery-3.7.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/signature_pad@4.0.0/dist/signature_pad.umd.min.js"></script>

    <script>
        const TIPOS_MAP = <?php echo json_encode($mapa_tipos); ?>;
        
        function selectType(idTipo, nombreTipo) {
            $('#id_tipo_bien_seleccionado').val(idTipo);
            $('#step-1').removeClass('active'); $('#step-2').addClass('active');
            $('#lblTipoSeleccionado').text(nombreTipo);
            $('#elemento').val('');
            cargarEstados(idTipo);
            
            $('#panel-matafuegos').hide();
            $('#render-campos').hide().empty();

            if (idTipo == TIPOS_MAP.matafuego) {
                $('#panel-matafuegos').show();
                cargarCombo('#mat_tipo_carga_id', 'get_agentes');
                cargarCombo('#mat_capacidad', 'get_capacidades');
                cargarCombo('#mat_clase_id', 'get_clases');
            } else {
                $('#render-campos').show();
                cargarCamposDinamicos(idTipo);
            }
        }

        // --- LÓGICA DE MODO RESPONSABLE/JEFE ---
        function toggleMode(rol, modo) {
            $('#input_nombre_' + rol).hide();
            $('#select_' + rol + '_wrapper').hide();
            $('#msg_' + rol + '_remoto').hide();
            
            if (modo === 'manual') {
                $('#input_nombre_' + rol).show();
                $('#preview_' + rol).removeClass('firma-disabled').attr('onclick', "abrirFirma('"+rol+"', '"+(rol==='jefe'?'Jefe Servicio':'Responsable')+"')");
                $('#preview_' + rol).html('<small class="text-muted">Click para firmar</small>');
            
            } else if (modo === 'sistema') {
                $('#select_' + rol + '_wrapper').show();
                $('#preview_' + rol).addClass('firma-disabled').removeAttr('onclick');
                $('#preview_' + rol).html('<small class="text-muted"><i class="fas fa-bell"></i> Notificación a Usuario</small>');
                $('#base64_' + rol).val(''); 

            } else if (modo === 'remoto') {
                $('#msg_' + rol + '_remoto').show();
                $('#preview_' + rol).addClass('firma-disabled').removeAttr('onclick');
                $('#preview_' + rol).html('<small class="text-muted"><i class="fas fa-link"></i> Link Remoto</small>');
                $('#base64_' + rol).val(''); 
            }
        }
        
        function cargarCamposDinamicos(idTipo) {
            $.ajax({
                url: 'ajax_obtener_campos_dinamicos.php',
                type: 'POST',
                data: { id_tipo_bien: idTipo },
                dataType: 'json',
                success: function(campos) {
                    var html = '';
                    var esIT = (idTipo == TIPOS_MAP.informatica);
                    var esCam = (idTipo == TIPOS_MAP.camara);
                    var esTel = (idTipo == TIPOS_MAP.telefono);

                    campos.forEach(function(c) {
                        var label = c.etiqueta.toUpperCase();
                        if (label.includes('IOSFA') || label.includes('PATRIMONIAL')) return;
                        
                        // --- CORRECCIÓN SOLICITADA: Renombrar etiqueta N° Serie ---
                        if (label === 'N° SERIE' || label === 'NUMERO SERIE' || label === 'NO. SERIE') {
                            c.etiqueta = "N° Serie Fabrica";
                        }
                        // --------------------------------------------------------

                        var name = 'dinamico[' + c.id_campo + ']';
                        var input = '';

                        if ((esIT || esCam || esTel) && label.includes('MARCA')) {
                            var ambito = esIT ? 'informatica' : (esCam ? 'camara' : 'telefono');
                            input = crearSelectDB(name, 'get_marcas', 'select-marca input-autoname', {ambito: ambito});
                        }
                        else if ((esIT || esCam || esTel) && label.includes('MODELO')) {
                            input = `<select name="${name}" class="form-select select-modelo input-autoname" disabled><option value="">Marca primero</option></select>`;
                        }
                        else if (esIT && (label.includes('TIPO') || label.includes('EQUIPO'))) {
                            input = crearSelectDB(name, 'get_tipos_it', 'select-tipo-it input-autoname');
                        }
                        else {
                            if (c.id_campo_dependencia && c.id_campo_dependencia > 0) {
                                input = `<select name="${name}" class="form-select input-autoname" data-depende-de="${c.id_campo_dependencia}" disabled><option value="">Seleccione la opción anterior...</option></select>`;
                            }
                            else if (c.opciones && c.opciones.length > 0) {
                                input = `<select name="${name}" class="form-select input-autoname"><option value="">-- Seleccionar --</option>`;
                                c.opciones.forEach(op => { input += `<option value="${op}">${op}</option>`; });
                                input += `</select>`;
                            } 
                            else {
                                var type = c.tipo_entrada === 'date' ? 'date' : 'text';
                                input = `<input type="${type}" name="${name}" class="form-control">`;
                            }
                        }
                        html += `<div class="col-md-6"><label class="small fw-bold text-muted">${c.etiqueta}</label>${input}</div>`;
                    });
                    $('#render-campos').html(html);
                    inicializarLogicaCombos();
                }
            });
        }

        function crearSelectDB(name, accion, classes, extraData={}) {
            let tempId = 'sel_' + Math.random().toString(36).substr(2, 9);
            setTimeout(() => {
                $.post('ajax_combos.php', { accion: accion, ...extraData }, function(data) {
                    let $sel = $('#'+tempId);
                    $sel.empty().append('<option value="">-- Seleccionar --</option>');
                    if(data) data.forEach(i => {
                        let val = i.capacidad || i.nombre;
                        $sel.append(`<option value="${val}" data-id="${i.id_config || i.id_marca || i.id_tipo_it}">${val}</option>`);
                    });
                }, 'json');
            }, 50);
            return `<select name="${name}" id="${tempId}" class="form-select ${classes}"><option>Cargando...</option></select>`;
        }

        function inicializarLogicaCombos() {
            $(document).on('change', '.select-marca', function() {
                let idMarca = $(this).find(':selected').data('id');
                let $mod = $('.select-modelo');
                if(!idMarca) { $mod.html('<option>Marca primero</option>').prop('disabled',true); return; }
                $.post('ajax_combos.php', { accion: 'get_modelos', id_marca: idMarca }, function(data) {
                    $mod.empty().append('<option value="">-- Seleccionar --</option>');
                    if(data) data.forEach(i => $mod.append(`<option value="${i.nombre}">${i.nombre}</option>`));
                    $mod.prop('disabled', false);
                }, 'json');
            });
            $(document).on('change', 'select[name^="dinamico"]', function() {
                let name = $(this).attr('name');
                let match = name.match(/dinamico\[(\d+)\]/);
                if (!match) return; 
                let idPadre = match[1];
                let valorPadre = $(this).val();
                let $hijos = $(`[data-depende-de="${idPadre}"]`);
                if ($hijos.length > 0) {
                    if (valorPadre) {
                        $hijos.each(function() {
                            let $child = $(this);
                            $child.html('<option>Cargando...</option>').prop('disabled', true);
                            let nameHijo = $child.attr('name');
                            let matchHijo = nameHijo.match(/dinamico\[(\d+)\]/);
                            if (!matchHijo) return;
                            let idHijo = matchHijo[1];
                            $.post('ajax_opciones_dinamicas.php', { accion: 'listar', id_campo: idHijo }, function(data) {
                                $child.empty().append('<option value="">-- Seleccionar --</option>');
                                if (data && data.length > 0) {
                                    data.forEach(d => { $child.append(`<option value="${d.valor}">${d.valor}</option>`); });
                                    $child.prop('disabled', false);
                                } else {
                                    $child.append('<option value="">(Sin opciones cargadas)</option>');
                                    $child.prop('disabled', false);
                                }
                            }, 'json');
                        });
                    } else {
                        $hijos.html('<option value="">Seleccione la opción anterior...</option>').prop('disabled', true);
                        $hijos.trigger('change'); 
                    }
                }
            });
            $(document).on('change input', '.input-autoname, #panel-matafuegos select', function() {
                let nombre = '';
                if ($('#panel-matafuegos').is(':visible')) {
                    nombre = 'MATAFUEGO';
                    let agente = $('#mat_tipo_carga_id option:selected').text();
                    let cap = $('#mat_capacidad option:selected').text();
                    if(agente && !agente.includes('--')) nombre += ' ' + agente;
                    if(cap && !cap.includes('--')) nombre += ' ' + cap;
                } else {
                    if ($('.select-tipo-it').is(':visible')) {
                        let tipo = $('.select-tipo-it option:selected').text();
                        let marca = $('.select-marca option:selected').text();
                        let modelo = $('.select-modelo option:selected').text();
                        if(tipo && !tipo.includes('--')) nombre += tipo + ' ';
                        if(marca && !marca.includes('--')) nombre += marca + ' ';
                        if(modelo && !modelo.includes('--')) nombre += modelo;
                    } else {
                        let cat = $('#lblTipoSeleccionado').text();
                        nombre = cat; 
                        $('select[name^="dinamico"]').each(function() {
                             let val = $(this).val();
                             if(val && !val.includes('--')) nombre += ' ' + val;
                        });
                    }
                }
                $('#elemento').val(nombre.trim().toUpperCase());
            });
        }

        function resetWizard() { $('#step-2').removeClass('active'); $('#step-1').addClass('active'); $('#formInventario')[0].reset(); $('#render-campos').empty(); }
        $('#id_destino').change(function() {
            let id = $(this).val();
            let $area = $('#servicio_ubicacion').empty().append('<option>Cargando...</option>').prop('disabled', true);
            $.getJSON('ajax_obtener_areas.php', { id_destino: id }, function(data) {
                $area.empty();
                if(data && data.length > 0) { $area.append('<option value="">Seleccione Área</option>'); data.forEach(item => $area.append(new Option(item.nombre, item.nombre))); $area.prop('disabled', false).prop('required', true); } 
                else { $area.append('<option value="General">General (Sin áreas)</option>'); $area.prop('disabled', false).prop('required', false); $area.val('General'); }
            });
        });
        function cargarCombo(selector, accion) { $.post('ajax_combos.php', { accion: accion }, function(data) { let $sel = $(selector); $sel.empty().append('<option value="">-- Seleccionar --</option>'); if(data) data.forEach(i => { let val = i.capacidad || i.nombre; $sel.append(`<option value="${val}">${val}</option>`); }); }, 'json'); }
        function cargarEstados(idTipo) { $.post('ajax_combos.php', { accion: 'get_estados', id_tipo_bien: idTipo }, function(data) { let $sel = $('#id_estado').empty(); data.forEach(est => $sel.append(`<option value="${est.id_estado}">${est.nombre}</option>`)); }, 'json'); }

        $('.select2').select2({ theme: 'bootstrap-5' });
        inicializarLogicaCombos();

        let signaturePad = null; 
        let rolActivo = '';
        const modalFirma = new bootstrap.Modal(document.getElementById('modalFirma'));

        function abrirFirma(rol, titulo) { 
            if ($('#preview_' + rol).hasClass('firma-disabled')) return;
            rolActivo = rol; 
            $('#lblRolFirma').text(titulo); 
            modalFirma.show(); 
        }

        document.getElementById('modalFirma').addEventListener('shown.bs.modal', function() { 
            let canvas = document.getElementById('signaturePad');
            let container = document.getElementById('canvasContainer');
            var ratio = Math.max(window.devicePixelRatio || 1, 1);
            canvas.width = container.offsetWidth * ratio;
            canvas.height = container.offsetHeight * ratio;
            canvas.getContext("2d").scale(ratio, ratio);
            if (signaturePad) signaturePad.clear();
            signaturePad = new SignaturePad(canvas, { minWidth: 1, maxWidth: 2.5, penColor: "rgb(0, 0, 0)", velocityFilterWeight: 0.7 });
        });

        function limpiarFirma() { if(signaturePad) signaturePad.clear(); }
        function guardarFirma() {
            if (!signaturePad || signaturePad.isEmpty()) return alert('Debe firmar sobre la línea para aceptar.');
            let data = signaturePad.toDataURL('image/png');
            $('#base64_' + rolActivo).val(data);
            $('#preview_' + rolActivo).html(`<img src="${data}" style="max-height:100%; max-width:100%;">`).css({'background-color': '#e8f5e9', 'border-color': '#28a745'});
            modalFirma.hide();
        }
    </script>
</body>
</html>