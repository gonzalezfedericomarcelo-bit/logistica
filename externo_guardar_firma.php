<?php
// Archivo: externo_guardar_firma.php
// OBJETIVO: Procesar firma, Generar Acta PRO y Enviar Email Profesional.
session_start();
include 'conexion.php';
include 'envio_correo_hostinger.php'; 
require('fpdf/fpdf.php');
date_default_timezone_set('America/Argentina/Buenos_Aires');

header('Content-Type: application/json');

// Helper QR
function descargar_qr_temp($contenido) {
    $dir = 'uploads/temp_qr/';
    if (!is_dir($dir)) @mkdir($dir, 0777, true);
    $nombre = md5($contenido) . '.png';
    $ruta_local = $dir . $nombre;
    if (file_exists($ruta_local)) return $ruta_local;
    $url_api = "https://api.qrserver.com/v1/create-qr-code/?size=150x150&data=" . urlencode($contenido);
    $img = @file_get_contents($url_api);
    if ($img) { file_put_contents($ruta_local, $img); return $ruta_local; }
    return false;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') { echo json_encode(['status'=>'error']); exit; }

$accion = $_POST['accion'] ?? 'confirmar'; 
$token = $_POST['token'] ?? '';

if (empty($token)) { echo json_encode(['status' => 'error', 'msg' => 'Token faltante.']); exit; }

try {
    // --- ACCIÓN 1: ENVIAR OTP ---
    if ($accion === 'enviar_otp') {
        $email = trim($_POST['email'] ?? '');
        if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) throw new Exception("Email inválido.");

        $stmt = $pdo->prepare("SELECT id_token, nuevo_responsable_nombre FROM inventario_transferencias_pendientes WHERE token_hash = ? AND estado = 'pendiente'");
        $stmt->execute([$token]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$row) throw new Exception("Solicitud inválida.");

        $otp = rand(100000, 999999);
        $pdo->prepare("UPDATE inventario_transferencias_pendientes SET token_otp = ? WHERE id_token = ?")->execute([$otp, $row['id_token']]);

        // Plantilla OTP
        $asunto = "Código de Seguridad - Firma Digital";
        $cuerpo = "
        <div style='font-family: Arial, sans-serif; max-width: 600px; margin: 0 auto; border: 1px solid #e0e0e0; border-radius: 8px; overflow: hidden;'>
            <div style='background-color: #0d6efd; padding: 20px; text-align: center; color: white;'>
                <h2 style='margin: 0;'>LOGÍSTICA IOSFA</h2>
            </div>
            <div style='padding: 30px; background-color: #ffffff;'>
                <h3 style='color: #333; margin-top: 0;'>Verificación de Identidad</h3>
                <p style='color: #666; font-size: 16px;'>Hola <strong>{$row['nuevo_responsable_nombre']}</strong>,</p>
                <p style='color: #666;'>Usted está iniciando el proceso de firma digital para una transferencia de bienes.</p>
                <p style='color: #666;'>Utilice el siguiente código para validar su identidad:</p>
                
                <div style='background-color: #f8f9fa; border: 2px dashed #0d6efd; color: #0d6efd; font-size: 32px; font-weight: bold; text-align: center; padding: 15px; margin: 25px 0; letter-spacing: 5px;'>
                    $otp
                </div>
                
                <p style='color: #999; font-size: 13px;'>Si no ha solicitado este código, por favor ignore este correo.</p>
            </div>
            <div style='background-color: #f1f1f1; padding: 15px; text-align: center; font-size: 12px; color: #888;'>
                Departamento de Logística - IOSFA<br>
                Este es un mensaje automático, no responder.
            </div>
        </div>";
        
        if (enviarCorreoNativo($email, $asunto, $cuerpo) === true) {
            echo json_encode(['status' => 'success']);
        } else {
            echo json_encode(['status' => 'success', 'msg' => 'Enviado (verifique spam)']); 
        }
        exit;
    }

    // --- ACCIÓN 2: CONFIRMAR ---
    if ($accion === 'confirmar') {
        $otp = $_POST['otp'] ?? '';
        $firma_data = $_POST['firma_base64'] ?? '';
        $nombre_firmante = $_POST['nombre_firmante'] ?? '';
        $email_final = $_POST['email_final'] ?? ''; 

        if (empty($firma_data)) throw new Exception("Falta la firma.");
        
        $pdo->beginTransaction();

        $stmt = $pdo->prepare("SELECT t.*, u.nombre_completo as n_rel, u.firma_imagen_path as f_rel 
                               FROM inventario_transferencias_pendientes t
                               LEFT JOIN usuarios u ON t.creado_por = u.id_usuario
                               WHERE t.token_hash = ? AND t.token_otp = ? AND t.estado = 'pendiente'");
        $stmt->execute([$token, $otp]);
        $solicitud = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$solicitud) throw new Exception("Código incorrecto o solicitud vencida.");

        // Guardar Firma
        $ruta_carpeta = 'uploads/firmas/';
        if (!file_exists($ruta_carpeta)) mkdir($ruta_carpeta, 0777, true);
        $nombre_archivo_firma = 'firma_ext_' . $solicitud['id_bien'] . '_' . uniqid() . '.png';
        $ruta_firma_entregue = $ruta_carpeta . $nombre_archivo_firma;
        file_put_contents($ruta_firma_entregue, base64_decode(preg_replace('#^data:image/\w+;base64,#i', '', $firma_data)));

        // Datos
        $bien_ant = $pdo->query("SELECT * FROM inventario_cargos WHERE id_cargo = {$solicitud['id_bien']}")->fetch(PDO::FETCH_ASSOC);
        $origen_txt = $bien_ant['destino_principal'] . ' - ' . $bien_ant['servicio_ubicacion'];
        $patrimonialData = $pdo->query("SELECT nombre_completo, firma_imagen_path FROM usuarios WHERE rol = 'cargopatrimonial' AND activo = 1 LIMIT 1")->fetch(PDO::FETCH_ASSOC);
        
        // Actualizar
        $nuevo_dest_val = $solicitud['nuevo_destino_id'] > 0 ? $solicitud['nuevo_destino_id'] : $solicitud['nuevo_destino_nombre'];
        $sqlUpd = "UPDATE inventario_cargos SET destino_principal=?, servicio_ubicacion=?, nombre_responsable=?, nombre_jefe_servicio=?, firma_responsable_path=?, firma_jefe_path=? WHERE id_cargo=?";
        $pdo->prepare($sqlUpd)->execute([
            $nuevo_dest_val, $solicitud['nueva_area_nombre'], 
            $solicitud['nuevo_responsable_nombre'], $solicitud['nuevo_jefe_nombre'],
            $solicitud['firma_nuevo_responsable_path'], $solicitud['firma_nuevo_jefe_path'], 
            $solicitud['id_bien']
        ]);

        // Historial
        $obs = "TRANSFERENCIA EXTERNA.\nRecibió: " . $solicitud['nuevo_responsable_nombre'];
        $stmtHist = $pdo->prepare("INSERT INTO historial_movimientos (id_bien, usuario_registro, tipo_movimiento, ubicacion_anterior, ubicacion_nueva, observacion_movimiento, fecha_movimiento) VALUES (?, ?, 'TRANSFERENCIA', ?, ?, ?, NOW())");
        $stmtHist->execute([$solicitud['id_bien'], $solicitud['creado_por'], $origen_txt, $solicitud['nuevo_destino_nombre'], $obs]);
        $id_movimiento_nuevo = $pdo->lastInsertId();

        // Cerrar
        $pdo->prepare("UPDATE inventario_transferencias_pendientes SET estado='confirmado' WHERE id_token=?")->execute([$solicitud['id_token']]);

        // Notificar
        $link_notif = "inventario_movimientos.php?highlight_id=" . $id_movimiento_nuevo; 
        $stmtRoles = $pdo->prepare("SELECT id_usuario FROM usuarios WHERE rol = 'cargopatrimonial' OR rol = 'admin'");
        $stmtRoles->execute();
        foreach ($stmtRoles->fetchAll(PDO::FETCH_COLUMN) as $uid) {
            $pdo->prepare("INSERT INTO notificaciones (id_usuario_destino, tipo, mensaje, url, fecha_creacion) VALUES (?, 'aviso', ?, ?, NOW())")
                ->execute([$uid, "Transferencia Externa Completada (ID {$solicitud['id_bien']})", $link_notif]);
        }

        // GENERAR PDF (Mismo código de antes)
        $protocolo = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on') ? "https" : "http";
        $nombre_pdf = 'Acta_Transferencia_' . $solicitud['id_bien'] . '_' . uniqid() . '.pdf';
        $ruta_relativa_pdf = 'pdfs_publicos/inventario_pdf/' . $nombre_pdf;
        $url_pdf_final = "$protocolo://{$_SERVER['HTTP_HOST']}" . dirname($_SERVER['PHP_SELF']) . "/$ruta_relativa_pdf";

        class PDF_Acta_Pro extends FPDF {
            public $qr_link; public $id_solicitud;
            function Header() {
                $logoIzq = __DIR__ . '/assets/img/logo.png'; $logoDer = __DIR__ . '/assets/img/sello_trabajo.png';
                if (file_exists($logoIzq)) $this->Image($logoIzq, 10, 8, 18); 
                if (file_exists($logoDer)) $this->Image($logoDer, 182, 8, 18);
                $this->SetY(10); $this->SetFont('Arial','B',12);
                $this->Cell(0, 5, utf8_decode('IOSFA - INSTITUTO DE OBRA SOCIAL DE LAS FUERZAS ARMADAS'), 0, 1, 'C');
                $this->SetFont('Arial','',8); $this->Cell(0, 4, utf8_decode('"2025 - AÑO DE LA LIBERTAD"'), 0, 1, 'C');
                $this->Ln(10); $this->SetFont('Arial','B',14);
                $this->Cell(0, 8, utf8_decode('ACTA DE TRANSFERENCIA DE BIENES'), 0, 1, 'C');
                $this->Line(40, $this->GetY(), 170, $this->GetY());
                $watermark = __DIR__ . '/assets/img/logo_watermark_gris.png';
                if(file_exists($watermark)) $this->Image($watermark, 50, 70, 110);
                $this->Ln(5);
            }
            function Footer() {
                $this->SetY(-30); $this->SetLineWidth(0.4); $this->SetDrawColor(100,100,100);
                $this->Line(10, $this->GetY(), 200, $this->GetY());
                if ($this->qr_link) {
                    $ruta_qr = descargar_qr_temp($this->qr_link);
                    if ($ruta_qr) $this->Image($ruta_qr, 12, $this->GetY()+2, 22, 22);
                }
                $this->SetX(38); $this->SetFont('Arial','B',7);
                $this->Cell(0, 4, utf8_decode('DOCUMENTO OFICIAL - SISTEMA DE LOGÍSTICA'), 0, 1, 'L');
                $this->SetX(38); $this->SetFont('Arial','',6); $this->SetTextColor(80,80,80);
                $texto_footer = "ID OPERACIÓN: " . str_pad($this->id_solicitud, 8, '0', STR_PAD_LEFT) . " | FECHA: " . date('d/m/Y H:i') . "\n" . "VALIDACIÓN: Verificación pública mediante código QR.\n" . "DIRECCIÓN: Gerencia de Tecnologías de la Información y las Comunicaciones";
                $this->MultiCell(120, 3, utf8_decode($texto_footer), 0, 'L');
                $this->SetY(-12); $this->SetFont('Arial','I',7); $this->Cell(0, 10, utf8_decode('Página '.$this->PageNo()), 0, 0, 'R');
            }
            function ImageFit($file, $x, $y, $w, $h) {
                if (!file_exists($file)) return;
                list($width, $height) = getimagesize($file);
                if ($width == 0 || $height == 0) return;
                $ratioImg = $width / $height; $ratioBox = $w / $h;
                if ($ratioImg > $ratioBox) { $newW = $w; $newH = $w / $ratioImg; $newY = $y + ($h - $newH) / 2; $this->Image($file, $x, $newY, $newW, $newH); } 
                else { $newH = $h; $newW = $h * $ratioImg; $newX = $x + ($w - $newW) / 2; $this->Image($file, $newX, $y, $newW, $newH); }
            }
        }

        $pdf = new PDF_Acta_Pro();
        $pdf->qr_link = $url_pdf_final; $pdf->id_solicitud = $solicitud['id_token'];
        $pdf->AddPage();
        
        $dias = date('d'); $meses = ['Enero','Febrero','Marzo','Abril','Mayo','Junio','Julio','Agosto','Septiembre','Octubre','Noviembre','Diciembre'];
        $mes = $meses[date('n')-1]; $anio = date('Y');
        
        $pdf->SetFont('Arial','',9);
        $intro = "En la Ciudad Autónoma de Buenos Aires, a los $dias días del mes de $mes del año $anio, se labra la presente acta de entrega y recepción definitiva del bien patrimonial detallado a continuación.";
        $pdf->MultiCell(0, 5, utf8_decode($intro), 0, 'L');
        $pdf->Ln(5);

        $pdf->SetFillColor(240,240,240); $pdf->SetDrawColor(200,200,200);
        $pdf->SetFont('Arial','B',9); $pdf->Cell(0,6, utf8_decode('  1. DATOS DEL BIEN TRANSFERIDO'),1,1,'L',true);
        $pdf->SetFont('Arial','',9);
        $pdf->Cell(30,6, 'Elemento:',1); $pdf->Cell(0,6, utf8_decode($bien_ant['elemento']),1,1);
        $pdf->Cell(30,6, utf8_decode('Código:'),1); $pdf->Cell(60,6, utf8_decode($bien_ant['codigo_inventario']),1);
        $pdf->Cell(30,6, 'Serie:',1); $pdf->Cell(0,6, utf8_decode($bien_ant['mat_numero_grabado']),1,1);
        $pdf->Ln(3);

        $pdf->SetFont('Arial','B',9); $pdf->Cell(0,6, utf8_decode('  2. DETALLE DEL MOVIMIENTO'),1,1,'L',true);
        $pdf->SetFont('Arial','',9);
        $pdf->Cell(40,6, utf8_decode('UBICACIÓN ANTERIOR:'),1); $pdf->Cell(0,6, utf8_decode($origen_txt),1,1);
        $pdf->Cell(40,6, utf8_decode('NUEVA UBICACIÓN:'),1); $pdf->Cell(0,6, utf8_decode($solicitud['nuevo_destino_nombre'] . ' - ' . $solicitud['nueva_area_nombre']),1,1);
        $pdf->Ln(5);

        $pdf->SetFont('Arial','I',9); $pdf->Cell(0, 6, utf8_decode("C.A.B.A, $dias de $mes de $anio"), 0, 1, 'R');
        $pdf->Ln(3);

        $pdf->SetFont('Arial','B',9); $pdf->Cell(0,6, utf8_decode('  3. CONFORMIDAD Y RESPONSABLES'),1,1,'L',true);
        $pdf->Ln(4);
        
        $y = $pdf->GetY(); $margen = 12; $ancho = 88; $alto = 28; $sep = 10;

        $pdf->Rect($margen, $y, $ancho, $alto);
        if(file_exists($ruta_firma_entregue)) $pdf->ImageFit($ruta_firma_entregue, $margen, $y, $ancho, $alto);
        $pdf->Rect($margen + $ancho + $sep, $y, $ancho, $alto);
        $pathJefe = __DIR__ . '/' . $solicitud['firma_nuevo_jefe_path'];
        if(file_exists($pathJefe)) $pdf->ImageFit($pathJefe, $margen + $ancho + $sep, $y, $ancho, $alto);

        $pdf->SetXY($margen, $y + $alto);
        $pdf->SetFont('Arial','B',7); $pdf->Cell($ancho, 5, utf8_decode('RECIBIÓ CONFORME (Responsable)'), 'T', 0, 'C', true);
        $pdf->SetXY($margen + $ancho + $sep, $y + $alto);
        $pdf->Cell($ancho, 5, utf8_decode('JEFE DE SERVICIO (Aval)'), 'T', 0, 'C', true);
        
        $pdf->Ln(5);
        $pdf->SetFont('Arial','',7);
        $pdf->SetX($margen); $pdf->Cell($ancho, 4, utf8_decode($nombre_firmante), 0, 0, 'C');
        $pdf->SetX($margen + $ancho + $sep); $pdf->Cell($ancho, 4, utf8_decode($solicitud['nuevo_jefe_nombre']), 0, 1, 'C');

        $y2 = $pdf->GetY() + 6;
        $pdf->Rect($margen, $y2, $ancho, $alto);
        $pathRel = $solicitud['f_rel'] ? __DIR__ . '/uploads/firmas/' . $solicitud['f_rel'] : null;
        if($pathRel && file_exists($pathRel)) $pdf->ImageFit($pathRel, $margen, $y2, $ancho, $alto);
        $pdf->Rect($margen + $ancho + $sep, $y2, $ancho, $alto);
        $pathPat = ($patrimonialData && $patrimonialData['firma_imagen_path']) ? __DIR__ . '/uploads/firmas/' . $patrimonialData['firma_imagen_path'] : null;
        if($pathPat && file_exists($pathPat)) $pdf->ImageFit($pathPat, $margen + $ancho + $sep, $y2, $ancho, $alto);

        $pdf->SetXY($margen, $y2 + $alto);
        $pdf->SetFont('Arial','B',7); $pdf->Cell($ancho, 5, utf8_decode('RELEVADOR (SISTEMA)'), 'T', 0, 'C', true);
        $pdf->SetXY($margen + $ancho + $sep, $y2 + $alto);
        $pdf->Cell($ancho, 5, utf8_decode('ENCARGADO CARGO PATRIMONIAL'), 'T', 0, 'C', true);

        $pdf->Ln(5);
        $pdf->SetFont('Arial','',7);
        $pdf->SetX($margen); $pdf->Cell($ancho, 4, utf8_decode($solicitud['n_rel']), 0, 0, 'C');
        $pdf->SetX($margen + $ancho + $sep); $pdf->Cell($ancho, 4, utf8_decode($patrimonialData['nombre_completo'] ?? ''), 0, 1, 'C');

        // Guardar
        $ruta_abs_pdf = __DIR__ . '/' . $ruta_relativa_pdf;
        if (!file_exists(dirname($ruta_abs_pdf))) mkdir(dirname($ruta_abs_pdf), 0777, true);
        $pdf->Output('F', $ruta_abs_pdf);

        // 9. COPIA MAIL PROFESIONAL
        if (!empty($email_final)) {
            $asunto_mail = "Acta de Transferencia Completada - IOSFA";
            $cuerpo_mail = "
            <div style='font-family: Arial, sans-serif; max-width: 600px; margin: 0 auto; border: 1px solid #e0e0e0; border-radius: 8px; overflow: hidden;'>
                <div style='background-color: #198754; padding: 20px; text-align: center; color: white;'>
                    <h2 style='margin: 0;'>TRANSACCIÓN EXITOSA</h2>
                </div>
                <div style='padding: 30px; background-color: #ffffff;'>
                    <h3 style='color: #333; margin-top: 0;'>¡Felicitaciones!</h3>
                    <p style='color: #666; font-size: 16px;'>Hola <strong>$nombre_firmante</strong>,</p>
                    <p style='color: #666;'>La transferencia del bien <strong>{$bien_ant['elemento']}</strong> ha sido registrada y validada correctamente en nuestro sistema.</p>
                    <p style='color: #666;'>Adjuntamos el enlace para descargar su copia oficial del acta firmada:</p>
                    
                    <div style='text-align: center; margin: 30px 0;'>
                        <a href='$url_pdf_final' style='background-color: #0d6efd; color: white; padding: 15px 25px; text-decoration: none; border-radius: 5px; font-weight: bold; font-size: 16px;'>DESCARGAR ACTA OFICIAL (PDF)</a>
                    </div>
                    
                    <p style='color: #999; font-size: 13px; text-align: center;'>Este enlace es válido indefinidamente.</p>
                </div>
                <div style='background-color: #f1f1f1; padding: 15px; text-align: center; font-size: 12px; color: #888;'>
                    Departamento de Logística - IOSFA<br>
                    Buenos Aires, Argentina
                </div>
            </div>";
            enviarCorreoNativo($email_final, $asunto_mail, $cuerpo_mail);
        }

        $pdo->commit();
        echo json_encode(['status' => 'ok', 'pdf_url' => $url_pdf_final]);

    }

} catch (Exception $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    echo json_encode(['status' => 'error', 'msg' => $e->getMessage()]);
}
?>