<?php
// Archivo: externo_login.php
session_start();
include 'conexion.php';

// 1. Validar el Token del Link
$token = $_GET['t'] ?? '';
if (empty($token)) die("Enlace inválido o incompleto.");

// 2. Verificar en la Base de Datos
$stmt = $pdo->prepare("SELECT * FROM verificaciones_externas WHERE token_acceso = ? AND estado = 'pendiente'");
$stmt->execute([$token]);
$solicitud = $stmt->fetch();

if (!$solicitud) die("Este enlace ya fue utilizado, venció o no existe.");

// =======================================================================
// CONFIGURACIÓN DE CORREO HOSTINGER (SMTP)
// =======================================================================
define('SMTP_HOST', 'smtp.hostinger.com');      // Servidor Hostinger (No tocar)
define('SMTP_PORT', 465);                       // Puerto SSL (No tocar)
define('SMTP_USER', 'info@federicogonzalez.net'); // Tu usuario (No tocar)

// ↓↓↓↓↓↓↓↓↓↓↓↓↓↓↓↓↓↓↓↓↓↓↓↓↓↓↓↓↓↓↓↓↓↓↓↓↓↓↓↓↓↓↓↓↓↓↓↓↓↓↓↓↓↓↓↓↓↓↓↓↓↓↓↓↓↓↓↓↓↓↓
define('SMTP_PASS', 'Fmg35911@'); // <--- BORRA ESTO Y PONE TU CLAVE REAL
// ↑↑↑↑↑↑↑↑↑↑↑↑↑↑↑↑↑↑↑↑↑↑↑↑↑↑↑↑↑↑↑↑↑↑↑↑↑↑↑↑↑↑↑↑↑↑↑↑↑↑↑↑↑↑↑↑↑↑↑↑↑↑↑↑↑↑↑↑↑↑↑

// Función de Envío SMTP Manual (Sin librerías externas)
function enviarEmailSMTP($destinatario, $nombre, $codigo, $destino_nombre) {
    
    $asunto = "Codigo de Seguridad - Firma Digital";
    $cuerpo = "Hola $nombre.\r\n\r\nEstas por firmar el inventario de: $destino_nombre.\r\n\r\nTu codigo de seguridad es: $codigo\r\n\r\n(Valido por 24hs).";

    // 1. Conectar al servidor
    $socket = fsockopen("ssl://" . SMTP_HOST, SMTP_PORT, $errno, $errstr, 10);
    if (!$socket) return false;

    // Función auxiliar para leer respuesta del servidor
    function leer_server($socket, $esperado) {
        $data = "";
        while($str = fgets($socket, 515)) {
            $data .= $str;
            if(substr($str, 3, 1) == " ") { break; }
        }
        return true;
    }

    leer_server($socket, "220");
    
    // 2. Saludo
    fputs($socket, "HELO " . SMTP_HOST . "\r\n");
    leer_server($socket, "250");

    // 3. Autenticación
    fputs($socket, "AUTH LOGIN\r\n");
    leer_server($socket, "334");
    fputs($socket, base64_encode(SMTP_USER) . "\r\n");
    leer_server($socket, "334");
    fputs($socket, base64_encode(SMTP_PASS) . "\r\n");
    leer_server($socket, "235"); // Si falla acá, es la contraseña

    // 4. Configurar envío
    fputs($socket, "MAIL FROM: <" . SMTP_USER . ">\r\n");
    leer_server($socket, "250");
    fputs($socket, "RCPT TO: <" . $destinatario . ">\r\n");
    leer_server($socket, "250");
    
    // 5. Enviar contenido
    fputs($socket, "DATA\r\n");
    leer_server($socket, "354");
    
    $headers = "From: Sistema Logistica <" . SMTP_USER . ">\r\n";
    $headers .= "To: $nombre <$destinatario>\r\n";
    $headers .= "Subject: $asunto\r\n";
    $headers .= "MIME-Version: 1.0\r\n";
    $headers .= "Content-Type: text/plain; charset=UTF-8\r\n";
    
    fputs($socket, $headers . "\r\n" . $cuerpo . "\r\n.\r\n");
    $resultado = leer_server($socket, "250");
    
    // 6. Cerrar
    fputs($socket, "QUIT\r\n");
    fclose($socket);
    
    return true;
}

// PROCESAR FORMULARIO
$error = "";
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $nombre = trim($_POST['nombre']);
    $email = trim($_POST['email']);
    
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = "El correo no es válido.";
    } else {
        // Generar Código OTP
        $otp = rand(100000, 999999);
        $expira = date('Y-m-d H:i:s', strtotime('+24 hours'));
        
        // Actualizar Base de Datos
        $upd = $pdo->prepare("UPDATE verificaciones_externas SET nombre_usuario=?, email_usuario=?, codigo_otp=?, otp_expiracion=? WHERE id_verificacion=?");
        
        if ($upd->execute([$nombre, $email, $otp, $expira, $solicitud['id_verificacion']])) {
            
            // INTENTAR ENVIAR MAIL
            if (enviarEmailSMTP($email, $nombre, $otp, $solicitud['destino_objetivo'])) {
                header("Location: externo_validar.php?t=$token");
                exit();
            } else {
                $error = "Error de conexión SMTP. Verifique la contraseña del correo en el archivo.";
            }
        } else {
            $error = "Error al guardar en base de datos.";
        }
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Acceso Seguro</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>
<body class="bg-light">
    <div class="container mt-5">
        <div class="row justify-content-center">
            <div class="col-md-6 col-lg-4">
                <div class="card shadow-lg border-0">
                    <div class="card-header bg-primary text-white text-center py-3">
                        <h5 class="mb-0"><i class="fas fa-envelope me-2"></i> Validación de Identidad</h5>
                    </div>
                    <div class="card-body p-4">
                        <p class="text-center text-muted small mb-4">
                            Usted va a firmar la documentación para:<br>
                            <strong class="text-dark fs-5"><?php echo htmlspecialchars($solicitud['destino_objetivo']); ?></strong>
                        </p>
                        
                        <?php if($error): ?>
                            <div class="alert alert-danger py-2 small text-center">
                                <i class="fas fa-exclamation-triangle me-1"></i> <?php echo $error; ?>
                            </div>
                        <?php endif; ?>

                        <form method="POST">
                            <div class="mb-3">
                                <label class="form-label fw-bold small">Su Nombre Completo</label>
                                <input type="text" name="nombre" class="form-control" required placeholder="Ej: Juan Pérez" value="<?php echo isset($_POST['nombre']) ? htmlspecialchars($_POST['nombre']) : ''; ?>">
                            </div>
                            
                            <div class="mb-4">
                                <label class="form-label fw-bold small">Su Correo Electrónico</label>
                                <input type="email" name="email" class="form-control" required placeholder="nombre@ejemplo.com" value="<?php echo isset($_POST['email']) ? htmlspecialchars($_POST['email']) : ''; ?>">
                                <small class="text-muted">Le enviaremos un código válido por 24hs.</small>
                            </div>
                            
                            <div class="d-grid">
                                <button type="submit" class="btn btn-primary fw-bold">
                                    Enviar Código <i class="fas fa-paper-plane ms-2"></i>
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>
</body>
</html>