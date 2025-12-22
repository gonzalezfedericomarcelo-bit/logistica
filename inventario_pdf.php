<?php
// Archivo: inventario_pdf.php (VERSIÓN DEFINITIVA: MEMBRETES INTELIGENTES + LOGOS + SIN DUPLICADOS)
if (ob_get_length()) ob_clean(); 
ob_start();

session_start();
ini_set('display_errors', 0); error_reporting(0);

require('fpdf/fpdf.php');
include 'conexion.php';
include 'funciones_permisos.php';

// --- CONFIGURACIÓN ---
$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if ($id <= 0) die("ID inválido.");

// Rutas de guardado
$carpeta_relativa = "pdfs_publicos/inventario/";
$ruta_base = __DIR__ . "/"; 
$ruta_destino_final = $ruta_base . $carpeta_relativa;

if (!file_exists($ruta_destino_final)) { @mkdir($ruta_destino_final, 0777, true); }

$nombre_archivo = "Ficha_Patrimonial_{$id}.pdf";
$archivo_completo_fisico = $ruta_destino_final . $nombre_archivo;

// URL QR
$protocolo = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on') ? "https" : "http";
$dominio = $_SERVER['HTTP_HOST'];
$path_script = dirname($_SERVER['PHP_SELF']); 
$path_script = ($path_script == '/' || $path_script == '\\') ? '' : $path_script;
$url_publica_pdf = "{$protocolo}://{$dominio}{$path_script}/{$carpeta_relativa}{$nombre_archivo}";

// --- DATOS DEL BIEN ---
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

    // --- LÓGICA DE MEMBRETE INTELIGENTE ---
    // 1. Extraemos el año de creación del bien
    $anio_creacion = date('Y', strtotime($bien['fecha_creacion']));
    
    // 2. Buscamos si existe texto para ESE año
    $stmtM = $pdo->prepare("SELECT texto FROM inventario_config_membretes WHERE anio = ?");
    $stmtM->execute([$anio_creacion]);
    $texto_membrete = $stmtM->fetchColumn();

    // 3. Si no existe, usamos un genérico o el del año actual como fallback
    if(!$texto_membrete) {
        $texto_membrete = '"' . $anio_creacion . ' - AÑO DE LA DEFENSA NACIONAL"'; // Fallback por defecto
    }

    // Datos Dinámicos
    $marca = '-'; $modelo = '-'; $serie_fabrica = '-'; $tiene_datos_extra = false;
    $sqlDyn = "SELECT c.etiqueta, v.valor FROM inventario_valores_dinamicos v 
               JOIN inventario_campos_dinamicos c ON v.id_campo = c.id_campo 
               WHERE v.id_cargo = ?";
    $stmtD = $pdo->prepare($sqlDyn);
    $stmtD->execute([$id]);
    $resDyn = $stmtD->fetchAll(PDO::FETCH_ASSOC);
    if (count($resDyn) > 0) $tiene_datos_extra = true;
    
    foreach($resDyn as $d) {
        $lbl = strtoupper($d['etiqueta']);
        $val = trim($d['valor']) ?: '-';
        if(strpos($lbl, 'MARCA')!==false) $marca=$val;
        elseif(strpos($lbl, 'MODELO')!==false) $modelo=$val;
        elseif(strpos($lbl, 'SERIE')!==false || strpos($lbl, 'FABRICA')!==false) $serie_fabrica=$val;
    }

    // Firmas
    $relevador_nombre = "Relevador Sistema"; $relevador_firma = null;
    if (!empty($bien['id_usuario_relevador'])) {
        $stmtRel = $pdo->prepare("SELECT nombre_completo, grado, firma_imagen_path FROM usuarios WHERE id_usuario = ?");
        $stmtRel->execute([$bien['id_usuario_relevador']]);
        $relData = $stmtRel->fetch(PDO::FETCH_ASSOC);
        if ($relData) {
            $relevador_nombre = ($relData['grado'] ? $relData['grado'] . ' ' : '') . $relData['nombre_completo'];
            $relevador_firma = $relData['firma_imagen_path'];
        }
    }
    $patrimonial_nombre = "Enc. Cargo Patrimonial"; $patrimonial_firma = null;
    $stmtCP = $pdo->prepare("SELECT nombre_completo, grado, firma_imagen_path FROM usuarios WHERE rol = 'cargopatrimonial' AND activo = 1 LIMIT 1");
    $stmtCP->execute();
    $cpData = $stmtCP->fetch(PDO::FETCH_ASSOC);
    if ($cpData) {
        $patrimonial_nombre = ($cpData['grado'] ? $cpData['grado'] . ' ' : '') . $cpData['nombre_completo'];
        $patrimonial_firma = $cpData['firma_imagen_path'];
    }

} catch (Exception $e) { die("Error DB: " . $e->getMessage()); }

// --- HELPER QR ---
function descargar_qr_temporal($contenido, $id_archivo) {
    $dir = 'uploads/temp_qr/';
    if (!is_dir($dir)) @mkdir($dir, 0777, true);
    $ruta_local = $dir . 'qr_public_' . $id_archivo . '.png';
    if (file_exists($ruta_local) && (time() - filemtime($ruta_local) < 3600)) return $ruta_local;
    $url_api = "https://api.qrserver.com/v1/create-qr-code/?size=150x150&data=" . urlencode($contenido);
    if (ini_get('allow_url_fopen')) {
        $img = @file_get_contents($url_api);
        if ($img) { file_put_contents($ruta_local, $img); return $ruta_local; }
    }
    return false;
}

// --- CLASE PDF ---
class PDF extends FPDF {
    function Header() {
        global $texto_membrete; // Accedemos a la variable global calculada arriba

        // 1. MARCA DE AGUA
        $watermark = __DIR__ . '/assets/img/logo_watermark_gris.png';
        if(!file_exists($watermark)) $watermark = 'assets/img/logo_watermark_gris.png';
        if(file_exists($watermark)) {
            $this->Image($watermark, 35, 80, 140); 
        }

        // 2. LOGOS
        $logoIzq = __DIR__ . '/assets/img/logo.png';
        if (!file_exists($logoIzq)) $logoIzq = 'assets/img/logo.png'; 
        $logoDer = __DIR__ . '/assets/img/sello_trabajo.png';
        if (!file_exists($logoDer)) $logoDer = 'assets/img/sello_trabajo.png'; 

        if (file_exists($logoIzq)) $this->Image($logoIzq, 10, 10, 20); 
        if (file_exists($logoDer)) $this->Image($logoDer, 180, 10, 20);
        
        // 3. TÍTULO
        $this->SetY(15); 
        $this->SetFont('Arial','B',12);
        $this->SetX(30); 
        $this->Cell(150, 5, utf8_decode('IOSFA - INSTITUTO DE OBRA SOCIAL DE LAS FUERZAS ARMADAS'), 0, 1, 'C');
        
        $this->SetFont('Arial','',9);
        $this->SetX(30);
        // AQUÍ USAMOS EL TEXTO DINÁMICO
        $this->Cell(150, 5, utf8_decode($texto_membrete), 0, 1, 'C');
        
        // 4. FICHA
        $this->SetY(35);
        $this->SetFillColor(40, 40, 40);
        $this->SetTextColor(255, 255, 255);
        $this->SetFont('Arial','B',14);
        $this->Cell(0, 10, utf8_decode(' FICHA TÉCNICA DE BIEN PATRIMONIAL '), 0, 1, 'C', true);
        $this->SetTextColor(0, 0, 0);
        $this->Ln(5);
    }

    function Footer() {
        global $id, $url_publica_pdf;
        $this->SetY(-35);
        $this->SetDrawColor(150,150,150);
        $this->Line(10, $this->GetY(), 200, $this->GetY());
        $this->Ln(2);

        $ruta_qr = descargar_qr_temporal($url_publica_pdf, $id);
        if ($ruta_qr && file_exists($ruta_qr)) {
            $this->Image($ruta_qr, 10, $this->GetY(), 25, 25);
        }
        
        $this->SetX(40);
        $this->SetFont('Arial','B',8);
        $this->Cell(0, 4, utf8_decode('SISTEMA DE LOGÍSTICA - DOCUMENTO OFICIAL PÚBLICO'), 0, 1, 'L');
        $this->SetX(40);
        $this->SetFont('Arial','',7);
        $this->SetTextColor(80, 80, 80);
        $txt = "ID Único: " . str_pad($id, 6, '0', STR_PAD_LEFT) . "\n" .
               "Fecha Emisión: " . date('d/m/Y H:i:s') . "\n" .
               "Validación: El QR permite descargar esta ficha sin credenciales.";
        $this->MultiCell(150, 3.5, utf8_decode($txt), 0, 'L');
        $this->SetY(-15);
        $this->Cell(0, 10, utf8_decode('Página '.$this->PageNo().'/{nb}'), 0, 0, 'R');
    }

    function ImageFit($file, $x, $y, $w, $h) {
        if (!file_exists($file)) return;
        list($width, $height) = getimagesize($file);
        if ($width == 0 || $height == 0) return;
        $ratioImg = $width / $height;
        $ratioBox = $w / $h;
        if ($ratioImg > $ratioBox) {
            $newW = $w; $newH = $w / $ratioImg; $newY = $y + ($h - $newH) / 2;
            $this->Image($file, $x, $newY, $newW, $newH);
        } else {
            $newH = $h; $newW = $h * $ratioImg; $newX = $x + ($w - $newW) / 2;
            $this->Image($file, $newX, $y, $newW, $newH);
        }
    }
}

// --- GENERAR PDF ---
$pdf = new PDF();
$pdf->AliasNbPages();
$pdf->AddPage();
$pdf->SetAutoPageBreak(true, 40);

$sec_num = 1;

// SECCIÓN 1
$pdf->SetFillColor(220,220,220);
$pdf->SetFont('Arial','B',10);
$pdf->Cell(0, 8, utf8_decode("  $sec_num. DATOS DE IDENTIFICACIÓN"), 1, 1, 'L', true);
$sec_num++;

$pdf->SetFont('Arial','',9);
$pdf->SetFont('Arial','B',9); $pdf->Cell(30, 8, 'Elemento:', 1, 0, 'L', false);
$pdf->SetFont('Arial','B',11); $pdf->Cell(110, 8, utf8_decode($bien['elemento']), 1, 0, 'L', false);
$pdf->SetFont('Arial','B',9); $pdf->Cell(20, 8, 'Estado:', 1, 0, 'C', true);
$pdf->Cell(0, 8, utf8_decode($bien['nombre_estado']), 1, 1, 'C', false);

$pdf->SetFont('Arial','B',9); $pdf->Cell(30, 8, 'N. IOSFA:', 1, 0, 'L', false);
$pdf->SetFont('Arial','',10); $pdf->Cell(65, 8, utf8_decode($bien['n_iosfa'] ?: '-'), 1, 0, 'L', false);
$pdf->SetFont('Arial','B',9); $pdf->Cell(30, 8, 'Patrimonial:', 1, 0, 'L', false);
$pdf->SetFont('Arial','',10); $pdf->Cell(0, 8, utf8_decode($bien['codigo_patrimonial'] ?: '-'), 1, 1, 'L', false);

$pdf->SetFont('Arial','B',9); $pdf->Cell(30, 8, utf8_decode('Ubicación:'), 1, 0, 'L', false);
$pdf->SetFont('Arial','',9); $pdf->Cell(0, 8, utf8_decode($bien['nombre_destino'] . ($bien['servicio_ubicacion'] ? " - " . $bien['servicio_ubicacion'] : "")), 1, 1, 'L', false);

$pdf->Ln(5);

// SECCIÓN 2
if ($tiene_datos_extra && file_exists('inventario_pdf_cuerpo_dinamico.php')) {
    include 'inventario_pdf_cuerpo_dinamico.php';
    $sec_num++; 
    $pdf->Ln(5);
}

// SECCIÓN FIRMAS
if ($pdf->GetY() > 210) $pdf->AddPage();

$pdf->SetFillColor(220,220,220);
$pdf->SetFont('Arial','B',10);
$pdf->Cell(0, 8, utf8_decode("  $sec_num. CONFORMIDAD Y RESPONSABILIDADES"), 1, 1, 'L', true);
$pdf->Ln(5);

$y_firmas = $pdf->GetY();
$ancho_firma = 85; $alto_firma = 30; $gap = 10;

// FILA 1
$pdf->SetXY(15, $y_firmas); $pdf->Cell($ancho_firma, $alto_firma, '', 1); 
if (!empty($bien['firma_responsable_path'])) $pdf->ImageFit($bien['firma_responsable_path'], 15, $y_firmas, $ancho_firma, $alto_firma);

$pdf->SetXY(15, $y_firmas + $alto_firma); $pdf->SetFillColor(240,240,240); $pdf->SetFont('Arial','B',7);
$pdf->Cell($ancho_firma, 5, utf8_decode('RESPONSABLE DEL BIEN - CONFORME'), 1, 0, 'C', true);
$pdf->Ln(5); $pdf->SetX(15); $pdf->SetFont('Arial','',7);
$pdf->Cell($ancho_firma, 5, utf8_decode($bien['nombre_responsable']), 1, 0, 'C');

$pdf->SetXY(15 + $ancho_firma + $gap, $y_firmas); $pdf->Cell($ancho_firma, $alto_firma, '', 1); 
if ($relevador_firma) $pdf->ImageFit('uploads/firmas/'.$relevador_firma, 15 + $ancho_firma + $gap, $y_firmas, $ancho_firma, $alto_firma);

$pdf->SetXY(15 + $ancho_firma + $gap, $y_firmas + $alto_firma); $pdf->SetFont('Arial','B',7);
$pdf->Cell($ancho_firma, 5, utf8_decode('RELEVADOR (SISTEMA)'), 1, 0, 'C', true);
$pdf->Ln(5); $pdf->SetX(15 + $ancho_firma + $gap); $pdf->SetFont('Arial','',7);
$pdf->Cell($ancho_firma, 5, utf8_decode($relevador_nombre), 1, 0, 'C');

// FILA 2
$y_firmas_2 = $pdf->GetY() + 8; 
$pdf->SetXY(15, $y_firmas_2); $pdf->Cell($ancho_firma, $alto_firma, '', 1);
if (!empty($bien['firma_jefe_path'])) $pdf->ImageFit($bien['firma_jefe_path'], 15, $y_firmas_2, $ancho_firma, $alto_firma);

$pdf->SetXY(15, $y_firmas_2 + $alto_firma); $pdf->SetFont('Arial','B',7);
$pdf->Cell($ancho_firma, 5, utf8_decode('JEFE DE SERVICIO - CONFORME'), 1, 0, 'C', true);
$pdf->Ln(5); $pdf->SetX(15); $pdf->SetFont('Arial','',7);
$pdf->Cell($ancho_firma, 5, utf8_decode($bien['nombre_jefe_servicio']), 1, 0, 'C');

$pdf->SetXY(15 + $ancho_firma + $gap, $y_firmas_2); $pdf->Cell($ancho_firma, $alto_firma, '', 1);
if ($patrimonial_firma) $pdf->ImageFit('uploads/firmas/'.$patrimonial_firma, 15 + $ancho_firma + $gap, $y_firmas_2, $ancho_firma, $alto_firma);

$pdf->SetXY(15 + $ancho_firma + $gap, $y_firmas_2 + $alto_firma); $pdf->SetFont('Arial','B',7);
$pdf->Cell($ancho_firma, 5, utf8_decode('ENC. CARGO PATRIMONIAL'), 1, 0, 'C', true);
$pdf->Ln(5); $pdf->SetX(15 + $ancho_firma + $gap); $pdf->SetFont('Arial','',7);
$pdf->Cell($ancho_firma, 5, utf8_decode($patrimonial_nombre), 1, 0, 'C');

// FINAL
$contenido_pdf = $pdf->Output('S'); 
@file_put_contents($archivo_completo_fisico, $contenido_pdf);
header('Content-Type: application/pdf');
header('Content-Disposition: inline; filename="' . $nombre_archivo . '"');
echo $contenido_pdf;
?>