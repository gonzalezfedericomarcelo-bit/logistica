<?php
// Archivo: inventario_movimientos_pdf.php
session_start(); // <--- ESTO FALTABA Y POR ESO TE PATEABA
require('fpdf/fpdf.php');
include 'conexion.php';
include 'funciones_permisos.php'; // <--- ESTO TAMBIÉN FALTABA

// Lógica de Permisos: Historial General O Transferencias
if (!isset($_SESSION['usuario_id']) || (!tiene_permiso('inventario_historial', $pdo) && !tiene_permiso('inventario_ver_transferencias', $pdo))) {
    header("Location: inventario_lista.php"); exit();
}

// =======================================================
// MODO 1: REPORTE INDIVIDUAL (COMPROBANTE)
// =======================================================
if (!empty($_GET['id']) && is_numeric($_GET['id'])) {
    
    $id = $_GET['id'];
    
    $sql = "SELECT h.*, i.elemento, i.codigo_inventario, i.mat_numero_grabado, u.nombre_completo as usuario 
            FROM historial_movimientos h 
            LEFT JOIN inventario_cargos i ON h.id_bien = i.id_cargo 
            LEFT JOIN usuarios u ON h.usuario_registro = u.id_usuario 
            WHERE h.id_movimiento = ?";
            
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$id]);
    $mov = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$mov) {
        die("Error: El movimiento #$id no existe.");
    }

    class PDF_Individual extends FPDF {
        function Header() {
            $this->SetFont('Arial','B',16);
            $this->Cell(0,10, utf8_decode('COMPROBANTE DE MOVIMIENTO'),0,1,'C');
            $this->SetFont('Arial','',10);
            $this->Cell(0,5, utf8_decode('Sistema de Logística'),0,1,'C');
            $this->Line(10, 30, 200, 30);
            $this->Ln(15);
        }
        function Footer() {
            $this->SetY(-15);
            $this->SetFont('Arial','I',8);
            $this->Cell(0,10, utf8_decode('Generado el '.date('d/m/Y H:i')),0,0,'C');
        }
    }

    $pdf = new PDF_Individual('P','mm','A4');
    $pdf->AddPage();
    $pdf->SetFont('Arial','',12);

    $pdf->SetFillColor(245, 245, 245);
    $pdf->Rect(10, 40, 190, 120, 'F');
    $pdf->Rect(10, 40, 190, 120);

    $pdf->SetXY(20, 50);

    // Datos
    $pdf->SetFont('Arial','B',12); $pdf->Cell(40,10,'ID Registro:',0,0);
    $pdf->SetFont('Arial','',12);  $pdf->Cell(0,10,'#'.$mov['id_movimiento'],0,1);
    
    $pdf->SetX(20);
    $pdf->SetFont('Arial','B',12); $pdf->Cell(40,10,'Fecha:',0,0);
    $pdf->SetFont('Arial','',12);  $pdf->Cell(0,10,date('d/m/Y H:i', strtotime($mov['fecha_movimiento'])),0,1);

    $pdf->SetX(20);
    $pdf->SetFont('Arial','B',12); $pdf->Cell(40,10,'Responsable:',0,0);
    $pdf->SetFont('Arial','',12);  $pdf->Cell(0,10,utf8_decode($mov['usuario'] ?? 'Sistema'),0,1);

    $pdf->Ln(5);
    $pdf->SetX(20);
    $pdf->SetFont('Arial','B',12); $pdf->Cell(40,10,'Bien:',0,0);
    $pdf->SetFont('Arial','',12);  
    $pdf->MultiCell(130,10,utf8_decode($mov['elemento']));

    $pdf->Ln(2);
    $pdf->SetX(20);
    $pdf->SetFont('Arial','B',12); $pdf->Cell(40,10,utf8_decode('Acción:'),0,0);
    $pdf->SetFont('Arial','B',14); 
    $pdf->Cell(0,10,utf8_decode($mov['tipo_movimiento']),0,1);

    $pdf->Ln(5);
    $pdf->SetX(20);
    $pdf->SetFont('Arial','B',12); $pdf->Cell(40,10,'Detalles:',0,1);
    $pdf->SetX(20);
    $pdf->SetFont('Arial','',11);
    
    // Si es transferencia, mostramos origen y destino guardados
    $detalle = $mov['observacion_movimiento'];
    if(stripos($mov['tipo_movimiento'], 'Transferencia') !== false) {
        $detalle .= "\n\nDESDE: " . ($mov['ubicacion_anterior'] ?? '-');
        $detalle .= "\nHACIA: " . ($mov['ubicacion_nueva'] ?? '-');
    }
    
    $pdf->MultiCell(170, 7, utf8_decode($detalle), 0, 'L');

    $pdf->Output('I', 'Movimiento_'.$id.'.pdf');
    exit;
}

// =======================================================
// MODO 2: REPORTE GLOBAL (LISTADO)
// =======================================================

$where = "1=1";
$params = [];
$texto_filtros = "";

if (!empty($_GET['fecha_desde'])) {
    $where .= " AND DATE(h.fecha_movimiento) >= ?";
    $params[] = $_GET['fecha_desde'];
    $texto_filtros .= " Desde: " . date('d/m/Y', strtotime($_GET['fecha_desde']));
}
if (!empty($_GET['fecha_hasta'])) {
    $where .= " AND DATE(h.fecha_movimiento) <= ?";
    $params[] = $_GET['fecha_hasta'];
    $texto_filtros .= " Hasta: " . date('d/m/Y', strtotime($_GET['fecha_hasta']));
}
if (!empty($_GET['tipo_movimiento'])) {
    $where .= " AND h.tipo_movimiento LIKE ?";
    $params[] = "%" . $_GET['tipo_movimiento'] . "%";
    $texto_filtros .= " Tipo: " . $_GET['tipo_movimiento'];
}
if (!empty($_GET['q'])) {
    $term = "%" . $_GET['q'] . "%";
    $where .= " AND (i.elemento LIKE ? OR u.nombre_completo LIKE ? OR h.observacion_movimiento LIKE ?)";
    $params[] = $term; $params[] = $term; $params[] = $term;
    $texto_filtros .= " Busq: " . $_GET['q'];
}

$sql = "SELECT h.*, i.elemento, u.nombre_completo as usuario 
        FROM historial_movimientos h 
        LEFT JOIN inventario_cargos i ON h.id_bien = i.id_cargo 
        LEFT JOIN usuarios u ON h.usuario_registro = u.id_usuario 
        WHERE $where 
        ORDER BY h.fecha_movimiento DESC LIMIT 1000";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$datos = $stmt->fetchAll(PDO::FETCH_ASSOC);

class PDF_Listado extends FPDF {
    function Header() {
        global $texto_filtros;
        $this->SetFont('Arial','B',14);
        $this->Cell(0,10, utf8_decode('Reporte de Movimientos'),0,1,'C');
        if($texto_filtros) {
            $this->SetFont('Arial','I',8);
            $this->Cell(0,5, utf8_decode($texto_filtros),0,1,'C');
        }
        $this->Ln(5);
        
        $this->SetFillColor(230,230,230);
        $this->SetFont('Arial','B',8);
        $this->Cell(30,6, 'Fecha',1,0,'C',true);
        $this->Cell(60,6, 'Bien',1,0,'C',true);
        $this->Cell(30,6, utf8_decode('Acción'),1,0,'C',true);
        $this->Cell(30,6, 'Usuario',1,0,'C',true);
        $this->Cell(127,6, 'Detalle',1,1,'C',true); 
    }
    function Footer() {
        $this->SetY(-15);
        $this->SetFont('Arial','I',8);
        $this->Cell(0,10, utf8_decode('Pág ').$this->PageNo(),0,0,'C');
    }
}

$pdf = new PDF_Listado('L','mm','A4');
$pdf->AliasNbPages();
$pdf->AddPage();
$pdf->SetFont('Arial','',8);

foreach ($datos as $fila) {
    $fecha = date('d/m/y H:i', strtotime($fila['fecha_movimiento']));
    $bien = utf8_decode(substr($fila['elemento'] ?? 'Eliminado', 0, 35));
    $tipo = utf8_decode($fila['tipo_movimiento']);
    $user = utf8_decode(substr($fila['usuario'] ?? '-', 0, 18));
    
    // Detalle inteligente
    $detalle = $fila['observacion_movimiento'];
    if(stripos($fila['tipo_movimiento'], 'Transferencia') !== false) {
        $origen = $fila['ubicacion_anterior'] ?: '?';
        $destino = $fila['ubicacion_nueva'] ?: '?';
        $detalle = "De: $origen -> A: $destino";
    }
    
    $pdf->Cell(30,6, $fecha, 1);
    $pdf->Cell(60,6, $bien, 1);
    $pdf->Cell(30,6, $tipo, 1, 0, 'C');
    $pdf->Cell(30,6, $user, 1);
    $pdf->Cell(127,6, substr(utf8_decode($detalle), 0, 85), 1);
    $pdf->Ln();
}

$pdf->Output('I', 'Reporte_Global.pdf');
?>