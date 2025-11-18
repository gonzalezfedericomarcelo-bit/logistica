<?php
// Archivo: admin_usuarios_toggle_status.php
// *** MODIFICADO (v2) POR GEMINI PARA QUITAR RESTRICCIÓN 'rol = empleado' ***
session_start();
include 'conexion.php';
include 'funciones_permisos.php'; // Asegurar inclusión

// --- Respuesta JSON por defecto ---
header('Content-Type: application/json');
$response = ['success' => false, 'message' => 'Error no especificado.'];
// ---

// 1. Proteger (solo con permiso y POST)
// Reemplazando: $_SESSION['usuario_rol'] !== 'admin'
if (!isset($_SESSION['usuario_id']) || !tiene_permiso('admin_usuarios_toggle_status', $pdo) || $_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(403); $response['message'] = 'Acceso denegado.';
    echo json_encode($response);
    exit();
}

// 2. Obtener y validar ID de usuario y nuevo estado
$id_usuario_toggle = (int)($_POST['id_usuario'] ?? 0);
$nuevo_estado = isset($_POST['nuevo_estado']) ? (int)$_POST['nuevo_estado'] : -1; // -1 como inválido

if ($id_usuario_toggle <= 0 || ($nuevo_estado !== 0 && $nuevo_estado !== 1)) {
    http_response_code(400); // Bad Request
    $response['message'] = 'ID de usuario o estado inválido.';
    echo json_encode($response);
    exit();
}

// Seguridad: No permitir desactivar al propio admin
if ($id_usuario_toggle === $_SESSION['usuario_id'] && $nuevo_estado === 0) {
    http_response_code(400);
    $response['message'] = 'No puede desactivar su propia cuenta de administrador.';
    echo json_encode($response);
    exit();
}

// 3. Actualizar en la base de datos
try {
    // --- INICIO DE LA MODIFICACIÓN DE GEMINI ---
    // Se quitó "AND rol = 'empleado'" para activar/desactivar CUALQUIER rol
    $sql = "UPDATE usuarios SET activo = :activo WHERE id_usuario = :id";
    // --- FIN DE LA MODIFICACIÓN DE GEMINI ---
    
    $stmt = $pdo->prepare($sql);
    $stmt->bindParam(':activo', $nuevo_estado, PDO::PARAM_INT);
    $stmt->bindParam(':id', $id_usuario_toggle, PDO::PARAM_INT);
    $stmt->execute();

    if ($stmt->rowCount() > 0) {
        $response['success'] = true;
        $action_text = $nuevo_estado === 1 ? 'activado' : 'desactivado';
        $response['message'] = "Usuario {$action_text} exitosamente.";
    } else {
        // Podría ser que el ID no exista, o ya tuviera ese estado
        
        // --- INICIO DE LA MODIFICACIÓN DE GEMINI ---
        // Se quitó "AND rol = 'empleado'" del chequeo
         $check_exists_sql = "SELECT COUNT(*) FROM usuarios WHERE id_usuario = :id";
        // --- FIN DE LA MODIFICACIÓN DE GEMINI ---

         $check_stmt = $pdo->prepare($check_exists_sql);
         $check_stmt->execute([':id' => $id_usuario_toggle]);
         if ($check_stmt->fetchColumn() > 0) {
             $response['success'] = true; // Considerar éxito si el estado ya era el deseado
             $response['message'] = "El usuario ya se encontraba en ese estado.";
         } else {
             http_response_code(404); // Not Found
             $response['message'] = 'No se encontró un usuario con ese ID para cambiar el estado.';
         }
    }

} catch (PDOException $e) {
    http_response_code(500); // Internal Server Error
    error_log("Error DB al cambiar estado de usuario: " . $e->getMessage());
    $response['message'] = 'Error de base de datos al actualizar el estado.';
}

// 4. Devolver respuesta JSON
echo json_encode($response);
exit();
?>