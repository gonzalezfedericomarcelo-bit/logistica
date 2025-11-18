<?php
// Archivo: perfil.php (COMPLETO y REVISADO v3 - Con Firma Digital Dinámica)
// *** MODIFICADO (v4) PARA FONDO DE FIRMA TRANSPARENTE ***
session_start();
include 'conexion.php'; // Asegúrate que $pdo esté disponible

// 1. Verificar si el usuario está logueado
if (!isset($_SESSION['usuario_id'])) {
    header("Location: login.php");
    exit();
}

$id_usuario = $_SESSION['usuario_id'];
$mensaje = '';
$alerta_tipo = ''; // Se usará para mostrar mensajes (success, danger, warning, info)
$usuario_data = false; // Inicializar

// 2. Obtener datos ACTUALES del usuario para mostrar en el formulario
try {
    // Incluir 'genero' y 'firma_imagen_path' en la consulta
    $sql_load = "SELECT nombre_completo, usuario, email, telefono, foto_perfil, genero, firma_imagen_path FROM usuarios WHERE id_usuario = :id";
    $stmt_load = $pdo->prepare($sql_load);
    $stmt_load->bindParam(':id', $id_usuario, PDO::PARAM_INT);
    $stmt_load->execute();
    $usuario_data = $stmt_load->fetch(PDO::FETCH_ASSOC); // Usar FETCH_ASSOC

    if (!$usuario_data) {
        // Esto no debería pasar si la sesión es válida, pero por seguridad
        session_destroy(); // Limpiar sesión potencialmente inválida
        header("Location: login.php?error=userdata_fail");
        exit();
    }
} catch (PDOException $e) {
    $mensaje = "Error fatal al cargar los datos del usuario: " . $e->getMessage();
    $alerta_tipo = 'danger';
    error_log("Error DB en perfil.php (carga inicial): " . $e->getMessage());
}


// 3. Procesar envíos de formularios (POST)
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    
    // --- ACCIÓN: Actualizar Información General (y Firma) ---
    if (isset($_POST['action']) && $_POST['action'] == 'actualizar_info') {
        // Recoger datos del formulario
        $nombre_completo = trim($_POST['nombre_completo']);
        $email = trim($_POST['email']);
        $telefono = trim($_POST['telefono']);
        $genero = $_POST['genero'] ?? 'otro';
        
        // Recoger firma (si se dibujó una nueva)
        $firma_base64 = $_POST['firma_base64_hidden'] ?? '';

        $pdo->beginTransaction();
        try {
            $sql_update_info = "UPDATE usuarios SET nombre_completo = :nombre, email = :email, telefono = :telefono, genero = :genero WHERE id_usuario = :id";
            $stmt_update_info = $pdo->prepare($sql_update_info);
            $stmt_update_info->execute([
                ':nombre' => $nombre_completo,
                ':email' => $email,
                ':telefono' => $telefono,
                ':genero' => $genero,
                ':id' => $id_usuario
            ]);

            // Si se dibujó una nueva firma, procesarla
            if (!empty($firma_base64)) {
                $upload_dir_firmas = 'uploads/firmas/'; // Carpeta para firmas de usuario
                if (!is_dir($upload_dir_firmas)) {
                    if (!mkdir($upload_dir_firmas, 0777, true)) {
                        throw new Exception("Error crítico: No se pudo crear el directorio de firmas.");
                    }
                }

                $data = explode(',', $firma_base64);
                $encoded_image = (count($data) > 1) ? $data[1] : $data[0];
                $decoded_image = base64_decode($encoded_image);

                if ($decoded_image === false) {
                    throw new Exception("Error: El formato de la firma enviada es inválido.");
                }

                // Generar nombre de archivo único
                $filename = 'firma_' . $id_usuario . '_' . time() . '.png';
                $ruta_completa_firma = $upload_dir_firmas . $filename;

                if (file_put_contents($ruta_completa_firma, $decoded_image)) {
                    // Firma guardada, ahora actualizamos la BD
                    
                    // Opcional: Borrar la firma antigua si existe
                    $firma_antigua_relativa = $usuario_data['firma_imagen_path'] ?? null;
                    if ($firma_antigua_relativa) {
                         // (Cuidado: 'uploads/firmas_pedidos/' vs 'uploads/firmas/')
                         // Asumimos que las firmas de perfil están en 'uploads/firmas/'
                        $ruta_antigua_completa = 'uploads/firmas/' . $firma_antigua_relativa;
                        if (file_exists($ruta_antigua_completa)) {
                            @unlink($ruta_antigua_completa);
                        }
                    }

                    // Actualizar la BD con la ruta de la NUEVA firma (solo el nombre del archivo)
                    $sql_update_firma = "UPDATE usuarios SET firma_imagen_path = :firma_path WHERE id_usuario = :id";
                    $stmt_update_firma = $pdo->prepare($sql_update_firma);
                    $stmt_update_firma->execute([
                        ':firma_path' => $filename, // Guardar solo el nombre del archivo
                        ':id' => $id_usuario
                    ]);
                    
                    $usuario_data['firma_imagen_path'] = $filename; // Actualizar para mostrar
                } else {
                    throw new Exception("Error: No se pudo guardar el archivo de la firma en el servidor.");
                }
            }
            
            $pdo->commit();
            $mensaje = "Información general y/o firma actualizada correctamente.";
            $alerta_tipo = 'success';
            
            // Actualizar el nombre en la sesión
            $_SESSION['usuario_nombre'] = $nombre_completo; 
            
        } catch (PDOException $e) {
            $pdo->rollBack();
            $mensaje = "Error de BD al actualizar la información: " . $e->getMessage();
            $alerta_tipo = 'danger';
        } catch (Exception $e) {
            $pdo->rollBack();
            $mensaje = $e->getMessage(); // Mostrar error (ej. "No se pudo crear directorio")
            $alerta_tipo = 'danger';
        }
    }

    // --- ACCIÓN: Actualizar Foto de Perfil ---
    if (isset($_POST['action']) && $_POST['action'] == 'actualizar_foto') {
        if (isset($_FILES['foto_perfil']) && $_FILES['foto_perfil']['error'] === UPLOAD_ERR_OK) {
            
            $file = $_FILES['foto_perfil'];
            $upload_dir = 'uploads/perfiles/';
            if (!is_dir($upload_dir)) { mkdir($upload_dir, 0777, true); }

            $extension = pathinfo($file['name'], PATHINFO_EXTENSION);
            $allowed_types = ['jpg', 'jpeg', 'png', 'gif', 'webp'];
            
            if (in_array(strtolower($extension), $allowed_types) && $file['size'] < 5000000) { // 5MB Límite
                $new_filename = 'perfil_' . $id_usuario . '_' . time() . '.' . $extension;
                $ruta_completa = $upload_dir . $new_filename;

                if (move_uploaded_file($file['tmp_name'], $ruta_completa)) {
                    try {
                        // Borrar foto anterior (si no es 'default.png')
                        $foto_antigua = $usuario_data['foto_perfil'] ?? 'default.png';
                        if ($foto_antigua != 'default.png' && file_exists($upload_dir . $foto_antigua)) {
                            @unlink($upload_dir . $foto_antigua);
                        }
                        
                        // Actualizar BD
                        $sql_update_foto = "UPDATE usuarios SET foto_perfil = :foto WHERE id_usuario = :id";
                        $stmt_update_foto = $pdo->prepare($sql_update_foto);
                        $stmt_update_foto->execute([':foto' => $new_filename, ':id' => $id_usuario]);

                        $_SESSION['usuario_perfil'] = $new_filename;
                        $usuario_data['foto_perfil'] = $new_filename;
                        $mensaje = "Foto de perfil actualizada.";
                        $alerta_tipo = 'success';
                        
                    } catch (PDOException $e) {
                        $mensaje = "Error de BD al actualizar la foto: " . $e->getMessage();
                        $alerta_tipo = 'danger';
                        @unlink($ruta_completa); // Borrar si falló la BD
                    }
                } else {
                    $mensaje = "Error al mover el archivo subido.";
                    $alerta_tipo = 'danger';
                }
            } else {
                $mensaje = "Archivo no permitido. Debe ser JPG, PNG, GIF o WEBP y menor a 5MB.";
                $alerta_tipo = 'warning';
            }
        } else {
            $mensaje = "Error al subir el archivo (Código: " . ($_FILES['foto_perfil']['error'] ?? 'N/A') . ")";
            $alerta_tipo = 'danger';
        }
    }

    // --- ACCIÓN: Actualizar Contraseña ---
    if (isset($_POST['action']) && $_POST['action'] == 'actualizar_pass') {
        $pass_actual = $_POST['password_actual'];
        $pass_nueva = $_POST['password_nueva'];
        $pass_confirmar = $_POST['password_confirmar'];

        // Cargar hash actual
        $sql_hash = "SELECT password FROM usuarios WHERE id_usuario = :id";
        $stmt_hash = $pdo->prepare($sql_hash);
        $stmt_hash->execute([':id' => $id_usuario]);
        $hash_actual_db = $stmt_hash->fetchColumn();

        if (password_verify($pass_actual, $hash_actual_db)) {
            if (empty($pass_nueva) || strlen($pass_nueva) < 6) {
                $mensaje = "La nueva contraseña debe tener al menos 6 caracteres.";
                $alerta_tipo = 'warning';
            } elseif ($pass_nueva === $pass_actual) {
                $mensaje = "La nueva contraseña no puede ser igual a la actual.";
                $alerta_tipo = 'warning';
            } elseif ($pass_nueva !== $pass_confirmar) {
                $mensaje = "La nueva contraseña y su confirmación no coinciden.";
                $alerta_tipo = 'warning';
            } else {
                // Todo OK: Actualizar contraseña
                try {
                    $nuevo_hash = password_hash($pass_nueva, PASSWORD_DEFAULT);
                    $sql_update_pass = "UPDATE usuarios SET password = :hash, reset_pendiente = 0 WHERE id_usuario = :id";
                    $stmt_update_pass = $pdo->prepare($sql_update_pass);
                    $stmt_update_pass->execute([':hash' => $nuevo_hash, ':id' => $id_usuario]);
                    
                    $mensaje = "Contraseña actualizada exitosamente.";
                    $alerta_tipo = 'success';
                } catch (PDOException $e) {
                    $mensaje = "Error de BD al actualizar la contraseña: " . $e->getMessage();
                    $alerta_tipo = 'danger';
                }
            }
        } else {
            $mensaje = "La contraseña actual ingresada es incorrecta.";
            $alerta_tipo = 'danger';
        }
    }
    
    // Si la página se recarga por un POST, volvemos a cargar los datos (especialmente si falló algo)
    if ($alerta_tipo === 'danger' || $alerta_tipo === 'warning' || $alerta_tipo === 'success') {
         $sql_load = "SELECT nombre_completo, usuario, email, telefono, foto_perfil, genero, firma_imagen_path FROM usuarios WHERE id_usuario = :id";
         $stmt_load = $pdo->prepare($sql_load);
         $stmt_load->execute([':id' => $id_usuario]);
         $usuario_data = $stmt_load->fetch(PDO::FETCH_ASSOC);
    }
}

// 4. Incluir Navbar (después de la lógica POST para que el nombre se actualice)
include 'navbar.php'; 
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Mi Perfil</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    
    <style>
        .signature-pad-container {
            border: 2px dashed #ccc;
            border-radius: 0.375rem;
            position: relative;
            height: 200px;
            overflow: hidden; 
            background-color: #fff;
        }
        #signature-canvas {
            width: 100%;
            height: 100%;
            cursor: crosshair;
        }
        .signature-pad-actions {
           position: absolute;
           top: 5px;
           right: 5px;
        }
    </style>
    
</head>
<body>

<div class="container mt-4 mb-5">
    
    <?php if ($mensaje): ?>
        <div class="alert alert-<?php echo $alerta_tipo; ?> alert-dismissible fade show" role="alert">
            <?php echo htmlspecialchars($mensaje); ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    <?php endif; ?>
    
    <?php if (!$usuario_data): ?>
        <div class="alert alert-danger" role="alert">
            Error fatal: No se pudieron cargar los datos del usuario.
        </div>
        <?php exit(); ?>
    <?php endif; ?>

    <div class="row">
        <div class="col-lg-4 mb-4">
            <div class="card shadow-sm">
                <div class="card-header"><i class="fas fa-camera me-2"></i> Foto de Perfil</div>
                <div class="card-body text-center">
                    <img src="uploads/perfiles/<?php echo htmlspecialchars($usuario_data['foto_perfil'] ?? 'default.png'); ?>" 
                         alt="Foto de Perfil" 
                         class="img-thumbnail rounded-circle mb-3" 
                         style="width: 200px; height: 200px; object-fit: cover;">
                    
                    <form action="perfil.php" method="POST" enctype="multipart/form-data">
                        <input type="hidden" name="action" value="actualizar_foto">
                        <div class="mb-3">
                            <label for="foto_perfil" class="form-label">Cambiar Foto (JPG, PNG, GIF, WEBP)</label>
                            <input class="form-control" type="file" id="foto_perfil" name="foto_perfil" accept="image/*" required>
                        </div>
                        <button type="submit" class="btn btn-info text-white"><i class="fas fa-upload"></i> Subir Nueva Foto</button>
                    </form>
                </div>
            </div>
            
        </div>

        <div class="col-lg-8">
            <div class="card shadow-sm mb-4">
                <div class="card-header"><i class="fas fa-user-edit me-2"></i> Información General y Firma</div>
                <div class="card-body">
                    <form action="perfil.php" method="POST" id="main-profile-form">
                        <input type="hidden" name="action" value="actualizar_info">
                        
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label for="nombre_completo" class="form-label">Nombre Completo (*)</label>
                                <input type="text" class="form-control" id="nombre_completo" name="nombre_completo" 
                                       value="<?php echo htmlspecialchars($usuario_data['nombre_completo']); ?>" required>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label for="usuario" class="form-label">Usuario (Login)</label>
                                <input type="text" class="form-control" id="usuario" name="usuario" 
                                       value="<?php echo htmlspecialchars($usuario_data['usuario']); ?>" readonly disabled>
                                <small class="text-muted">El nombre de usuario no se puede cambiar.</small>
                            </div>
                        </div>
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label for="email" class="form-label">Email</label>
                                <input type="email" class="form-control" id="email" name="email" 
                                       value="<?php echo htmlspecialchars($usuario_data['email'] ?? ''); ?>">
                            </div>
                            <div class="col-md-6 mb-3">
                                <label for="telefono" class="form-label">Teléfono</label>
                                <input type="text" class="form-control" id="telefono" name="telefono" 
                                       value="<?php echo htmlspecialchars($usuario_data['telefono'] ?? ''); ?>">
                            </div>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Género</label>
                            <select class="form-select" name="genero">
                                <option value="masculino" <?php echo ($usuario_data['genero'] == 'masculino') ? 'selected' : ''; ?>>Masculino</option>
                                <option value="femenino" <?php echo ($usuario_data['genero'] == 'femenino') ? 'selected' : ''; ?>>Femenino</option>
                                <option value="otro" <?php echo ($usuario_data['genero'] == 'otro') ? 'selected' : ''; ?>>Otro / No especificar</option>
                            </select>
                        </div>
                        
                        <hr class="my-4">
                        
                        <h5 class="mb-3"><i class="fas fa-signature me-2"></i> Firma Digital</h5>
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Firma Actual Guardada</label>
                                <div class="p-2 border rounded" style="background-color: #f8f9fa; height: 200px; display: flex; align-items: center; justify-content: center;">
                                    <?php if (!empty($usuario_data['firma_imagen_path'])): ?>
                                        <img src="uploads/firmas/<?php echo htmlspecialchars($usuario_data['firma_imagen_path']); ?>" 
                                             alt="Firma Actual" 
                                             style="max-width: 100%; max-height: 180px; background-color: #fff; padding: 5px; border: 1px solid #ddd;">
                                    <?php else: ?>
                                        <span class="text-muted fst-italic">(Sin firma guardada)</span>
                                    <?php endif; ?>
                                </div>
                            </div>
                            
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Dibujar Nueva Firma (Opcional)</label>
                                <div class="signature-pad-container">
                                    <canvas id="signature-canvas"></canvas>
                                    <div class="signature-pad-actions">
                                        <button type="button" class="btn btn-sm btn-outline-danger" id="clear-signature">
                                            <i class="fas fa-times"></i> Limpiar
                                        </button>
                                    </div>
                                </div>
                                <small class="text-muted">Si dibuja aquí, reemplazará su firma actual al guardar.</small>
                            </div>
                        </div>
                        
                        <input type="hidden" name="firma_base64_hidden" id="firma_base64_hidden">

                        <div class="text-end mt-3">
                            <button type="submit" class="btn btn-primary"><i class="fas fa-save"></i> Guardar Información y Firma</button>
                        </div>
                    </form>
                </div>
            </div>

            <div class="card shadow-sm mb-4">
                <div class="card-header"><i class="fas fa-key me-2"></i> Cambiar Contraseña</div>
                <div class="card-body">
                    <form action="perfil.php" method="POST">
                        <input type="hidden" name="action" value="actualizar_pass">
                        <div class="row">
                            <div class="col-md-4 mb-3">
                                <label for="password_actual" class="form-label">Contraseña Actual (*)</label>
                                <input type="password" class="form-control" id="password_actual" name="password_actual" autocomplete="current-password" required>
                                <small class="text-muted">Dejar en blanco si no cambia la contraseña.</small>
                            </div>
                            <div class="col-md-4 mb-3">
                                <label for="password_nueva" class="form-label">Nueva Contraseña</label>
                                <input type="password" class="form-control" id="password_nueva" name="password_nueva" autocomplete="new-password" minlength="6">
                                <small class="text-muted">Mínimo 6 caracteres.</small>
                            </div>
                            <div class="col-md-4 mb-3">
                                <label for="password_confirmar" class="form-label">Confirmar Nueva Contraseña</label>
                                <input type="password" class="form-control" id="password_confirmar" name="password_confirmar" autocomplete="new-password" minlength="6">
                            </div>
                        </div>

                        <div class="text-end mt-3">
                            <button type="submit" class="btn btn-warning"><i class="fas fa-save"></i> Guardar Contraseña</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<div class="toast-container position-fixed bottom-0 end-0 p-3" id="notificationToastContainer" style="z-index: 1080;"></div>

<script src="https://cdnjs.cloudflare.com/ajax/libs/signature_pad/1.5.3/signature_pad.min.js"></script>

<script>
    document.addEventListener('DOMContentLoaded', function() {
        var canvas = document.getElementById('signature-canvas');
        if (canvas) {
            var signaturePad = new SignaturePad(canvas, {
                // backgroundColor: 'rgb(255, 255, 255)' // <-- ESTA LÍNEA CAUSA EL FONDO BLANCO
                // Al omitirla, el fondo es transparente por defecto.
            });

            // Ajustar tamaño del canvas
            function resizeCanvas() {
                var data = null;
                if (!signaturePad.isEmpty()) {
                    data = signaturePad.toDataURL(); // Guardar firma
                }
                var ratio =  Math.max(window.devicePixelRatio || 1, 1);
                canvas.width = canvas.offsetWidth * ratio;
                canvas.height = canvas.offsetHeight * ratio;
                canvas.getContext("2d").scale(ratio, ratio);
                signaturePad.clear();
                if (data) {
                    signaturePad.fromDataURL(data); // Restaurar firma
                }
            }
            window.addEventListener("resize", resizeCanvas);
            resizeCanvas();

            // Limpiar firma
            document.getElementById('clear-signature').addEventListener('click', function (e) {
                e.preventDefault();
                signaturePad.clear();
            });

            // Lógica de envío del formulario principal
            var form = document.getElementById('main-profile-form');
            form.addEventListener('submit', function (e) {
                if (!signaturePad.isEmpty()) {
                    // Importante: toDataURL() sin argumentos guarda un PNG transparente
                    var dataURL = signaturePad.toDataURL(); 
                    document.getElementById('firma_base64_hidden').value = dataURL;
                }
            });
        }
    });
</script>
</body>
</html>