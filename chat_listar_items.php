<?php
// chat_listar_items.php - CON FILTROS DINÁMICOS Y CATEGORÍAS
error_reporting(0);
ini_set('display_errors', 0);
session_start();
include 'conexion.php';
if (ob_get_length()) ob_clean();
header('Content-Type: application/json; charset=utf-8');

try { if(isset($pdo)) $pdo->exec("SET NAMES utf8mb4"); } catch(Exception $e){}

$tipo = $_GET['tipo'] ?? '';
$action = $_GET['action'] ?? 'listar'; // 'listar' o 'filtros'
$q = isset($_GET['q']) ? trim($_GET['q']) : '';

$items = [];

try {
    // --- MODO 1: DEVOLVER LISTA DE FILTROS (CATEGORÍAS/ESTADOS) ---
    if ($action === 'filtros') {
        if ($tipo === 'tareas') {
            // Obtener categorías reales de la BD
            $sql = "SELECT nombre FROM categorias ORDER BY nombre ASC";
            $stmt = $pdo->query($sql);
            while($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                $items[] = $row['nombre'];
            }
        } elseif ($tipo === 'pedidos') {
            // Para pedidos, devolvemos estados comunes
            $items = ['Pendiente', 'En Proceso', 'Finalizado', 'Cancelado'];
        }
        echo json_encode($items);
        exit;
    }

    // --- MODO 2: LISTADO DE ITEMS (BUSCADOR) ---
    if ($tipo === 'tareas') {
        // JOIN con categorías para buscar por rubro
        $sql = "SELECT t.id_tarea, t.titulo, t.estado, t.prioridad, t.descripcion,
                       uc.nombre_completo as creador, 
                       ua.nombre_completo as tecnico,
                       c.nombre as categoria
                FROM tareas t
                LEFT JOIN usuarios uc ON t.id_creador = uc.id_usuario
                LEFT JOIN usuarios ua ON t.id_asignado = ua.id_usuario
                LEFT JOIN categorias c ON t.id_categoria = c.id_categoria
                WHERE 1=1";
        
        $params = [];
        
        if (!empty($q)) {
            $sql .= " AND (
                t.id_tarea LIKE :q OR 
                t.titulo LIKE :q OR 
                t.descripcion LIKE :q OR 
                t.estado LIKE :q OR 
                uc.nombre_completo LIKE :q OR 
                ua.nombre_completo LIKE :q OR
                c.nombre LIKE :q
            )";
            $params[':q'] = "%$q%";
        }
        
        $sql .= " ORDER BY t.id_tarea DESC LIMIT 50";
        
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        
        while($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $color = 'secondary';
            $estado_txt = ucfirst($row['estado']);
            
            if ($row['estado'] == 'verificada' || $row['estado'] == 'finalizada') $color = 'success';
            elseif ($row['estado'] == 'cancelada') $color = 'danger';
            elseif ($row['estado'] == 'en_proceso') $color = 'primary';
            elseif ($row['estado'] == 'pendiente') $color = 'warning text-dark';
            
            $tecnico = $row['tecnico'] ?? 'Sin asignar';
            $cat = $row['categoria'] ?? 'Gral';

            // String de búsqueda completo
            $search_term = strtolower("tarea #{$row['id_tarea']} {$row['titulo']} {$cat} {$estado_txt} {$tecnico}");

            $items[] = [
                'tag' => "#T" . $row['id_tarea'], 
                'titulo' => substr($row['titulo'], 0, 50),
                'subtexto' => "{$cat} | Tec: " . substr($tecnico, 0, 10) . " | " . $estado_txt,
                'badge_color' => $color,
                'badge_text' => "#" . $row['id_tarea'],
                'full_search' => $search_term
            ];
        }
    } elseif ($tipo === 'pedidos') {
        $sql = "SELECT p.id_pedido, p.solicitante_real_nombre, p.descripcion_sintomas, p.id_tarea_generada,
                       u.nombre_completo as auxiliar
                FROM pedidos_trabajo p
                LEFT JOIN usuarios u ON p.id_auxiliar = u.id_usuario
                WHERE 1=1";
                
        $params = [];
        if (!empty($q)) {
            $sql .= " AND (p.id_pedido LIKE :q OR p.solicitante_real_nombre LIKE :q OR p.descripcion_sintomas LIKE :q OR u.nombre_completo LIKE :q)";
            $params[':q'] = "%$q%";
        }
        
        $sql .= " ORDER BY p.id_pedido DESC LIMIT 50";
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        
        while($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $desc = !empty($row['solicitante_real_nombre']) ? $row['solicitante_real_nombre'] : $row['descripcion_sintomas'];
            if(empty($desc)) $desc = "Sin detalle";
            
            if (!empty($row['id_tarea_generada']) && $row['id_tarea_generada'] > 0) {
                $estado_txt = "En Proceso"; $color = 'success';
            } else {
                $estado_txt = "Pendiente"; $color = 'warning text-dark';
            }

            $items[] = [
                'tag' => "#P" . $row['id_pedido'], 
                'titulo' => substr($desc, 0, 40),
                'subtexto' => "Pedido #" . $row['id_pedido'] . " (" . $estado_txt . ")",
                'badge_color' => $color,
                'badge_text' => "#" . $row['id_pedido'],
                'full_search' => strtolower("pedido #{$row['id_pedido']} {$desc} {$estado_txt}")
            ];
        }
    }
} catch (Exception $e) { }
echo json_encode($items);
?>