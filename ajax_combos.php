<?php
// Archivo: ajax_combos.php
require_once 'conexion.php';
ob_clean(); 
header('Content-Type: application/json; charset=utf-8');

$accion = $_POST['accion'] ?? '';

try {
    // --- ESTADOS (NUEVO) ---
    // --- ESTADOS (INTELIGENTE) ---
    if ($accion === 'get_estados') {
        $tipo_bien_id = isset($_POST['id_tipo_bien']) && is_numeric($_POST['id_tipo_bien']) ? $_POST['id_tipo_bien'] : 0;
        
        // Lógica: Traer estados asignados a ESTA categoría O estados Generales (NULL)
        // Mantenemos compatibilidad con 'ambos'/'general' para los datos viejos que aún no actualices
        $sql = "SELECT id_estado, nombre FROM inventario_estados 
                WHERE id_tipo_bien = ? 
                OR id_tipo_bien IS NULL 
                OR (id_tipo_bien IS NULL AND ambito = 'ambos')
                OR (id_tipo_bien IS NULL AND ambito = 'general')";
        
        // Si es matafuego legacy, incluimos los viejos marcados como 'matafuego' aunque no tengan ID aún
        $es_matafuego = ($_POST['es_matafuego'] ?? 'false') === 'true';
        if ($es_matafuego) {
            $sql .= " OR (ambito = 'matafuego' AND id_tipo_bien IS NULL)";
        }

        $sql .= " ORDER BY nombre ASC";
        
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$tipo_bien_id]);
        echo json_encode($stmt->fetchAll(PDO::FETCH_ASSOC));
    }

    // --- MATAFUEGOS ---
    elseif ($accion === 'get_capacidades') {
        $stmt = $pdo->query("SELECT id_capacidad, capacidad FROM inventario_config_capacidades ORDER BY capacidad ASC");
        echo json_encode($stmt->fetchAll(PDO::FETCH_ASSOC));
    }
    elseif ($accion === 'get_agentes') {
        // CORRECCIÓN: Traemos 'vida_util' para el cálculo automático
        $stmt = $pdo->query("SELECT id_config, tipo_carga as nombre, vida_util FROM inventario_config_matafuegos ORDER BY tipo_carga ASC");
        echo json_encode($stmt->fetchAll(PDO::FETCH_ASSOC));
    }
    elseif ($accion === 'get_clases') {
        $stmt = $pdo->query("SELECT id_clase, nombre FROM inventario_config_clases ORDER BY nombre ASC");
        echo json_encode($stmt->fetchAll(PDO::FETCH_ASSOC));
    }
    
    // --- OTROS (Cámaras, IT) ---
    elseif ($accion === 'get_marcas') {
        $ambito = $_POST['ambito'] ?? '';
        $stmt = $pdo->prepare("SELECT id_marca, nombre FROM inventario_config_marcas WHERE ambito = ? ORDER BY nombre ASC");
        $stmt->execute([$ambito]);
        echo json_encode($stmt->fetchAll(PDO::FETCH_ASSOC));
    }
    elseif ($accion === 'get_tipos_it') {
        $stmt = $pdo->query("SELECT id_tipo_it, nombre FROM inventario_config_tipos_it ORDER BY nombre ASC");
        echo json_encode($stmt->fetchAll(PDO::FETCH_ASSOC));
    }
    elseif ($accion === 'get_modelos') {
        $id_marca = $_POST['id_marca'] ?? 0;
        $id_tipo_it = $_POST['id_tipo_it'] ?? null; // Puede venir vacío
        
        $sql = "SELECT id_modelo, nombre FROM inventario_config_modelos WHERE id_marca = ?";
        $params = [$id_marca];

        if (!empty($id_tipo_it) && $id_tipo_it != 'undefined') {
            $sql .= " AND (id_tipo_it = ? OR id_tipo_it IS NULL)";
            $params[] = $id_tipo_it;
        }
        $stmt = $pdo->prepare($sql . " ORDER BY nombre ASC");
        $stmt->execute($params);
        echo json_encode($stmt->fetchAll(PDO::FETCH_ASSOC));
    }

} catch (Exception $e) {
    echo json_encode(['error' => $e->getMessage()]);
}
?>