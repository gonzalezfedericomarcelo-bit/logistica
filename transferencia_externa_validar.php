<?php
// Archivo: transferencia_externa_validar.php
ob_start();
session_start();
error_reporting(E_ALL);
ini_set('display_errors', 0);

include 'conexion.php';
include 'envio_correo_hostinger.php';

header('Content-Type: application/json');

$accion = $_POST['accion'] ?? '';
$token = $_POST['token'] ?? '';
$respuesta = ['status' => 'error', 'msg' => 'Acción desconocida'];

try {
    // 1. ENVIAR OTP
    if ($accion == 'enviar_otp') {
        $email = trim($_POST['email']);
        $otp = rand(100000, 999999);

        // Validar que el token existe y está pendiente
        $stmt = $pdo->prepare("SELECT id_token FROM inventario_transferencias_pendientes WHERE token_hash = ? AND estado='pendiente'");
        $stmt->execute([$token]);
        
        if ($stmt->rowCount() > 0) {
            $stmtUpd = $pdo->prepare("UPDATE inventario_transferencias_pendientes SET email_verificacion = ?, token_otp = ? WHERE token_hash = ?");
            $stmtUpd->execute([$email, $otp, $token]);

            $asunto = "Codigo de Validacion - Transferencia";
            $cuerpo = "<div style='font-family:Arial; padding:20px; background:#f4f4f4;'><div style='background:#fff; padding:30px; border-radius:10px; text-align:center;'><h2>Código: <span style='color:#0d6efd;'>$otp</span></h2><p>Ingrese este código para firmar la entrega del bien.</p></div></div>";

            $envio = enviarCorreoNativo($email, $asunto, $cuerpo);

            if ($envio === true) {
                $respuesta = ['status' => 'ok'];
            } else {
                $respuesta = ['status' => 'error', 'msg' => 'Error enviando correo: ' . $envio];
            }
        } else {
            $respuesta = ['status' => 'error', 'msg' => 'El enlace ha expirado o no existe.'];
        }
    }

    // 2. SOLO VERIFICAR OTP (NO CONFIRMAR AÚN)
    if ($accion == 'verificar_otp_only') {
        $otp = $_POST['otp'];
        $stmt = $pdo->prepare("SELECT id_token FROM inventario_transferencias_pendientes WHERE token_hash = ? AND token_otp = ? AND estado='pendiente'");
        $stmt->execute([$token, $otp]);
        
        if ($stmt->fetch()) {
            $respuesta = ['status' => 'ok'];
        } else {
            $respuesta = ['status' => 'error', 'msg' => 'Código incorrecto.'];
        }
    }

} catch (Exception $e) {
    $respuesta = ['status' => 'error', 'msg' => 'Error: ' . $e->getMessage()];
}

ob_end_clean(); 
echo json_encode($respuesta);
?>