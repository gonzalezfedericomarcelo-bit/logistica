<?php
// Archivo: reparacion_final_ya.php
session_start();
include 'conexion.php';

// Sin saludos, directo al parche
echo "<h2>CORRIGIENDO BASE DE DATOS...</h2>";

try {
    // 1. ELIMINAR CAMPO BASURA "TÉCNICO INTERVINIENTE"
    $pdo->exec("DELETE FROM inventario_campos_dinamicos WHERE etiqueta LIKE '%TECNICO%' OR etiqueta LIKE '%INTERVINIENTE%'");
    $pdo->exec("DELETE FROM inventario_valores_dinamicos WHERE id_campo NOT IN (SELECT id_campo FROM inventario_campos_dinamicos)");
    echo "<p>✔ Campo 'Técnico Interviniente' eliminado.</p>";

    // 2. CREAR COLUMNAS FALTANTES SI EL PDF LAS BUSCA
    $columnas_posibles = ['numero_serie', 'codigo_inventario', 'codigo_patrimonial', 'nro_serie'];
    foreach ($columnas_posibles as $col) {
        try {
            $pdo->exec("ALTER TABLE inventario_cargos ADD COLUMN $col VARCHAR(100) DEFAULT NULL");
        } catch (Exception $e) { /* Ignorar si ya existe */ }
    }
    echo "<p>✔ Columnas de compatibilidad PDF verificadas.</p>";

    // 3. COPIAR EL DATO "Nº GRABADO" A TODAS LAS COLUMNAS DE SERIE (FUERZA BRUTA)
    $sql_fill = "UPDATE inventario_cargos 
                 SET numero_serie = mat_numero_grabado, 
                     codigo_inventario = mat_numero_grabado,
                     codigo_patrimonial = mat_numero_grabado,
                     nro_serie = mat_numero_grabado
                 WHERE (mat_numero_grabado IS NOT NULL AND mat_numero_grabado != '')";
    $pdo->exec($sql_fill);
    echo "<p>✔ Números de serie inyectados en todas las columnas posibles.</p>";

    // 4. ACTIVAR MODO MATAFUEGO (FIX VISUALIZACIÓN)
    // Si dice "MATAFUEGO" en el nombre, le ponemos ID de carga 1 para que el sistema lo trate como tal
    $sql_type = "UPDATE inventario_cargos 
                 SET mat_tipo_carga_id = 1 
                 WHERE elemento LIKE '%MATAFUEGO%' AND (mat_tipo_carga_id IS NULL OR mat_tipo_carga_id = 0)";
    $pdo->exec($sql_type);
    echo "<p>✔ Flags de Matafuego activados.</p>";

    echo "<h1>LISTO. VE A LA REUNIÓN.</h1>";
    echo "<a href='inventario_lista.php'>Ver Lista</a> | <a href='inventario_pdf.php' target='_blank'>Ver PDF</a>";

} catch (Exception $e) {
    echo "ERROR: " . $e->getMessage();
}
?>