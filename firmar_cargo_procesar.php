<?php
// Archivo: firmar_cargo_procesar.php
session_start();
include 'conexion.php';
include 'envio_correo_hostinger.php'; // USAMOS TU SISTEMA DE CORREO
date_default_timezone_set('America/Argentina/Buenos_Aires');
header('Content-Type: application/json');

$accion = $_POST['accion'] ?? '';
$token = $_POST['token'] ?? '';

try {
    // 1. Verificar Token
    $stmt = $pdo->prepare("SELECT * FROM inventario_firmas_remotas WHERE token = ? AND estado IN ('pendiente', 'verificado')");
    $stmt->execute([$token]);
    $solicitud = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$solicitud) { throw new Exception("El enlace ha expirado o no es válido."); }
    
    // --- ACCIÓN: ENVIAR OTP ---
    if ($accion == 'enviar_otp') {
        $email = trim($_POST['email']);
        $nombre = trim($_POST['nombre']);
        
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) throw new Exception("Email inválido");

        $otp = rand(100000, 999999);
        $expira = date('Y-m-d H:i:s', strtotime('+48 hours'));

        $pdo->prepare("UPDATE inventario_firmas_remotas SET email_destinatario=?, nombre_firmante=?, codigo_otp=?, otp_expiracion=? WHERE id_solicitud=?")
            ->execute([$email, $nombre, $otp, $expira, $solicitud['id_solicitud']]);

        // ENVIAR CORREO
        $asunto = "Codigo de Validacion - Firma de Cargo";
        $cuerpo = "
            <div style='font-family:Arial; padding:20px; background:#f8f9fa;'>
                <div style='background:#fff; padding:30px; border-radius:10px; border:1px solid #ddd; text-align:center;'>
                    <h2 style='color:#333;'>Código de Seguridad</h2>
                    <p>Hola <strong>$nombre</strong>, usa el siguiente código para firmar el cargo asignado:</p>
                    <h1 style='color:#0d6efd; letter-spacing: 5px; font-size: 40px; margin: 20px 0;'>$otp</h1>
                    <p style='color:#777; font-size:12px;'>Este código es válido por 48 horas.</p>
                </div>
            </div>";
        
        $envio = enviarCorreoNativo($email, $asunto, $cuerpo);

        if ($envio === true) {
            echo json_encode(['status'=>'success', 'msg'=>'Código enviado a ' . $email]);
        } else {
            throw new Exception("Error enviando correo: " . $envio);
        }
    }

    // --- ACCIÓN: VERIFICAR OTP ---
    if ($accion == 'verificar_otp') {
        $otp_ingresado = $_POST['otp'];
        if ($solicitud['codigo_otp'] == $otp_ingresado) {
            if (new DateTime() > new DateTime($solicitud['otp_expiracion'])) throw new Exception("El código ha expirado.");
            
            $pdo->prepare("UPDATE inventario_firmas_remotas SET estado='verificado' WHERE id_solicitud=?")->execute([$solicitud['id_solicitud']]);
            echo json_encode(['status'=>'success']);
        } else {
            throw new Exception("Código incorrecto.");
        }
    }

    // --- ACCIÓN: GUARDAR FIRMA ---
    if ($accion == 'guardar_firma') {
        if ($solicitud['estado'] != 'verificado') throw new Exception("Debe verificar el código primero.");
        
        $firma_base64 = $_POST['firma'];
        $nombre_final = $solicitud['nombre_firmante'];

        // Guardar Imagen
        $ruta_dir = 'uploads/firmas/';
        if (!file_exists($ruta_dir)) mkdir($ruta_dir, 0777, true);
        
        $img = base64_decode(preg_replace('#^data:image/\w+;base64,#i', '', $firma_base64));
        $filename = 'remoto_' . $solicitud['rol'] . '_' . time() . '.png';
        $path_final = $ruta_dir . $filename;
        file_put_contents($path_final, $img);

        // Actualizar Cargo
        $columna = ($solicitud['rol'] == 'jefe') ? 'firma_jefe_path' : 'firma_responsable_path';
        $col_nombre = ($solicitud['rol'] == 'jefe') ? 'nombre_jefe_servicio' : 'nombre_responsable';
        
        $pdo->prepare("UPDATE inventario_cargos SET $columna = ?, $col_nombre = ? WHERE id_cargo = ?")
            ->execute([$path_final, $nombre_final, $solicitud['id_cargo']]);
        
        // Finalizar solicitud
        $pdo->prepare("UPDATE inventario_firmas_remotas SET estado='firmado' WHERE id_solicitud=?")->execute([$solicitud['id_solicitud']]);

        // NOTIFICACIONES (Admin y Relevador)
        // Obtener datos del cargo para notificar
        $stmtC = $pdo->prepare("SELECT elemento, id_usuario_relevador FROM inventario_cargos WHERE id_cargo=?");
        $stmtC->execute([$solicitud['id_cargo']]);
        $cargoData = $stmtC->fetch();

        $msj = "Firma REMOTA recibida de $nombre_final (" . ucfirst($solicitud['rol']) . ") para: " . $cargoData['elemento'];
        $link = "inventario_editar.php?id=" . $solicitud['id_cargo'];

        // Al Relevador
        if ($cargoData['id_usuario_relevador']) {
            $pdo->prepare("INSERT INTO notificaciones (id_usuario_destino, tipo, mensaje, url, fecha_creacion) VALUES (?, 'aviso_firma', ?, ?, NOW())")->execute([$cargoData['id_usuario_relevador'], $msj, $link]);
        }
        // A Admins
        $admins = $pdo->query("SELECT id_usuario FROM usuarios WHERE rol='admin'")->fetchAll();
        foreach($admins as $adm) {
            if ($adm['id_usuario'] != $cargoData['id_usuario_relevador']) {
                $pdo->prepare("INSERT INTO notificaciones (id_usuario_destino, tipo, mensaje, url, fecha_creacion) VALUES (?, 'aviso_firma', ?, ?, NOW())")->execute([$adm['id_usuario'], $msj, $link]);
            }
        }

        echo json_encode(['status'=>'success']);
    }

} catch (Exception $e) {
    echo json_encode(['status'=>'error', 'msg'=>$e->getMessage()]);
}
?>