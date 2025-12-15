<?php
// Archivo: inventario_nuevo.php
// MODIFICADO: Soporte para Informática, Cámaras, Telefonía y Matafuegos según Excel provistos.

session_start();
include 'conexion.php';
include 'funciones_permisos.php'; 

if (!isset($_SESSION['usuario_id']) || !tiene_permiso('acceso_inventario', $pdo)) {
    header("Location: dashboard.php");
    exit();
}

// Cargar listas para desplegables (Destino -> Área)
$lista_destinos = [];
try {
    $stmt = $pdo->query("SELECT id_destino, nombre FROM destinos_internos ORDER BY CASE WHEN nombre LIKE '%Actis%' THEN 0 ELSE 1 END, nombre ASC");
    $lista_destinos = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) { }
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Nuevo Inventario | Logística</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />
    <link href="https://cdn.jsdelivr.net/npm/select2-bootstrap-5-theme@1.3.0/dist/select2-bootstrap-5-theme.min.css" rel="stylesheet" />

    <style>
        body { background-color: #f8f9fa; }
        
        .signature-pad-container {
            border: 2px dashed #ced4da;
            border-radius: 5px;
            background-color: #fff;
            position: relative;
            height: 180px;
            cursor: crosshair;
            transition: border-color 0.3s;
        }
        .signature-pad-container:hover { border-color: #0d6efd; }
        
        .signature-line {
            position: absolute; bottom: 50px; left: 20px; right: 20px;
            border-bottom: 1px solid #ccc; pointer-events: none; text-align: center;
        }
        .signature-line::after {
            content: "Firme sobre la línea"; font-size: 0.7rem; color: #aaa;
            background: #fff; padding: 0 5px; position: relative; top: 10px;
        }

        canvas { width: 100%; height: 100%; touch-action: none; }
        #modalSignatureCanvas { width: 100%; height: 300px; background-color: #fff; border: 1px solid #ddd; cursor: crosshair; }

        /* Estilos Tarjetas Modal */
        .type-card { cursor: pointer; transition: all 0.2s; border: 2px solid transparent; }
        .type-card:hover { transform: translateY(-3px); border-color: #0d6efd; background-color: #f8f9fa; }
        .type-card.active { border-color: #0d6efd; background-color: #e7f1ff; }
        .type-icon { font-size: 2rem; margin-bottom: 10px; color: #6c757d; }
        .type-card.active .type-icon { color: #0d6efd; }
    </style>
</head>
<body>

    <?php include 'navbar.php'; ?>

    <div class="container mt-4 mb-5">
        <div class="card shadow-lg border-0">
            <div class="card-header bg-primary text-white d-flex justify-content-between align-items-center">
                <h4 class="mb-0"><i class="fas fa-boxes me-2"></i> Nuevo Cargo Patrimonial</h4>
                <small>Asignación de Bienes</small>
            </div>
            <div class="card-body p-4">
                
                <?php if (isset($_GET['error'])): ?>
                    <div class="alert alert-danger">Error al guardar. Verifique los datos.</div>
                <?php endif; ?>

                <form id="formInventario" method="POST" action="inventario_guardar.php">
                    
                    <div class="mb-5">
                        <h5 class="text-primary border-bottom pb-2 mb-3"><i class="fas fa-desktop me-2"></i>1. Datos del Elemento</h5>
                        
                        <div class="mb-3">
                            <label class="form-label fw-bold">Tipo de Bien:</label>
                            <div class="input-group">
                                <button type="button" class="btn btn-outline-primary fw-bold" data-bs-toggle="modal" data-bs-target="#modalTipoBien">
                                    <i class="fas fa-th-large me-2"></i> <span id="labelTipoSeleccionado">Seleccionar Tipo...</span>
                                </button>
                                <input type="text" class="form-control bg-white" id="displayTipo" readonly placeholder="Clic para seleccionar..." style="pointer-events: none;">
                            </div>
                        </div>

                        <div id="campos_informatica" class="card bg-light border-info mb-3 d-none bloque-especifico">
                            <div class="card-header bg-info text-white fw-bold"><i class="fas fa-laptop me-2"></i> Detalle Informática</div>
                            <div class="card-body">
                                <div class="row g-3">
                                    <div class="col-md-3">
                                        <label class="form-label small fw-bold">Equipo</label>
                                        <select class="form-select" id="info_equipo" onchange="actualizarResumen()">
                                            <option value="">-- Seleccione --</option>
                                            <option value="CPU">CPU</option>
                                            <option value="MONITOR">MONITOR</option>
                                            <option value="NOTEBOOK">NOTEBOOK</option>
                                            <option value="IMPRESORA">IMPRESORA</option>
                                            <option value="TECLADO">TECLADO</option>
                                            <option value="MOUSE">MOUSE</option>
                                            <option value="UPS">UPS</option>
                                            <option value="OTRO">OTRO</option>
                                        </select>
                                    </div>
                                    <div class="col-md-3">
                                        <label class="form-label small fw-bold">Marca</label>
                                        <input type="text" class="form-control" id="info_marca" oninput="actualizarResumen()">
                                    </div>
                                    <div class="col-md-3">
                                        <label class="form-label small fw-bold">Modelo</label>
                                        <input type="text" class="form-control" id="info_modelo" oninput="actualizarResumen()">
                                    </div>
                                    <div class="col-md-3">
                                        <label class="form-label small fw-bold">N° Serie</label>
                                        <input type="text" class="form-control" id="info_serial" oninput="actualizarResumen()">
                                    </div>
                                    <div class="col-md-12">
                                        <label class="form-label small fw-bold">N° IOSE Sistemas (Inv. Interno)</label>
                                        <input type="text" class="form-control" id="info_interno" oninput="actualizarResumen()">
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div id="campos_camaras" class="card bg-light border-warning mb-3 d-none bloque-especifico">
                            <div class="card-header bg-warning text-dark fw-bold"><i class="fas fa-video me-2"></i> Detalle Cámaras</div>
                            <div class="card-body">
                                <div class="row g-3">
                                    <div class="col-md-3">
                                        <label class="form-label small fw-bold">Equipo</label>
                                        <input type="text" class="form-control" id="cam_equipo" value="CAMARA" readonly>
                                    </div>
                                    <div class="col-md-3">
                                        <label class="form-label small fw-bold">Marca</label>
                                        <input type="text" class="form-control" id="cam_marca" placeholder="Ej: HIKVISION" oninput="actualizarResumen()">
                                    </div>
                                    <div class="col-md-3">
                                        <label class="form-label small fw-bold">Modelo</label>
                                        <input type="text" class="form-control" id="cam_modelo" placeholder="Ej: DS-2CE..." oninput="actualizarResumen()">
                                    </div>
                                    <div class="col-md-3">
                                        <label class="form-label small fw-bold">Descripción / Lente</label>
                                        <input type="text" class="form-control" id="cam_desc" placeholder="Ej: 2,8mm 12V" oninput="actualizarResumen()">
                                    </div>
                                    <div class="col-md-6">
                                        <label class="form-label small fw-bold">S/N (Serie)</label>
                                        <input type="text" class="form-control" id="cam_serial" oninput="actualizarResumen()">
                                    </div>
                                    <div class="col-md-6">
                                        <label class="form-label small fw-bold">N° IOSE (Si tiene)</label>
                                        <input type="text" class="form-control" id="cam_iose" oninput="actualizarResumen()">
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div id="campos_telefonia" class="card bg-light border-primary mb-3 d-none bloque-especifico">
                            <div class="card-header bg-primary text-white fw-bold"><i class="fas fa-phone me-2"></i> Detalle Telefonía</div>
                            <div class="card-body">
                                <div class="row g-3">
                                    <div class="col-md-4">
                                        <label class="form-label small fw-bold">N° Interno</label>
                                        <input type="text" class="form-control" id="tel_interno" placeholder="Ej: 115" oninput="actualizarResumen()">
                                    </div>
                                    <div class="col-md-8">
                                        <label class="form-label small fw-bold">Sector / Oficina</label>
                                        <input type="text" class="form-control" id="tel_sector" placeholder="Ej: FACTURACION" oninput="actualizarResumen()">
                                    </div>
                                    <div class="col-md-12">
                                        <small class="text-muted"><i class="fas fa-info-circle"></i> Se generará el cargo por el número de interno asignado.</small>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div id="campos_matafuegos" class="card bg-light border-danger mb-3 d-none bloque-especifico">
                            <div class="card-header bg-danger text-white fw-bold"><i class="fas fa-fire-extinguisher me-2"></i> Detalle Matafuegos</div>
                            <div class="card-body">
                                <div class="row g-3">
                                    <div class="col-md-3">
                                        <label class="form-label small fw-bold">Tipo Carga</label>
                                        <select class="form-select" id="mat_tipo" onchange="actualizarResumen()">
                                            <option value="POLVO QUIMICO">Polvo Químico (ABC)</option>
                                            <option value="CO2">CO2 (BC)</option>
                                            <option value="AGUA">Agua (A)</option>
                                            <option value="AGUA-ESPUMA">Agua - Espuma</option>
                                            <option value="HALOCLEAN">Haloclean</option>
                                        </select>
                                    </div>
                                    <div class="col-md-2">
                                        <label class="form-label small fw-bold">Capacidad (Kg)</label>
                                        <select class="form-select" id="mat_capacidad" onchange="actualizarResumen()">
                                            <option value="1">1 Kg</option>
                                            <option value="2.5">2.5 Kg</option>
                                            <option value="3.5">3.5 Kg</option>
                                            <option value="5" selected>5 Kg</option>
                                            <option value="10">10 Kg</option>
                                        </select>
                                    </div>
                                    <div class="col-md-2">
                                        <label class="form-label small fw-bold">Clase</label>
                                        <input type="text" class="form-control" id="mat_clase" value="ABC" placeholder="Ej: ABC" oninput="actualizarResumen()">
                                    </div>
                                    <div class="col-md-5">
                                        <label class="form-label small fw-bold">N° Matafuego (Fabricación)</label>
                                        <input type="text" class="form-control" id="mat_numero" placeholder="N° Grabado en el equipo" oninput="actualizarResumen()">
                                    </div>
                                    
                                    <div class="col-12"><hr class="my-1"></div>
                                    
                                    <div class="col-md-4">
                                        <label class="form-label small fw-bold text-danger">Vto. Carga</label>
                                        <input type="date" class="form-control" id="mat_vto_carga" onchange="actualizarResumen()">
                                    </div>
                                    <div class="col-md-4">
                                        <label class="form-label small fw-bold text-danger">Vto. Prueba Hidráulica</label>
                                        <input type="date" class="form-control" id="mat_vto_ph" onchange="actualizarResumen()">
                                    </div>
                                    <div class="col-md-4">
                                        <label class="form-label small fw-bold">Año Fabricación</label>
                                        <input type="number" class="form-control" id="mat_fab" placeholder="Ej: 2024" oninput="actualizarResumen()">
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="row g-3">
                            <div class="col-md-6">
                                <label class="form-label fw-bold">Descripción Final del Elemento</label>
                                <input type="text" name="elemento" id="input_elemento" class="form-control fw-bold bg-white" placeholder="Descripción..." required>
                            </div>
                            <div class="col-md-2">
                                <label class="form-label fw-bold">Código Patrimonial</label>
                                <input type="text" name="codigo_inventario" id="input_codigo" class="form-control" placeholder="Opcional">
                            </div>
                            
                            <div class="col-md-4">
                                <div class="row g-2">
                                    <div class="col-12">
                                        <label class="form-label fw-bold small">1° Destino</label>
                                        <select id="select_destino" class="form-select form-select-sm" required>
                                            <option value="">-- Seleccione --</option>
                                            <?php foreach ($lista_destinos as $dest): ?>
                                                <option value="<?php echo $dest['id_destino']; ?>"><?php echo htmlspecialchars($dest['nombre']); ?></option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                    <div class="col-12">
                                        <label class="form-label fw-bold small">2° Área</label>
                                        <select name="servicio_ubicacion" id="select_area" class="form-select form-select-sm" disabled required>
                                            <option value="">-- Primero Destino --</option>
                                        </select>
                                    </div>
                                </div>
                            </div>

                            <div class="col-12">
                                <label class="form-label fw-bold">Observaciones / Detalles Adicionales</label>
                                <textarea name="observaciones" id="input_observaciones" class="form-control" rows="2"></textarea>
                            </div>
                        </div>
                    </div>

                    <div class="mb-5">
                        <h5 class="text-primary border-bottom pb-2 mb-3"><i class="fas fa-user me-2"></i>2. Responsable (Usuario Final)</h5>
                        <div class="row align-items-center">
                            <div class="col-md-6">
                                <label class="form-label fw-bold">Nombre y Apellido</label>
                                <input type="text" name="nombre_responsable" class="form-control form-control-lg" required>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label fw-bold text-muted mb-2">Firma del Responsable</label>
                                <div class="signature-pad-container">
                                    <canvas id="sigResponsable"></canvas>
                                    <div class="signature-line"></div>
                                </div>
                                <div class="d-flex justify-content-between mt-2">
                                    <button type="button" class="btn btn-sm btn-outline-danger" onclick="clearPad('Responsable')">Borrar</button>
                                    <button type="button" class="btn btn-sm btn-primary" onclick="openFullPad('Responsable')">Firmar Grande</button>
                                </div>
                                <input type="hidden" name="base64_responsable" id="base64_responsable">
                            </div>
                        </div>
                    </div>

                    <div class="mb-5">
                        <h5 class="text-primary border-bottom pb-2 mb-3"><i class="fas fa-user-tie me-2"></i>3. Jefe de Servicio (Aval)</h5>
                        <div class="row align-items-center">
                            <div class="col-md-6">
                                <label class="form-label fw-bold">Nombre y Apellido (Jefe)</label>
                                <input type="text" name="nombre_jefe_servicio" class="form-control form-control-lg" required>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label fw-bold text-muted mb-2">Firma del Jefe</label>
                                <div class="signature-pad-container">
                                    <canvas id="sigJefe"></canvas>
                                    <div class="signature-line"></div>
                                </div>
                                <div class="d-flex justify-content-between mt-2">
                                    <button type="button" class="btn btn-sm btn-outline-danger" onclick="clearPad('Jefe')">Borrar</button>
                                    <button type="button" class="btn btn-sm btn-primary" onclick="openFullPad('Jefe')">Firmar Grande</button>
                                </div>
                                <input type="hidden" name="base64_jefe" id="base64_jefe">
                            </div>
                        </div>
                    </div>

                    <div class="alert alert-info mb-4">
                        <i class="fas fa-info-circle me-2"></i> Su firma (Logística) se adjuntará automáticamente.
                    </div>

                    <div class="d-grid gap-2">
                        <button type="submit" class="btn btn-success btn-lg py-3 fw-bold shadow">GUARDAR</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <div class="modal fade" id="modalTipoBien" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title fw-bold">Seleccionar Tipo</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body p-4">
                    <div class="row g-3 justify-content-center">
                        <div class="col-6 col-md-4">
                            <div class="card h-100 text-center type-card" onclick="seleccionarTipo('informatica')">
                                <div class="card-body">
                                    <div class="type-icon"><i class="fas fa-laptop"></i></div>
                                    <h6>Informática</h6>
                                </div>
                            </div>
                        </div>
                        <div class="col-6 col-md-4">
                            <div class="card h-100 text-center type-card" onclick="seleccionarTipo('camaras')">
                                <div class="card-body">
                                    <div class="type-icon"><i class="fas fa-video"></i></div>
                                    <h6>Cámaras</h6>
                                </div>
                            </div>
                        </div>
                        <div class="col-6 col-md-4">
                            <div class="card h-100 text-center type-card" onclick="seleccionarTipo('telefonia')">
                                <div class="card-body">
                                    <div class="type-icon"><i class="fas fa-phone-alt"></i></div>
                                    <h6>Telefonía</h6>
                                </div>
                            </div>
                        </div>
                        <div class="col-6 col-md-4">
                            <div class="card h-100 text-center type-card" onclick="seleccionarTipo('matafuegos')">
                                <div class="card-body">
                                    <div class="type-icon text-danger"><i class="fas fa-fire-extinguisher"></i></div>
                                    <h6>Matafuegos</h6>
                                </div>
                            </div>
                        </div>
                        <div class="col-6 col-md-4">
                            <div class="card h-100 text-center type-card" onclick="seleccionarTipo('general')">
                                <div class="card-body">
                                    <div class="type-icon"><i class="fas fa-box-open"></i></div>
                                    <h6>General</h6>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="modal fade" id="modalFirma" tabindex="-1" data-bs-backdrop="static">
        <div class="modal-dialog modal-lg modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header bg-dark text-white">
                    <h5 class="modal-title">Firmar: <span id="tituloModalFirma"></span></h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body bg-light text-center">
                    <canvas id="modalSignatureCanvas"></canvas>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" onclick="limpiarModalFirma()">Limpiar</button>
                    <button type="button" class="btn btn-success" onclick="guardarFirmaModal()">Aceptar</button>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://code.jquery.com/jquery-3.7.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/signature_pad@4.0.0/dist/signature_pad.umd.min.js"></script>
    
    <script>
        // 1. Config Select2/AJAX
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

        // 2. LÓGICA DINÁMICA DE TIPOS
        let tipoActual = 'general';

        function seleccionarTipo(tipo) {
            tipoActual = tipo;
            document.querySelectorAll('.bloque-especifico').forEach(el => el.classList.add('d-none'));
            const inputElemento = document.getElementById('input_elemento');
            const inputCodigo = document.getElementById('input_codigo');
            const inputObs = document.getElementById('input_observaciones');
            const display = document.getElementById('displayTipo');
            const labelBtn = document.getElementById('labelTipoSeleccionado');
            
            // Limpiar inputs
            inputElemento.readOnly = false; inputElemento.value = ''; inputCodigo.value = ''; inputObs.value = '';

            if (tipo === 'informatica') {
                document.getElementById('campos_informatica').classList.remove('d-none');
                display.value = "Informática"; labelBtn.innerText = "Informática";
                inputElemento.readOnly = true; inputElemento.placeholder = "Automático...";
            } else if (tipo === 'camaras') {
                document.getElementById('campos_camaras').classList.remove('d-none');
                display.value = "Cámaras de Seguridad"; labelBtn.innerText = "Cámaras";
                inputElemento.readOnly = true; inputElemento.placeholder = "Automático...";
            } else if (tipo === 'telefonia') {
                document.getElementById('campos_telefonia').classList.remove('d-none');
                display.value = "Telefonía"; labelBtn.innerText = "Telefonía";
                inputElemento.readOnly = true; inputElemento.placeholder = "Automático...";
            } else if (tipo === 'matafuegos') {
                document.getElementById('campos_matafuegos').classList.remove('d-none');
                display.value = "Matafuegos"; labelBtn.innerText = "Matafuegos";
                inputElemento.readOnly = true; inputElemento.placeholder = "Automático...";
            } else {
                display.value = "General"; labelBtn.innerText = "General";
                inputElemento.placeholder = "Descripción del bien...";
            }
            bootstrap.Modal.getInstance(document.getElementById('modalTipoBien')).hide();
        }

        // GENERADOR DE TEXTO INTELIGENTE
        function actualizarResumen() {
            const el = document.getElementById('input_elemento');
            const cod = document.getElementById('input_codigo');
            const obs = document.getElementById('input_observaciones');
            let desc = "", codigo = "", observaciones = "";

            if (tipoActual === 'informatica') {
                const eq = document.getElementById('info_equipo').value;
                const ma = document.getElementById('info_marca').value;
                const mo = document.getElementById('info_modelo').value;
                const se = document.getElementById('info_serial').value;
                const inte = document.getElementById('info_interno').value;
                desc = `${eq} ${ma} ${mo}`;
                codigo = inte;
                if(se) observaciones = `S/N: ${se}`;

            } else if (tipoActual === 'camaras') {
                const ma = document.getElementById('cam_marca').value;
                const mo = document.getElementById('cam_modelo').value;
                const de = document.getElementById('cam_desc').value;
                const se = document.getElementById('cam_serial').value;
                const io = document.getElementById('cam_iose').value;
                desc = `CAMARA ${ma} ${mo} ${de}`;
                codigo = io;
                if(se) observaciones = `S/N: ${se}`;

            } else if (tipoActual === 'telefonia') {
                const inte = document.getElementById('tel_interno').value;
                const sec = document.getElementById('tel_sector').value;
                desc = `TELEFONO INTERNO ${inte}`;
                codigo = inte;
                if(sec) observaciones = `Sector Original: ${sec}`;

            } else if (tipoActual === 'matafuegos') {
                const tipo = document.getElementById('mat_tipo').value;
                const cap = document.getElementById('mat_capacidad').value;
                const cla = document.getElementById('mat_clase').value;
                const num = document.getElementById('mat_numero').value;
                const vc = document.getElementById('mat_vto_carga').value;
                const vph = document.getElementById('mat_vto_ph').value;
                const fab = document.getElementById('mat_fab').value;
                
                desc = `MATAFUEGO ${tipo} ${cap}KG (${cla})`;
                codigo = num;
                
                let partesObs = [];
                if(vc) partesObs.push(`Vto Carga: ${vc}`);
                if(vph) partesObs.push(`Vto PH: ${vph}`);
                if(fab) partesObs.push(`Fab: ${fab}`);
                observaciones = partesObs.join(' / ');
            }

            if (tipoActual !== 'general') {
                el.value = desc.trim().toUpperCase();
                cod.value = codigo.trim().toUpperCase();
                // Solo actualizamos obs si no fue editada manualmente o si está vacía para no borrar notas del usuario
                // Pero para simplificar en este modelo, reemplazamos si es automático.
                obs.value = observaciones.toUpperCase(); 
            }
        }

        // 3. FIRMAS (Igual que antes)
        let padResponsable, padJefe, padModal, currentSigner;
        function initPad(id) {
            const c = document.getElementById(id); const r = Math.max(window.devicePixelRatio||1,1);
            c.width=c.parentElement.offsetWidth*r; c.height=c.parentElement.offsetHeight*r; c.getContext("2d").scale(r,r);
            return new SignaturePad(c, { backgroundColor:'rgba(255,255,255,0)' });
        }
        window.addEventListener('DOMContentLoaded', ()=>{
            padResponsable=initPad('sigResponsable'); padJefe=initPad('sigJefe');
            padModal=new SignaturePad(document.getElementById('modalSignatureCanvas'),{backgroundColor:'rgb(255,255,255)'});
        });
        window.clearPad=function(q){ if(q==='Responsable')padResponsable.clear(); if(q==='Jefe')padJefe.clear(); }
        window.openFullPad=function(q){
            currentSigner=q; document.getElementById('tituloModalFirma').innerText=(q==='Responsable')?'Responsable':'Jefe';
            const m = new bootstrap.Modal(document.getElementById('modalFirma')); m.show();
            setTimeout(()=>{
                const c = document.getElementById('modalSignatureCanvas');
                const r = Math.max(window.devicePixelRatio||1,1);
                c.width=c.offsetWidth*r; c.height=c.offsetHeight*r; c.getContext("2d").scale(r,r); padModal.clear();
            },500);
        }
        window.limpiarModalFirma=function(){ padModal.clear(); }
        window.guardarFirmaModal=function(){
            if(padModal.isEmpty()){alert("Firme antes de aceptar.");return;}
            const d=padModal.toDataURL();
            if(currentSigner==='Responsable')padResponsable.fromDataURL(d);
            if(currentSigner==='Jefe')padJefe.fromDataURL(d);
            bootstrap.Modal.getInstance(document.getElementById('modalFirma')).hide();
        }
        document.getElementById('formInventario').addEventListener('submit', function(e){
            if(padResponsable.isEmpty() || padJefe.isEmpty()){ e.preventDefault(); alert("Faltan firmas."); return; }
            document.getElementById('base64_responsable').value=padResponsable.toDataURL();
            document.getElementById('base64_jefe').value=padJefe.toDataURL();
        });
    </script>

    <?php include 'footer.php'; ?>
</body>
</html>