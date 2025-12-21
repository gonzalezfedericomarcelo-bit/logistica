<?php
// Archivo: inventario_pdf.php (CORREGIDO: id_token)
if (ob_get_length()) ob_clean(); // CRÍTICO: Limpia basura para evitar Error 500

session_start();
ini_set('display_errors', 0); error_reporting(0); // Errores apagados para no romper el PDF

require('fpdf/fpdf.php');
include 'conexion.php';
include 'funciones_permisos.php';

// VALIDACIÓN DE ACCESO
$acceso_permitido = false;

if (isset($_SESSION['usuario_id'])) {
    $acceso_permitido = true;
} 
elseif (isset($_GET['token'])) {
    $token = $_GET['token'];
    // --- AQUÍ ESTABA EL ERROR: Cambiado id_transferencia por id_token ---
    $stmtT = $pdo->prepare("SELECT id_token FROM inventario_transferencias_pendientes WHERE token_hash = ?");
    $stmtT->execute([$token]);
    if ($stmtT->fetch()) {
        $acceso_permitido = true;
    }
}

if (!$acceso_permitido) die("Acceso denegado.");

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

// OBTENER DATOS DEL BIEN
try {
    $sql = "SELECT i.*, e.nombre as nombre_estado, d.nombre as nombre_destino, t.nombre as tipo_bien_dinamico
            FROM inventario_cargos i
            LEFT JOIN inventario_estados e ON i.id_estado_fk = e.id_estado
            LEFT JOIN destinos_internos d ON i.destino_principal = d.id_destino
            LEFT JOIN inventario_tipos_bien t ON i.id_tipo_bien = t.id_tipo_bien
            WHERE i.id_cargo = ?";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$id]);
    $bien = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$bien) die("El bien no existe.");

    // Datos Dinámicos
    $marca = '-'; $modelo = '-'; $serie_fabrica = '-'; $otros_datos = [];
    $sqlDyn = "SELECT c.etiqueta, v.valor FROM inventario_valores_dinamicos v 
               JOIN inventario_campos_dinamicos c ON v.id_campo = c.id_campo 
               WHERE v.id_cargo = ?";
    $stmtD = $pdo->prepare($sqlDyn);
    $stmtD->execute([$id]);
    foreach($stmtD->fetchAll(PDO::FETCH_ASSOC) as $d) {
        $lbl = strtoupper($d['etiqueta']);
        $val = trim($d['valor']) ?: '-';
        if(strpos($lbl, 'MARCA')!==false) $marca=$val;
        elseif(strpos($lbl, 'MODELO')!==false) $modelo=$val;
        elseif(strpos($lbl, 'SERIE')!==false || strpos($lbl, 'FABRICA')!==false) $serie_fabrica=$val;
        else $otros_datos[$d['etiqueta']] = $val;
    }

} catch (Exception $e) { die("Error DB"); }

// GENERACIÓN PDF
class PDF extends FPDF {
    function Header() {
        $logo = 'assets/img/iosfa.png';
        if(file_exists($logo)) { $this->Image($logo, 12, 10, 22); $this->Image($logo, 176, 10, 22); }
        $this->SetFont('Arial','B',11);
        $this->Cell(0,5, utf8_decode('IOSFA - INSTITUTO DE OBRA SOCIAL DE LAS FUERZAS ARMADAS'),0,1,'C');
        $this->SetFont('Arial','',9);
        $this->Cell(0,5, utf8_decode('"2025 - AÑO DE LA RECONSTRUCCIÓN DE LA NACIÓN ARGENTINA"'),0,1,'C');
        $this->Ln(10);
        $this->SetFillColor(40, 40, 40);
        $this->SetTextColor(255, 255, 255);
        $this->SetFont('Arial','B',12);
        $this->Cell(0, 8, utf8_decode('ACTA DE RECEPCIÓN DEFINITIVA DE BIEN'), 1, 1, 'C', true);
        $this->SetTextColor(0, 0, 0);
        $this->Ln(3);
    }
    function Footer() {
        $this->SetY(-30);
        $this->SetDrawColor(150,150,150); $this->Line(10,$this->GetY(),200,$this->GetY());
        $this->SetFont('Arial','',7);
        $this->SetXY(10, $this->GetY()+2);
        $this->MultiCell(0, 3, utf8_decode("Generado por Sistema Logística.\nID: ".uniqid()." | ".date('d/m/Y H:i')), 0, 'C');
    }
}

$pdf = new PDF();
$pdf->AliasNbPages();
$pdf->AddPage();
$pdf->SetAutoPageBreak(true, 30);
$h_row = 6;

// CUERPO
$pdf->SetFillColor(230,230,230);
$pdf->SetFont('Arial','B',9);
$pdf->Cell(0, 6, utf8_decode('1. DATOS DE IDENTIFICACIÓN'), 1, 1, 'L', true);

$pdf->SetFont('Arial','B',8); $pdf->Cell(25, $h_row, 'Elemento:', 1);
$pdf->SetFont('Arial','',8); $pdf->Cell(110, $h_row, utf8_decode($bien['elemento']), 1);
$pdf->SetFont('Arial','B',8); $pdf->Cell(20, $h_row, 'Estado:', 1);
$pdf->SetFont('Arial','',8); $pdf->Cell(0, $h_row, utf8_decode($bien['nombre_estado']), 1, 1, 'C');

$pdf->SetFont('Arial','B',8); $pdf->Cell(25, $h_row, utf8_decode('N° Serie:'), 1);
$pdf->SetFont('Arial','',8); $pdf->Cell(40, $h_row, utf8_decode($serie_fabrica), 1, 0, 'C');
$pdf->SetFont('Arial','B',8); $pdf->Cell(25, $h_row, 'Patrimonial:', 1);
$pdf->SetFont('Arial','',8); $pdf->Cell(35, $h_row, utf8_decode($bien['codigo_patrimonial'] ?: '-'), 1, 0, 'C');
$pdf->SetFont('Arial','B',8); $pdf->Cell(20, $h_row, 'IOSFA:', 1);
$pdf->SetFont('Arial','',8); $pdf->Cell(0, $h_row, utf8_decode($bien['n_iosfa'] ?: '-'), 1, 1, 'C');

$ubi = $bien['nombre_destino'] . ($bien['servicio_ubicacion'] ? " - " . $bien['servicio_ubicacion'] : "");
$pdf->SetFont('Arial','B',8); $pdf->Cell(25, $h_row, utf8_decode('Ubicación:'), 1);
$pdf->SetFont('Arial','',8); $pdf->Cell(0, $h_row, utf8_decode($ubi), 1, 1);

$pdf->Ln(3);
$pdf->SetFont('Arial','B',9);
$pdf->Cell(0, 6, utf8_decode('2. ESPECIFICACIONES Y FIRMAS'), 1, 1, 'L', true);

// Marca y Modelo
$pdf->SetFont('Arial','B',8); $pdf->Cell(25, $h_row, 'Marca:', 1);
$pdf->SetFont('Arial','',8); $pdf->Cell(65, $h_row, utf8_decode($marca), 1);
$pdf->SetFont('Arial','B',8); $pdf->Cell(25, $h_row, 'Modelo:', 1);
$pdf->SetFont('Arial','',8); $pdf->Cell(0, $h_row, utf8_decode($modelo), 1, 1);

// Firmas
$pdf->Ln(20);
$y = $pdf->GetY();
if(!empty($bien['firma_responsable_path']) && file_exists($bien['firma_responsable_path'])) $pdf->Image($bien['firma_responsable_path'], 20, $y-15, 30);
if(!empty($bien['firma_jefe_path']) && file_exists($bien['firma_jefe_path'])) $pdf->Image($bien['firma_jefe_path'], 120, $y-15, 30);

$pdf->SetXY(12, $y);
$pdf->SetFont('Arial','B',7);
$pdf->Cell(45, 4, 'Responsable', 'T', 0, 'C');
$pdf->Cell(48, 4, '', 0);
$pdf->Cell(45, 4, 'Jefe Servicio', 'T', 0, 'C');

$pdf->Output('I', 'Ficha_'.$id.'.pdf');
?>