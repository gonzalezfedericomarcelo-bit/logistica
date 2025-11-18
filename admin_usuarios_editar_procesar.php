<?php
// Archivo: admin_usuarios_editar_procesar.php
session_start();
include 'conexion.php';
include 'funciones_permisos.php'; 

// 1. Proteger
if (!isset($_SESSION['usuario_id']) || !tiene_permiso('admin_usuarios', $pdo) || $_SERVER['REQUEST_METHOD'] !== 'POST') {
    header("Location: dashboard.php");
    exit();
}
$mensaje = '';
$alerta_tipo = '';

// 2. Obtener datos del formulario
$id_usuario_edit = (int)($_POST['id_usuario_edit'] ?? 0);
$nombre_completo = trim($_POST['nombre_completo_edit']);
$usuario = trim($_POST['usuario_edit']);
$email = trim($_POST['email_edit'] ?? '');
$telefono = trim($_POST['telefono_edit'] ?? '');
$genero = strtolower(trim($_POST['genero_edit'] ?? 'otro'));
$grado = trim($_POST['grado_edit'] ?? ''); // <-- NUEVO CAMPO
$password_nueva = $_POST['password_edit'] ?? '';

// 3. Validar datos básicos
if ($id_usuario_edit <= 0 || empty($nombre_completo) || empty($usuario)) {
    $mensaje = "Error: Faltan datos obligatorios.";
    $alerta_tipo = 'danger';
} else {
    // Seguridad: No editar admin propio aquí
    if ($id_usuario_edit === $_SESSION['usuario_id']) {
        $mensaje = "Error: No puede editar su propio perfil desde aquí. Use 'Perfil'.";
        $alerta_tipo = 'danger';
    } else {
        try {
            $update_fields = [];
            $bind_params = [':id' => $id_usuario_edit];

            // 4. Obtener datos actuales
            $sql_current = "SELECT nombre_completo, usuario, email, telefono, genero, grado, password FROM usuarios WHERE id_usuario = :id";
            $stmt_current = $pdo->prepare($sql_current);
            $stmt_current->execute($bind_params);
            $current_data = $stmt_current->fetch();
            
            if (!$current_data) throw new Exception("Usuario no encontrado.");

            // 5. Comparar campos
            if ($current_data['nombre_completo'] !== $nombre_completo) { $update_fields[] = "nombre_completo = :nombre_completo"; $bind_params[':nombre_completo'] = $nombre_completo; }
            if ($current_data['usuario'] !== $usuario) { $update_fields[] = "usuario = :usuario"; $bind_params[':usuario'] = $usuario; }
            if ($current_data['email'] !== $email) { $update_fields[] = "email = :email"; $bind_params[':email'] = $email; }
            if ($current_data['telefono'] !== $telefono) { $update_fields[] = "telefono = :telefono"; $bind_params[':telefono'] = $telefono; }
            if ($current_data['genero'] !== $genero) { $update_fields[] = "genero = :genero"; $bind_params[':genero'] = $genero; }
            
            // Actualizar GRADO
            if (($current_data['grado'] ?? '') !== $grado) { 
                $update_fields[] = "grado = :grado"; 
                $bind_params[':grado'] = $grado; 
            }

            // Contraseña
            if (!empty($password_nueva)) {
                if (strlen($password_nueva) < 6) throw new Exception("Contraseña muy corta.");
                $update_fields[] = "password = :password";
                $bind_params[':password'] = password_hash($password_nueva, PASSWORD_DEFAULT);
            }

            // 7. Ejecutar
            if (!empty($update_fields)) {
                $sql_update = "UPDATE usuarios SET " . implode(", ", $update_fields) . " WHERE id_usuario = :id";
                $stmt_update = $pdo->prepare($sql_update);
                $stmt_update->execute($bind_params); 

                if ($stmt_update->rowCount() > 0) {
                     $mensaje = "Usuario actualizado correctamente.";
                     $alerta_tipo = 'success';
                } else {
                     $mensaje = "No hubo cambios.";
                     $alerta_tipo = 'info';
                }
            } else {
                 $mensaje = "No hubo cambios.";
                 $alerta_tipo = 'info';
            }

        } catch (PDOException $e) {
            if ($e->getCode() == '23000') $mensaje = "Error: Usuario/Email duplicado.";
            else $mensaje = "Error DB: " . $e->getMessage();
            $alerta_tipo = 'danger';
        } catch (Exception $e) {
            $mensaje = "Error: " . $e->getMessage();
            $alerta_tipo = 'danger';
        }
    }
}

$_SESSION['admin_usuarios_mensaje'] = $mensaje;
$_SESSION['admin_usuarios_alerta'] = $alerta_tipo; 
header("Location: admin_usuarios.php"); 
exit();
?>