<?php
// Archivo: asistencia_pdf.php (VERSIÓN FINAL CON MULTICELL Y ZONA HORARIA)

// ----------------------------------------------------------------------------------
// --- CORRECCIÓN HORA: Establecer Zona Horaria Argentina (GMT-3) ---
// ----------------------------------------------------------------------------------
date_default_timezone_set('America/Argentina/Buenos_Aires');

// 1. BUFFER Y LIMPIEZA (Evita Error 500)
ob_start(); 
error_reporting(E_ALL & ~E_DEPRECATED & ~E_NOTICE);
ini_set('display_errors', 0); 

require('fpdf/fpdf.php');
include 'conexion.php';

// Función para caracteres especiales
function convertir_texto($str) {
    return mb_convert_encoding($str, 'ISO-8859-1', 'UTF-8');
}

// --- CONFIGURACIÓN QR ---
$phpqrcode_path = 'phpqrcode/qrlib.php'; 
$usar_qr = file_exists($phpqrcode_path);
if ($usar_qr) include_once($phpqrcode_path);

$id_parte = (int)($_GET['id'] ?? 0);
if ($id_parte <= 0) { ob_end_clean(); die("ID invalido"); }

// 2. OBTENER DATOS
$stmt = $pdo->prepare("SELECT p.*, u.nombre_completo as firmante, u.firma_imagen_path, p.estado FROM asistencia_partes p JOIN usuarios u ON p.id_creador = u.id_usuario WHERE p.id_parte = :id");
$stmt->execute([':id' => $id_parte]);
$parte = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$parte) { ob_end_clean(); die("Parte no encontrado"); }

$stmt_det = $pdo->prepare("SELECT d.*, u.nombre_completo, u.grado FROM asistencia_detalles d JOIN usuarios u ON d.id_usuario = u.id_usuario WHERE d.id_parte = :id");
$stmt_det->execute([':id' => $id_parte]);
$detalles_db = $stmt_det->fetchAll(PDO::FETCH_ASSOC);

// 3. PLANTILLA DE ORDEN
$plantilla_orden = [
    1  => 'CANETE', 2  => 'LOPEZ', 3  => 'GONZALEZ', 4  => 'PAZ',
    5  => 'BALLADARES', 6  => 'RODRIGUEZ', 7  => 'BENSO', 8  => 'VILLA',
    9  => 'CACERES', 10 => 'GARCIA', 11 => 'LAZZARI', 12 => 'BONFIGLIOLI', 13 => 'PIHUALA'
];

function limpiar_txt($s) { return str_replace(['Á','É','Í','Ó','Ú','Ñ'], ['A','E','I','O','U','N'], mb_strtoupper($s, 'UTF-8')); }

// 4. PROCESAR FILAS
$filas_imprimir = [];
foreach ($plantilla_orden as $nro => $keyword) {
    $encontrado = null;
    foreach ($detalles_db as $db_row) {
        if (strpos(limpiar_txt($db_row['nombre_completo']), $keyword) !== false) {
            $encontrado = $db_row;
            break;
        }
    }
    if ($encontrado) {
        $filas_imprimir[] = [
            'nro' => $nro, 'grado' => $encontrado['grado'], 'nombre' => $encontrado['nombre_completo'],
            'presente' => $encontrado['presente'], 'obs' => $encontrado['observacion_individual']
        ];
    } else {
        $filas_imprimir[] = ['nro' => $nro, 'grado' => '', 'nombre' => '', 'presente' => '', 'obs' => ''];
    }
}

// 5. GENERAR QR
$archivo_qr_temp = null;
if ($usar_qr) {
    $tempDir = 'temp_qr/';
    if (!file_exists($tempDir)) mkdir($tempDir, 0777, true);
    $archivo_qr_temp = $tempDir . 'qr_' . $id_parte . '_' . time() . '.png';
    
    $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
    $protocolo = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? "https" : "http";
    
    // URL CORREGIDA: Incluye /logistica/ y apunta a PDF directo
    $nombre_archivo_final = 'parte_' . $id_parte . '.pdf';
    $contenido_qr = "$protocolo://$host/logistica/pdfs_publicos_novedades/" . $nombre_archivo_final;
    
    QRcode::png($contenido_qr, $archivo_qr_temp, QR_ECLEVEL_L, 3, 1);
}

// 6. CLASE PDF
class PDF_Final extends FPDF {
    public $qrPath = null;
    public $hashSeguridad = '';
    public $fechaImpresion = '';

    function Header() {
        $this->SetMargins(20, 10, 20);
        
        // Logos
        $logo_size = 20;
        if(file_exists('assets/iosfa.png')) $this->Image('assets/iosfa.png', 20, 10, $logo_size);
        elseif(file_exists('assets/log.png')) $this->Image('assets/log.png', 20, 10, $logo_size);

        // Marca de agua
        if(file_exists('assets/img/logo_watermark_gris.png')) {
            $this->SetAlpha(0.15);
            $this->Image('assets/img/logo_watermark_gris.png', 55, 100, 100);
            $this->SetAlpha(1);
        }

        // Membrete
        $this->SetXY(20, 18); 
        $this->SetFont('Arial', 'B', 10);
        $this->Cell(170, 4, convertir_texto('"2025 - AÑO DE LA RECONSTRUCCIÓN DE LA NACIÓN ARGENTINA"'), 0, 1, 'R');
        
        // --- TÍTULOS (Ajustado) ---
        $this->SetY(40);
        $this->SetFont('Arial', 'B', 12);
        $this->Cell(170, 6, convertir_texto('PARTES DE NOVEDADES POLICLÍNICA "GRAL DON OMAR ACTIS"'), 0, 1, 'C');
        $this->SetFont('Arial', 'U', 10);
        $this->Cell(170, 5, convertir_texto('SUBOFICIALES Y SOLDADOS VOLUNTARIOS'), 0, 1, 'C');
        $this->Ln(5);

        // Tabla Header
        $this->SetFont('Arial', 'B', 8);
        $this->SetFillColor(245,245,245);
        $this->Cell(10, 7, 'Nro', 1,0,'C',1);
        $this->Cell(25, 7, 'GRADO', 1,0,'C',1);
        $this->Cell(80, 7, 'APELLIDO Y NOMBRE', 1,0,'C',1);
        $this->Cell(15, 7, 'PRES.', 1,0,'C',1);
        $this->Cell(40, 7, 'OBSERVACIONES', 1,1,'C',1);
    }

    function Footer() {
        $this->SetY(-25);
        $this->SetDrawColor(200, 200, 200);
        $this->Line(20, $this->GetY(), 190, $this->GetY());
        $this->Ln(2);

        $y_inicio = $this->GetY();

        // QR Izquierda
        if ($this->qrPath && file_exists($this->qrPath)) {
            $this->Image($this->qrPath, 20, $y_inicio, 16); 
        }

        // Info Derecha (Negro forzado)
        $this->SetFont('Courier', 'B', 7); 
        $this->SetTextColor(0, 0, 0); // NEGRO
        $x_texto = 110; 
        
        $this->SetXY($x_texto, $y_inicio);
        $this->Cell(80, 3, convertir_texto('CÓDIGO DE INTEGRIDAD:'), 0, 1, 'R');
        $this->SetXY($x_texto, $y_inicio + 3);
        $this->SetFont('Courier', 'B', 7);
        $this->Cell(80, 3, convertir_texto($this->hashSeguridad), 0, 1, 'R');
        $this->SetXY($x_texto, $y_inicio + 7);
        $this->SetFont('Courier', 'B', 6);
        $this->Cell(80, 3, convertir_texto('GENERADO: ' . $this->fechaImpresion . ' | VALIDACIÓN WEB'), 0, 1, 'R');
        $this->SetXY($x_texto, $y_inicio + 11);
        $this->SetFont('Arial', 'B' , 5);
        $this->SetTextColor(0); // NEGRO
        $this->MultiCell(80, 2.5, convertir_texto("Doc. Oficial Policlínica Actis. Validez verificable vía QR."), 0, 'R');

        $this->SetY(-10); 
        $this->SetFont('Arial', 'B', 8);
        $this->SetTextColor(0);
        $this->Cell(0,2,convertir_texto('Página ').$this->PageNo().'/{nb}',0,0,'C');
    }

    function SetAlpha($alpha) {
        $this->_out(sprintf('/ca %1.3F gs /CA %1.3F gs', $alpha, $alpha));
    }
}

// 7. DATOS Y SALIDA
$raw_hash = md5($id_parte . $parte['fecha'] . 'SGAL_KEY');
$hash_display = 'DOC-' . str_pad($id_parte, 5, '0', STR_PAD_LEFT) . '-' . strtoupper(substr($raw_hash, 0, 8));

$pdf = new PDF_Final('P','mm','A4');
$pdf->qrPath = $archivo_qr_temp;
$pdf->hashSeguridad = $hash_display;
$pdf->fechaImpresion = date('d/m/Y H:i'); // Hora de Argentina
$pdf->AliasNbPages();
$pdf->AddPage();
$pdf->SetFont('Arial','',9);

// 8. IMPRIMIR TABLA (CON MULTICELL PARA SALTO DE LÍNEA)
$w = [10, 25, 80, 15, 40]; // Anchos de las columnas
$h_linea = 3.5; // Altura de línea para MultiCell
$margen_celda = 1; // Margen interno de la celda (para cálculo de ancho)

foreach ($filas_imprimir as $f) {
    if (empty($f['nombre'])) continue; 
    
    // --- 1. PREPARAR Y CALCULAR ALTURA DE LA FILA ---
    $pdf->SetFont('Arial', '', 9);
    $obs_a_medir = convertir_texto($f['obs']);
    
    // Calcular cuántas líneas ocupa la Observación (ancho de columna 40mm)
    $num_lineas = $pdf->GetStringWidth($obs_a_medir) > 0 ? ceil($pdf->GetStringWidth($obs_a_medir) / ($w[4] - (2 * $margen_celda))) : 1;

    $altura_final = max(7, $num_lineas * $h_linea + 2); // Altura final de la fila (mínimo 7mm)

    $current_y = $pdf->GetY();
    $current_x = $pdf->GetX();
    
    // --- 2. DIBUJAR RECTÁNGULOS Y SETEAR POSICIÓN ---

    // Dibujar el rectángulo grande que abarca toda la fila (Borde para todas las celdas)
    $pdf->Rect($current_x, $current_y, array_sum($w), $altura_final); 

    // Nro
    $pdf->SetXY($current_x, $current_y);
    $pdf->Cell($w[0], $altura_final, $f['nro'], 0, 0, 'C');
    $current_x += $w[0];

    // Grado
    $pdf->SetXY($current_x, $current_y);
    $pdf->Cell($w[1], $altura_final, convertir_texto($f['grado']), 0, 0, 'C');
    $current_x += $w[1];

    // Nombre
    $pdf->SetXY($current_x, $current_y);
    $pdf->Cell($w[2], $altura_final, convertir_texto(mb_strtoupper($f['nombre'])), 0, 0, 'L');
    $current_x += $w[2];

    // Presente
    $pres = ($f['presente'] == 1) ? 'SI' : 'NO';
    $pdf->SetXY($current_x, $current_y);
    $pdf->Cell($w[3], $altura_final, $pres, 0, 0, 'C');
    $current_x += $w[3];
    
    // --- 3. IMPRIMIR OBSERVACIONES (MultiCell) ---
    
    $obs = $f['obs'];
    if ($obs == 'Ausente' && $f['presente'] == 1) $obs = '';
    if (empty($obs) && $f['presente'] == 0) $obs = 'Ausente';
    if (empty($obs)) $obs = '-';
    
    // Necesitamos reposicionar la X para la MultiCell y ajustar la Y para que no se imprima el texto en la parte superior del borde
    $pdf->SetXY($current_x + $margen_celda, $current_y + $margen_celda); 
    
    // MultiCell: (Ancho, Alto de Línea, Texto, Borde, Alineación, Fill)
    // El ancho de MultiCell debe ser (ancho_columna - 2*margen)
    $pdf->MultiCell($w[4] - (2 * $margen_celda), $h_linea, convertir_texto($obs), 0, 'C'); 

    // 4. Actualizar la posición Y para la siguiente fila
    $pdf->SetY($current_y + $altura_final);
}

// 9. FIRMAS
$pdf->Ln(10);
$meses = ["Enero","Febrero","Marzo","Abril","Mayo","Junio","Julio","Agosto","Septiembre","Octubre","Noviembre","Diciembre"];
$ts = strtotime($parte['fecha']);
$fecha_txt = "CABA, " . date('d', $ts) . " DE " . strtoupper($meses[date('n', $ts)-1]) . " DE " . date('Y', $ts) . ".";

$pdf->SetFont('Arial','',10);
$pdf->Cell(0, 6, convertir_texto($fecha_txt), 0, 1, 'R');

if ($pdf->GetY() > 210) $pdf->AddPage(); 
$pdf->Ln(20);
$y = $pdf->GetY();

$es_canete_creador = (stripos($parte['firmante'], 'Cañete') !== false);

function firma_bloque($pdf, $x, $y, $nombre, $cargo, $img) {
    $pdf->Line($x, $y, $x+60, $y);
    if ($img && file_exists("uploads/firmas/$img")) {
        $pdf->Image("uploads/firmas/$img", $x+10, $y-20, 40);
    }
    $pdf->SetXY($x, $y+2);
    $pdf->SetFont('Arial','B',8);
    $pdf->Cell(60, 4, convertir_texto(strtoupper($nombre)), 0, 1, 'C');
    $pdf->SetX($x);
    $pdf->SetFont('Arial','',7);
    $pdf->Cell(60, 3, convertir_texto($cargo), 0, 1, 'C');
    $pdf->SetX($x);
    $pdf->Cell(60, 3, convertir_texto('SUBGERENCIA EFECTORES SANITARIOS PROPIOS IOSFA'), 0, 1, 'C');
}

if ($es_canete_creador) {
    // FIRMA DE CAÑETE COMO CREADOR (Derecha)
    firma_bloque($pdf, 130, $y, $parte['firmante'], 'ENCARGADO DE LA POLICLÍNICA ACTIS', $parte['firma_imagen_path']);
} else {
    // Firma del creador (Izquierda)
    $cargo = 'ENCARGADO DE TURNO';
    if (stripos($parte['firmante'], 'Federico') !== false) $cargo = 'ENCARGADO DE INFORMÁTICA';
    if (stripos($parte['firmante'], 'Ezequiel') !== false) $cargo = 'ENCARGADO DE MANTENIMIENTO';
    firma_bloque($pdf, 20, $y, $parte['firmante'], $cargo, $parte['firma_imagen_path']);
    
    // Firma de Cañete (Derecha)
    $res = $pdo->query("SELECT firma_imagen_path FROM usuarios WHERE nombre_completo LIKE '%Cañete%'")->fetchColumn();
    firma_bloque($pdf, 130, $y, 'SM MARCELO MARTÍN CAÑETE', 'ENCARGADO DE LA POLICLÍNICA ACTIS', $res);
}

// ---------------------------------------------------------
// BLOQUE FINAL: GUARDAR EN SERVIDOR + MOSTRAR EN PANTALLA
// ---------------------------------------------------------

// Definir ruta de guardado físico
$nombre_archivo_server = 'parte_' . $id_parte . '.pdf';
$ruta_destino = __DIR__ . '/pdfs_publicos_novedades/' . $nombre_archivo_server;

// Crear carpeta si no existe
if (!file_exists(__DIR__ . '/pdfs_publicos_novedades')) {
    mkdir(__DIR__ . '/pdfs_publicos_novedades', 0777, true);
}

// 1. GUARDAR ARCHIVO ('F')
$pdf->Output('F', $ruta_destino); 

// 2. MOSTRAR EN NAVEGADOR ('I')
ob_end_clean();
$pdf->Output('I', 'Parte_Novedades.pdf');

// Limpiar QR temporal
if ($archivo_qr_temp && file_exists($archivo_qr_temp)) {
    unlink($archivo_qr_temp);
}
?>