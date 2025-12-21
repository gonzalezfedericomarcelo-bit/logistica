<?php
// Archivo: transferencia_externa.php
include 'conexion.php';

$token = $_GET['token'] ?? '';
$error = '';
$datos = null;
$bien = null;
$yaConfirmado = false;

if ($token) {
    // 1. Buscar si está pendiente
    $stmt = $pdo->prepare("SELECT * FROM inventario_transferencias_pendientes WHERE token_hash = ? AND estado = 'pendiente' AND fecha_expiracion > NOW()");
    $stmt->execute([$token]);
    $datos = $stmt->fetch(PDO::FETCH_ASSOC);

    // 2. Si no está pendiente, buscar si ya está confirmado
    if (!$datos) {
        $stmtC = $pdo->prepare("SELECT * FROM inventario_transferencias_pendientes WHERE token_hash = ? AND estado = 'confirmado'");
        $stmtC->execute([$token]);
        $datosConfirmado = $stmtC->fetch(PDO::FETCH_ASSOC);
        
        if ($datosConfirmado) {
             $datos = $datosConfirmado;
             $yaConfirmado = true;
        }
    }

    if ($datos) {
        $stmtB = $pdo->prepare("SELECT * FROM inventario_cargos WHERE id_cargo = ?");
        $stmtB->execute([$datos['id_bien']]);
        $bien = $stmtB->fetch(PDO::FETCH_ASSOC);
    } else {
        $error = "Enlace no válido o expirado.";
    }
} else {
    $error = "Enlace inválido.";
}

// URLs
$baseUrl = "https://" . $_SERVER['HTTP_HOST'] . dirname($_SERVER['PHP_SELF']);
$urlPdfViejo = $baseUrl . "/pdfs_publicos/inventario_pdf/old_" . $token . ".pdf";
$urlPdfNuevo = $baseUrl . "/pdfs_publicos/inventario_pdf/new_" . $token . ".pdf";
$urlPreview = "inventario_pdf.php?token=" . $token . "&id=" . ($bien['id_cargo'] ?? 0);
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, maximum-scale=1, user-scalable=0">
    <title>Validar Transferencia</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <script src="https://code.jquery.com/jquery-3.7.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/signature_pad@4.0.0/dist/signature_pad.umd.min.js"></script>
    
    <style> 
        body { background-color: #f0f2f5; font-family: 'Segoe UI', sans-serif; } 
        .pdf-frame { width: 100%; height: 350px; border: 1px solid #dee2e6; border-radius: 8px; background: #fff; }
        
        /* ESTILO PARA LA FIRMA EN PANTALLA COMPLETA */
        #signature-overlay {
            position: fixed; top: 0; left: 0; width: 100%; height: 100%;
            background: white; z-index: 9999; display: flex; flex-direction: column;
            padding: 20px; box-sizing: border-box;
        }
        #signature-body { 
            flex: 1; 
            position: relative; 
            border: 2px solid #ccc; 
            border-radius: 10px; 
            background: #fff; 
            margin: 10px 0; 
            overflow: hidden;
        }
        /* LÍNEA DE FIRMA */
        .signature-line {
            position: absolute;
            bottom: 30%;
            left: 5%;
            right: 5%;
            border-bottom: 2px dashed #bbb;
            pointer-events: none; /* Para que no interfiera con el dibujo */
            z-index: 0;
        }
        .signature-text {
            position: absolute;
            bottom: 25%;
            width: 100%;
            text-align: center;
            color: #bbb;
            font-size: 0.9rem;
            pointer-events: none;
            z-index: 0;
            text-transform: uppercase;
        }

        canvas { width: 100%; height: 100%; display: block; position: relative; z-index: 1; }
        .rotate-msg { display: none; color: orange; font-weight: bold; text-align: center; }
        
        @media (orientation: portrait) {
            .rotate-msg { display: block; margin-bottom: 10px; }
        }
    </style>
</head>
<body>
    <div class="container mt-4 mb-5">
        <div class="row justify-content-center">
            <div class="col-md-8 col-lg-6">
                
                <?php if ($error): ?>
                    <div class="alert alert-danger shadow p-4 text-center rounded-4">
                        <h3>Enlace Inválido</h3><p class="mb-0"><?php echo $error; ?></p>
                    </div>
                <?php elseif ($datos && $bien): ?>

                    <div class="card shadow-lg border-0 <?php echo $yaConfirmado ? 'd-none' : ''; ?>" id="cardInicio">
                        <div class="card-header bg-primary text-white text-center py-4">
                            <h4 class="mb-0 fw-bold">Validar Entrega</h4>
                            <small>Transferencia de Bien Patrimonial</small>
                        </div>
                        <div class="card-body p-4">
                            <h5 class="text-primary fw-bold text-center mb-1"><?php echo htmlspecialchars($bien['elemento']); ?></h5>
                            <p class="text-muted text-center small mb-3"><?php echo $bien['codigo_inventario']; ?></p>

                            <div class="text-center mb-3">
                                <a href="<?php echo $urlPreview; ?>" target="_blank" class="btn btn-outline-secondary btn-sm mb-2 w-100">
                                    <i class="fas fa-expand me-2"></i> Abrir PDF Completo
                                </a>
                                <iframe src="<?php echo $urlPreview; ?>" class="pdf-frame shadow-sm"></iframe>
                            </div>

                            <div class="alert alert-light border small">
                                <strong>De:</strong> <?php echo $bien['nombre_responsable']; ?><br>
                                <strong>Para:</strong> <?php echo $datos['nuevo_responsable_nombre']; ?>
                            </div>

                            <div class="d-grid">
                                <label class="fw-bold mb-2">Validar Identidad (Correo Institucional)</label>
                                <input type="email" id="emailUser" class="form-control form-control-lg text-center mb-3" placeholder="usuario@iosfa.gob.ar">
                                <button id="btnSolicitar" class="btn btn-primary btn-lg fw-bold" onclick="enviarOTP()">
                                    ENVIAR CÓDIGO <i class="fas fa-arrow-right ms-2"></i>
                                </button>
                            </div>
                        </div>
                    </div>

                    <div class="card shadow-lg border-0 d-none" id="cardOTP">
                        <div class="card-body p-5 text-center">
                            <div class="mb-3"><i class="fas fa-lock text-warning" style="font-size: 3rem;"></i></div>
                            <h4 class="mb-3">Código de Seguridad</h4>
                            <p class="text-muted">Enviado a: <strong id="emailDisplay"></strong></p>
                            
                            <input type="number" id="otpInput" class="form-control form-control-lg text-center fw-bold mb-4" placeholder="000000" style="font-size: 2.5rem; letter-spacing: 8px;">
                            
                            <button id="btnVerificar" class="btn btn-warning w-100 btn-lg text-white fw-bold shadow" onclick="verificarCodigo()">
                                VERIFICAR Y FIRMAR
                            </button>
                            <button class="btn btn-link text-muted mt-3" onclick="$('#cardOTP').addClass('d-none'); $('#cardInicio').removeClass('d-none');">Cancelar</button>
                        </div>
                    </div>

                    <div class="card shadow-lg border-0 <?php echo $yaConfirmado ? '' : 'd-none'; ?>" id="cardFinal">
                        <div class="card-body p-5 text-center">
                            <div class="mb-4"><i class="fas fa-check-circle text-success" style="font-size: 5rem;"></i></div>
                            <h2 class="fw-bold text-success mb-3">¡Transferencia Completa!</h2>
                            <p class="text-muted mb-4">El bien ha sido entregado formalmente.</p>
                            
                            <div class="d-grid gap-3">
                                <a href="<?php echo $urlPdfViejo; ?>" target="_blank" class="btn btn-secondary btn-lg">
                                    <i class="fas fa-history me-2"></i> Acta Anterior
                                </a>
                                <a href="<?php echo $urlPdfNuevo; ?>" target="_blank" class="btn btn-danger btn-lg shadow">
                                    <i class="fas fa-file-contract me-2"></i> Descargar Nueva Acta
                                </a>
                            </div>
                        </div>
                    </div>

                <?php endif; ?>
            </div>
        </div>
    </div>

    <div id="signature-overlay" class="d-none">
        <div class="d-flex justify-content-between align-items-center mb-2">
            <h5 class="mb-0 fw-bold"><i class="fas fa-pen-nib me-2"></i>Firma de Entrega</h5>
            <button class="btn btn-close" onclick="cerrarFirma()"></button>
        </div>
        
        <p class="small text-muted mb-2">Yo, <strong id="signerNameDisplay">...</strong>, certifico la entrega del bien.</p>
        <div class="mb-2">
             <input type="text" id="nombreFirmante" class="form-control" placeholder="Escriba su Nombre y Apellido aquí..." value="<?php echo $bien['nombre_responsable'] ?? ''; ?>">
        </div>

        <div class="rotate-msg"><i class="fas fa-mobile-alt me-1"></i> Gire su teléfono para más espacio</div>

        <div id="signature-body">
            <div class="signature-line"></div>
            <div class="signature-text">FIRMA SOBRE ESTA LÍNEA</div>
            <canvas id="signature-pad"></canvas>
        </div>

        <div class="d-flex gap-2 mt-2" style="height: 50px;">
            <button class="btn btn-outline-danger flex-grow-1 fw-bold" onclick="limpiarFirma()">BORRAR</button>
            <button id="btnFinalizar" class="btn btn-success flex-grow-1 fw-bold" onclick="guardarFirmaYFinalizar()">CONFIRMAR ENTREGA</button>
        </div>
    </div>

    <script>
        const token = "<?php echo $token; ?>";
        let signaturePad;

        function enviarOTP() {
            const email = $('#emailUser').val();
            if(!email.includes('@')) { alert('Correo inválido'); return; }
            $('#btnSolicitar').prop('disabled', true).text('Enviando...');

            $.post('transferencia_externa_validar.php', { accion: 'enviar_otp', token: token, email: email }, function(res) {
                if(res.status === 'ok') {
                    $('#cardInicio').addClass('d-none');
                    $('#cardOTP').removeClass('d-none');
                    $('#emailDisplay').text(email);
                } else {
                    alert(res.msg);
                    $('#btnSolicitar').prop('disabled', false).html('ENVIAR CÓDIGO <i class="fas fa-arrow-right ms-2"></i>');
                }
            }, 'json');
        }

        function verificarCodigo() {
            const otp = $('#otpInput').val();
            if(otp.length < 4) { alert("Código incompleto"); return; }
            $('#btnVerificar').prop('disabled', true).text('Verificando...');

            $.post('transferencia_externa_validar.php', { accion: 'verificar_otp_only', token: token, otp: otp }, function(res) {
                if(res.status === 'ok') {
                    abrirFirma(); 
                } else {
                    alert(res.msg);
                    $('#btnVerificar').prop('disabled', false).text('VERIFICAR Y FIRMAR');
                }
            }, 'json');
        }

        // --- LÓGICA DE FIRMA ---
        function abrirFirma() {
            $('#signature-overlay').removeClass('d-none');
            const canvas = document.getElementById('signature-pad');
            const container = document.getElementById('signature-body');
            
            canvas.width = container.offsetWidth;
            canvas.height = container.offsetHeight;

            signaturePad = new SignaturePad(canvas, {
                backgroundColor: 'rgba(255, 255, 255, 0)', // Transparente para ver la línea
                penColor: 'rgb(0, 0, 0)'
            });

            window.addEventListener("resize", function() {
                const ratio =  Math.max(window.devicePixelRatio || 1, 1);
                canvas.width = container.offsetWidth * ratio;
                canvas.height = container.offsetHeight * ratio;
                canvas.getContext("2d").scale(ratio, ratio);
                signaturePad.clear();
            });
            
            $('#signerNameDisplay').text($('#nombreFirmante').val() || 'Usuario');
            $('#nombreFirmante').on('input', function(){ $('#signerNameDisplay').text(this.value); });
        }

        function cerrarFirma() {
            if(confirm("¿Cancelar firma? Deberá ingresar el código nuevamente.")) {
                $('#signature-overlay').addClass('d-none');
                $('#cardOTP').removeClass('d-none');
                $('#btnVerificar').prop('disabled', false).text('VERIFICAR Y FIRMAR');
            }
        }

        function limpiarFirma() {
            signaturePad.clear();
        }

        function guardarFirmaYFinalizar() {
            if (signaturePad.isEmpty()) { alert("Por favor firme sobre la línea."); return; }
            const nombre = $('#nombreFirmante').val();
            if (!nombre || nombre.length < 3) { alert("Escriba su Nombre y Apellido completo."); return; }

            const otp = $('#otpInput').val();
            const firma = signaturePad.toDataURL();

            $('#btnFinalizar').prop('disabled', true).html('<i class="fas fa-spinner fa-spin"></i> PROCESANDO...');

            $.post('externo_guardar_firma.php', { 
                token: token, 
                otp: otp,
                nombre_firmante: nombre,
                firma_base64: firma 
            }, function(res) {
                if(res.status === 'ok') {
                    $('#signature-overlay').addClass('d-none');
                    $('#cardOTP').addClass('d-none');
                    $('#cardFinal').removeClass('d-none');
                } else {
                    alert("Error: " + res.msg);
                    $('#btnFinalizar').prop('disabled', false).text('CONFIRMAR ENTREGA');
                }
            }, 'json').fail(function() { 
                alert('Error de conexión con el servidor.'); 
                $('#btnFinalizar').prop('disabled', false).text('CONFIRMAR ENTREGA');
            });
        }
    </script>
</body>
</html>