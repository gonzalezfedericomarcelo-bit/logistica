<?php
// Archivo: inventario_pdf.php
require('fpdf/fpdf.php');
include 'conexion.php';

// Validar ID
if (!isset($_GET['id'])) {
    die("Error: No se especificó el ID del bien.");
}

$id = $_GET['id'];

// 1. BUSCAR SOLAMENTE EL BIEN ESPECÍFICO (No toda la ubicación)
$stmt = $pdo->prepare("SELECT * FROM inventario_cargos WHERE id_cargo = ?");
$stmt->execute([$id]);
$bien = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$bien) {
    die("Error: El bien con ID $id no existe.");
}

// 2. TOMAR DATOS ESPECÍFICOS DE ESTE BIEN
$ubicacion = $bien['servicio_ubicacion'];
$firma_resp = $bien['firma_responsable'];
$nombre_resp = $bien['nombre_responsable'];
$firma_jefe = $bien['firma_jefe'];
$nombre_jefe = $bien['nombre_jefe_servicio'];
$firma_rel = $bien['firma_relevador'] ?? ''; // Firma del relevador si existe

// ---------------------------------------------------------
// 3. GENERACIÓN DEL PDF
// ---------------------------------------------------------
class PDF extends FPDF {
    public $nombre_lugar;
    function Header() {
        if(file_exists('assets/img/sgalp.png')) {
            $this->Image('assets/img/sgalp.png', 10, 8, 30);
        }
        $this->SetFont('Arial', 'B', 14);
        $this->Cell(0, 10, mb_convert_encoding('ACTA DE ENTREGA DE BIEN / CARGO INDIVIDUAL', 'ISO-8859-1', 'UTF-8'), 0, 1, 'C');
        $this->SetFont('Arial', '', 10);
        $this->Cell(0, 8, mb_convert_encoding('Ubicación de Destino: ' . $this->nombre_lugar, 'ISO-8859-1', 'UTF-8'), 0, 1, 'C');
        $this->Ln(5);
        
        $this->SetFillColor(230);
        $this->SetFont('Arial', 'B', 8);
        $this->Cell(15, 8, 'ID', 1, 0, 'C', true);
        $this->Cell(25, 8, mb_convert_encoding('Código', 'ISO-8859-1', 'UTF-8'), 1, 0, 'C', true);
        $this->Cell(80, 8, 'Elemento / Descripción', 1, 0, 'C', true);
        $this->Cell(25, 8, 'Estado', 1, 0, 'C', true);
        $this->Cell(45, 8, 'Observaciones', 1, 1, 'C', true);
    }
    function Footer() {
        $this->SetY(-15);
        $this->SetFont('Arial', 'I', 8);
        $this->Cell(0, 10, mb_convert_encoding('Pág ' . $this->PageNo() . '/{nb}', 'ISO-8859-1', 'UTF-8'), 0, 0, 'C');
    }
}

$pdf = new PDF();
$pdf->AliasNbPages();
$pdf->nombre_lugar = $ubicacion;
$pdf->AddPage();
$pdf->SetFont('Arial', '', 8);

// Imprimir SOLO la fila del bien actual
$elem = substr($bien['elemento'], 0, 55);
$obs = substr($bien['observaciones'], 0, 25);

$pdf->Cell(15, 8, $bien['id_cargo'], 1, 0, 'C');
$pdf->Cell(25, 8, $bien['codigo_inventario'], 1, 0, 'C');
$pdf->Cell(80, 8, mb_convert_encoding($elem, 'ISO-8859-1', 'UTF-8'), 1, 0, 'L');
$pdf->Cell(25, 8, mb_convert_encoding($bien['estado'], 'ISO-8859-1', 'UTF-8'), 1, 0, 'C');
$pdf->Cell(45, 8, mb_convert_encoding($obs, 'ISO-8859-1', 'UTF-8'), 1, 1, 'L');

// ---------------------------------------------------------
// 4. SECCIÓN DE FIRMAS (ESTRICTAMENTE LAS DE ESTE BIEN)
// ---------------------------------------------------------
$pdf->Ln(25); // Más espacio para que se vea claro

// Definir posiciones
$y_firmas = $pdf->GetY();
$w_bloque = 60;
$x_col1 = 10;
$x_col2 = 75;
$x_col3 = 140;

// --- 1. RESPONSABLE (Izquierda) ---
if (!empty($firma_resp) && file_exists($firma_resp)) {
    $pdf->Image($firma_resp, $x_col1 + 10, $y_firmas - 15, 40, 15);
}
$pdf->SetXY($x_col1, $y_firmas);
$pdf->Cell($w_bloque, 0, '', 'T'); // Línea
$pdf->Ln(2);
$pdf->SetX($x_col1);
$pdf->SetFont('Arial', 'B', 8);
$pdf->Cell($w_bloque, 4, mb_convert_encoding($nombre_resp, 'ISO-8859-1', 'UTF-8'), 0, 1, 'C');
$pdf->SetX($x_col1);
$pdf->SetFont('Arial', '', 7);
$pdf->Cell($w_bloque, 4, 'RESPONSABLE A CARGO', 0, 0, 'C');

// --- 2. RELEVADOR (Centro) ---
if (!empty($firma_rel) && file_exists($firma_rel)) {
    $pdf->Image($firma_rel, $x_col2 + 10, $y_firmas - 15, 40, 15);
}
$pdf->SetXY($x_col2, $y_firmas);
$pdf->Cell($w_bloque, 0, '', 'T');
$pdf->Ln(2);
$pdf->SetX($x_col2);
$pdf->SetFont('Arial', 'B', 8);
$pdf->Cell($w_bloque, 4, 'LOGISTICA / RELEVADOR', 0, 1, 'C');
// Si no hay firma, no ponemos nombre falso, dejamos espacio.

// --- 3. JEFE SERVICIO (Derecha) ---
if (!empty($firma_jefe) && file_exists($firma_jefe)) {
    $pdf->Image($firma_jefe, $x_col3 + 10, $y_firmas - 15, 40, 15);
}
$pdf->SetXY($x_col3, $y_firmas);
$pdf->Cell($w_bloque, 0, '', 'T');
$pdf->Ln(2);
$pdf->SetX($x_col3);
$pdf->SetFont('Arial', 'B', 8);
$pdf->Cell($w_bloque, 4, mb_convert_encoding($nombre_jefe, 'ISO-8859-1', 'UTF-8'), 0, 1, 'C');
$pdf->SetX($x_col3);
$pdf->SetFont('Arial', '', 7);
$pdf->Cell($w_bloque, 4, 'JEFE DE SERVICIO / AVAL', 0, 0, 'C');

// ---------------------------------------------------------
// 5. SALIDA
// ---------------------------------------------------------
$pdf->Output('I', 'Recibo_Bien_'.$id.'.pdf'); // 'I' para mostrar en navegador directamente
?>