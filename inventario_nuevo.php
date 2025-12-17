<?php
// Archivo: inventario_nuevo.php
session_start();
include 'conexion.php';
include 'funciones_permisos.php'; 

if (!isset($_SESSION['usuario_id']) || !tiene_permiso('inventario_nuevo', $pdo)) {
    header("Location: inventario_lista.php"); exit();
}

$lista_destinos = $pdo->query("SELECT id_destino, nombre FROM destinos_internos ORDER BY nombre ASC")->fetchAll(PDO::FETCH_ASSOC);
$estados_db = $pdo->query("SELECT * FROM inventario_estados")->fetchAll(PDO::FETCH_ASSOC);
$tipos_matafuegos = $pdo->query("SELECT * FROM inventario_config_matafuegos")->fetchAll(PDO::FETCH_ASSOC);
$clases_fuego = $pdo->query("SELECT * FROM inventario_config_clases")->fetchAll(PDO::FETCH_ASSOC);
$json_tipos = json_encode($tipos_matafuegos);
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Nuevo Bien | Logística</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />
    <link href="https://cdn.jsdelivr.net/npm/select2-bootstrap-5-theme@1.3.0/dist/select2-bootstrap-5-theme.min.css" rel="stylesheet" />
    <script src="https://cdn.jsdelivr.net/npm/signature_pad@4.0.0/dist/signature_pad.umd.min.js"></script>

    <style>
        body { background-color: #f8f9fa; }
        .signature-pad-container { border: 2px dashed #ced4da; border-radius: 5px; background-color: #fff; height: 180px; position: relative; }
        .type-card { cursor: pointer; transition: all 0.2s; border: 2px solid transparent; }
        .type-card:hover, .type-card.active { border-color: #0d6efd; background-color: #e7f1ff; }
        .type-icon { font-size: 2rem; margin-bottom: 10px; color: #6c757d; }
    </style>
</head>
<body>
    <?php include 'navbar.php'; ?>

    <div class="container mt-4 mb-5">
        <form id="formInventario" method="POST" action="inventario_guardar.php" enctype="multipart/form-data">
            
            <div class="card shadow border-0 mb-4">
                <div class="card-header bg-primary text-white d-flex justify-content-between align-items-center">
                    <h4 class="mb-0"><i class="fas fa-plus-circle me-2"></i> Nuevo Bien Patrimonial</h4>
                    <a href="inventario_config.php" class="btn btn-sm btn-light text-primary fw-bold"><i class="fas fa-cogs"></i> Configuración</a>
                </div>
                <div class="card-body p-4">
                    
                    <div class="row mb-4">
                        <div class="col-md-6">
                            <label class="form-label fw-bold">Tipo de Bien</label>
                            <div class="input-group">
                                <button type="button" class="btn btn-outline-primary fw-bold" data-bs-toggle="modal" data-bs-target="#modalTipoBien">
                                    <i class="fas fa-th-large me-2"></i> <span id="labelTipoSeleccionado">Seleccionar...</span>
                                </button>
                                <input type="text" class="form-control bg-white" id="displayTipo" readonly value="General" style="pointer-events: none;">
                                <input type="hidden" name="categoria_bien" id="categoria_bien" value="general">
                            </div>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-bold">Estado del Bien</label>
                            <select name="id_estado" class="form-select fw-bold" required>
                                <?php foreach($estados_db as $e): ?>
                                    <option value="<?php echo $e['id_estado']; ?>"><?php echo htmlspecialchars($e['nombre']); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>

                    <div id="campos_matafuegos" class="card bg-light border-danger mb-4 d-none bloque-especifico">
                        <div class="card-header bg-danger text-white fw-bold">
                            <i class="fas fa-fire-extinguisher me-2"></i> Detalle Matafuegos
                        </div>
                        <div class="card-body">
                            <div class="row g-3">
                                <div class="col-md-3">
                                    <label class="form-label small fw-bold">Tipo de Carga</label>
                                    <select name="mat_tipo_carga_id" id="mat_tipo_id" class="form-select" onchange="calcularVidaUtil()">
                                        <option value="">-- Seleccione --</option>
                                        <?php foreach($tipos_matafuegos as $tm): ?>
                                            <option value="<?php echo $tm['id_config']; ?>" data-vida="<?php echo $tm['vida_util_anios']; ?>">
                                                <?php echo htmlspecialchars($tm['tipo_carga']); ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <div class="col-md-2">
                                    <label class="form-label small fw-bold">Capacidad (Kg)</label>
                                    <select name="mat_capacidad" id="mat_capacidad" class="form-select" onchange="actualizarResumen()">
                                        <option value="1">1 Kg</option>
                                        <option value="2.5">2.5 Kg</option>
                                        <option value="3.5">3.5 Kg</option>
                                        <option value="5" selected>5 Kg</option>
                                        <option value="10">10 Kg</option>
                                    </select>
                                </div>
                                <div class="col-md-2">
                                    <label class="form-label small fw-bold">Clase Fuego</label>
                                    <select name="mat_clase_id" id="mat_clase" class="form-select" onchange="actualizarResumen()">
                                        <option value="">--</option>
                                        <?php foreach($clases_fuego as $cf): ?>
                                            <option value="<?php echo $cf['id_clase']; ?>">
                                                <?php echo htmlspecialchars($cf['nombre']); ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                
                                <div class="col-md-5">
                                    <label class="form-label small fw-bold text-danger">N° Matafuego (Grabado en equipo)</label>
                                    <input type="text" name="mat_numero_grabado" id="mat_numero" class="form-control border-danger" placeholder="Ej: 123456" oninput="actualizarResumen()">
                                    <small class="text-muted" style="font-size:0.75rem">Diferente al código interno.</small>
                                </div>

                                <div class="col-md-3">
                                    <label class="form-label small fw-bold text-danger">Año Fabricación</label>
                                    <input type="number" name="fecha_fabricacion" id="mat_fab" class="form-control" placeholder="Ej: 2024" oninput="calcularVidaUtil()">
                                </div>
                                <div class="col-md-3">
                                    <label class="form-label small fw-bold text-success">Vida Útil (Calculada)</label>
                                    <input type="text" id="mat_vida_util_display" class="form-control bg-success-subtle fw-bold" readonly placeholder="Automático">
                                    <input type="hidden" name="vida_util_limite" id="mat_vida_util_val">
                                </div>
                                <div class="col-md-3">
                                    <label class="form-label small fw-bold">Última Carga</label>
                                    <input type="date" name="mat_fecha_carga" id="mat_fecha_carga" class="form-control" onchange="actualizarResumen()">
                                </div>
                                <div class="col-md-3">
                                    <label class="form-label small fw-bold">Prueba Hidráulica</label>
                                    <input type="date" name="mat_fecha_ph" id="mat_fecha_ph" class="form-control" onchange="actualizarResumen()">
                                </div>

                                <div class="col-12">
                                    <label class="form-label small fw-bold">Complementos (Opcional)</label>
                                    <input type="text" name="complementos" class="form-control" placeholder="Ej: Carro transporte, Soporte pared...">
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="row g-3 mb-4">
                        <div class="col-md-8">
                            <label class="form-label fw-bold">Descripción Final</label>
                            <input type="text" name="elemento" id="input_elemento" class="form-control fw-bold" placeholder="Descripción del bien..." required>
                        </div>
                        
                        <div class="col-md-4">
                            <label class="form-label fw-bold text-primary">Código Interno (Institucional)</label>
                            <input type="text" name="codigo_inventario" id="input_codigo" class="form-control border-primary" placeholder="Ej: PAT-001">
                            <small class="text-muted" style="font-size:0.75rem">Código asignado por logística.</small>
                        </div>
                        
                        <div class="col-md-6">
                            <label class="form-label fw-bold small">1° Destino</label>
                            <select id="select_destino" class="form-select form-select-sm" required>
                                <option value="">-- Seleccione --</option>
                                <?php foreach ($lista_destinos as $dest): ?>
                                    <option value="<?php echo $dest['id_destino']; ?>"><?php echo htmlspecialchars($dest['nombre']); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-bold small">2° Área</label>
                            <select name="servicio_ubicacion" id="select_area" class="form-select form-select-sm" disabled required>
                                <option value="">-- Primero Destino --</option>
                            </select>
                        </div>
                        
                        <div class="col-12">
                            <label class="form-label fw-bold">Observaciones</label>
                            <textarea name="observaciones" id="input_observaciones" class="form-control" rows="2" placeholder="Opcional"></textarea>
                        </div>
                    </div>

                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label fw-bold">Responsable (Usuario Final)</label>
                            <input type="text" name="nombre_responsable" class="form-control mb-2" placeholder="Nombre y Apellido" required>
                            <div class="signature-pad-container"><canvas id="sigResponsable"></canvas></div>
                            <div class="mt-1"><button type="button" class="btn btn-sm btn-outline-secondary" onclick="clearPad('Responsable')">Limpiar</button></div>
                            <input type="hidden" name="base64_responsable" id="base64_responsable">
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label fw-bold">Jefe de Servicio (Aval)</label>
                            <input type="text" name="nombre_jefe_servicio" class="form-control mb-2" placeholder="Nombre y Apellido" required>
                            <div class="signature-pad-container"><canvas id="sigJefe"></canvas></div>
                            <div class="mt-1"><button type="button" class="btn btn-sm btn-outline-secondary" onclick="clearPad('Jefe')">Limpiar</button></div>
                            <input type="hidden" name="base64_jefe" id="base64_jefe">
                        </div>
                    </div>
                    
                    <div class="d-grid mt-4">
                        <button type="submit" class="btn btn-success btn-lg shadow fw-bold">GUARDAR BIEN</button>
                    </div>

                </div>
            </div>
        </form>
    </div>

    <div class="modal fade" id="modalTipoBien" tabindex="-1">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header"><h5 class="modal-title">Seleccionar Tipo</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
                <div class="modal-body text-center">
                    <div class="row g-3">
                        <div class="col-6"><div class="card p-3 type-card" onclick="seleccionarTipo('matafuegos')"><i class="fas fa-fire-extinguisher text-danger fs-1"></i><h6 class="mt-2">Matafuegos</h6></div></div>
                        <div class="col-6"><div class="card p-3 type-card" onclick="seleccionarTipo('general')"><i class="fas fa-box fs-1"></i><h6 class="mt-2">General</h6></div></div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://code.jquery.com/jquery-3.7.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>
    
    <script>
        // Configuración Select2
        $(document).ready(function() {
            $('#select_destino').select2({ theme: 'bootstrap-5', placeholder: 'Buscar Destino...' });
            $('#select_area').select2({ theme: 'bootstrap-5', placeholder: 'Seleccione Área...' });

            $('#select_destino').on('change', function() {
                var idDestino = $(this).val();
                var $selectArea = $('#select_area');
                $selectArea.empty().append('<option value="">Cargando...</option>').prop('disabled', true);
                if (idDestino) {
                    $.ajax({
                        url: 'ajax_obtener_areas.php', data: { id_destino: idDestino }, dataType: 'json',
                        success: function(areas) {
                            $selectArea.empty().append('<option value="">-- Seleccione Área --</option>');
                            if (areas.length > 0) {
                                $.each(areas, function(i, a) { $selectArea.append('<option value="' + a.nombre + '">' + a.nombre + '</option>'); });
                                $selectArea.prop('disabled', false);
                            } else { $selectArea.append('<option value="">Sin áreas</option>'); }
                        }
                    });
                } else { $selectArea.empty().append('<option value="">-- Primero Destino --</option>'); }
            });
        });

        // Lógica Tipos y Cálculo Matafuegos
        let tipoActual = 'general';
        const tiposMatafuegos = <?php echo $json_tipos; ?>;

        function seleccionarTipo(tipo) {
            tipoActual = tipo;
            document.getElementById('categoria_bien').value = tipo;
            document.querySelectorAll('.bloque-especifico').forEach(el => el.classList.add('d-none'));
            
            if(tipo === 'matafuegos') {
                document.getElementById('campos_matafuegos').classList.remove('d-none');
                document.getElementById('displayTipo').value = 'Matafuegos';
                document.getElementById('labelTipoSeleccionado').innerText = 'Matafuegos';
                document.getElementById('input_elemento').readOnly = true; 
                document.getElementById('input_elemento').placeholder = 'Automático...';
            } else {
                document.getElementById('displayTipo').value = 'General';
                document.getElementById('labelTipoSeleccionado').innerText = 'General';
                document.getElementById('input_elemento').readOnly = false;
            }
            bootstrap.Modal.getInstance(document.getElementById('modalTipoBien')).hide();
        }

        function calcularVidaUtil() {
            if(tipoActual !== 'matafuegos') return;
            
            const sel = document.getElementById('mat_tipo_id');
            const op = sel.options[sel.selectedIndex];
            const vidaAnios = op.getAttribute('data-vida');
            const fab = parseInt(document.getElementById('mat_fab').value);
            
            if (vidaAnios && fab) {
                const finVida = fab + parseInt(vidaAnios);
                document.getElementById('mat_vida_util_display').value = finVida;
                document.getElementById('mat_vida_util_val').value = finVida;
            } else {
                document.getElementById('mat_vida_util_display').value = '';
                document.getElementById('mat_vida_util_val').value = '';
            }
            actualizarResumen();
        }

        function actualizarResumen() {
            if(tipoActual === 'matafuegos') {
                const selTipo = document.getElementById('mat_tipo_id');
                const tipoTxt = selTipo.selectedIndex > 0 ? selTipo.options[selTipo.selectedIndex].text : '';
                
                const selClase = document.getElementById('mat_clase');
                const claseTxt = selClase.selectedIndex > 0 ? selClase.options[selClase.selectedIndex].text.trim() : '';

                const cap = document.getElementById('mat_capacidad').value;
                const num = document.getElementById('mat_numero').value; // N° Grabado
                const vtoCarga = document.getElementById('mat_fecha_carga').value;
                const vtoPh = document.getElementById('mat_fecha_ph').value;
                
                // Descripción automática
                let desc = `MATAFUEGO ${tipoTxt} ${cap}KG (${claseTxt})`;
                document.getElementById('input_elemento').value = desc.toUpperCase();
                
                // NOTA: No pisamos el código interno con el grabado. Son separados.

                // Observaciones automáticas de vencimientos
                let obs = [];
                if(vtoCarga) obs.push(`Carga: ${vtoCarga}`);
                if(vtoPh) obs.push(`PH: ${vtoPh}`);
                document.getElementById('input_observaciones').value = obs.join(' / ');
            }
        }

        // Firmas (Simplificado para el ejemplo)
        const padResp = new SignaturePad(document.getElementById('sigResponsable'));
        const padJefe = new SignaturePad(document.getElementById('sigJefe'));
        
        function clearPad(quien) { 
            if(quien==='Responsable') padResp.clear(); 
            else padJefe.clear(); 
        }

        document.getElementById('formInventario').addEventListener('submit', function(e) {
            if(padResp.isEmpty() || padJefe.isEmpty()){
                e.preventDefault(); alert('Faltan firmas requeridas.'); return;
            }
            document.getElementById('base64_responsable').value = padResp.toDataURL();
            document.getElementById('base64_jefe').value = padJefe.toDataURL();
        });
        
        // Ajustar canvas
        function resizeCanvas() {
            const ratio = Math.max(window.devicePixelRatio || 1, 1);
            document.querySelectorAll('canvas').forEach(c => {
                c.width = c.parentElement.offsetWidth * ratio;
                c.height = c.parentElement.offsetHeight * ratio;
                c.getContext("2d").scale(ratio, ratio);
            });
        }
        window.addEventListener("resize", resizeCanvas);
        resizeCanvas();
    </script>
</body>
</html>