<?php
// Archivo: importar_estructura_procesar.php (VERSIÓN CORREGIDA FINAL)
session_start();
// Activar errores para ver qué pasa realmente
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

include 'conexion.php';
include 'funciones_permisos.php';

// 1. DIAGNÓSTICO INICIAL (Para evitar pantalla en blanco o errores genéricos)
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    die("<h3>Error:</h3> El acceso debe ser a través del formulario (POST).");
}

// Verificar si el servidor eliminó el POST por límite de tamaño
if (empty($_POST) && empty($_FILES)) {
    die("<h3>Error de Servidor:</h3> El envío llegó vacío. Posiblemente el archivo CSV excede el límite `post_max_size` de tu servidor PHP.");
}

// Validar Permisos
if (!isset($_SESSION['usuario_id']) || !tiene_permiso('acceso_inventario', $pdo)) {
    die("Error: No tienes permisos o la sesión expiró.");
}

// 2. AUTO-REPARACIÓN DE TABLA (Por si acaso)
try {
    $pdo->query("SELECT 1 FROM inventario_campos_dinamicos LIMIT 1");
    try {
        $pdo->query("SELECT ancho FROM inventario_campos_dinamicos LIMIT 1");
    } catch (Exception $e) {
        $pdo->exec("ALTER TABLE inventario_campos_dinamicos 
                    ADD COLUMN ancho INT DEFAULT 6, ADD COLUMN opciones TEXT DEFAULT NULL");
    }
} catch (Exception $e) {
    $sql = "CREATE TABLE IF NOT EXISTS `inventario_campos_dinamicos` (
      `id_campo` int(11) NOT NULL AUTO_INCREMENT,
      `id_tipo_bien` int(11) NOT NULL,
      `etiqueta` varchar(100) NOT NULL,
      `tipo_input` varchar(50) DEFAULT 'text',
      `ancho` int(11) DEFAULT 6,
      `opciones` text DEFAULT NULL,
      `orden` int(11) DEFAULT 0,
      PRIMARY KEY (`id_campo`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;";
    $pdo->exec($sql);
}

// 3. VALIDACIÓN DE ARCHIVO (Aquí es donde fallaba antes)
if (!isset($_FILES['archivo_csv'])) {
    die("<h3>Error Crítico:</h3> No se recibió el campo 'archivo_csv'. <br>Verifica que en el HTML el input sea: <code>&lt;input type='file' name='archivo_csv'...&gt;</code>");
}

if ($_FILES['archivo_csv']['error'] !== UPLOAD_ERR_OK) {
    die("<h3>Error al subir archivo:</h3> Código de error PHP: " . $_FILES['archivo_csv']['error']);
}

// 4. PROCESAR EL CSV
try {
    $nombre_categoria = trim($_POST['nombre_categoria']);
    $icono = !empty($_POST['icono']) ? trim($_POST['icono']) : 'fas fa-box';
    $archivo = $_FILES['archivo_csv']['tmp_name'];

    if (empty($nombre_categoria)) die("Error: Falta el nombre de la categoría.");

    $pdo->beginTransaction();

    // Limpiar anterior si existe
    $pdo->prepare("DELETE FROM inventario_tipos_bien WHERE nombre = ? AND tiene_campos_tecnicos = 2")->execute([$nombre_categoria]);

    // Crear Categoría
    $stmt = $pdo->prepare("INSERT INTO inventario_tipos_bien (nombre, icono, descripcion, tiene_campos_tecnicos, categoria_agrupadora) VALUES (?, ?, 'Importada Excel', 2, 'General')");
    $stmt->execute([$nombre_categoria, $icono]);
    $id_tipo_nuevo = $pdo->lastInsertId();

    // Leer Archivo
    $contenido = file_get_contents($archivo);
    // Quitar BOM (Byte Order Mark) que mete Excel a veces
    $bom = pack('H*','EFBBBF'); 
    $contenido = preg_replace("/^$bom/", '', $contenido);
    $contenido = str_replace(array("\r\n", "\r"), "\n", $contenido);
    $lineas = explode("\n", $contenido);

    // Detectar Cabecera y Modo
    $encabezados = [];
    $fila_datos = 0;
    $sep = ',';
    $modo_avanzado = false;

    foreach($lineas as $i => $linea) {
        if(trim($linea) === '') continue;
        $sep_local = (substr_count($linea, ';') > substr_count($linea, ',')) ? ';' : ',';
        $row = str_getcsv($linea, $sep_local);
        $cols = array_filter($row, function($x) { return trim($x) !== ''; });

        if(count($cols) >= 2) {
            $header_str = strtoupper(implode(' ', $cols));
            // Si vemos "ETIQUETA" y "TIPO", es tu formato de estructura avanzado
            if (strpos($header_str, 'ETIQUETA') !== false && strpos($header_str, 'TIPO') !== false) {
                $modo_avanzado = true;
            }
            $encabezados = $row;
            $fila_datos = $i + 1;
            $sep = $sep_local;
            break;
        }
    }

    if (empty($encabezados)) throw new Exception("No se encontraron columnas válidas en el CSV.");

    // Insertar Campos
    $orden = 1;
    
    // CASO A: ESTRUCTURA AVANZADA (Etiqueta, Tipo, Ancho, Opciones)
    if ($modo_avanzado) {
        // Mapear columnas
        $idx_e = -1; $idx_t = -1; $idx_a = -1; $idx_o = -1;
        foreach($encabezados as $k => $h) {
            $h = strtoupper(trim($h));
            if(strpos($h,'ETIQUETA')!==false) $idx_e=$k;
            if(strpos($h,'TIPO')!==false) $idx_t=$k;
            if(strpos($h,'ANCHO')!==false) $idx_a=$k;
            if(strpos($h,'OPCI')!==false) $idx_o=$k;
        }

        for ($j=$fila_datos; $j<count($lineas); $j++) {
            $linea = trim($lineas[$j]);
            if(empty($linea)) continue;
            $d = str_getcsv($linea, $sep);
            
            if(isset($d[$idx_e]) && $d[$idx_e] !== '') {
                $et = trim($d[$idx_e]);
                $tip = isset($d[$idx_t]) ? strtolower(trim($d[$idx_t])) : 'text';
                $anc = (isset($d[$idx_a]) && is_numeric($d[$idx_a])) ? (int)$d[$idx_a] : 6;
                $opt = isset($d[$idx_o]) ? trim($d[$idx_o]) : null;

                $pdo->prepare("INSERT INTO inventario_campos_dinamicos (id_tipo_bien, etiqueta, tipo_input, ancho, opciones, orden) VALUES (?,?,?,?,?,?)")
                    ->execute([$id_tipo_nuevo, $et, $tip, $anc, $opt, $orden++]);
            }
        }
    } 
    // CASO B: SIMPLE (Marca, Modelo, Serie...)
    else {
        // ... (Lógica simple para compatibilidad anterior)
        foreach ($encabezados as $col) {
            $et = trim(str_replace(['"',"'"], '', $col));
            if($et === '') continue;
            // Ignorar columnas de sistema
            if(preg_match('/(DESTINO|AREA|RESPONSABLE|JEFE|ELEMENTO|EQUIPO)/i', $et)) continue;
            
            $tip = 'text';
            if(preg_match('/(FECHA|VENCIMIENTO)/i', $et)) $tip='date';
            if(preg_match('/(CANTIDAD|NUMERO)/i', $et)) $tip='number';
            
            $pdo->prepare("INSERT INTO inventario_campos_dinamicos (id_tipo_bien, etiqueta, tipo_input, ancho, orden) VALUES (?,?,?,6,?)")
                ->execute([$id_tipo_nuevo, $et, $tip, $orden++]);
        }
    }

    $pdo->commit();
    header("Location: inventario_config.php?status=ok_estructura");
    exit();

} catch (Exception $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    die("<h3>Error Fatal:</h3> " . $e->getMessage());
}
?>