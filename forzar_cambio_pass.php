<?php
// Archivo: forzar_cambio_pass.php (NUEVO)
session_start();
include 'conexion.php'; // Necesitamos $pdo

// Verificar si el usuario está en el estado correcto (forzar cambio)
if (!isset($_SESSION['usuario_id_reset']) || !isset($_SESSION['force_password_change']) || $_SESSION['force_password_change'] !== true) {
    // Si no está forzado a cambiar, lo mandamos al login (o dashboard si ya tiene sesión normal)
    if (isset($_SESSION['usuario_id'])) {
         header("Location: dashboard.php");
    } else {
         header("Location: login.php");
    }
    exit();
}

$id_usuario_forzado = $_SESSION['usuario_id_reset'];
$mensaje = '';
$alerta_tipo = '';

// Procesar el cambio de contraseña
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $password_nueva = $_POST['password_nueva'] ?? '';
    $password_confirmar = $_POST['password_confirmar'] ?? '';

    if (empty($password_nueva) || empty($password_confirmar)) {
        $mensaje = "Debe ingresar la nueva contraseña y confirmarla.";
        $alerta_tipo = 'danger';
    } elseif ($password_nueva !== $password_confirmar) {
        $mensaje = "Las contraseñas no coinciden.";
        $alerta_tipo = 'danger';
    } elseif (strlen($password_nueva) < 6) { // Validación simple de longitud
        $mensaje = "La nueva contraseña debe tener al menos 6 caracteres.";
        $alerta_tipo = 'danger';
    } else {
        // Todo OK, actualizar contraseña y quitar flag
        try {
            $pdo->beginTransaction();

            $password_hashed = password_hash($password_nueva, PASSWORD_DEFAULT);

            // Actualizar password y poner reset_pendiente a 0
            $sql_update = "UPDATE usuarios SET password = :password, reset_pendiente = 0 WHERE id_usuario = :id";
            $stmt_update = $pdo->prepare($sql_update);
            $stmt_update->execute([
                ':password' => $password_hashed,
                ':id' => $id_usuario_forzado
            ]);

            if ($stmt_update->rowCount() > 0) {
                // Obtener datos completos del usuario para iniciar sesión normal
                $sql_user = "SELECT id_usuario, nombre_completo, rol, foto_perfil FROM usuarios WHERE id_usuario = :id";
                $stmt_user = $pdo->prepare($sql_user);
                $stmt_user->execute([':id' => $id_usuario_forzado]);
                $usuario_db = $stmt_user->fetch(PDO::FETCH_ASSOC);

                if ($usuario_db) {
                    // Limpiar sesión temporal y establecer sesión normal
                    unset($_SESSION['usuario_id_reset']);
                    unset($_SESSION['force_password_change']);

                    $_SESSION['usuario_id'] = $usuario_db['id_usuario'];
                    $_SESSION['usuario_nombre'] = $usuario_db['nombre_completo'];
                    $_SESSION['usuario_rol'] = $usuario_db['rol'];
                    $_SESSION['usuario_perfil'] = $usuario_db['foto_perfil'];
                    // $_SESSION['usuario_genero'] = $usuario_db['genero']; // Opcional

                    $pdo->commit();

                    // Redirigir al dashboard con mensaje de éxito (opcional)
                    $_SESSION['action_success_message'] = "¡Contraseña actualizada! Ya puedes continuar."; // Usamos la misma variable que otros modales
                    header("Location: dashboard.php");
                    exit();
                } else {
                     throw new Exception("No se encontraron los datos del usuario después de actualizar.");
                }
            } else {
                 throw new Exception("No se pudo actualizar la contraseña en la base de datos.");
            }

        } catch (Exception $e) {
            $pdo->rollBack();
            $mensaje = "Error al actualizar la contraseña: " . $e->getMessage();
            $alerta_tipo = 'danger';
            error_log("Error en forzar_cambio_pass.php: " . $e->getMessage());
        }
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Cambiar Contraseña - Sistema Logística</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        body { background-color: #f8f9fa; }
        .change-password-container { max-width: 500px; margin-top: 100px; padding: 30px; border-radius: 10px; box-shadow: 0 4px 8px rgba(0,0,0,0.1); background-color: white; }
    </style>
</head>
<body>
    <div class="container">
        <div class="row justify-content-center">
            <div class="col-md-8 col-lg-6">
                <div class="change-password-container">
                    <h2 class="text-center mb-4 text-primary"><i class="fas fa-key"></i> Establecer Nueva Contraseña</h2>
                    <p class="text-center text-muted">Por seguridad, debes establecer una nueva contraseña para continuar.</p>

                    <?php if ($mensaje): ?>
                        <div class="alert alert-<?php echo $alerta_tipo; ?>" role="alert">
                            <?php echo htmlspecialchars($mensaje); ?>
                        </div>
                    <?php endif; ?>

                    <form method="POST" action="forzar_cambio_pass.php">
                        <div class="mb-3">
                            <label for="password_nueva" class="form-label">Nueva Contraseña</label>
                            <input type="password" class="form-control" id="password_nueva" name="password_nueva" required minlength="6">
                        </div>
                        <div class="mb-3">
                            <label for="password_confirmar" class="form-label">Confirmar Nueva Contraseña</label>
                            <input type="password" class="form-control" id="password_confirmar" name="password_confirmar" required minlength="6">
                        </div>
                        <div class="d-grid mt-4">
                            <button type="submit" class="btn btn-primary btn-lg">Actualizar Contraseña y Continuar</button>
                        </div>
                    </form>
                    <p class="mt-3 text-center small text-muted">
                        <a href="logout.php">Cancelar y Salir</a>
                    </p>
                </div>
            </div>
        </div>
    </div>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>