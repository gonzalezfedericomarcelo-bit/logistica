<?php
// Archivo: inventario_transferir_procesar.php
session_start();
include 'conexion.php';
require('fpdf/fpdf.php'); 

if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !isset($_SESSION['usuario_id'])) {
    header("Location: dashboard.php"); exit();
}

// ============================================================================
// 1. PROCESAR FIRMAS NUEVAS (RECIBIDAS DEL FORMULARIO)
// ============================================================================
$ruta_firmas = __DIR__ . '/uploads/firmas/';
if (!file_exists($ruta_firmas)) mkdir($ruta_firmas, 0777, true);

function saveSig($base64, $prefix, $path) {
    if ($base64 == 'SAME') return 'SAME';
    // Limpieza de cabecera base64
    $data = base64_decode(preg_replace('#^data:image/\w+;base64,#i', '', $base64));
    $filename = $prefix . '_' . uniqid() . '.png';
    file_put_contents($path . $filename, $data);
    return 'uploads/firmas/' . $filename; // Ruta relativa para BD y FPDF
}

$firma_new_resp = saveSig($_POST['base64_responsable'], 'transf_resp', $ruta_firmas);
$firma_new_jefe = saveSig($_POST['base64_jefe'], 'transf_jefe', $ruta_firmas);

$nombre_new_resp = $_POST['nuevo_responsable_nombre'];
$nombre_new_jefe = $_POST['nuevo_jefe_nombre'];

if ($firma_new_jefe === 'SAME') {
    $firma_new_jefe = $firma_new_resp;
    $nombre_new_jefe = $nombre_new_resp;
}

// ============================================================================
// 2. PREPARAR DATOS (DESTINO NUEVO VS ORIGEN)
// ============================================================================
// Datos NUEVOS (Vienen del POST)
$id_destino_nuevo = $_POST['select_destino'];
$nombre_area_nueva = $_POST['nueva_area'];
if ($nombre_area_nueva === 'General' || $nombre_area_nueva === 'General / Única') $nombre_area_nueva = ''; 

// Buscar nombre del destino nuevo
$stmtDest = $pdo->prepare("SELECT nombre FROM destinos_internos WHERE id_destino = ?");
$stmtDest->execute([$id_destino_nuevo]);
$rowDest = $stmtDest->fetch(PDO::FETCH_ASSOC);
$nombre_destino_nuevo = $rowDest ? $rowDest['nombre'] : 'Desconocido';

$token = bin2hex(random_bytes(32)); 
$id_bien = $_POST['id_bien'];

// Datos VIEJOS/ACTUALES (Vienen de la DB)
// Es crucial hacer esto ANTES de insertar nada para tener la "foto" vieja intacta.
$sqlBien = "SELECT i.*, e.nombre as nombre_estado, m.tipo_carga, c.nombre as nombre_clase
            FROM inventario_cargos i 
            LEFT JOIN inventario_estados e ON i.id_estado_fk = e.id_estado
            LEFT JOIN inventario_config_matafuegos m ON i.mat_tipo_carga_id = m.id_config
            LEFT JOIN inventario_config_clases c ON i.mat_clase_id = c.id_clase
            WHERE i.id_cargo = ?";
$stmtB = $pdo->prepare($sqlBien);
$stmtB->execute([$id_bien]);
$bien = $stmtB->fetch(PDO::FETCH_ASSOC);

// ============================================================================
// 3. GENERAR PDFs FÍSICOS
// ============================================================================
$ruta_pdfs_abs = __DIR__ . '/pdfs_publicos/inventario_pdf/';
if (!file_exists($ruta_pdfs_abs)) mkdir($ruta_pdfs_abs, 0777, true);

// Clase PDF Base
class PDF_Base extends FPDF {
    function Header() {
        $this->SetFont('Arial','B',14);
        $this->Cell(0,10, utf8_decode('ACTA DE ENTREGA / CARGO INDIVIDUAL'),0,1,'C');
        $this->Ln(5);
    }
    function Footer() {
        $this->SetY(-15);
        $this->SetFont('Arial','I',8);
        $this->Cell(0,10, utf8_decode('Generado el '.date('d/m/Y H:i')),0,0,'C');
    }
    function BloqueBien($b) {
        $this->SetFillColor(230,230,230);
        $this->SetFont('Arial','B',10);
        $this->Cell(0,7, utf8_decode('DATOS DEL BIEN'),1,1,'L',true);
        $this->SetFont('Arial','',10);
        $this->Cell(30,7, 'Elemento:',1); $this->Cell(0,7, utf8_decode($b['elemento']),1,1);
        $this->Cell(30,7, utf8_decode('Código:'),1); $this->Cell(60,7, utf8_decode($b['codigo_inventario']),1);
        $this->Cell(30,7, 'Serie:',1); $this->Cell(0,7, utf8_decode($b['mat_numero_grabado']),1,1);
        $this->Ln(5);
    }
}

// --- A. PDF ANTIGUO (USAMOS SOLO DATOS DE $bien) ---
$pdfOld = new PDF_Base();
$pdfOld->AddPage();
$pdfOld->BloqueBien($bien);

$pdfOld->SetFont('Arial','B',10);
$pdfOld->Cell(0,7, utf8_decode('ESTADO ACTUAL (PREVIO A TRANSFERENCIA)'),1,1,'L',true);
$pdfOld->SetFont('Arial','',10);
// AQUÍ ESTABA EL ERROR: Usamos estrictamente $bien, NO las variables nuevas
$pdfOld->Cell(30,7, utf8_decode('Ubicación:'),1); $pdfOld->Cell(0,7, utf8_decode($bien['destino_principal'] . ' - ' . $bien['servicio_ubicacion']),1,1);
$pdfOld->Cell(30,7, 'Responsable:',1); $pdfOld->Cell(0,7, utf8_decode($bien['nombre_responsable']),1,1);

// Firmas Viejas (Desde DB)
$pdfOld->Ln(20);
$y = $pdfOld->GetY();
if(!empty($bien['firma_responsable']) && file_exists(__DIR__ . '/' . $bien['firma_responsable'])) {
    $pdfOld->Image(__DIR__ . '/' . $bien['firma_responsable'], 30, $y, 30);
}
if(!empty($bien['firma_jefe']) && file_exists(__DIR__ . '/' . $bien['firma_jefe'])) {
    $pdfOld->Image(__DIR__ . '/' . $bien['firma_jefe'], 130, $y, 30);
}
// Nombres viejos debajo de firmas viejas
$pdfOld->SetXY(20, $y+30); $pdfOld->Cell(60,5, utf8_decode('Responsable Actual'), 'T', 1, 'C');
$pdfOld->SetX(20); $pdfOld->Cell(60,5, utf8_decode($bien['nombre_responsable']), 0, 0, 'C'); // <--- Nombre Viejo

$pdfOld->SetXY(120, $y+30); $pdfOld->Cell(60,5, utf8_decode('Jefe Servicio Actual'), 'T', 1, 'C');
$pdfOld->SetX(120); $pdfOld->Cell(60,5, utf8_decode($bien['nombre_jefe_servicio']), 0, 0, 'C'); // <--- Nombre Viejo

$pdfOld->Output('F', $ruta_pdfs_abs . 'old_' . $token . '.pdf');


// --- B. PDF NUEVO (USAMOS DATOS VIEJOS PARA ORIGEN Y NUEVOS PARA DESTINO) ---
$pdfNew = new PDF_Base();
$pdfNew->AddPage();
$pdfNew->BloqueBien($bien);

$pdfNew->SetFont('Arial','B',10);
$pdfNew->Cell(0,7, utf8_decode('DETALLE DE TRANSFERENCIA'),1,1,'L',true);
$pdfNew->SetFont('Arial','',10);
// Origen (Viejo)
$pdfNew->Cell(30,7, utf8_decode('Se retira de:'),1); $pdfNew->Cell(0,7, utf8_decode($bien['destino_principal'] . ' - ' . $bien['servicio_ubicacion']),1,1);
$pdfNew->Cell(30,7, utf8_decode('Entrega:'),1); $pdfNew->Cell(0,7, utf8_decode($bien['nombre_responsable']),1,1);

$pdfNew->Ln(2);
$pdfNew->SetFont('Arial','B',10);
// Destino (Nuevo)
$pdfNew->Cell(30,7, utf8_decode('Se incorpora a:'),1); $pdfNew->Cell(0,7, utf8_decode($nombre_destino_nuevo . ' - ' . $nombre_area_nueva),1,1);
$pdfNew->Cell(30,7, utf8_decode('Recibe:'),1); $pdfNew->Cell(0,7, utf8_decode($nombre_new_resp),1,1);

// Firmas Nuevas (Del POST)
$pdfNew->Ln(20);
$y = $pdfNew->GetY();
if($firma_new_resp != 'SAME' && file_exists(__DIR__ . '/' . $firma_new_resp)) {
    $pdfNew->Image(__DIR__ . '/' . $firma_new_resp, 30, $y, 30);
}
if($firma_new_jefe != 'SAME' && file_exists(__DIR__ . '/' . $firma_new_jefe)) {
    $pdfNew->Image(__DIR__ . '/' . $firma_new_jefe, 130, $y, 30);
}
// Nombres NUEVOS debajo de firmas NUEVAS
$pdfNew->SetXY(20, $y+30); $pdfNew->Cell(60,5, utf8_decode('Recibe (Nuevo Resp.)'), 'T', 1, 'C');
$pdfNew->SetX(20); $pdfNew->Cell(60,5, utf8_decode($nombre_new_resp), 0, 0, 'C'); // <--- Nombre Nuevo

$pdfNew->SetXY(120, $y+30); $pdfNew->Cell(60,5, utf8_decode('Aval (Jefe Servicio)'), 'T', 1, 'C');
$pdfNew->SetX(120); $pdfNew->Cell(60,5, utf8_decode($nombre_new_jefe), 0, 0, 'C'); // <--- Nombre Nuevo

$pdfNew->Output('F', $ruta_pdfs_abs . 'new_' . $token . '.pdf');


// ============================================================================
// 4. INSERTAR EN BASE DE DATOS
// ============================================================================
$sql = "INSERT INTO inventario_transferencias_pendientes 
        (token_hash, id_bien, nuevo_destino_id, nuevo_destino_nombre, nueva_area_nombre, nuevo_responsable_nombre, firma_nuevo_responsable_path, nuevo_jefe_nombre, firma_nuevo_jefe_path, motivo_transferencia, observaciones, creado_por, fecha_expiracion) 
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, DATE_ADD(NOW(), INTERVAL 48 HOUR))";

$stmt = $pdo->prepare($sql);
$stmt->execute([
    $token, $id_bien, $id_destino_nuevo, $nombre_destino_nuevo, $nombre_area_nueva,
    $nombre_new_resp, $firma_new_resp, $nombre_new_jefe, $firma_new_jefe,
    $_POST['motivo_id'] == 'OTRO' ? $_POST['observacion_texto'] : $_POST['motivo_id'] . ' - ' . $_POST['observacion_texto'],
    $_POST['observacion_texto'], $_SESSION['usuario_id']
]);

// 5. LINK
$link = "https://" . $_SERVER['HTTP_HOST'] . dirname($_SERVER['PHP_SELF']) . "/transferencia_externa.php?token=" . $token;
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Transferencia Iniciada</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>
<body class="bg-light">
    <div class="container mt-5">
        <div class="card shadow border-success">
            <div class="card-header bg-success text-white text-center">
                <h4 class="mb-0">Transferencia Creada Exitosamente</h4>
            </div>
            <div class="card-body text-center p-5">
                <h3 class="mb-4">Se requiere validación externa</h3>
                <div class="alert alert-info d-inline-block text-start">
                    <strong>Archivos Generados:</strong><br>
                    <i class="fas fa-check text-success"></i> Acta Antigua (Estado Actual)<br>
                    <i class="fas fa-check text-success"></i> Acta Nueva (Transferencia)
                </div>
                <br>
                <div class="alert alert-warning d-inline-block text-start p-4 mb-4 mt-3">
                    <strong>Instrucciones:</strong>
                    <ol class="mb-0">
                        <li>Copie el enlace.</li>
                        <li>Envíelo al Responsable Anterior.</li>
                    </ol>
                </div>
                <div class="input-group mb-3 w-75 mx-auto">
                    <input type="text" id="linkInput" class="form-control form-control-lg text-center fw-bold text-primary" value="<?php echo $link; ?>" readonly>
                    <button class="btn btn-dark" onclick="copyLink()">Copiar</button>
                </div>
                <a href="inventario_lista.php" class="btn btn-outline-secondary mt-4">Volver</a>
            </div>
        </div>
    </div>
    <script>
        function copyLink() {
            var copyText = document.getElementById("linkInput");
            copyText.select();
            document.execCommand("copy");
            alert("Enlace copiado");
        }
    </script>
</body>
</html>