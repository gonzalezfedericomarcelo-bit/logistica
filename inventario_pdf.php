<?php
// Archivo: inventario_pdf.php (Versión Estandarizada Estilo Pedidos + QR Público)
// Adaptado para guardar en pdfs_publicos/inventario/ y generar QR con URL pública.

// --- MANEJO DE ERRORES ---
error_reporting(E_ALL);
ini_set('display_errors', 0); 
ini_set('log_errors', 1);

// --- CARGA LIBRERÍAS ---
require('fpdf/fpdf.php');

// Verificación de librería QR (Igual que en pedidos)
$phpqrcode_loaded = false;
$phpqrcode_path = __DIR__ . '/phpqrcode/qrlib.php';
if (file_exists($phpqrcode_path)) {
    include_once($phpqrcode_path);
    $phpqrcode_loaded = true;
} else {
    error_log("Error CRÍTICO: No se encontró la librería QR en " . $phpqrcode_path);
}

// --- CONEXIÓN A BASE DE DATOS ---
include 'conexion.php';

// --- VALIDACIÓN DE ID ---
if (!isset($_GET['id'])) die("Falta ID");
$id_cargo = (int)$_GET['id'];

// Consultar datos del inventario (Adaptado a tu estructura)
$stmt = $pdo->prepare("SELECT i.*, u.nombre_completo as nombre_relevador, u.firma_imagen_path as firma_relevador 
                       FROM inventario_cargos i 
                       JOIN usuarios u ON i.id_usuario_relevador = u.id_usuario 
                       WHERE i.id_cargo = :id");
$stmt->execute([':id' => $id_cargo]);
$datos = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$datos) die("Documento no encontrado");

// --- CONFIGURACIÓN DE RUTAS Y DIRECTORIOS ---
define('USABLE_WIDTH', 210 - 20 - 10); // A4 ancho - margen izq - margen der
define('LEFT_MARGIN', 20);

// 1. Definir Directorio Público
$public_base_dir = 'pdfs_publicos';
$inventory_subdir = 'inventario';
$full_save_dir = __DIR__ . DIRECTORY_SEPARATOR . $public_base_dir . DIRECTORY_SEPARATOR . $inventory_subdir;

// 2. Crear directorios si no existen
if (!is_dir($full_save_dir)) {
    if (!@mkdir($full_save_dir, 0775, true)) {
        error_log("Error: No se pudo crear el directorio de inventario: " . $full_save_dir);
    }
}

// 3. Definir Nombre de Archivo y Ruta
$pdf_filename = 'Constancia_Inventario_' . $datos['codigo_inventario'] . '_' . $id_cargo . '.pdf';
// Limpiamos caracteres raros del nombre por seguridad
$pdf_filename = preg_replace('/[^A-Za-z0-9_\-\.]/', '_', $pdf_filename);
$pdf_save_path = $full_save_dir . DIRECTORY_SEPARATOR . $pdf_filename;

// 4. Construir URL PÚBLICA (Para el QR)
$protocol = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? "https" : "http");
$host = $_SERVER['HTTP_HOST'];
// Detectar subcarpeta si el script no está en la raíz
$script_dir = rtrim(dirname($_SERVER['PHP_SELF']), '/\\');
// Como inventario_pdf.php suele estar en la misma carpeta base que pdfs_publicos, ajustamos:
$baseUrl = $protocol . "://" . $host . $script_dir; 
$urlParaPDFPublico = $baseUrl . '/' . $public_base_dir . '/' . $inventory_subdir . '/' . rawurlencode($pdf_filename);

// --- GENERACIÓN DEL QR ---
$qr_code_file_path = null;
if ($phpqrcode_loaded) {
    try {
        $temp_dir = sys_get_temp_dir();
        $qr_filename_temp = 'qr_inv_' . $id_cargo . '_' . time() . '.png';
        $qr_code_file_path = $temp_dir . DIRECTORY_SEPARATOR . $qr_filename_temp;

        // Generar QR apuntando a la URL del PDF PÚBLICO
        QRcode::png($urlParaPDFPublico, $qr_code_file_path, QR_ECLEVEL_L, 4, 1);
    } catch (Exception $e) {
        error_log("Error generando QR: " . $e->getMessage());
    }
}

// --- CLASE PDF (IDÉNTICA A PEDIDOS) ---
class PDF extends FPDF {
    public $qrFilePath = null;
    
    function Header() {
        // 1. MARCA DE AGUA
        $watermarkPath = 'assets/img/logo_watermark_gris.png';
        if (file_exists($watermarkPath)) {
            $pageW = $this->GetPageWidth();
            $pageH = $this->GetPageHeight();
            $imgW = 90; 
            $imgX = ($pageW - $imgW) / 2;
            $imgY = ($pageH / 2) - ($imgW / 2) - 10; 
            $this->Image($watermarkPath, $imgX, $imgY, $imgW, 0, 'PNG');
        }

        // 2. LOGOS Y MEMBRETES
        $sello_margin_top = 5;
        // Logo Izquierdo (Opcional, si existe sgalp.png o sello_duplicado según tu preferencia)
        // Usaremos la estructura de Pedidos: Sello a la izquierda, Membrete texto
        // Si tienes logo institucional:
        if(file_exists('assets/img/sgalp.png')) {
             $this->Image('assets/img/sgalp.png', 10, 5, 25);
        }

        // Títulos
        $this->SetY(12);
        $this->SetX(40); // Mover un poco a la derecha para no pisar el logo
        $this->SetFont('Arial', 'B', 10);
        $this->Cell(0, 5, utf8_decode('SUBGERENCIA DE EFECTORES SANITARIOS PROPIOS'), 0, 1, 'L');
        $this->SetX(40);
        $this->SetFont('Arial', '', 10); 
        $this->Cell(0, 5, utf8_decode('APOYO LOGÍSTICO - GESTIÓN DE INVENTARIO'), 0, 1, 'L');
        
        $this->Ln(8);
        $this->SetDrawColor(0,0,0);
        $this->Line(LEFT_MARGIN, $this->GetY(), 200, $this->GetY());
        $this->Ln(5);
    }

    function SectionTitle($title) {
        $this->SetFont('Arial', 'B', 10); 
        $this->SetFillColor(230, 230, 230); 
        $this->Cell(USABLE_WIDTH, 6, utf8_decode($title), 1, 1, 'L', true); 
        $this->SetFont('Arial', '', 9);
    }

    function DrawSignatureBlock($title, $name, $date_event, $signature_path_relative, $x, $y, $w, $h) {
        // Guardar posición
        $current_x_sig = $this->GetX(); 
        $current_y_sig = $this->GetY(); 
        
        $this->SetXY($x, $y); 
        $this->SetFont('Arial', 'B', 8); 
        $this->Cell($w, 4, utf8_decode($title), 0, 1, 'L');
        
        $y_content_start = $this->GetY(); 
        $content_height = $h - 6; 
        $signature_area_height = $content_height * 0.85; 
        $name_area_height = $content_height * 0.35;
        
        // Cargar Firma
        $firma_path_completa = null;
        if (!empty($signature_path_relative)) {
            // Manejar rutas relativas o absolutas
            if (file_exists($signature_path_relative)) {
                $firma_path_completa = $signature_path_relative;
            } elseif (file_exists('uploads/firmas/' . $signature_path_relative)) {
                $firma_path_completa = 'uploads/firmas/' . $signature_path_relative;
            }
        }
        
        if ($firma_path_completa) { 
            try { 
                list($img_w, $img_h) = @getimagesize($firma_path_completa); 
                if ($img_w && $img_h) { 
                    $aspect_ratio = $img_w / $img_h; 
                    $max_img_h = $signature_area_height - 1; 
                    $max_img_w = $w - 2; 
                    
                    $new_w = $max_img_w; 
                    $new_h = $new_w / $aspect_ratio; 
                    
                    if ($new_h > $max_img_h) { 
                        $new_h = $max_img_h; 
                        $new_w = $new_h * $aspect_ratio; 
                    } 
                    
                    $img_draw_x = $x + ($w - $new_w) / 2; 
                    $img_draw_y = $y_content_start + 1; 
                    $this->Image($firma_path_completa, $img_draw_x, $img_draw_y, $new_w, $new_h); 
                } 
            } catch (Exception $e) { /* Ignorar error imagen */ } 
        } else {
             $this->SetXY($x, $y_content_start + 5);
             $this->SetFont('Arial', 'I', 7);
             $this->Cell($w, 4, '(Sin Firma Digital)', 0, 1, 'C');
        }

        // Fecha (si aplica)
        /*
        if ($date_event) {
            $this->SetXY($x + $w - 25, $y);
            $this->SetFont('Arial', '', 7);
            $this->Cell(25, 4, date('d/m/Y', strtotime($date_event)), 0, 0, 'R');
        }
        */

        // Nombre y Cargo
        $y_name_start = $y + $h - $name_area_height;
        $this->SetXY($x, $y_name_start); 
        $this->SetFont('Arial', '', 8); 
        $this->Cell($w, $name_area_height, utf8_decode($name), 'T', 0, 'C'); 
        
        // Restaurar
        $this->SetXY($current_x_sig, $current_y_sig);
    }

    function Footer() {
        $this->SetY(-25); 
        $qr_size = 20; 
        $text_start_x = LEFT_MARGIN;
        
        // Dibujar QR
        if ($this->qrFilePath && file_exists($this->qrFilePath)) {
            $qr_x = LEFT_MARGIN; 
            $qr_y = $this->GetY() - 2;
            $this->Image($this->qrFilePath, $qr_x, $qr_y, $qr_size);
            $text_start_x = $qr_x + $qr_size + 5;
        }

        // Texto Footer
        $this->SetX($text_start_x);
        $this->SetFont('Arial','I',8);
        $this->Cell(0, 4, utf8_decode('Documento oficial - Sistema de Gestión Logística'), 0, 1, 'L');
        
        $this->SetX($text_start_x);
        $this->Cell(0, 4, utf8_decode('Escanee el código QR para validar la autenticidad de este documento.'), 0, 1, 'L');
        
        $this->SetX($text_start_x);
        $this->Cell(0, 4, utf8_decode('Generado el: ' . date('d/m/Y H:i:s')), 0, 1, 'L');

        // Paginación
        $this->SetY(-15);
        $this->SetX(-30);
        $this->Cell(0, 10, utf8_decode('Página ').$this->PageNo().'/{nb}', 0, 0, 'C');
    }
}

// --- CREACIÓN DEL PDF ---
$pdf = new PDF('P', 'mm', 'A4');
if ($qr_code_file_path) { $pdf->qrFilePath = $qr_code_file_path; }

$pdf->SetLeftMargin(LEFT_MARGIN);
$pdf->SetRightMargin(10);
$pdf->AliasNbPages();
$pdf->AddPage();
$pdf->SetAutoPageBreak(true, 25);

// 1. TÍTULO PRINCIPAL
$pdf->SetFont('Arial', 'B', 16);
$pdf->Cell(0, 10, utf8_decode('CONSTANCIA DE CARGO PATRIMONIAL'), 0, 1, 'C');
$pdf->Ln(2);

// 2. DATOS GENERALES (Estilo Pedidos)
$pdf->SetFont('Arial', 'B', 10);
$pdf->Cell(30, 6, utf8_decode('Fecha Alta:'), 0, 0, 'L');
$pdf->SetFont('Arial', '', 10);
$pdf->Cell(60, 6, date('d/m/Y H:i', strtotime($datos['fecha_creacion'])), 0, 0, 'L');

$pdf->SetFont('Arial', 'B', 10);
$pdf->Cell(30, 6, utf8_decode('N° ID Cargo:'), 0, 0, 'R');
$pdf->SetFont('Arial', 'B', 12);
$pdf->Cell(0, 6, $datos['id_cargo'], 0, 1, 'L'); // ID en grande
$pdf->Ln(5);

// 3. DETALLE DEL BIEN (Usando SectionTitle)
$pdf->SectionTitle('Información del Bien');
$pdf->Ln(2);

$pdf->SetFont('Arial', 'B', 10); $pdf->Cell(40, 8, 'Elemento:', 'B', 0);
$pdf->SetFont('Arial', '', 11); $pdf->Cell(0, 8, utf8_decode($datos['elemento']), 'B', 1);

$pdf->SetFont('Arial', 'B', 10); $pdf->Cell(40, 8, utf8_decode('Código/Serie:'), 'B', 0);
$pdf->SetFont('Arial', '', 11); $pdf->Cell(0, 8, utf8_decode($datos['codigo_inventario']), 'B', 1);

$pdf->SetFont('Arial', 'B', 10); $pdf->Cell(40, 8, utf8_decode('Ubicación:'), 'B', 0);
$pdf->SetFont('Arial', '', 11); $pdf->Cell(0, 8, utf8_decode($datos['servicio_ubicacion']), 'B', 1);

$pdf->SetFont('Arial', 'B', 10); $pdf->Cell(40, 8, 'Observaciones:', 'B', 0);
$pdf->SetFont('Arial', '', 10); $pdf->MultiCell(0, 8, utf8_decode($datos['observaciones'] ? $datos['observaciones'] : 'Sin observaciones.'), 'B', 'L');

$pdf->Ln(8);
$pdf->SetFont('Arial', '', 10);
$pdf->MultiCell(0, 6, utf8_decode("Por medio de la presente, se deja constancia de la entrega y responsabilidad de uso del elemento patrimonial detallado anteriormente. El responsable asume el compromiso de cuidar el bien asignado y notificar cualquier desperfecto a Logística."), 0, 'J');

// 4. FIRMAS (Bloque de 3 Firmas Adaptado)
$pdf->Ln(15);
$pdf->SectionTitle('Conformidad y Responsables');
$pdf->Ln(5);

// Definir geometría para 3 columnas
$margin_between = 5;
$col_width = (USABLE_WIDTH - ($margin_between * 2)) / 3;
$sig_height = 35;
$y_sig = $pdf->GetY();

// A. RESPONSABLE (Quien recibe)
$pdf->DrawSignatureBlock(
    'Responsable / Usuario',
    $datos['nombre_responsable'],
    $datos['fecha_creacion'],
    $datos['firma_responsable'], // Campo de la BD para la firma
    LEFT_MARGIN,
    $y_sig,
    $col_width,
    $sig_height
);

// B. JEFE SERVICIO (Testigo/Jefe)
$pdf->DrawSignatureBlock(
    'Jefe de Servicio',
    $datos['nombre_jefe_servicio'],
    $datos['fecha_creacion'],
    $datos['firma_jefe'], // Campo de la BD
    LEFT_MARGIN + $col_width + $margin_between,
    $y_sig,
    $col_width,
    $sig_height
);

// C. RELEVADOR (Logística)
$pdf->DrawSignatureBlock(
    'Relevador (Logística)',
    $datos['nombre_relevador'],
    $datos['fecha_creacion'],
    $datos['firma_relevador'], // Campo de la BD
    LEFT_MARGIN + ($col_width + $margin_between) * 2,
    $y_sig,
    $col_width,
    $sig_height
);

// --- GUARDADO Y SALIDA ---

// 1. Guardar copia PÚBLICA en el servidor
$pdf->Output('F', $pdf_save_path);

// 2. Limpiar QR temporal
if ($qr_code_file_path && file_exists($qr_code_file_path)) {
    unlink($qr_code_file_path);
}

// 3. Redirigir al usuario al PDF público (para que lo vea y la URL del navegador coincida con la del QR)
if (file_exists($pdf_save_path)) {
    header("Location: " . $urlParaPDFPublico);
    exit;
} else {
    // Fallback por si falló el guardado (lo muestra inline pero sin URL persistente)
    $pdf->Output('I', 'Constancia.pdf');
}
?>