<?php
// Archivo: inventario_nuevo.php (Soporte FULL para Campos Dinámicos + Correcciones)
session_start();
include 'conexion.php';
include 'funciones_permisos.php'; 

if (!isset($_SESSION['usuario_id']) || !tiene_permiso('acceso_inventario', $pdo)) {
    header("Location: dashboard.php"); exit();
}

// 1. CARGA DE CONFIGURACIONES
try {
    $tipos_bien_db = $pdo->query("SELECT * FROM inventario_tipos_bien ORDER BY id_tipo_bien ASC")->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) { $tipos_bien_db = []; }

// 2. CARGAR CAMPOS DINÁMICOS (Lo que importaste del Excel)
// Obtenemos todos los campos y los agrupamos por id_tipo_bien en un Array PHP para pasarlo a JS
$sql_campos = "SELECT * FROM inventario_campos_dinamicos ORDER BY id_tipo_bien, orden ASC";
try {
    $raw_campos = $pdo->query($sql_campos)->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) { $raw_campos = []; }

$campos_por_tipo = [];
foreach($raw_campos as $c) {
    $campos_por_tipo[$c['id_tipo_bien']][] = $c;
}

// 3. Configuración Vida Útil (Matafuegos)
$sql_tipos = "SELECT id_config, tipo_carga, vida_util_anios FROM inventario_config_matafuegos";
$tipos_mat = $pdo->query($sql_tipos)->fetchAll(PDO::FETCH_ASSOC);
$json_vida_util = [];
$json_nombres_tipos = [];
foreach($tipos_mat as $t) {
    $json_vida_util[$t['id_config']] = $t['vida_util_anios'];
    $json_nombres_tipos[$t['id_config']] = $t['tipo_carga'];
}

$destinos = $pdo->query("SELECT * FROM destinos_internos ORDER BY nombre ASC")->fetchAll(PDO::FETCH_ASSOC);
$estados = $pdo->query("SELECT * FROM inventario_estados ORDER BY nombre ASC")->fetchAll(PDO::FETCH_ASSOC);
$clases_fuego = $pdo->query("SELECT * FROM inventario_config_clases")->fetchAll(PDO::FETCH_ASSOC);

$id_rel = $_SESSION['usuario_id'];
$relevador = $pdo->query("SELECT nombre_completo, firma_imagen_path FROM usuarios WHERE id_usuario = $id_rel")->fetch(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=0">
    <title>Nuevo Cargo</title>
    
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />
    <link href="https://cdn.jsdelivr.net/npm/select2-bootstrap-5-theme@1.3.0/dist/select2-bootstrap-5-theme.min.css" rel="stylesheet" />

    <style>
        body { background-color: #f0f2f5; font-family: 'Segoe UI', system-ui, sans-serif; overscroll-behavior: none; }
        
        .step-container { display: none; animation: fadeIn 0.3s ease-in-out; }
        .step-container.active { display: block; }
        
        .selection-card {
            cursor: pointer; transition: all 0.2s; border: 2px solid #dee2e6;
            background: #fff; border-radius: 12px; height: 100%;
        }
        .selection-card:hover {
            transform: translateY(-5px); border-color: #0d6efd;
            box-shadow: 0 10px 20px rgba(13,110,253,0.15);
        }
        .selection-card i { font-size: 3rem; margin-bottom: 15px; }
        
        .preview-firma {
            height: 90px; border: 2px dashed #ccc; border-radius: 8px;
            display: flex; align-items: center; justify-content: center;
            background: #fff; cursor: pointer;
        }
        .preview-firma img { max-height: 100%; max-width: 100%; }

        /* MODAL FIRMA */
        .modal-fullscreen .modal-body {
            position: relative; padding: 0; margin: 0;
            overflow: hidden; background-color: #fff; touch-action: none;
        }
        #canvasContainer {
            position: absolute; top: 0; left: 0; right: 0; bottom: 0;
            width: 100%; height: 100%; background-color: white; z-index: 10;
        }
        canvas { display: block; width: 100%; height: 100%; cursor: crosshair; }
        .overlay-firma {
            position: absolute; bottom: 20%; left: 10%; right: 10%;
            text-align: center; pointer-events: none; z-index: 20;
        }
        .linea-guia { border-bottom: 2px solid #333; margin-bottom: 5px; }

        @keyframes fadeIn { from { opacity: 0; transform: translateY(10px); } to { opacity: 1; transform: translateY(0); } }
    </style>
</head>
<body>

    <?php include 'navbar.php'; ?>

    <div class="container mt-4 mb-5">
        
        <form id="formInventario" method="POST" action="inventario_guardar.php">
            <input type="hidden" name="id_tipo_bien_seleccionado" id="id_tipo_bien_seleccionado">

            <div id="step-1" class="step-container active">
                <div class="text-center mb-5">
                    <h2 class="fw-bold text-dark">Nuevo Relevamiento</h2>
                    <p class="text-muted">Seleccione la categoría.</p>
                </div>
                
                <div class="row justify-content-center g-4">
                    <?php if (count($tipos_bien_db) > 0): ?>
                        <?php foreach($tipos_bien_db as $tipo): ?>
                            <div class="col-6 col-md-4 col-lg-3">
                                <div class="card p-4 text-center selection-card" 
                                     onclick="selectType(<?php echo $tipo['id_tipo_bien']; ?>, <?php echo $tipo['tiene_campos_tecnicos']; ?>, '<?php echo htmlspecialchars($tipo['nombre']); ?>')">
                                    <div class="card-body p-0">
                                        <i class="<?php echo $tipo['icono'] ? htmlspecialchars($tipo['icono']) : 'fas fa-box'; ?>"></i>
                                        <h5 class="fw-bold mt-2"><?php echo htmlspecialchars($tipo['nombre']); ?></h5>
                                        <p class="text-muted small mb-0"><?php echo htmlspecialchars($tipo['descripcion']); ?></p>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <div class="col-12 text-center">
                            <div class="alert alert-warning">No hay categorías. Ve a Configuración e importa un Excel.</div>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

            <div id="step-2" class="step-container">
                <div class="card shadow-lg border-0 rounded-3">
                    <div class="card-header bg-primary text-white d-flex justify-content-between align-items-center py-3">
                        <h5 class="mb-0"><i class="fas fa-clipboard-list me-2"></i> Alta: <span id="lblTipoSeleccionado" class="fw-bold text-warning">Bien</span></h5>
                        <button type="button" class="btn btn-sm btn-light text-primary fw-bold" onclick="resetWizard()">
                            <i class="fas fa-arrow-left me-1"></i> Volver
                        </button>
                    </div>
                    <div class="card-body p-4">

                        <div class="row g-3 mb-4">
                            <div class="col-md-6">
                                <label class="fw-bold form-label">Destino (Sede/Edificio)</label>
                                <select name="id_destino" id="id_destino" class="form-select select2" required>
                                    <option value="">-- Seleccionar --</option>
                                    <?php foreach($destinos as $d): ?>
                                        <option value="<?php echo $d['id_destino']; ?>"><?php echo htmlspecialchars($d['nombre']); ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-md-6">
                                <label class="fw-bold form-label">Área / Servicio</label>
                                <select name="servicio_ubicacion" id="servicio_ubicacion" class="form-select select2" disabled required>
                                    <option value="">(Seleccione Destino primero)</option>
                                </select>
                            </div>
                        </div>

                        <div id="panel-tecnico" style="display:none;" class="bg-light p-3 rounded border mb-4 position-relative">
                            <span class="badge bg-danger position-absolute top-0 end-0 m-2">Extintores</span>
                            <div class="row g-3">
                                <div class="col-md-3">
                                    <label class="small fw-bold">Tipo Agente</label>
                                    <select name="mat_tipo_carga_id" id="mat_tipo_carga_id" class="form-select" onchange="calcularAutomaticos()">
                                        <option value="">-- Seleccionar --</option>
                                        <?php foreach($tipos_mat as $tm): ?>
                                            <option value="<?php echo $tm['id_config']; ?>"><?php echo htmlspecialchars($tm['tipo_carga']); ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <div class="col-md-2">
                                    <label class="small fw-bold">Capacidad</label>
                                    <select name="mat_capacidad" id="mat_capacidad" class="form-select" onchange="calcularAutomaticos()">
                                        <option value="">-- Kg --</option>
                                        <option value="1">1 Kg</option>
                                        <option value="2.5">2.5 Kg</option>
                                        <option value="5">5 Kg</option>
                                        <option value="10">10 Kg</option>
                                    </select>
                                </div>
                                <div class="col-md-2">
                                    <label class="small fw-bold">Clase</label>
                                    <select name="mat_clase_id" id="mat_clase_id" class="form-select" onchange="calcularAutomaticos()">
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
                                    <input type="number" name="fecha_fabricacion" id="fecha_fabricacion" class="form-control" oninput="calcularVidaUtil()">
                                </div>
                                <div class="col-md-4"><label class="small fw-bold text-success">Carga</label><input type="date" name="mat_fecha_carga" class="form-control"></div>
                                <div class="col-md-4"><label class="small fw-bold text-primary">PH</label><input type="date" name="mat_fecha_ph" class="form-control"></div>
                                <div class="col-md-4"><label class="small fw-bold text-danger">Vida Útil</label><input type="number" name="vida_util_limite" id="vida_util_limite" class="form-control fw-bold bg-white" readonly></div>
                            </div>
                        </div>

                        <div id="panel-dinamico" style="display:none;" class="bg-light p-3 rounded border mb-4 position-relative border-success">
                            <span class="badge bg-success position-absolute top-0 end-0 m-2">Ficha Técnica Importada</span>
                            <div class="row g-3" id="contenido-dinamico">
                                </div>
                        </div>

                        <div class="row g-3 mb-4">
                            <div class="col-md-8">
                                <label class="fw-bold form-label">Elemento / Descripción</label>
                                <input type="text" name="elemento" id="elemento" class="form-control form-control-lg" placeholder="Describa el bien..." required>
                            </div>
                            <div class="col-md-4">
                                <label class="fw-bold form-label">Código Patrimonial</label>
                                <input type="text" name="codigo_inventario" class="form-control form-control-lg">
                            </div>
                            <div class="col-md-4">
                                <label class="fw-bold form-label">Estado Inicial</label>
                                <select name="id_estado" class="form-select">
                                    <?php foreach($estados as $e): ?>
                                        <option value="<?php echo $e['id_estado']; ?>"><?php echo htmlspecialchars($e['nombre']); ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-md-8">
                                <label class="fw-bold form-label">Observaciones</label>
                                <input type="text" name="observaciones" class="form-control" placeholder="Detalles, estado general...">
                            </div>
                        </div>

                        <h5 class="text-primary fw-bold mb-3 border-bottom pb-2"><i class="fas fa-file-contract me-2"></i> Conformidad</h5>
                        <div class="row g-3 mb-3">
                            <div class="col-md-6"><label class="fw-bold small">Responsable del Bien</label><input type="text" name="nombre_responsable" class="form-control" required></div>
                            <div class="col-md-6"><label class="fw-bold small">Jefe de Servicio</label><input type="text" name="nombre_jefe_servicio" class="form-control" required></div>
                        </div>
                        <div class="row text-center g-3">
                            <div class="col-md-4">
                                <div class="card h-100 bg-light p-2">
                                    <label class="small fw-bold mb-1">Responsable</label>
                                    <div class="preview-firma mb-2" id="preview_responsable" onclick="abrirFirma('responsable', 'Responsable')">
                                        <span class="text-muted small"><i class="fas fa-pen"></i> Tocar</span>
                                    </div>
                                    <input type="hidden" name="base64_responsable" id="base64_responsable">
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="card h-100 bg-light p-2">
                                    <label class="small fw-bold mb-1">Jefe Servicio</label>
                                    <div class="preview-firma mb-2" id="preview_jefe" onclick="abrirFirma('jefe', 'Jefe de Servicio')">
                                        <span class="text-muted small"><i class="fas fa-pen"></i> Tocar</span>
                                    </div>
                                    <input type="hidden" name="base64_jefe" id="base64_jefe">
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="card h-100 border-success bg-success-subtle p-2">
                                    <label class="small fw-bold text-success mb-1">Relevador</label>
                                    <div class="d-flex flex-column align-items-center justify-content-center h-100">
                                        <?php if($relevador['firma_imagen_path']): ?>
                                            <img src="uploads/firmas/<?php echo $relevador['firma_imagen_path']; ?>" style="max-height: 40px;">
                                        <?php else: ?>
                                            <i class="fas fa-user-check fa-2x text-success"></i>
                                        <?php endif; ?>
                                        <small class="fw-bold text-dark" style="font-size: 0.7rem;"><?php echo strtok($relevador['nombre_completo'], " "); ?></small>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="d-grid mt-4">
                            <button type="submit" class="btn btn-success btn-lg py-3 fw-bold shadow">
                                <i class="fas fa-check-circle me-2"></i> GUARDAR ACTA
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </form>
    </div>

    <div class="modal fade" id="modalFirma" tabindex="-1" aria-hidden="true" data-bs-backdrop="static">
        <div class="modal-dialog modal-fullscreen">
            <div class="modal-content">
                <div class="modal-header bg-dark text-white py-2" style="z-index: 30;">
                    <h5 class="modal-title fs-6"><i class="fas fa-pen-fancy me-2"></i> <span id="lblRolFirma"></span></h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div id="canvasContainer">
                        <canvas id="signaturePad"></canvas>
                    </div>
                    <div class="overlay-firma">
                        <div class="linea-guia"></div>
                        <small class="text-muted fw-bold">FIRME AQUÍ</small>
                    </div>
                </div>
                <div class="modal-footer py-2 justify-content-between bg-white" style="z-index: 30;">
                    <button type="button" class="btn btn-outline-danger" onclick="limpiarFirma()">Borrar</button>
                    <button type="button" class="btn btn-success fw-bold px-4" onclick="guardarFirma()">CONFIRMAR</button>
                </div>
            </div>
        </div>
    </div>

    <script src="https://code.jquery.com/jquery-3.7.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/signature_pad@4.0.0/dist/signature_pad.umd.min.js"></script>

    <script>
        // CONFIGURACIONES
        const configVidaUtil = <?php echo json_encode($json_vida_util); ?>;
        const nombresTipos = <?php echo json_encode($json_nombres_tipos); ?>;
        // AQUÍ ESTÁ LA MAGIA: Cargamos los campos dinámicos en JS
        const camposDinamicos = <?php echo json_encode($campos_por_tipo); ?>;
        let tieneCamposTecnicos = 0; // 0=Gral, 1=Mata, 2=Dinamico

        // WIZARD CONTROL
        function selectType(idTipo, techFlag, nombreTipo) {
            tieneCamposTecnicos = techFlag;
            $('#id_tipo_bien_seleccionado').val(idTipo); // Guardamos ID para usar al guardar
            
            $('#step-1').removeClass('active');
            setTimeout(() => { $('#step-2').addClass('active'); }, 100);
            $('#lblTipoSeleccionado').text(nombreTipo);

            // Reiniciar paneles
            $('#panel-tecnico').slideUp();
            $('#panel-dinamico').slideUp();
            $('#contenido-dinamico').empty();
            $('#elemento').prop('readonly', false).removeClass('bg-light').val('');

            // CASO 1: MATAFUEGOS (HARDCODED)
            if(tieneCamposTecnicos == 1) {
                $('#panel-tecnico').slideDown();
                $('#elemento').prop('readonly', true).addClass('bg-light');
                calcularAutomaticos();
            } 
            // CASO 2: DINÁMICO (EXCEL IMPORTADO)
            else if(tieneCamposTecnicos == 2) {
                renderizarCamposDinamicos(idTipo);
            }
        }

        // FUNCIÓN PARA DIBUJAR LOS CAMPOS DEL EXCEL
        function renderizarCamposDinamicos(idTipo) {
            if(camposDinamicos[idTipo]) {
                let html = '';
                camposDinamicos[idTipo].forEach(campo => {
                    let tipoInput = campo.tipo_input === 'date' ? 'date' : (campo.tipo_input === 'number' ? 'number' : 'text');
                    html += `
                        <div class="col-md-6">
                            <label class="small fw-bold text-success">${campo.etiqueta}</label>
                            <input type="${tipoInput}" name="dinamico[${campo.id_campo}]" class="form-control border-success" placeholder="${campo.etiqueta}">
                        </div>
                    `;
                });
                $('#contenido-dinamico').html(html);
                $('#panel-dinamico').slideDown();
                // Bloqueamos descripción para forzar formato estandarizado
                $('#elemento').val("FICHA TÉCNICA - " + $('#lblTipoSeleccionado').text().toUpperCase());
            }
        }

        function resetWizard() {
            $('#step-2').removeClass('active'); $('#step-1').addClass('active');
            document.getElementById("formInventario").reset();
            $('#id_destino').val('').trigger('change');
            $('#servicio_ubicacion').empty().trigger('change');
            $('.preview-firma').html('<span class="text-muted small"><i class="fas fa-pen"></i> Tocar</span>').removeClass('border-primary');
        }

        // AUTO-CALCULO MATAFUEGOS
        function calcularAutomaticos() {
            if(tieneCamposTecnicos != 1) return;
            const idTipo = $('#mat_tipo_carga_id').val();
            const cap = $('#mat_capacidad').val();
            const claseText = $('#mat_clase_id option:selected').text();
            let nombre = "MATAFUEGO";
            if(idTipo && nombresTipos[idTipo]) nombre += " " + nombresTipos[idTipo].toUpperCase();
            if(cap) nombre += " " + cap + "KG";
            if(claseText) nombre += " (" + claseText + ")";
            $('#elemento').val(nombre);
            calcularVidaUtil();
        }
        function calcularVidaUtil() {
            if(tieneCamposTecnicos != 1) return;
            const anioFab = parseInt($('#fecha_fabricacion').val());
            const idTipo = $('#mat_tipo_carga_id').val();
            if(anioFab > 1900 && idTipo && configVidaUtil[idTipo]) {
                $('#vida_util_limite').val(anioFab + parseInt(configVidaUtil[idTipo]));
            } else { $('#vida_util_limite').val(''); }
        }

        // SELECT2
        $(document).ready(function() {
            $('.select2').select2({ theme: 'bootstrap-5' });
            $('#id_destino').on('change', function() {
                var id = $(this).val(); var $area = $('#servicio_ubicacion');
                $area.prop('disabled', true).empty().append('<option>Cargando...</option>');
                $.getJSON('ajax_obtener_areas.php', { id_destino: id }, function(data) {
                    $area.empty().append('<option value="">-- Seleccione o Escriba --</option>');
                    $area.select2({ theme: 'bootstrap-5', tags: true });
                    if(data && data.length > 0) $.each(data, function(i, item) { $area.append(new Option(item.nombre, item.nombre)); });
                    $area.prop('disabled', false);
                });
            });
        });

        // FIRMA (CANVAS)
        let signaturePad = null;
        let rolActivo = '';
        const modalEl = document.getElementById('modalFirma');
        const modalObj = new bootstrap.Modal(modalEl);

        function abrirFirma(rol, label) {
            rolActivo = rol;
            document.getElementById('lblRolFirma').innerText = label;
            modalObj.show();
        }

        modalEl.addEventListener('shown.bs.modal', function () { iniciarCanvas(); });
        window.addEventListener("orientationchange", function() { if(modalEl.classList.contains('show')) setTimeout(iniciarCanvas, 200); });

        function iniciarCanvas() {
            const canvas = document.getElementById('signaturePad');
            const container = document.getElementById('canvasContainer');
            const ratio = Math.max(window.devicePixelRatio || 1, 1);
            
            canvas.width = container.offsetWidth * ratio;
            canvas.height = container.offsetHeight * ratio;
            
            const ctx = canvas.getContext("2d");
            ctx.scale(ratio, ratio);

            if (signaturePad) signaturePad.off();
            signaturePad = new SignaturePad(canvas, { minWidth: 2, maxWidth: 4, penColor: "black" });
            signaturePad.clear(); 
        }

        function limpiarFirma() { if(signaturePad) signaturePad.clear(); }
        function guardarFirma() {
            if(signaturePad.isEmpty()) { alert("Debe firmar."); return; }
            const data = signaturePad.toDataURL("image/png");
            document.getElementById('base64_' + rolActivo).value = data;
            const div = document.getElementById('preview_' + rolActivo);
            div.innerHTML = `<img src="${data}">`;
            div.classList.add('border-primary');
            modalObj.hide();
        }

        $('#formInventario').on('submit', function(e) {
            if(!$('#base64_responsable').val() || !$('#base64_jefe').val()) {
                e.preventDefault();
                alert("Faltan firmas obligatorias.");
            }
        });
    </script>
</body>
</html>