<?php
// Archivo: ascensor_reporte_pdf.php (CORREGIDO)
// Limpiamos cualquier salida previa para evitar error de PDF
if (ob_get_length()) ob_end_clean(); 
require('fpdf/fpdf.php');
require_once 'conexion.php';

$id_incidencia = $_GET['id'] ?? 0;
if ($id_incidencia <= 0) die("ID Inválido");

// Consulta Datos
$sql = "SELECT i.*, a.nombre as nombre_ascensor, a.ubicacion, a.nro_serie, u.nombre_completo as reportado_por 
        FROM ascensor_incidencias i 
        JOIN ascensores a ON i.id_ascensor = a.id_ascensor 
        JOIN usuarios u ON i.id_usuario_reporta = u.id_usuario 
        WHERE i.id_incidencia = ?";
$stmt = $pdo->prepare($sql);
$stmt->execute([$id_incidencia]);
$data = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$data) die("Incidencia no encontrada");

// Consulta Visitas
$visitas = $pdo->prepare("SELECT * FROM ascensor_visitas_tecnicas WHERE id_incidencia = ? ORDER BY fecha_visita ASC");
$visitas->execute([$id_incidencia]);
$lista_visitas = $visitas->fetchAll(PDO::FETCH_ASSOC);

// CLASE PDF ÚNICA PARA EVITAR CONFLICTOS
class PDF_Ascensor extends FPDF {
    function Header() {
        $this->SetFont('Arial', 'B', 14);
        $this->Cell(0, 10, utf8_decode('REPORTE DE SERVICIO - ASCENSORES'), 0, 1, 'C');
        $this->Ln(5);
        
        // Línea separadora
        $this->SetDrawColor(0,0,0);
        $this->Line(10, 25, 200, 25);
        $this->Ln(5);
    }

    function Footer() {
        $this->SetY(-15);
        $this->SetFont('Arial', 'I', 8);
        $this->Cell(0, 10, utf8_decode('Página ') . $this->PageNo() . '/{nb} - Generado el ' . date('d/m/Y H:i'), 0, 0, 'C');
    }
    
    function SectionTitle($title) {
        $this->SetFont('Arial', 'B', 12);
        $this->SetFillColor(240, 240, 240);
        $this->Cell(0, 8, utf8_decode($title), 1, 1, 'L', true);
        $this->Ln(2);
    }
}

// Generación
$pdf = new PDF_Ascensor();
$pdf->AliasNbPages();
$pdf->AddPage();

// SECCIÓN 1: DATOS
$pdf->SectionTitle('1. Datos del Equipo y Reporte');
$pdf->SetFont('Arial', '', 10);
$pdf->Cell(40, 6, utf8_decode('Ascensor:'), 0, 0, 'B'); 
$pdf->Cell(0, 6, utf8_decode($data['nombre_ascensor'] . " - " . $data['ubicacion']), 0, 1);
$pdf->Cell(40, 6, utf8_decode('N° Serie:'), 0, 0, 'B'); 
$pdf->Cell(0, 6, utf8_decode($data['nro_serie']), 0, 1);
$pdf->Cell(40, 6, utf8_decode('Reportado por:'), 0, 0, 'B'); 
$pdf->Cell(0, 6, utf8_decode($data['reportado_por']), 0, 1);
$pdf->Cell(40, 6, utf8_decode('Fecha Reporte:'), 0, 0, 'B'); 
$pdf->Cell(0, 6, date('d/m/Y H:i', strtotime($data['fecha_reporte'])), 0, 1);
$pdf->Ln(2);

$pdf->SetFont('Arial', 'B', 10);
$pdf->Cell(0, 6, 'Problema Reportado:', 0, 1);
$pdf->SetFont('Arial', '', 10);
$pdf->MultiCell(0, 6, utf8_decode($data['descripcion_problema']), 1);
$pdf->Ln(5);

// SECCIÓN 2: ESTADO
$pdf->SectionTitle('2. Estado Actual');
$estado = strtoupper(str_replace('_', ' ', $data['estado']));
$pdf->SetFont('Arial', 'B', 12);
$pdf->Cell(40, 10, 'ESTADO:', 0, 0);
$pdf->SetTextColor($estado=='RESUELTO'?0:255, $estado=='RESUELTO'?128:0, 0);
$pdf->Cell(0, 10, $estado, 0, 1);
$pdf->SetTextColor(0);
$pdf->Ln(5);

// SECCIÓN 3: VISITAS
$pdf->SectionTitle('3. Historial de Visitas Técnicas');
if(empty($lista_visitas)) {
    $pdf->Cell(0, 10, utf8_decode('No hay visitas registradas aún.'), 1, 1, 'C');
} else {
    $pdf->SetFont('Arial', 'B', 9);
    $pdf->Cell(30, 7, 'Fecha', 1);
    $pdf->Cell(50, 7, utf8_decode('Técnico'), 1);
    $pdf->Cell(110, 7, 'Detalle del Trabajo', 1, 1);
    
    $pdf->SetFont('Arial', '', 9);
    foreach($lista_visitas as $v) {
        $pdf->Cell(30, 7, date('d/m/Y', strtotime($v['fecha_visita'])), 1);
        $pdf->Cell(50, 7, utf8_decode(substr($v['tecnico_nombre'],0,25)), 1);
        // MultiCell simulado para descripción corta
        $desc = substr(str_replace("\n", " ", $v['descripcion_trabajo']), 0, 60);
        $pdf->Cell(110, 7, utf8_decode($desc), 1, 1);
    }
}

$pdf->Output('I', 'Reporte_Ascensor.pdf');
?>
