<?php
// Archivo: inventario_pdf.php
require('fpdf/fpdf.php');
include 'conexion.php';

if (!isset($_GET['id'])) die("Error: ID no especificado.");
$id = $_GET['id'];

// Obtener datos completos
$sql = "SELECT i.*, e.nombre as nombre_estado, m.tipo_carga, c.nombre as nombre_clase
        FROM inventario_cargos i 
        LEFT JOIN inventario_estados e ON i.id_estado_fk = e.id_estado
        LEFT JOIN inventario_config_matafuegos m ON i.mat_tipo_carga_id = m.id_config
        LEFT JOIN inventario_config_clases c ON i.mat_clase_id = c.id_clase
        WHERE id_cargo = ?";
$stmt = $pdo->prepare($sql);
$stmt->execute([$id]);
$bien = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$bien) die("Error: Bien inexistente.");

// PDF
class PDF extends FPDF {
    function Header() {
        if(file_exists('assets/img/sgalp.png')) $this->Image('assets/img/sgalp.png', 10, 8, 30);
        $this->SetFont('Arial', 'B', 14);
        $this->Cell(0, 10, mb_convert_encoding('ACTA DE ENTREGA / CARGO INDIVIDUAL', 'ISO-8859-1', 'UTF-8'), 0, 1, 'C');
        $this->Ln(10);
    }
    function Footer() {
        $this->SetY(-15);
        $this->SetFont('Arial', 'I', 8);
        $this->Cell(0, 10, 'Pagina '.$this->PageNo().'/{nb}', 0, 0, 'C');
    }
}

$pdf = new PDF();
$pdf->AliasNbPages();
$pdf->AddPage();
$pdf->SetFont('Arial', '', 10);

// TABLA GENERAL
$pdf->SetFillColor(230);
$pdf->Cell(35, 8, 'Codigo', 1, 0, 'L', true);
$pdf->Cell(155, 8, $bien['codigo_inventario'], 1, 1);
$pdf->Cell(35, 8, 'Elemento', 1, 0, 'L', true);
$pdf->Cell(155, 8, mb_convert_encoding($bien['elemento'], 'ISO-8859-1', 'UTF-8'), 1, 1);
$pdf->Cell(35, 8, 'Ubicacion', 1, 0, 'L', true);
$pdf->Cell(155, 8, mb_convert_encoding($bien['servicio_ubicacion'], 'ISO-8859-1', 'UTF-8'), 1, 1);
$pdf->Cell(35, 8, 'Estado', 1, 0, 'L', true);
$pdf->Cell(155, 8, $bien['nombre_estado'] ? $bien['nombre_estado'] : 'Sin Asignar', 1, 1);
$pdf->Cell(35, 8, 'Observaciones', 1, 0, 'L', true);
$pdf->Cell(155, 8, mb_convert_encoding($bien['observaciones'], 'ISO-8859-1', 'UTF-8'), 1, 1);

// DETALLE MATAFUEGO (Si existe)
if (!empty($bien['mat_tipo_carga_id'])) {
    $pdf->Ln(8);
    $pdf->SetFont('Arial', 'B', 11);
    $pdf->Cell(0, 8, 'FICHA TECNICA MATAFUEGO', 0, 1, 'L');
    $pdf->SetFont('Arial', '', 9);
    
    // Fila 1
    $pdf->Cell(35, 7, 'Tipo Carga:', 1, 0, 'L', true);
    $pdf->Cell(30, 7, mb_convert_encoding($bien['tipo_carga'], 'ISO-8859-1', 'UTF-8'), 1, 0);
    $pdf->Cell(30, 7, 'Capacidad:', 1, 0, 'L', true);
    $pdf->Cell(30, 7, $bien['mat_capacidad'].' Kg', 1, 0);
    $pdf->Cell(30, 7, 'Clase:', 1, 0, 'L', true);
    $pdf->Cell(35, 7, $bien['nombre_clase'], 1, 1);

    // Fila 2
    $pdf->Cell(35, 7, 'Fabricacion:', 1, 0, 'L', true);
    $pdf->Cell(30, 7, $bien['fecha_fabricacion'], 1, 0);
    $pdf->Cell(30, 7, 'Vida Util Limite:', 1, 0, 'L', true);
    $pdf->Cell(30, 7, $bien['vida_util_limite'], 1, 0);
    $pdf->Cell(30, 7, 'Complementos:', 1, 0, 'L', true);
    $pdf->Cell(35, 7, mb_convert_encoding($bien['complementos'], 'ISO-8859-1', 'UTF-8'), 1, 1);

    // Fila 3
    $pdf->Cell(35, 7, 'Ultima Carga:', 1, 0, 'L', true);
    $pdf->Cell(30, 7, $bien['mat_fecha_carga'], 1, 0);
    $pdf->Cell(30, 7, 'Prueba Hidraulica:', 1, 0, 'L', true);
    $pdf->Cell(30, 7, $bien['mat_fecha_ph'], 1, 0);
    $pdf->Cell(30, 7, 'Tecnico:', 1, 0, 'L', true);
    $pdf->Cell(35, 7, mb_convert_encoding($bien['nombre_tecnico'], 'ISO-8859-1', 'UTF-8'), 1, 1);

    // Adjuntos
    $pdf->Ln(2);
    $info = "Adjuntos: ";
    $info .= ($bien['archivo_remito']) ? "[Remito SI] " : "[Remito NO] ";
    $info .= ($bien['archivo_comprobante']) ? "[Comprobante SI]" : "[Comprobante NO]";
    $pdf->Cell(0, 6, $info, 0, 1, 'L');
}

// FIRMAS
$pdf->Ln(30);
$y = $pdf->GetY();
// Responsable
if($bien['firma_responsable'] && file_exists($bien['firma_responsable'])) 
    $pdf->Image($bien['firma_responsable'], 20, $y-15, 40, 15);
$pdf->Line(20, $y, 70, $y);
$pdf->SetXY(20, $y+2); $pdf->Cell(50, 4, 'Responsable: '.$bien['nombre_responsable'], 0, 0, 'C');

// Relevador
if($bien['firma_relevador'] && file_exists($bien['firma_relevador'])) 
    $pdf->Image($bien['firma_relevador'], 80, $y-15, 40, 15);
$pdf->Line(80, $y, 130, $y);
$pdf->SetXY(80, $y+2); $pdf->Cell(50, 4, 'Logistica / Relevador', 0, 0, 'C');

// Jefe
if($bien['firma_jefe'] && file_exists($bien['firma_jefe'])) 
    $pdf->Image($bien['firma_jefe'], 140, $y-15, 40, 15);
$pdf->Line(140, $y, 190, $y);
$pdf->SetXY(140, $y+2); $pdf->Cell(50, 4, 'Jefe: '.$bien['nombre_jefe_servicio'], 0, 0, 'C');

$pdf->Output();
?>