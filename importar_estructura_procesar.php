<?php
session_start();
include 'conexion.php';

if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_FILES['archivo_estructura']) && !empty($_POST['nombre_categoria'])) {
    
    $nombre_cat = trim($_POST['nombre_categoria']);
    $archivo = $_FILES['archivo_estructura']['tmp_name'];

    try {
        $pdo->beginTransaction();

        // 1. Gestionar Categoría (Busca o Crea)
        $stmt = $pdo->prepare("SELECT id_tipo_bien FROM inventario_tipos_bien WHERE nombre = ?");
        $stmt->execute([$nombre_cat]);
        $existe = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($existe) {
            $id_tipo = $existe['id_tipo_bien'];
            // Forzar flag 2 para que use el sistema dinámico
            $pdo->prepare("UPDATE inventario_tipos_bien SET tiene_campos_tecnicos = 2 WHERE id_tipo_bien = ?")->execute([$id_tipo]);
        } else {
            $stmt = $pdo->prepare("INSERT INTO inventario_tipos_bien (nombre, descripcion, icono, tiene_campos_tecnicos) VALUES (?, 'Importado', 'fas fa-file-csv', 2)");
            $stmt->execute([$nombre_cat]);
            $id_tipo = $pdo->lastInsertId();
        }

        // 2. Procesar Encabezados del CSV
        if (($handle = fopen($archivo, "r")) !== FALSE) {
            $linea1 = fgets($handle);
            rewind($handle);
            $separador = (substr_count($linea1, ';') > substr_count($linea1, ',')) ? ';' : ',';

            $encabezados = fgetcsv($handle, 10000, $separador);
            
            if ($encabezados) {
                // Obtener orden actual máximo para agregar al final
                $stmtOrden = $pdo->prepare("SELECT MAX(orden) FROM inventario_campos_dinamicos WHERE id_tipo_bien = ?");
                $stmtOrden->execute([$id_tipo]);
                $orden = ($stmtOrden->fetchColumn() ?: 0) + 1;

                foreach ($encabezados as $columna) {
                    $etiqueta = trim(trim($columna, "\xEF\xBB\xBF")); // Limpieza UTF-8 BOM
                    
                    if (!empty($etiqueta)) {
                        // Verificar existencia para no duplicar
                        $check = $pdo->prepare("SELECT id_campo FROM inventario_campos_dinamicos WHERE id_tipo_bien = ? AND etiqueta = ?");
                        $check->execute([$id_tipo, $etiqueta]);
                        
                        if (!$check->fetch()) {
                            $sql = "INSERT INTO inventario_campos_dinamicos (id_tipo_bien, etiqueta, orden, tipo_input) VALUES (?, ?, ?, 'text')";
                            $pdo->prepare($sql)->execute([$id_tipo, $etiqueta, $orden++]);
                        }
                    }
                }
            }
            fclose($handle);
        }

        $pdo->commit();
        $_SESSION['mensaje'] = "Estructura procesada dinámicamente.";
        
    } catch (Exception $e) {
        $pdo->rollBack();
        $_SESSION['error'] = "Error: " . $e->getMessage();
    }
}
header("Location: inventario_config.php");
exit();
?>