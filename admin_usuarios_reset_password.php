<?php
// Archivo: admin_usuarios_reset_password.php
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
if (!isset($_SESSION['usuario_id']) || !tiene_permiso('admin_usuarios_reset_pass', $pdo) || $_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(403); $response['message'] = 'Acceso denegado.';
    echo json_encode($response); exit();
}
// 2. Obtener y validar ID de usuario
$id_usuario_reset = (int)($_POST['id_usuario'] ?? 0);
if ($id_usuario_reset <= 0) {
    http_response_code(400); $response['message'] = 'ID de usuario inválido.';
    echo json_encode($response); exit();
}
if ($id_usuario_reset === $_SESSION['usuario_id']) {
    http_response_code(400); $response['message'] = 'No puede resetear su propia contraseña desde aquí.';
    echo json_encode($response); exit();
}

// 3. Obtener datos del usuario (Necesitamos el email)
try {
    
    // --- INICIO DE LA MODIFICACIÓN DE GEMINI ---
    // Se quitó "AND rol = 'empleado'" para encontrar CUALQUIER rol
    $sql_user = "SELECT usuario, email, nombre_completo FROM usuarios WHERE id_usuario = :id";
    // --- FIN DE LA MODIFICACIÓN DE GEMINI ---

    $stmt_user = $pdo->prepare($sql_user);
    $stmt_user->execute([':id' => $id_usuario_reset]);
    $user_data = $stmt_user->fetch(PDO::FETCH_ASSOC);

    if (!$user_data || empty($user_data['email'])) {
        http_response_code(404);
        $response['message'] = 'Usuario no encontrado o no tiene un email registrado para enviar la contraseña.';
        echo json_encode($response); exit();
    }
    $user_email = $user_data['email'];
    $user_login = $user_data['usuario'];
    $user_name = $user_data['nombre_completo'];

} catch (PDOException $e) {
    http_response_code(500); error_log("Error DB obteniendo email para reset: " . $e->getMessage());
    $response['message'] = 'Error de base de datos al buscar al usuario.';
    echo json_encode($response); exit();
}


// 4. Generar contraseña temporal segura
$caracteres_permitidos = 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789';
$longitud_password = 10;
$password_temporal = '';
$max_index = strlen($caracteres_permitidos) - 1;
for ($i = 0; $i < $longitud_password; $i++) {
    $password_temporal .= $caracteres_permitidos[random_int(0, $max_index)];
}

// 5. Hashear y Actualizar en BD (Incluyendo el flag reset_pendiente)
$password_hashed = password_hash($password_temporal, PASSWORD_DEFAULT);
try {
    $pdo->beginTransaction(); // Usar transacción

    // --- INICIO DE LA MODIFICACIÓN DE GEMINI ---
    // Se quitó "AND rol = 'empleado'" para actualizar CUALQUIER rol
    $sql_update = "UPDATE usuarios SET password = :password, reset_pendiente = 1 WHERE id_usuario = :id";
    // --- FIN DE LA MODIFICACIÓN DE GEMINI ---

    $stmt_update = $pdo->prepare($sql_update);
    $stmt_update->bindParam(':password', $password_hashed);
    $stmt_update->bindParam(':id', $id_usuario_reset, PDO::PARAM_INT);
    $stmt_update->execute();

    if ($stmt_update->rowCount() <= 0) {
        throw new Exception('No se encontró un usuario con ese ID para actualizar.');
    }

    // 6. Enviar Correo Electrónico
    $login_url = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? "https" : "http") . "://$_SERVER[HTTP_HOST]" . rtrim(dirname($_SERVER['PHP_SELF']), '/\\') . "/login.php";
    $subject = "Reseteo de Contraseña - Sistema Logística";
    $body = "Hola " . htmlspecialchars($user_name) . ",\n\n" .
            "Se ha reseteado tu contraseña para el sistema de Logística.\n\n" .
            "Tu usuario es: " . htmlspecialchars($user_login) . "\n" .
            "Tu contraseña temporal es: " . $password_temporal . "\n\n" .
            "Por favor, inicia sesión usando esta contraseña temporal en:\n" .
            $login_url . "\n\n" .
            "Se te pedirá que establezcas una nueva contraseña inmediatamente después de iniciar sesión.\n\n" .
            "Si no solicitaste este reseteo, contacta al administrador.\n";
    $headers = "From: Sistema Logistica <no-reply@tu-dominio.com>\r\n"; // Cambia tu-dominio.com
    $headers .= "Reply-To: no-reply@tu-dominio.com\r\n";
    $headers .= "Content-Type: text/plain; charset=UTF-8\r\n";

    // **Intento con mail() - Necesita configuración en localhost**
    // @ suprime los warnings si mail() falla, pero capturamos el resultado
    if (@mail($user_email, $subject, $body, $headers)) {
        $pdo->commit(); // Confirmar transacción SOLO si el email se envió
        $response['success'] = true;
        $response['message'] = "Contraseña reseteada. Se envió un correo a " . htmlspecialchars($user_email) . " con la contraseña temporal.";
        echo json_encode($response); exit();
    } else {
         // Si mail() falla, revertir la transacción y mostrar error
         $pdo->rollBack();
         http_response_code(500);
         // ESTE ES EL MENSAJE QUE PROBABLEMENTE VERÁS EN LOCALHOST HASTA QUE CONFIGURES mail()
         $response['message'] = "Error: La contraseña se generó, PERO falló el envío del correo. Verifica la configuración del servidor de correo (PHP mail()). La contraseña NO fue reseteada en la BD.";
         error_log("Fallo mail() en reset password para user ID: " . $id_usuario_reset . " Email: " . $user_email);
         echo json_encode($response); exit();
    }

} catch (Exception $e) {
    if ($pdo->inTransaction()) { $pdo->rollBack(); }
    http_response_code(500); error_log("Error general al resetear password: " . $e->getMessage());
    $response['message'] = 'Error interno del servidor: ' . $e->getMessage();
    echo json_encode($response); exit();
}
?>