<?php
// Archivo: inventario_pdf.php (TU DISEÑO ORIGINAL + TÍTULOS CORREGIDOS)
session_start();
require('fpdf/fpdf.php');
include 'conexion.php';
include 'funciones_permisos.php';

// Idioma español
setlocale(LC_TIME, 'es_ES.UTF-8', 'spanish');

// Validar Acceso
$acceso = false;
if (isset($_SESSION['usuario_id']) && (tiene_permiso('inventario_reportes', $pdo) || tiene_permiso('inventario_historial', $pdo))) $acceso = true;
if (!$acceso && isset($_GET['token']) && isset($_GET['id'])) {
    $stmt = $pdo->prepare("SELECT id_token FROM inventario_transferencias_pendientes WHERE token_hash = ? AND id_bien = ?");
    $stmt->execute([$_GET['token'], $_GET['id']]);
    if ($stmt->rowCount() > 0) $acceso = true;
}
if (!$acceso) die("Acceso denegado.");

// Obtener Datos
$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$sql = "SELECT i.*, e.nombre as nombre_estado, m.tipo_carga, c.nombre as nombre_clase
        FROM inventario_cargos i 
        LEFT JOIN inventario_estados e ON i.id_estado_fk = e.id_estado
        LEFT JOIN inventario_config_matafuegos m ON i.mat_tipo_carga_id = m.id_config
        LEFT JOIN inventario_config_clases c ON i.mat_clase_id = c.id_clase
        WHERE i.id_cargo = ?";
$stmt = $pdo->prepare($sql);
$stmt->execute([$id]);
$bien = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$bien) die("Bien no encontrado. (ID: $id)");

$url_actual = (isset($_SERVER['HTTPS']) ? "https" : "http") . "://" . $_SERVER['HTTP_HOST'] . $_SERVER['REQUEST_URI'];

class PDF extends FPDF {
    function Header() {
        if(file_exists('logo.png')) $this->Image('logo.png', 10, 10, 30); 
        elseif(file_exists('assets/logo.png')) $this->Image('assets/logo.png', 10, 10, 30);

        $this->SetFont('Times','',9);
        $this->SetTextColor(80,80,80);
        $this->Cell(0,4, utf8_decode('República Argentina - Poder Ejecutivo Nacional'),0,1,'R');
        $this->SetFont('Times','B',9);
        $this->Cell(0,4, utf8_decode('2025 - Año de la Reconstrucción de la Nación Argentina'),0,1,'R');
        $this->Ln(8);
        
        $this->SetFont('Arial','B',14);
        $this->SetTextColor(0,0,0);
        $this->Cell(0,8, utf8_decode('ACTA DE RECEPCIÓN DEFINITIVA DE BIEN'),0,1,'C');
        $this->SetLineWidth(0.4);
        $this->Line(10, $this->GetY(), 200, $this->GetY());
        $this->Ln(5);
    }
    function Footer() {
        global $url_actual;
        $this->SetY(-25);
        $qr = "https://api.qrserver.com/v1/create-qr-code/?size=150x150&data=" . urlencode($url_actual);
        $this->Image($qr, 10, $this->GetY(), 18, 18, 'PNG');
        $this->SetX(30);
        $this->SetFont('Arial','',7);
        $this->SetTextColor(100,100,100);
        $this->MultiCell(0,3, utf8_decode("Documento generado electrónicamente por Sistema de Gestión Logística.\nValidez verificable mediante código QR.\nUso interno."),0,'L');
        $this->SetY(-12);
        $this->SetFont('Arial','I',7);
        $this->Cell(0,10, utf8_decode('Página ').$this->PageNo().'/{nb}',0,0,'R');
    }
}

$pdf = new PDF();
$pdf->AliasNbPages();
$pdf->AddPage();
$pdf->SetAutoPageBreak(true, 20);

// 1. IDENTIFICACIÓN
$pdf->SetFillColor(240,240,240);
$pdf->SetFont('Arial','B',10);
$pdf->Cell(0,6, utf8_decode('1. IDENTIFICACIÓN DEL BIEN PATRIMONIAL'),1,1,'L',true);

$h = 7;
$pdf->SetFont('Arial','B',9);
$pdf->Cell(35,$h, utf8_decode('Elemento / Bien:'),1); 
$pdf->SetFont('Arial','',9);
$pdf->Cell(0,$h, utf8_decode($bien['elemento']),1,1);

// --- CAMBIOS SOLICITADOS AQUÍ ---
$pdf->SetFont('Arial','B',9);
$pdf->Cell(35,$h, utf8_decode('N° Cargo Patrimonial:'),1); // CAMBIADO
$pdf->SetFont('Arial','',9);
$pdf->Cell(50,$h, utf8_decode($bien['codigo_inventario']),1);

$pdf->SetFont('Arial','B',9);
$pdf->Cell(35,$h, utf8_decode('N° Serie de Fábrica:'),1); // CAMBIADO
$pdf->SetFont('Arial','',9);
$pdf->Cell(0,$h, utf8_decode($bien['mat_numero_grabado']),1,1);
// --------------------------------

$pdf->SetFont('Arial','B',9);
$pdf->Cell(35,$h, utf8_decode('Ubicación Física:'),1); 
$pdf->SetFont('Arial','',9);
$ubicacion = $bien['destino_principal'];
if($bien['servicio_ubicacion']) $ubicacion .= " - " . $bien['servicio_ubicacion'];
$pdf->Cell(0,$h, utf8_decode($ubicacion),1,1);

$pdf->SetFont('Arial','B',9);
$pdf->Cell(35,$h, utf8_decode('Estado Actual:'),1); 
$pdf->SetFont('Arial','',9);
$pdf->Cell(0,$h, utf8_decode($bien['nombre_estado']),1,1);

// 2. FICHA TÉCNICA (Solo se muestra si tiene datos técnicos)
if ($bien['mat_tipo_carga_id'] || $bien['mat_capacidad']) {
    $pdf->Ln(3);
    $pdf->SetFont('Arial','B',10);
    $pdf->Cell(0,6, utf8_decode('2. ESPECIFICACIONES TÉCNICAS'),1,1,'L',true);
    
    // Fila 1
    $pdf->SetFont('Arial','B',9);
    $pdf->Cell(25,$h, utf8_decode('Tipo Agente:'),1);
    $pdf->SetFont('Arial','',9);
    $pdf->Cell(70,$h, utf8_decode($bien['tipo_carga']),1);
    
    $pdf->SetFont('Arial','B',9);
    $pdf->Cell(25,$h, utf8_decode('Capacidad:'),1);
    $pdf->SetFont('Arial','',9);
    $pdf->Cell(25,$h, $bien['mat_capacidad'].' Kg',1);
    
    $pdf->SetFont('Arial','B',9);
    $pdf->Cell(20,$h, utf8_decode('Clase:'),1);
    $pdf->SetFont('Arial','',9);
    $pdf->Cell(0,$h, utf8_decode($bien['nombre_clase']),1,1);

    // Fila 2
    $pdf->SetFont('Arial','B',9);
    $pdf->Cell(25,$h, utf8_decode('Fabricación:'),1);
    $pdf->SetFont('Arial','',9);
    $pdf->Cell(70,$h, $bien['fecha_fabricacion'],1);
    
    $pdf->SetFont('Arial','B',9);
    $pdf->Cell(25,$h, utf8_decode('Vida Útil:'),1);
    $pdf->SetFont('Arial','',9);
    $pdf->Cell(0,$h, $bien['vida_util_limite'] ? $bien['vida_util_limite'].' '.utf8_decode('años') : '',1,1);

    // Fila 3
    $pdf->SetFont('Arial','B',9);
    $pdf->Cell(25,$h, utf8_decode('Última Carga:'),1);
    $pdf->SetFont('Arial','',9);
    $ultima_carga = $bien['mat_fecha_carga'] ? date('d/m/Y', strtotime($bien['mat_fecha_carga'])) : '-';
    $pdf->Cell(70,$h, $ultima_carga, 1);
    
    $pdf->SetFont('Arial','B',9);
    $pdf->Cell(25,$h, utf8_decode('Vto Carga:'),1);
    $pdf->SetFont('Arial','B',9);
    $vto_carga = $bien['mat_fecha_carga'] ? date('d/m/Y', strtotime($bien['mat_fecha_carga']. ' +1 year')) : '-';
    $pdf->Cell(0,$h, $vto_carga, 1, 1);

    // Fila 4
    $pdf->SetFont('Arial','B',9);
    $pdf->Cell(25,$h, utf8_decode('Última P.H.:'),1);
    $pdf->SetFont('Arial','',9);
    $ultima_ph = $bien['mat_fecha_ph'] ? date('d/m/Y', strtotime($bien['mat_fecha_ph'])) : '-';
    $pdf->Cell(70,$h, $ultima_ph, 1);
    
    $pdf->SetFont('Arial','B',9);
    $pdf->Cell(25,$h, utf8_decode('Vto P.H.:'),1);
    $pdf->SetFont('Arial','B',9);
    $vto_ph = $bien['mat_fecha_ph'] ? date('d/m/Y', strtotime($bien['mat_fecha_ph']. ' +5 years')) : '-';
    $pdf->Cell(0,$h, $vto_ph, 1, 1);
}

// FECHA Y FIRMAS
$pdf->Ln(3);
$fecha = new DateTime($bien['fecha_creacion']);
$meses = ['Enero','Febrero','Marzo','Abril','Mayo','Junio','Julio','Agosto','Septiembre','Octubre','Noviembre','Diciembre'];
$pdf->SetFont('Arial','I',10);
$pdf->Cell(0, 6, utf8_decode("Ciudad Autónoma de Buenos Aires, " . $fecha->format('d') . " de " . $meses[$fecha->format('n')-1] . " de " . $fecha->format('Y')), 0, 1, 'R');

$pdf->Ln(5);
$pdf->SetFont('Arial','B',10);
$pdf->Cell(0,6, utf8_decode('3. CONFORMIDAD Y FIRMAS'),0,1,'L');

$y = $pdf->GetY() + 5;
$h_firma = 35; 
$w_col = 85; 
$x2 = 105;

if($bien['firma_responsable'] && file_exists($bien['firma_responsable'])) $pdf->Image($bien['firma_responsable'], 30, $y+10, 35);
$pdf->SetXY(10, $y+$h_firma);
$pdf->Cell($w_col, 5, utf8_decode('Firma Responsable a Cargo'), 'T', 1, 'C');
$pdf->SetX(10); $pdf->SetFont('Arial','',8); $pdf->Cell($w_col, 4, utf8_decode($bien['nombre_responsable']), 0, 0, 'C');

if($bien['firma_jefe'] && file_exists($bien['firma_jefe'])) $pdf->Image($bien['firma_jefe'], $x2+25, $y+10, 35);
$pdf->SetXY($x2, $y+$h_firma);
$pdf->SetFont('Arial','B',10);
$pdf->Cell($w_col, 5, utf8_decode('Firma Jefe de Servicio'), 'T', 1, 'C');
$pdf->SetXY($x2, $pdf->GetY()); $pdf->SetFont('Arial','',8); $pdf->Cell($w_col, 4, utf8_decode($bien['nombre_jefe_servicio']), 0, 0, 'C');

$y += $h_firma + 20;
if($bien['firma_relevador'] && file_exists($bien['firma_relevador'])) $pdf->Image($bien['firma_relevador'], 30, $y+10, 35);
$pdf->SetXY(10, $y+$h_firma);
$pdf->SetFont('Arial','B',10);
$pdf->Cell($w_col, 5, utf8_decode('Firma Relevador (Logística)'), 'T', 1, 'C');

$pdf->SetXY($x2, $y+$h_firma);
$pdf->Cell($w_col, 5, utf8_decode('Encargada Cargo Patrimonial'), 'T', 1, 'C');

$pdf->Output('I', 'Acta_'.$bien['id_cargo'].'.pdf');
?>