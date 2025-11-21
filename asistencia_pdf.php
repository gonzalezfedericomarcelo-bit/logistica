<?php
// Archivo: asistencia_pdf.php (DISEÑO FINAL: MEMBRETE ALINEADO + FECHAS TEXTUALES + FOOTER COMPLETO)
date_default_timezone_set('America/Argentina/Buenos_Aires');
ob_start(); 
error_reporting(0);

require('fpdf/fpdf.php');
include 'conexion.php';

function convertir_texto($str) { return mb_convert_encoding($str, 'ISO-8859-1', 'UTF-8'); }
$phpqrcode_path = 'phpqrcode/qrlib.php'; 
$usar_qr = file_exists($phpqrcode_path);
if ($usar_qr) include_once($phpqrcode_path);

$id_parte = (int)($_GET['id'] ?? 0);
if ($id_parte <= 0) { ob_end_clean(); die("ID invalido"); }

// 1. Datos Cabecera
$stmt = $pdo->prepare("SELECT p.*, u.nombre_completo as firmante_nombre, u.grado as firmante_grado, u.firma_imagen_path, p.estado, p.bitacora FROM asistencia_partes p JOIN usuarios u ON p.id_creador = u.id_usuario WHERE p.id_parte = :id");
$stmt->execute([':id' => $id_parte]);
$parte = $stmt->fetch(PDO::FETCH_ASSOC);
if (!$parte) { ob_end_clean(); die("Parte no encontrado"); }

$nombre_firma = strtoupper(($parte['firmante_grado'] ?? '') . ' ' . $parte['firmante_nombre']);
if (stripos($parte['firmante_nombre'], 'Cañete') !== false) { $nombre_firma = 'SM MARCELO MARTÍN CAÑETE'; }

// 2. Detalles
$stmt_det = $pdo->prepare("SELECT d.*, u.nombre_completo, u.grado FROM asistencia_detalles d JOIN usuarios u ON d.id_usuario = u.id_usuario WHERE d.id_parte = :id");
$stmt_det->execute([':id' => $id_parte]);
$detalles_db = $stmt_det->fetchAll(PDO::FETCH_ASSOC);

// 3. Stats
$stats = ['SUBOF' => ['EFE'=>0,'PRES'=>0,'AUS'=>0], 'SOLD' => ['EFE'=>0,'PRES'=>0,'AUS'=>0], 'TOTAL' => ['EFE'=>0,'PRES'=>0,'AUS'=>0]];
$grados_subof = ['SM', 'SP', 'SA', 'SI', 'SS', 'SG', 'CI', 'CB', 'CS'];
$grados_sold = ['VS', 'VP'];

foreach ($detalles_db as $d) {
    $grado = strtoupper(trim($d['grado'])); $cat = null;
    if (in_array($grado, $grados_subof)) $cat = 'SUBOF';
    elseif (in_array($grado, $grados_sold) || strpos($grado, 'VS') !== false) $cat = 'SOLD';
    if ($cat) {
        $stats[$cat]['EFE']++; $stats['TOTAL']['EFE']++;
        if ($d['presente'] == 1) { $stats[$cat]['PRES']++; $stats['TOTAL']['PRES']++; } else { $stats[$cat]['AUS']++; $stats['TOTAL']['AUS']++; }
    }
}

// 4. Ordenar
$plantilla_orden = [1=>'CANETE', 2=>'LOPEZ', 3=>'GONZALEZ', 4=>'PAZ', 5=>'BALLADARES', 6=>'RODRIGUEZ', 7=>'BENSO', 8=>'VILLA', 9=>'CACERES', 10=>'GARCIA', 11=>'LAZZARI', 12=>'BONFIGLIOLI', 13=>'PIHUALA'];
function limpiar_txt($s) { return str_replace(['Á','É','Í','Ó','Ú','Ñ'], ['A','E','I','O','U','N'], mb_strtoupper($s, 'UTF-8')); }
$filas_imprimir = [];
foreach ($plantilla_orden as $nro => $keyword) {
    $encontrado = null;
    foreach ($detalles_db as $db_row) {
        if (strpos(limpiar_txt($db_row['nombre_completo']), $keyword) !== false) { $encontrado = $db_row; break; }
    }
    if ($encontrado) {
        $tipo = $encontrado['tipo_asistencia'] ?? 'presente';
        $pres_txt = ($tipo === 'ausente') ? 'NO' : (($tipo === 'tarde') ? 'T.T' : (($tipo === 'comision') ? 'COM' : 'SI'));
        $filas_imprimir[] = ['nro'=>$nro, 'grado'=>$encontrado['grado'], 'nombre'=>$encontrado['nombre_completo'], 'presente_txt'=>$pres_txt, 'obs'=>$encontrado['observacion_individual']];
    } else {
        $filas_imprimir[] = ['nro'=>$nro, 'grado'=>'', 'nombre'=>'', 'presente_txt'=>'', 'obs'=>''];
    }
}

// QR
$archivo_qr_temp = null;
if ($usar_qr) {
    $tempDir = 'temp_qr/'; if (!file_exists($tempDir)) mkdir($tempDir, 0777, true);
    $archivo_qr_temp = $tempDir . 'qr_' . $id_parte . '.png';
    $hash_display = 'DOC-' . str_pad($id_parte, 5, '0', STR_PAD_LEFT) . '-' . strtoupper(substr(md5($id_parte . 'KEY'), 0, 8));
    QRcode::png($hash_display, $archivo_qr_temp, QR_ECLEVEL_L, 3, 1);
}

class PDF_Final extends FPDF {
    public $qrPath = null; public $hashSeguridad = ''; public $fechaImpresion = ''; public $estadoParte = ''; public $cuadroStats = [];
    public $fechaParteStr = ''; public $idParteStr = '';

    function NbLines($w, $txt) {
        $cw = &$this->CurrentFont['cw'];
        if($w==0) $w = $this->w-$this->rMargin-$this->x;
        $wmax = ($w-2*$this->cMargin)*1000/$this->FontSize;
        $s = str_replace("\r",'', $txt);
        $nb = strlen($s);
        if($nb>0 && $s[$nb-1]=="\n") $nb--;
        $sep = -1; $i = 0; $j = 0; $l = 0; $nl = 1;
        while($i<$nb) {
            $c = $s[$i];
            if($c=="\n") { $i++; $sep = -1; $j = $i; $l = 0; $nl++; continue; }
            if($c==' ') $sep = $i;
            $l += $cw[$c];
            if($l>$wmax) { if($sep==-1) { if($i==$j) $i++; } else $i = $sep+1; $sep = -1; $j = $i; $l = 0; $nl++; } else $i++;
        }
        return $nl;
    }

    function Header() {
        // MARCA DE AGUA
        $watermark_path = 'assets/img/logo_watermark_gris2.png';
        if (file_exists($watermark_path)) {
            $this->Image($watermark_path, 35, 80, 140, 0, '', '', false);
        }

        $this->SetMargins(10, 10, 10);
        $this->SetY(10);

        // --- 1. MEMBRETE (Logos y Año Alineados) ---
        
        // Logo Izquierda
        if(file_exists('assets/iosfa.png')) $this->Image('assets/iosfa.png', 12, 8, 16);
        elseif(file_exists('assets/log.png')) $this->Image('assets/log.png', 12, 8, 16);

        // Sello Derecha
        $sello_path = 'assets/img/sello_trabajo.png';
        if (file_exists($sello_path)) {
            $this->Image($sello_path, 180, 6, 20); 
        }

        // Texto del Año (Centrado Verticalmente con los logos)
        $this->SetXY(30, 12); // Margen izquierdo para no pisar logo
        $this->SetFont('Arial', 'B', 9);
        $this->Cell(150, 4, convertir_texto('"2025 - AÑO DE LA RECONSTRUCCIÓN DE LA NACIÓN ARGENTINA"'), 0, 1, 'C');

        // --- LÍNEA DIVISORIA ---
        /*$this->Ln(8); 
        $this->SetDrawColor(0); 
        $this->SetLineWidth(0.4);
        $this->Line(10, $this->GetY(), 200, $this->GetY());
        $this->SetLineWidth(0.2); */
        
        // --- 2. BLOQUE DE TÍTULOS (Debajo de la línea) ---
        $this->Ln(15);
        
        $this->SetFont('Arial', 'B', 14);
        $this->Cell(0, 6, convertir_texto('PARTE DE NOVEDADES DIARIAS'), 0, 1, 'C');
        
        $this->SetFont('Arial', 'B', 12);
        $this->Cell(0, 6, convertir_texto('POLICLÍNICA "GRAL DON OMAR ACTIS"'), 0, 1, 'C');
        
        $this->SetFont('Arial', '', 10);
        $this->Cell(0, 5, convertir_texto('SUBGERENCIA DE EFECTORES SANITARIOS PROPIOS - IOSFA'), 0, 1, 'C');
        
        $this->Ln(5);

        // --- 3. BARRA DE ESTADÍSTICAS ---
        if (!empty($this->cuadroStats)) {
            $this->SetFont('Arial', 'B', 9);
            $this->SetFillColor(240); $this->SetDrawColor(0);
            $tot_efe = $this->cuadroStats['TOTAL']['EFE'];
            $tot_pres = $this->cuadroStats['TOTAL']['PRES'];
            $tot_aus = $this->cuadroStats['TOTAL']['AUS'];
            
            $texto_stats = "FUERZA EFECTIVA: $tot_efe   |   PRESENTES: $tot_pres   |   AUSENTES: $tot_aus";
            $this->Cell(0, 8, convertir_texto($texto_stats), 1, 1, 'C', 1);
        }
        
        $this->Ln(5);

        // --- 4. ENCABEZADOS TABLA ---
        $this->SetDrawColor(0); // Negro firme
        $this->SetFont('Arial', 'B', 8); 
        $this->SetFillColor(220); 
        
        $w0=10; $w1=25; $w2=80; $w3=15; $w4=60; 
        
        $this->Cell($w0, 7, 'Nro', 1,0,'C',1); 
        $this->Cell($w1, 7, 'GRADO', 1,0,'C',1); 
        $this->Cell($w2, 7, 'APELLIDO Y NOMBRE', 1,0,'C',1); 
        $this->Cell($w3, 7, 'PRES.', 1,0,'C',1); 
        $this->Cell($w4, 7, 'OBSERVACIONES', 1,1,'C',1);
    }
    
    function Footer() {
        $this->SetY(-32); // Un poco más alto para que entre todo
        $this->SetDrawColor(0);
        $this->Line(10, $this->GetY(), 200, $this->GetY());
        $this->Ln(2);
        
        $y_qr = $this->GetY();
        if ($this->qrPath && file_exists($this->qrPath)) {
            $this->Image($this->qrPath, 10, $y_qr, 18);
        }
        
        // --- DATOS DEL PARTE (Pequeños pero legibles) ---
        $x_txt = 32;
        $this->SetXY($x_txt, $y_qr + 1); 
        
        // Fila 1: Fecha y Número de Parte
        $this->SetFont('Arial', 'B', 8); $this->SetTextColor(0);
        $this->Cell(0, 4, convertir_texto('FECHA DEL PARTE: ' . $this->fechaParteStr . '   |   PARTE N°: ' . str_pad($this->idParteStr, 4, '0', STR_PAD_LEFT)), 0, 1, 'L');
        
        // Fila 2: Datos de Generación
        $this->SetX($x_txt);
        $this->SetFont('Courier', '', 7);
        $this->Cell(0, 3, convertir_texto('Generado: ' . $this->fechaImpresion . ' | ID Seg: ' . $this->hashSeguridad), 0, 1, 'L');
        
        // Fila 3: Leyenda QR
        $this->SetX($x_txt);
        $this->SetFont('Arial', 'I', 6);
        $this->Cell(0, 3, convertir_texto("Documento Oficial. La validez de este parte puede ser verificada escaneando el código QR."), 0, 1, 'L');
        
        // Info Derecha (Sistema)
        $this->SetXY(-60, $y_qr + 3);
        $this->SetFont('Arial', 'B',6);
        $this->Cell(50, 3, convertir_texto('SISTEMA DE GESTIÓN AVANZADO DE LOGÍSTICA Y PERSONAL'), 0, 1, 'R');
        $this->SetXY(-60, $y_qr + 7);
        $this->SetFont('Arial', '', 6);
        $this->Cell(50, 3, convertir_texto('v2.0 - DESARROLLO BY SG MEC INFO FEDERICO GONZÁLEZ'), 0, 1, 'R');

        // Paginación
        $this->SetY(-10);
        $this->SetFont('Arial', '', 8);
        $this->Cell(0, 5, convertir_texto('Página ').$this->PageNo().'/{nb}', 0, 0, 'C');
    }
    
    function GetMultiCellHeight($w, $h, $txt) {
        $cw = &$this->CurrentFont['cw'];
        if($w==0) $w = $this->w-$this->rMargin-$this->x;
        $wmax = ($w-2*$this->cMargin)*1000/$this->FontSize;
        $s = str_replace("\r",'', $txt);
        $nb = strlen($s);
        if($nb>0 && $s[$nb-1]=="\n") $nb--;
        $sep = -1; $i = 0; $j = 0; $l = 0; $nl = 1;
        while($i<$nb) {
            $c = $s[$i];
            if($c=="\n") { $i++; $sep = -1; $j = $i; $l = 0; $nl++; continue; }
            if($c==' ') $sep = $i;
            $l += $cw[$c];
            if($l>$wmax) { if($sep==-1) { if($i==$j) $i++; } else $i = $sep+1; $sep = -1; $j = $i; $l = 0; $nl++; } else $i++;
        }
        return $nl * $h;
    }
    
    function RotatedText($x, $y, $txt, $angle) { $this->Rotate($angle,$x,$y); $this->Text($x,$y,$txt); $this->Rotate(0); }
    function Rotate($angle,$x=-1,$y=-1) { if($x==-1) $x=$this->x; if($y==-1) $y=$this->y; if(isset($this->angle) && $this->angle!=0) $this->_out('Q'); $this->angle=$angle; if($angle!=0) { $angle*=M_PI/180; $c=cos($angle); $s=sin($angle); $cx=$x*$this->k; $cy=($this->h-$y)*this->k; $this->_out(sprintf('q %.5F %.5F %.5F %.5F %.2F %.2F cm 1 0 0 1 %.2F %.2F cm',$c,$s,-$s,$c,$cx,$cy,-$cx,-$cy)); } }
}

$pdf = new PDF_Final('P','mm','A4');
$pdf->qrPath = $archivo_qr_temp;
$pdf->hashSeguridad = $hash_display;
$pdf->fechaImpresion = date('d/m/Y H:i');
$pdf->estadoParte = $parte['estado'];
$pdf->cuadroStats = $stats;
$pdf->fechaParteStr = date('d/m/Y', strtotime($parte['fecha']));
$pdf->idParteStr = $id_parte;

$pdf->AliasNbPages();
$pdf->AddPage();
$pdf->SetFont('Arial','',9);
$pdf->SetDrawColor(0,0,0);

// --- CUERPO TABLA DINÁMICA ---
$w = [10, 25, 80, 15, 60]; 
$h_base = 6;

foreach ($filas_imprimir as $f) {
    $obs = $f['obs']; if (empty($obs)) $obs = '-';
    $pres = $f['presente_txt'];
    
    $pdf->SetFont('Arial', '', 8);
    $texto_obs = convertir_texto($obs);
    $altura_obs = $pdf->GetMultiCellHeight($w[4] - 4, 4, $texto_obs); 
    $h_fila = max($h_base, $altura_obs + 2); 

    if($pdf->GetY() + $h_fila > 235) $pdf->AddPage();
    
    $x = $pdf->GetX(); 
    $y = $pdf->GetY();
    $pad_top = ($h_fila - 5) / 2; 
    
    $pdf->Rect($x, $y, $w[0], $h_fila); $pdf->SetXY($x, $y + $pad_top); $pdf->Cell($w[0], 5, $f['nro'], 0, 0, 'C');
    $pdf->Rect($x+$w[0], $y, $w[1], $h_fila); $pdf->SetXY($x+$w[0], $y + $pad_top); $pdf->Cell($w[1], 5, convertir_texto($f['grado']), 0, 0, 'C');
    $pdf->Rect($x+$w[0]+$w[1], $y, $w[2], $h_fila); $pdf->SetXY($x+$w[0]+$w[1]+1, $y + $pad_top); $pdf->Cell($w[2]-2, 5, convertir_texto($f['nombre']), 0, 0, 'L');
    
    $pdf->Rect($x+$w[0]+$w[1]+$w[2], $y, $w[3], $h_fila); $pdf->SetXY($x+$w[0]+$w[1]+$w[2], $y + $pad_top);
    $pdf->Cell($w[3], 5, $pres, 0, 0, 'C'); $pdf->SetTextColor(0);
    
    $pdf->Rect($x+$w[0]+$w[1]+$w[2]+$w[3], $y, $w[4], $h_fila);
    $pdf->SetXY($x+$w[0]+$w[1]+$w[2]+$w[3] + 2, $y + 1); 
    $pdf->MultiCell($w[4] - 4, 4, $texto_obs, 0, 'L');
    
    $pdf->SetY($y + $h_fila);
}

// --- BITÁCORA ---
if (!empty($parte['bitacora'])) {
    $pdf->Ln(6);
    if ($pdf->GetY() > 200) $pdf->AddPage();

    $pdf->SetFillColor(240); $pdf->SetDrawColor(0);
    $pdf->SetFont('Arial', 'B', 10);
    $pdf->Cell(0, 7, convertir_texto('NOVEDADES DIARIAS Y MOVIMIENTOS'), 1, 1, 'L', 1);
    
    $lineas = explode("\n", $parte['bitacora']);
    foreach($lineas as $linea) {
        if (trim($linea) == '') continue;
        if (stripos($linea, 'CAMBIO DE ESTADO') !== false || stripos($linea, '>>') !== false) {
            $pdf->SetFont('Arial', 'BI', 8); $pdf->SetTextColor(50);
        } else {
            $pdf->SetFont('Courier', '', 9); $pdf->SetTextColor(0);
        }
        $pdf->MultiCell(0, 5, convertir_texto($linea), 'LR', 'L');
    }
    $pdf->Cell(0, 0, '', 'T'); 
}

// --- FECHA Y FIRMAS ---
$pdf->Ln(10); // Espacio antes de la fecha

// FORMATO DE FECHA SOLICITADO: CABA, 20 DE NOVIEMBRE DE 2025.
$meses = ["ENERO","FEBRERO","MARZO","ABRIL","MAYO","JUNIO","JULIO","AGOSTO","SEPTIEMBRE","OCTUBRE","NOVIEMBRE","DICIEMBRE"];
$ts = strtotime($parte['fecha']);
$fecha_larga = "CABA, " . date('d', $ts) . " DE " . $meses[date('n', $ts)-1] . " DE " . date('Y', $ts) . ".";

$pdf->SetFont('Arial','',10); 
$pdf->Cell(0, 6, convertir_texto($fecha_larga), 0, 1, 'R');

// Espacio Generoso antes de Firmas
$pdf->Ln(15);

// Verificar si hay espacio para firmas, sino nueva página
if ($pdf->GetY() > 230) $pdf->AddPage(); 

$y = $pdf->GetY();

function firma_bloque($pdf, $x, $y, $nombre, $cargo, $img) {
    $pdf->Line($x, $y, $x+60, $y);
    if ($img && file_exists("uploads/firmas/$img")) { $pdf->Image("uploads/firmas/$img", $x+10, $y-20, 40); }
    $pdf->SetXY($x, $y+2); $pdf->SetFont('Arial','B',8); $pdf->Cell(60, 4, convertir_texto($nombre), 0, 1, 'C');
    $pdf->SetX($x); $pdf->SetFont('Arial','',7); $pdf->Cell(60, 3, convertir_texto($cargo), 0, 1, 'C');
    $pdf->SetX($x); $pdf->Cell(60, 3, convertir_texto('SUBGERENCIA EFECTORES SANITARIOS PROPIOS IOSFA'), 0, 1, 'C');
}

$es_creador_canete = (stripos($nombre_firma, 'CAÑETE') !== false);

if ($parte['estado'] === 'pendiente') {
    if (!$es_creador_canete) {
        firma_bloque($pdf, 20, $y, $nombre_firma, 'ENCARGADO DE TURNO', $parte['firma_imagen_path']);
    }
} else {
    if ($es_creador_canete) {
        firma_bloque($pdf, 130, $y, $nombre_firma, 'ENCARGADO DE LA POLICLÍNICA ACTIS', $parte['firma_imagen_path']);
    } else {
        firma_bloque($pdf, 20, $y, $nombre_firma, 'ENCARGADO DE TURNO', $parte['firma_imagen_path']);
        $res = $pdo->query("SELECT firma_imagen_path FROM usuarios WHERE nombre_completo LIKE '%Cañete%'")->fetchColumn();
        firma_bloque($pdf, 130, $y, 'SM MARCELO MARTÍN CAÑETE', 'ENCARGADO DE LA POLICLÍNICA ACTIS', $res);
    }
}

$pdf->Output('I', 'Parte_Novedades.pdf');
if ($archivo_qr_temp && file_exists($archivo_qr_temp)) unlink($archivo_qr_temp);
?>