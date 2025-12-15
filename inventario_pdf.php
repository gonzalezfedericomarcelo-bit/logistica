<?php
// Archivo: inventario_pdf.php
require('fpdf/fpdf.php'); // Asegúrate que la ruta a fpdf sea correcta
include 'conexion.php';

if (!isset($_GET['id'])) die("Falta ID");

// Obtener datos
$stmt = $pdo->prepare("SELECT i.*, u.nombre_completo as nombre_relevador 
                       FROM inventario_cargos i 
                       JOIN usuarios u ON i.id_usuario_relevador = u.id_usuario 
                       WHERE i.id_cargo = :id");
$stmt->execute([':id' => $_GET['id']]);
$datos = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$datos) die("Documento no encontrado");

class PDF extends FPDF {
    function Header() {
        // LOGO
        if(file_exists('assets/img/sgalp.png')) {
            $this->Image('assets/img/sgalp.png', 10, 10, 30);
        }
        
        $this->SetFont('Arial', 'B', 16);
        $this->Cell(0, 10, utf8_decode('CONSTANCIA DE CARGO PATRIMONIAL'), 0, 1, 'C');
        $this->SetFont('Arial', '', 10);
        $this->Cell(0, 5, utf8_decode('Departamento de Logística e Infraestructura'), 0, 1, 'C');
        $this->Cell(0, 5, utf8_decode('Clínica Privada'), 0, 1, 'C');
        
        // SELLO LOGISTICA (Simulado arriba a la derecha)
        if(file_exists('assets/img/sello_logistica.png')) {
            $this->Image('assets/img/sello_logistica.png', 170, 10, 30);
        } else {
            // Si no hay imagen, un recuadro
            $this->SetXY(170, 10);
            $this->SetFont('Arial', 'B', 8);
            $this->MultiCell(30, 5, "SELLO\nLOGISTICA\nVERIFICADO", 1, 'C');
        }
        
        $this->Ln(20);
        $this->SetDrawColor(0,0,0);
        $this->Line(10, 45, 200, 45);
        $this->Ln(10);
    }

    function Footer() {
        $this->SetY(-30);
        $this->SetFont('Arial', 'I', 8);
        $this->Cell(0, 10, utf8_decode('Documento generado digitalmente por el Sistema de Gestión Logística (SGALP)'), 0, 0, 'C');
        
        // QR Simulado
        $this->Image('https://api.qrserver.com/v1/create-qr-code/?size=150x150&data=CargoID-'.$GLOBALS['datos']['id_cargo'], 180, 265, 20, 20, 'PNG');
    }
}

$pdf = new PDF();
$pdf->AddPage();
$pdf->SetFont('Arial', '', 12);

// FECHA
$pdf->SetFont('Arial', 'B', 11);
$pdf->Cell(0, 10, 'Fecha de Emisión: ' . date('d/m/Y H:i', strtotime($datos['fecha_creacion'])), 0, 1, 'R');
$pdf->Ln(5);

// CUERPO DEL DOCUMENTO
$pdf->SetFont('Arial', '', 12);
$texto = "Por medio de la presente, se deja constancia de la entrega y responsabilidad de uso del siguiente elemento patrimonial:";
$pdf->MultiCell(0, 8, utf8_decode($texto));
$pdf->Ln(10);

// DETALLES EN RECUADRO
$pdf->SetFillColor(245, 245, 245);
$pdf->SetFont('Arial', 'B', 12);
$pdf->Cell(50, 10, 'Elemento:', 1, 0, 'L', true);
$pdf->SetFont('Arial', '', 12);
$pdf->Cell(140, 10, utf8_decode($datos['elemento']), 1, 1, 'L');

$pdf->SetFont('Arial', 'B', 12);
$pdf->Cell(50, 10, utf8_decode('Código/Serie:'), 1, 0, 'L', true);
$pdf->SetFont('Arial', '', 12);
$pdf->Cell(140, 10, utf8_decode($datos['codigo_inventario']), 1, 1, 'L');

$pdf->SetFont('Arial', 'B', 12);
$pdf->Cell(50, 10, utf8_decode('Ubicación:'), 1, 0, 'L', true);
$pdf->SetFont('Arial', '', 12);
$pdf->Cell(140, 10, utf8_decode($datos['servicio_ubicacion']), 1, 1, 'L');

$pdf->SetFont('Arial', 'B', 12);
$pdf->Cell(50, 10, 'Observaciones:', 1, 0, 'L', true);
$pdf->SetFont('Arial', '', 12);
$pdf->MultiCell(140, 10, utf8_decode($datos['observaciones']), 1, 'L');

$pdf->Ln(15);
$pdf->MultiCell(0, 8, utf8_decode("El responsable asume el compromiso de cuidar el bien asignado y notificar cualquier desperfecto a Logística."));

// FIRMAS
$pdf->Ln(20);
$y_firmas = $pdf->GetY();

// Ancho para 3 firmas
$w_firma = 60;

// 1. Responsable
$pdf->SetXY(10, $y_firmas);
if(file_exists($datos['firma_responsable'])) {
    $pdf->Image($datos['firma_responsable'], 15, $y_firmas, 40, 20);
}
$pdf->SetXY(10, $y_firmas + 25);
$pdf->SetFont('Arial', 'B', 9);
$pdf->Cell($w_firma, 5, utf8_decode($datos['nombre_responsable']), 'T', 1, 'C');
$pdf->Cell($w_firma, 5, 'Responsable / Usuario', 0, 0, 'C');

// 2. Relevador (Logística)
$pdf->SetXY(10 + $w_firma + 5, $y_firmas);
if(file_exists($datos['firma_relevador'])) {
    $pdf->Image($datos['firma_relevador'], 10 + $w_firma + 10, $y_firmas, 40, 20);
}
$pdf->SetXY(10 + $w_firma + 5, $y_firmas + 25);
$pdf->SetFont('Arial', 'B', 9);
$pdf->Cell($w_firma, 5, utf8_decode($datos['nombre_relevador']), 'T', 1, 'C');
$pdf->Cell($w_firma, 5, utf8_decode('Relevador (Logística)'), 0, 0, 'C');

// 3. Jefe Servicio
$pdf->SetXY(10 + ($w_firma * 2) + 10, $y_firmas);
if(file_exists($datos['firma_jefe'])) {
    $pdf->Image($datos['firma_jefe'], 10 + ($w_firma * 2) + 15, $y_firmas, 40, 20);
}
$pdf->SetXY(10 + ($w_firma * 2) + 10, $y_firmas + 25);
$pdf->SetFont('Arial', 'B', 9);
$pdf->Cell($w_firma, 5, utf8_decode($datos['nombre_jefe_servicio']), 'T', 1, 'C');
$pdf->Cell($w_firma, 5, 'Jefe de Servicio', 0, 0, 'C');

$pdf->Output();
?>