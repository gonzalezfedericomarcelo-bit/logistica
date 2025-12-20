<?php
// Archivo: importar_estructura_procesar.php (VERSIÓN CORREGIDA)
session_start();
include 'conexion.php';

// Configuración de errores para ver qué pasa si falla algo más
ini_set('display_errors', 1);
error_reporting(E_ALL);

// 1. VALIDACIÓN DE SEGURIDAD
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header("Location: inventario_importar.php");
    exit();
}

// Recibimos los datos de forma segura (evita el error 'Undefined array key')
$nombre_cat = isset($_POST['nombre_categoria']) ? trim($_POST['nombre_categoria']) : '';
$archivo = isset($_FILES['archivo_estructura']['tmp_name']) ? $_FILES['archivo_estructura']['tmp_name'] : null;

// Si falta algo, detenemos todo y mostramos el error claro
if (empty($nombre_cat) || empty($archivo)) {
    echo "<div style='background:darkred; color:white; padding:20px; font-family:sans-serif;'>";
    echo "<h3>❌ Error: Faltan datos obligatorios.</h3>";
    echo "<p>Asegúrate de escribir un nombre para la categoría y seleccionar un archivo CSV.</p>";
    echo "<a href='inventario_importar.php' style='color:yellow'>Volver a intentar</a>";
    echo "</div>";
    exit();
}

try {
    $pdo->beginTransaction();

    // 2. GESTIONAR LA CATEGORÍA (CREAR O RECUPERAR)
    // Buscamos si ya existe una categoría con ese nombre exacto
    $stmt = $pdo->prepare("SELECT id_tipo_bien FROM inventario_tipos_bien WHERE nombre = ?");
    $stmt->execute([$nombre_cat]);
    $existe = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($existe) {
        $id_tipo = $existe['id_tipo_bien'];
        // Aseguramos que tenga el flag activado para campos técnicos (valor 2 = dinámico)
        $pdo->prepare("UPDATE inventario_tipos_bien SET tiene_campos_tecnicos = 2 WHERE id_tipo_bien = ?")->execute([$id_tipo]);
    } else {
        // Creamos la categoría nueva con un icono genérico
        $stmt = $pdo->prepare("INSERT INTO inventario_tipos_bien (nombre, descripcion, icono, tiene_campos_tecnicos) VALUES (?, 'Importado masivamente', 'fas fa-box-open', 2)");
        $stmt->execute([$nombre_cat]);
        $id_tipo = $pdo->lastInsertId();
    }

    // 3. PROCESAR EL ARCHIVO CSV
    if (($handle = fopen($archivo, "r")) !== FALSE) {
        // Leer primera línea para detectar separador
        $linea1 = fgets($handle);
        rewind($handle); // Volver al principio
        
        // Detectar si es punto y coma (Excel español) o coma (Estándar)
        $separador = (substr_count($linea1, ';') > substr_count($linea1, ',')) ? ';' : ',';

        // Leer los encabezados (la única fila que nos importa en este archivo)
        $encabezados = fgetcsv($handle, 0, $separador);
        
        if ($encabezados) {
            // Obtener el último número de orden para agregar al final
            $stmtOrden = $pdo->prepare("SELECT MAX(orden) FROM inventario_campos_dinamicos WHERE id_tipo_bien = ?");
            $stmtOrden->execute([$id_tipo]);
            $orden_actual = $stmtOrden->fetchColumn();
            $orden = ($orden_actual) ? $orden_actual + 1 : 1;

            foreach ($encabezados as $columna) {
                // Limpieza profunda de caracteres raros (BOM UTF-8) que rompen la primera columna
                $etiqueta = preg_replace('/[\x00-\x1F\x7F\xEF\xBB\xBF]/', '', $columna);
                $etiqueta = trim($etiqueta);
                
                if (!empty($etiqueta)) {
                    // Verificar si este campo ya existe en esta categoría para no duplicarlo
                    $check = $pdo->prepare("SELECT id_campo FROM inventario_campos_dinamicos WHERE id_tipo_bien = ? AND etiqueta = ?");
                    $check->execute([$id_tipo, $etiqueta]);
                    
                    if (!$check->fetch()) {
                        // Insertamos el campo nuevo. Por defecto tipo 'text'.
                        $sql = "INSERT INTO inventario_campos_dinamicos (id_tipo_bien, etiqueta, orden, tipo_input) VALUES (?, ?, ?, 'text')";
                        $pdo->prepare($sql)->execute([$id_tipo, $etiqueta, $orden++]);
                    }
                }
            }
        }
        fclose($handle);
    } else {
        throw new Exception("No se pudo abrir el archivo CSV.");
    }

    $pdo->commit();
    
    // Todo salió bien
    header("Location: inventario_lista.php?msg=estructura_ok&cat=" . urlencode($nombre_cat));
    exit();

} catch (Exception $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    echo "<div style='background:darkred; color:white; padding:20px; font-family:sans-serif;'>";
    echo "<h2>❌ Error en el proceso</h2>";
    echo "<p>" . $e->getMessage() . "</p>";
    echo "<a href='inventario_importar.php' style='color:yellow'>Volver</a>";
    echo "</div>";
    exit();
}
?>