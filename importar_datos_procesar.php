<?php
// Archivo: importar_datos_procesar.php (BLINDADO)
session_start();
include 'conexion.php';
set_time_limit(300);
ini_set('display_errors', 0); // Ocultar warnings en pantalla
error_reporting(E_ALL);

if ($_SERVER['REQUEST_METHOD'] !== 'POST') die("Error: Acceso incorrecto.");
if (empty($_POST['id_tipo_bien'])) die("Error: Selecciona la categoría.");

$id_tipo_bien = $_POST['id_tipo_bien'];
$archivo = $_FILES['archivo_datos']['tmp_name'];
$id_usuario = $_SESSION['usuario_id'] ?? 1;

// Función que no falla nunca
function dame_dato($header, $row, $posibles) {
    if (!is_array($posibles)) $posibles = [$posibles];
    foreach ($posibles as $p) {
        $key = mb_strtoupper(trim($p), 'UTF-8');
        if (isset($header[$key]) && isset($row[$header[$key]])) {
            $val = trim($row[$header[$key]]);
            return ($val === '' || $val === 'NO') ? '-' : $val;
        }
    }
    return '-';
}

try {
    $pdo->beginTransaction();

    // MAPEO DE CAMPOS
    $stmt = $pdo->prepare("SELECT id_campo, etiqueta FROM inventario_campos_dinamicos WHERE id_tipo_bien = ?");
    $stmt->execute([$id_tipo_bien]);
    $mapa = [];
    while($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $mapa[mb_strtoupper(trim($row['etiqueta']), 'UTF-8')] = $row['id_campo'];
    }

    if (($handle = fopen($archivo, "r")) !== FALSE) {
        $linea1 = fgets(fopen($archivo, 'r'));
        $separador = (substr_count($linea1, ';') > substr_count($linea1, ',')) ? ';' : ',';
        rewind($handle);

        $header = [];
        $fila = 0;

        while (($row = fgetcsv($handle, 1000, $separador)) !== FALSE) {
            if (empty($header)) {
                foreach($row as $k => $v) {
                    $limpio = mb_strtoupper(trim(preg_replace('/[\x00-\x1F\x7F]/', '', $v)), 'UTF-8');
                    if($limpio) $header[$limpio] = $k;
                }
                continue;
            }
            $fila++;

            // DATOS FIJOS
            $destino = dame_dato($header, $row, ['DESTINO', 'EDIFICIO']);
            $area    = dame_dato($header, $row, ['AREA', 'SECTOR']);
            $resp    = dame_dato($header, $row, ['RESPONSABLE', 'CARGO']);
            $jefe    = dame_dato($header, $row, 'JEFE');

            // DATOS ESPECÍFICOS
            $tipo_eq = dame_dato($header, $row, ['TIPO EQUIPO', 'TIPO']);
            $marca   = dame_dato($header, $row, 'MARCA');
            $modelo  = dame_dato($header, $row, 'MODELO');
            $tipo_ag = dame_dato($header, $row, ['TIPO AGENTE', 'TIPO DE CARGA']);
            $cap     = dame_dato($header, $row, ['CAPACIDAD', 'CAPACIDAD (KG)']);
            $clase   = dame_dato($header, $row, ['CLASE', 'CLASE FUEGO']);

            // NOMBRE AUTOMÁTICO
            $elemento = dame_dato($header, $row, ['ELEMENTO', 'EQUIPO', 'DESCRIPCION']);
            if ($elemento === '-' || $elemento === 'IMPORTADO') {
                if ($tipo_eq !== '-') {
                    $elemento = "$tipo_eq $marca $modelo";
                } elseif ($tipo_ag !== '-' || $cap !== '-') {
                    $elemento = "Matafuego $tipo_ag $cap ($clase)";
                } else {
                    $elemento = "$marca $modelo";
                }
            }
            $elemento = str_replace(' - ', ' ', $elemento); 

            // CÓDIGOS (CRUCIAL)
            $cod_pat = dame_dato($header, $row, ['N° IOSFA SISTEMAS', 'N° IOSE SISTEMAS', 'CODIGO PATRIMONIAL', 'CODIGO INTERNO']);
            $serie   = dame_dato($header, $row, ['N° SERIE', 'NUMERO DE SERIE', 'N° GRABADO', 'SERIE']);

            // INSERTAR
            $sql = "INSERT INTO inventario_cargos (
                id_usuario_relevador, id_estado_fk, elemento, fecha_creacion,
                destino_principal, servicio_ubicacion, 
                nombre_responsable, nombre_jefe_servicio, 
                codigo_inventario, mat_numero_grabado
            ) VALUES (?, 1, ?, NOW(), ?, ?, ?, ?, ?, ?)";
            
            $pdo->prepare($sql)->execute([$id_usuario, $elemento, $destino, $area, $resp, $jefe, $cod_pat, $serie]);
            $id_cargo = $pdo->lastInsertId();

            // GUARDAR DINÁMICOS
            foreach ($mapa as $etiqueta => $id_campo) {
                if (isset($header[$etiqueta])) {
                    $val = trim($row[$header[$etiqueta]] ?? '');
                    if ($val !== '') {
                        $pdo->prepare("INSERT INTO inventario_valores_dinamicos (id_cargo, id_campo, valor) VALUES (?, ?, ?)")
                            ->execute([$id_cargo, $id_campo, $val]);
                    }
                }
            }
        }
        fclose($handle);
        $pdo->commit();
    }
    header("Location: inventario_lista.php?msg=importado_ok&filas=$fila");
    exit();
} catch (Exception $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    die("Error Fatal: " . $e->getMessage());
}
?>