<?php
session_start();
include 'conexion.php';

// Si el usuario ya está logueado, redirigir al dashboard
if (isset($_SESSION['usuario_id'])) {
    header("Location: dashboard.php");
    exit();
}

$error = '';

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $usuario = trim($_POST['usuario']);
    $password = $_POST['password'];

    if (empty($usuario) || empty($password)) {
        $error = "Por favor, ingrese usuario y contraseña.";
    } else {
        try {
            $sql = "SELECT * FROM usuarios WHERE usuario = :usuario";
            $stmt = $pdo->prepare($sql);
            $stmt->bindParam(':usuario', $usuario);
            $stmt->execute();
            $usuario_db = $stmt->fetch();

            // **MODIFICADO**: Añadido chequeo de 'activo' y 'reset_pendiente'
            if ($usuario_db && password_verify($password, $usuario_db['password'])) {
                // Contraseña correcta, ahora verificar estado
                if ($usuario_db['activo'] != 1) { 
                    $error = "Su cuenta está inactiva. Contacte al administrador.";
                } elseif ($usuario_db['reset_pendiente'] == 1) {
                    // Contraseña correcta y reset pendiente -> Forzar cambio
                    $_SESSION['usuario_id_reset'] = $usuario_db['id_usuario']; // Guardar solo ID temporalmente
                    $_SESSION['force_password_change'] = true;
                    header("Location: forzar_cambio_pass.php"); // Redirigir a la nueva página
                    exit();
                } else {
                    // Contraseña correcta, activo y sin reset pendiente -> Login normal
                    $_SESSION['usuario_id'] = $usuario_db['id_usuario'];
                    $_SESSION['usuario_nombre'] = $usuario_db['nombre_completo'];
                    $_SESSION['usuario_rol'] = $usuario_db['rol'];
                    $_SESSION['usuario_perfil'] = $usuario_db['foto_perfil'];
                    // $_SESSION['usuario_genero'] = $usuario_db['genero']; // Podrías añadir genero si lo usas

                    // Redirigir al dashboard
                    header("Location: dashboard.php");
                    exit();
                }
            } else {
                 // Usuario/Contraseña incorrectos (o inactivo si no entramos al if de 'activo')
                 if ($usuario_db && $usuario_db['activo'] != 1 && !$error) {
                      // Si el usuario existe pero está inactivo y no hay otro error
                      $error = "Su cuenta está inactiva. Contacte al administrador.";
                 } elseif (!$error) {
                      $error = "Usuario o contraseña incorrectos.";
                 }
            }
        } catch (PDOException $e) {
            $error = "Error al intentar iniciar sesión: " . $e->getMessage();
        }
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Iniciar Sesión - Gestión de Tareas</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        body {
            background-color: #f8f9fa;
        }
        .login-container {
            max-width: 400px;
            margin-top: 100px;
            padding: 30px;
            border-radius: 10px;
            box-shadow: 0 4px 8px rgba(0,0,0,0.1);
            background-color: white;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="row justify-content-center">
            <div class="col-md-6 col-lg-4">
                <div class="login-container">
                    <h2 class="text-center mb-4">Acceso al Sistema</h2>
                    
                    <?php if ($error): ?>
                        <div class="alert alert-danger" role="alert">
                            <?php echo $error; ?>
                        </div>
                    <?php endif; ?>

                    <form method="POST" action="login.php">
                        <div class="mb-3">
                            <label for="usuario" class="form-label">Usuario</label>
                            <input type="text" class="form-control" id="usuario" name="usuario" required>
                        </div>
                        <div class="mb-3">
                            <label for="password" class="form-label">Contraseña</label>
                            <input type="password" class="form-control" id="password" name="password" required>
                        </div>
                        <div class="d-grid">
                            <button type="submit" class="btn btn-primary">Ingresar</button>
                        </div>
                    </form>
                    <p class="mt-3 text-center text-muted">Acceso restringido para personal de logística.</p>
                </div>
            </div>
        </div>
    </div>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>