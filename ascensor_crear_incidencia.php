<?php
// Archivo: ascensor_crear_incidencia.php (FINAL)
session_start();
require_once 'conexion.php';
require_once 'funciones_permisos.php';
require_once 'envio_correo_hostinger.php'; 

if (!isset($_SESSION['usuario_id']) || !tiene_permiso('acceso_ascensores', $pdo)) {
    header("Location: index.php");
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $id_ascensor = filter_input(INPUT_POST, 'id_ascensor', FILTER_VALIDATE_INT);
    $titulo = trim($_POST['titulo']);
    $descripcion = trim($_POST['descripcion']);
    $prioridad = $_POST['prioridad'];
    $usuario_id = $_SESSION['usuario_id'];

    if (!$id_ascensor || empty($titulo) || empty($descripcion)) {
        $_SESSION['mensaje'] = "Por favor complete todos los campos.";
        $_SESSION['tipo_mensaje'] = "warning";
        header("Location: mantenimiento_ascensores.php");
        exit;
    }

    try {
        // A. Datos del Usuario (Reply-To)
        $stmt_user = $pdo->prepare("SELECT nombre_completo, email FROM usuarios WHERE id_usuario = ?");
        $stmt_user->execute([$usuario_id]);
        $user_data = $stmt_user->fetch(PDO::FETCH_ASSOC);
        $nombre_usuario = $user_data['nombre_completo'] ?? 'Usuario Sistema';
        $email_usuario = $user_data['email'] ?? '';

        // B. Datos Ascensor
        $sql_datos = "SELECT a.nombre as nombre_ascensor, a.ubicacion, e.id_empresa, e.email_contacto, e.nombre as nombre_empresa 
                      FROM ascensores a 
                      LEFT JOIN empresas_mantenimiento e ON a.id_empresa = e.id_empresa 
                      WHERE a.id_ascensor = ?";
        $stmt_datos = $pdo->prepare($sql_datos);
        $stmt_datos->execute([$id_ascensor]);
        $info = $stmt_datos->fetch(PDO::FETCH_ASSOC);

        if (!$info) throw new Exception("Error datos ascensor.");

        // C. Insertar
        $sql_insert = "INSERT INTO ascensor_incidencias 
                       (id_ascensor, id_empresa, id_usuario_reporta, titulo, descripcion_problema, prioridad, estado, fecha_reporte) 
                       VALUES (?, ?, ?, ?, ?, ?, 'reportado', NOW())";
        $stmt_insert = $pdo->prepare($sql_insert);
        $stmt_insert->execute([$id_ascensor, $info['id_empresa'], $usuario_id, $titulo, $descripcion, $prioridad]);
        $id_incidencia = $pdo->lastInsertId();

        // D. Enviar Correo
        $aviso_correo = " (Correo no configurado en empresa)";
        if (!empty($info['email_contacto'])) {
            $asunto = "URGENTE: Falla Reportada (Orden #$id_incidencia) - " . $info['nombre_ascensor'];
            $cuerpoHTML = "
            <html>
            <body style='font-family: Arial, sans-serif;'>
                <div style='background-color: #f8f9fa; padding: 20px; border: 1px solid #ddd;'>
                    <h2 style='color: #d9534f;'>Reporte de Falla #$id_incidencia</h2>
                    <p>Estimados <strong>{$info['nombre_empresa']}</strong>,</p>
                    <p>Nueva incidencia reportada por <strong>$nombre_usuario</strong>.</p>
                    <table style='width: 100%; border-collapse: collapse; background: #fff;'>
                        <tr><td style='padding:10px;border:1px solid #eee;'><strong>Equipo:</strong></td><td style='padding:10px;border:1px solid #eee;'>{$info['nombre_ascensor']}</td></tr>
                        <tr><td style='padding:10px;border:1px solid #eee;'><strong>Falla:</strong></td><td style='padding:10px;border:1px solid #eee;'>{$titulo}</td></tr>
                        <tr><td style='padding:10px;border:1px solid #eee;'><strong>Prioridad:</strong></td><td style='padding:10px;border:1px solid #eee;color:red;'><strong>".strtoupper($prioridad)."</strong></td></tr>
                    </table>
                    <p style='margin-top:10px'>Puede responder este correo para contactar al usuario.</p>
                </div>
            </body>
            </html>";
            
            $resultado_envio = enviarCorreoNativo($info['email_contacto'], $asunto, $cuerpoHTML, $email_usuario);
            if ($resultado_envio === true) {
                $pdo->prepare("UPDATE ascensor_incidencias SET estado = 'reclamo_enviado', fecha_reclamo_enviado = NOW() WHERE id_incidencia = ?")->execute([$id_incidencia]);
                $aviso_correo = " y notificado por email.";
            } else {
                $aviso_correo = ". Falló envío email: " . $resultado_envio;
            }
        }

        // MENSAJE FINAL CON BOTÓN PDF
        $_SESSION['mensaje'] = "Incidencia #$id_incidencia creada$aviso_correo. <br><br> <a href='ascensor_orden_pdf.php?id=$id_incidencia' target='_blank' class='btn btn-warning btn-sm text-dark fw-bold'><i class='fas fa-file-pdf'></i> Descargar Orden de Trabajo</a>";
        $_SESSION['tipo_mensaje'] = "success";

    } catch (Exception $e) {
        $_SESSION['mensaje'] = "Error: " . $e->getMessage();
        $_SESSION['tipo_mensaje'] = "danger";
    }

    header("Location: mantenimiento_ascensores.php");
    exit;
}
?>