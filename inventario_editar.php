<?php
// Archivo: inventario_editar.php
// OBJETIVO: Edición completa + Modal de Firma con solicitud de Nombre y Email para Token.
session_start();
include 'conexion.php';
include 'funciones_permisos.php';

if (!isset($_SESSION['usuario_id']) || !tiene_permiso('acceso_inventario', $pdo)) {
    header("Location: dashboard.php"); exit();
}

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$id_usuario_actual = $_SESSION['usuario_id'];

// Obtener datos del bien
$stmt = $pdo->prepare("SELECT i.*, t.nombre as nombre_tipo FROM inventario_cargos i LEFT JOIN inventario_tipos_bien t ON i.id_tipo_bien = t.id_tipo_bien WHERE i.id_cargo = ?");
$stmt->execute([$id]);
$bien = $stmt->fetch(PDO::FETCH_ASSOC);
if (!$bien) die("Bien no encontrado.");

$id_tipo_bien = $bien['id_tipo_bien'];
$es_matafuego = (stripos($bien['nombre_tipo'], 'matafuego') !== false);
$destinos = $pdo->query("SELECT * FROM destinos_internos ORDER BY nombre ASC")->fetchAll(PDO::FETCH_ASSOC);

$stmtEstados = $pdo->prepare("SELECT * FROM inventario_estados WHERE id_tipo_bien = ? OR id_tipo_bien IS NULL OR id_estado = ? ORDER BY nombre ASC");
$stmtEstados->execute([$id_tipo_bien, $bien['id_estado_fk']]);
$estados = $stmtEstados->fetchAll(PDO::FETCH_ASSOC);

$usuarios_sistema = $pdo->query("SELECT id_usuario, nombre_completo FROM usuarios ORDER BY nombre_completo ASC")->fetchAll(PDO::FETCH_ASSOC);

$sqlDyn = "SELECT id_campo, valor FROM inventario_valores_dinamicos WHERE id_cargo = ?";
$stmtDyn = $pdo->prepare($sqlDyn);
$stmtDyn->execute([$id]);
$valores_existentes = $stmtDyn->fetchAll(PDO::FETCH_KEY_PAIR); 

$nombre_tipo = mb_strtolower($bien['nombre_tipo']);
$ambito_js = '';
if (strpos($nombre_tipo, 'informática') !== false || strpos($nombre_tipo, 'informatica') !== false) $ambito_js = 'informatica';
elseif (strpos($nombre_tipo, 'cámara') !== false || strpos($nombre_tipo, 'camara') !== false) $ambito_js = 'camara';
elseif (strpos($nombre_tipo, 'teléfono') !== false || strpos($nombre_tipo, 'telefono') !== false) $ambito_js = 'telefono';

// Lógica de Firmas
$soy_responsable = ($bien['id_responsable'] == $id_usuario_actual);
$soy_jefe = ($bien['id_jefe'] == $id_usuario_actual);
$falta_firma_resp = empty($bien['firma_responsable_path']);
$falta_firma_jefe = empty($bien['firma_jefe_path']);
$debo_firmar = ($soy_responsable && $falta_firma_resp) || ($soy_jefe && $falta_firma_jefe);
$mi_rol_firma = ($soy_responsable && $falta_firma_resp) ? 'responsable' : 'jefe';
$titulo_modal_firma = ($mi_rol_firma == 'responsable') ? 'Firma de Responsable' : 'Firma de Jefe de Servicio';
$modo_resp = !empty($bien['id_responsable']) ? 'sistema' : 'manual';
$modo_jefe = !empty($bien['id_jefe']) ? 'sistema' : 'manual';
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Editar Bien | Inventario</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />
    <link href="https://cdn.jsdelivr.net/npm/select2-bootstrap-5-theme@1.3.0/dist/select2-bootstrap-5-theme.min.css" rel="stylesheet" />
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>
<body class="bg-light">
    <?php include 'navbar.php'; ?>

    <div class="container mt-4 mb-5">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <div>
                <h2 class="mb-0"><i class="fas fa-edit text-primary"></i> Editar: <?php echo htmlspecialchars($bien['elemento']); ?></h2>
                <span class="badge bg-secondary"><?php echo htmlspecialchars($bien['nombre_tipo']); ?></span>
            </div>
            <a href="inventario_lista.php" class="btn btn-secondary"><i class="fas fa-arrow-left"></i> Volver</a>
        </div>

        <?php if($debo_firmar): ?>
            <div class="alert alert-primary shadow-sm border-0 d-flex align-items-center justify-content-between">
                <div>
                    <h5 class="fw-bold mb-0"><i class="fas fa-pen-fancy me-2"></i> Firma Pendiente</h5>
                    <small>Se requiere su firma digital autenticada para validar este cargo.</small>
                </div>
                <button class="btn btn-primary fw-bold px-4 rounded-pill" onclick="abrirModalFirmaSistema()">
                    <i class="fas fa-file-signature me-2"></i> FIRMAR AHORA
                </button>
            </div>
        <?php endif; ?>

        <form action="inventario_guardar.php" method="POST" enctype="multipart/form-data">
            <input type="hidden" name="id_cargo" value="<?php echo $id; ?>">
            <input type="hidden" name="accion" value="editar"> 
            <input type="hidden" name="id_tipo_bien_seleccionado" value="<?php echo $id_tipo_bien; ?>">
            <span id="lblTipoSeleccionado" style="display:none;"><?php echo htmlspecialchars($bien['nombre_tipo']); ?></span>

            <div class="row g-4">
                <div class="col-md-6">
                    <div class="card shadow h-100">
                        <div class="card-header bg-dark text-white fw-bold">Datos Generales</div>
                        <div class="card-body">
                            <div class="mb-3">
                                <label class="form-label fw-bold">Nombre del Elemento / Bien</label>
                                <input type="text" name="elemento" id="elemento" class="form-control fw-bold" value="<?php echo htmlspecialchars($bien['elemento']); ?>" required>
                                <small class="text-muted" style="font-size: 0.8rem;">* Se actualizará automáticamente si cambia las características.</small>
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
                                            <option value="<?php echo $e['id_estado']; ?>" <?php if($bien['id_estado_fk'] == $e['id_estado']) echo 'selected'; ?>><?php echo htmlspecialchars($e['nombre']); ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <div class="col-md-6 mb-3">
                                    <label class="form-label fw-bold">Ubicación Principal</label>
                                    <select name="id_destino" id="id_destino" class="form-select select2">
                                        <option value="">-- Seleccionar --</option>
                                        <?php foreach($destinos as $d): ?>
                                            <option value="<?php echo $d['id_destino']; ?>" <?php if($bien['destino_principal'] == $d['id_destino']) echo 'selected'; ?>><?php echo htmlspecialchars($d['nombre']); ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                            </div>
                            <div class="mb-3">
                                <label class="form-label fw-bold">Area / Servicio</label>
                                <select name="servicio_ubicacion" id="servicio_ubicacion" class="form-select select2" required>
                                    <option value="<?php echo htmlspecialchars($bien['servicio_ubicacion']); ?>"><?php echo htmlspecialchars($bien['servicio_ubicacion']); ?></option>
                                </select>
                            </div>

                            <hr class="my-3">
                            <h6 class="fw-bold text-primary">Asignación de Responsables</h6>

                            <div class="mb-3 p-2 border rounded bg-light">
                                <div class="d-flex justify-content-between">
                                    <label class="form-label fw-bold small">Responsable:</label>
                                    <?php if(!empty($bien['firma_responsable_path'])): ?>
                                        <span class="badge bg-success"><i class="fas fa-check"></i> Firmado</span>
                                    <?php else: ?>
                                        <span class="badge bg-warning text-dark">Pendiente</span>
                                    <?php endif; ?>
                                </div>
                                <div class="btn-group w-100 btn-group-sm mb-2">
                                    <input type="radio" class="btn-check" name="modo_responsable" id="rm_manual" value="manual" <?php echo ($modo_resp=='manual')?'checked':''; ?> onchange="cambiarModo('resp', 'manual')">
                                    <label class="btn btn-outline-secondary" for="rm_manual">Manual</label>
                                    <input type="radio" class="btn-check" name="modo_responsable" id="rm_sistema" value="sistema" <?php echo ($modo_resp=='sistema')?'checked':''; ?> onchange="cambiarModo('resp', 'sistema')">
                                    <label class="btn btn-outline-secondary" for="rm_sistema">Sistema</label>
                                    <input type="radio" class="btn-check" name="modo_responsable" id="rm_remoto" value="remoto" onchange="cambiarModo('resp', 'remoto')">
                                    <label class="btn btn-outline-secondary" for="rm_remoto">Firma Remota</label>
                                </div>
                                <input type="text" name="nombre_responsable" id="input_resp_manual" class="form-control" value="<?php echo htmlspecialchars($bien['nombre_responsable']); ?>" <?php if($modo_resp!='manual') echo 'style="display:none;"'; ?>>
                                <div id="div_resp_sistema" <?php if($modo_resp!='sistema') echo 'style="display:none;"'; ?>>
                                    <select name="id_responsable_sistema" class="form-select select2" style="width:100%">
                                        <option value="">-- Seleccionar --</option>
                                        <?php foreach($usuarios_sistema as $u): ?>
                                            <option value="<?php echo $u['id_usuario']; ?>" <?php if($bien['id_responsable'] == $u['id_usuario']) echo 'selected'; ?>><?php echo htmlspecialchars($u['nombre_completo']); ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <div id="div_resp_remoto" style="display:none;" class="bg-white p-2 border rounded">
                                    <button type="button" class="btn btn-sm btn-info text-white w-100 mb-2" onclick="generarLink('responsable')"><i class="fas fa-link me-1"></i>Generar Link de Firma</button>
                                    <input type="text" id="link_responsable" class="form-control form-control-sm" readonly placeholder="El link aparecerá aquí...">
                                </div>
                            </div>

                            <div class="mb-3 p-2 border rounded bg-light">
                                <div class="d-flex justify-content-between">
                                    <label class="form-label fw-bold small">Jefe Servicio:</label>
                                    <?php if(!empty($bien['firma_jefe_path'])): ?>
                                        <span class="badge bg-success"><i class="fas fa-check"></i> Firmado</span>
                                    <?php else: ?>
                                        <span class="badge bg-warning text-dark">Pendiente</span>
                                    <?php endif; ?>
                                </div>
                                <div class="btn-group w-100 btn-group-sm mb-2">
                                    <input type="radio" class="btn-check" name="modo_jefe" id="jm_manual" value="manual" <?php echo ($modo_jefe=='manual')?'checked':''; ?> onchange="cambiarModo('jefe', 'manual')">
                                    <label class="btn btn-outline-secondary" for="jm_manual">Manual</label>
                                    <input type="radio" class="btn-check" name="modo_jefe" id="jm_sistema" value="sistema" <?php echo ($modo_jefe=='sistema')?'checked':''; ?> onchange="cambiarModo('jefe', 'sistema')">
                                    <label class="btn btn-outline-secondary" for="jm_sistema">Sistema</label>
                                    <input type="radio" class="btn-check" name="modo_jefe" id="jm_remoto" value="remoto" onchange="cambiarModo('jefe', 'remoto')">
                                    <label class="btn btn-outline-secondary" for="jm_remoto">Firma Remota</label>
                                </div>
                                <input type="text" name="nombre_jefe_servicio" id="input_jefe_manual" class="form-control" value="<?php echo htmlspecialchars($bien['nombre_jefe_servicio']); ?>" <?php if($modo_jefe!='manual') echo 'style="display:none;"'; ?>>
                                <div id="div_jefe_sistema" <?php if($modo_jefe!='sistema') echo 'style="display:none;"'; ?>>
                                    <select name="id_jefe_sistema" class="form-select select2" style="width:100%">
                                        <option value="">-- Seleccionar --</option>
                                        <?php foreach($usuarios_sistema as $u): ?>
                                            <option value="<?php echo $u['id_usuario']; ?>" <?php if($bien['id_jefe'] == $u['id_usuario']) echo 'selected'; ?>><?php echo htmlspecialchars($u['nombre_completo']); ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <div id="div_jefe_remoto" style="display:none;" class="bg-white p-2 border rounded">
                                    <button type="button" class="btn btn-sm btn-info text-white w-100 mb-2" onclick="generarLink('jefe')"><i class="fas fa-link me-1"></i>Generar Link de Firma</button>
                                    <input type="text" id="link_jefe" class="form-control form-control-sm" readonly placeholder="El link aparecerá aquí...">
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
                            <div id="render-campos" class="row g-3">
                                <div class="col-12 text-center py-5"><div class="spinner-border text-primary"></div><br>Cargando datos...</div>
                            </div>
                            <?php if ($es_matafuego): ?>
                                <div class="alert alert-danger mt-4" id="panel-matafuegos">
                                    <i class="fas fa-fire-extinguisher me-2"></i><strong>Datos de Matafuegos</strong>
                                    <div class="row g-2 mt-2">
                                        <div class="col-md-6 mb-2"><label class="form-label small">Capacidad</label><input type="text" name="mat_capacidad" class="form-control" value="<?php echo $bien['mat_capacidad']; ?>"></div>
                                        <div class="col-md-6 mb-2"><label class="form-label small">N° Grabado</label><input type="text" name="mat_numero_grabado" class="form-control" value="<?php echo $bien['mat_numero_grabado']; ?>"></div>
                                        <div class="col-md-6 mb-2"><label class="form-label small">Fecha Carga</label><input type="date" name="mat_fecha_carga" class="form-control" value="<?php echo $bien['mat_fecha_carga']; ?>"></div>
                                        <div class="col-md-6 mb-2"><label class="form-label small">Fecha PH</label><input type="date" name="mat_fecha_ph" class="form-control" value="<?php echo $bien['mat_fecha_ph']; ?>"></div>
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

    <div class="modal fade" id="modalFirmaSistema" tabindex="-1" data-bs-backdrop="static">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header bg-primary text-white">
                    <h5 class="modal-title"><i class="fas fa-file-contract me-2"></i> <?php echo $titulo_modal_firma ?? 'Firma Digital'; ?></h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body text-center p-4">
                    
                    <div id="step-firma-1">
                        <i class="fas fa-user-shield fa-4x text-primary mb-3"></i>
                        <h4>Autenticación de Firma</h4>
                        <p class="lead">Confirme sus datos para enviar el código de seguridad.</p>
                        
                        <div class="card bg-light border-0 my-3 p-3 text-start">
                            <div class="mb-3">
                                <label class="fw-bold">Nombre Completo:</label>
                                <input type="text" id="input_nombre_token" class="form-control" value="<?php echo htmlspecialchars($_SESSION['nombre_usuario'] ?? ''); ?>" placeholder="Su Nombre">
                            </div>
                            <div class="mb-3">
                                <label class="fw-bold">Correo Electrónico (Registrado):</label>
                                <input type="email" id="input_email_token" class="form-control" placeholder="ejemplo@correo.com">
                            </div>
                        </div>

                        <div class="d-grid gap-2 mt-4">
                            <button class="btn btn-primary btn-lg fw-bold" onclick="solicitarTokenFirma()">
                                <i class="fas fa-paper-plane me-2"></i> ENVIAR CÓDIGO DE VALIDACIÓN
                            </button>
                        </div>
                    </div>

                    <div id="step-firma-2" style="display:none;">
                        <i class="fas fa-envelope-open-text fa-4x text-success mb-3"></i>
                        <h4>¡Código Enviado!</h4>
                        <p>Revise su correo electrónico e ingrese el código de 6 dígitos.</p>
                        
                        <div class="col-md-6 mx-auto my-4">
                            <input type="text" id="input_token_firma" class="form-control form-control-lg text-center fw-bold" placeholder="000000" maxlength="6" style="letter-spacing: 5px; font-size: 1.5rem;">
                        </div>

                        <button type="button" class="btn btn-secondary me-2" onclick="volverPaso1()">Atrás</button>
                        <button type="button" class="btn btn-success fw-bold px-4" id="btnConfirmarFirma" onclick="firmarComoSistema()">
                            <i class="fas fa-pen-nib me-2"></i> CONFIRMAR Y FIRMAR
                        </button>
                    </div>

                </div>
            </div>
        </div>
    </div>
    
    <script src="https://code.jquery.com/jquery-3.7.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>
    
    <script>
        const ID_TIPO_BIEN = <?php echo $id_tipo_bien; ?>;
        const VALORES_DB = <?php echo json_encode($valores_existentes); ?>;
        const AMBITO_JS = '<?php echo $ambito_js; ?>';
        const VALOR_AREA_ACTUAL = '<?php echo htmlspecialchars($bien['servicio_ubicacion']); ?>';
        let isInitializing = true;

        $('.select2').select2({ theme: 'bootstrap-5' });

        $(document).ready(function() {
            let $destino = $('#id_destino');
            cargarAreas($destino.val(), VALOR_AREA_ACTUAL);
            $destino.change(function() { cargarAreas($(this).val(), ''); });
            cargarCamposDinamicosEdicion(ID_TIPO_BIEN);
            setTimeout(() => { isInitializing = false; }, 2500);
        });

        // --- FUNCIONES DEL MODAL DE FIRMA ---
        const debeFirmar = <?php echo $debo_firmar ? 'true' : 'false'; ?>;
        if(debeFirmar) { 
            $(document).ready(function() { 
                var m = new bootstrap.Modal(document.getElementById('modalFirmaSistema')); 
                m.show(); 
            }); 
        }
        
        function abrirModalFirmaSistema() { 
            var m = new bootstrap.Modal(document.getElementById('modalFirmaSistema')); 
            m.show(); 
        }

        function solicitarTokenFirma() {
            let email = $('#input_email_token').val();
            let nombre = $('#input_nombre_token').val();

            if(!email || !email.includes('@')) { return alert('Por favor ingrese un correo válido.'); }
            if(!nombre) { return alert('Por favor ingrese su nombre.'); }

            $('#step-firma-1 button').prop('disabled', true).html('<i class="fas fa-spinner fa-spin"></i> Enviando...');
            
            $.post('ajax_generar_token_firma.php', { email: email, nombre: nombre }, function(res) {
                if(res.status === 'success') {
                    $('#step-firma-1').hide();
                    $('#step-firma-2').fadeIn();
                    $('#input_token_firma').focus();
                } else {
                    alert('Error: ' + res.msg);
                    $('#step-firma-1 button').prop('disabled', false).html('<i class="fas fa-paper-plane me-2"></i> ENVIAR CÓDIGO DE VALIDACIÓN');
                }
            }, 'json').fail(function() {
                alert('Error de conexión con el servidor.');
                $('#step-firma-1 button').prop('disabled', false).html('<i class="fas fa-paper-plane me-2"></i> ENVIAR CÓDIGO DE VALIDACIÓN');
            });
        }

        function volverPaso1() {
            $('#step-firma-2').hide();
            $('#step-firma-1').fadeIn();
            $('#step-firma-1 button').prop('disabled', false).html('<i class="fas fa-paper-plane me-2"></i> ENVIAR CÓDIGO DE VALIDACIÓN');
        }

        function firmarComoSistema() {
            let idCargo = <?php echo $id; ?>;
            let rol = '<?php echo $mi_rol_firma ?? ''; ?>';
            let token = $('#input_token_firma').val();

            if(!rol) return alert("Error de rol.");
            if(token.length < 6) return alert("Ingrese el código completo.");

            $('#btnConfirmarFirma').prop('disabled', true).html('<i class="fas fa-spinner fa-spin"></i> Verificando...');

            $.post('ajax_firmar_cargo.php', { 
                id_cargo: idCargo, 
                rol: rol, 
                tipo_origen: 'registrada',
                token: token 
            }, function(res) {
                if(res.status === 'success') {
                    alert('¡Firma verificada y registrada con éxito!');
                    location.reload(); 
                } else {
                    alert("Error: " + res.msg);
                    $('#btnConfirmarFirma').prop('disabled', false).html('<i class="fas fa-pen-nib me-2"></i> CONFIRMAR Y FIRMAR');
                }
            }, 'json').fail(function() {
                alert('Error de conexión.');
                $('#btnConfirmarFirma').prop('disabled', false).html('<i class="fas fa-pen-nib me-2"></i> CONFIRMAR Y FIRMAR');
            });
        }

        // --- FUNCIONES DE COMBOS (MANTENIDAS) ---
        function cargarCamposDinamicosEdicion(idTipo) {
            $.ajax({
                url: 'ajax_obtener_campos_dinamicos.php',
                type: 'POST',
                data: { id_tipo_bien: idTipo },
                dataType: 'json',
                success: function(campos) {
                    var html = '';
                    var esIT = (AMBITO_JS === 'informatica');
                    var esCam = (AMBITO_JS === 'camara');
                    var esTel = (AMBITO_JS === 'telefono');
                    var pendingPromises = []; 
                    
                    campos.forEach(function(c) {
                        var label = c.etiqueta.toUpperCase();
                        if (label.includes('IOSFA') || label.includes('PATRIMONIAL')) return;
                        if (label === 'N° SERIE' || label === 'NUMERO SERIE' || label === 'NO. SERIE') { c.etiqueta = "N° Serie Fabrica"; }

                        var name = 'dinamico[' + c.id_campo + ']';
                        var valorActual = VALORES_DB[c.id_campo] || '';
                        var input = '';

                        if ((esIT || esCam || esTel) && label.includes('MARCA')) {
                            input = `<select name="${name}" id="dyn_${c.id_campo}" class="form-select select-marca input-autoname"><option>Cargando...</option></select>`;
                            pendingPromises.push(crearSelectDB_Promise(`#dyn_${c.id_campo}`, 'get_marcas', {ambito: AMBITO_JS}, valorActual));
                        }
                        else if ((esIT || esCam || esTel) && label.includes('MODELO')) {
                            input = `<select name="${name}" class="form-select select-modelo input-autoname" data-valor-inicial="${valorActual}" disabled><option value="">Marca primero</option></select>`;
                        }
                        else if (esIT && (label.includes('TIPO') || label.includes('EQUIPO'))) {
                            input = `<select name="${name}" id="dyn_${c.id_campo}" class="form-select select-tipo-it input-autoname"><option>Cargando...</option></select>`;
                            pendingPromises.push(crearSelectDB_Promise(`#dyn_${c.id_campo}`, 'get_tipos_it', {}, valorActual));
                        }
                        else {
                            if (c.id_campo_dependencia && c.id_campo_dependencia > 0) {
                                input = `<select name="${name}" class="form-select input-autoname" data-depende-de="${c.id_campo_dependencia}" data-valor-inicial="${valorActual}" disabled><option value="">Seleccione la opción anterior...</option></select>`;
                            }
                            else if (c.opciones && c.opciones.length > 0) {
                                input = `<select name="${name}" class="form-select input-autoname"><option value="">-- Seleccionar --</option>`;
                                c.opciones.forEach(op => { 
                                    let sel = compararValores(op, valorActual) ? 'selected' : '';
                                    input += `<option value="${op}" ${sel}>${op}</option>`; 
                                });
                                input += `</select>`;
                            } 
                            else {
                                var type = c.tipo_entrada === 'date' ? 'date' : 'text';
                                input = `<input type="${type}" name="${name}" class="form-control" value="${valorActual}">`;
                            }
                        }
                        html += `<div class="col-md-6"><label class="small fw-bold text-muted">${c.etiqueta}</label>${input}</div>`;
                    });

                    $('#render-campos').html(html);
                    inicializarLogicaCombos();

                    $.when.apply($, pendingPromises).done(function() {
                        $('.select-marca').each(function() { if($(this).val()) $(this).trigger('change'); });
                        $('select[name^="dinamico"]').each(function() { if($(this).val()) $(this).trigger('change'); });
                    });
                }
            });
        }

        function crearSelectDB_Promise(selectorId, accion, extraData, valorSeleccionado) {
            return $.post('ajax_combos.php', { accion: accion, ...extraData }, function(data) {
                let $sel = $(selectorId);
                $sel.empty().append('<option value="">-- Seleccionar --</option>');
                if(data) {
                    data.forEach(i => {
                        let val = i.capacidad || i.nombre;
                        let idData = i.id_config || i.id_marca || i.id_tipo_it; 
                        let isSelected = compararValores(val, valorSeleccionado) ? 'selected' : '';
                        $sel.append(`<option value="${val}" data-id="${idData}" ${isSelected}>${val}</option>`);
                    });
                    if(valorSeleccionado) {
                        $sel.find('option').each(function() { if(compararValores($(this).val(), valorSeleccionado)) { $sel.val($(this).val()); } });
                    }
                }
            }, 'json');
        }

        function compararValores(a, b) {
            if(!a || !b) return false;
            return String(a).trim().toUpperCase() === String(b).trim().toUpperCase();
        }

        function inicializarLogicaCombos() {
            $(document).on('change', '.select-marca', function() {
                let idMarca = $(this).find(':selected').data('id');
                let $mod = $('.select-modelo');
                let valorPre = $mod.data('valor-inicial');
                if(!idMarca) { $mod.html('<option>Marca primero</option>').prop('disabled',true); return; }
                $.post('ajax_combos.php', { accion: 'get_modelos', id_marca: idMarca }, function(data) {
                    $mod.empty().append('<option value="">-- Seleccionar --</option>');
                    if(data) {
                        data.forEach(i => {
                            let selected = compararValores(i.nombre, valorPre) ? 'selected' : '';
                            $mod.append(`<option value="${i.nombre}" ${selected}>${i.nombre}</option>`);
                        });
                        if(valorPre) {
                             $mod.find('option').each(function() { if(compararValores($(this).val(), valorPre)) $mod.val($(this).val()); });
                             $mod.data('valor-inicial', ''); 
                        }
                        $mod.prop('disabled', false);
                    }
                }, 'json');
            });
            $(document).on('change', 'select[name^="dinamico"]', function() {
                let name = $(this).attr('name');
                if(!name) return;
                let match = name.match(/dinamico\[(\d+)\]/);
                if (!match) return; 
                let idPadre = match[1];
                let valorPadre = $(this).val();
                let $hijos = $(`[data-depende-de="${idPadre}"]`);
                if ($hijos.length > 0) {
                    if (valorPadre) {
                        $hijos.each(function() {
                            let $child = $(this);
                            let valorHijoPre = $child.data('valor-inicial');
                            $child.html('<option>Cargando...</option>').prop('disabled', true);
                            let nameHijo = $child.attr('name');
                            let matchHijo = nameHijo.match(/dinamico\[(\d+)\]/);
                            if (!matchHijo) return;
                            let idHijo = matchHijo[1];
                            $.post('ajax_opciones_dinamicas.php', { accion: 'listar', id_campo: idHijo }, function(data) {
                                $child.empty().append('<option value="">-- Seleccionar --</option>');
                                if (data && data.length > 0) {
                                    data.forEach(d => { 
                                        let sel = compararValores(d.valor, valorHijoPre) ? 'selected' : '';
                                        $child.append(`<option value="${d.valor}" ${sel}>${d.valor}</option>`); 
                                    });
                                    if(valorHijoPre) {
                                        $child.find('option').each(function() { if(compararValores($(this).val(), valorHijoPre)) $child.val($(this).val()); });
                                        $child.data('valor-inicial', '');
                                    }
                                    $child.prop('disabled', false);
                                } else { $child.append('<option value="">(Sin opciones)</option>'); $child.prop('disabled', false); }
                            }, 'json');
                        });
                    } else { $hijos.html('<option value="">Seleccione la opción anterior...</option>').prop('disabled', true); }
                }
            });
            $(document).on('change', '.input-autoname, #panel-matafuegos select', function(e) {
                if (isInitializing && !e.originalEvent) return; 
                let nombre = '';
                if ($('.select-tipo-it').length > 0) {
                    let tipo = $('.select-tipo-it option:selected').text();
                    let marca = $('.select-marca option:selected').text();
                    let modelo = $('.select-modelo option:selected').text();
                    if(tipo && !tipo.includes('--') && !tipo.includes('Cargando')) nombre += tipo + ' ';
                    if(marca && !marca.includes('--') && !marca.includes('Cargando')) nombre += marca + ' ';
                    if(modelo && !modelo.includes('--') && !modelo.includes('Marca primero')) nombre += modelo;
                } else {
                    let cat = $('#lblTipoSeleccionado').text();
                    nombre = cat; 
                    $('select[name^="dinamico"]').each(function() {
                         let val = $(this).val();
                         if(val && !val.includes('--') && !val.includes('Cargando')) nombre += ' ' + val;
                    });
                }
                if(nombre.trim().length > 3) { $('#elemento').val(nombre.trim().toUpperCase()); }
            });
        }
        function cargarAreas(idDestino, areaSeleccionada) {
            let $area = $('#servicio_ubicacion');
            if(!idDestino) { $area.empty().append('<option value="">(Seleccione Destino)</option>').prop('disabled', true); return; }
            $area.empty().append('<option>Cargando...</option>').prop('disabled', true);
            $.getJSON('ajax_obtener_areas.php', { id_destino: idDestino }, function(data) {
                $area.empty();
                if(data && data.length > 0) {
                    $area.append('<option value="">Seleccione Área</option>');
                    data.forEach(item => {
                        let sel = (item.nombre == areaSeleccionada) ? 'selected' : '';
                        $area.append(`<option value="${item.nombre}" ${sel}>${item.nombre}</option>`);
                    });
                    $area.prop('disabled', false);
                } else { $area.append('<option value="General">General (Sin áreas)</option>'); $area.prop('disabled', false); if(!areaSeleccionada || areaSeleccionada == 'General') $area.val('General'); }
            });
        }
        function cambiarModo(rol, modo) {
            $('#input_'+rol+'_manual').hide(); $('#div_'+rol+'_sistema').hide(); $('#div_'+rol+'_remoto').hide();
            if(modo === 'manual') $('#input_'+rol+'_manual').show();
            if(modo === 'sistema') $('#div_'+rol+'_sistema').show();
            if(modo === 'remoto') $('#div_'+rol+'_remoto').show();
        }
        function generarLink(rol) {
            let idCargo = <?php echo $id; ?>;
            $.post('ajax_link_remoto.php', { accion:'generar', id_cargo: idCargo, rol: rol }, function(res) {
                if(res.status === 'success') { $('#link_'+rol).val(res.link).select(); } 
                else { alert("Error: " + res.msg); }
            }, 'json');
        }
    </script>
</body>
</html>