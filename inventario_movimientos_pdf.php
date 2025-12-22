<?php
// Archivo: inventario_movimientos_pdf.php
// OBJETIVO: Generar/Mostrar Constancia de Movimiento (Acceso Público si ya existe)
session_start();
require('fpdf/fpdf.php');
include 'conexion.php';
include 'funciones_permisos.php';

// ==============================================================================
// 1. LÓGICA DE ACCESO PÚBLICO (Bypass de Login si el archivo ya existe)
// ==============================================================================
if (!empty($_GET['id']) && is_numeric($_GET['id'])) {
    $id_publico = $_GET['id'];
    
    // Definir rutas públicas
    $ruta_publica_rel = 'pdfs_publicos/inventario_constancia/';
    $nombre_archivo = 'Constancia_Movimiento_' . $id_publico . '.pdf';
    $ruta_archivo_abs = __DIR__ . '/' . $ruta_publica_rel . $nombre_archivo;
    
    // Si el archivo ya fue generado, redirigir DIRECTAMENTE al PDF público (Sin Login)
    if (file_exists($ruta_archivo_abs)) {
        // Detectar protocolo para la redirección URL
        $protocolo = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on') ? "https" : "http";
        $url_final = "$protocolo://{$_SERVER['HTTP_HOST']}" . dirname($_SERVER['PHP_SELF']) . "/$ruta_publica_rel" . $nombre_archivo;
        
        header("Location: " . $url_final);
        exit();
    }
}

// ==============================================================================
// 2. SEGURIDAD (Solo si hay que generar el reporte o es el listado global)
// ==============================================================================
if (!isset($_SESSION['usuario_id']) || (!tiene_permiso('inventario_historial', $pdo) && !tiene_permiso('inventario_ver_transferencias', $pdo))) {
    // Si no está logueado y el archivo no existía, mandar al login
    header("Location: index.php"); exit();
}

// =======================================================
// MODO 1: GENERAR CONSTANCIA INDIVIDUAL (SI NO EXISTÍA)
// =======================================================
if (!empty($_GET['id']) && is_numeric($_GET['id'])) {
    
    $id = $_GET['id'];
    
    // Consulta de datos
    $sql = "SELECT h.*, 
                   i.elemento, i.codigo_inventario, i.mat_numero_grabado, 
                   u.nombre_completo as usuario_nombre, u.firma_imagen_path as usuario_firma
            FROM historial_movimientos h 
            LEFT JOIN inventario_cargos i ON h.id_bien = i.id_cargo 
            LEFT JOIN usuarios u ON h.usuario_registro = u.id_usuario 
            WHERE h.id_movimiento = ?";
            
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$id]);
    $mov = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$mov) die("Error: Movimiento no encontrado.");

    // Datos extra de Transferencia
    $sqlTransf = "SELECT motivo_transferencia, observaciones, nuevo_responsable_nombre, firma_nuevo_responsable_path 
                  FROM inventario_transferencias_pendientes 
                  WHERE id_bien = ? AND estado = 'confirmado' 
                  ORDER BY id_token DESC LIMIT 1";
    $stmtT = $pdo->prepare($sqlTransf);
    $stmtT->execute([$mov['id_bien']]);
    $transfData = $stmtT->fetch(PDO::FETCH_ASSOC);

    // Procesar Datos
    $motivo_raw = $transfData ? $transfData['motivo_transferencia'] : $mov['observacion_movimiento'];
    $plazo_mostrar = "Inmediato";

    if (preg_match('/\[EJECUCIÓN.*?:(.*?)\]/', $motivo_raw, $matches)) {
        $plazo_mostrar = trim($matches[1]);
        $motivo_raw = trim(str_replace($matches[0], '', $motivo_raw));
    }

    $partes_motivo = explode(' - ', $motivo_raw);
    if (count($partes_motivo) == 2 && trim($partes_motivo[0]) == trim($partes_motivo[1])) {
        $motivo_mostrar = trim($partes_motivo[0]);
    } else {
        $motivo_mostrar = $motivo_raw;
    }

    class PDF_Anexo4 extends FPDF {
        function Header() {
            $this->SetMargins(15, 15, 15);
            $this->SetAutoPageBreak(true, 10);
            
            // --- MEMBRETE ---
            $x = 15; $y = 15;
            $w_total = 180; $w_logo = 40; $w_texto = $w_total - $w_logo; 
            $h_fila1 = 25; $h_fila2 = 8;   

            // 1. LOGO
            $this->Rect($x, $y, $w_logo, $h_fila1);
            $logo = './assets/iosfa.png'; 
            if (file_exists($logo)) { $this->ImageFit($logo, $x + 3, $y + 3, $w_logo - 6, $h_fila1 - 6); }

            // 2. TÍTULO
            $this->Rect($x + $w_logo, $y, $w_texto, $h_fila1);
            $y_texto = $y + 8; 
            $this->SetXY($x + $w_logo, $y_texto);
            $this->SetFont('Arial', 'B', 11);
            $this->Cell($w_texto, 5, utf8_decode('PROCEDIMIENTO PARA LA ADMINISTRACIÓN DE BIENES'), 0, 1, 'C');
            $this->SetX($x + $w_logo);
            $this->Cell($w_texto, 5, utf8_decode('PATRIMONIALES'), 0, 1, 'C');

            // 3. VERSIÓN
            $this->Rect($x, $y + $h_fila1, $w_total, $h_fila2);
            $this->SetXY($x, $y + $h_fila1 + 1.5);
            $this->SetFont('Arial', '', 9);
            $this->Cell($w_total, 5, utf8_decode('Versión: 1.0.0.'), 0, 1, 'C');

            // --- TÍTULOS ---
            $this->Ln(12);
            $this->SetFont('Arial', 'B', 10);
            $this->Cell(0, 6, utf8_decode('ANEXO 4 - PLANILLA REGISTRO DE TRANSFERENCIAS'), 0, 1, 'R');
            $this->Ln(5);
            $this->SetFont('Arial', 'B', 12);
            $this->Cell(0, 6, utf8_decode('TRANSFERENCIA DE BIENES MUEBLES'), 0, 1, 'C');
            $this->Ln(8);
        }

        function Footer() {
            $this->SetY(-30);
            $this->SetFont('Arial', '', 8);
            $this->Cell(0, 5, utf8_decode('1) Firma del Titular de la Dependencia que entrega el BIEN.'), 0, 1, 'L');
            $this->Cell(0, 5, utf8_decode('2) Firma del Titular de la Dependencia que recepciona el BIEN.'), 0, 1, 'L');
        }
        
        function ImageFit($file, $x, $y, $w, $h) {
            if (!file_exists($file)) return;
            list($width, $height) = getimagesize($file);
            if ($width == 0 || $height == 0) return;
            $ratioImg = $width / $height; $ratioBox = $w / $h;
            if ($ratioImg > $ratioBox) { $newW = $w; $newH = $w / $ratioImg; $newY = $y + ($h - $newH) / 2; $this->Image($file, $x, $newY, $newW, $newH); } 
            else { $newH = $h; $newW = $h * $ratioImg; $newX = $x + ($w - $newW) / 2; $this->Image($file, $newX, $y, $newW, $newH); }
        }
        
        function VCell($w, $h, $txt, $border=0, $align='C', $fill=false) {
            if($w==0) $w = $this->w - $this->rMargin - $this->x;
            $s = str_replace("\r", '', $txt); $nb = strlen($s);
            if($nb > 0 && $s[$nb-1] == "\n") $nb--;
            $cw = &$this->CurrentFont['cw'];
            $wmax = ($w - 2 * $this->cMargin) * 1000 / $this->FontSize;
            $i = 0; $nl = 1; $sep = -1; $j = 0; $l = 0; $ns = 0;
            while($i < $nb) {
                $c = $s[$i];
                if($c == "\n") { $i++; $sep = -1; $j = $i; $l = 0; $ns = 0; $nl++; continue; }
                if($c == ' ') { $sep = $i; $ns++; }
                $l += $cw[ord($c)];
                if($l > $wmax) { if($sep == -1) { if($i == $j) $i++; } else $i = $sep + 1; $sep = -1; $j = $i; $l = 0; $ns = 0; $nl++; } else $i++;
            }
            $h_txt = $nl * 5; $y_offset = ($h - $h_txt) / 2;
            $x = $this->GetX(); $y = $this->GetY();
            if($border) $this->Rect($x, $y, $w, $h);
            $this->SetXY($x, $y + $y_offset);
            $this->MultiCell($w, 5, $txt, 0, $align, $fill);
            $this->SetXY($x + $w, $y);
        }
    }

    $pdf = new PDF_Anexo4('P','mm','A4');
    $pdf->AddPage();

    // --- DATOS ---
    $pdf->SetFont('Arial', 'B', 10);
    $pdf->Write(6, utf8_decode('INSTANCIA QUE ORDENA / PROPONE LA TRANSFERENCIA: '));
    $pdf->SetFont('Arial', '', 10);
    $pdf->Write(6, utf8_decode(strtoupper($mov['usuario_nombre'] ?? 'SISTEMA')));
    $pdf->Ln(8);

    $pdf->SetFont('Arial', 'B', 10);
    $pdf->Write(6, utf8_decode('FUNDAMENTACIÓN: '));
    $pdf->SetFont('Arial', '', 10);
    $pdf->Write(6, utf8_decode($motivo_mostrar));
    $pdf->Ln(8);

    $pdf->SetFont('Arial', 'B', 10);
    $pdf->Write(6, utf8_decode('PLAZO DE EJECUCIÓN: '));
    $pdf->SetFont('Arial', '', 10);
    $pdf->Write(6, utf8_decode($plazo_mostrar));
    $pdf->Ln(15);

    // --- TABLA ---
    $pdf->SetFillColor(230, 230, 230);
    $pdf->SetFont('Arial', 'B', 9);
    $w1 = 35; $w2 = 90; $w3 = 55; $h_header = 10;
    
    $pdf->Cell($w1, $h_header, utf8_decode('N° INVENTARIO'), 1, 0, 'C', true);
    $pdf->Cell($w2, $h_header, utf8_decode('DETALLE DEL BIEN'), 1, 0, 'C', true);
    $pdf->Cell($w3, $h_header, utf8_decode('DEPENDENCIA RECEPTORA'), 1, 1, 'C', true);
    
    $pdf->SetX(15);
    $pdf->SetFont('Arial', '', 9);
    $h_fila = 18;
    
    $pdf->VCell($w1, $h_fila, utf8_decode($mov['codigo_inventario']), 1, 'C');
    $pdf->VCell($w2, $h_fila, utf8_decode($mov['elemento']), 1, 'C');
    $destino = !empty($mov['ubicacion_nueva']) ? $mov['ubicacion_nueva'] : '-';
    if(strlen($destino) > 40) $destino = substr($destino, 0, 40) . '...';
    $pdf->VCell($w3, $h_fila, utf8_decode($destino), 1, 'C');

    $pdf->Ln($h_fila + 15);

    // --- FIRMAS ---
    $yFirmas = $pdf->GetY();
    $anchoFirma = 80; $margenIzq = 15; $separacion = 10;

    // ENTREGA
    $x1 = $margenIzq;
    if (!empty($mov['usuario_firma'])) {
        $rutaFirma1 = 'uploads/firmas/' . $mov['usuario_firma'];
        if(!file_exists($rutaFirma1)) $rutaFirma1 = __DIR__ . '/uploads/firmas/' . $mov['usuario_firma'];
        if (file_exists($rutaFirma1)) $pdf->ImageFit($rutaFirma1, $x1 + 10, $yFirmas, 60, 25);
    }
    $pdf->SetXY($x1, $yFirmas + 28);
    $pdf->SetFont('Arial', '', 9);
    $pdf->MultiCell($anchoFirma, 5, utf8_decode($mov['usuario_nombre']), 0, 'C');
    $pdf->SetXY($x1, $pdf->GetY()); 
    $pdf->SetFont('Arial', 'B', 11);
    $pdf->Cell($anchoFirma, 6, '(1)', 0, 0, 'C');

    // RECEPCIONA
    $x2 = $margenIzq + $anchoFirma + $separacion;
    $firma2_path = $transfData ? $transfData['firma_nuevo_responsable_path'] : null;
    $nombre2 = $transfData ? $transfData['nuevo_responsable_nombre'] : ($mov['nombre_responsable'] ?? '-');

    if (!empty($firma2_path)) {
        if(!file_exists($firma2_path)) $firma2_path = __DIR__ . '/' . $firma2_path;
        if (file_exists($firma2_path)) $pdf->ImageFit($firma2_path, $x2 + 10, $yFirmas, 60, 25);
    }
    $pdf->SetXY($x2, $yFirmas + 28);
    $pdf->SetFont('Arial', '', 9);
    $pdf->MultiCell($anchoFirma, 5, utf8_decode($nombre2), 0, 'C');
    $pdf->SetXY($x2, $pdf->GetY());
    $pdf->SetFont('Arial', 'B', 11);
    $pdf->Cell($anchoFirma, 6, '(2)', 0, 0, 'C');

    // --- GUARDADO AUTOMÁTICO EN CARPETA PÚBLICA ---
    $ruta_publica_rel = 'pdfs_publicos/inventario_constancia/';
    $ruta_publica_abs = __DIR__ . '/' . $ruta_publica_rel;
    if (!file_exists($ruta_publica_abs)) mkdir($ruta_publica_abs, 0777, true);

    $nombre_archivo = 'Constancia_Movimiento_' . $id . '.pdf';
    $ruta_archivo_final = $ruta_publica_abs . $nombre_archivo;

    // Guardar en disco
    $pdf->Output('F', $ruta_archivo_final);

    // Redirigir al archivo público
    $protocolo = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on') ? "https" : "http";
    $url_final = "$protocolo://{$_SERVER['HTTP_HOST']}" . dirname($_SERVER['PHP_SELF']) . "/$ruta_publica_rel" . $nombre_archivo;
    
    header("Location: " . $url_final);
    exit;
}

// =======================================================
// MODO 2: REPORTE GLOBAL (LISTADO)
// =======================================================
// ... (Se mantiene el código del listado intacto) ...
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