<?php
// Archivo: inventario_validar_transferencia.php
session_start();
include 'conexion.php';
include 'funciones_permisos.php';

// 1. Validar Sesión
if (!isset($_SESSION['usuario_id'])) {
    header("Location: login.php"); exit();
}

// ---------------------------------------------------------
// VALIDACIÓN ROBUSTA (A PRUEBA DE FALLOS)
// ---------------------------------------------------------
$rol_actual = strtolower(trim($_SESSION['rol'] ?? ''));
$acceso_permitido = false;

// Opción A: Tiene el permiso activado (Ideal)
if (function_exists('tiene_permiso') && tiene_permiso('inventario_validar_patrimonial', $pdo)) {
    $acceso_permitido = true;
}

// Opción B: Es Admin (Siempre pasa)
if ($rol_actual === 'admin') {
    $acceso_permitido = true;
}

// Opción C (FALLBACK): El nombre del rol coincide (Por si falla el sistema de permisos)
// Aceptamos variaciones comunes para evitar bloqueos
$roles_permitidos = ['cargopatrimonial', 'cargo patrimonial', 'patrimonio', 'encargado patrimonio'];
if (in_array($rol_actual, $roles_permitidos)) {
    $acceso_permitido = true;
}

if (!$acceso_permitido) {
    // Debug visual para que sepas exactamente qué rol está detectando el sistema
    die('<div class="container mt-5"><div class="alert alert-danger shadow p-4">
            <h3><i class="fas fa-lock me-2"></i>Acceso Denegado</h3>
            <p>El sistema no detecta permisos para validar esta transferencia.</p>
            <hr>
            <strong>Diagnóstico:</strong><br>
            Rol detectado en sesión: <code>' . $_SESSION['rol'] . '</code><br>
            Permiso "inventario_validar_patrimonial": ' . (tiene_permiso('inventario_validar_patrimonial', $pdo) ? 'SÍ' : 'NO') . '
         </div></div>');
}
// ---------------------------------------------------------

$token = $_GET['token'] ?? '';
if (empty($token)) die('<div class="container mt-5"><div class="alert alert-danger">Token inválido o faltante.</div></div>');

// 2. Obtener datos de la solicitud
$stmt = $pdo->prepare("SELECT t.*, i.elemento, i.codigo_inventario, 
                       i.destino_principal as origen_dest, i.servicio_ubicacion as origen_area 
                       FROM inventario_transferencias_pendientes t
                       JOIN inventario_cargos i ON t.id_bien = i.id_cargo
                       WHERE t.token_hash = ?");
$stmt->execute([$token]);
$solicitud = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$solicitud) die('<div class="container mt-5"><div class="alert alert-warning">La solicitud no existe o el enlace ha vencido.</div></div>');

// Lógica Nombre Destino
$origen_mostrar = $solicitud['origen_dest'];
if (is_numeric($origen_mostrar)) {
    $stmtD = $pdo->prepare("SELECT nombre FROM destinos_internos WHERE id_destino = ?");
    $stmtD->execute([$origen_mostrar]);
    $resD = $stmtD->fetch(PDO::FETCH_ASSOC);
    if ($resD) $origen_mostrar = $resD['nombre'];
}

$ya_firmo = !empty($solicitud['firma_patrimonial_path']);
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Validar Transferencia</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>
<body class="bg-light">
    <?php include 'navbar.php'; ?>

    <div class="container mt-5 mb-5">
        <div class="card shadow border-0">
            <div class="card-header bg-dark text-white d-flex justify-content-between align-items-center py-3">
                <h5 class="mb-0"><i class="fas fa-stamp me-2"></i>Área de Patrimonio</h5>
                <?php if($ya_firmo): ?>
                    <span class="badge bg-success">Validado <i class="fas fa-check"></i></span>
                <?php else: ?>
                    <span class="badge bg-warning text-dark">Pendiente</span>
                <?php endif; ?>
            </div>
            <div class="card-body p-5">
                
                <h3 class="fw-bold mb-4 text-primary text-uppercase border-bottom pb-2">
                    <i class="fas fa-box me-2"></i> <?php echo htmlspecialchars($solicitud['elemento']); ?>
                </h3>
                
                <div class="row g-4 mb-4">
                    <div class="col-md-6">
                        <div class="p-3 border rounded bg-white h-100">
                            <h6 class="text-muted fw-bold text-uppercase small"><i class="fas fa-arrow-left me-1"></i> Origen</h6>
                            <p class="fs-5 mb-1 fw-bold text-secondary"><?php echo htmlspecialchars($origen_mostrar); ?></p>
                            <small class="text-muted"><?php echo htmlspecialchars($solicitud['origen_area']); ?></small>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="p-3 border rounded bg-white h-100 border-success">
                            <h6 class="text-success fw-bold text-uppercase small"><i class="fas fa-arrow-right me-1"></i> Destino Solicitado</h6>
                            <p class="fs-5 mb-1 text-success fw-bold"><?php echo htmlspecialchars($solicitud['nuevo_destino_nombre']); ?></p>
                            <small class="text-muted"><?php echo htmlspecialchars($solicitud['nueva_area_nombre']); ?></small>
                            <hr class="my-2">
                            <div class="small text-dark">
                                <strong>Nuevo Resp:</strong> <?php echo htmlspecialchars($solicitud['nuevo_responsable_nombre']); ?>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="alert alert-info shadow-sm">
                    <strong><i class="fas fa-info-circle me-2"></i>Motivo:</strong> 
                    <?php echo htmlspecialchars($solicitud['motivo_transferencia']); ?>
                </div>

                <hr class="my-4">

                <div class="text-center">
                    <?php if ($ya_firmo): ?>
                        <div class="alert alert-success d-inline-block px-5 py-4 shadow-sm">
                            <h4 class="alert-heading"><i class="fas fa-check-circle"></i> Operación Validada</h4>
                            <p class="mb-0">Su firma ya ha sido registrada en este documento.</p>
                        </div>
                        <div class="mt-4">
                            <a href="inventario_lista.php" class="btn btn-outline-dark px-4">Volver al Tablero</a>
                        </div>
                    <?php else: ?>
                        <p class="mb-3 text-muted">
                            Al confirmar, se estampará su firma digital registrada como responsable de Patrimonio.
                        </p>
                        <button class="btn btn-success btn-lg px-5 fw-bold shadow" onclick="confirmarFirma()">
                            <i class="fas fa-file-signature me-2"></i> CONFIRMAR Y FIRMAR
                        </button>
                        <div class="mt-3">
                            <a href="inventario_lista.php" class="text-decoration-none text-muted">Cancelar</a>
                        </div>
                    <?php endif; ?>
                </div>

            </div>
        </div>
    </div>

    <div class="modal fade" id="modalConfirm" tabindex="-1">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header bg-success text-white">
                    <h5 class="modal-title">Confirmar Validación</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body text-center p-4">
                    <i class="fas fa-shield-alt fa-4x text-success mb-3"></i>
                    <h5 class="fw-bold">¿Aprobar Transferencia?</h5>
                    <p class="mb-0">Esta acción es irreversible y quedará registrada.</p>
                </div>
                <div class="modal-footer justify-content-center">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                    <button type="button" class="btn btn-success fw-bold px-4" onclick="procesarFirma()">SI, APROBAR</button>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://code.jquery.com/jquery-3.7.0.min.js"></script>
    <script>
        const TOKEN = '<?php echo $token; ?>';
        const modal = new bootstrap.Modal(document.getElementById('modalConfirm'));

        function confirmarFirma() { modal.show(); }

        function procesarFirma() {
            $('#modalConfirm button').prop('disabled', true).text('Procesando...');
            
            $.post('ajax_firmar_patrimonial.php', { token: TOKEN }, function(res) {
                if(res.status === 'success') {
                    alert('¡Firma registrada correctamente!');
                    location.reload();
                } else {
                    alert('Error: ' + res.msg);
                    $('#modalConfirm button').prop('disabled', false).text('SI, APROBAR');
                    modal.hide();
                }
            }, 'json').fail(function() {
                alert('Error de conexión.');
                $('#modalConfirm button').prop('disabled', false).text('SI, APROBAR');
            });
        }
    </script>
</body>
</html>