<?php
// Archivo: externo_validar.php
session_start();
include 'conexion.php';

$token = $_GET['t'] ?? '';
$error = "";

$stmt = $pdo->prepare("SELECT * FROM verificaciones_externas WHERE token_acceso = ? AND estado = 'pendiente'");
$stmt->execute([$token]);
$solicitud = $stmt->fetch();

if (!$solicitud) die("Sesión inválida.");

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $codigo_ingresado = trim($_POST['codigo']);
    $ahora = date('Y-m-d H:i:s');
    
    // Verificar si ya pasaron las 24 horas
    if ($solicitud['otp_expiracion'] < $ahora) {
        $error = "El código ha caducado (duración máx 24hs). Por favor solicite uno nuevo.";
    } 
    elseif ($solicitud['codigo_otp'] == $codigo_ingresado) {
        // CÓDIGO CORRECTO
        $pdo->prepare("UPDATE verificaciones_externas SET estado='verificado' WHERE id_verificacion=?")->execute([$solicitud['id_verificacion']]);
        header("Location: externo_firmar.php?t=$token");
        exit();
    } else {
        $error = "Código incorrecto. Revise su email.";
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Validar Email</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body class="bg-light">
    <div class="container mt-5">
        <div class="row justify-content-center">
            <div class="col-md-6 col-lg-4">
                <div class="card shadow border-0">
                    <div class="card-body p-4 text-center">
                        <div class="mb-3 text-primary">
                            <i class="fas fa-envelope-open-text fa-3x"></i>
                        </div>
                        <h4 class="mb-2">Revise su Correo</h4>
                        <p class="text-muted small">
                            Enviamos un código a: <br>
                            <strong><?php echo htmlspecialchars($solicitud['email_usuario']); ?></strong>
                        </p>

                        <?php if($error): ?>
                            <div class="alert alert-danger py-2 small"><?php echo $error; ?></div>
                        <?php endif; ?>

                        <form method="POST">
                            <div class="mb-4">
                                <input type="number" name="codigo" class="form-control form-control-lg text-center fw-bold" placeholder="000000" autocomplete="off" required>
                            </div>
                            
                            <div class="d-grid mb-3">
                                <button type="submit" class="btn btn-success fw-bold">Validar y Firmar</button>
                            </div>
                        </form>
                        
                        <div class="mt-3">
                            <a href="externo_login.php?t=<?php echo $token; ?>" class="small text-decoration-none text-muted">
                                ¿No llegó? Intentar con otro email
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</body>
</html>