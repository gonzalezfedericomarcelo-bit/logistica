<?php
// Archivo: inventario_reporte_pdf.php
session_start(); // <--- ESTO FALTABA, POR ESO NO TE RECONOCÍA EL PERMISO
require('fpdf/fpdf.php');
include 'conexion.php';
include 'funciones_permisos.php'; // Aseguramos que cargue las funciones

if (!isset($_SESSION['usuario_id']) || !tiene_permiso('inventario_reportes', $pdo)) {
    die("Acceso denegado: No tienes permiso para generar reportes.");
}

// Filtros
$where = "1=1";
$params = [];

if (!empty($_GET['ubicacion'])) { 
    $where .= " AND i.servicio_ubicacion = :u"; 
    $params[':u'] = $_GET['ubicacion']; 
}
if (!empty($_GET['estado'])) { 
    $where .= " AND e.nombre = :e"; 
    $params[':e'] = $_GET['estado']; 
}
if (!empty($_GET['tipo_bien'])) {
    if ($_GET['tipo_bien'] == 'Matafuegos') {
        $where .= " AND i.mat_tipo_carga_id IS NOT NULL";
    } elseif ($_GET['tipo_bien'] == 'General') {
        $where .= " AND i.mat_tipo_carga_id IS NULL";
    } else {
        // Si filtra por un tipo específico de matafuego
        $where .= " AND (SELECT tipo_carga FROM inventario_config_matafuegos WHERE id_config = i.mat_tipo_carga_id) = :t";
        $params[':t'] = $_GET['tipo_bien'];
    }
}

$sql = "SELECT i.*, e.nombre as nombre_estado, m.tipo_carga, c.nombre as nombre_clase
        FROM inventario_cargos i 
        LEFT JOIN inventario_estados e ON i.id_estado_fk = e.id_estado
        LEFT JOIN inventario_config_matafuegos m ON i.mat_tipo_carga_id = m.id_config
        LEFT JOIN inventario_config_clases c ON i.mat_clase_id = c.id_clase
        WHERE $where ORDER BY i.servicio_ubicacion, i.elemento";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$items = $stmt->fetchAll(PDO::FETCH_ASSOC);

class PDF_Repo extends FPDF {
    function Header() {
        $this->SetFont('Arial','B',14);
        $this->Cell(0,10, utf8_decode('Reporte de Inventario'),0,1,'C');
        $this->SetFont('Arial','',8);
        $this->Cell(0,5, utf8_decode('Generado el: ' . date('d/m/Y H:i')),0,1,'C');
        $this->Ln(5);
        
        $this->SetFillColor(220,220,220);
        $this->SetFont('Arial','B',7);
        
        $this->Cell(15,6, utf8_decode('Cód'),1,0,'C',true);
        $this->Cell(25,6, utf8_decode('Ubicación'),1,0,'C',true);
        $this->Cell(50,6, utf8_decode('Descripción'),1,0,'C',true);
        $this->Cell(15,6, 'Tipo',1,0,'C',true);
        $this->Cell(10,6, 'Kg',1,0,'C',true);
        $this->Cell(10,6, 'Clase',1,0,'C',true);
        $this->Cell(15,6, 'Fab/Vida',1,0,'C',true);
        $this->Cell(12,6, 'Carga',1,0,'C',true);
        $this->Cell(12,6, 'PH',1,0,'C',true);
        $this->Cell(20,6, 'Estado',1,0,'C',true);
        $this->Cell(15,6, utf8_decode('Técnico'),1,0,'C',true);
        $this->Cell(30,6, 'Responsable',1,0,'C',true);
        $this->Cell(20,6, 'Remito',1,1,'C',true);
    }

    function Footer() {
        $this->SetY(-10);
        $this->SetFont('Arial','I',6);
        $this->Cell(0,10, utf8_decode('Página ').$this->PageNo(),0,0,'C');
    }
}

// LANDSCAPE (L) para que entre todo
$pdf = new PDF_Repo('L','mm','A4');
$pdf->AliasNbPages();
$pdf->AddPage();
$pdf->SetFont('Arial','',6);

foreach($items as $i) {
    // Preparar textos para que no rompan la celda
    $cod = substr($i['codigo_inventario'] ?? '', 0, 10);
    $ubi = substr(utf8_decode($i['servicio_ubicacion'] ?? ''), 0, 22);
    $desc = substr(utf8_decode($i['elemento']), 0, 45);
    $tipo = $i['tipo_carga'] ? substr(utf8_decode($i['tipo_carga']), 0, 12) : '-';
    $kg = $i['mat_capacidad'] ?? '-';
    $clase = $i['nombre_clase'] ?? '-';
    $fab = ($i['fecha_fabricacion'] ?? '-') . '/' . ($i['vida_util_limite'] ?? '-');
    $uc = $i['mat_fecha_carga'] ? date('m/y', strtotime($i['mat_fecha_carga'])) : '-';
    $ph = $i['mat_fecha_ph'] ? date('m/y', strtotime($i['mat_fecha_ph'])) : '-';
    $estado = substr(utf8_decode($i['nombre_estado'] ?? ''), 0, 15);
    $tec = substr(utf8_decode($i['nombre_tecnico'] ?? ''), 0, 12);
    $resp = substr(utf8_decode($i['nombre_responsable'] ?? ''), 0, 25);
    $remito = $i['archivo_remito'] ? 'SI' : 'NO';

    $pdf->Cell(15,6, $cod, 1);
    $pdf->Cell(25,6, $ubi, 1);
    $pdf->Cell(50,6, $desc, 1);
    $pdf->Cell(15,6, $tipo, 1, 0, 'C');
    $pdf->Cell(10,6, $kg, 1, 0, 'C');
    $pdf->Cell(10,6, $clase, 1, 0, 'C');
    $pdf->Cell(15,6, $fab, 1, 0, 'C');
    $pdf->Cell(12,6, $uc, 1, 0, 'C');
    $pdf->Cell(12,6, $ph, 1, 0, 'C');
    $pdf->Cell(20,6, $estado, 1, 0, 'C');
    $pdf->Cell(15,6, $tec, 1);
    $pdf->Cell(30,6, $resp, 1);
    $pdf->Cell(20,6, $remito, 1, 0, 'C');
    $pdf->Ln();
}

$pdf->Output('I', 'Reporte_Inventario.pdf');
?>