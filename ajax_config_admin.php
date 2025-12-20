<?php
// Archivo: ajax_config_admin.php
require_once 'conexion.php';
session_start();
header('Content-Type: application/json');

if (!isset($_SESSION['usuario_id'])) { echo json_encode(['status'=>'error','msg'=>'Auth']); exit; }

$accion = $_POST['accion'] ?? '';
$response = ['status'=>'error','msg'=>'Accion invalida'];

try {
    // --- 1. MATAFUEGOS ---
    if ($accion == 'add_agente') {
        $val = trim($_POST['valor']);
        $vida = (int)$_POST['vida_util'];
        if($val) {
            $pdo->prepare("INSERT INTO inventario_config_matafuegos (tipo_carga, vida_util) VALUES (?, ?)")->execute([$val, $vida]);
            $response = ['status'=>'ok', 'id'=>$pdo->lastInsertId(), 'valor'=>$val, 'vida'=>$vida];
        }
    }
    elseif ($accion == 'edit_agente') {
        $id = $_POST['id'];
        $val = trim($_POST['valor']);
        $vida = (int)$_POST['vida_util'];
        if($val && $id) {
            $pdo->prepare("UPDATE inventario_config_matafuegos SET tipo_carga = ?, vida_util = ? WHERE id_config = ?")->execute([$val, $vida, $id]);
            $response = ['status'=>'ok'];
        }
    }
    elseif ($accion == 'del_agente') {
        $pdo->prepare("DELETE FROM inventario_config_matafuegos WHERE id_config=?")->execute([$_POST['id']]);
        $response = ['status'=>'ok'];
    }
    // Clases y Capacidades (Genérico simple)
    elseif ($accion == 'add_clase') {
        $pdo->prepare("INSERT INTO inventario_config_clases (nombre) VALUES (?)")->execute([trim($_POST['valor'])]);
        $response = ['status'=>'ok', 'id'=>$pdo->lastInsertId(), 'valor'=>trim($_POST['valor'])];
    }
    elseif ($accion == 'del_clase') {
        $pdo->prepare("DELETE FROM inventario_config_clases WHERE id_clase=?")->execute([$_POST['id']]);
        $response = ['status'=>'ok'];
    }
    elseif ($accion == 'add_capacidad') {
        $pdo->prepare("INSERT INTO inventario_config_capacidades (capacidad) VALUES (?)")->execute([trim($_POST['valor'])]);
        $response = ['status'=>'ok', 'id'=>$pdo->lastInsertId(), 'valor'=>trim($_POST['valor'])];
    }
    elseif ($accion == 'del_capacidad') {
        $pdo->prepare("DELETE FROM inventario_config_capacidades WHERE id_capacidad=?")->execute([$_POST['id']]);
        $response = ['status'=>'ok'];
    }

    // --- 2. MARCAS Y MODELOS (Generico y Específico) ---
    elseif ($accion == 'add_marca') {
        $pdo->prepare("INSERT INTO inventario_config_marcas (nombre, ambito) VALUES (?, ?)")->execute([trim($_POST['valor']), $_POST['ambito']]);
        $response = ['status'=>'ok', 'id'=>$pdo->lastInsertId(), 'valor'=>trim($_POST['valor'])];
    }
    elseif ($accion == 'edit_marca') { // Edición simple de nombre
        $pdo->prepare("UPDATE inventario_config_marcas SET nombre = ? WHERE id_marca = ?")->execute([trim($_POST['valor']), $_POST['id']]);
        $response = ['status'=>'ok'];
    }
    elseif ($accion == 'del_marca') {
        $pdo->prepare("DELETE FROM inventario_config_marcas WHERE id_marca=?")->execute([$_POST['id']]);
        $pdo->prepare("DELETE FROM inventario_config_modelos WHERE id_marca=?")->execute([$_POST['id']]);
        $response = ['status'=>'ok'];
    }
    
    elseif ($accion == 'add_modelo') {
        // Obtenemos nombre marca para devolverlo al front
        $stmt = $pdo->prepare("SELECT nombre FROM inventario_config_marcas WHERE id_marca = ?");
        $stmt->execute([$_POST['id_marca']]);
        $marca = $stmt->fetchColumn();
        
        $pdo->prepare("INSERT INTO inventario_config_modelos (nombre, id_marca, id_tipo_it) VALUES (?, ?, ?)")
            ->execute([trim($_POST['valor']), $_POST['id_marca'], $_POST['id_tipo_it'] ?? null]);
        $response = ['status'=>'ok', 'id'=>$pdo->lastInsertId(), 'valor'=>trim($_POST['valor']), 'nombre_marca'=>$marca];
    }
    elseif ($accion == 'edit_modelo') {
        // Edición Completa: Nombre y Marca Padre
        $id = $_POST['id'];
        $val = trim($_POST['valor']);
        $id_marca = $_POST['id_marca'];
        if($val && $id && $id_marca) {
            $pdo->prepare("UPDATE inventario_config_modelos SET nombre = ?, id_marca = ? WHERE id_modelo = ?")->execute([$val, $id_marca, $id]);
            $response = ['status'=>'ok'];
        }
    }
    elseif ($accion == 'del_modelo') {
        $pdo->prepare("DELETE FROM inventario_config_modelos WHERE id_modelo=?")->execute([$_POST['id']]);
        $response = ['status'=>'ok'];
    }

    // --- 3. INFORMÁTICA (Tipos) ---
    elseif ($accion == 'add_tipo_it') {
        $pdo->prepare("INSERT INTO inventario_config_tipos_it (nombre) VALUES (?)")->execute([trim($_POST['valor'])]);
        $response = ['status'=>'ok', 'id'=>$pdo->lastInsertId(), 'valor'=>trim($_POST['valor'])];
    }
    elseif ($accion == 'del_tipo_it') {
        $pdo->prepare("DELETE FROM inventario_config_tipos_it WHERE id_tipo_it=?")->execute([$_POST['id']]);
        $response = ['status'=>'ok'];
    }

    // --- 4. ESTADOS ---
    elseif ($accion == 'add_estado') {
        $pdo->prepare("INSERT INTO inventario_estados (nombre, ambito) VALUES (?, ?)")->execute([trim($_POST['valor']), $_POST['ambito']]);
        $response = ['status'=>'ok', 'id'=>$pdo->lastInsertId()]; // Recargaremos pag
    }
    elseif ($accion == 'edit_estado') {
        $pdo->prepare("UPDATE inventario_estados SET nombre = ?, ambito = ? WHERE id_estado = ?")->execute([trim($_POST['valor']), $_POST['ambito'], $_POST['id']]);
        $response = ['status'=>'ok'];
    }
    elseif ($accion == 'del_estado') {
        $pdo->prepare("DELETE FROM inventario_estados WHERE id_estado=?")->execute([$_POST['id']]);
        $response = ['status'=>'ok'];
    }

    // --- 5. FICHAS ---
    elseif ($accion == 'add_ficha') {
        $es_tecnico = ($_POST['es_tecnico'] === 'true') ? 1 : 0;
        $pdo->prepare("INSERT INTO inventario_tipos_bien (nombre, icono, descripcion, tiene_campos_tecnicos, categoria_agrupadora) VALUES (?, ?, ?, ?, 'General')")
            ->execute([trim($_POST['nombre']), $_POST['icono'], $_POST['descripcion'], $es_tecnico]);
        $response = ['status'=>'ok'];
    }
    elseif ($accion == 'edit_ficha') {
        $es_tecnico = ($_POST['es_tecnico'] === 'true') ? 1 : 0;
        $pdo->prepare("UPDATE inventario_tipos_bien SET nombre=?, icono=?, descripcion=?, tiene_campos_tecnicos=? WHERE id_tipo_bien=?")
            ->execute([trim($_POST['nombre']), $_POST['icono'], $_POST['descripcion'], $es_tecnico, $_POST['id']]);
        $response = ['status'=>'ok'];
    }
    elseif ($accion == 'del_ficha') {
        $pdo->prepare("DELETE FROM inventario_tipos_bien WHERE id_tipo_bien=?")->execute([$_POST['id']]);
        $response = ['status'=>'ok'];
    }

    // --- EDICIÓN GENÉRICA SIMPLE (Para clases, capacidades, tipos IT) ---
    elseif ($accion == 'edit_simple') {
        $tabla = $_POST['tabla'];
        $campo_id = $_POST['campo_id'];
        $campo_val = $_POST['campo_val']; // nombre, capacidad, etc
        
        // Whitelist básica
        $tablas = ['inventario_config_clases', 'inventario_config_capacidades', 'inventario_config_tipos_it', 'inventario_config_marcas'];
        if(in_array($tabla, $tablas)) {
            $sql = "UPDATE $tabla SET $campo_val = ? WHERE $campo_id = ?";
            $pdo->prepare($sql)->execute([trim($_POST['valor']), $_POST['id']]);
            $response = ['status'=>'ok'];
        }
    }

} catch (Exception $e) {
    $response = ['status'=>'error', 'msg'=>$e->getMessage()];
}
echo json_encode($response);
?>