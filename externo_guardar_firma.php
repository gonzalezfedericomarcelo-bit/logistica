<?php
// Archivo: externo_guardar_firma.php
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

        $asunto = "Codigo de Seguridad - Firma Digital";
        $cuerpo = "<html><body><h3>Validación de Identidad</h3><p>Su código es:</p><h2 style='background:#eee;padding:10px;'>$otp</h2></body></html>";
        
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

        // 1. Validar
        $stmt = $pdo->prepare("SELECT t.*, u.nombre_completo as n_rel, u.firma_imagen_path as f_rel 
                               FROM inventario_transferencias_pendientes t
                               LEFT JOIN usuarios u ON t.creado_por = u.id_usuario
                               WHERE t.token_hash = ? AND t.token_otp = ? AND t.estado = 'pendiente'");
        $stmt->execute([$token, $otp]);
        $solicitud = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$solicitud) throw new Exception("Código incorrecto o solicitud vencida.");

        // 2. Guardar Firma
        $ruta_carpeta = 'uploads/firmas/';
        if (!file_exists($ruta_carpeta)) mkdir($ruta_carpeta, 0777, true);
        $nombre_archivo_firma = 'firma_ext_' . $solicitud['id_bien'] . '_' . uniqid() . '.png';
        $ruta_firma_entregue = $ruta_carpeta . $nombre_archivo_firma;
        file_put_contents($ruta_firma_entregue, base64_decode(preg_replace('#^data:image/\w+;base64,#i', '', $firma_data)));

        // 3. Obtener Datos Previos y Corrección ID->Nombre
        $bien_ant = $pdo->query("SELECT * FROM inventario_cargos WHERE id_cargo = {$solicitud['id_bien']}")->fetch(PDO::FETCH_ASSOC);
        
        $nombre_destino_ant = $bien_ant['destino_principal'];
        if (is_numeric($nombre_destino_ant)) {
            $stmtDA = $pdo->prepare("SELECT nombre FROM destinos_internos WHERE id_destino = ?");
            $stmtDA->execute([$nombre_destino_ant]);
            $resDA = $stmtDA->fetch(PDO::FETCH_ASSOC);
            if ($resDA) $nombre_destino_ant = $resDA['nombre'];
        }
        $area_ant = $bien_ant['servicio_ubicacion'];
        if (stripos($area_ant, 'General') !== false || stripos($area_ant, 'Sin áreas') !== false) $area_ant = '';
        $origen_txt = $nombre_destino_ant . ($area_ant ? ' - ' . $area_ant : '');

        $patrimonialData = $pdo->query("SELECT nombre_completo, firma_imagen_path FROM usuarios WHERE rol = 'cargopatrimonial' AND activo = 1 LIMIT 1")->fetch(PDO::FETCH_ASSOC);
        
        // 4. Actualizar Cargo
        $nuevo_dest_val = $solicitud['nuevo_destino_id'] > 0 ? $solicitud['nuevo_destino_id'] : $solicitud['nuevo_destino_nombre'];
        $sqlUpd = "UPDATE inventario_cargos SET destino_principal=?, servicio_ubicacion=?, nombre_responsable=?, nombre_jefe_servicio=?, firma_responsable_path=?, firma_jefe_path=? WHERE id_cargo=?";
        $pdo->prepare($sqlUpd)->execute([
            $nuevo_dest_val, $solicitud['nueva_area_nombre'], 
            $solicitud['nuevo_responsable_nombre'], $solicitud['nuevo_jefe_nombre'],
            $solicitud['firma_nuevo_responsable_path'], $solicitud['firma_nuevo_jefe_path'], 
            $solicitud['id_bien']
        ]);

        // 5. Historial (INSERTAR MOVIMIENTO)
        $destino_nuevo_txt = $solicitud['nuevo_destino_nombre'];
        if (!empty($solicitud['nueva_area_nombre'])) $destino_nuevo_txt .= ' - ' . $solicitud['nueva_area_nombre'];

        $obs = "TRANSFERENCIA EXTERNA.\nRecibió: " . $solicitud['nuevo_responsable_nombre'];
        $stmtHist = $pdo->prepare("INSERT INTO historial_movimientos (id_bien, usuario_registro, tipo_movimiento, ubicacion_anterior, ubicacion_nueva, observacion_movimiento, fecha_movimiento) VALUES (?, ?, 'TRANSFERENCIA', ?, ?, ?, NOW())");
        $stmtHist->execute([$solicitud['id_bien'], $solicitud['creado_por'], $origen_txt, $destino_nuevo_txt, $obs]);
        $id_movimiento_nuevo = $pdo->lastInsertId(); // ID DEL MOVIMIENTO CREADO

        // 6. Cerrar Solicitud
        $pdo->prepare("UPDATE inventario_transferencias_pendientes SET estado='confirmado' WHERE id_token=?")->execute([$solicitud['id_token']]);

        // 7. Notificar
        $link_notif = "inventario_movimientos.php?highlight_id=" . $id_movimiento_nuevo; 
        $stmtRoles = $pdo->prepare("SELECT id_usuario FROM usuarios WHERE rol = 'cargopatrimonial' OR rol = 'admin'");
        $stmtRoles->execute();
        foreach ($stmtRoles->fetchAll(PDO::FETCH_COLUMN) as $uid) {
            $pdo->prepare("INSERT INTO notificaciones (id_usuario_destino, tipo, mensaje, url, fecha_creacion) VALUES (?, 'aviso', ?, ?, NOW())")
                ->execute([$uid, "Transferencia Externa Completada (ID {$solicitud['id_bien']})", $link_notif]);
        }

        // ====================================================================
        // 8. GENERAR PDF 1: ACTA DE TRANSFERENCIA (DISEÑO TUYO)
        // ====================================================================
        $protocolo = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on') ? "https" : "http";
        $nombre_acta = 'Acta_Transferencia_' . $solicitud['id_bien'] . '_' . uniqid() . '.pdf';
        $ruta_rel_acta = 'pdfs_publicos/inventario_pdf/' . $nombre_acta;
        $url_acta = "$protocolo://{$_SERVER['HTTP_HOST']}" . dirname($_SERVER['PHP_SELF']) . "/$ruta_rel_acta";

        // CLASE PDF ACTA
        class PDF_Acta_Pro extends FPDF {
            public $qr_link; public $id_solicitud;
            function Header() {
                $logoIzq = __DIR__ . '/assets/img/logo.png'; $logoDer = __DIR__ . '/assets/img/sello_trabajo.png';
                if (file_exists($logoIzq)) $this->Image($logoIzq, 10, 8, 18); 
                if (file_exists($logoDer)) $this->Image($logoDer, 182, 8, 18);
                $this->SetY(10); $this->SetFont('Arial','B',12);
                $this->Cell(0, 5, utf8_decode('IOSFA - INSTITUTO DE OBRA SOCIAL DE LAS FUERZAS ARMADAS'), 0, 1, 'C');
                $this->SetFont('Arial','',8); $this->Cell(0, 4, utf8_decode('"2025 - AÑO DE LA LIBERTAD"'), 0, 1, 'C');
                $this->Ln(5); $this->SetFont('Arial','B',14);
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
        $pdf->qr_link = $url_acta; $pdf->id_solicitud = $solicitud['id_token'];
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
        $pdf->Cell(40,6, utf8_decode('NUEVA UBICACIÓN:'),1); $pdf->Cell(0,6, utf8_decode($destino_nuevo_txt),1,1);
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
        
        // GUARDAR ACTA
        $ruta_abs_acta = __DIR__ . '/' . $ruta_rel_acta;
        if (!file_exists(dirname($ruta_abs_acta))) mkdir(dirname($ruta_abs_acta), 0777, true);
        $pdf->Output('F', $ruta_abs_acta);


        // ====================================================================
        // 9. GENERAR PDF 2: CONSTANCIA (ANEXO 4) AUTOMÁTICAMENTE
        // ====================================================================
        
        // DEFINICIÓN DE CLASE PARA CONSTANCIA (ANEXO 4)
        class PDF_Constancia_Gen extends FPDF {
            function Header() {
                $this->SetMargins(15, 15, 15); $this->SetAutoPageBreak(true, 10);
                $x = 15; $y = 15; $w_total = 180; $w_logo = 40; $w_texto = $w_total - $w_logo; $h_fila1 = 25; $h_fila2 = 8;
                
                // 1. LOGO
                $this->Rect($x, $y, $w_logo, $h_fila1);
                $logo = './assets/iosfa.png'; 
                if (file_exists($logo)) $this->ImageFit($logo, $x + 3, $y + 3, $w_logo - 6, $h_fila1 - 6);

                // 2. TÍTULO
                $this->Rect($x + $w_logo, $y, $w_texto, $h_fila1);
                $y_texto = $y + 8;
                $this->SetXY($x + $w_logo, $y_texto);
                $this->SetFont('Arial', 'B', 11);
                $this->Cell($w_texto, 5, utf8_decode('PROCEDIMIENTO PARA LA ADMINISTRACIÓN DE BIENES'), 0, 1, 'C');
                $this->SetX($x + $w_logo);
                $this->Cell($w_texto, 5, utf8_decode('PATRIMONIALES'), 0, 1, 'C');

                // 3. VERSIÓN
                $this->Rect($x, $y + $h_fila1, $w_total, $h_fila2);
                $this->SetXY($x, $y + $h_fila1 + 1.5);
                $this->SetFont('Arial', '', 9);
                $this->Cell($w_total, 5, utf8_decode('Versión: 1.0.0.'), 0, 1, 'C');

                $this->Ln(12);
                $this->SetFont('Arial', 'B', 10);
                $this->Cell(0, 6, utf8_decode('ANEXO 4 - PLANILLA REGISTRO DE TRANSFERENCIAS'), 0, 1, 'R');
                $this->Ln(5);
                $this->SetFont('Arial', 'B', 12);
                $this->Cell(0, 6, utf8_decode('TRANSFERENCIA DE BIENES MUEBLES'), 0, 1, 'C');
                $this->Ln(8);
            }
            function Footer() {
                $this->SetY(-30);
                $this->SetFont('Arial', '', 8);
                $this->Cell(0, 5, utf8_decode('1) Firma del Titular de la Dependencia que entrega el BIEN.'), 0, 1, 'L');
                $this->Cell(0, 5, utf8_decode('2) Firma del Titular de la Dependencia que recepciona el BIEN.'), 0, 1, 'L');
            }
            function ImageFit($file, $x, $y, $w, $h) {
                if (!file_exists($file)) return;
                list($width, $height) = getimagesize($file);
                if ($width == 0 || $height == 0) return;
                $ratioImg = $width / $height; $ratioBox = $w / $h;
                if ($ratioImg > $ratioBox) { $newW = $w; $newH = $w / $ratioImg; $newY = $y + ($h - $newH) / 2; $this->Image($file, $x, $newY, $newW, $newH); } 
                else { $newH = $h; $newW = $h * $ratioImg; $newX = $x + ($w - $newW) / 2; $this->Image($file, $newX, $y, $newW, $newH); }
            }
            function VCell($w, $h, $txt, $border=0, $align='C', $fill=false) {
                if($w==0) $w = $this->w - $this->rMargin - $this->x;
                $s = str_replace("\r", '', $txt); $nb = strlen($s); if($nb > 0 && $s[$nb-1] == "\n") $nb--;
                $cw = &$this->CurrentFont['cw']; $wmax = ($w - 2 * $this->cMargin) * 1000 / $this->FontSize;
                $i = 0; $nl = 1; while($i < $nb) { $c = $s[$i]; if($c == "\n") { $i++; $nl++; continue; } if($c == ' ') { } $i++; }
                $h_txt = $nl * 5; $y_offset = ($h - $h_txt) / 2;
                $x = $this->GetX(); $y = $this->GetY();
                if($border) $this->Rect($x, $y, $w, $h);
                $this->SetXY($x, $y + $y_offset); $this->MultiCell($w, 5, $txt, 0, $align, $fill); $this->SetXY($x + $w, $y);
            }
        }

        // Datos para constancia
        $motivo_raw = $solicitud['motivo_transferencia'];
        $plazo_mostrar = "Inmediato";
        if (preg_match('/\[EJECUCIÓN.*?:(.*?)\]/', $motivo_raw, $matches)) {
            $plazo_mostrar = trim($matches[1]);
            $motivo_raw = trim(str_replace($matches[0], '', $motivo_raw));
        }
        $parts = array_map('trim', explode('-', $motivo_raw));
        $parts = array_filter($parts); $parts = array_unique($parts);
        $motivo_mostrar = implode(' - ', $parts);

        $pdfC = new PDF_Constancia_Gen('P','mm','A4');
        $pdfC->AddPage();
        
        $pdfC->SetFont('Arial', 'B', 10);
        $pdfC->Write(6, utf8_decode('INSTANCIA QUE ORDENA / PROPONE LA TRANSFERENCIA: '));
        $pdfC->SetFont('Arial', '', 10);
        $pdfC->Write(6, utf8_decode(strtoupper($solicitud['n_rel'] ?? 'SISTEMA')));
        $pdfC->Ln(8);
        $pdfC->SetFont('Arial', 'B', 10);
        $pdfC->Write(6, utf8_decode('FUNDAMENTACIÓN: '));
        $pdfC->SetFont('Arial', '', 10);
        $pdfC->Write(6, utf8_decode($motivo_mostrar));
        $pdfC->Ln(8);
        $pdfC->SetFont('Arial', 'B', 10);
        $pdfC->Write(6, utf8_decode('PLAZO DE EJECUCIÓN: '));
        $pdfC->SetFont('Arial', '', 10);
        $pdfC->Write(6, utf8_decode($plazo_mostrar));
        $pdfC->Ln(15);

        $pdfC->SetFillColor(230, 230, 230); $pdfC->SetFont('Arial', 'B', 9);
        $w1 = 35; $w2 = 90; $w3 = 55; $h_header = 10;
        $pdfC->Cell($w1, $h_header, utf8_decode('N° INVENTARIO'), 1, 0, 'C', true);
        $pdfC->Cell($w2, $h_header, utf8_decode('DETALLE DEL BIEN'), 1, 0, 'C', true);
        $pdfC->Cell($w3, $h_header, utf8_decode('DEPENDENCIA RECEPTORA'), 1, 1, 'C', true);
        $pdfC->SetX(15); $pdfC->SetFont('Arial', '', 9); $h_fila = 18;
        $pdfC->VCell($w1, $h_fila, utf8_decode($bien_ant['codigo_inventario']), 1, 'C');
        $pdfC->VCell($w2, $h_fila, utf8_decode($bien_ant['elemento']), 1, 'C');
        $dest_c = $destino_nuevo_txt; if(strlen($dest_c)>40) $dest_c=substr($dest_c,0,40).'...';
        $pdfC->VCell($w3, $h_fila, utf8_decode($dest_c), 1, 'C');
        $pdfC->Ln($h_fila + 15);

        // Firmas Constancia
        $yF = $pdfC->GetY(); $margen = 15; $ancho = 80;
        // Firma 1 (Entrega)
        if (!empty($solicitud['f_rel'])) {
            $rutaf1 = 'uploads/firmas/'.$solicitud['f_rel'];
            if(!file_exists($rutaf1)) $rutaf1 = __DIR__.'/uploads/firmas/'.$solicitud['f_rel'];
            if(file_exists($rutaf1)) $pdfC->ImageFit($rutaf1, $margen+10, $yF, 60, 25);
        }
        $pdfC->SetXY($margen, $yF+28); $pdfC->SetFont('Arial','',9);
        $pdfC->MultiCell($ancho, 5, utf8_decode($solicitud['n_rel']), 0, 'C');
        $pdfC->SetXY($margen, $pdfC->GetY()); $pdfC->SetFont('Arial','B',11); $pdfC->Cell($ancho, 6, '(1)', 0, 0, 'C');
        
        // Firma 2 (Recibe)
        $x2 = $margen+$ancho+10;
        if(file_exists($ruta_firma_entregue)) $pdfC->ImageFit($ruta_firma_entregue, $x2+10, $yF, 60, 25);
        $pdfC->SetXY($x2, $yF+28); $pdfC->SetFont('Arial','',9);
        $pdfC->MultiCell($ancho, 5, utf8_decode($nombre_firmante), 0, 'C');
        $pdfC->SetXY($x2, $pdfC->GetY()); $pdfC->SetFont('Arial','B',11); $pdfC->Cell($ancho, 6, '(2)', 0, 0, 'C');

        // Guardar Constancia
        $nombre_const = 'Constancia_Movimiento_' . $id_movimiento_nuevo . '.pdf';
        $ruta_rel_const = 'pdfs_publicos/inventario_constancia/' . $nombre_const;
        $ruta_abs_const = __DIR__ . '/' . $ruta_rel_const;
        if (!file_exists(dirname($ruta_abs_const))) mkdir(dirname($ruta_abs_const), 0777, true);
        $pdfC->Output('F', $ruta_abs_const);
        $url_const = "$protocolo://{$_SERVER['HTTP_HOST']}" . dirname($_SERVER['PHP_SELF']) . "/$ruta_rel_const";


        // 10. ENVIAR COPIA POR EMAIL (AMBOS LINKS)
        if (!empty($email_final)) {
            $asunto_mail = "Transferencia Exitosa - Documentos Adjuntos";
            $cuerpo_mail = "
            <div style='font-family: Arial, sans-serif; max-width: 600px; margin: 0 auto; border: 1px solid #e0e0e0; border-radius: 8px; overflow: hidden;'>
                <div style='background-color: #198754; padding: 20px; text-align: center; color: white;'>
                    <h2 style='margin: 0;'>TRANSACCIÓN EXITOSA</h2>
                </div>
                <div style='padding: 30px; background-color: #ffffff;'>
                    <h3 style='color: #333; margin-top: 0;'>¡Felicitaciones!</h3>
                    <p style='color: #666; font-size: 16px;'>Hola <strong>$nombre_firmante</strong>,</p>
                    <p style='color: #666;'>La transferencia del bien <strong>{$bien_ant['elemento']}</strong> ha sido completada correctamente.</p>
                    <p style='color: #666;'>Aquí tiene sus documentos oficiales de respaldo:</p>
                    
                    <div style='text-align: center; margin: 30px 0;'>
                        <a href='$url_acta' style='display: block; margin-bottom: 10px; background-color: #0d6efd; color: white; padding: 15px 25px; text-decoration: none; border-radius: 5px; font-weight: bold;'>
                            DESCARGAR ACTA DE TRANSFERENCIA
                        </a>
                        <a href='$url_const' style='display: block; background-color: #6c757d; color: white; padding: 15px 25px; text-decoration: none; border-radius: 5px; font-weight: bold;'>
                            DESCARGAR CONSTANCIA DE MOVIMIENTO
                        </a>
                    </div>
                </div>
                <div style='background-color: #f1f1f1; padding: 15px; text-align: center; font-size: 12px; color: #888;'>
                    Departamento de Logística - IOSFA
                </div>
            </div>";
            enviarCorreoNativo($email_final, $asunto_mail, $cuerpo_mail);
        }

        $pdo->commit();
        
        // Retornar ambas URLs para el modal
        echo json_encode([
            'status' => 'ok', 
            'pdf_acta_url' => $url_acta,
            'pdf_constancia_url' => $url_const
        ]);

    }

} catch (Exception $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    echo json_encode(['status' => 'error', 'msg' => $e->getMessage()]);
}
?>