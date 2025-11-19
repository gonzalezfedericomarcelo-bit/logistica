<?php
// Archivo: asistencia_pdf.php (CON DOBLE FIRMA Y CONTROL DE APROBACIÓN)
date_default_timezone_set('America/Argentina/Buenos_Aires');
ob_start(); 
error_reporting(E_ALL & ~E_DEPRECATED & ~E_NOTICE);
ini_set('display_errors', 0); 

require('fpdf/fpdf.php');
include 'conexion.php';

function convertir_texto($str) { return mb_convert_encoding($str, 'ISO-8859-1', 'UTF-8'); }
$phpqrcode_path = 'phpqrcode/qrlib.php'; 
$usar_qr = file_exists($phpqrcode_path);
if ($usar_qr) include_once($phpqrcode_path);

$id_parte = (int)($_GET['id'] ?? 0);
if ($id_parte <= 0) { ob_end_clean(); die("ID invalido"); }

// Consultamos el ESTADO también
$stmt = $pdo->prepare("SELECT p.*, u.nombre_completo as firmante, u.firma_imagen_path, p.estado FROM asistencia_partes p JOIN usuarios u ON p.id_creador = u.id_usuario WHERE p.id_parte = :id");
$stmt->execute([':id' => $id_parte]);
$parte = $stmt->fetch(PDO::FETCH_ASSOC);
if (!$parte) { ob_end_clean(); die("Parte no encontrado"); }

// Obtener detalles...
$stmt_det = $pdo->prepare("SELECT d.*, u.nombre_completo, u.grado FROM asistencia_detalles d JOIN usuarios u ON d.id_usuario = u.id_usuario WHERE d.id_parte = :id");
$stmt_det->execute([':id' => $id_parte]);
$detalles_db = $stmt_det->fetchAll(PDO::FETCH_ASSOC);

// --- PROCESO DE DATOS (NbLines y tabla) IGUAL QUE ANTES ---
$plantilla_orden = [1=>'CANETE', 2=>'LOPEZ', 3=>'GONZALEZ', 4=>'PAZ', 5=>'BALLADARES', 6=>'RODRIGUEZ', 7=>'BENSO', 8=>'VILLA', 9=>'CACERES', 10=>'GARCIA', 11=>'LAZZARI', 12=>'BONFIGLIOLI', 13=>'PIHUALA'];
function limpiar_txt($s) { return str_replace(['Á','É','Í','Ó','Ú','Ñ'], ['A','E','I','O','U','N'], mb_strtoupper($s, 'UTF-8')); }
$filas_imprimir = [];
foreach ($plantilla_orden as $nro => $keyword) {
    $encontrado = null;
    foreach ($detalles_db as $db_row) {
        if (strpos(limpiar_txt($db_row['nombre_completo']), $keyword) !== false) {
            $encontrado = $db_row; break;
        }
    }
    if ($encontrado) $filas_imprimir[] = ['nro'=>$nro, 'grado'=>$encontrado['grado'], 'nombre'=>$encontrado['nombre_completo'], 'presente'=>$encontrado['presente'], 'obs'=>$encontrado['observacion_individual']];
    else $filas_imprimir[] = ['nro'=>$nro, 'grado'=>'', 'nombre'=>'', 'presente'=>'', 'obs'=>''];
}

// QR
$archivo_qr_temp = null;
if ($usar_qr) {
    $tempDir = 'temp_qr/';
    if (!file_exists($tempDir)) mkdir($tempDir, 0777, true);
    $archivo_qr_temp = $tempDir . 'qr_' . $id_parte . '_' . time() . '.png';
    $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
    $protocolo = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? "https" : "http";
    QRcode::png("$protocolo://$host/logistica/pdfs_publicos_novedades/parte_$id_parte.pdf", $archivo_qr_temp, QR_ECLEVEL_L, 3, 1);
}

class PDF_Final extends FPDF {
    public $qrPath = null; public $hashSeguridad = ''; public $fechaImpresion = ''; public $estadoParte = '';

    function NbLines($w, $txt) {
        if(!isset($this->CurrentFont)) $this->Error('No font has been set');
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
        $this->SetMargins(20, 10, 20);
        if(file_exists('assets/iosfa.png')) $this->Image('assets/iosfa.png', 20, 10, 20);
        elseif(file_exists('assets/log.png')) $this->Image('assets/log.png', 20, 10, 20);

        // MARCA DE AGUA DE BORRADOR SI NO ESTÁ APROBADO
        if ($this->estadoParte === 'pendiente') {
            $this->SetFont('Arial','B',50);
            $this->SetTextColor(255,192,203);
            $this->RotatedText(35,190,'BORRADOR - FALTA APROBACION',45);
            $this->SetTextColor(0,0,0);
        }

        $this->SetXY(20, 18); $this->SetFont('Arial', 'B', 10);
        $this->Cell(170, 4, convertir_texto('"2025 - AÑO DE LA RECONSTRUCCIÓN DE LA NACIÓN ARGENTINA"'), 0, 1, 'R');
        $this->SetY(40); $this->SetFont('Arial', 'B', 12);
        $this->Cell(170, 6, convertir_texto('PARTES DE NOVEDADES POLICLÍNICA "GRAL DON OMAR ACTIS"'), 0, 1, 'C');
        $this->SetFont('Arial', 'U', 10);
        $this->Cell(170, 5, convertir_texto('SUBOFICIALES Y SOLDADOS VOLUNTARIOS'), 0, 1, 'C');
        $this->Ln(5);
        $this->SetFont('Arial', 'B', 8); $this->SetFillColor(245,245,245);
        $this->Cell(10, 7, 'Nro', 1,0,'C',1); $this->Cell(25, 7, 'GRADO', 1,0,'C',1); $this->Cell(80, 7, 'APELLIDO Y NOMBRE', 1,0,'C',1); $this->Cell(15, 7, 'PRES.', 1,0,'C',1); $this->Cell(40, 7, 'OBSERVACIONES', 1,1,'C',1);
    }

    function Footer() {
        $this->SetY(-25); $this->SetDrawColor(200, 200, 200); $this->Line(20, $this->GetY(), 190, $this->GetY()); $this->Ln(2);
        $y_inicio = $this->GetY();
        if ($this->qrPath && file_exists($this->qrPath)) $this->Image($this->qrPath, 20, $y_inicio, 16);
        $this->SetFont('Courier', 'B', 7); $this->SetTextColor(0, 0, 0); $x_texto = 110;
        $this->SetXY($x_texto, $y_inicio); $this->Cell(80, 3, convertir_texto('CÓDIGO DE INTEGRIDAD:'), 0, 1, 'R');
        $this->SetXY($x_texto, $y_inicio + 3); $this->Cell(80, 3, convertir_texto($this->hashSeguridad), 0, 1, 'R');
        $this->SetXY($x_texto, $y_inicio + 7); $this->SetFont('Courier', 'B', 6); $this->Cell(80, 3, convertir_texto('GENERADO: ' . $this->fechaImpresion . ' | VALIDACIÓN WEB'), 0, 1, 'R');
        $this->SetXY($x_texto, $y_inicio + 11); $this->SetFont('Arial', 'B' , 5); $this->SetTextColor(0); $this->MultiCell(80, 2.5, convertir_texto("Doc. Oficial Policlínica Actis. Validez verificable vía QR."), 0, 'R');
        $this->SetY(-10); $this->SetFont('Arial', 'B', 8); $this->SetTextColor(0); $this->Cell(0,2,convertir_texto('Página ').$this->PageNo().'/{nb}',0,0,'C');
    }
    // Función helper para texto rotado
    function RotatedText($x, $y, $txt, $angle) {
        $this->Rotate($angle,$x,$y); $this->Text($x,$y,$txt); $this->Rotate(0);
    }
    function Rotate($angle,$x=-1,$y=-1) {
        if($x==-1) $x=$this->x; if($y==-1) $y=$this->y;
        if(isset($this->angle) && $this->angle!=0) $this->_out('Q');
        $this->angle=$angle;
        if($angle!=0) { $angle*=M_PI/180; $c=cos($angle); $s=sin($angle); $cx=$x*$this->k; $cy=($this->h-$y)*this->k; $this->_out(sprintf('q %.5F %.5F %.5F %.5F %.2F %.2F cm 1 0 0 1 %.2F %.2F cm',$c,$s,-$s,$c,$cx,$cy,-$cx,-$cy)); }
    }
}

$raw_hash = md5($id_parte . $parte['fecha'] . 'SGAL_KEY');
$hash_display = 'DOC-' . str_pad($id_parte, 5, '0', STR_PAD_LEFT) . '-' . strtoupper(substr($raw_hash, 0, 8));

$pdf = new PDF_Final('P','mm','A4');
$pdf->qrPath = $archivo_qr_temp;
$pdf->hashSeguridad = $hash_display;
$pdf->fechaImpresion = date('d/m/Y H:i');
$pdf->estadoParte = $parte['estado'] ?? 'aprobado'; // Pasamos el estado a la clase
$pdf->AliasNbPages();
$pdf->AddPage();
$pdf->SetFont('Arial','',9);

// --- IMPRIMIR TABLA (MISMO CODIGO ANTERIOR) ---
$w = [10, 25, 80, 15, 40]; $h_linea = 4;
foreach ($filas_imprimir as $f) {
    $obs = $f['obs'];
    if ($obs == 'Ausente' && $f['presente'] == 1) $obs = '';
    if (empty($obs) && $f['presente'] == 0) $obs = 'Ausente';
    if (empty($obs)) $obs = '-';
    $pdf->SetFont('Arial', '', 8); 
    $obs_texto = convertir_texto($obs);
    $cant_lineas = $pdf->NbLines($w[4] - 2, $obs_texto); 
    $altura_fila = max(7, ($cant_lineas * $h_linea) + 4); 
    if ($pdf->GetY() + $altura_fila > 260) $pdf->AddPage();
    $x_inicio = $pdf->GetX(); $y_inicio = $pdf->GetY();
    $pdf->Rect($x_inicio, $y_inicio, array_sum($w), $altura_fila);
    $acumulado_x = $x_inicio;
    for ($i = 0; $i < count($w) - 1; $i++) { $acumulado_x += $w[$i]; $pdf->Line($acumulado_x, $y_inicio, $acumulado_x, $y_inicio + $altura_fila); }
    $pdf->SetXY($x_inicio, $y_inicio); $pdf->Cell($w[0], $altura_fila, $f['nro'], 0, 0, 'C');
    $pdf->SetXY($x_inicio + $w[0], $y_inicio); $pdf->Cell($w[1], $altura_fila, convertir_texto($f['grado']), 0, 0, 'C');
    $pdf->SetXY($x_inicio + $w[0] + $w[1], $y_inicio); $pdf->SetX($x_inicio + $w[0] + $w[1] + 1); $pdf->Cell($w[2] - 2, $altura_fila, convertir_texto(mb_strtoupper($f['nombre'])), 0, 0, 'L');
    $pres = ($f['presente'] == 1) ? 'SI' : 'NO';
    $pdf->SetXY($x_inicio + $w[0] + $w[1] + $w[2], $y_inicio); $pdf->Cell($w[3], $altura_fila, $pres, 0, 0, 'C');
    $x_obs = $x_inicio + $w[0] + $w[1] + $w[2] + $w[3];
    $alto_bloque_texto = $cant_lineas * $h_linea; $padding_top = ($altura_fila - $alto_bloque_texto) / 2;
    $pdf->SetXY($x_obs + 1, $y_inicio + $padding_top); $pdf->MultiCell($w[4] - 2, $h_linea, $obs_texto, 0, 'C');
    $pdf->SetY($y_inicio + $altura_fila);
}

// --- ZONA DE FIRMAS INTELIGENTE ---
$pdf->Ln(10);
$meses = ["Enero","Febrero","Marzo","Abril","Mayo","Junio","Julio","Agosto","Septiembre","Octubre","Noviembre","Diciembre"];
$ts = strtotime($parte['fecha']);
$fecha_txt = "CABA, " . date('d', $ts) . " DE " . strtoupper($meses[date('n', $ts)-1]) . " DE " . date('Y', $ts) . ".";

$pdf->SetFont('Arial','',10);
$pdf->Cell(0, 6, convertir_texto($fecha_txt), 0, 1, 'R');

if ($pdf->GetY() > 210) $pdf->AddPage(); 
$pdf->Ln(20);
$y = $pdf->GetY();

// Función para dibujar bloque de firma
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

$es_creador_canete = (stripos($parte['firmante'], 'Cañete') !== false);
$estado = $parte['estado'] ?? 'aprobado';

if ($estado === 'pendiente') {
    // ESTADO PENDIENTE: Solo mostramos firma del creador (si no es Cañete)
    if (!$es_creador_canete) {
        $cargo = 'ENCARGADO DE TURNO';
        if (stripos($parte['firmante'], 'Federico') !== false) $cargo = 'ENCARGADO DE INFORMÁTICA';
        if (stripos($parte['firmante'], 'Ezequiel') !== false) $cargo = 'ENCARGADO DE MANTENIMIENTO';
        firma_bloque($pdf, 20, $y, $parte['firmante'], $cargo, $parte['firma_imagen_path']);
    }
    // Nota: Si está pendiente, NO dibujamos la firma de Cañete.
} else {
    // ESTADO APROBADO (Muestra firmas definitivas)
    
    if ($es_creador_canete) {
        // CASO 1: Creador es Cañete -> SOLO FIRMA DERECHA
        firma_bloque($pdf, 130, $y, $parte['firmante'], 'ENCARGADO DE LA POLICLÍNICA ACTIS', $parte['firma_imagen_path']);
    } else {
        // CASO 2: Creador NO es Cañete (González o Paz) -> DOS FIRMAS
        
        // 2A. Firma Izquierda (Creador)
        $cargo = 'ENCARGADO DE TURNO';
        if (stripos($parte['firmante'], 'Federico') !== false) $cargo = 'ENCARGADO DE INFORMÁTICA';
        if (stripos($parte['firmante'], 'Ezequiel') !== false) $cargo = 'ENCARGADO DE MANTENIMIENTO';
        firma_bloque($pdf, 20, $y, $parte['firmante'], $cargo, $parte['firma_imagen_path']);
        
        // 2B. Firma Derecha (Cañete - Aprobador)
        // Buscamos la firma de Cañete en la BD
        $res = $pdo->query("SELECT firma_imagen_path FROM usuarios WHERE nombre_completo LIKE '%Cañete%'")->fetchColumn();
        firma_bloque($pdf, 130, $y, 'SM MARCELO MARTÍN CAÑETE', 'ENCARGADO DE LA POLICLÍNICA ACTIS', $res);
    }
}

// GUARDAR
$nombre_archivo_server = 'parte_' . $id_parte . '.pdf';
$ruta_destino = __DIR__ . '/pdfs_publicos_novedades/' . $nombre_archivo_server;
if (!file_exists(__DIR__ . '/pdfs_publicos_novedades')) mkdir(__DIR__ . '/pdfs_publicos_novedades', 0777, true);
$pdf->Output('F', $ruta_destino); 
ob_end_clean();
$pdf->Output('I', 'Parte_Novedades.pdf');
if ($archivo_qr_temp && file_exists($archivo_qr_temp)) unlink($archivo_qr_temp);
?>
