<?php
// Archivo: actualizar_chat_db.php (Maneja la solicitud AJAX para marcar como leído)
session_start();
// Incluimos conexion.php para acceder a la variable $pdo
include 'conexion.php'; 

// Verificamos que el usuario esté autenticado para seguridad
if (!isset($_SESSION['usuario_id'])) {
    header("HTTP/1.1 403 Forbidden");
    exit();
}

// Verificamos que sea una solicitud POST (que viene del JavaScript)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    
    $id_usuario = $_SESSION['usuario_id'];
    
    // 1. Intentamos añadir la columna (Esto es por si la ejecutan antes de que el dashboard lo haga)
    try {
        $sql_add_column = "ALTER TABLE usuarios ADD COLUMN chat_notificacion_leida BOOLEAN DEFAULT 0";
        $pdo->exec($sql_add_column);
    } catch (PDOException $e) {
        // El error 42S21 o 42S01 significa que la columna ya existe. Es seguro ignorarlo.
    }
    
    // 2. Marcamos al usuario actual como "Enterado" (1)
    try {
        $sql_update = "UPDATE usuarios SET chat_notificacion_leida = 1 WHERE id_usuario = :id";
        $stmt = $pdo->prepare($sql_update);
        
        if ($stmt->execute([':id' => $id_usuario])) {
            echo "success";
        } else {
            http_response_code(500);
            echo "error_update";
        }
        
    } catch (PDOException $e) {
        http_response_code(500);
        // error_log("Error de DB al actualizar estado de chat: " . $e->getMessage());
        echo "error_db";
    }
} else {
    http_response_code(400);
}
?>