<?php
// Archivo: inventario_transferir.php
session_start();
include 'conexion.php';
include 'funciones_permisos.php';

if (!isset($_SESSION['usuario_id']) || !tiene_permiso('inventario_transferir', $pdo)) {
    header("Location: inventario_lista.php"); exit();
}

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$bien = $pdo->query("SELECT * FROM inventario_cargos WHERE id_cargo = $id")->fetch(PDO::FETCH_ASSOC);

if (!$bien) die("Bien no encontrado.");

$destinos = $pdo->query("SELECT * FROM destinos_internos ORDER BY nombre ASC")->fetchAll(PDO::FETCH_ASSOC);
$motivos = $pdo->query("SELECT * FROM inventario_config_motivos ORDER BY motivo ASC")->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, maximum-scale=1, user-scalable=no">
    <title>Transferir Bien | Logística</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />
    <link href="https://cdn.jsdelivr.net/npm/select2-bootstrap-5-theme@1.3.0/dist/select2-bootstrap-5-theme.min.css" rel="stylesheet" />
    <script src="https://cdn.jsdelivr.net/npm/signature_pad@4.0.0/dist/signature_pad.umd.min.js"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        body { background-color: #f8f9fa; }
        .step-icon { width: 32px; height: 32px; background: #0d6efd; color: white; border-radius: 50%; display: inline-flex; align-items: center; justify-content: center; font-weight: bold; margin-right: 10px; font-size: 14px; flex-shrink: 0; }
        .card-header-custom { background: linear-gradient(45deg, #ffc107, #ffdb4d); color: #212529; }
        
        .firma-preview-box {
            cursor: pointer; border: 2px dashed #0d6efd; background-color: #fff; transition: all 0.3s;
            height: 160px; display: flex; flex-direction: column; align-items: center; justify-content: center;
            border-radius: 8px; width: 100%;
        }
        .firma-preview-box:hover { background-color: #e9f2ff; border-color: #0b5ed7; }
        .firma-preview-box img { max-height: 130px; max-width: 95%; }

        #canvasContainer {
            width: 98%; height: 60vh; background: #fff; margin: auto; border: 2px solid #ccc;
            box-shadow: 0 10px 25px rgba(0,0,0,0.1); position: relative; border-radius: 8px;
        }
        .firma-linea { position: absolute; top: 70%; left: 5%; right: 5%; border-bottom: 2px solid #333; z-index: 1; pointer-events: none; opacity: 0.5; }
        .firma-texto { position: absolute; top: 75%; width: 100%; text-align: center; color: #777; font-weight: bold; font-size: 0.8rem; pointer-events: none; text-transform: uppercase; letter-spacing: 1px; }

        @media (max-width: 768px) {
            .container { padding-left: 10px; padding-right: 10px; }
            .card-body { padding: 1.2rem !important; }
            h4 { font-size: 1.2rem; }
            .btn-lg { width: 100%; margin-top: 10px; }
            .d-flex.justify-content-between { flex-direction: column-reverse; gap: 10px; }
        }
    </style>
</head>
<body>
    <?php include 'navbar.php'; ?>

    <div class="container mt-3 mb-5">
        <div class="card shadow-lg border-0 rounded-3">
            <div class="card-header card-header-custom py-3">
                <h4 class="mb-0 fw-bold"><i class="fas fa-exchange-alt me-2"></i> Transferencia de Bien</h4>
            </div>
            <div class="card-body p-4">
                
                <div class="alert alert-light border shadow-sm mb-4">
                    <div class="row align-items-center">
                        <div class="col-12 col-md-8 mb-2 mb-md-0">
                            <h5 class="mb-1 text-primary fw-bold text-break"><?php echo htmlspecialchars($bien['elemento']); ?></h5>
                            <small class="text-muted d-block"><i class="fas fa-barcode me-1"></i> <?php echo htmlspecialchars($bien['codigo_inventario']); ?> </small>
                            <small class="text-muted d-block"><i class="fas fa-map-marker-alt me-1"></i> <strong><?php echo htmlspecialchars($bien['servicio_ubicacion']); ?></strong></small>
                        </div>
                        <div class="col-12 col-md-4 text-md-end">
                            <span class="badge bg-secondary p-2 w-100 w-md-auto">Resp: <?php echo htmlspecialchars($bien['nombre_responsable']); ?></span>
                        </div>
                    </div>
                </div>

                <form id="formTransferencia" method="POST" action="inventario_transferir_procesar.php">
                    <input type="hidden" name="id_bien" value="<?php echo $id; ?>">
                    
                    <h5 class="mb-3 text-dark fw-bold border-bottom pb-2 d-flex align-items-center"><span class="step-icon">1</span> Nuevo Destino</h5>
                    <div class="row g-3 mb-4">
                        <div class="col-12 col-md-6">
                            <label class="fw-bold small text-muted mb-1">Destino Principal</label>
                            <select name="select_destino" id="select_destino" class="form-select w-100" required>
                                <option value="">-- Seleccione --</option>
                                <?php foreach($destinos as $d): ?>
                                    <option value="<?php echo $d['id_destino']; ?>"><?php echo htmlspecialchars($d['nombre']); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-12 col-md-6">
                            <label class="fw-bold small text-muted mb-1">Área Específica</label>
                            <select name="nueva_area" id="select_area" class="form-select w-100" required disabled>
                                <option value="">-- Seleccione destino --</option>
                            </select>
                        </div>
                    </div>

                    <h5 class="mb-3 text-dark fw-bold border-bottom pb-2 d-flex align-items-center"><span class="step-icon">2</span> Motivo y Plazos</h5>
                    <div class="row g-3 mb-4">
                        <div class="col-12 col-md-4">
                            <label class="fw-bold small text-muted mb-1">Motivo</label>
                            <select name="motivo_id" class="form-select" onchange="checkMotivo(this)">
                                <option value="">-- Seleccione --</option>
                                <option value="REASIGNACION">Reasignación de Usuario</option>
                                <option value="REPARACION">Reparación / Mantenimiento</option>
                                <option value="PRESTAMO">Préstamo Temporal</option>
                                <option value="DEVOLUCION">Devolución</option>
                                <option value="CAMBIO_OFICINA">Cambio de Oficina/Destino</option>
                                <option value="ALTA_NUEVA">Alta / Ingreso Nuevo</option>
                                <option disabled>──────────</option>
                                
                                <?php foreach($motivos as $m): ?>
                                    <option value="<?php echo htmlspecialchars($m['motivo']); ?>"><?php echo htmlspecialchars($m['motivo']); ?></option>
                                <?php endforeach; ?>
                                
                                <option value="OTRO">Otro (Especificar)</option>
                            </select>
                        </div>
                        <div class="col-12 col-md-8">
                            <label class="fw-bold small text-muted mb-1">Detalle / Observación</label>
                            <input type="text" name="observacion_texto" id="obs_texto" class="form-control" placeholder="Describa el motivo..." required>
                        </div>
                        
                        <div class="col-12">
                            <div class="bg-light p-3 border rounded">
                                <label class="fw-bold small text-primary mb-2"><i class="fas fa-calendar-alt me-1"></i> Fecha de Ejecución</label>
                                <div class="row align-items-center">
                                    <div class="col-auto">
                                        <div class="form-check form-check-inline">
                                            <input class="form-check-input" type="radio" name="tipo_ejecucion" id="ejecucion_inmediata" value="inmediato" checked onchange="toggleFecha(false)">
                                            <label class="form-check-label fw-bold" for="ejecucion_inmediata">Inmediato</label>
                                        </div>
                                    </div>
                                    <div class="col-auto">
                                        <div class="form-check form-check-inline">
                                            <input class="form-check-input" type="radio" name="tipo_ejecucion" id="ejecucion_programada" value="programado" onchange="toggleFecha(true)">
                                            <label class="form-check-label fw-bold" for="ejecucion_programada">Programar Fecha</label>
                                        </div>
                                    </div>
                                    <div class="col-12 col-md-4 mt-2 mt-md-0">
                                        <input type="date" name="fecha_ejecucion" id="input_fecha_ejecucion" class="form-control" disabled style="display:none;">
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <h5 class="mb-3 text-dark fw-bold border-bottom pb-2 d-flex align-items-center"><span class="step-icon">3</span> Recepción</h5>
                    
                    <div class="form-check form-switch mb-3 p-3 bg-light rounded border shadow-sm">
                        <input class="form-check-input" type="checkbox" id="checkMismaPersona" onchange="toggleJefe()">
                        <label class="form-check-label fw-bold ms-2 small" for="checkMismaPersona">
                            El Nuevo Responsable es también el Jefe de Servicio (Firmar una sola vez)
                        </label>
                    </div>

                    <div class="row g-4">
                        <div class="col-12 col-md-6">
                            <div class="card h-100 shadow-sm border-0 bg-light">
                                <div class="card-header bg-success text-white fw-bold"><i class="fas fa-user-edit me-2"></i> Nuevo Responsable</div>
                                <div class="card-body">
                                    <label class="small fw-bold mb-1">Nombre y Apellido</label>
                                    <input type="text" name="nuevo_responsable_nombre" class="form-control mb-3" required>
                                    <label class="small fw-bold text-muted mb-1">Firma Digital:</label>
                                    <div class="firma-preview-box" onclick="abrirModalFirma('responsable')">
                                        <div id="preview_responsable" class="text-center w-100">
                                            <i class="fas fa-signature fa-2x text-primary opacity-50 mb-2"></i>
                                            <div class="badge bg-primary">TOCAR PARA FIRMAR</div>
                                        </div>
                                    </div>
                                    <input type="hidden" name="base64_responsable" id="base64_responsable">
                                </div>
                            </div>
                        </div>

                        <div class="col-12 col-md-6" id="bloqueJefe">
                            <div class="card h-100 shadow-sm border-0 bg-light">
                                <div class="card-header bg-primary text-white fw-bold"><i class="fas fa-user-tie me-2"></i> Jefe de Servicio (Aval)</div>
                                <div class="card-body">
                                    <label class="small fw-bold mb-1">Nombre y Apellido</label>
                                    <input type="text" name="nuevo_jefe_nombre" id="inputJefeNombre" class="form-control mb-3" required>
                                    <label class="small fw-bold text-muted mb-1">Firma Digital:</label>
                                    <div class="firma-preview-box" onclick="abrirModalFirma('jefe')">
                                        <div id="preview_jefe" class="text-center w-100">
                                            <i class="fas fa-signature fa-2x text-primary opacity-50 mb-2"></i>
                                            <div class="badge bg-primary">TOCAR PARA FIRMAR</div>
                                        </div>
                                    </div>
                                    <input type="hidden" name="base64_jefe" id="base64_jefe">
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="alert alert-warning mt-4 shadow-sm">
                        <div class="d-flex">
                            <div class="me-3"><i class="fas fa-exclamation-triangle fa-2x"></i></div>
                            <div class="small">
                                <strong>IMPORTANTE:</strong> Se generará un enlace temporal. Envíelo al <strong>Responsable Anterior</strong> para validar la entrega.
                            </div>
                        </div>
                    </div>

                    <div class="d-flex justify-content-between mt-4">
                        <a href="inventario_lista.php" class="btn btn-secondary px-4 fw-bold order-2 order-md-1">Cancelar</a>
                        <button type="submit" class="btn btn-success btn-lg px-5 fw-bold shadow order-1 order-md-2">
                            CONFIRMAR <i class="fas fa-arrow-right ms-2"></i>
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <div class="modal fade" id="modalFirma" tabindex="-1" data-bs-backdrop="static">
        <div class="modal-dialog modal-fullscreen">
            <div class="modal-content">
                <div class="modal-header bg-dark text-white py-2 shadow">
                    <h6 class="modal-title"><i class="fas fa-pen-fancy me-2"></i>Firmar como: <span id="lblRolFirma" class="fw-bold text-warning"></span></h6>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body bg-light d-flex align-items-center justify-content-center p-0">
                    <div id="canvasContainer">
                        <canvas id="signaturePad" style="width:100%; height:100%; display:block; touch-action: none;"></canvas>
                        <div class="firma-linea"></div>
                        <div class="firma-texto">FIRME SOBRE LA LÍNEA</div>
                    </div>
                </div>
                <div class="modal-footer justify-content-center bg-white p-2">
                    <button class="btn btn-outline-danger px-4 rounded-pill" onclick="limpiarFirma()">
                        <i class="fas fa-eraser"></i>
                    </button>
                    <button class="btn btn-success px-5 fw-bold rounded-pill shadow" onclick="guardarFirma()">
                        ACEPTAR
                    </button>
                </div>
            </div>
        </div>
    </div>

    <script src="https://code.jquery.com/jquery-3.7.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>
    
    <script>
        $('.form-select').select2({ theme: 'bootstrap-5', width: '100%' });

        $('#select_destino').on('change', function() {
            var id = $(this).val();
            var $area = $('#select_area');
            $area.empty().prop('disabled', true);
            if(id) {
                $.getJSON('ajax_obtener_areas.php', {id_destino: id}, function(data) {
                    $area.prop('disabled', false);
                    if (data && data.length > 0) {
                        $area.append('<option value="">-- Seleccione Área --</option>');
                        $.each(data, function(i, item) { $area.append(new Option(item.nombre, item.nombre)); });
                        $area.prop('required', true); 
                    } else {
                        $area.append('<option value="" selected>(Sin áreas - Opcional)</option>');
                        $area.prop('required', false);
                    }
                }).fail(function() { $area.prop('disabled', false).append('<option value="" selected>General</option>'); });
            }
        });

        function checkMotivo(sel) {
            document.getElementById('obs_texto').value = (sel.value !== 'OTRO' && sel.value !== '') ? sel.value : '';
            if(sel.value === 'OTRO') document.getElementById('obs_texto').focus();
        }

        function toggleFecha(activar) {
            const input = document.getElementById('input_fecha_ejecucion');
            if(activar) {
                input.disabled = false; input.style.display = 'block'; input.required = true; input.focus();
            } else {
                input.disabled = true; input.style.display = 'none'; input.required = false; input.value = '';
            }
        }

        // SISTEMA DE FIRMA
        let signaturePad = null;
        let rolActivo = '';
        const modalElement = document.getElementById('modalFirma');
        const modalFirma = new bootstrap.Modal(modalElement);

        function abrirModalFirma(rol) {
            rolActivo = rol;
            document.getElementById('lblRolFirma').innerText = (rol === 'responsable') ? 'NUEVO RESPONSABLE' : 'JEFE DE SERVICIO';
            modalFirma.show();
        }

        modalElement.addEventListener('shown.bs.modal', function() {
            const canvas = document.getElementById('signaturePad');
            const container = document.getElementById('canvasContainer');
            const ratio = Math.max(window.devicePixelRatio || 1, 1);
            canvas.width = container.offsetWidth * ratio;
            canvas.height = container.offsetHeight * ratio;
            canvas.getContext("2d").scale(ratio, ratio);
            if (signaturePad) signaturePad.clear();
            signaturePad = new SignaturePad(canvas, { minWidth: 1, maxWidth: 2.5, penColor: "rgb(0, 0, 0)", velocityFilterWeight: 0.7 });
        });

        function limpiarFirma() { if (signaturePad) signaturePad.clear(); }

        function guardarFirma() {
            if (!signaturePad || signaturePad.isEmpty()) { alert('Por favor, firme antes de aceptar.'); return; }
            const data = signaturePad.toDataURL('image/png');
            document.getElementById('base64_' + rolActivo).value = data;
            const previewDiv = document.getElementById('preview_' + rolActivo);
            previewDiv.innerHTML = `<img src="${data}" style="max-height:100%; max-width:100%;">`;
            previewDiv.parentElement.style.borderColor = '#198754';
            previewDiv.parentElement.style.backgroundColor = '#e8f5e9';
            modalFirma.hide();
        }

        function toggleJefe() {
            const isSame = document.getElementById('checkMismaPersona').checked;
            const block = document.getElementById('bloqueJefe');
            const input = document.getElementById('inputJefeNombre');
            if (isSame) {
                block.style.opacity = '0.5'; block.style.pointerEvents = 'none';
                input.required = false; document.getElementById('base64_jefe').value = 'SAME';
            } else {
                block.style.opacity = '1'; block.style.pointerEvents = 'auto';
                input.required = true; document.getElementById('base64_jefe').value = '';
            }
        }

        document.getElementById('formTransferencia').addEventListener('submit', function(e) {
            if(!document.getElementById('base64_responsable').value) { e.preventDefault(); alert('Falta la firma del Nuevo Responsable'); return; }
            if(!document.getElementById('checkMismaPersona').checked && !document.getElementById('base64_jefe').value) {
                e.preventDefault(); alert('Falta la firma del Jefe de Servicio'); return;
            }
        });
    </script>
</body>
</html>