<?php
// Archivo: inventario_nuevo.php (100% BASE DE DATOS - SIN TEXTOS FIJOS)
session_start();
include 'conexion.php';
include 'funciones_permisos.php'; 

if (!isset($_SESSION['usuario_id']) || !tiene_permiso('acceso_inventario', $pdo)) {
    header("Location: dashboard.php"); exit();
}

// 1. CARGA DE CONFIGURACIONES (Tipos de Bien)
try {
    $tipos_bien_db = $pdo->query("SELECT * FROM inventario_tipos_bien ORDER BY id_tipo_bien ASC")->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) { $tipos_bien_db = []; }

// 2. CARGAR CAMPOS DINÁMICOS
$sql_campos = "SELECT * FROM inventario_campos_dinamicos ORDER BY id_tipo_bien, orden ASC";
try {
    $raw_campos = $pdo->query($sql_campos)->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) { $raw_campos = []; }

$campos_por_tipo = [];
foreach($raw_campos as $c) {
    $campos_por_tipo[$c['id_tipo_bien']][] = $c;
}

// 3. CONFIGURACIÓN MATAFUEGOS (SOLO DB)
// A. Tipos de Agente
try {
    // Busca directamente en la tabla de configuración. Si agregas uno nuevo en la BD, aparece acá.
    $tipos_mat = $pdo->query("SELECT id_config, tipo_carga FROM inventario_config_matafuegos ORDER BY tipo_carga ASC")->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) { $tipos_mat = []; }

// B. Clases de Fuego
try {
    $clases_fuego = $pdo->query("SELECT * FROM inventario_config_clases ORDER BY nombre ASC")->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) { $clases_fuego = []; }

// C. Capacidades (HISTORIAL DB)
// Busca todas las capacidades diferentes que ya se hayan guardado en el sistema alguna vez.
try {
    $capacidades_db = $pdo->query("SELECT DISTINCT mat_capacidad FROM inventario_cargos WHERE mat_capacidad IS NOT NULL AND mat_capacidad != '' ORDER BY mat_capacidad ASC")->fetchAll(PDO::FETCH_COLUMN);
} catch (Exception $e) { $capacidades_db = []; }


// DATOS GENERALES
$destinos = $pdo->query("SELECT * FROM destinos_internos ORDER BY nombre ASC")->fetchAll(PDO::FETCH_ASSOC);
$estados = $pdo->query("SELECT * FROM inventario_estados ORDER BY nombre ASC")->fetchAll(PDO::FETCH_ASSOC);

$id_rel = $_SESSION['usuario_id'];
$relevador = $pdo->query("SELECT nombre_completo, firma_imagen_path FROM usuarios WHERE id_usuario = $id_rel")->fetch(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Nuevo Cargo</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />
    <link href="https://cdn.jsdelivr.net/npm/select2-bootstrap-5-theme@1.3.0/dist/select2-bootstrap-5-theme.min.css" rel="stylesheet" />
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        .step-container { display: none; }
        .step-container.active { display: block; animation: fadeIn 0.3s; }
        .selection-card { cursor: pointer; transition: all 0.2s; border: 2px solid #dee2e6; }
        .selection-card:hover { transform: translateY(-5px); border-color: #0d6efd; }
        .preview-firma { height: 90px; border: 2px dashed #ccc; display: flex; align-items: center; justify-content: center; cursor: pointer; }
        .preview-firma img { max-height: 100%; }
        #canvasContainer { width: 100%; height: 100%; background: #fff; }
        @keyframes fadeIn { from { opacity: 0; } to { opacity: 1; } }
    </style>
</head>
<body class="bg-light">
    <?php include 'navbar.php'; ?>

    <div class="container mt-4 mb-5">
        <form id="formInventario" method="POST" action="inventario_guardar.php">
            <input type="hidden" name="id_tipo_bien_seleccionado" id="id_tipo_bien_seleccionado">

            <div id="step-1" class="step-container active">
                <div class="text-center mb-4"><h3>Seleccione Categoría</h3></div>
                <div class="row justify-content-center g-3">
                    <?php foreach($tipos_bien_db as $tipo): ?>
                        <div class="col-6 col-md-3">
                            <div class="card p-3 text-center selection-card" 
                                 onclick="selectType(<?php echo $tipo['id_tipo_bien']; ?>, '<?php echo htmlspecialchars($tipo['nombre']); ?>')">
                                <i class="<?php echo $tipo['icono'] ? $tipo['icono'] : 'fas fa-box'; ?> fa-3x mb-2 text-primary"></i>
                                <h6 class="fw-bold"><?php echo htmlspecialchars($tipo['nombre']); ?></h6>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>

            <div id="step-2" class="step-container">
                <div class="card shadow rounded-3 border-0">
                    <div class="card-header bg-primary text-white d-flex justify-content-between align-items-center">
                        <h5 class="mb-0">Alta: <span id="lblTipoSeleccionado" class="fw-bold">Bien</span></h5>
                        <button type="button" class="btn btn-sm btn-light text-primary fw-bold" onclick="resetWizard()">Volver</button>
                    </div>
                    <div class="card-body p-4">

                        <div class="row g-3 mb-4">
                            <div class="col-md-6">
                                <label class="fw-bold small">Destino</label>
                                <select name="id_destino" id="id_destino" class="form-select select2" required>
                                    <option value="">-- Seleccionar --</option>
                                    <?php foreach($destinos as $d): ?>
                                        <option value="<?php echo $d['id_destino']; ?>"><?php echo htmlspecialchars($d['nombre']); ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-md-6">
                                <label class="fw-bold small">Área / Servicio</label>
                                <select name="servicio_ubicacion" id="servicio_ubicacion" class="form-select select2" disabled required>
                                    <option value="">(Seleccione Destino)</option>
                                </select>
                            </div>
                        </div>

                        <div id="panel-tecnico" style="display:none;" class="bg-white p-3 rounded border border-danger mb-4">
                            <h6 class="text-danger fw-bold border-bottom pb-2 mb-3"><i class="fas fa-fire-extinguisher"></i> Datos del Extintor</h6>
                            <div class="row g-3">
                                <div class="col-md-3">
                                    <label class="small fw-bold">Tipo Agente</label>
                                    <select name="mat_tipo_carga_id" id="mat_tipo_carga_id" class="form-select" onchange="autoNombreMatafuego()">
                                        <option value="">-- Seleccionar --</option>
                                        <?php foreach($tipos_mat as $tm): ?>
                                            <option value="<?php echo $tm['id_config']; ?>"><?php echo htmlspecialchars($tm['tipo_carga']); ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <div class="col-md-2">
                                    <label class="small fw-bold">Capacidad</label>
                                    <select name="mat_capacidad" id="mat_capacidad" class="form-select" onchange="autoNombreMatafuego()">
                                        <option value="">-- Kg --</option>
                                        <?php foreach($capacidades_db as $cap): ?>
                                            <option value="<?php echo $cap; ?>"><?php echo $cap; ?> Kg</option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <div class="col-md-2">
                                    <label class="small fw-bold">Clase</label>
                                    <select name="mat_clase_id" id="mat_clase_id" class="form-select" onchange="autoNombreMatafuego()">
                                        <option value="">--</option>
                                        <?php foreach($clases_fuego as $cf): ?>
                                            <option value="<?php echo $cf['id_clase']; ?>"><?php echo htmlspecialchars($cf['nombre']); ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <div class="col-md-2">
                                    <label class="small fw-bold">N° Grabado</label>
                                    <input type="text" name="mat_numero_grabado" class="form-control">
                                </div>
                                <div class="col-md-3">
                                    <label class="small fw-bold">Año Fab.</label>
                                    <input type="number" name="fecha_fabricacion" id="fecha_fabricacion" class="form-control" placeholder="Ej: 2024" oninput="calcularVencimiento()">
                                </div>
                                <div class="col-md-4"><label class="small fw-bold text-success">Venc. Carga</label><input type="date" name="mat_fecha_carga" class="form-control"></div>
                                <div class="col-md-4"><label class="small fw-bold text-primary">Venc. PH</label><input type="date" name="mat_fecha_ph" class="form-control"></div>
                                <div class="col-md-4">
                                    <label class="small fw-bold text-danger">Vida Útil (Vence)</label>
                                    <input type="text" id="vida_util_display" class="form-control bg-light fw-bold" readonly>
                                    <input type="hidden" name="vida_util_limite" id="vida_util_limite">
                                </div>
                            </div>
                        </div>

                        <div id="panel-dinamico" style="display:none;" class="bg-white p-3 rounded border border-success mb-4">
                            <h6 class="text-success fw-bold border-bottom pb-2 mb-3"><i class="fas fa-microchip"></i> Ficha Técnica</h6>
                            <div class="row g-3" id="contenido-dinamico"></div>
                        </div>

                        <div class="row g-3 mb-4">
                            <div class="col-md-8">
                                <label class="fw-bold small">Nombre / Descripción</label>
                                <input type="text" name="elemento" id="elemento" class="form-control" required>
                            </div>
                            <div class="col-md-4">
                                <label class="fw-bold small">Código Patrimonial</label>
                                <input type="text" name="codigo_inventario" class="form-control">
                            </div>
                            <div class="col-md-4">
                                <label class="fw-bold small">Estado</label>
                                <select name="id_estado" class="form-select">
                                    <?php foreach($estados as $e): ?>
                                        <option value="<?php echo $e['id_estado']; ?>"><?php echo htmlspecialchars($e['nombre']); ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-md-8">
                                <label class="fw-bold small">Observaciones</label>
                                <input type="text" name="observaciones" class="form-control">
                            </div>
                        </div>

                        <div class="row g-3 mb-3 border-top pt-3">
                            <div class="col-md-6"><label class="fw-bold small">Responsable</label><input type="text" name="nombre_responsable" class="form-control" required></div>
                            <div class="col-md-6"><label class="fw-bold small">Jefe Servicio</label><input type="text" name="nombre_jefe_servicio" class="form-control" required></div>
                        </div>
                        <div class="row g-2 text-center">
                            <div class="col-4">
                                <div class="card p-2 h-100">
                                    <small>Firma Responsable</small>
                                    <div class="preview-firma mt-1" id="preview_responsable" onclick="abrirFirma('responsable', 'Responsable')"><i class="fas fa-pen"></i></div>
                                    <input type="hidden" name="base64_responsable" id="base64_responsable">
                                </div>
                            </div>
                            <div class="col-4">
                                <div class="card p-2 h-100">
                                    <small>Firma Jefe</small>
                                    <div class="preview-firma mt-1" id="preview_jefe" onclick="abrirFirma('jefe', 'Jefe Servicio')"><i class="fas fa-pen"></i></div>
                                    <input type="hidden" name="base64_jefe" id="base64_jefe">
                                </div>
                            </div>
                            <div class="col-4">
                                <div class="card p-2 h-100 bg-light">
                                    <small>Relevador</small>
                                    <div class="mt-2 fw-bold text-success"><?php echo strtok($relevador['nombre_completo'], " "); ?></div>
                                </div>
                            </div>
                        </div>

                        <div class="d-grid mt-4">
                            <button type="submit" class="btn btn-success btn-lg fw-bold">GUARDAR</button>
                        </div>
                    </div>
                </div>
            </div>
        </form>
    </div>

    <div class="modal fade" id="modalFirma" tabindex="-1" data-bs-backdrop="static">
        <div class="modal-dialog modal-fullscreen">
            <div class="modal-content">
                <div class="modal-header bg-dark text-white py-2">
                    <h6 class="modal-title" id="lblRolFirma">Firmar</h6>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body p-0">
                    <div id="canvasContainer"><canvas id="signaturePad"></canvas></div>
                </div>
                <div class="modal-footer justify-content-between">
                    <button class="btn btn-outline-danger" onclick="limpiarFirma()">Borrar</button>
                    <button class="btn btn-success" onclick="guardarFirma()">Aceptar</button>
                </div>
            </div>
        </div>
    </div>

    <script src="https://code.jquery.com/jquery-3.7.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/signature_pad@4.0.0/dist/signature_pad.umd.min.js"></script>

    <script>
        const camposDinamicos = <?php echo json_encode($campos_por_tipo); ?>;
        
        function selectType(idTipo, nombreTipo) {
            $('#id_tipo_bien_seleccionado').val(idTipo);
            $('#step-1').removeClass('active');
            $('#step-2').addClass('active');
            $('#lblTipoSeleccionado').text(nombreTipo);

            $('#panel-tecnico').hide();
            $('#panel-dinamico').hide();
            $('#contenido-dinamico').empty();
            $('#elemento').prop('readonly', false).val('');

            // LOGICA: SI ES MATAFUEGO (ID=1 o contiene nombre), MUESTRA PANEL TÉCNICO
            if (idTipo == 1 || nombreTipo.toUpperCase().includes('MATAFUEGO')) {
                $('#panel-tecnico').show();
                $('#elemento').prop('readonly', true);
            } else {
                if (camposDinamicos[idTipo]) {
                    renderizarCampos(camposDinamicos[idTipo]);
                    $('#panel-dinamico').show();
                }
            }
        }

        function renderizarCampos(campos) {
            let html = '';
            campos.forEach(c => {
                let lbl = c.etiqueta.toUpperCase();
                // Oculta duplicados si existen en panel tecnico
                if (lbl.includes('AGENTE') || lbl.includes('CARGA') || lbl.includes('CLASE') || lbl.includes('CAPACIDAD') || lbl.includes('FABRICACION')) return;
                
                let type = c.tipo_input === 'date' ? 'date' : 'text';
                html += `<div class="col-md-6"><label class="small fw-bold text-success">${c.etiqueta}</label><input type="${type}" name="dinamico[${c.id_campo}]" class="form-control"></div>`;
            });
            $('#contenido-dinamico').html(html);
        }

        function resetWizard() {
            $('#step-2').removeClass('active'); $('#step-1').addClass('active');
            document.getElementById("formInventario").reset();
            $('#panel-tecnico, #panel-dinamico').hide();
        }

        function autoNombreMatafuego() {
            let tipo = $('#mat_tipo_carga_id option:selected').text();
            let cap = $('#mat_capacidad').val();
            let clase = $('#mat_clase_id option:selected').text();
            if (tipo.includes('--')) tipo = '';
            
            let nombre = 'MATAFUEGO';
            if(tipo) nombre += ' ' + tipo;
            if(cap) nombre += ' ' + cap + 'KG';
            if(clase && !clase.includes('-')) nombre += ' (' + clase + ')';
            $('#elemento').val(nombre);
        }

        function calcularVencimiento() {
            let anio = parseInt($('#fecha_fabricacion').val());
            if(anio > 1900) {
                $('#vida_util_display').val((anio + 20) + ' (Vence)');
                $('#vida_util_limite').val(anio + 20);
            }
        }

        $(document).ready(function() {
            $('.select2').select2({ theme: 'bootstrap-5' });
            
            $('#id_destino').change(function() {
                let id = $(this).val();
                let $area = $('#servicio_ubicacion').empty().append('<option>Cargando...</option>').prop('disabled', true);
                $.getJSON('ajax_obtener_areas.php', { id_destino: id }, function(data) {
                    $area.empty().append('<option value="">Seleccione o Escriba</option>');
                    if(data.length) $.each(data, function(i, item) { $area.append(new Option(item.nombre, item.nombre)); });
                    $area.prop('disabled', false).select2({ theme: 'bootstrap-5', tags: true });
                });
            });
        });

        // FIRMA
        let signaturePad = null; let rolActivo = '';
        const modalFirma = new bootstrap.Modal(document.getElementById('modalFirma'));
        
        function abrirFirma(rol, titulo) {
            rolActivo = rol; $('#lblRolFirma').text(titulo); modalFirma.show();
        }
        
        document.getElementById('modalFirma').addEventListener('shown.bs.modal', function() {
            let canvas = document.getElementById('signaturePad');
            canvas.width = canvas.parentElement.offsetWidth;
            canvas.height = canvas.parentElement.offsetHeight;
            signaturePad = new SignaturePad(canvas);
        });

        function limpiarFirma() { signaturePad.clear(); }
        function guardarFirma() {
            if (signaturePad.isEmpty()) return alert('Debe firmar');
            let data = signaturePad.toDataURL();
            $('#base64_' + rolActivo).val(data);
            $('#preview_' + rolActivo).html(`<img src="${data}">`);
            modalFirma.hide();
        }
    </script>
</body>
</html>