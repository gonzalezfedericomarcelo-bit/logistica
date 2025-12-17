<?php
// Archivo: inventario_reporte_pdf.php
require('fpdf/fpdf.php');
include 'conexion.php';

// Filtros
$where = "1=1";
$params = [];
if (!empty($_GET['ubicacion'])) { $where .= " AND servicio_ubicacion = :u"; $params[':u'] = $_GET['ubicacion']; }
if (!empty($_GET['estado'])) { 
    $where .= " AND e.nombre = :e"; 
    $params[':e'] = $_GET['estado']; 
}
// Filtro especial Matafuegos
if (!empty($_GET['tipo']) && $_GET['tipo']=='MATAFUEGOS') {
    $where .= " AND mat_tipo_carga_id IS NOT NULL";
}

$sql = "SELECT i.*, e.nombre as nombre_estado, m.tipo_carga, c.nombre as nombre_clase
        FROM inventario_cargos i 
        LEFT JOIN inventario_estados e ON i.id_estado_fk = e.id_estado
        LEFT JOIN inventario_config_matafuegos m ON i.mat_tipo_carga_id = m.id_config
        LEFT JOIN inventario_config_clases c ON i.mat_clase_id = c.id_clase
        WHERE $where ORDER BY servicio_ubicacion, elemento";
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$items = $stmt->fetchAll(PDO::FETCH_ASSOC);

class PDF_Repo extends FPDF {
    function Header() {
        $this->SetFont('Arial','B',10);
        $this->Cell(0,6,'REPORTE GENERAL DE INVENTARIO / MATAFUEGOS',0,1,'C');
        $this->Ln(4);
        
        $this->SetFillColor(200);
        $this->SetFont('Arial','B',6);
        // Ancho Total: 277 (A4 Landscape = 297mm - margenes)
        $this->Cell(20,6,'NRO/COD',1,0,'C',true);
        $this->Cell(40,6,'UBICACION',1,0,'C',true);
        $this->Cell(30,6,'TIPO CARGA',1,0,'C',true);
        $this->Cell(10,6,'KG',1,0,'C',true);
        $this->Cell(10,6,'CLASE',1,0,'C',true);
        $this->Cell(15,6,'FAB/VIDA',1,0,'C',true);
        $this->Cell(15,6,'ULT.CARGA',1,0,'C',true);
        $this->Cell(15,6,'PRUEBA H.',1,0,'C',true);
        $this->Cell(15,6,'ESTADO',1,0,'C',true);
        $this->Cell(25,6,'TECNICO',1,0,'C',true);
        $this->Cell(25,6,'RESPONSABLE',1,0,'C',true);
        $this->Cell(57,6,'OBSERVACIONES',1,1,'C',true);
    }
    function Footer() {
        $this->SetY(-10);
        $this->SetFont('Arial','I',6);
        $this->Cell(0,10,'Pagina '.$this->PageNo(),0,0,'C');
    }
}

// LANDSCAPE (L)
$pdf = new PDF_Repo('L','mm','A4');
$pdf->AliasNbPages();
$pdf->AddPage();
$pdf->SetFont('Arial','',6);

foreach($items as $i) {
    // Preparar textos
    $cod = $i['codigo_inventario'];
    $ubi = substr(utf8_decode($i['servicio_ubicacion']),0,25);
    $tipo = $i['tipo_carga'] ? substr(utf8_decode($i['tipo_carga']),0,15) : '-';
    $kg = $i['mat_capacidad'];
    $clase = $i['nombre_clase'];
    $fab = $i['fecha_fabricacion'] ? $i['fecha_fabricacion'].'/'.$i['vida_util_limite'] : '-';
    $uc = $i['mat_fecha_carga'] ? date('m/y', strtotime($i['mat_fecha_carga'])) : '-';
    $ph = $i['mat_fecha_ph'] ? date('m/y', strtotime($i['mat_fecha_ph'])) : '-';
    $estado = utf8_decode($i['nombre_estado']);
    $tec = substr(utf8_decode($i['nombre_tecnico']),0,15);
    $resp = substr(utf8_decode($i['nombre_responsable']),0,15);
    $obs = substr(utf8_decode($i['observaciones']),0,40);

    $pdf->Cell(20,6,$cod,1,0,'C');
    $pdf->Cell(40,6,$ubi,1,0,'L');
    $pdf->Cell(30,6,$tipo,1,0,'L');
    $pdf->Cell(10,6,$kg,1,0,'C');
    $pdf->Cell(10,6,$clase,1,0,'C');
    $pdf->Cell(15,6,$fab,1,0,'C');
    $pdf->Cell(15,6,$uc,1,0,'C');
    $pdf->Cell(15,6,$ph,1,0,'C');
    $pdf->Cell(15,6,$estado,1,0,'C');
    $pdf->Cell(25,6,$tec,1,0,'L');
    $pdf->Cell(25,6,$resp,1,0,'L');
    $pdf->Cell(57,6,$obs,1,1,'L');
}

$pdf->Output('I','Reporte_Matafuegos.pdf');
?>