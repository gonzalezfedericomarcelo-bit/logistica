<?php
// Archivo: ajax_config_admin.php (CON LIMPIEZA DE BUFFER PARA EVITAR ERRORES DE CARGA)
ob_start(); // Inicia captura de salida
session_start();
include 'conexion.php';

// Ocultar errores visuales para no romper el JSON
ini_set('display_errors', 0);
error_reporting(E_ALL);

if (!isset($_SESSION['usuario_id'])) { 
    ob_end_clean(); echo json_encode(['status'=>'error','msg'=>'Sin sesión']); exit; 
}

$accion = $_POST['accion'] ?? '';
$response = ['status'=>'error','msg'=>'Accion no valida'];

try {
    // --- GESTIÓN DE FICHAS Y ESTRUCTURA ---
    if ($accion == 'get_ficha_campos') {
        $stmt = $pdo->prepare("SELECT id_campo, etiqueta FROM inventario_campos_dinamicos WHERE id_tipo_bien = ? ORDER BY orden ASC");
        $stmt->execute([$_POST['id']]);
        $data = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        ob_end_clean(); // Limpiar cualquier texto basura antes de enviar
        header('Content-Type: application/json');
        echo json_encode($data);
        exit;
    }

    elseif ($accion == 'add_ficha') {
        $pdo->prepare("INSERT INTO inventario_tipos_bien (nombre, icono, descripcion, tiene_campos_tecnicos) VALUES (?, ?, ?, 2)")
            ->execute([trim($_POST['nombre']), $_POST['icono'], $_POST['descripcion']]);
        $response = ['status'=>'ok'];
    }

    elseif ($accion == 'edit_ficha') {
        $pdo->beginTransaction();
        $id_tipo = $_POST['id'];

        // 1. Datos Básicos
        $pdo->prepare("UPDATE inventario_tipos_bien SET nombre=?, icono=?, descripcion=? WHERE id_tipo_bien=?")
            ->execute([trim($_POST['nombre']), $_POST['icono'], $_POST['descripcion'], $id_tipo]);

        // 2. Estructura (Columnas)
        $campos_enviados = isset($_POST['campos']) ? $_POST['campos'] : [];
        
        // ¡IMPORTANTE! Si no envía campos, NO borramos todo ciegamente. 
        // Solo procesamos si el array existe.
        if (isset($_POST['campos'])) {
            $ids_mantener = [];
            
            // Obtener orden
            $stmtOrd = $pdo->prepare("SELECT MAX(orden) FROM inventario_campos_dinamicos WHERE id_tipo_bien = ?");
            $stmtOrd->execute([$id_tipo]);
            $orden = ($stmtOrd->fetchColumn() ?: 0) + 1;

            foreach ($campos_enviados as $c) {
                $etiqueta = trim($c['valor']);
                if(empty($etiqueta)) continue;

                if (isset($c['id']) && is_numeric($c['id'])) {
                    $pdo->prepare("UPDATE inventario_campos_dinamicos SET etiqueta = ? WHERE id_campo = ?")
                        ->execute([$etiqueta, $c['id']]);
                    $ids_mantener[] = $c['id'];
                } else {
                    $pdo->prepare("INSERT INTO inventario_campos_dinamicos (id_tipo_bien, etiqueta, tipo_input, orden) VALUES (?, ?, 'text', ?)")
                        ->execute([$id_tipo, $etiqueta, $orden++]);
                }
            }

            // Solo borramos los que NO vinieron en la lista (pero la lista debe haber cargado primero)
            if (!empty($ids_mantener)) {
                $inQuery = implode(',', array_fill(0, count($ids_mantener), '?'));
                $pdo->prepare("DELETE FROM inventario_campos_dinamicos WHERE id_tipo_bien = ? AND id_campo NOT IN ($inQuery)")
                    ->execute(array_merge([$id_tipo], $ids_mantener));
            } elseif (count($campos_enviados) == 0) {
                // Si la lista vino vacía explícitamente (el usuario borró todo), borramos todo.
                // PERO el JS ahora previene enviar esto si fue un error de carga.
                $pdo->prepare("DELETE FROM inventario_campos_dinamicos WHERE id_tipo_bien = ?")->execute([$id_tipo]);
            }
        }

        $pdo->commit();
        $response = ['status'=>'ok'];
    }
    
    // --- Resto de acciones (Matafuegos, etc) se mantienen igual ---
    elseif ($accion == 'add_agente') {
        $pdo->prepare("INSERT INTO inventario_config_matafuegos (tipo_carga, vida_util) VALUES (?, ?)")->execute([$_POST['valor'], $_POST['vida_util']]);
        $response = ['status'=>'ok'];
    }
    elseif ($accion == 'edit_agente') {
        $pdo->prepare("UPDATE inventario_config_matafuegos SET tipo_carga=?, vida_util=? WHERE id_config=?")->execute([$_POST['valor'], $_POST['vida_util'], $_POST['id']]);
        $response = ['status'=>'ok'];
    }
    elseif (strpos($accion, 'del_') === 0) {
        // Mapeo simple de borrado
        $tablas = [
            'del_ficha' => ['inventario_tipos_bien', 'id_tipo_bien'],
            'del_agente' => ['inventario_config_matafuegos', 'id_config'],
            'del_clase' => ['inventario_config_clases', 'id_clase'],
            'del_capacidad' => ['inventario_config_capacidades', 'id_capacidad'],
            'del_tipo_it' => ['inventario_config_tipos_it', 'id_tipo_it'],
            'del_marca' => ['inventario_config_marcas', 'id_marca'],
            'del_modelo' => ['inventario_config_modelos', 'id_modelo'],
            'del_estado' => ['inventario_estados', 'id_estado']
        ];
        if(isset($tablas[$accion])) {
            $pdo->prepare("DELETE FROM {$tablas[$accion][0]} WHERE {$tablas[$accion][1]} = ?")->execute([$_POST['id']]);
            $response = ['status'=>'ok'];
        }
    }
    // Agrega aquí los add/edit simples si faltan, pero lo crítico es lo de arriba.

} catch (Exception $e) {
    if($pdo->inTransaction()) $pdo->rollBack();
    $response = ['status'=>'error', 'msg'=>$e->getMessage()];
}

ob_end_clean(); // Limpieza final
header('Content-Type: application/json');
echo json_encode($response);
?>