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
    // --- 1. ENVIAR CÓDIGO ---
    if ($accion == 'enviar_otp') {
        $email = trim($_POST['email']);
        $otp = rand(100000, 999999);

        $stmt = $pdo->prepare("SELECT id_token FROM inventario_transferencias_pendientes WHERE token_hash = ? AND estado='pendiente'");
        $stmt->execute([$token]);
        
        if ($stmt->rowCount() > 0) {
            $stmtUpd = $pdo->prepare("UPDATE inventario_transferencias_pendientes SET email_verificacion = ?, token_otp = ? WHERE token_hash = ?");
            $stmtUpd->execute([$email, $otp, $token]);

            $asunto = "Codigo de Validacion - Transferencia";
            $cuerpo = "<div style='padding:20px; background:#f4f4f4;'><div style='background:#fff; padding:20px;'><h2>Código: $otp</h2><p>Ingrese este código para validar la transferencia.</p></div></div>";

            $envio = enviarCorreoNativo($email, $asunto, $cuerpo);

            if ($envio === true) {
                $respuesta = ['status' => 'ok'];
            } else {
                $respuesta = ['status' => 'error', 'msg' => 'Fallo envío: ' . $envio];
            }
        } else {
            $respuesta = ['status' => 'error', 'msg' => 'Enlace expirado.'];
        }
    }

    // --- 2. CONFIRMAR Y GUARDAR ---
    if ($accion == 'confirmar') {
        $otp = $_POST['otp'];
        
        $stmt = $pdo->prepare("SELECT * FROM inventario_transferencias_pendientes WHERE token_hash = ? AND token_otp = ? AND estado='pendiente'");
        $stmt->execute([$token, $otp]);
        $trans = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($trans) {
            // Datos del bien antes de moverlo
            $bien = $pdo->query("SELECT * FROM inventario_cargos WHERE id_cargo = " . $trans['id_bien'])->fetch(PDO::FETCH_ASSOC);
            
            // --- CONSTRUIR UBICACIONES COMPLETAS (Destino + Área) ---
            $origen_completo = trim(($bien['destino_principal'] ?? '') . ' - ' . ($bien['servicio_ubicacion'] ?? ''));
            $destino_completo = trim(($trans['nuevo_destino_nombre'] ?? '') . ' - ' . ($trans['nueva_area_nombre'] ?? ''));
            
            // Texto para observaciones
            $obs_detalle = "TRANSFERENCIA EXTERNA COMPLETADA.\n";
            $obs_detalle .= "Desde: $origen_completo (" . $bien['nombre_responsable'] . ")\n";
            $obs_detalle .= "Hacia: $destino_completo (" . $trans['nuevo_responsable_nombre'] . ")\n";
            $obs_detalle .= "Motivo: " . $trans['motivo_transferencia'];

            // INSERTAR HISTORIAL (Con las ubicaciones completas)
            $sqlH = "INSERT INTO historial_movimientos 
                    (id_bien, tipo_movimiento, ubicacion_anterior, ubicacion_nueva, usuario_registro, observacion_movimiento, fecha_movimiento) 
                    VALUES (?, 'Transferencia', ?, ?, ?, ?, NOW())";
            
            $pdo->prepare($sqlH)->execute([
                $trans['id_bien'],
                $origen_completo, 
                $destino_completo,
                $trans['creado_por'],
                $obs_detalle
            ]);

            // MOVER EL BIEN
            $sqlUpd = "UPDATE inventario_cargos SET 
                       destino_principal = ?, 
                       servicio_ubicacion = ?, 
                       nombre_responsable = ?, 
                       nombre_jefe_servicio = ? 
                       WHERE id_cargo = ?";
            
            $pdo->prepare($sqlUpd)->execute([
                $trans['nuevo_destino_nombre'], 
                $trans['nueva_area_nombre'],
                $trans['nuevo_responsable_nombre'],
                $trans['nuevo_jefe_nombre'],
                $trans['id_bien']
            ]);

            // CERRAR TOKEN
            $pdo->prepare("UPDATE inventario_transferencias_pendientes SET estado='confirmado' WHERE id_token = ?")->execute([$trans['id_token']]);

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