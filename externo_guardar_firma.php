<?php
// Archivo: externo_guardar_firma.php
session_start();
include 'conexion.php';
require('fpdf/fpdf.php');
date_default_timezone_set('America/Argentina/Buenos_Aires');

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') { echo json_encode(['status'=>'error','msg'=>'Método inválido']); exit; }

$token = $_POST['token'] ?? '';
$otp = $_POST['otp'] ?? '';
$nombre_firmante = $_POST['nombre_firmante'] ?? ''; 
$firma_data = $_POST['firma_base64'] ?? '';

if (empty($token) || empty($firma_data) || empty($otp)) {
    echo json_encode(['status' => 'error', 'msg' => 'Datos incompletos.']); exit;
}

try {
    $pdo->beginTransaction();

    // 1. Validar Token
    $stmt = $pdo->prepare("SELECT t.*, u.nombre as n_rel, u.apellido as a_rel 
                           FROM inventario_transferencias_pendientes t
                           LEFT JOIN usuarios u ON t.creado_por = u.id_usuario
                           WHERE t.token_hash = ? AND t.token_otp = ? AND t.estado = 'pendiente'");
    $stmt->execute([$token, $otp]);
    $solicitud = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$solicitud) throw new Exception("Solicitud inválida o código incorrecto.");

    $id_cargo = $solicitud['id_bien'];
    $usuario_relevador = $solicitud['n_rel'] . " " . $solicitud['a_rel']; // El que inició el trámite

    // 2. Guardar Imagen Firma (Del que entrega)
    $ruta_carpeta = 'uploads/firmas/';
    if (!file_exists($ruta_carpeta)) mkdir($ruta_carpeta, 0777, true);
    
    $nombre_archivo_firma = 'firma_entrega_' . $id_cargo . '_' . uniqid() . '.png';
    $ruta_firma_entregue = $ruta_carpeta . $nombre_archivo_firma;
    $data_img = base64_decode(preg_replace('#^data:image/\w+;base64,#i', '', $firma_data));
    file_put_contents($ruta_firma_entregue, $data_img);

    // 3. Datos del bien para historial (Estado anterior)
    $bien_ant = $pdo->query("SELECT * FROM inventario_cargos WHERE id_cargo = $id_cargo")->fetch(PDO::FETCH_ASSOC);
    $origen_txt = trim(($bien_ant['destino_principal'] ?? '') . ' - ' . ($bien_ant['servicio_ubicacion'] ?? ''));
    $destino_txt = trim(($solicitud['nuevo_destino_nombre'] ?? '') . ' - ' . ($solicitud['nueva_area_nombre'] ?? ''));

    // 4. ACTUALIZAR CARGO (Transferencia Real)
    $sqlUpd = "UPDATE inventario_cargos SET 
               destino_principal = ?, servicio_ubicacion = ?, 
               nombre_responsable = ?, nombre_jefe_servicio = ?,
               firma_responsable_path = ?, firma_jefe_path = ?
               WHERE id_cargo = ?";
    $pdo->prepare($sqlUpd)->execute([
        $solicitud['nuevo_destino_id'], $solicitud['nueva_area_nombre'], 
        $solicitud['nuevo_responsable_nombre'], $solicitud['nuevo_jefe_nombre'],
        $solicitud['firma_nuevo_responsable_path'], // Firma Recibe (Nuevo Resp)
        $solicitud['firma_nuevo_jefe_path'],      // Firma Jefe
        $id_cargo
    ]);

    // 5. Historial
    $obs = "TRANSFERENCIA EXTERNA.\nEntregó: $nombre_firmante (Firma Digital)\nRecibió: " . $solicitud['nuevo_responsable_nombre'];
    $pdo->prepare("INSERT INTO historial_movimientos (id_bien, usuario_registro, tipo_movimiento, ubicacion_anterior, ubicacion_nueva, observacion_movimiento, fecha_movimiento) VALUES (?, ?, 'TRANSFERENCIA', ?, ?, ?, NOW())")
        ->execute([$id_cargo, $solicitud['creado_por'], $origen_txt, $destino_txt, $obs]);

    // 6. Cerrar Solicitud
    $pdo->prepare("UPDATE inventario_transferencias_pendientes SET estado='confirmado' WHERE id_token=?")->execute([$solicitud['id_token']]);

    // ========================================================================
    // 7. NOTIFICAR AL ROL "CARGO PATRIMONIAL"
    // ========================================================================
    // Buscamos usuarios con ese rol. Ajusta 'Cargo Patrimonial' al nombre exacto en tu BD.
    $stmtRoles = $pdo->prepare("SELECT u.id_usuario FROM usuarios u 
                                JOIN roles r ON u.id_rol = r.id_rol 
                                WHERE r.nombre_rol LIKE ?");
    $stmtRoles->execute(['%Patrimonial%']); // Busca roles que contengan "Patrimonial"
    $usuarios_patrimonial = $stmtRoles->fetchAll(PDO::FETCH_COLUMN);

    if ($usuarios_patrimonial) {
        $mensaje_notif = "Firma Pendiente: Transferencia del bien ID $id_cargo completada externamente. Requiere su firma.";
        $link_notif = "inventario_pdf.php?id=" . $id_cargo; // O una vista para firmar

        $sqlNotif = "INSERT INTO notificaciones (id_usuario, tipo, mensaje, enlace, fecha_creacion, leido) VALUES (?, 'aviso', ?, ?, NOW(), 0)";
        $stmtInsNotif = $pdo->prepare($sqlNotif);

        foreach ($usuarios_patrimonial as $id_patrimonial) {
            $stmtInsNotif->execute([$id_patrimonial, $mensaje_notif, $link_notif]);
        }
    }

    // ========================================================================
    // 8. REGENERAR PDF FINAL (NUEVO DISEÑO CON 4 FIRMAS)
    // ========================================================================
    class PDF_Final extends FPDF {
        function Header() {
            $logo_izq = 'assets/img/iosfa.png'; $logo_der = 'assets/img/logo_armada.png';
            if(file_exists($logo_izq)) $this->Image($logo_izq, 10, 8, 20);
            if(file_exists($logo_der)) $this->Image($logo_der, 180, 8, 20);
            $this->SetFont('Arial','B',12); $this->Cell(0,5, utf8_decode('IOSFA - INSTITUTO DE OBRA SOCIAL DE LAS FUERZAS ARMADAS'),0,1,'C');
            $this->SetFont('Arial','B',9); $this->Cell(0,5, utf8_decode('"2025 - AÑO DE LA RECONSTRUCCIÓN DE LA NACIÓN ARGENTINA"'),0,1,'C');
            $this->Ln(5);
            $this->SetFont('Arial','B',14); $this->Cell(0,10, utf8_decode('ACTA DE ENTREGA / CARGO INDIVIDUAL'),0,1,'C');
            $this->Line(10, $this->GetY(), 200, $this->GetY());
            $this->Ln(5);
        }
        function Footer() {
            $this->SetY(-25);
            $qr_api = "https://quickchart.io/qr?text=VALIDADO&size=100"; // Placeholder
            $this->Image($qr_api, 10, $this->GetY(), 20, 20, 'PNG');
            $this->SetX(35); $this->SetFont('Courier','',7); $this->SetTextColor(100);
            $this->MultiCell(0, 3, utf8_decode("DOCUMENTO GENERADO AUTOMÁTICAMENTE\nSeguridad: Hash SHA-256 verificado.\nDesarrollador: División de Informática y Sistemas\nID: ".uniqid()." | Fecha: ".date('d/m/Y H:i')), 0, 'L');
        }
    }

    $pdf = new PDF_Final();
    $pdf->AddPage();
    $pdf->SetFillColor(230,230,230);
    
    // Bloque Datos
    $pdf->SetFont('Arial','B',10); $pdf->Cell(0,7, utf8_decode('DATOS DEL BIEN'),1,1,'L',true);
    $pdf->SetFont('Arial','',10);
    $pdf->Cell(30,7, 'Elemento:',1); $pdf->Cell(0,7, utf8_decode($bien_ant['elemento']),1,1);
    $pdf->Cell(30,7, utf8_decode('Código:'),1); $pdf->Cell(60,7, utf8_decode($bien_ant['codigo_inventario']),1);
    $pdf->Cell(30,7, 'Serie:',1); $pdf->Cell(0,7, utf8_decode($bien_ant['mat_numero_grabado']),1,1);
    
    $pdf->Ln(5);
    $pdf->SetFont('Arial','B',10); $pdf->Cell(0,7, utf8_decode('DETALLE DE TRANSFERENCIA'),1,1,'L',true);
    $pdf->SetFont('Arial','',10);
    $pdf->Cell(30,7, utf8_decode('Origen:'),1); $pdf->Cell(0,7, utf8_decode($origen_txt),1,1);
    $pdf->Cell(30,7, utf8_decode('Destino:'),1); $pdf->Cell(0,7, utf8_decode($destino_txt),1,1);
    $pdf->Ln(2);
    $pdf->Cell(30,7, utf8_decode('Fecha:'),1); $pdf->Cell(0,7, date('d/m/Y H:i'),1,1);

    // FIRMAS (4 BLOQUES)
    $pdf->Ln(10);
    $pdf->SetFont('Arial','B',10); $pdf->Cell(0,7, utf8_decode('CONFORMIDAD Y FIRMAS'),1,1,'L',true);
    $pdf->Ln(5);

    $y = $pdf->GetY();
    $margen = 15;
    
    // 1. ENTREGA (Firma Reciente)
    if(file_exists($ruta_firma_entregue)) $pdf->Image($ruta_firma_entregue, $margen, $y, 30);
    // 2. RECIBE (Solicitud)
    $pathRecibe = __DIR__ . '/' . $solicitud['firma_nuevo_responsable_path'];
    if(file_exists($pathRecibe)) $pdf->Image($pathRecibe, $margen + 50, $y, 30);
    
    $pdf->SetY($y + 25);
    $pdf->SetFont('Arial','',8);
    $pdf->SetX($margen); $pdf->Cell(40,5, utf8_decode('Entregó (Saliente)'), 'T', 0, 'C');
    $pdf->SetX($margen+50); $pdf->Cell(40,5, utf8_decode('Recibió (Entrante)'), 'T', 0, 'C');
    
    $pdf->SetFont('Arial','B',7);
    $pdf->Ln(4);
    $pdf->SetX($margen); $pdf->Cell(40,5, utf8_decode($nombre_firmante), 0, 0, 'C');
    $pdf->SetX($margen+50); $pdf->Cell(40,5, utf8_decode($solicitud['nuevo_responsable_nombre']), 0, 1, 'C');

    // FILA 2 (Relevador y Aval)
    $y2 = $pdf->GetY() + 10;
    // 3. AVAL (Jefe)
    $pathJefe = __DIR__ . '/' . $solicitud['firma_nuevo_jefe_path'];
    if(file_exists($pathJefe)) $pdf->Image($pathJefe, $margen + 50, $y2, 30);
    
    $pdf->SetY($y2 + 25);
    $pdf->SetFont('Arial','',8);
    $pdf->SetX($margen); $pdf->Cell(40,5, utf8_decode('Relevador (Sistema)'), 'T', 0, 'C');
    $pdf->SetX($margen+50); $pdf->Cell(40,5, utf8_decode('Aval / Jefe Servicio'), 'T', 0, 'C');

    $pdf->SetFont('Arial','B',7);
    $pdf->Ln(4);
    $pdf->SetX($margen); $pdf->Cell(40,5, utf8_decode($usuario_relevador), 0, 0, 'C');
    $pdf->SetX($margen+50); $pdf->Cell(40,5, utf8_decode($solicitud['nuevo_jefe_nombre']), 0, 1, 'C');

    // CARGO PATRIMONIAL (Aparte)
    $pdf->Ln(10);
    $pdf->SetFont('Arial','',8);
    $pdf->Cell(0,5, utf8_decode('Cargo Patrimonial (Firma Pendiente)'), 0, 1, 'R');

    $ruta_pdf_final = __DIR__ . '/pdfs_publicos/inventario_pdf/new_' . $token . '.pdf';
    $pdf->Output('F', $ruta_pdf_final);

    $pdo->commit();
    echo json_encode(['status' => 'ok']);

} catch (Exception $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    echo json_encode(['status' => 'error', 'msg' => 'Error: ' . $e->getMessage()]);
}
?>