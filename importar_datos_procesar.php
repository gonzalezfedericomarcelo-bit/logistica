<?php
// Archivo: importar_datos_procesar.php (SOLUCIÓN DEFINITIVA RELACIONAL)
session_start();
include 'conexion.php';

// Configuración de errores y tiempo
ini_set('display_errors', 1);
error_reporting(E_ALL);
set_time_limit(0);
ini_set('auto_detect_line_endings', true);

if ($_SERVER['REQUEST_METHOD'] !== 'POST') die("Error: Formulario no enviado.");
if (empty($_FILES['archivo_datos']['name'])) die("Error: Archivo no adjunto.");

$id_tipo_bien = $_POST['id_tipo_bien']; // El ID de la categoría (ej: Matafuegos)
$archivo = $_FILES['archivo_datos']['tmp_name'];
$id_usuario = $_SESSION['usuario_id'] ?? 1;

try {
    $pdo->beginTransaction();

    // 1. FUNCIONES AUXILIARES PARA OBTENER IDs (Crucial para que el PDF funcione)
    
    // Función para obtener ID de CLASE (Ej: 'ABC' -> id_clase)
    function get_id_clase($pdo, $nombre) {
        if(empty($nombre)) return null;
        $nombre = strtoupper(trim($nombre));
        // Buscar si existe
        $stmt = $pdo->prepare("SELECT id_clase FROM inventario_config_clases WHERE UPPER(nombre) = ? LIMIT 1");
        $stmt->execute([$nombre]);
        $row = $stmt->fetch();
        if ($row) return $row['id_clase'];
        
        // Si no existe, crear
        $pdo->prepare("INSERT INTO inventario_config_clases (nombre) VALUES (?)")->execute([$nombre]);
        return $pdo->lastInsertId();
    }

    // Función para obtener ID de TIPO CARGA (Ej: 'POLVO QUIMICO' -> id_config)
    function get_id_tipo_carga($pdo, $nombre) {
        if(empty($nombre)) return null;
        $nombre = strtoupper(trim($nombre));
        $stmt = $pdo->prepare("SELECT id_config FROM inventario_config_matafuegos WHERE UPPER(tipo_carga) = ? LIMIT 1");
        $stmt->execute([$nombre]);
        $row = $stmt->fetch();
        if ($row) return $row['id_config'];
        
        $pdo->prepare("INSERT INTO inventario_config_matafuegos (tipo_carga) VALUES (?)")->execute([$nombre]);
        return $pdo->lastInsertId();
    }

    // 2. LEER CSV
    if (($handle = fopen($archivo, "r")) !== FALSE) {
        $linea1 = fgets($handle);
        $separador = (substr_count($linea1, ';') > substr_count($linea1, ',')) ? ';' : ',';
        rewind($handle);

        $headers = fgetcsv($handle, 0, $separador);
        $headers = array_map(function($h) { return mb_strtoupper(trim($h), 'UTF-8'); }, $headers);

        // Mapeo de campos dinámicos (para guardar lo que sobre en la ficha técnica)
        $stmt = $pdo->prepare("SELECT id_campo, etiqueta FROM inventario_campos_dinamicos WHERE id_tipo_bien = ?");
        $stmt->execute([$id_tipo_bien]);
        $mapa_dinamico = [];
        while($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $mapa_dinamico[mb_strtoupper(trim($row['etiqueta']), 'UTF-8')] = $row['id_campo'];
        }

        $i = 0;
        while (($fila = fgetcsv($handle, 0, $separador)) !== FALSE) {
            if(array_filter($fila) == []) continue;

            // Helper para sacar datos del CSV
            $get = function($nombres) use ($headers, $fila) {
                if(!is_array($nombres)) $nombres = [$nombres];
                foreach($nombres as $nom) {
                    $k = array_search(mb_strtoupper($nom, 'UTF-8'), $headers);
                    if($k !== false && isset($fila[$k])) return trim($fila[$k]);
                }
                return '';
            };

            // A. DATOS BÁSICOS
            $destino = $get(['DESTINO', 'EDIFICIO']);
            $area    = $get(['AREA', 'UBICACION', 'SECTOR']);
            $resp    = $get(['RESPONSABLE', 'RESPONSABLE DEL BIEN']);
            $jefe    = $get(['JEFE', 'JEFE DE SERVICIO']);

            // B. DATOS TÉCNICOS (MATAFUEGOS)
            $txt_clase      = $get(['CLASE FUEGO', 'CLASE', 'MAT_CLASE']);
            $txt_carga      = $get(['TIPO DE CARGA', 'TIPO AGENTE', 'AGENTE']);
            $capacidad      = $get(['CAPACIDAD', 'KILOS']);
            $num_grabado    = $get(['Nº MATAFUEGO', 'N MATAFUEGO', 'N GRABADO', 'NUMERO DE SERIE', 'SERIE']);
            $fecha_fab      = $get(['FABRICACIÓN', 'FABRICACION', 'AÑO FAB']);
            $fecha_carga    = $get(['ULTIMA CARGA', 'CARGA']);
            $fecha_ph       = $get(['PRUEBA HIDRÁULICA', 'PRUEBA HIDRAULICA', 'ULTIMA PH']);

            // Obtener IDs relacionales para el PDF
            $id_clase = get_id_clase($pdo, $txt_clase);
            $id_tipo_carga = get_id_tipo_carga($pdo, $txt_carga);

            // Formatear Fechas
            $fmt_fecha = function($f) {
                if (!$f) return null;
                if (is_numeric($f) && strlen($f) == 4) return "$f-01-01";
                return date('Y-m-d', strtotime(str_replace('/', '-', $f)));
            };

            // Nombre del Elemento
            $elemento = "MATAFUEGO";
            if($txt_carga) $elemento .= " " . $txt_carga;
            if($capacidad) $elemento .= " " . $capacidad . " KG";

            // C. INSERTAR EN BASE DE DATOS
            // Nota: Llenamos codigo_inventario con el número de grabado para que el PDF muestre el "Cargo Patrimonial"
            $sql = "INSERT INTO inventario_cargos (
                id_usuario_relevador, id_estado_fk, elemento, servicio_ubicacion, destino_principal, 
                nombre_responsable, nombre_jefe_servicio, fecha_creacion,
                mat_tipo_carga_id, mat_clase_id, mat_capacidad, mat_numero_grabado, 
                fecha_fabricacion, mat_fecha_carga, mat_fecha_ph,
                codigo_inventario 
            ) VALUES (?, 1, ?, ?, ?, ?, ?, NOW(), ?, ?, ?, ?, ?, ?, ?, ?)";
            
            $stmt = $pdo->prepare($sql);
            $stmt->execute([
                $id_usuario, 
                $elemento, 
                $area, 
                $destino, 
                $resp, 
                $jefe,
                $id_tipo_carga,      // ID real para el JOIN del PDF
                $id_clase,           // ID real para el JOIN del PDF
                $capacidad, 
                $num_grabado,        // "N° Serie de Fábrica" en el PDF
                $fmt_fecha($fecha_fab), 
                $fmt_fecha($fecha_carga), 
                $fmt_fecha($fecha_ph),
                $num_grabado         // "N° Cargo Patrimonial" en el PDF (Usamos el grabado como fallback)
            ]);
            $id_cargo = $pdo->lastInsertId();

            // D. GUARDAR EN FICHA DINÁMICA (Respaldo)
            foreach($headers as $idx => $h) {
                if(isset($mapa_dinamico[$h]) && isset($fila[$idx]) && $fila[$idx] !== '') {
                    $pdo->prepare("INSERT INTO inventario_valores_dinamicos (id_cargo, id_campo, valor) VALUES (?, ?, ?)")
                        ->execute([$id_cargo, $mapa_dinamico[$h], $fila[$idx]]);
                }
            }
            $i++;
        }
        fclose($handle);
        $pdo->commit();

        header("Location: inventario_lista.php?msg=importado_ok&cant=$i");
        exit();
    }
} catch (Exception $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    die("<div style='font-family:sans-serif; color:red; padding:20px;'>
            <h2>Error en la importación</h2>
            <p>" . $e->getMessage() . "</p>
            <p><strong>Posible solución:</strong> Verifica si faltan las tablas 'inventario_config_clases' o 'inventario_config_matafuegos'. Si no existen, el sistema intentó crearlas pero pudo fallar por permisos.</p>
         </div>");
}
?>