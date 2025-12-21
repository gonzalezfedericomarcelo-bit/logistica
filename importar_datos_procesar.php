<?php
// Archivo: importar_datos_procesar.php (MAPEO CORRECTO: SERIE != PATRIMONIAL)
session_start();
include 'conexion.php';
ini_set('display_errors', 1); error_reporting(E_ALL); set_time_limit(0);
$pdo->exec("SET NAMES 'utf8mb4'");

// --- FUNCIONES ---
function forzar_utf8($str) {
    if (!$str) return '';
    if (mb_detect_encoding($str, 'UTF-8', true)) return trim($str);
    return trim(mb_convert_encoding($str, 'UTF-8', 'Windows-1252'));
}

function normalizar_texto_comparacion($str) {
    $str = mb_strtoupper(forzar_utf8($str), 'UTF-8');
    $unwanted = ['Š'=>'S', 'š'=>'s', 'Ž'=>'Z', 'ž'=>'z', 'À'=>'A', 'Á'=>'A', 'Â'=>'A', 'Ã'=>'A', 'Ä'=>'A', 'Å'=>'A', 'Æ'=>'A', 'Ç'=>'C', 'È'=>'E', 'É'=>'E', 'Ê'=>'E', 'Ë'=>'E', 'Ì'=>'I', 'Í'=>'I', 'Î'=>'I', 'Ï'=>'I', 'Ñ'=>'N', 'Ò'=>'O', 'Ó'=>'O', 'Ô'=>'O', 'Õ'=>'O', 'Ö'=>'O', 'Ø'=>'O', 'Ù'=>'U', 'Ú'=>'U', 'Û'=>'U', 'Ü'=>'U', 'Ý'=>'Y', 'Þ'=>'B', 'ß'=>'Ss', 'à'=>'a', 'á'=>'a', 'â'=>'a', 'ã'=>'a', 'ä'=>'a', 'å'=>'a', 'æ'=>'a', 'ç'=>'c', 'è'=>'e', 'é'=>'e', 'ê'=>'e', 'ë'=>'e', 'ì'=>'i', 'í'=>'i', 'î'=>'i', 'ï'=>'i', 'ð'=>'o', 'ñ'=>'n', 'ò'=>'o', 'ó'=>'o', 'ô'=>'o', 'õ'=>'o', 'ö'=>'o', 'ø'=>'o', 'ù'=>'u', 'ú'=>'u', 'û'=>'u', 'ý'=>'y', 'þ'=>'b', 'ÿ'=>'y'];
    return strtr($str, $unwanted);
}

function get_id_destino_inteligente($pdo, $nombre_csv) {
    if(!$nombre_csv) return null;
    $nombre_limpio = forzar_utf8($nombre_csv);
    $nombre_norm = normalizar_texto_comparacion($nombre_limpio);
    $stmt = $pdo->query("SELECT id_destino, nombre FROM destinos_internos");
    foreach($stmt->fetchAll(PDO::FETCH_ASSOC) as $d) {
        if (normalizar_texto_comparacion($d['nombre']) === $nombre_norm) return $d['id_destino'];
    }
    $stmt = $pdo->prepare("INSERT INTO destinos_internos (nombre) VALUES (?)");
    $stmt->execute([$nombre_limpio]);
    return $pdo->lastInsertId();
}

function get_nombre_area_inteligente($pdo, $id_destino, $nombre_area_csv) {
    if(!$nombre_area_csv) return 'General';
    $nombre_limpio = forzar_utf8($nombre_area_csv);
    $nombre_norm = normalizar_texto_comparacion($nombre_limpio);
    try {
        $stmt = $pdo->prepare("SELECT nombre FROM areas WHERE id_destino = ?");
        $stmt->execute([$id_destino]);
        foreach($stmt->fetchAll(PDO::FETCH_COLUMN) as $area) {
            if (normalizar_texto_comparacion($area) === $nombre_norm) return $area;
        }
    } catch (Exception $e) {}
    return $nombre_limpio;
}

// --- PROCESO ---
$campo_archivo = !empty($_FILES['archivo_datos']['name']) ? 'archivo_datos' : 'archivo_excel';
if (empty($_FILES[$campo_archivo]['name'])) die("Error: No se recibió archivo.");

$archivo_temporal = $_FILES[$campo_archivo]['tmp_name'];
$id_usuario = $_SESSION['usuario_id'] ?? 1;
$id_tipo_bien = $_POST['id_tipo_bien'] ?? null;

try {
    $pdo->beginTransaction();

    $contenido_raw = file_get_contents($archivo_temporal);
    $contenido_utf8 = mb_convert_encoding($contenido_raw, 'UTF-8', 'Windows-1252'); 
    
    $temp_handle = tmpfile();
    fwrite($temp_handle, $contenido_utf8);
    fseek($temp_handle, 0);

    $linea1 = fgets($temp_handle);
    $separador = (substr_count($linea1, ';') > substr_count($linea1, ',')) ? ';' : ',';
    rewind($temp_handle);
    
    $headers_raw = fgetcsv($temp_handle, 0, $separador);
    $headers = array_map(function($h) { 
        return mb_strtoupper(trim(preg_replace('/[\x00-\x1F\x7F\xEF\xBB\xBF]/', '', $h)), 'UTF-8'); 
    }, $headers_raw);

    // Mapa de campos dinámicos
    $mapa_dinamico = [];
    if ($id_tipo_bien) {
        $stmt = $pdo->prepare("SELECT id_campo, etiqueta FROM inventario_campos_dinamicos WHERE id_tipo_bien = ?");
        $stmt->execute([$id_tipo_bien]);
        while($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $mapa_dinamico[mb_strtoupper(forzar_utf8($row['etiqueta']), 'UTF-8')] = $row['id_campo'];
        }
    }

    $i = 0;
    while (($fila = fgetcsv($temp_handle, 0, $separador)) !== FALSE) {
        if(array_filter($fila) == []) continue;
        $fila = array_map('trim', $fila);

        $get = function($nombres) use ($headers, $fila) {
            if(!is_array($nombres)) $nombres = [$nombres];
            foreach($nombres as $nom) {
                $nom_norm = mb_strtoupper($nom, 'UTF-8');
                $k = array_search($nom_norm, $headers);
                if($k !== false && isset($fila[$k])) return $fila[$k];
                foreach($headers as $idx => $h) if (strpos($h, $nom_norm) !== false && isset($fila[$idx])) return $fila[$idx];
            }
            return '';
        };

        // Datos Generales
        $id_destino = get_id_destino_inteligente($pdo, $get(['DESTINO', 'EDIFICIO']));
        $area = get_nombre_area_inteligente($pdo, $id_destino, $get(['AREA', 'UBICACION']));
        $tipo = $get(['TIPO EQUIPO', 'TIPO']);
        $marca = $get(['MARCA']);
        $modelo = $get(['MODELO']);
        $elemento = implode(' ', array_filter([$tipo, ($marca!='-'?$marca:''), ($modelo!='-'?$modelo:'')])) ?: "Item Importado";

        // --- SEPARACIÓN DE CÓDIGOS ---
        
        // 1. CODIGO PATRIMONIAL (Solo si dice explícitamente PATRIMONIAL)
        $cod_patrimonial = $get(['N° CARGO PATRIMONIAL', 'CARGO PATRIMONIAL', 'CODIGO PATRIMONIAL', 'PATRIMONIO']);
        if($cod_patrimonial == '-' || $cod_patrimonial == '') $cod_patrimonial = null;

        // 2. N° IOSFA
        $n_iosfa = $get(['N IOSFA SISTEMAS', 'IOSFA', 'N° IOSFA']);
        if($n_iosfa == '-' || $n_iosfa == '') $n_iosfa = null;

        // 3. N° SERIE (FÁBRICA) - Este irá al campo dinámico, NO al patrimonial
        $n_serie = $get(['NÚMERO DE SERIE', 'NUMERO DE SERIE', 'SERIE', 'N° SERIE']);
        if($n_serie == '-' || $n_serie == '') $n_serie = null;

        // Insertar Cargos (Patrimonial y IOSFA van aquí)
        $sql = "INSERT INTO inventario_cargos (
            id_usuario_relevador, id_tipo_bien, id_estado_fk, elemento, servicio_ubicacion, 
            destino_principal, nombre_responsable, nombre_jefe_servicio, fecha_creacion,
            codigo_patrimonial, n_iosfa, observaciones
        ) VALUES (?, ?, 1, ?, ?, ?, ?, ?, NOW(), ?, ?, ?)";

        $pdo->prepare($sql)->execute([
            $id_usuario, $id_tipo_bien, $elemento, $area, $id_destino, 
            $get(['RESPONSABLE']), $get(['JEFE']), 
            $cod_patrimonial, $n_iosfa, 'Importación Masiva.'
        ]);
        $id_cargo = $pdo->lastInsertId();

        // Insertar Dinámicos (MARCA, MODELO y SERIE van aquí)
        if (!empty($mapa_dinamico)) {
            // Marca
            if(isset($mapa_dinamico['MARCA']) && $marca && $marca!='-') 
                $pdo->prepare("INSERT INTO inventario_valores_dinamicos (id_cargo, id_campo, valor) VALUES (?,?,?)")->execute([$id_cargo, $mapa_dinamico['MARCA'], $marca]);
            // Modelo
            if(isset($mapa_dinamico['MODELO']) && $modelo && $modelo!='-') 
                $pdo->prepare("INSERT INTO inventario_valores_dinamicos (id_cargo, id_campo, valor) VALUES (?,?,?)")->execute([$id_cargo, $mapa_dinamico['MODELO'], $modelo]);
            // Serie (Buscamos la columna dinámica que se llame 'SERIE')
            $campo_serie_key = false;
            foreach($mapa_dinamico as $key => $val) { if(strpos($key, 'SERIE') !== false) $campo_serie_key = $val; }
            
            if($campo_serie_key && $n_serie) {
                $pdo->prepare("INSERT INTO inventario_valores_dinamicos (id_cargo, id_campo, valor) VALUES (?,?,?)")->execute([$id_cargo, $campo_serie_key, $n_serie]);
            }
        }
        $i++;
    }
    
    fclose($temp_handle);
    $pdo->commit();
    header("Location: inventario_lista.php?msg=importado_ok&cant=$i");

} catch (Exception $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    die("Error crítico: " . $e->getMessage());
}
?>