<?php
// Archivo: ascensor_pdf.php (VERSIÓN FINAL - ESTILO IDÉNTICO A TAREAS)
// Limpiamos memoria para evitar error "Some data has already been output"
if (ob_get_length()) ob_end_clean();

require('fpdf/fpdf.php');
require_once 'conexion.php';

// 1. OBTENER DATOS
$id = $_GET['id'] ?? 0;
if ($id <= 0) die("ID Inválido");

// Datos de la Incidencia
$sql = "SELECT i.*, a.nombre as ascensor, a.ubicacion, e.nombre as empresa, e.email_contacto, u.nombre_completo as solicitante
        FROM ascensor_incidencias i 
        JOIN ascensores a ON i.id_ascensor = a.id_ascensor 
        LEFT JOIN empresas_mantenimiento e ON i.id_empresa = e.id_empresa
        LEFT JOIN usuarios u ON i.id_usuario_reporta = u.id_usuario
        WHERE i.id_incidencia = ?";
$stmt = $pdo->prepare($sql);
$stmt->execute([$id]);
$data = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$data) die("Orden no encontrada.");

// Datos de la Visita (Última registrada)
$sql_v = "SELECT * FROM ascensor_visitas_tecnicas WHERE id_incidencia = ? ORDER BY fecha_visita DESC LIMIT 1";
$stmt_v = $pdo->prepare($sql_v);
$stmt_v->execute([$id]);
$visita = $stmt_v->fetch(PDO::FETCH_ASSOC);

// Constantes de Diseño (Copiadas de generar_pedido_pdf.php)
define('USABLE_WIDTH', 180); // Aprox A4 - margenes
define('LEFT_MARGIN', 15);

class PDF extends FPDF {
    // Cabecera con Logos y Marca de Agua
    function Header() {
        // Marca de Agua (Fondo)
        $watermark = 'assets/img/logo_watermark_gris.png';
        if (file_exists($watermark)) {
            $this->Image($watermark, 50, 80, 110, 0, 'PNG'); 
        }

        // Logo Izquierdo
        $logo = 'assets/img/logo.png'; 
        if (file_exists($logo)) $this->Image($logo, 10, 8, 20);

        // Sello Derecho (Duplicado)
        $sello = 'assets/img/sello_duplicado.png';
        if (file_exists($sello)) $this->Image($sello, 170, 8, 30);

        // Títulos Institucionales
        $this->SetFont('Arial', 'B', 10);
        $this->SetXY(35, 10);
        $this->Cell(0, 5, utf8_decode('SUBGERENCIA DE EFECTORES SANITARIOS PROPIOS'), 0, 1, 'L');
        $this->SetX(35);
        $this->Cell(0, 5, utf8_decode('APOYO LOGÍSTICO - MANTENIMIENTO DE ELEVADORES'), 0, 1, 'L');
        $this->Ln(15);
        
        // Título del Documento
        $this->SetFont('Arial', 'B', 16);
        $this->SetFillColor(230, 230, 230);
        $this->Cell(0, 10, utf8_decode('ORDEN DE SERVICIO N° ' . str_pad($GLOBALS['id'], 6, '0', STR_PAD_LEFT)), 1, 1, 'C', true);
        $this->Ln(5);
    }

    // Pie de Página
    function Footer() {
        $this->SetY(-15);
        $this->SetFont('Arial', 'I', 8);
        $this->Cell(0, 10, utf8_decode('Generado el ' . date('d/m/Y H:i') . ' - Página ') . $this->PageNo() . '/{nb}', 0, 0, 'C');
    }

    // Títulos de Sección (Estilo Gris)
    function SectionTitle($label) {
        $this->SetFont('Arial', 'B', 11);
        $this->SetFillColor(230, 230, 230);
        $this->Cell(0, 6, utf8_decode($label), 1, 1, 'L', true);
        $this->Ln(2);
    }

    // Bloque de Firma (Copiado de tu lógica original)
    function DrawSignatureBlock($title, $name, $date, $path, $x, $y, $w, $h) {
        $this->SetXY($x, $y);
        $this->SetFont('Arial', 'B', 8);
        $this->Cell($w, 5, utf8_decode($title), 0, 1, 'L');
        
        // Recuadro
        $this->Rect($x, $y+5, $w, $h-5);
        
        // Imagen de Firma
        if ($path && file_exists($path)) {
            // Ajustamos la imagen para que entre en el recuadro
            $this->Image($path, $x+2, $y+6, $w-4, $h-15); 
        } else {
            $this->SetXY($x, $y + ($h/2));
            $this->SetFont('Arial', 'I', 8);
            $this->Cell($w, 5, '(Sin Firma Digital)', 0, 0, 'C');
        }

        // Nombre y Fecha debajo
        $this->SetXY($x, $y + $h - 10);
        $this->SetFont('Arial', '', 7);
        $this->Cell($w, 4, utf8_decode($name), 0, 1, 'C');
        $this->SetX($x);
        $this->Cell($w, 4, utf8_decode($date), 0, 0, 'C');
    }
}

// --- GENERACIÓN ---
$pdf = new PDF();
$pdf->AliasNbPages();
$pdf->AddPage();
$pdf->SetLeftMargin(LEFT_MARGIN);
$pdf->SetRightMargin(10);

// SECCIÓN 1: DATOS DEL EQUIPO
$pdf->SectionTitle('1. DATOS DE LA SOLICITUD');
$pdf->SetFont('Arial', '', 10);

// Fila 1
$pdf->Cell(30, 7, 'Fecha:', 1);
$pdf->Cell(60, 7, date('d/m/Y H:i', strtotime($data['fecha_reporte'])), 1);
$pdf->Cell(30, 7, 'Prioridad:', 1);
$prioridad = strtoupper($data['prioridad']);
$pdf->SetFont('Arial', 'B', 10);
$pdf->Cell(60, 7, $prioridad, 1, 1);

// Fila 2
$pdf->SetFont('Arial', '', 10);
$pdf->Cell(30, 7, 'Equipo:', 1);
$pdf->Cell(60, 7, utf8_decode($data['ascensor']), 1);
$pdf->Cell(30, 7, utf8_decode('Ubicación:'), 1);
$pdf->Cell(60, 7, utf8_decode($data['ubicacion']), 1, 1);

// Fila 3
$pdf->Cell(30, 7, 'Empresa:', 1);
$pdf->Cell(60, 7, utf8_decode($data['empresa']), 1);
$pdf->Cell(30, 7, 'Solicitante:', 1);
$pdf->Cell(60, 7, utf8_decode($data['solicitante']), 1, 1);

$pdf->Ln(2);
$pdf->SetFont('Arial', 'B', 10);
$pdf->Cell(0, 7, utf8_decode('Descripción del Problema:'), 0, 1);
$pdf->SetFont('Arial', '', 10);
$pdf->MultiCell(0, 6, utf8_decode($data['descripcion_problema']), 1);
$pdf->Ln(5);

// SECCIÓN 2: INTERVENCIÓN TÉCNICA
$pdf->SectionTitle('2. INTERVENCIÓN TÉCNICA');

if ($visita) {
    $pdf->SetFont('Arial', '', 10);
    $pdf->Cell(40, 7, utf8_decode('Fecha de Visita:'), 1);
    $pdf->Cell(140, 7, date('d/m/Y H:i', strtotime($visita['fecha_visita'])), 1, 1);
    
    $pdf->Cell(40, 7, utf8_decode('Técnico:'), 1);
    $pdf->Cell(140, 7, utf8_decode($visita['tecnico_nombre']), 1, 1);
    
    $pdf->Ln(2);
    $pdf->SetFont('Arial', 'B', 10);
    $pdf->Cell(0, 7, utf8_decode('Trabajo Realizado:'), 0, 1);
    $pdf->SetFont('Arial', '', 10);
    $pdf->MultiCell(0, 6, utf8_decode($visita['descripcion_trabajo']), 1);
    
    $pdf->Ln(5);
    
    // --- FIRMAS (Usando la función personalizada) ---
    $pdf->SectionTitle('3. CONFORMIDAD DEL SERVICIO');
    $y = $pdf->GetY() + 2;
    
    // Bloque Firma Técnico (Izquierda)
    $pdf->DrawSignatureBlock(
        'Firma Técnico (' . $data['empresa'] . ')', 
        $visita['tecnico_nombre'], 
        date('d/m/Y', strtotime($visita['fecha_visita'])), 
        $visita['firma_tecnico_path'], 
        LEFT_MARGIN, $y, 80, 40
    );

    // Bloque Firma Receptor (Derecha)
    $pdf->DrawSignatureBlock(
        'Firma Receptor (Clínica)', 
        'Responsable de Turno', // Podríamos buscar el nombre del receptor si quisiéramos
        date('d/m/Y', strtotime($visita['fecha_visita'])), 
        $visita['firma_receptor_path'], 
        LEFT_MARGIN + 90, $y, 80, 40
    );

} else {
    $pdf->SetFont('Arial', 'I', 10);
    $pdf->Cell(0, 15, utf8_decode('Aún no se ha registrado la visita técnica para esta orden.'), 1, 1, 'C');
}

$pdf->Output('I', 'Orden_Ascensor_'.$id.'.pdf');
?>