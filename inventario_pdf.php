<?php
// Archivo: inventario_pdf.php (EN LA RAÍZ)
require('fpdf/fpdf.php');
include 'conexion.php';

$ubicacion_nombre = "";

// ---------------------------------------------------------
// 1. LÓGICA INTELIGENTE PARA DETECTAR LA UBICACIÓN
// ---------------------------------------------------------
if (isset($_GET['id'])) {
    $id_recibido = $_GET['id'];
    
    // PASO A: Intentar ver si el ID es de un DESTINO (Tabla destinos_internos)
    $stmt = $pdo->prepare("SELECT nombre FROM destinos_internos WHERE id_destino = ?");
    $stmt->execute([$id_recibido]);
    $resultado_destino = $stmt->fetchColumn();
    
    if ($resultado_destino) {
        $ubicacion_nombre = $resultado_destino;
    } else {
        // PASO B: Si no es destino, seguro es un ID DE CARGO (Tabla inventario_cargos)
        // Buscamos a qué ubicación pertenece ese bien (ID 203)
        $stmt2 = $pdo->prepare("SELECT servicio_ubicacion FROM inventario_cargos WHERE id_cargo = ?");
        $stmt2->execute([$id_recibido]);
        $resultado_bien = $stmt2->fetchColumn();
        
        if ($resultado_bien) {
            $ubicacion_nombre = $resultado_bien;
        } else {
            die("<h2>Error: No se encontró nada con el ID $id_recibido</h2><p>Ni destino, ni bien inventariado.</p>");
        }
    }

} elseif (isset($_GET['ubicacion'])) {
    // Si viene texto directo (?ubicacion=Farmacia)
    $ubicacion_nombre = $_GET['ubicacion'];
} else {
    die("<h2>Error: Falta información.</h2><p>El enlace debe tener ?id=NUMERO o ?ubicacion=NOMBRE</p>");
}

// ---------------------------------------------------------
// 2. BUSCAR TODOS LOS BIENES DE ESA UBICACIÓN
// ---------------------------------------------------------
$sql = "SELECT * FROM inventario_cargos WHERE servicio_ubicacion = :ub ORDER BY elemento ASC";
$stmt = $pdo->prepare($sql);
$stmt->execute([':ub' => $ubicacion_nombre]);
$bienes = $stmt->fetchAll(PDO::FETCH_ASSOC);

if (count($bienes) == 0) {
    die("<h1>Inventario Vacío</h1><p>La ubicación <strong>$ubicacion_nombre</strong> no tiene bienes cargados todavía.</p>");
}

// 3. DATOS DE LAS FIRMAS (Tomamos del primer bien, ya que se firma por lote)
$p = $bienes[0];
$firma_resp = $p['firma_responsable'] ?? ''; 
$nombre_resp = $p['nombre_responsable'] ?? 'A Designar';
$firma_jefe = $p['firma_jefe'] ?? '';
$nombre_jefe = $p['nombre_jefe_servicio'] ?? 'A Designar';

// ---------------------------------------------------------
// 4. GENERACIÓN DEL PDF
// ---------------------------------------------------------
class PDF extends FPDF {
    public $nombre_lugar;
    function Header() {
        // Logo (Ruta relativa desde la raíz)
        if(file_exists('assets/img/sgalp.png')) {
            $this->Image('assets/img/sgalp.png', 10, 8, 30);
        }
        $this->SetFont('Arial', 'B', 14);
        $this->Cell(0, 10, mb_convert_encoding('PLANILLA DE CARGO PATRIMONIAL', 'ISO-8859-1', 'UTF-8'), 0, 1, 'C');
        $this->SetFont('Arial', '', 10);
        $this->Cell(0, 8, mb_convert_encoding('Ubicación: ' . $this->nombre_lugar, 'ISO-8859-1', 'UTF-8'), 0, 1, 'C');
        $this->Ln(5);
        
        $this->SetFillColor(230);
        $this->SetFont('Arial', 'B', 8);
        $this->Cell(15, 8, 'ID', 1, 0, 'C', true);
        $this->Cell(25, 8, mb_convert_encoding('Código', 'ISO-8859-1', 'UTF-8'), 1, 0, 'C', true);
        $this->Cell(80, 8, 'Elemento / Descripción', 1, 0, 'C', true);
        $this->Cell(25, 8, 'Estado', 1, 0, 'C', true);
        $this->Cell(45, 8, 'Observaciones', 1, 1, 'C', true);
    }
    function Footer() {
        $this->SetY(-15);
        $this->SetFont('Arial', 'I', 8);
        $this->Cell(0, 10, mb_convert_encoding('Pág ' . $this->PageNo() . '/{nb}', 'ISO-8859-1', 'UTF-8'), 0, 0, 'C');
    }
}

$pdf = new PDF();
$pdf->AliasNbPages();
$pdf->nombre_lugar = $ubicacion_nombre;
$pdf->AddPage();
$pdf->SetFont('Arial', '', 8);

foreach ($bienes as $b) {
    // Recorte de textos largos
    $elem = substr($b['elemento'], 0, 55);
    $obs = substr($b['observaciones'], 0, 25);
    
    $pdf->Cell(15, 8, $b['id_cargo'], 1, 0, 'C');
    $pdf->Cell(25, 8, $b['codigo_inventario'], 1, 0, 'C');
    $pdf->Cell(80, 8, mb_convert_encoding($elem, 'ISO-8859-1', 'UTF-8'), 1, 0, 'L');
    $pdf->Cell(25, 8, mb_convert_encoding($b['estado'], 'ISO-8859-1', 'UTF-8'), 1, 0, 'C');
    $pdf->Cell(45, 8, mb_convert_encoding($obs, 'ISO-8859-1', 'UTF-8'), 1, 1, 'L');
}

// ---------------------------------------------------------
// 5. SECCIÓN DE FIRMAS
// ---------------------------------------------------------
$pdf->Ln(15);
// Si queda poco espacio, saltamos de hoja
if ($pdf->GetY() > 230) $pdf->AddPage();
$y_firmas = $pdf->GetY();

// --- Firma Responsable (Izquierda) ---
$pdf->SetXY(20, $y_firmas);
if (!empty($firma_resp) && file_exists($firma_resp)) {
    // La imagen está en uploads/firmas/... (desde la raíz)
    $pdf->Image($firma_resp, 30, $y_firmas, 40, 15);
    $pdf->SetXY(20, $y_firmas + 15);
} else {
    $pdf->SetXY(20, $y_firmas + 15);
}
$pdf->Cell(60, 0, '', 'T'); // Línea
$pdf->Ln(2);
$pdf->SetX(20);
$pdf->Cell(60, 4, mb_convert_encoding($nombre_resp, 'ISO-8859-1', 'UTF-8'), 0, 1, 'C');
$pdf->SetX(20);
$pdf->SetFont('Arial', 'B', 7);
$pdf->Cell(60, 4, 'RESPONSABLE A CARGO', 0, 0, 'C');


// --- Firma Jefe (Derecha) ---
$pdf->SetFont('Arial', '', 8);
$pdf->SetXY(120, $y_firmas);
if (!empty($firma_jefe) && file_exists($firma_jefe)) {
    $pdf->Image($firma_jefe, 130, $y_firmas, 40, 15);
    $pdf->SetXY(120, $y_firmas + 15);
} else {
    $pdf->SetXY(120, $y_firmas + 15);
}
$pdf->Cell(60, 0, '', 'T'); // Línea
$pdf->Ln(2);
$pdf->SetX(120);
$pdf->Cell(60, 4, mb_convert_encoding($nombre_jefe, 'ISO-8859-1', 'UTF-8'), 0, 1, 'C');
$pdf->SetX(120);
$pdf->SetFont('Arial', 'B', 7);
$pdf->Cell(60, 4, 'JEFE DE SERVICIO / AVAL', 0, 0, 'C');

// ---------------------------------------------------------
// 6. GUARDAR Y MOSTRAR
// ---------------------------------------------------------
// Limpieza de nombre de archivo
$nombre_limpio = preg_replace('/[^a-zA-Z0-9]/', '_', $ubicacion_nombre);
$nombre_archivo = "Inventario_" . $nombre_limpio . ".pdf";

// Carpeta destino (Asegurarse que exista)
$carpeta_destino = 'pdfs_publicos/inventario/';
if (!file_exists($carpeta_destino)) {
    mkdir($carpeta_destino, 0777, true);
}

$ruta_completa = $carpeta_destino . $nombre_archivo;

// 'F' guarda el archivo en el servidor
$pdf->Output('F', $ruta_completa);

// Redirigir al usuario al archivo generado
header("Location: " . $ruta_completa);
exit;
?>