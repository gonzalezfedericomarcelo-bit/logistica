<?php
// Archivo: inventario_transferir_procesar.php
session_start();
include 'conexion.php';
require('fpdf/fpdf.php'); 

if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !isset($_SESSION['usuario_id'])) {
    header("Location: dashboard.php"); exit();
}

// 1. PROCESAR FIRMAS
$ruta_firmas = __DIR__ . '/uploads/firmas/';
if (!file_exists($ruta_firmas)) mkdir($ruta_firmas, 0777, true);

function saveSig($base64, $prefix, $path) {
    if ($base64 == 'SAME') return 'SAME';
    $data = base64_decode(preg_replace('#^data:image/\w+;base64,#i', '', $base64));
    $filename = $prefix . '_' . uniqid() . '.png';
    file_put_contents($path . $filename, $data);
    return 'uploads/firmas/' . $filename;
}

$firma_new_resp = saveSig($_POST['base64_responsable'], 'transf_resp', $ruta_firmas);
$firma_new_jefe = saveSig($_POST['base64_jefe'], 'transf_jefe', $ruta_firmas);

$nombre_new_resp = $_POST['nuevo_responsable_nombre'];
$nombre_new_jefe = $_POST['nuevo_jefe_nombre'];

if ($firma_new_jefe === 'SAME') {
    $firma_new_jefe = $firma_new_resp;
    $nombre_new_jefe = $nombre_new_resp;
}

// 2. PREPARAR DATOS
$id_destino_nuevo = $_POST['select_destino'];
$nombre_area_nueva = $_POST['nueva_area'];
if ($nombre_area_nueva === 'General' || $nombre_area_nueva === 'General / Única') $nombre_area_nueva = ''; 

$stmtDest = $pdo->prepare("SELECT nombre FROM destinos_internos WHERE id_destino = ?");
$stmtDest->execute([$id_destino_nuevo]);
$rowDest = $stmtDest->fetch(PDO::FETCH_ASSOC);
$nombre_destino_nuevo = $rowDest ? $rowDest['nombre'] : 'Desconocido';

$token = bin2hex(random_bytes(32)); 
$id_bien = $_POST['id_bien'];

// --- PROCESAR FECHA EJECUCIÓN ---
$motivo_final = $_POST['motivo_id'] == 'OTRO' ? $_POST['observacion_texto'] : $_POST['motivo_id'] . ' - ' . $_POST['observacion_texto'];
$obs_final = $_POST['observacion_texto'];

if (isset($_POST['tipo_ejecucion']) && $_POST['tipo_ejecucion'] == 'programado' && !empty($_POST['fecha_ejecucion'])) {
    $fecha_fmt = date('d/m/Y', strtotime($_POST['fecha_ejecucion']));
    $motivo_final .= " [EJECUCIÓN PROGRAMADA: $fecha_fmt]";
    $obs_final .= " (Fecha Ejecución: $fecha_fmt)";
} else {
    $motivo_final .= " [EJECUCIÓN: INMEDIATA]";
}

$sqlBien = "SELECT i.* FROM inventario_cargos i WHERE i.id_cargo = ?";
$stmtB = $pdo->prepare($sqlBien);
$stmtB->execute([$id_bien]);
$bien = $stmtB->fetch(PDO::FETCH_ASSOC);

// --- CORRECCIÓN ORIGEN ---
$nombre_destino_ant = $bien['destino_principal'];
if (is_numeric($nombre_destino_ant)) {
    $stmtDA = $pdo->prepare("SELECT nombre FROM destinos_internos WHERE id_destino = ?");
    $stmtDA->execute([$nombre_destino_ant]);
    $resDA = $stmtDA->fetch(PDO::FETCH_ASSOC);
    if ($resDA) $nombre_destino_ant = $resDA['nombre'];
}
$area_ant = $bien['servicio_ubicacion'];
if (stripos($area_ant, 'General') !== false || stripos($area_ant, 'Sin áreas') !== false) $area_ant = '';
$origen_txt_real = $nombre_destino_ant . ($area_ant ? ' - ' . $area_ant : '');

// 3. GENERAR PDFs
$ruta_pdfs_abs = __DIR__ . '/pdfs_publicos/inventario_pdf/';
if (!file_exists($ruta_pdfs_abs)) mkdir($ruta_pdfs_abs, 0777, true);

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

// PDF OLD
$pdfOld = new PDF_Base(); $pdfOld->AddPage(); $pdfOld->BloqueBien($bien);
$pdfOld->SetFont('Arial','B',10); $pdfOld->Cell(0,7, utf8_decode('ESTADO ACTUAL (PREVIO A TRANSFERENCIA)'),1,1,'L',true);
$pdfOld->SetFont('Arial','',10);
$pdfOld->Cell(30,7, utf8_decode('Ubicación:'),1); $pdfOld->Cell(0,7, utf8_decode($origen_txt_real),1,1);
$pdfOld->Cell(30,7, 'Responsable:',1); $pdfOld->Cell(0,7, utf8_decode($bien['nombre_responsable']),1,1);
$pdfOld->Ln(20); $y = $pdfOld->GetY();
if(!empty($bien['firma_responsable']) && file_exists(__DIR__ . '/' . $bien['firma_responsable'])) $pdfOld->Image(__DIR__ . '/' . $bien['firma_responsable'], 30, $y, 30);
if(!empty($bien['firma_jefe']) && file_exists(__DIR__ . '/' . $bien['firma_jefe'])) $pdfOld->Image(__DIR__ . '/' . $bien['firma_jefe'], 130, $y, 30);
$pdfOld->Output('F', $ruta_pdfs_abs . 'old_' . $token . '.pdf');

// PDF NEW
$pdfNew = new PDF_Base(); $pdfNew->AddPage(); $pdfNew->BloqueBien($bien);
$pdfNew->SetFont('Arial','B',10); $pdfNew->Cell(0,7, utf8_decode('DETALLE DE TRANSFERENCIA'),1,1,'L',true);
$pdfNew->SetFont('Arial','',10);
$pdfNew->Cell(30,7, utf8_decode('Se retira de:'),1); $pdfNew->Cell(0,7, utf8_decode($origen_txt_real),1,1);
$pdfNew->Cell(30,7, utf8_decode('Entrega:'),1); $pdfNew->Cell(0,7, utf8_decode($bien['nombre_responsable']),1,1);
$pdfNew->Ln(2); $pdfNew->SetFont('Arial','B',10);

$destino_nuevo_txt = $nombre_destino_nuevo;
if (!empty($nombre_area_nueva)) $destino_nuevo_txt .= ' - ' . $nombre_area_nueva;

$pdfNew->Cell(30,7, utf8_decode('Se incorpora a:'),1); $pdfNew->Cell(0,7, utf8_decode($destino_nuevo_txt),1,1);
$pdfNew->Cell(30,7, utf8_decode('Recibe:'),1); $pdfNew->Cell(0,7, utf8_decode($nombre_new_resp),1,1);
$pdfNew->Ln(5); 
$pdfNew->MultiCell(0,7, utf8_decode('MOTIVO / FECHA: ' . $motivo_final), 0, 'L');

$pdfNew->Ln(15); $y = $pdfNew->GetY();
if($firma_new_resp != 'SAME' && file_exists(__DIR__ . '/' . $firma_new_resp)) $pdfNew->Image(__DIR__ . '/' . $firma_new_resp, 30, $y, 30);
if($firma_new_jefe != 'SAME' && file_exists(__DIR__ . '/' . $firma_new_jefe)) $pdfNew->Image(__DIR__ . '/' . $firma_new_jefe, 130, $y, 30);
$pdfNew->Output('F', $ruta_pdfs_abs . 'new_' . $token . '.pdf');

// 4. DB INSERT (TABLA PRINCIPAL)
$sql = "INSERT INTO inventario_transferencias_pendientes 
        (token_hash, id_bien, nuevo_destino_id, nuevo_destino_nombre, nueva_area_nombre, nuevo_responsable_nombre, firma_nuevo_responsable_path, nuevo_jefe_nombre, firma_nuevo_jefe_path, motivo_transferencia, observaciones, creado_por, fecha_expiracion) 
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, DATE_ADD(NOW(), INTERVAL 48 HOUR))";
$stmt = $pdo->prepare($sql);
$stmt->execute([
    $token, $id_bien, $id_destino_nuevo, $nombre_destino_nuevo, $nombre_area_nueva,
    $nombre_new_resp, $firma_new_resp, $nombre_new_jefe, $firma_new_jefe,
    $motivo_final, 
    $obs_final,    
    $_SESSION['usuario_id']
]);

// ------------------------------------------------------------------------------------
// 5. NOTIFICAR AL ROL "CARGOPATRIMONIAL" (LÓGICA CORREGIDA)
// ------------------------------------------------------------------------------------
$link_validacion = "inventario_validar_transferencia.php?token=" . $token;
$mensaje_patrimonio = "Nueva Solicitud de Transferencia (Bien: " . $bien['elemento'] . "). Requiere su validación.";

// 1. Buscamos usuarios que sean ADMIN o tengan el PERMISO ESPECÍFICO activado
$sqlNotif = "SELECT DISTINCT u.id_usuario 
             FROM usuarios u
             LEFT JOIN rol_permiso rp ON u.rol = rp.nombre_rol
             WHERE u.rol = 'admin' OR rp.clave_permiso = 'inventario_validar_patrimonial'";

$stmtN = $pdo->prepare($sqlNotif);
$stmtN->execute();
$destinatarios = $stmtN->fetchAll(PDO::FETCH_COLUMN);

// 2. Insertamos la notificación para cada uno
if ($destinatarios) {
    // NOTA: Si tu tabla notificaciones tiene columna 'leido', úsala. Si no, usa esta línea:
    $sqlIns = "INSERT INTO notificaciones (id_usuario_destino, tipo, mensaje, url, fecha_creacion) VALUES (?, 'aviso', ?, ?, NOW())";
    $stmtIns = $pdo->prepare($sqlIns);
    foreach ($destinatarios as $uid) {
        $stmtIns->execute([$uid, $mensaje_patrimonio, $link_validacion]);
    }
}
// ------------------------------------------------------------------------------------

// 6. LINK (GENERACIÓN)
$protocol = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on') ? "https://" : "http://";
$path = str_replace('\\', '/', dirname($_SERVER['PHP_SELF']));
if ($path == '/') $path = '';
$link = $protocol . $_SERVER['HTTP_HOST'] . $path . "/transferencia_externa.php?token=" . $token;

$wa_text = urlencode("Hola, te envío el enlace para validar la transferencia del bien *{$bien['elemento']}*.\n\nPor favor ingresa aquí para firmar:\n" . $link);
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, maximum-scale=1, user-scalable=no">
    <title>Transferencia Iniciada</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        @media (max-width: 768px) {
            .card-body { padding: 1.5rem !important; }
            h3 { font-size: 1.5rem; }
            .input-group { width: 100% !important; flex-direction: column; }
            .input-group input { border-radius: 5px !important; margin-bottom: 10px; text-align: center; width: 100%; }
            .input-group button, .input-group a { border-radius: 5px !important; width: 100%; margin-bottom: 10px; }
            .action-buttons { flex-direction: column; gap: 10px; }
        }
    </style>
</head>
<body class="bg-light">
    <div class="container mt-4 mt-md-5">
        <div class="row justify-content-center">
            <div class="col-12 col-md-8 col-lg-6">
                <div class="card shadow border-success">
                    <div class="card-header bg-success text-white text-center py-3">
                        <h4 class="mb-0 fs-5 fw-bold"><i class="fas fa-check-circle me-2"></i> Transferencia Creada</h4>
                    </div>
                    <div class="card-body text-center p-4 p-md-5">
                        
                        <div class="alert alert-info text-start shadow-sm mb-4">
                            <h6 class="fw-bold mb-2"><i class="fas fa-file-contract me-2"></i>Documentos Generados:</h6>
                            <ul class="mb-0 ps-3 small">
                                <li>Acta de Estado Anterior (Backup)</li>
                                <li>Acta de Solicitud de Transferencia</li>
                            </ul>
                        </div>

                        <div class="alert alert-warning text-start p-3 mb-4 mt-3 shadow-sm border-warning">
                            <h6 class="fw-bold text-warning-emphasis"><i class="fas fa-share-alt me-2"></i>Próximo Paso:</h6>
                            <p class="mb-0 small text-muted">Para finalizar el proceso, envíe el siguiente enlace al <strong>Responsable Anterior</strong> para que valide la entrega.</p>
                        </div>

                        <label class="fw-bold mb-2 text-primary d-block">Enlace de Validación:</label>
                        
                        <div class="input-group mb-4 w-100 mx-auto">
                            <input type="text" id="linkInput" class="form-control form-control-lg text-center fw-bold text-dark bg-white" value="<?php echo $link; ?>" readonly onclick="this.select()">
                        </div>

                        <div class="d-flex justify-content-center action-buttons gap-2 mb-3">
                            <button class="btn btn-dark fw-bold px-4 py-2 flex-fill" onclick="copyLink()">
                                <i class="fas fa-copy me-2"></i> COPIAR
                            </button>
                            
                            <a href="https://api.whatsapp.com/send?text=<?php echo $wa_text; ?>" target="_blank" class="btn btn-success fw-bold px-4 py-2 flex-fill">
                                <i class="fab fa-whatsapp me-2"></i> WHATSAPP
                            </a>
                        </div>

                        <a href="inventario_lista.php" class="btn btn-outline-secondary mt-3 w-100 fw-bold">
                            <i class="fas fa-arrow-left me-2"></i> Volver al Inventario
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <script>
        function copyLink() {
            var input = document.getElementById("linkInput");
            if (navigator.clipboard && window.isSecureContext) {
                navigator.clipboard.writeText(input.value).then(function() { feedbackCopy(); }, function(err) { fallbackCopy(input); });
            } else { fallbackCopy(input); }
        }
        function fallbackCopy(input) {
            input.focus(); input.select(); input.setSelectionRange(0, 99999);
            try { var s = document.execCommand('copy'); if(s) feedbackCopy(); else alert('Copie manualmente.'); } catch (e) { alert('Copie manualmente.'); }
        }
        function feedbackCopy() {
            var btn = document.querySelector('.btn-dark');
            var original = btn.innerHTML;
            btn.innerHTML = '<i class="fas fa-check"></i> ¡LISTO!';
            btn.classList.replace('btn-dark','btn-secondary');
            setTimeout(() => { btn.innerHTML = original; btn.classList.replace('btn-secondary','btn-dark'); }, 2000);
        }
    </script>
</body>
</html>