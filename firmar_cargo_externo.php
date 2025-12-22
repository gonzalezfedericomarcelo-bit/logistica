<?php
// Archivo: firmar_cargo_externo.php (LANDING PAGE PARA FIRMA REMOTA)
session_start();
include 'conexion.php';

$token = $_GET['t'] ?? '';

// Buscar solicitud
$stmt = $pdo->prepare("SELECT r.*, c.elemento, c.codigo_patrimonial, c.n_iosfa, c.servicio_ubicacion, c.observaciones, c.id_tipo_bien, t.nombre as nombre_tipo, r.nombre_firmante 
                       FROM inventario_firmas_remotas r
                       JOIN inventario_cargos c ON r.id_cargo = c.id_cargo
                       LEFT JOIN inventario_tipos_bien t ON c.id_tipo_bien = t.id_tipo_bien
                       WHERE r.token = ? AND r.estado IN ('pendiente', 'verificado')");
$stmt->execute([$token]);
$data = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$data) {
    die("<!DOCTYPE html><html><head><title>Error</title><link href='https://cdn.jsdelivr.net/npm/bootstrap@5.3/dist/css/bootstrap.min.css' rel='stylesheet'></head><body class='bg-light d-flex align-items-center justify-content-center' style='height:100vh'><div class='text-center'><h1 class='text-danger'>Enlace No Válido</h1><p>El enlace ha expirado, ya fue firmado o no existe.</p></div></body></html>");
}

// Obtener campos dinámicos para mostrar detalle
$stmtDyn = $pdo->prepare("SELECT c.etiqueta, v.valor FROM inventario_campos_dinamicos c JOIN inventario_valores_dinamicos v ON c.id_campo = v.id_campo WHERE v.id_cargo = ? AND c.etiqueta NOT LIKE '%IOSFA%' AND c.etiqueta NOT LIKE '%PATRIMONIAL%'");
$stmtDyn->execute([$data['id_cargo']]);
$dinamicos = $stmtDyn->fetchAll(PDO::FETCH_ASSOC);

$titulo_rol = ($data['rol'] == 'jefe') ? 'JEFE DE SERVICIO' : 'RESPONSABLE DE CARGO';
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Firma Remota de Cargo</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        body { background-color: #f4f6f9; }
        .card-main { max-width: 900px; margin: 30px auto; border: none; box-shadow: 0 10px 30px rgba(0,0,0,0.1); border-radius: 12px; overflow: hidden; }
        .header-bg { background: linear-gradient(135deg, #0d6efd, #0a58ca); color: white; padding: 25px; }
        .step-section { display: none; animation: fadeIn 0.5s; }
        .step-section.active { display: block; }
        @keyframes fadeIn { from { opacity: 0; transform: translateY(10px); } to { opacity: 1; transform: translateY(0); } }
        #canvasContainer { width: 100%; height: 250px; background: #fff; border: 2px dashed #ccc; border-radius: 8px; position: relative; }
    </style>
</head>
<body>

<div class="container">
    <div class="card card-main">
        <div class="header-bg text-center">
            <h3 class="fw-bold mb-0"><i class="fas fa-file-signature me-2"></i> Firma Digital Remota</h3>
            <p class="mb-0 opacity-75">Sistema de Inventario - <?php echo $titulo_rol; ?></p>
        </div>

        <div class="card-body p-4 p-md-5">

            <div id="step-1" class="step-section active">
                <div class="alert alert-info border-0 shadow-sm mb-4">
                    <i class="fas fa-info-circle me-2"></i>Por favor revise los detalles del cargo asignado. Si todo es correcto, proceda a autenticarse.
                </div>

                <div class="card bg-light border-0 mb-4">
                    <div class="card-body">
                        <h4 class="fw-bold text-dark"><?php echo htmlspecialchars($data['elemento']); ?></h4>
                        <div class="text-primary fw-bold mb-3"><?php echo htmlspecialchars($data['nombre_tipo']); ?></div>
                        
                        <div class="row g-3">
                            <div class="col-md-6"><small class="text-muted d-block fw-bold">Ubicación</small> <?php echo htmlspecialchars($data['servicio_ubicacion']); ?></div>
                            <div class="col-md-6"><small class="text-muted d-block fw-bold">Código Patrimonial</small> <?php echo htmlspecialchars($data['codigo_patrimonial'] ?: '---'); ?></div>
                            <div class="col-12"><small class="text-muted d-block fw-bold">Observaciones</small> <?php echo htmlspecialchars($data['observaciones'] ?: 'Sin observaciones'); ?></div>
                            
                            <?php if($dinamicos): ?>
                            <div class="col-12"><hr></div>
                            <?php foreach($dinamicos as $d): ?>
                                <div class="col-md-4 col-6"><small class="text-muted d-block fw-bold"><?php echo htmlspecialchars($d['etiqueta']); ?></small> <?php echo htmlspecialchars($d['valor']); ?></div>
                            <?php endforeach; endif; ?>
                        </div>
                    </div>
                </div>

                <div class="text-center mt-4">
                    <button class="btn btn-primary btn-lg px-5 rounded-pill shadow" onclick="goToStep(2)">
                        <i class="fas fa-shield-alt me-2"></i>Autenticar y Firmar
                    </button>
                </div>
            </div>

            <div id="step-2" class="step-section">
                <h5 class="fw-bold text-center mb-4">Validación de Identidad</h5>
                
                <div class="row justify-content-center">
                    <div class="col-md-8">
                        <div class="mb-3">
                            <label class="fw-bold small">Nombre Completo (Quien firma)</label>
                            <input type="text" id="nombre_firmante" class="form-control form-control-lg" value="<?php echo htmlspecialchars($data['nombre_firmante']); ?>" placeholder="Ej: Juan Perez">
                        </div>
                        <div class="mb-4">
                            <label class="fw-bold small">Correo Electrónico (Para recibir código)</label>
                            <input type="email" id="email_firmante" class="form-control form-control-lg" value="<?php echo htmlspecialchars($data['email_destinatario']); ?>" placeholder="nombre@correo.com">
                            <div class="form-text">Enviaremos un código de seguridad válido por 48hs.</div>
                        </div>
                        
                        <div class="d-grid gap-2">
                            <button class="btn btn-dark btn-lg" id="btnEnviarOTP" onclick="enviarOTP()">
                                <i class="fas fa-paper-plane me-2"></i>Autenticar con Token
                            </button>
                            <button class="btn btn-link text-muted" onclick="goToStep(1)">Volver a detalles</button>
                        </div>
                    </div>
                </div>
            </div>

            <div id="step-3" class="step-section">
                <div class="text-center mb-4">
                    <i class="fas fa-envelope-open-text fa-3x text-primary mb-3"></i>
                    <h5>Ingrese el Código</h5>
                    <p class="text-muted">Hemos enviado un código de 6 dígitos a su correo.</p>
                </div>
                
                <div class="row justify-content-center">
                    <div class="col-md-6">
                        <div class="mb-4">
                            <input type="number" id="otp_input" class="form-control form-control-lg text-center fw-bold fs-2" placeholder="000000">
                        </div>
                        <div class="d-grid">
                            <button class="btn btn-success btn-lg" onclick="verificarOTP()">Verificar Código</button>
                        </div>
                    </div>
                </div>
            </div>

            <div id="step-4" class="step-section">
                <h5 class="fw-bold text-center mb-3">Zona de Firma</h5>
                <p class="text-center text-muted small mb-3">Dibuje su firma en el recuadro para finalizar.</p>
                
                <div id="canvasContainer">
                    <canvas id="signaturePad" style="width:100%; height:100%; display:block; touch-action:none;"></canvas>
                </div>
                
                <div class="row mt-4">
                    <div class="col-6">
                        <button class="btn btn-outline-danger w-100" onclick="pad.clear()">Borrar</button>
                    </div>
                    <div class="col-6">
                        <button class="btn btn-success fw-bold w-100 shadow" id="btnGuardarFirma" onclick="guardarFirma()">CONFIRMAR FIRMA</button>
                    </div>
                </div>
            </div>

            <div id="step-5" class="step-section text-center py-5">
                <i class="fas fa-check-circle fa-5x text-success mb-3"></i>
                <h2 class="fw-bold">¡Firma Registrada!</h2>
                <p class="text-muted">El proceso ha finalizado correctamente. Puede cerrar esta ventana.</p>
            </div>

        </div>
    </div>
</div>

<script src="https://code.jquery.com/jquery-3.7.0.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/signature_pad@4.0.0/dist/signature_pad.umd.min.js"></script>
<script>
    const token = "<?php echo $token; ?>";
    let pad = null;

    function goToStep(n) {
        $('.step-section').removeClass('active');
        $('#step-'+n).addClass('active');
        if(n === 4) initPad();
    }

    function initPad() {
        let canvas = document.getElementById('signaturePad');
        let container = document.getElementById('canvasContainer');
        let ratio = Math.max(window.devicePixelRatio || 1, 1);
        canvas.width = container.offsetWidth * ratio;
        canvas.height = container.offsetHeight * ratio;
        canvas.getContext("2d").scale(ratio, ratio);
        pad = new SignaturePad(canvas, { minWidth: 1, maxWidth: 2.5 });
    }

    function enviarOTP() {
        let nombre = $('#nombre_firmante').val();
        let email = $('#email_firmante').val();
        
        if(nombre.length < 3 || !email.includes('@')) return alert("Complete los datos correctamente.");
        
        $('#btnEnviarOTP').prop('disabled', true).html('<i class="fas fa-spinner fa-spin"></i> Enviando...');
        
        $.post('firmar_cargo_procesar.php', { accion: 'enviar_otp', token: token, nombre: nombre, email: email }, function(res) {
            if(res.status === 'success') {
                goToStep(3);
            } else {
                alert(res.msg);
                $('#btnEnviarOTP').prop('disabled', false).html('Autenticar con Token');
            }
        }, 'json');
    }

    function verificarOTP() {
        let otp = $('#otp_input').val();
        $.post('firmar_cargo_procesar.php', { accion: 'verificar_otp', token: token, otp: otp }, function(res) {
            if(res.status === 'success') {
                goToStep(4);
            } else {
                alert("Código incorrecto o expirado.");
            }
        }, 'json');
    }

    function guardarFirma() {
        if(pad.isEmpty()) return alert("Debe firmar.");
        
        $('#btnGuardarFirma').prop('disabled', true).html('Guardando...');
        let data = pad.toDataURL();
        
        $.post('firmar_cargo_procesar.php', { accion: 'guardar_firma', token: token, firma: data }, function(res) {
            if(res.status === 'success') {
                goToStep(5);
            } else {
                alert(res.msg);
                $('#btnGuardarFirma').prop('disabled', false).html('CONFIRMAR FIRMA');
            }
        }, 'json');
    }
</script>
</body>
</html>