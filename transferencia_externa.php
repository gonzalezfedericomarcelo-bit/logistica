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
        // Traer datos del bien
        // NOTA: Si ya está confirmado, los datos del bien en 'inventario_cargos' YA CAMBIARON.
        // Pero no importa, porque para los PDFs usamos los archivos estáticos generados al inicio.
        $stmtB = $pdo->prepare("SELECT * FROM inventario_cargos WHERE id_cargo = ?");
        $stmtB->execute([$datos['id_bien']]);
        $bien = $stmtB->fetch(PDO::FETCH_ASSOC);
    } else {
        $error = "Enlace no válido o expirado.";
    }
} else {
    $error = "Enlace inválido.";
}

// URL base para compartir
$baseUrl = "https://" . $_SERVER['HTTP_HOST'] . dirname($_SERVER['PHP_SELF']);
// Rutas de los PDFs estáticos
$urlPdfViejo = $baseUrl . "/pdfs_publicos/inventario_pdf/old_" . $token . ".pdf";
$urlPdfNuevo = $baseUrl . "/pdfs_publicos/inventario_pdf/new_" . $token . ".pdf";
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Validar Transferencia</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <script src="https://code.jquery.com/jquery-3.7.0.min.js"></script>
    <style> body { background-color: #f0f2f5; font-family: 'Segoe UI', sans-serif; } </style>
</head>
<body>
    <div class="container mt-4 mb-5">
        <div class="row justify-content-center">
            <div class="col-md-7 col-lg-6">
                
                <?php if ($error): ?>
                    <div class="alert alert-danger shadow p-4 text-center rounded-4">
                        <h3>Error</h3><p><?php echo $error; ?></p>
                    </div>
                <?php elseif ($datos && $bien): ?>

                    <div class="card shadow-lg border-0 <?php echo $yaConfirmado ? 'd-none' : ''; ?>" id="cardInicio">
                        <div class="card-header bg-primary text-white text-center py-4">
                            <h4 class="mb-0">Validar Transferencia</h4>
                        </div>
                        <div class="card-body p-4 text-center">
                            <h5 class="text-primary fw-bold"><?php echo htmlspecialchars($bien['elemento']); ?></h5>
                            <p class="text-muted mb-4"><?php echo $bien['codigo_inventario']; ?></p>

                            <div class="bg-white p-3 rounded border mb-4 text-start small shadow-sm">
                                <p class="mb-1"><strong>N° Serie:</strong> <?php echo $bien['mat_numero_grabado']; ?></p>
                                <p class="mb-1"><strong>Ubicación Actual:</strong> <?php echo $bien['servicio_ubicacion']; ?></p>
                                <p class="mb-3"><strong>Responsable:</strong> <?php echo $bien['nombre_responsable']; ?></p>
                                <hr>
                                <p class="mb-1 text-primary"><strong>Nuevo Destino:</strong> <?php echo $datos['nuevo_destino_nombre']; ?></p>
                                <p class="mb-0 text-primary"><strong>Recibe:</strong> <?php echo $datos['nuevo_responsable_nombre']; ?></p>
                            </div>

                            <div class="d-grid mb-4">
                                <a href="<?php echo $urlPdfViejo; ?>" target="_blank" class="btn btn-outline-danger fw-bold border-2">
                                    <i class="fas fa-file-pdf me-2"></i> VER FICHA ACTUAL (ANTIGUA)
                                </a>
                                <div class="form-text text-center small mt-1">Verifique el documento antes de entregar.</div>
                            </div>

                            <hr>
                            <input type="email" id="emailUser" class="form-control text-center mb-3" placeholder="Correo Institucional">
                            <button id="btnSolicitar" class="btn btn-primary w-100 btn-lg" onclick="enviarOTP()">SOLICITAR CÓDIGO</button>
                        </div>
                    </div>

                    <div class="card shadow-lg border-0 d-none" id="cardOTP">
                        <div class="card-body p-5 text-center">
                            <h4 class="mb-4">Verificar Identidad</h4>
                            <p>Código enviado a <strong id="emailDisplay"></strong></p>
                            <input type="number" id="otpInput" class="form-control form-control-lg text-center fw-bold mb-4" placeholder="000000" style="font-size: 2rem; letter-spacing: 5px;">
                            <button id="btnConfirmar" class="btn btn-success w-100 btn-lg mb-3" onclick="confirmarTransferencia()">CONFIRMAR ENTREGA</button>
                            <button class="btn btn-link text-muted" onclick="location.reload()">Volver</button>
                        </div>
                    </div>

                    <div class="card shadow-lg border-0 <?php echo $yaConfirmado ? '' : 'd-none'; ?>" id="cardFinal">
                        <div class="card-body p-5 text-center">
                            <div class="mb-4"><i class="fas fa-check-circle text-success" style="font-size: 5rem;"></i></div>
                            <h2 class="fw-bold text-success mb-3">¡Transferencia Exitosa!</h2>
                            <p class="text-muted mb-4">El bien ha sido liberado correctamente.</p>
                            
                            <div class="d-grid gap-3 mb-4">
                                <a href="<?php echo $urlPdfViejo; ?>" target="_blank" class="btn btn-secondary btn-lg">
                                    <i class="fas fa-history me-2"></i> Descargar Acta Antigua
                                </a>
                                
                                <a href="<?php echo $urlPdfNuevo; ?>" target="_blank" class="btn btn-danger btn-lg shadow">
                                    <i class="fas fa-file-contract me-2"></i> Descargar Nueva Acta
                                </a>
                            </div>

                            <hr>
                            <h6 class="fw-bold text-muted mb-3 small">COMPARTIR NUEVA ACTA</h6>
                            <div class="d-flex gap-2">
                                <button onclick="window.open('https://wa.me/?text=Acta:%20<?php echo urlencode($urlPdfNuevo); ?>', '_blank')" class="btn btn-success flex-grow-1">
                                    <i class="fab fa-whatsapp me-2"></i> WhatsApp
                                </button>
                                <button onclick="location.href='mailto:?subject=Acta&body=Link: <?php echo $urlPdfNuevo; ?>'" class="btn btn-dark flex-grow-1">
                                    <i class="fas fa-envelope me-2"></i> Email
                                </button>
                            </div>
                        </div>
                    </div>

                <?php endif; ?>
            </div>
        </div>
    </div>

    <script>
        const token = "<?php echo $token; ?>";

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
                    $('#btnSolicitar').prop('disabled', false).text('SOLICITAR CÓDIGO');
                }
            }, 'json').fail(function() { alert('Error conexión'); $('#btnSolicitar').prop('disabled', false).text('SOLICITAR CÓDIGO'); });
        }

        function confirmarTransferencia() {
            const otp = $('#otpInput').val();
            $('#btnConfirmar').prop('disabled', true).text('Verificando...');
            
            $.post('transferencia_externa_validar.php', { accion: 'confirmar', token: token, otp: otp }, function(res) {
                if(res.status === 'ok') {
                    $('#cardOTP').addClass('d-none');
                    $('#cardFinal').removeClass('d-none');
                } else {
                    alert(res.msg);
                    $('#btnConfirmar').prop('disabled', false).text('CONFIRMAR ENTREGA');
                }
            }, 'json').fail(function() { alert('Error conexión'); $('#btnConfirmar').prop('disabled', false).text('CONFIRMAR ENTREGA'); });
        }
    </script>
</body>
</html>