<?php
// chat_listar_items.php
error_reporting(0);
ini_set('display_errors', 0);
session_start();
include 'conexion.php';
if (ob_get_length()) ob_clean();
header('Content-Type: application/json');

$tipo = $_GET['tipo'] ?? '';
$items = [];

try {
    if ($tipo === 'tareas') {
        $sql = "SELECT id_tarea, titulo FROM tareas ORDER BY id_tarea DESC LIMIT 20";
        $stmt = $pdo->query($sql);
        while($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $items[] = ['tag' => "#T" . $row['id_tarea'], 'texto' => "Tarea #" . $row['id_tarea'] . ": " . substr($row['titulo'], 0, 30)];
        }
    } elseif ($tipo === 'pedidos') {
        $sql = "SELECT id_pedido, cliente FROM pedidos ORDER BY id_pedido DESC LIMIT 20";
        $stmt = $pdo->query($sql);
        while($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $items[] = ['tag' => "#P" . $row['id_pedido'], 'texto' => "Pedido #" . $row['id_pedido'] . " - " . substr($row['cliente'], 0, 20)];
        }
    }
} catch (Exception $e) {}
echo json_encode($items);
?>