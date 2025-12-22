<?php
// Archivo: transferencia_externa.php
// OBJETIVO: Vista de Acta Digital + Firma "Profesional" (Estilo Perfil)
session_start();
include 'conexion.php';

$token = $_GET['token'] ?? '';
$error = ''; $datos = null; $bien = null; $yaConfirmado = false;

if ($token) {
    $stmt = $pdo->prepare("SELECT * FROM inventario_transferencias_pendientes WHERE token_hash = ?");
    $stmt->execute([$token]);
    $datos = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$datos) { $error = "El enlace no existe o es incorrecto."; } 
    elseif ($datos['estado'] == 'confirmado') { $yaConfirmado = true; } 
    elseif ($datos['fecha_expiracion'] < date('Y-m-d H:i:s')) { $error = "Este enlace ha caducado."; }

    if ($datos) {
        $stmtBien = $pdo->prepare("SELECT i.*, t.nombre as tipo_bien FROM inventario_cargos i LEFT JOIN inventario_tipos_bien t ON i.id_tipo_bien = t.id_tipo_bien WHERE i.id_cargo = ?");
        $stmtBien->execute([$datos['id_bien']]);
        $bien = $stmtBien->fetch(PDO::FETCH_ASSOC);
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Acta de Transferencia | Logística</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <script src="https://cdn.jsdelivr.net/npm/signature_pad@4.0.0/dist/signature_pad.umd.min.js"></script>
    <style>
        body { background-color: #f4f6f9; font-family: 'Segoe UI', system-ui, sans-serif; }
        .paper { background: #fff; max-width: 800px; margin: 30px auto; padding: 40px; box-shadow: 0 0 15px rgba(0,0,0,0.1); border-radius: 4px; border-top: 5px solid #0d6efd; }
        .acta-header { text-align: center; border-bottom: 2px solid #eee; padding-bottom: 20px; margin-bottom: 20px; }
        .acta-title { font-weight: bold; text-transform: uppercase; font-size: 1.5rem; color: #333; }
        .acta-meta { color: #666; font-size: 0.9rem; }
        .detail-box { background: #f8f9fa; border: 1px solid #dee2e6; border-radius: 5px; padding: 15px; margin-bottom: 20px; }
        .detail-label { font-weight: bold; font-size: 0.85rem; color: #6c757d; text-transform: uppercase; display: block; margin-bottom: 3px; }
        .detail-value { font-size: 1.1rem; color: #000; font-weight: 500; }
        
        /* ESTILOS DE FIRMA (IDÉNTICOS A PERFIL.PHP) */
        #canvasContainer {
            width: 95%; 
            height: 60vh; /* Altura cómoda */
            background: #fff; 
            margin: 0 auto;
            border: 2px solid #ccc; 
            position: relative; 
            box-shadow: 0 10px 25px rgba(0,0,0,0.1); 
            border-radius: 8px;
        }
        .firma-linea {
            position: absolute;
            top: 70%; left: 10%; right: 10%;
            border-bottom: 2px solid #333;
            z-index: 1;
            pointer-events: none; opacity: 0.5;
        }
        .firma-texto {
            position: absolute;
            top: 75%; width: 100%;
            text-align: center; color: #777;
            font-weight: bold; font-size: 0.9rem;
            pointer-events: none; text-transform: uppercase; letter-spacing: 2px;
        }
        
        /* Modal Steps */
        .modal-step { display: none; }
        .modal-step.active { display: block; animation: fadeIn 0.3s; }
        @keyframes fadeIn { from { opacity: 0; transform: translateY(10px); } to { opacity: 1; transform: translateY(0); } }
    </style>
</head>
<body>

<div class="container pb-5">
    <?php if ($error): ?>
        <div class="alert alert-danger mt-5 text-center shadow">
            <h3><i class="fas fa-exclamation-circle"></i> Enlace Inválido</h3>
            <p><?php echo $error; ?></p>
        </div>
    <?php elseif ($yaConfirmado): ?>
        <div class="paper text-center">
            <i class="fas fa-check-circle text-success fa-5x mb-3"></i>
            <h2 class="text-success fw-bold">Transferencia Completada</h2>
            <p class="lead">Esta acta ya ha sido firmada y procesada correctamente.</p>
            <a href="#" onclick="location.reload()" class="btn btn-outline-primary mt-3">Recargar</a>
        </div>
    <?php else: ?>
        
        <div class="paper" id="vistaActa">
            <div class="acta-header">
                <div class="acta-title">Acta de Transferencia de Cargo</div>
                <div class="acta-meta mt-2">ID Solicitud: #<?php echo $datos['id_token']; ?> | Fecha: <?php echo date('d/m/Y'); ?></div>
            </div>

            <div class="alert alert-info border-0 shadow-sm">
                <i class="fas fa-info-circle me-2"></i> Usted está por aceptar la responsabilidad sobre el siguiente bien:
            </div>

            <div class="detail-box">
                <h6 class="border-bottom pb-2 mb-3 fw-bold text-primary"><i class="fas fa-box me-2"></i>DATOS DEL BIEN</h6>
                <div class="row g-3">
                    <div class="col-md-8">
                        <span class="detail-label">Descripción / Elemento</span>
                        <div class="detail-value"><?php echo htmlspecialchars($bien['elemento']); ?></div>
                    </div>
                    <div class="col-md-4">
                        <span class="detail-label">Tipo</span>
                        <div class="detail-value"><?php echo htmlspecialchars($bien['tipo_bien'] ?? 'General'); ?></div>
                    </div>
                    <div class="col-md-4">
                        <span class="detail-label">Cód. Patrimonial</span>
                        <div class="detail-value"><?php echo htmlspecialchars($bien['codigo_patrimonial'] ?? '---'); ?></div>
                    </div>
                    <div class="col-md-4">
                        <span class="detail-label">N° Serie / Grabado</span>
                        <div class="detail-value"><?php echo htmlspecialchars($bien['mat_numero_grabado'] ?? $bien['nro_serie'] ?? '---'); ?></div>
                    </div>
                    <div class="col-md-4">
                        <span class="detail-label">N° IOSFA</span>
                        <div class="detail-value"><?php echo htmlspecialchars($bien['n_iosfa'] ?? '---'); ?></div>
                    </div>
                </div>
            </div>

            <div class="detail-box">
                <h6 class="border-bottom pb-2 mb-3 fw-bold text-success"><i class="fas fa-exchange-alt me-2"></i>DETALLES DE TRANSFERENCIA</h6>
                <div class="row g-3">
                    <div class="col-md-6 border-end">
                        <span class="detail-label text-muted">Ubicación Anterior (Origen)</span>
                        <div class="text-secondary"><?php echo htmlspecialchars($bien['destino_principal'] . " - " . $bien['servicio_ubicacion']); ?></div>
                        <div class="small mt-1">Resp: <?php echo htmlspecialchars($bien['nombre_responsable']); ?></div>
                    </div>
                    <div class="col-md-6">
                        <span class="detail-label text-success">Nueva Ubicación (Destino)</span>
                        <div class="detail-value text-success"><?php echo htmlspecialchars($datos['nuevo_destino_nombre'] . " - " . $datos['nueva_area_nombre']); ?></div>
                        <div class="small mt-1 fw-bold">Nuevo Resp: <?php echo htmlspecialchars($datos['nuevo_responsable_nombre']); ?></div>
                    </div>
                </div>
            </div>

            <div class="form-check mb-4 p-3 bg-light rounded border">
                <input class="form-check-input" type="checkbox" id="checkAcepto" style="transform: scale(1.2); margin-right: 10px;">
                <label class="form-check-label fw-bold text-dark" for="checkAcepto">
                    Declaro que he verificado el estado del bien y acepto la transferencia bajo mi responsabilidad.
                </label>
            </div>

            <div class="d-grid mt-4">
                <button class="btn btn-primary btn-lg fw-bold p-3" id="btnIniciarFirma" onclick="iniciarFirma()" disabled>
                    <i class="fas fa-file-signature me-2"></i> FIRMAR Y ACEPTAR ACTA
                </button>
            </div>
        </div>

        <div class="modal fade" id="modalFirmaExterna" data-bs-backdrop="static" tabindex="-1">
            <div class="modal-dialog modal-dialog-centered" id="modalDialog">
                <div class="modal-content">
                    <div class="modal-header bg-dark text-white">
                        <h5 class="modal-title">Autenticación de Firma</h5>
                        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body p-4 bg-light d-flex flex-column align-items-center justify-content-center">
                        
                        <div id="step-1" class="modal-step active w-100" style="max-width: 400px;">
                            <p class="mb-3 text-center">Para validar su identidad, enviaremos un código de seguridad a su correo.</p>
                            <label class="fw-bold mb-1">Su Correo Electrónico:</label>
                            <input type="email" id="email_input" class="form-control mb-3" placeholder="ejemplo@correo.com">
                            <div class="d-grid">
                                <button class="btn btn-primary" onclick="enviarOTP()" id="btnEnviar">Enviar Código</button>
                            </div>
                        </div>

                        <div id="step-2" class="modal-step w-100" style="max-width: 400px;">
                            <div class="alert alert-success"><i class="fas fa-check"></i> Código enviado. Revise su bandeja.</div>
                            <label class="fw-bold mb-1">Ingrese el Código (6 dígitos):</label>
                            <input type="text" id="otp_input" class="form-control text-center fw-bold fs-3 mb-3" maxlength="6" placeholder="000000">
                            <div class="d-grid">
                                <button class="btn btn-success" onclick="validarOTP()">Validar Código</button>
                            </div>
                        </div>

                        <div id="step-3" class="modal-step w-100">
                            <div id="canvasContainer">
                                <canvas id="signaturePad" style="width:100%; height:100%; display:block; touch-action: none;"></canvas>
                                <div class="firma-linea"></div>
                                <div class="firma-texto">FIRME SOBRE LA LÍNEA</div>
                            </div>
                            <div class="d-flex justify-content-center mt-4 gap-3">
                                <button class="btn btn-outline-danger px-4 rounded-pill" onclick="limpiarFirma()">
                                    <i class="fas fa-eraser me-2"></i>Borrar
                                </button>
                                <button class="btn btn-success px-5 fw-bold rounded-pill shadow" onclick="confirmarTransferencia()" id="btnFinal">
                                    <i class="fas fa-check me-2"></i>ACEPTAR FIRMA
                                </button>
                            </div>
                        </div>

                    </div>
                </div>
            </div>
        </div>

        <div class="modal fade" id="modalExito" tabindex="-1" data-bs-backdrop="static">
            <div class="modal-dialog modal-dialog-centered text-center">
                <div class="modal-content border-success">
                    <div class="modal-body p-5">
                        <i class="fas fa-check-circle text-success fa-5x mb-3"></i>
                        <h2 class="fw-bold text-success">¡Transferencia Exitosa!</h2>
                        <p class="lead mb-4">El acta ha sido firmada y procesada correctamente.</p>
                        <p class="text-muted small">Se ha enviado una copia a su correo electrónico.</p>
                        <a id="btnDescargaPDF" href="#" class="btn btn-primary btn-lg w-100 mb-3 fw-bold shadow">
                            <i class="fas fa-file-pdf me-2"></i> DESCARGAR ACTA AHORA
                        </a>
                        <button class="btn btn-outline-secondary w-100" onclick="location.reload()">Cerrar</button>
                    </div>
                </div>
            </div>
        </div>

    <?php endif; ?>
</div>

<script>
    const TOKEN = "<?php echo $token; ?>";
    let modal, signaturePad;
    let emailUsuario = ""; 

    // LÓGICA DEL CHECKBOX
    document.getElementById('checkAcepto')?.addEventListener('change', function() {
        document.getElementById('btnIniciarFirma').disabled = !this.checked;
    });

    function iniciarFirma() {
        modal = new bootstrap.Modal(document.getElementById('modalFirmaExterna'));
        modal.show();
    }

    function enviarOTP() {
        let email = document.getElementById('email_input').value;
        if(!email.includes('@')) return alert("Ingrese un email válido.");
        
        emailUsuario = email; 
        let btn = document.getElementById('btnEnviar');
        btn.disabled = true; btn.innerText = "Enviando...";

        let formData = new FormData();
        formData.append('accion', 'enviar_otp');
        formData.append('token', TOKEN);
        formData.append('email', email);

        fetch('externo_guardar_firma.php', { method: 'POST', body: formData })
        .then(r => r.json())
        .then(res => {
            if(res.status === 'success') {
                document.getElementById('step-1').style.display = 'none';
                document.getElementById('step-2').style.display = 'block';
            } else {
                alert("Error: " + res.msg);
                btn.disabled = false; btn.innerText = "Enviar Código";
            }
        })
        .catch(e => { alert("Error de conexión"); btn.disabled = false; });
    }

    function validarOTP() {
        let otp = document.getElementById('otp_input').value;
        if(otp.length < 6) return alert("Código incompleto");

        document.getElementById('step-2').style.display = 'none';
        document.getElementById('step-3').style.display = 'block';
        
        // CAMBIAR MODAL A FULLSCREEN PARA FIRMAR CÓMODAMENTE
        document.getElementById('modalDialog').classList.remove('modal-dialog-centered');
        document.getElementById('modalDialog').classList.add('modal-fullscreen');

        // INICIAR CANVAS CON LÓGICA "PERFIL" (Alta Definición)
        setTimeout(() => {
            let canvas = document.getElementById('signaturePad');
            let container = document.getElementById('canvasContainer');
            
            // Nitidez
            var ratio = Math.max(window.devicePixelRatio || 1, 1);
            canvas.width = container.offsetWidth * ratio;
            canvas.height = container.offsetHeight * ratio;
            canvas.getContext("2d").scale(ratio, ratio);
            
            signaturePad = new SignaturePad(canvas, {
                minWidth: 1, 
                maxWidth: 2.5, 
                penColor: "rgb(0, 0, 0)", 
                velocityFilterWeight: 0.7
            });
        }, 300);
    }

    function limpiarFirma() { if(signaturePad) signaturePad.clear(); }

    function confirmarTransferencia() {
        if(!signaturePad || signaturePad.isEmpty()) return alert("Debe firmar sobre la línea.");
        
        let btn = document.getElementById('btnFinal');
        btn.disabled = true; btn.innerText = "PROCESANDO...";

        let otp = document.getElementById('otp_input').value;
        let firma = signaturePad.toDataURL('image/png');
        let nombre = "<?php echo $datos['nuevo_responsable_nombre'] ?? 'Usuario Externo'; ?>";

        let formData = new FormData();
        formData.append('accion', 'confirmar');
        formData.append('token', TOKEN);
        formData.append('otp', otp);
        formData.append('firma_base64', firma);
        formData.append('nombre_firmante', nombre);
        formData.append('email_final', emailUsuario); 

        fetch('externo_guardar_firma.php', { method: 'POST', body: formData })
        .then(r => r.json())
        .then(res => {
            if(res.status === 'ok') {
                modal.hide();
                document.getElementById('btnDescargaPDF').href = res.pdf_url;
                new bootstrap.Modal(document.getElementById('modalExito')).show();
            } else {
                alert("Error al guardar: " + res.msg);
                btn.disabled = false; btn.innerText = "ACEPTAR FIRMA";
            }
        })
        .catch(e => { alert("Error crítico de conexión."); btn.disabled = false; });
    }
</script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>