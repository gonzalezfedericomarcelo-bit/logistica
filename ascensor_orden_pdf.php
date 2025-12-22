<?php
// Archivo: ascensor_orden_pdf.php
// OBJETIVO: PDF Profesional (Ajuste Final: Header aireado y Firma superpuesta)
if (ob_get_length()) ob_clean(); 
ob_start();

session_start();
ini_set('display_errors', 0); error_reporting(0);

require('fpdf/fpdf.php');
include 'conexion.php';

// 1. Validar ID
$id_incidencia = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
if (!$id_incidencia) die("ID Inválido");

// 2. Obtener Datos
$sql = "SELECT i.*, 
               a.nombre as ascensor, a.ubicacion, a.nro_serie, 
               e.nombre as empresa, e.telefono as tel_empresa, e.email_contacto,
               u.nombre_completo as usuario, u.email as usuario_email, u.firma_imagen_path as usuario_firma
        FROM ascensor_incidencias i
        JOIN ascensores a ON i.id_ascensor = a.id_ascensor
        LEFT JOIN empresas_mantenimiento e ON i.id_empresa = e.id_empresa
        LEFT JOIN usuarios u ON i.id_usuario_reporta = u.id_usuario
        WHERE i.id_incidencia = ?";
$stmt = $pdo->prepare($sql);
$stmt->execute([$id_incidencia]);
$datos = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$datos) die("Orden no encontrada.");

// --- CONFIGURACIÓN RUTAS ---
$carpeta_relativa = "pdfs_publicos/ascensores/";
$ruta_base = __DIR__ . "/"; 
$ruta_destino_final = $ruta_base . $carpeta_relativa;
if (!file_exists($ruta_destino_final)) { @mkdir($ruta_destino_final, 0777, true); }

$nombre_archivo = "Orden_Ascensor_{$id_incidencia}.pdf";
$archivo_completo_fisico = $ruta_destino_final . $nombre_archivo;

$protocolo = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on') ? "https" : "http";
$dominio = $_SERVER['HTTP_HOST'];
$path_script = dirname($_SERVER['PHP_SELF']); 
$path_script = ($path_script == '/' || $path_script == '\\') ? '' : $path_script;
$url_publica_pdf = "{$protocolo}://{$dominio}{$path_script}/{$carpeta_relativa}{$nombre_archivo}";

// Helper QR
function descargar_qr_temporal($contenido, $id_archivo) {
    $dir = 'uploads/temp_qr/';
    if (!is_dir($dir)) @mkdir($dir, 0777, true);
    $ruta_local = $dir . 'qr_asc_' . $id_archivo . '.png';
    if (file_exists($ruta_local) && (time() - filemtime($ruta_local) < 3600)) return $ruta_local;
    $url_api = "https://api.qrserver.com/v1/create-qr-code/?size=150x150&data=" . urlencode($contenido);
    if (ini_get('allow_url_fopen')) {
        $img = @file_get_contents($url_api);
        if ($img) { file_put_contents($ruta_local, $img); return $ruta_local; }
    }
    return false;
}

class PDF extends FPDF {
    function Header() {
        // Marca de Agua
        $watermark = 'assets/img/logo_watermark_gris.png';
        if(file_exists($watermark)) $this->Image($watermark, 35, 80, 140); 

        // Logos (Y=10, Alto=20 -> Terminan en Y=30)
        $logoIzq = 'assets/img/logo.png';
        $logoDer = 'assets/img/sello_trabajo.png'; 
        if (file_exists($logoIzq)) $this->Image($logoIzq, 10, 10, 20); 
        if (file_exists($logoDer)) $this->Image($logoDer, 180, 10, 20);
        
        // Texto Institucional (Centrado entre logos)
        // Ajustamos Y para que quede alineado visualmente con los logos
        $this->SetY(13); 
        $this->SetFont('Arial','B',12);
        $this->Cell(0, 5, utf8_decode('IOSFA - INSTITUTO DE OBRA SOCIAL DE LAS FUERZAS ARMADAS'), 0, 1, 'C');
        $this->SetFont('Arial','',9);
        $this->Cell(0, 5, utf8_decode('SUBGERENCIA DE LOGÍSTICA - MANTENIMIENTO EDILICIO'), 0, 1, 'C');
        
        // Título Rojo (Bajamos a Y=40 para darle aire respecto a los logos)
        $this->SetY(40); 
        $this->SetFillColor(220, 53, 69);
        $this->SetTextColor(255, 255, 255);
        $this->SetFont('Arial','B',14);
        $this->Cell(0, 8, utf8_decode(' ORDEN DE RECLAMO / INCIDENCIA TÉCNICA '), 0, 1, 'C', true);
        $this->SetTextColor(0, 0, 0);
        $this->Ln(3);
    }

    function Footer() {
        global $id_incidencia, $url_publica_pdf;
        $this->SetY(-30);
        $this->SetDrawColor(150,150,150);
        $this->Line(10, $this->GetY(), 200, $this->GetY());
        $this->Ln(2);

        $ruta_qr = descargar_qr_temporal($url_publica_pdf, $id_incidencia);
        // QR Chico (12 mm)
        if ($ruta_qr && file_exists($ruta_qr)) {
            $this->Image($ruta_qr, 10, $this->GetY(), 12, 12);
        }
        
        $this->SetX(25); 
        $this->SetFont('Arial','B',8);
        $this->Cell(0, 4, utf8_decode('DOCUMENTO GENERADO AUTOMÁTICAMENTE - SISTEMA DE GESTIÓN'), 0, 1, 'L');
        $this->SetX(25);
        $this->SetFont('Arial','',7);
        $this->SetTextColor(80, 80, 80);
        $txt = "ID Orden: #" . str_pad($id_incidencia, 6, '0', STR_PAD_LEFT) . " | Fecha: " . date('d/m/Y H:i');
        $this->Cell(0, 4, utf8_decode($txt), 0, 1, 'L');
        
        $this->SetY(-15);
        $this->Cell(0, 10, utf8_decode('Página '.$this->PageNo().'/{nb}'), 0, 0, 'R');
    }

    // Función para centrar imagen
    function ImageCenter($file, $y, $w_max, $h_max) {
        if (!file_exists($file)) return;
        list($width, $height) = getimagesize($file);
        if ($width == 0 || $height == 0) return;
        
        $ratio = $width / $height;
        $newH = $h_max;
        $newW = $newH * $ratio;
        
        if ($newW > $w_max) {
            $newW = $w_max;
            $newH = $newW / $ratio;
        }
        
        $x = (210 - $newW) / 2;
        $this->Image($file, $x, $y, $newW, $newH);
    }
}

// --- CUERPO DEL PDF ---
$pdf = new PDF();
$pdf->AliasNbPages();
$pdf->AddPage();

// 1. IDENTIFICACIÓN
$pdf->SetFont('Arial','B',10);
$pdf->SetFillColor(240, 240, 240);
$pdf->Cell(0, 6, utf8_decode(' 1. IDENTIFICACIÓN DEL REPORTE'), 1, 1, 'L', true);
$pdf->Ln(1);

$pdf->SetFont('Arial','B',9); $pdf->Cell(30, 6, 'Nro Orden:', 1);
$pdf->SetFont('Arial','',10); $pdf->Cell(65, 6, '#'.str_pad($datos['id_incidencia'], 6, '0', STR_PAD_LEFT), 1);
$pdf->SetFont('Arial','B',9); $pdf->Cell(30, 6, 'Fecha:', 1);
$pdf->SetFont('Arial','',10); $pdf->Cell(0, 6, date('d/m/Y H:i', strtotime($datos['fecha_reporte'])), 1, 1);

$pdf->SetFont('Arial','B',9); $pdf->Cell(30, 6, 'Estado:', 1);
$pdf->SetFont('Arial','B',10); 
if($datos['estado']=='resuelto') $pdf->SetTextColor(0,150,0); else $pdf->SetTextColor(200,0,0);
$pdf->Cell(65, 6, strtoupper($datos['estado']), 1);
$pdf->SetTextColor(0,0,0);
$pdf->SetFont('Arial','B',9); $pdf->Cell(30, 6, 'Prioridad:', 1);
$pdf->SetFont('Arial','B',10); 
if($datos['prioridad']=='alta'||$datos['prioridad']=='emergencia') $pdf->SetTextColor(255,0,0);
$pdf->Cell(0, 6, strtoupper($datos['prioridad']), 1, 1);
$pdf->SetTextColor(0,0,0);
$pdf->Ln(3);

// 2. EQUIPO
$pdf->SetFont('Arial','B',10); 
$pdf->SetFillColor(240, 240, 240);
$pdf->Cell(0, 6, utf8_decode(' 2. EQUIPO AFECTADO'), 1, 1, 'L', true);
$pdf->Ln(1);

$pdf->SetFont('Arial','B',9); $pdf->Cell(30, 6, 'Nombre:', 1);
$pdf->SetFont('Arial','',10); $pdf->Cell(0, 6, utf8_decode($datos['ascensor']), 1, 1);
$pdf->SetFont('Arial','B',9); $pdf->Cell(30, 6, utf8_decode('Ubicación:'), 1);
$pdf->SetFont('Arial','',10); $pdf->Cell(0, 6, utf8_decode($datos['ubicacion']), 1, 1);
$pdf->SetFont('Arial','B',9); $pdf->Cell(30, 6, 'Nro Serie:', 1);
$pdf->SetFont('Arial','',10); $pdf->Cell(0, 6, utf8_decode($datos['nro_serie']), 1, 1);
$pdf->Ln(3);

// 3. DETALLE DE LA FALLA
$pdf->SetFont('Arial','B',10); 
$pdf->Cell(0, 6, utf8_decode(' 3. DETALLE DE LA FALLA'), 1, 1, 'L', true);
$pdf->Ln(1);

// Caja Título
$pdf->SetFont('Arial','B',9);
$pdf->Cell(0, 6, utf8_decode('TÍTULO / PROBLEMA PRINCIPAL:'), 'LTR', 1, 'L'); 
$pdf->SetFont('Arial','',10);
$pdf->MultiCell(0, 6, utf8_decode($datos['titulo']), 'LBR', 'L'); 

$pdf->Ln(1);

// Caja Descripción
$pdf->SetFont('Arial','B',9);
$pdf->Cell(0, 6, utf8_decode('DESCRIPCIÓN DETALLADA:'), 'LTR', 1, 'L');
$pdf->SetFont('Arial','',10);
$pdf->MultiCell(0, 6, utf8_decode($datos['descripcion_problema']), 'LBR', 'L');

$pdf->Ln(3);

// 4. EMPRESA
$pdf->SetFont('Arial','B',10); 
$pdf->Cell(0, 6, utf8_decode(' 4. EMPRESA DE MANTENIMIENTO ASIGNADA'), 1, 1, 'L', true);
$pdf->Ln(1);

$pdf->SetFont('Arial','',9);
$pdf->Cell(95, 6, 'Empresa: '.utf8_decode($datos['empresa']), 1);
$pdf->Cell(95, 6, 'Tel: '.utf8_decode($datos['tel_empresa']), 1, 1);
$pdf->Cell(0, 6, 'Email Notificado: '.utf8_decode($datos['email_contacto']), 1, 1);
$pdf->Ln(3);


// --- 5. CONSTANCIA DE REPORTE ---

if($pdf->GetY() > 220) $pdf->AddPage();

$pdf->SetFont('Arial','B',10);
$pdf->Cell(0, 6, utf8_decode(' 5. CONSTANCIA DE REPORTE'), 1, 1, 'L', true);
$pdf->Ln(5);

$y_inicio_firma = $pdf->GetY();
$espacio_disponible = 255 - $y_inicio_firma;

if ($espacio_disponible < 50) { // Si queda menos de 5cm, hoja nueva
    $pdf->AddPage();
    $y_inicio_firma = $pdf->GetY();
}

// 1. IMAGEN FIRMA (MÁS GRANDE: 60mm alto)
if (!empty($datos['usuario_firma'])) {
    $ruta_firma = 'uploads/firmas/' . $datos['usuario_firma'];
    // 140 ancho, 60 alto para que se imponga
    $pdf->ImageCenter($ruta_firma, $y_inicio_firma, 140, 60);
}

// 2. LÍNEA PUNTEADA (PEGADA A LA FIRMA)
// El secreto: No bajamos tanto el cursor. 
// Si la firma empieza en Y, bajamos Y + 50 (aunque la firma mida 60), 
// así la firma "pisa" la línea o queda justo encima.
$pdf->SetY($y_inicio_firma + 50);

$pdf->SetFont('Arial','',10);
$pdf->Cell(0, 4, '.............................................................', 0, 1, 'C');

// 3. NOMBRE y EMAIL (Centrados)
$pdf->SetFont('Arial','B',11);
$pdf->Cell(0, 5, utf8_decode($datos['usuario']), 0, 1, 'C');

$pdf->SetFont('Arial','',9);
$pdf->Cell(0, 4, utf8_decode($datos['usuario_email']), 0, 1, 'C');

// 4. LEYENDA (Centrada)
$pdf->Ln(1);
$pdf->SetFont('Arial','I',8);
$pdf->SetTextColor(100,100,100);
$pdf->Cell(0, 4, utf8_decode('( Reportado Por / Usuario Solicitante )'), 0, 1, 'C');


// FINALIZAR
$contenido_pdf = $pdf->Output('S'); 
@file_put_contents($archivo_completo_fisico, $contenido_pdf);
header("Location: " . $url_publica_pdf);
exit;
?>