<?php
// Archivo: admin_usuarios_reset_password.php
session_start();
include 'conexion.php';
include 'funciones_permisos.php';
include 'envio_correo_hostinger.php';

header('Content-Type: application/json');
$response = ['success' => false, 'message' => 'Error no especificado.'];

// 1. Verificación de seguridad
if (!isset($_SESSION['usuario_id']) || !tiene_permiso('admin_usuarios_reset_pass', $pdo) || $_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Acceso denegado.']);
    exit();
}

$id_usuario = (int)($_POST['id_usuario'] ?? 0);

try {
    $pdo->beginTransaction();

    // 2. Obtener datos del usuario
    $stmt = $pdo->prepare("SELECT nombre_completo, email, usuario FROM usuarios WHERE id_usuario = ?");
    $stmt->execute([$id_usuario]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$user || empty($user['email'])) {
        throw new Exception("El usuario no tiene email registrado.");
    }

    // 3. Generar contraseña y hash
    $nueva_pass = substr(str_shuffle("abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789"), 0, 8);
    $hash = password_hash($nueva_pass, PASSWORD_DEFAULT);

    // 4. Actualizar BD y activar "requiere_cambio_pass"
    $stmtUpd = $pdo->prepare("UPDATE usuarios SET password = :p, requiere_cambio_pass = 1 WHERE id_usuario = :id");
    $stmtUpd->execute([':p' => $hash, ':id' => $id_usuario]);

    // 5. Diseño del Correo (HTML Profesional)
    $link_login = $base_url . "/login.php?u=" . urlencode($user['usuario']); // Link autocompleta usuario
    
    $asunto = "🔐 Recuperación de Acceso - Acción Requerida";
    
    $cuerpo = "
    <!DOCTYPE html>
    <html>
    <head>
        <style>
            .btn {
                display: inline-block; padding: 12px 24px; color: #ffffff !important; background-color: #0d6efd; 
                text-decoration: none; border-radius: 5px; font-weight: bold; margin-top: 20px;
            }
            .container {
                font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
                max-width: 600px; margin: 0 auto; background-color: #ffffff; 
                border: 1px solid #e0e0e0; border-radius: 8px; overflow: hidden;
            }
            .header { background-color: #212529; color: #ffffff; padding: 20px; text-align: center; }
            .content { padding: 30px; color: #333333; line-height: 1.6; }
            .pass-box {
                background-color: #f8f9fa; border: 2px dashed #0d6efd; color: #212529;
                font-size: 24px; font-family: monospace; letter-spacing: 3px;
                text-align: center; padding: 15px; margin: 20px 0; border-radius: 6px;
            }
            .footer { background-color: #f8f9fa; padding: 15px; text-align: center; font-size: 12px; color: #888; }
        </style>
    </head>
    <body style='background-color: #f4f4f4; padding: 20px;'>
        <div class='container'>
            <div class='header'>
                <h2 style='margin:0;'>Sistema de Gestión</h2>
            </div>
            <div class='content'>
                <h3 style='color: #0d6efd;'>Hola, " . htmlspecialchars($user['nombre_completo']) . "</h3>
                <p>Se ha recibido una solicitud para restablecer tu contraseña. Hemos generado una clave temporal segura para ti.</p>
                
                <p style='margin-bottom: 5px;'>Tu nueva contraseña temporal es:</p>
                <div class='pass-box'>$nueva_pass</div>
                
                <p><strong>Instrucciones:</strong></p>
                <ol>
                    <li>Haz clic en el botón de abajo o copia tu usuario: <strong>" . htmlspecialchars($user['usuario']) . "</strong></li>
                    <li>Usa la contraseña temporal mostrada arriba.</li>
                    <li>Al ingresar, el sistema te pedirá crear una contraseña nueva y segura.</li>
                </ol>

                <div style='text-align: center;'>
                    <a href='https://federicogonzalez.net/demo_sgalp/login.php' class='btn'>Iniciar Sesión y Cambiar Clave</a>
                </div>
                
                
            </div>
            <div class='footer'>
                Enviado automáticamente por el Sistema de Logística.<br>
                Por seguridad, este cambio expira si se solicita uno nuevo.
            </div>
        </div>
    </body>
    </html>";

    // 6. Enviar
    $envio = enviarCorreoNativo($user['email'], $asunto, $cuerpo);

    if ($envio === true) {
        $pdo->commit();
        echo json_encode(['success' => true, 'message' => "Se envió la nueva clave a: <b>{$user['email']}</b>"]);
    } else {
        throw new Exception("Fallo en el envío: " . $envio);
    }

} catch (Exception $e) {
    if ($pdo->inTransaction()) { $pdo->rollBack(); }
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
?>