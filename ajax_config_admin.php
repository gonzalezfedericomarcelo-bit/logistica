<?php
// Archivo: ajax_config_admin.php (CON GESTIÓN DE MEMBRETES ANUALES)
ob_start(); 
session_start();
include 'conexion.php';

ini_set('display_errors', 0);
error_reporting(E_ALL);

if (!isset($_SESSION['usuario_id'])) { 
    ob_end_clean(); echo json_encode(['status'=>'error','msg'=>'Sin sesión']); exit; 
}

$accion = $_POST['accion'] ?? '';
$response = ['status'=>'error','msg'=>'Accion no valida'];

try {
    // --- 1. GESTIÓN DE FICHAS Y ESTRUCTURA ---
    if ($accion == 'get_ficha_campos') {
        $stmt = $pdo->prepare("SELECT id_campo, etiqueta, id_campo_dependencia FROM inventario_campos_dinamicos WHERE id_tipo_bien = ? ORDER BY orden ASC");
        $stmt->execute([$_POST['id']]);
        $data = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        ob_end_clean(); 
        header('Content-Type: application/json');
        echo json_encode($data);
        exit;
    }

    elseif ($accion == 'add_ficha') {
        $color = $_POST['color'] ?? 'primary';
        $pdo->prepare("INSERT INTO inventario_tipos_bien (nombre, icono, descripcion, color, tiene_campos_tecnicos) VALUES (?, ?, ?, ?, 2)")
            ->execute([trim($_POST['nombre']), $_POST['icono'], $_POST['descripcion'], $color]);
        $response = ['status'=>'ok'];
    }

    elseif ($accion == 'edit_ficha') {
        $pdo->beginTransaction();
        $id_tipo = $_POST['id'];
        $color = $_POST['color'] ?? 'primary';

        $pdo->prepare("UPDATE inventario_tipos_bien SET nombre=?, icono=?, descripcion=?, color=? WHERE id_tipo_bien=?")
            ->execute([trim($_POST['nombre']), $_POST['icono'], $_POST['descripcion'], $color, $id_tipo]);

        if (isset($_POST['campos'])) {
            $ids_mantener = [];
            $stmtOrd = $pdo->prepare("SELECT MAX(orden) FROM inventario_campos_dinamicos WHERE id_tipo_bien = ?");
            $stmtOrd->execute([$id_tipo]);
            $orden = ($stmtOrd->fetchColumn() ?: 0) + 1;

            foreach ($_POST['campos'] as $c) {
                $etiqueta = trim($c['valor']);
                $id_dependencia = (isset($c['dependencia']) && $c['dependencia'] != '') ? $c['dependencia'] : null;

                if(empty($etiqueta)) continue;

                if (isset($c['id']) && is_numeric($c['id']) && $c['id'] > 0) {
                    $pdo->prepare("UPDATE inventario_campos_dinamicos SET etiqueta = ?, id_campo_dependencia = ? WHERE id_campo = ?")
                        ->execute([$etiqueta, $id_dependencia, $c['id']]);
                    $ids_mantener[] = $c['id'];
                } else {
                    $stmtIns = $pdo->prepare("INSERT INTO inventario_campos_dinamicos (id_tipo_bien, etiqueta, tipo_input, id_campo_dependencia, orden) VALUES (?, ?, 'text', ?, ?)");
                    $stmtIns->execute([$id_tipo, $etiqueta, $id_dependencia, $orden++]);
                    $ids_mantener[] = $pdo->lastInsertId();
                }
            }

            if (!empty($ids_mantener)) {
                $inQuery = implode(',', array_fill(0, count($ids_mantener), '?'));
                $pdo->prepare("DELETE FROM inventario_campos_dinamicos WHERE id_tipo_bien = ? AND id_campo NOT IN ($inQuery)")
                    ->execute(array_merge([$id_tipo], $ids_mantener));
            } else {
                $pdo->prepare("DELETE FROM inventario_campos_dinamicos WHERE id_tipo_bien = ?")->execute([$id_tipo]);
            }
        }

        $pdo->commit();
        $response = ['status'=>'ok'];
    }
    
    // --- 2. CONFIGURACIONES SIMPLES ---
    elseif ($accion == 'add_agente') {
        $pdo->prepare("INSERT INTO inventario_config_matafuegos (tipo_carga, vida_util) VALUES (?, ?)")->execute([$_POST['valor'], $_POST['vida_util']]);
        $response = ['status'=>'ok'];
    }
    elseif ($accion == 'edit_agente') {
        $pdo->prepare("UPDATE inventario_config_matafuegos SET tipo_carga=?, vida_util=? WHERE id_config=?")->execute([$_POST['valor'], $_POST['vida_util'], $_POST['id']]);
        $response = ['status'=>'ok'];
    }
    elseif ($accion == 'add_marca') {
        $pdo->prepare("INSERT INTO inventario_config_marcas (nombre, ambito) VALUES (?, ?)")->execute([$_POST['valor'], $_POST['ambito']]);
        $response = ['status'=>'ok'];
    }
    elseif ($accion == 'edit_marca') {
        $pdo->prepare("UPDATE inventario_config_marcas SET nombre = ? WHERE id_marca = ?")->execute([$_POST['valor'], $_POST['id']]);
        $response = ['status'=>'ok'];
    }
    elseif ($accion == 'add_modelo') {
        $pdo->prepare("INSERT INTO inventario_config_modelos (nombre, id_marca) VALUES (?, ?)")->execute([$_POST['valor'], $_POST['id_marca']]);
        $response = ['status'=>'ok'];
    }
    elseif ($accion == 'edit_modelo') {
        $pdo->prepare("UPDATE inventario_config_modelos SET nombre = ?, id_marca = ? WHERE id_modelo = ?")->execute([$_POST['valor'], $_POST['id_marca'], $_POST['id']]);
        $response = ['status'=>'ok'];
    }
    elseif ($accion == 'add_estado') {
        $id_tipo = (!empty($_POST['id_tipo_bien']) && is_numeric($_POST['id_tipo_bien'])) ? $_POST['id_tipo_bien'] : null;
        $ambito_legacy = $id_tipo ? 'especifico' : 'general'; 
        $pdo->prepare("INSERT INTO inventario_estados (nombre, ambito, id_tipo_bien) VALUES (?, ?, ?)")
            ->execute([$_POST['valor'], $ambito_legacy, $id_tipo]);
        $response = ['status'=>'ok'];
    }
    elseif ($accion == 'edit_estado') {
        $id_tipo = (!empty($_POST['id_tipo_bien']) && is_numeric($_POST['id_tipo_bien'])) ? $_POST['id_tipo_bien'] : null;
        $ambito_legacy = $id_tipo ? 'especifico' : 'general';
        $pdo->prepare("UPDATE inventario_estados SET nombre=?, ambito=?, id_tipo_bien=? WHERE id_estado=?")
            ->execute([$_POST['valor'], $ambito_legacy, $id_tipo, $_POST['id']]);
        $response = ['status'=>'ok'];
    }
    elseif (in_array($accion, ['add_clase', 'add_capacidad', 'add_tipo_it'])) {
        $tablas = [
            'add_clase' => ['inventario_config_clases', 'nombre'],
            'add_capacidad' => ['inventario_config_capacidades', 'capacidad'],
            'add_tipo_it' => ['inventario_config_tipos_it', 'nombre']
        ];
        $t = $tablas[$accion];
        $pdo->prepare("INSERT INTO {$t[0]} ({$t[1]}) VALUES (?)")->execute([$_POST['valor']]);
        $response = ['status'=>'ok'];
    }
    elseif ($accion == 'edit_simple') {
        $tablas_permitidas = ['inventario_config_clases', 'inventario_config_capacidades', 'inventario_config_tipos_it'];
        $tabla = $_POST['tabla']; $campo_id = $_POST['campo_id']; $campo_val = $_POST['campo_val']; 
        if (in_array($tabla, $tablas_permitidas)) {
            if (preg_match('/^[a-zA-Z0-9_]+$/', $campo_val) && preg_match('/^[a-zA-Z0-9_]+$/', $campo_id)) {
                $pdo->prepare("UPDATE $tabla SET $campo_val = ? WHERE $campo_id = ?")->execute([$_POST['valor'], $_POST['id']]);
                $response = ['status'=>'ok'];
            }
        }
    }
    elseif (strpos($accion, 'del_') === 0) {
        $tablas = [
            'del_ficha' => ['inventario_tipos_bien', 'id_tipo_bien'],
            'del_agente' => ['inventario_config_matafuegos', 'id_config'],
            'del_clase' => ['inventario_config_clases', 'id_clase'],
            'del_capacidad' => ['inventario_config_capacidades', 'id_capacidad'],
            'del_tipo_it' => ['inventario_config_tipos_it', 'id_tipo_it'],
            'del_marca' => ['inventario_config_marcas', 'id_marca'],
            'del_modelo' => ['inventario_config_modelos', 'id_modelo'],
            'del_estado' => ['inventario_estados', 'id_estado'],
            'del_membrete' => ['inventario_config_membretes', 'anio'] // <--- NUEVO: Para borrar membretes
        ];
        if(isset($tablas[$accion])) {
            $pdo->prepare("DELETE FROM {$tablas[$accion][0]} WHERE {$tablas[$accion][1]} = ?")->execute([$_POST['id']]);
            $response = ['status'=>'ok'];
        }
    }

    // --- 3. GESTIÓN DE MEMBRETES (NUEVO) ---
    elseif ($accion == 'add_membrete') {
        $anio = (int)$_POST['anio'];
        $texto = trim($_POST['texto']);
        // REPLACE INTO funciona como "Insertar o Actualizar si ya existe la clave primaria (año)"
        $pdo->prepare("REPLACE INTO inventario_config_membretes (anio, texto) VALUES (?, ?)")->execute([$anio, $texto]);
        $response = ['status'=>'ok'];
    }

} catch (Exception $e) {
    if($pdo->inTransaction()) $pdo->rollBack();
    $response = ['status'=>'error', 'msg'=>$e->getMessage()];
}

ob_end_clean(); 
header('Content-Type: application/json');
echo json_encode($response);
?>