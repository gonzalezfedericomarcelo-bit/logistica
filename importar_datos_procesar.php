<?php
// Archivo: importar_datos_procesar.php (Carga Masiva de Bienes)
session_start();
include 'conexion.php';

// Aumentar tiempo de ejecución para archivos grandes
set_time_limit(300); 

if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_FILES['archivo_datos'])) {
    
    $id_tipo_bien = $_POST['id_tipo_bien']; // La categoría donde vamos a meter los datos
    $archivo = $_FILES['archivo_datos']['tmp_name'];
    $id_usuario = $_SESSION['usuario_id'];

    if (empty($id_tipo_bien) || empty($archivo)) die("Error: Datos faltantes.");

    // 1. Obtener los campos dinámicos de esta categoría para mapearlos
    $stmtCampos = $pdo->prepare("SELECT id_campo, etiqueta FROM inventario_campos_dinamicos WHERE id_tipo_bien = ?");
    $stmtCampos->execute([$id_tipo_bien]);
    $mapa_campos = []; // [ 'MARCA' => id_campo_15, 'MODELO' => id_campo_16 ]
    while($row = $stmtCampos->fetch(PDO::FETCH_ASSOC)) {
        $mapa_campos[strtoupper(trim($row['etiqueta']))] = $row['id_campo'];
    }

    // 2. Leer CSV
    if (($handle = fopen($archivo, "r")) !== FALSE) {
        
        $fila = 0;
        $indices_header = []; // Guardará en qué columna está cada dato (Ej: 'MARCA' => col 3)
        
        while (($datos = fgetcsv($handle, 1000, ",")) !== FALSE) {
            // Fix punto y coma
            if (count($datos) == 1 && strpos($datos[0], ';') !== false) $datos = explode(';', $datos[0]);

            // Detectar cabecera (primera fila con datos)
            if (empty($indices_header)) {
                $tiene_datos = false;
                foreach($datos as $idx => $val) {
                    if(trim($val) != '') {
                        $indices_header[strtoupper(trim($val))] = $idx;
                        $tiene_datos = true;
                    }
                }
                if(!$tiene_datos) $indices_header = []; // Si era fila vacía, seguir buscando
                continue;
            }

            // PROCESAR FILA DE DATOS
            $fila++;
            
            // A. Extraer Datos Estándar (Columnas fijas si existen en el Excel)
            // Buscamos columnas como 'DESTINO', 'AREA', 'ELEMENTO', etc.
            $destino_nom = isset($indices_header['DESTINO']) ? trim($datos[$indices_header['DESTINO']]) : 'SIN ASIGNAR';
            $area_nom = isset($indices_header['AREA']) ? trim($datos[$indices_header['AREA']]) : 'GENERAL';
            $responsable = isset($indices_header['RESPONSABLE A CARGO']) ? trim($datos[$indices_header['RESPONSABLE A CARGO']]) : '';
            $jefe = isset($indices_header['JEFE DE SERVICIO']) ? trim($datos[$indices_header['JEFE DE SERVICIO']]) : '';
            
            // Descripción del bien (Si hay columna EQUIPO o ELEMENTO, sino usa el nombre de categoría)
            $elemento = "Importado";
            if(isset($indices_header['EQUIPO'])) $elemento = trim($datos[$indices_header['EQUIPO']]);
            elseif(isset($indices_header['ELEMENTO'])) $elemento = trim($datos[$indices_header['ELEMENTO']]);

            // B. Resolver ID Destino (Crear si no existe)
            // Nota: Esto es básico. Idealmente debería buscar coincidencia exacta.
            $id_destino = 1; // Default
            if($destino_nom) {
                $stmtDest = $pdo->prepare("SELECT id_destino FROM destinos_internos WHERE nombre LIKE ?");
                $stmtDest->execute([$destino_nom]);
                $id_destino = $stmtDest->fetchColumn();
                if(!$id_destino) {
                    $pdo->prepare("INSERT INTO destinos_internos (nombre) VALUES (?)")->execute([$destino_nom]);
                    $id_destino = $pdo->lastInsertId();
                }
            }

            // C. Insertar el Cargo (Base)
            $sql = "INSERT INTO inventario_cargos (
                id_usuario_relevador, id_estado_fk, elemento, servicio_ubicacion, 
                nombre_responsable, nombre_jefe_servicio, fecha_creacion, id_destino
            ) VALUES (?, 1, ?, ?, ?, ?, NOW(), ?)";
            
            $pdo->prepare($sql)->execute([$id_usuario, $elemento, $area_nom, $responsable, $jefe, $id_destino]);
            $id_cargo = $pdo->lastInsertId();

            // D. Insertar Valores Dinámicos (Marca, Modelo, etc.)
            // Recorremos el mapa de campos que tiene la categoría
            foreach($mapa_campos as $nombre_columna_csv => $id_campo_db) {
                if(isset($indices_header[$nombre_columna_csv])) {
                    $valor = trim($datos[$indices_header[$nombre_columna_csv]]);
                    if($valor != '') {
                        $pdo->prepare("INSERT INTO inventario_valores_dinamicos (id_cargo, id_campo, valor) VALUES (?, ?, ?)")
                            ->execute([$id_cargo, $id_campo_db, $valor]);
                    }
                }
            }
        }
        fclose($handle);
    }

    header("Location: inventario_lista.php?msg=importacion_exito");
    exit();
}
?>