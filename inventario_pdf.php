<?php
// Archivo: inventario_pdf.php
session_start();
require('fpdf/fpdf.php');
include 'conexion.php';
include 'funciones_permisos.php';

// Configurar idioma local para fechas en español
setlocale(LC_TIME, 'es_ES.UTF-8', 'spanish');

// ============================================================================
// 1. LÓGICA DE SEGURIDAD
// ============================================================================
$acceso_permitido = false;

// A. Verificar usuario logueado
if (isset($_SESSION['usuario_id']) && (tiene_permiso('inventario_reportes', $pdo) || tiene_permiso('inventario_historial', $pdo))) {
    $acceso_permitido = true;
}

// B. Excepción por TOKEN (Acceso Externo)
if (!$acceso_permitido && isset($_GET['token']) && isset($_GET['id'])) {
    $token_check = $_GET['token'];
    $id_bien_check = $_GET['id'];
    $stmtCheck = $pdo->prepare("SELECT id_token FROM inventario_transferencias_pendientes WHERE token_hash = ? AND id_bien = ?");
    $stmtCheck->execute([$token_check, $id_bien_check]);
    if ($stmtCheck->rowCount() > 0) {
        $acceso_permitido = true;
    }
}

if (!$acceso_permitido) {
    die("Acceso denegado. No tienes permisos o el enlace es inválido.");
}

// ============================================================================
// 2. OBTENER DATOS
// ============================================================================
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

if (!$bien) die("Bien no encontrado.");

// URL actual para el QR
$protocol = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? "https" : "http";
$url_actual = $protocol . "://" . $_SERVER['HTTP_HOST'] . $_SERVER['REQUEST_URI'];

// ============================================================================
// 3. CLASE PDF PERSONALIZADA
// ============================================================================
class PDF extends FPDF {
    function Header() {
        // Logo
        if(file_exists('logo.png')) {
            $this->Image('logo.png', 10, 10, 30); 
        } elseif(file_exists('assets/logo.png')) {
             $this->Image('assets/logo.png', 10, 10, 30);
        }

        // Membrete (Más compacto)
        $this->SetFont('Times','',9);
        $this->SetTextColor(80,80,80);
        $this->Cell(0,4, utf8_decode('República Argentina - Poder Ejecutivo Nacional'),0,1,'R');
        $this->SetFont('Times','B',9);
        $this->Cell(0,4, utf8_decode('2025 - Año de la Reconstrucción de la Nación Argentina'),0,1,'R');
        $this->Ln(8); // Reducido de 15 a 8
        
        // Título
        $this->SetFont('Arial','B',14); // Reducido un poco para ahorrar espacio
        $this->SetTextColor(0,0,0);
        $this->Cell(0,8, utf8_decode('ACTA DE RECEPCIÓN DEFINITIVA DE BIEN'),0,1,'C');
        $this->SetLineWidth(0.4);
        $this->Line(10, $this->GetY(), 200, $this->GetY());
        $this->Ln(5); // Reducido de 8 a 5
    }

    function Footer() {
        global $url_actual;
        $this->SetY(-25); // Pie más compacto
        
        // QR Code
        $qr_url = "https://api.qrserver.com/v1/create-qr-code/?size=150x150&data=" . urlencode($url_actual);
        $this->Image($qr_url, 10, $this->GetY(), 18, 18, 'PNG');

        // Texto Legal
        $this->SetX(30);
        $this->SetFont('Arial','',7);
        $this->SetTextColor(100,100,100);
        $this->MultiCell(0,3, utf8_decode("Este documento ha sido generado electrónicamente por el Sistema de Gestión Logística Integral.\nLa validez del mismo puede ser verificada escaneando el código QR adjunto.\nDocumento oficial de uso interno y auditoría."),0,'L');
        
        // Paginación
        $this->SetY(-12);
        $this->SetFont('Arial','I',7);
        $this->Cell(0,10, utf8_decode('Página ').$this->PageNo().'/{nb}',0,0,'R');
    }
}

$pdf = new PDF();
$pdf->AliasNbPages();
$pdf->AddPage();
$pdf->SetAutoPageBreak(true, 20); // Margen inferior automático ajustado

// --- 1. IDENTIFICACIÓN DEL BIEN ---
$pdf->SetFillColor(240,240,240);
$pdf->SetFont('Arial','B',10);
$pdf->Cell(0,6, utf8_decode('1. IDENTIFICACIÓN DEL BIEN PATRIMONIAL'),1,1,'L',true);

// Usamos altura de línea 7 para compactar
$h_line = 7;

$pdf->SetFont('Arial','B',9);
$pdf->Cell(35,$h_line, utf8_decode('Elemento / Bien:'),1,0,'L',false); 
$pdf->SetFont('Arial','',9);
$pdf->Cell(0,$h_line, utf8_decode($bien['elemento']),1,1,'L',false);

$pdf->SetFont('Arial','B',9);
$pdf->Cell(35,$h_line, utf8_decode('Código Interno:'),1); 
$pdf->SetFont('Arial','',9);
$pdf->Cell(50,$h_line, utf8_decode($bien['codigo_inventario']),1);
$pdf->SetFont('Arial','B',9);
$pdf->Cell(35,$h_line, utf8_decode('N° Grabado/Serie:'),1); 
$pdf->SetFont('Arial','',9);
$pdf->Cell(0,$h_line, utf8_decode($bien['mat_numero_grabado']),1,1);

$pdf->SetFont('Arial','B',9);
$pdf->Cell(35,$h_line, utf8_decode('Ubicación Física:'),1); 
$pdf->SetFont('Arial','',9);
$pdf->Cell(0,$h_line, utf8_decode($bien['destino_principal'] . ' - ' . $bien['servicio_ubicacion']),1,1);

$pdf->SetFont('Arial','B',9);
$pdf->Cell(35,$h_line, utf8_decode('Estado Actual:'),1); 
$pdf->SetFont('Arial','',9);
$pdf->Cell(0,$h_line, utf8_decode($bien['nombre_estado']),1,1);

$pdf->SetFont('Arial','B',9);
$pdf->Cell(35,$h_line, utf8_decode('Observaciones:'),1); 
$pdf->SetFont('Arial','',9);
// MultiCell puede ocupar mucho, cuidado
$pdf->MultiCell(0,$h_line, utf8_decode($bien['observaciones']),1,'L');

// --- 2. FICHA TÉCNICA (Compacta) ---
if ($bien['mat_tipo_carga_id']) {
    $pdf->Ln(3); // Espacio pequeño
    $pdf->SetFont('Arial','B',10);
    $pdf->Cell(0,6, utf8_decode('2. ESPECIFICACIONES TÉCNICAS'),1,1,'L',true);
    
    // Fila 1
    $pdf->SetFont('Arial','B',9);
    $pdf->Cell(25,$h_line, utf8_decode('Tipo Agente:'),1);
    $pdf->SetFont('Arial','',9);
    $pdf->Cell(70,$h_line, utf8_decode($bien['tipo_carga']),1);
    
    $pdf->SetFont('Arial','B',9);
    $pdf->Cell(25,$h_line, utf8_decode('Capacidad:'),1);
    $pdf->SetFont('Arial','',9);
    $pdf->Cell(25,$h_line, $bien['mat_capacidad'].' Kg',1);
    
    $pdf->SetFont('Arial','B',9);
    $pdf->Cell(20,$h_line, utf8_decode('Clase:'),1);
    $pdf->SetFont('Arial','',9);
    $pdf->Cell(0,$h_line, utf8_decode($bien['nombre_clase']),1,1);

    // Fila 2
    $pdf->SetFont('Arial','B',9);
    $pdf->Cell(25,$h_line, utf8_decode('Fabricación:'),1);
    $pdf->SetFont('Arial','',9);
    $pdf->Cell(70,$h_line, $bien['fecha_fabricacion'],1);
    
    $pdf->SetFont('Arial','B',9);
    $pdf->Cell(25,$h_line, utf8_decode('Vida Útil:'),1);
    $pdf->SetFont('Arial','',9);
    $pdf->Cell(0,$h_line, $bien['vida_util_limite'].' '.utf8_decode('años'),1,1);

    // Fila 3
    $pdf->SetFont('Arial','B',9);
    $pdf->Cell(25,$h_line, utf8_decode('Vto Carga:'),1);
    $pdf->SetFont('Arial','',9);
    $fecha_carga = $bien['mat_fecha_carga'] ? date('d/m/Y', strtotime($bien['mat_fecha_carga']. ' +1 year')) : '-';
    $pdf->Cell(70,$h_line, $fecha_carga, 1);
    
    $pdf->SetFont('Arial','B',9);
    $pdf->Cell(25,$h_line, utf8_decode('Vto P.H.:'),1);
    $pdf->SetFont('Arial','',9);
    $fecha_ph = $bien['mat_fecha_ph'] ? date('d/m/Y', strtotime($bien['mat_fecha_ph']. ' +1 year')) : '-';
    $pdf->Cell(0,$h_line, $fecha_ph, 1, 1);
}

// --- FECHA Y LUGAR (Movido AQUÍ abajo) ---
$pdf->Ln(3);
$fecha_obj = new DateTime($bien['fecha_creacion']);
$meses = ['Enero','Febrero','Marzo','Abril','Mayo','Junio','Julio','Agosto','Septiembre','Octubre','Noviembre','Diciembre'];
$mes = $meses[$fecha_obj->format('n')-1];
$fecha_texto = "Ciudad Autónoma de Buenos Aires, " . $fecha_obj->format('d') . " de " . $mes . " de " . $fecha_obj->format('Y');

$pdf->SetFont('Arial','I',10);
$pdf->Cell(0, 6, utf8_decode($fecha_texto), 0, 1, 'R');

// --- 3. FIRMAS ---
$pdf->Ln(5); // Espacio antes de firmas
$pdf->SetFont('Arial','B',10);
$pdf->Cell(0,6, utf8_decode('3. CONFORMIDAD Y FIRMAS'),0,1,'L');

// Configuración de firmas
$y_inicio = $pdf->GetY() + 2; // Un pelín de margen
$alto_firma = 35; // Altura de la caja de firma
$ancho_col = 85;
$offset_col2 = 95; // Separación horizontal

// --- FILA 1 ---

// 1.1 Responsable
$pdf->SetXY(10, $y_inicio);
// AJUSTE CLAVE: Bajamos la imagen (+12 en vez de +5) para que toque la línea de abajo
if($bien['firma_responsable'] && file_exists($bien['firma_responsable'])) {
    $pdf->Image($bien['firma_responsable'], 25, $y_inicio + 12, 40); 
}
// La línea va abajo
$pdf->SetXY(10, $y_inicio + $alto_firma);
$pdf->Cell($ancho_col, 5, utf8_decode('Firma Responsable a Cargo'), 'T', 1, 'C');
$pdf->SetX(10);
$pdf->SetFont('Arial','',8);
$pdf->Cell($ancho_col, 4, utf8_decode($bien['nombre_responsable']), 0, 0, 'C');

// 1.2 Jefe Servicio
$pdf->SetXY(10 + $offset_col2, $y_inicio);
if($bien['firma_jefe'] && file_exists($bien['firma_jefe'])) {
    $pdf->Image($bien['firma_jefe'], 10 + $offset_col2 + 25, $y_inicio + 12, 40);
}
$pdf->SetXY(10 + $offset_col2, $y_inicio + $alto_firma);
$pdf->SetFont('Arial','B',10);
$pdf->Cell($ancho_col, 5, utf8_decode('Firma Jefe de Servicio'), 'T', 1, 'C');
$pdf->SetXY(10 + $offset_col2, $pdf->GetY());
$pdf->SetFont('Arial','',8);
$pdf->Cell($ancho_col, 4, utf8_decode($bien['nombre_jefe_servicio']), 0, 0, 'C');


// --- FILA 2 ---
$y_fila2 = $y_inicio + $alto_firma + 15; // Espacio entre filas de firmas

// 2.1 Relevador
$pdf->SetXY(10, $y_fila2);
if(!empty($bien['firma_relevador']) && file_exists($bien['firma_relevador'])) {
    $pdf->Image($bien['firma_relevador'], 25, $y_fila2 + 12, 40);
}
$pdf->SetXY(10, $y_fila2 + $alto_firma);
$pdf->SetFont('Arial','B',10);
$pdf->Cell($ancho_col, 5, utf8_decode('Firma Relevador (Logística)'), 'T', 1, 'C');
$pdf->SetX(10);
$pdf->SetFont('Arial','',8);
$pdf->Cell($ancho_col, 4, utf8_decode('Dpto. Logística'), 0, 0, 'C');

// 2.2 Patrimonial
$pdf->SetXY(10 + $offset_col2, $y_fila2);
$pdf->SetXY(10 + $offset_col2, $y_fila2 + $alto_firma);
$pdf->SetFont('Arial','B',10);
$pdf->Cell($ancho_col, 5, utf8_decode('Encargada Cargo Patrimonial'), 'T', 1, 'C');
$pdf->SetXY(10 + $offset_col2, $pdf->GetY());
$pdf->SetFont('Arial','',8);
$pdf->Cell($ancho_col, 4, utf8_decode('Verificación y Control'), 0, 0, 'C');

$pdf->Output('I', 'Acta_Inventario_'.$bien['id_cargo'].'.pdf');
?>