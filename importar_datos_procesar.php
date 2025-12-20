<?php
// Archivo: importar_datos_procesar.php (CORREGIDO: IDS Y CÓDIGOS)
session_start();
include 'conexion.php';
ini_set('display_errors', 1); error_reporting(E_ALL); set_time_limit(0);

// --- FUNCIONES DE BÚSQUEDA INTELIGENTE (IDS) ---
function get_id_destino($pdo, $nombre) {
    if(!$nombre) return null;
    $nombre = trim($nombre);
    // Buscar si existe el destino por nombre
    $stmt = $pdo->prepare("SELECT id_destino FROM destinos_internos WHERE nombre LIKE ? LIMIT 1");
    $stmt->execute([$nombre]);
    $row = $stmt->fetch();
    if($row) return $row['id_destino'];
    
    // Si no existe, LO CREAMOS
    $stmt = $pdo->prepare("INSERT INTO destinos_internos (nombre) VALUES (?)");
    $stmt->execute([$nombre]);
    return $pdo->lastInsertId();
}

function get_id_clase($pdo, $nombre) {
    if(!$nombre) return null;
    $stmt = $pdo->prepare("SELECT id_clase FROM inventario_config_clases WHERE UPPER(nombre) = ? LIMIT 1");
    $stmt->execute([strtoupper(trim($nombre))]);
    $row = $stmt->fetch();
    if ($row) return $row['id_clase'];
    $pdo->prepare("INSERT INTO inventario_config_clases (nombre) VALUES (?)")->execute([strtoupper(trim($nombre))]);
    return $pdo->lastInsertId();
}

// ... Recepción del archivo ...
$campo_archivo = !empty($_FILES['archivo_datos']['name']) ? 'archivo_datos' : 'archivo_excel';
if (empty($_FILES[$campo_archivo]['name'])) die("Error: No se recibió archivo.");

$archivo = $_FILES[$campo_archivo]['tmp_name'];
$id_usuario = $_SESSION['usuario_id'] ?? 1;
$id_tipo_bien = $_POST['id_tipo_bien'] ?? null;

try {
    $pdo->beginTransaction();

    if (($handle = fopen($archivo, "r")) !== FALSE) {
        $linea1 = fgets($handle);
        $separador = (substr_count($linea1, ';') > substr_count($linea1, ',')) ? ';' : ',';
        rewind($handle);
        
        // Limpieza de encabezados
        $headers_raw = fgetcsv($handle, 0, $separador);
        $headers = array_map(function($h) { 
            return mb_strtoupper(trim(preg_replace('/[\x00-\x1F\x7F\xEF\xBB\xBF]/', '', $h)), 'UTF-8'); 
        }, $headers_raw);

        // Mapeo campos dinámicos
        $mapa_dinamico = [];
        if ($id_tipo_bien) {
            $stmt = $pdo->prepare("SELECT id_campo, etiqueta FROM inventario_campos_dinamicos WHERE id_tipo_bien = ?");
            $stmt->execute([$id_tipo_bien]);
            while($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                $mapa_dinamico[mb_strtoupper(trim($row['etiqueta']), 'UTF-8')] = $row['id_campo'];
            }
        }

        $i = 0;
        while (($fila = fgetcsv($handle, 0, $separador)) !== FALSE) {
            if(array_filter($fila) == []) continue;

            $get = function($nombres) use ($headers, $fila) {
                if(!is_array($nombres)) $nombres = [$nombres];
                foreach($nombres as $nom) {
                    $k = array_search(mb_strtoupper($nom, 'UTF-8'), $headers);
                    if($k !== false && isset($fila[$k])) return trim($fila[$k]);
                }
                return '';
            };

            // A. DATOS Y UBICACIÓN
            $txt_destino = $get(['DESTINO', 'EDIFICIO', 'UBICACION PRINCIPAL']);
            $id_destino = get_id_destino($pdo, $txt_destino); // <--- AQUI LA MAGIA
            
            $area = $get(['AREA', 'UBICACION', 'SECTOR', 'SERVICIO']);
            $resp = $get(['RESPONSABLE']);
            $jefe = $get(['JEFE']);
            
            // B. IDENTIFICACIÓN
            $elemento = $get(['ELEMENTO', 'NOMBRE', 'MODELO', 'MARCA']);
            $marca = $get(['MARCA']);
            $modelo = $get(['MODELO']);
            if($marca || $modelo) $elemento = trim("$marca $modelo");
            
            // EL CODIGO: Buscamos SERIE o CODIGO
            $codigo = $get(['CODIGO', 'CODIGO PATRIMONIAL', 'SERIE', 'NUMERO DE SERIE', 'NUMERO SERIE']);

            // C. INSERTAR (Usando codigo_patrimonial y destino_principal como ID)
            $sql = "INSERT INTO inventario_cargos (
                id_usuario_relevador, id_tipo_bien, id_estado_fk, elemento, servicio_ubicacion, 
                destino_principal, nombre_responsable, nombre_jefe_servicio, fecha_creacion,
                codigo_patrimonial, observaciones
            ) VALUES (?, ?, 1, ?, ?, ?, ?, ?, NOW(), ?, 'Importado masivamente')";
            
            $stmt = $pdo->prepare($sql);
            $stmt->execute([
                $id_usuario, $id_tipo_bien, $elemento, $area, 
                $id_destino, // Ahora sí es un ID numérico
                $resp, $jefe, 
                $codigo // Ahora va a la columna correcta
            ]);
            $id_cargo = $pdo->lastInsertId();

            // D. DINÁMICOS
            if (!empty($mapa_dinamico)) {
                foreach($headers as $idx => $h) {
                    if(isset($mapa_dinamico[$h]) && isset($fila[$idx]) && $fila[$idx] !== '') {
                        $pdo->prepare("INSERT INTO inventario_valores_dinamicos (id_cargo, id_campo, valor) VALUES (?, ?, ?)")
                            ->execute([$id_cargo, $mapa_dinamico[$h], $fila[$idx]]);
                    }
                }
            }
            $i++;
        }
        fclose($handle);
        $pdo->commit();
        header("Location: inventario_lista.php?msg=importado_ok&cant=$i");

    } else { throw new Exception("No se pudo leer el archivo."); }

} catch (Exception $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    die("Error: " . $e->getMessage());
}
?>