<?php
// FICHA TÉCNICA DINÁMICA (Para Informática, Cámaras, Teléfonos, etc.)
// No tiene nada hardcodeado. Lee lo que hay en la BD.

// 1. Buscamos qué campos tiene cargado este bien específico
$sqlDin = "SELECT cd.etiqueta, vd.valor 
           FROM inventario_valores_dinamicos vd
           JOIN inventario_campos_dinamicos cd ON vd.id_campo = cd.id_campo
           WHERE vd.id_cargo = ?
           ORDER BY cd.orden ASC";
$stmtD = $pdo->prepare($sqlDin);
$stmtD->execute([$id]);
$campos = $stmtD->fetchAll(PDO::FETCH_ASSOC);

// 2. Si encontramos campos (ej: IP, MAC, Modelo), dibujamos la tabla
if (count($campos) > 0) {
    
    // Título dinámico: Toma el nombre real de la categoría (ej: "CAMARA DE VIGILANCIA")
    $titulo_seccion = '2. ESPECIFICACIONES TÉCNICAS';
    if (!empty($bien['tipo_bien_dinamico'])) {
        $titulo_seccion .= ' (' . mb_strtoupper($bien['tipo_bien_dinamico'], 'UTF-8') . ')';
    }

    $pdf->SetFont('Arial','B',10);
    $pdf->Cell(0,6, utf8_decode($titulo_seccion),1,1,'L',true);

    // Configuración de columnas
    $col = 0; // 0 = Izquierda, 1 = Derecha
    $ancho_etiqueta = 40;
    $ancho_valor = 55;
    
    // Bucle mágico: Dibuja cualquier campo que encuentre
    foreach ($campos as $campo) {
        $etiqueta = utf8_decode($campo['etiqueta'] . ':');
        $valor = utf8_decode($campo['valor']);

        $pdf->SetFont('Arial','B',9);
        $pdf->Cell($ancho_etiqueta, $h, $etiqueta, 1);
        $pdf->SetFont('Arial','',9);
        
        if ($col == 0) { // Columna Izquierda
            $pdf->Cell($ancho_valor, $h, $valor, 1);
            $col = 1;
        } else { // Columna Derecha
            $pdf->Cell(0, $h, $valor, 1, 1); // 0 llena hasta el margen derecho
            $col = 0;
        }
    }
    
    // Si terminó impar, cerramos el cuadro prolijamente
    if ($col == 1) {
        $pdf->Cell(0, $h, '', 1, 1);
    }
}
// Si no tiene campos cargados, no imprime nada (queda el espacio en blanco o pasa a firmas).
?>