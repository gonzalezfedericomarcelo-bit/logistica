<?php
// Archivo: inventario_transferir.php
session_start();
include 'conexion.php';
include 'funciones_permisos.php';

if (!isset($_SESSION['usuario_id']) || !tiene_permiso('inventario_transferir', $pdo)) {
    header("Location: inventario_lista.php"); exit();
}

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
// Buscamos el bien con sus datos actuales
$bien = $pdo->query("SELECT * FROM inventario_cargos WHERE id_cargo = $id")->fetch(PDO::FETCH_ASSOC);

if (!$bien) die("Bien no encontrado.");

// Cargar Listas para los desplegables
$destinos = $pdo->query("SELECT * FROM destinos_internos ORDER BY nombre ASC")->fetchAll(PDO::FETCH_ASSOC);
$motivos = $pdo->query("SELECT * FROM inventario_config_motivos ORDER BY motivo ASC")->fetchAll(PDO::FETCH_ASSOC);

?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Transferir Bien | Logística</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />
    <link href="https://cdn.jsdelivr.net/npm/select2-bootstrap-5-theme@1.3.0/dist/select2-bootstrap-5-theme.min.css" rel="stylesheet" />
    <script src="https://cdn.jsdelivr.net/npm/signature_pad@4.0.0/dist/signature_pad.umd.min.js"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        body { background-color: #f8f9fa; }
        .signature-pad-container { border: 2px dashed #ced4da; border-radius: 5px; background-color: #fff; height: 160px; position: relative; }
        .step-icon { width: 32px; height: 32px; background: #0d6efd; color: white; border-radius: 50%; display: inline-flex; align-items: center; justify-content: center; font-weight: bold; margin-right: 10px; font-size: 14px; }
        .card-header-custom { background: linear-gradient(45deg, #ffc107, #ffdb4d); color: #212529; }
    </style>
</head>
<body>
    <?php include 'navbar.php'; ?>

    <div class="container mt-4 mb-5">
        <div class="card shadow-lg border-0 rounded-3">
            <div class="card-header card-header-custom py-3">
                <h4 class="mb-0 fw-bold"><i class="fas fa-exchange-alt me-2"></i> Transferencia de Bien Patrimonial</h4>
            </div>
            <div class="card-body p-4">
                
                <div class="alert alert-light border shadow-sm mb-4">
                    <div class="row align-items-center">
                        <div class="col-md-8">
                            <h5 class="mb-1 text-primary fw-bold"><?php echo htmlspecialchars($bien['elemento']); ?></h5>
                            <small class="text-muted">
                                <i class="fas fa-barcode me-1"></i> <?php echo htmlspecialchars($bien['codigo_inventario']); ?> 
                                | <i class="fas fa-map-marker-alt me-1"></i> Actual: <strong><?php echo htmlspecialchars($bien['servicio_ubicacion']); ?></strong>
                            </small>
                        </div>
                        <div class="col-md-4 text-end">
                            <span class="badge bg-secondary p-2 shadow-sm">Responsable Actual: <?php echo htmlspecialchars($bien['nombre_responsable']); ?></span>
                        </div>
                    </div>
                </div>

                <form id="formTransferencia" method="POST" action="inventario_transferir_procesar.php">
                    <input type="hidden" name="id_bien" value="<?php echo $id; ?>">
                    
                    <h5 class="mb-3 text-dark fw-bold border-bottom pb-2"><span class="step-icon">1</span> Nuevo Destino</h5>
                    <div class="row g-3 mb-4">
                        <div class="col-md-6">
                            <label class="fw-bold small text-muted mb-1">Destino Principal</label>
                            <select name="select_destino" id="select_destino" class="form-select" required>
                                <option value="">-- Seleccione --</option>
                                <?php foreach($destinos as $d): ?>
                                    <option value="<?php echo $d['id_destino']; ?>"><?php echo htmlspecialchars($d['nombre']); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="fw-bold small text-muted mb-1">Área Específica</label>
                            <select name="nueva_area" id="select_area" class="form-select" required disabled>
                                <option value="">-- Primero seleccione destino --</option>
                            </select>
                        </div>
                    </div>

                    <h5 class="mb-3 text-dark fw-bold border-bottom pb-2"><span class="step-icon">2</span> Motivo del Movimiento</h5>
                    <div class="row g-3 mb-4">
                        <div class="col-md-4">
                            <label class="fw-bold small text-muted mb-1">Seleccionar Motivo</label>
                            <select name="motivo_id" class="form-select" onchange="checkMotivo(this)">
                                <option value="">-- Seleccione --</option>
                                <?php foreach($motivos as $m): ?>
                                    <option value="<?php echo htmlspecialchars($m['motivo']); ?>"><?php echo htmlspecialchars($m['motivo']); ?></option>
                                <?php endforeach; ?>
                                <option value="OTRO">Otro (Especificar)</option>
                            </select>
                        </div>
                        <div class="col-md-8">
                            <label class="fw-bold small text-muted mb-1">Detalle / Observación</label>
                            <input type="text" name="observacion_texto" id="obs_texto" class="form-control" placeholder="Describa el motivo..." required>
                        </div>
                    </div>

                    <h5 class="mb-3 text-dark fw-bold border-bottom pb-2"><span class="step-icon">3</span> Recepción (Quien recibe)</h5>
                    
                    <div class="form-check form-switch mb-3 p-3 bg-light rounded border shadow-sm">
                        <input class="form-check-input" type="checkbox" id="checkMismaPersona" onchange="toggleJefe()">
                        <label class="form-check-label fw-bold ms-2" for="checkMismaPersona">El Nuevo Responsable es también el Jefe de Servicio (Firmar una sola vez)</label>
                    </div>

                    <div class="row g-4">
                        <div class="col-md-6">
                            <div class="card h-100 shadow-sm border-0 bg-light">
                                <div class="card-header bg-success text-white fw-bold"><i class="fas fa-user-edit me-2"></i> Nuevo Responsable</div>
                                <div class="card-body">
                                    <label class="small fw-bold mb-1">Nombre y Apellido</label>
                                    <input type="text" name="nuevo_responsable_nombre" class="form-control mb-3" required>
                                    <label class="small fw-bold text-muted mb-1">Firma Digital:</label>
                                    <div class="signature-pad-container shadow-inner">
                                        <canvas id="sigResponsable"></canvas>
                                    </div>
                                    <button type="button" class="btn btn-sm btn-outline-secondary mt-2 w-100" onclick="padResp.clear()"><i class="fas fa-eraser me-1"></i> Limpiar Firma</button>
                                    <input type="hidden" name="base64_responsable" id="base64_responsable">
                                </div>
                            </div>
                        </div>

                        <div class="col-md-6" id="bloqueJefe">
                            <div class="card h-100 shadow-sm border-0 bg-light">
                                <div class="card-header bg-primary text-white fw-bold"><i class="fas fa-user-tie me-2"></i> Jefe de Servicio (Aval)</div>
                                <div class="card-body">
                                    <label class="small fw-bold mb-1">Nombre y Apellido</label>
                                    <input type="text" name="nuevo_jefe_nombre" id="inputJefeNombre" class="form-control mb-3" required>
                                    <label class="small fw-bold text-muted mb-1">Firma Digital:</label>
                                    <div class="signature-pad-container shadow-inner">
                                        <canvas id="sigJefe"></canvas>
                                    </div>
                                    <button type="button" class="btn btn-sm btn-outline-secondary mt-2 w-100" onclick="padJefe.clear()"><i class="fas fa-eraser me-1"></i> Limpiar Firma</button>
                                    <input type="hidden" name="base64_jefe" id="base64_jefe">
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="alert alert-warning mt-4 shadow-sm">
                        <div class="d-flex">
                            <div class="me-3"><i class="fas fa-exclamation-triangle fa-2x"></i></div>
                            <div>
                                <strong>IMPORTANTE:</strong> Al confirmar, se generará un <strong>enlace temporal</strong>. 
                                Deberá enviar ese enlace al <strong>Responsable Anterior</strong> para que valide su identidad y libere el bien.
                            </div>
                        </div>
                    </div>

                    <div class="d-flex justify-content-between mt-4">
                        <a href="inventario_lista.php" class="btn btn-secondary px-4 fw-bold"><i class="fas fa-times me-2"></i> Cancelar</a>
                        <button type="submit" class="btn btn-success btn-lg px-5 fw-bold shadow"><i class="fas fa-check-circle me-2"></i> CONFIRMAR Y GENERAR LINK</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script src="https://code.jquery.com/jquery-3.7.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>
    
    <script>
        // Select2
        $('#select_destino').select2({ theme: 'bootstrap-5' });
        $('#select_area').select2({ theme: 'bootstrap-5', tags: true }); // 'tags: true' permite escribir si falta el área

        // --- LÓGICA CORREGIDA: DESTINO Y ÁREAS VACÍAS ---
        $('#select_destino').on('change', function() {
            var id = $(this).val();
            var $area = $('#select_area');
            
            // Limpiar selector
            $area.empty().prop('disabled', true);
            
            if(id) {
                // AJAX para buscar áreas
                $.getJSON('ajax_obtener_areas.php', {id_destino: id}, function(data) {
                    $area.prop('disabled', false); // Habilitar siempre para no trabar
                    
                    if (data && data.length > 0) {
                        // Si hay áreas, las mostramos y obligamos a elegir
                        $area.append('<option value="">-- Seleccione Área --</option>');
                        $.each(data, function(i, item) {
                            $area.append(`<option value="${item.nombre}">${item.nombre}</option>`);
                        });
                        $area.prop('required', true); 
                    } else {
                        // Si NO hay áreas, ponemos la opción automática para destrabar
                        $area.append('<option value="" selected>(Sin áreas - Opcional)</option>');
                        $area.prop('required', false); // Ya no es obligatorio
                    }
                }).fail(function() {
                    // Si falla el server, destrabamos igual
                    $area.prop('disabled', false).append('<option value="" selected>General</option>');
                });
            }
        });

        // Motivos
        function checkMotivo(sel) {
            if (sel.value !== 'OTRO' && sel.value !== '') {
                document.getElementById('obs_texto').value = sel.value;
            } else {
                document.getElementById('obs_texto').value = '';
                document.getElementById('obs_texto').focus();
            }
        }

        // Firmas
        const padResp = new SignaturePad(document.getElementById('sigResponsable'));
        const padJefe = new SignaturePad(document.getElementById('sigJefe'));

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

        // Toggle Jefe
        function toggleJefe() {
            const isSame = document.getElementById('checkMismaPersona').checked;
            const block = document.getElementById('bloqueJefe');
            const input = document.getElementById('inputJefeNombre');
            
            if (isSame) {
                block.style.opacity = '0.5';
                block.style.pointerEvents = 'none';
                input.required = false;
            } else {
                block.style.opacity = '1';
                block.style.pointerEvents = 'auto';
                input.required = true;
            }
        }

        // Submit
        document.getElementById('formTransferencia').addEventListener('submit', function(e) {
            if(padResp.isEmpty()) {
                e.preventDefault(); alert('Falta la firma del Nuevo Responsable'); return;
            }
            document.getElementById('base64_responsable').value = padResp.toDataURL();

            if(!document.getElementById('checkMismaPersona').checked) {
                if(padJefe.isEmpty()) {
                    e.preventDefault(); alert('Falta la firma del Jefe de Servicio'); return;
                }
                document.getElementById('base64_jefe').value = padJefe.toDataURL();
            } else {
                document.getElementById('base64_jefe').value = 'SAME'; 
            }
        });
    </script>
</body>
</html>