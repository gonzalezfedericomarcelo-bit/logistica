<?php
// Archivo: inventario_pdf_cuerpo_dinamico.php (CORREGIDO Y ESTILIZADO)

// 1. Validar variables heredadas del padre
$h = isset($h_row) ? $h_row : 7; // Usar altura de fila definida o 7 por defecto
$num = isset($sec_num) ? $sec_num : 2; // Usar el contador de sección dinámico

// 2. Obtener datos (Intenta usar la variable $resDyn del padre para no consultar 2 veces)
$specs = [];
if (isset($resDyn)) {
    $specs = $resDyn;
} elseif (isset($campos)) {
    $specs = $campos;
} else {
    // Fallback: Si por alguna razón no llegan las variables, consulta manual
    try {
        $sqlDin = "SELECT cd.etiqueta, vd.valor 
                   FROM inventario_valores_dinamicos vd
                   JOIN inventario_campos_dinamicos cd ON vd.id_campo = cd.id_campo
                   WHERE vd.id_cargo = ? ORDER BY cd.orden ASC";
        $stmtD = $pdo->prepare($sqlDin);
        $stmtD->execute([$id]);
        $specs = $stmtD->fetchAll(PDO::FETCH_ASSOC);
    } catch (Exception $e) { $specs = []; }
}

// 3. Renderizar Tabla SOLO si hay datos
if (count($specs) > 0) {
    
    // Título de Sección con numeración correcta
    $titulo = "  $num. ESPECIFICACIONES TÉCNICAS";
    if (!empty($bien['tipo_bien_dinamico'])) {
        $titulo .= ' (' . mb_strtoupper($bien['tipo_bien_dinamico'], 'UTF-8') . ')';
    }

    // Dibujar Título (Estilo gris igual al punto 1)
    $pdf->SetFillColor(220,220,220);
    $pdf->SetFont('Arial','B',10);
    $pdf->Cell(0, 8, utf8_decode($titulo), 1, 1, 'L', true);
    
    // Configuración de Grilla (2 Columnas)
    $pdf->SetFont('Arial','',9);
    $col = 0; // 0 = Izquierda, 1 = Derecha
    
    // Anchos calculados para llenar la hoja (aprox 190mm ancho útil)
    // Columna Izq (95mm) + Columna Der (95mm)
    $w_label = 35; 
    $w_value = 60; 

    foreach ($specs as $campo) {
        $etiqueta = utf8_decode($campo['etiqueta']);
        $valor = utf8_decode($campo['valor']);

        // Etiqueta (Gris claro, Negrita)
        $pdf->SetFont('Arial','B',8);
        $pdf->SetFillColor(245,245,245);
        $pdf->Cell($w_label, $h, $etiqueta, 1, 0, 'L', true);
        
        // Valor (Blanco, Normal)
        $pdf->SetFont('Arial','',8);
        $pdf->SetFillColor(255,255,255);
        
        if ($col == 0) { 
            // Columna Izquierda -> No salta línea (ln=0)
            $pdf->Cell($w_value, $h, $valor, 1, 0, 'L', false);
            $col = 1;
        } else { 
            // Columna Derecha -> Salta línea al final (ln=1)
            $pdf->Cell($w_value, $h, $valor, 1, 1, 'L', false);
            $col = 0;
        }
    }
    
    // Si la tabla terminó impar (quedó abierta la columna derecha), cerramos con celda vacía
    if ($col == 1) {
        $pdf->SetFillColor(245,245,245);
        $pdf->Cell($w_label, $h, '', 1, 0, 'L', true);
        $pdf->SetFillColor(255,255,255);
        $pdf->Cell($w_value, $h, '', 1, 1, 'L', false);
    }
    
    $pdf->Ln(5); // Espacio antes de la siguiente sección
}
?>