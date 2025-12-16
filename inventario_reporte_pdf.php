<?php
// Archivo: inventario_reporte_pdf.php
// Genera PDF con listado general, filtrado por ubicación o tipo.
require('fpdf/fpdf.php');
include 'conexion.php';

// --- RECUPERAR FILTROS (Misma lógica) ---
$where = "1=1";
$params = [];
$titulo = "REPORTE GENERAL DE INVENTARIO";
$subtitulo = "Listado Completo";

if (!empty($_GET['ubicacion'])) {
    $where .= " AND servicio_ubicacion = :ubi";
    $params[':ubi'] = $_GET['ubicacion'];
    $titulo = "REPORTE DE INVENTARIO POR UBICACIÓN";
    $subtitulo = "Ubicación: " . $_GET['ubicacion'];
}

if (!empty($_GET['tipo'])) {
    $tipo = $_GET['tipo'];
    $subtitulo .= " - Tipo: " . $tipo;
    if ($tipo == 'INFORMATICA') $where .= " AND (elemento LIKE 'CPU%' OR elemento LIKE 'MONITOR%' OR elemento LIKE 'NOTEBOOK%')";
    elseif ($tipo == 'MATAFUEGOS') $where .= " AND elemento LIKE 'MATAFUEGO%'";
    elseif ($tipo == 'CAMARAS') $where .= " AND elemento LIKE 'CAMARA%'";
    elseif ($tipo == 'TELEFONIA') $where .= " AND elemento LIKE 'TELEFONO%'";
    else { $where .= " AND elemento LIKE :t"; $params[':t'] = $tipo.'%'; }
}

if (!empty($_GET['estado'])) {
    $where .= " AND estado = :est";
    $params[':est'] = $_GET['estado'];
    $subtitulo .= " (Estado: " . $_GET['estado'] . ")";
}

$sql = "SELECT * FROM inventario_cargos WHERE $where ORDER BY servicio_ubicacion, elemento";
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$items = $stmt->fetchAll(PDO::FETCH_ASSOC);

class PDF_Repo extends FPDF {
    public $tit, $sub;
    function Header() {
        if(file_exists('assets/img/sgalp.png')) $this->Image('assets/img/sgalp.png',10,10,20);
        $this->SetFont('Arial','B',12); $this->Cell(0,8,utf8_decode($this->tit),0,1,'C');
        $this->SetFont('Arial','',10); $this->Cell(0,5,utf8_decode($this->sub),0,1,'C');
        $this->Cell(0,5,date('d/m/Y H:i'),0,1,'C'); $this->Ln(5);
        
        $this->SetFillColor(220); $this->SetFont('Arial','B',8);
        $this->Cell(20,6,'CODIGO',1,0,'C',true);
        $this->Cell(80,6,'DESCRIPCION',1,0,'L',true);
        $this->Cell(50,6,'UBICACION',1,0,'L',true);
        $this->Cell(40,6,'RESPONSABLE',1,1,'L',true);
    }
    function Footer() {
        $this->SetY(-15); $this->SetFont('Arial','I',8);
        $this->Cell(0,10,utf8_decode('Página ').$this->PageNo().'/{nb}',0,0,'C');
    }
}

$pdf = new PDF_Repo();
$pdf->tit = $titulo; $pdf->sub = $subtitulo;
$pdf->AliasNbPages(); $pdf->AddPage(); $pdf->SetFont('Arial','',8);

if(count($items) > 0) {
    foreach($items as $i) {
        $elem = utf8_decode($i['elemento']);
        // Si hay S/N en observaciones, agregarlo
        if(strpos($i['observaciones'],'S/N')!==false) $elem .= "\n" . utf8_decode($i['observaciones']);
        
        $pdf->Cell(20,6,utf8_decode($i['codigo_inventario']),1,0,'C');
        // Recorte simple para evitar desbordes complejos en FPDF sin plugin
        $pdf->Cell(80,6,substr($elem,0,55),1,0,'L'); 
        $pdf->Cell(50,6,substr(utf8_decode($i['servicio_ubicacion']),0,30),1,0,'L');
        $pdf->Cell(40,6,substr(utf8_decode($i['nombre_responsable']),0,25),1,1,'L');
    }
    // Firmas al final
    $pdf->Ln(20); $y=$pdf->GetY();
    if($y>250) { $pdf->AddPage(); $y=$pdf->GetY()+20; }
    $pdf->Line(20,$y,80,$y); $pdf->Line(110,$y,170,$y);
    $pdf->SetXY(20,$y+2); $pdf->Cell(60,4,'Firma Relevador',0,0,'C');
    $pdf->SetXY(110,$y+2); $pdf->Cell(60,4,'Firma Responsable',0,0,'C');
} else {
    $pdf->Cell(0,10,'No hay datos para mostrar.',1,1,'C');
}

$pdf->Output('I','Reporte.pdf');
?>