<?php
// FICHA TÉCNICA ESPECÍFICA: MATAFUEGOS (Con cálculos automáticos)

$pdf->SetFont('Arial','B',10);
$pdf->Cell(0,6, utf8_decode('2. ESPECIFICACIONES TÉCNICAS (MATAFUEGOS)'),1,1,'L',true);

// FILA 1
$pdf->SetFont('Arial','B',9);
$pdf->Cell(25,$h, utf8_decode('Tipo Agente:'),1);
$pdf->SetFont('Arial','',9);
$pdf->Cell(70,$h, utf8_decode($bien['tipo_carga']),1);

$pdf->SetFont('Arial','B',9);
$pdf->Cell(25,$h, utf8_decode('Capacidad:'),1);
$pdf->SetFont('Arial','',9);
$pdf->Cell(25,$h, $bien['mat_capacidad'] ? $bien['mat_capacidad'].' Kg' : '-',1);

$pdf->SetFont('Arial','B',9);
$pdf->Cell(20,$h, utf8_decode('Clase:'),1);
$pdf->SetFont('Arial','',9);
$pdf->Cell(0,$h, utf8_decode($bien['nombre_clase']),1,1);

// FILA 2 - FABRICACIÓN Y VIDA ÚTIL
$pdf->SetFont('Arial','B',9);
$pdf->Cell(25,$h, utf8_decode('Fabricación:'),1);
$pdf->SetFont('Arial','',9);
// Formatear fecha fabricación
$fab_date = $bien['fecha_fabricacion'] && $bien['fecha_fabricacion'] != '0000-00-00' ? date('d/m/Y', strtotime($bien['fecha_fabricacion'])) : '-';
$pdf->Cell(70,$h, $fab_date, 1);

$pdf->SetFont('Arial','B',9);
$pdf->Cell(25,$h, utf8_decode('Vida Útil:'),1);
$pdf->SetFont('Arial','',9);

// CÁLCULO DE VIDA ÚTIL
$texto_vida_util = '-';
if ($bien['fecha_fabricacion'] && $bien['fecha_fabricacion'] != '0000-00-00') {
    // Tomamos la vida útil de la tabla config, o 20 por defecto si no está configurado
    $anos_vida = $bien['vida_util'] > 0 ? $bien['vida_util'] : 20;
    
    // Calculamos el año de vencimiento final
    $anio_fab = date('Y', strtotime($bien['fecha_fabricacion']));
    $anio_vence = $anio_fab + $anos_vida;
    
    $texto_vida_util = $anos_vida . ' Años (Vence: ' . $anio_vence . ')';
}
$pdf->Cell(0,$h, utf8_decode($texto_vida_util),1,1);

// FILA 3 - CARGA
$pdf->SetFont('Arial','B',9);
$pdf->Cell(25,$h, utf8_decode('Última Carga:'),1);
$pdf->SetFont('Arial','',9);
$ultima_carga = ($bien['mat_fecha_carga'] && $bien['mat_fecha_carga'] != '0000-00-00') ? date('d/m/Y', strtotime($bien['mat_fecha_carga'])) : '-';
$pdf->Cell(70,$h, $ultima_carga, 1);

$pdf->SetFont('Arial','B',9);
$pdf->Cell(25,$h, utf8_decode('Vto Carga:'),1);
$pdf->SetFont('Arial','B',9); // Negrita para el vencimiento
// CÁLCULO: +1 AÑO
$vto_carga = ($bien['mat_fecha_carga'] && $bien['mat_fecha_carga'] != '0000-00-00') ? date('d/m/Y', strtotime($bien['mat_fecha_carga']. ' +1 year')) : '-';
$pdf->Cell(0,$h, $vto_carga, 1, 1);

// FILA 4 - PRUEBA HIDRÁULICA (PH)
$pdf->SetFont('Arial','B',9);
$pdf->Cell(25,$h, utf8_decode('Última P.H.:'),1);
$pdf->SetFont('Arial','',9);
$ultima_ph = ($bien['mat_fecha_ph'] && $bien['mat_fecha_ph'] != '0000-00-00') ? date('d/m/Y', strtotime($bien['mat_fecha_ph'])) : '-';
$pdf->Cell(70,$h, $ultima_ph, 1);

$pdf->SetFont('Arial','B',9);
$pdf->Cell(25,$h, utf8_decode('Vto P.H.:'),1);
$pdf->SetFont('Arial','B',9); // Negrita para el vencimiento
// CÁLCULO: +5 AÑOS
$vto_ph = ($bien['mat_fecha_ph'] && $bien['mat_fecha_ph'] != '0000-00-00') ? date('d/m/Y', strtotime($bien['mat_fecha_ph']. ' +5 years')) : '-';
$pdf->Cell(0,$h, $vto_ph, 1, 1);
?>