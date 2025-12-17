<?php
// Archivo: inventario_pdf.php
session_start();
require('fpdf/fpdf.php');
include 'conexion.php';
include 'funciones_permisos.php';

// ============================================================================
// 1. LÓGICA DE SEGURIDAD
// ============================================================================
$acceso_permitido = false;

// A. Verificar usuario logueado (Admin o con permisos)
if (isset($_SESSION['usuario_id']) && (tiene_permiso('inventario_reportes', $pdo) || tiene_permiso('inventario_historial', $pdo))) {
    $acceso_permitido = true;
}

// B. Excepción por TOKEN (Acceso Externo)
// IMPORTANTE: Se permite el acceso si el token existe para ese bien, SIN importar el estado (pendiente o confirmado)
if (!$acceso_permitido && isset($_GET['token']) && isset($_GET['id'])) {
    $token_check = $_GET['token'];
    $id_bien_check = $_GET['id'];
    
    // Verificamos si existe el token asociado al bien
    $stmtCheck = $pdo->prepare("SELECT id_token FROM inventario_transferencias_pendientes 
                                WHERE token_hash = ? AND id_bien = ?");
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

// ============================================================================
// 3. GENERAR PDF (ACTA INDIVIDUAL / ACTUAL)
// ============================================================================
class PDF extends FPDF {
    function Header() {
        // $this->Image('logo.png',10,6,30); // Descomentar si hay logo
        $this->SetFont('Arial','B',14);
        $this->Cell(0,10, utf8_decode('ACTA DE ENTREGA / CARGO INDIVIDUAL'),0,1,'C');
        $this->Ln(5);
    }

    function Footer() {
        $this->SetY(-15);
        $this->SetFont('Arial','I',8);
        $this->Cell(0,10, utf8_decode('Página ').$this->PageNo(),0,0,'C');
    }
}

$pdf = new PDF();
$pdf->AddPage();
$pdf->SetFont('Arial','',11);

// --- DATOS GENERALES ---
$pdf->SetFillColor(230,230,230);
$pdf->SetFont('Arial','B',11);
$pdf->Cell(0,8, utf8_decode('IDENTIFICACIÓN DEL BIEN'),1,1,'L',true);
$pdf->SetFont('Arial','',10);

$pdf->Cell(40,8, utf8_decode('Código Interno:'),1); 
$pdf->Cell(55,8, utf8_decode($bien['codigo_inventario']),1);
$pdf->Cell(40,8, utf8_decode('N° Grabado/Serie:'),1); 
$pdf->Cell(0,8, utf8_decode($bien['mat_numero_grabado']),1,1);

$pdf->Cell(40,8, utf8_decode('Elemento:'),1); 
$pdf->Cell(0,8, utf8_decode($bien['elemento']),1,1);

$pdf->Cell(40,8, utf8_decode('Ubicación:'),1); 
$pdf->Cell(0,8, utf8_decode($bien['destino_principal'] . ' - ' . $bien['servicio_ubicacion']),1,1);

$pdf->Cell(40,8, utf8_decode('Estado:'),1); 
$pdf->Cell(0,8, utf8_decode($bien['nombre_estado']),1,1);

$pdf->Cell(40,8, utf8_decode('Observaciones:'),1); 
$pdf->Cell(0,8, utf8_decode($bien['observaciones']),1,1);

// --- DATOS TÉCNICOS (SI ES MATAFUEGO) ---
if ($bien['mat_tipo_carga_id']) {
    $pdf->Ln(5);
    $pdf->SetFont('Arial','B',11);
    $pdf->Cell(0,8, utf8_decode('FICHA TÉCNICA (MATAFUEGO)'),1,1,'L',true);
    $pdf->SetFont('Arial','',10);

    $pdf->Cell(30,8, utf8_decode('Tipo Carga:'),1); $pdf->Cell(65,8, utf8_decode($bien['tipo_carga']),1);
    $pdf->Cell(30,8, utf8_decode('Capacidad:'),1); $pdf->Cell(20,8, $bien['mat_capacidad'].' Kg',1);
    $pdf->Cell(20,8, utf8_decode('Clase:'),1); $pdf->Cell(0,8, utf8_decode($bien['nombre_clase']),1,1);

    $pdf->Cell(30,8, utf8_decode('Fabricación:'),1); $pdf->Cell(65,8, $bien['fecha_fabricacion'],1);
    $pdf->Cell(30,8, utf8_decode('Vida Útil:'),1); $pdf->Cell(0,8, $bien['vida_util_limite'],1,1);

    $pdf->Cell(30,8, utf8_decode('Vto Carga:'),1); 
    $pdf->Cell(65,8, $bien['mat_fecha_carga'] ? date('d/m/Y', strtotime($bien['mat_fecha_carga']. ' +1 year')) : '-',1);
    $pdf->Cell(30,8, utf8_decode('Vto PH:'),1); 
    $pdf->Cell(0,8, $bien['mat_fecha_ph'] ? date('d/m/Y', strtotime($bien['mat_fecha_ph']. ' +1 year')) : '-',1,1);
    
    $pdf->Cell(30,8, utf8_decode('Técnico:'),1); $pdf->Cell(0,8, utf8_decode($bien['nombre_tecnico']),1,1);
}

// --- FIRMAS ---
$pdf->Ln(20);
$pdf->SetFont('Arial','B',11);
$pdf->Cell(0,8, utf8_decode('RESPONSABLES ASIGNADOS'),0,1,'L');
$pdf->Ln(5);

$y = $pdf->GetY();

// Firma Responsable
$pdf->SetXY(20, $y);
if($bien['firma_responsable'] && file_exists($bien['firma_responsable'])) {
    $pdf->Image($bien['firma_responsable'], 30, $y, 40);
}
$pdf->SetXY(20, $y+35);
$pdf->Cell(60,5, utf8_decode('Responsable a Cargo'), 'T', 1, 'C');
$pdf->SetX(20);
$pdf->SetFont('Arial','',9);
$pdf->Cell(60,5, utf8_decode($bien['nombre_responsable']), 0, 0, 'C');

// Firma Jefe
$pdf->SetXY(120, $y);
if($bien['firma_jefe'] && file_exists($bien['firma_jefe'])) {
    $pdf->Image($bien['firma_jefe'], 130, $y, 40);
}
$pdf->SetXY(120, $y+35);
$pdf->SetFont('Arial','B',11);
$pdf->Cell(60,5, utf8_decode('Jefe de Servicio'), 'T', 1, 'C');
$pdf->SetX(120);
$pdf->SetFont('Arial','',9);
$pdf->Cell(60,5, utf8_decode($bien['nombre_jefe_servicio']), 0, 0, 'C');

$pdf->Output('I', 'Acta_Inventario_'.$bien['id_cargo'].'.pdf');
?>