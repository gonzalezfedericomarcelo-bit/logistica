<?php
// Archivo: ajax_generar_token_firma.php
// OBJETIVO: Validar email, generar token y ENVIAR USANDO HOSTINGER.
session_start();
include 'conexion.php';
include 'envio_correo_hostinger.php'; // <--- CORRECCIÓN IMPORTANTE

header('Content-Type: application/json');

if (!isset($_SESSION['usuario_id'])) {
    echo json_encode(['status' => 'error', 'msg' => 'Sesión no iniciada.']);
    exit();
}

try {
    $id_usuario = $_SESSION['usuario_id'];
    $email_ingresado = trim($_POST['email'] ?? '');

    if (empty($email_ingresado)) {
        throw new Exception("Debe ingresar su correo electrónico.");
    }
    
    // 1. Verificar que el email coincida con el usuario logueado
    $stmt = $pdo->prepare("SELECT email, nombre_completo FROM usuarios WHERE id_usuario = ?");
    $stmt->execute([$id_usuario]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$user || empty($user['email'])) {
        throw new Exception("Su usuario no tiene un email configurado en el sistema.");
    }

    if (strcasecmp($user['email'], $email_ingresado) !== 0) {
        throw new Exception("El correo ingresado no coincide con el registrado en su cuenta.");
    }

    // 2. Generar Token
    $token = rand(100000, 999999);
    $_SESSION['token_firma'] = $token;
    $_SESSION['token_creado'] = time();

    // 3. Preparar Email
    $asunto = "Codigo de Seguridad - Firma Digital";
    $mensaje = "
    <html>
    <head><title>Token de Seguridad</title></head>
    <body>
      <h3>Autorización de Firma Digital</h3>
      <p>Hola <strong>{$user['nombre_completo']}</strong>,</p>
      <p>Para firmar el documento, ingresa este código:</p>
      <h2 style='background:#f0f0f0; padding:10px; border:1px solid #ccc; display:inline-block;'>{$token}</h2>
      <p><small>Válido por 10 minutos.</small></p>
    </body>
    </html>
    ";

    // 4. ENVIAR CON HOSTINGER
    $resultado = enviarCorreoNativo($user['email'], $asunto, $mensaje);

    // Tu función devuelve TRUE o un string con error
    if ($resultado === true || (is_string($resultado) && stripos($resultado, 'Error') === false)) {
        echo json_encode(['status' => 'success', 'msg' => 'Código enviado a ' . $user['email']]);
    } else {
        throw new Exception("Fallo SMTP: " . $resultado);
    }

} catch (Exception $e) {
    echo json_encode(['status' => 'error', 'msg' => $e->getMessage()]);
}
?>