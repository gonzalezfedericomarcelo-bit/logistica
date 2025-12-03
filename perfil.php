<?php
// Archivo: perfil.php (vFinal Real - Galería Visualmente Correcta)
session_start();
include 'conexion.php';

// 1. Verificar Login
if (!isset($_SESSION['usuario_id'])) {
    header("Location: login.php");
    exit();
}

$id_usuario = $_SESSION['usuario_id'];
$mensaje = '';
$alerta_tipo = '';
$usuario_data = false;

// 2. Cargar Datos
try {
    $sql_load = "SELECT nombre_completo, usuario, email, telefono, foto_perfil, genero, firma_imagen_path FROM usuarios WHERE id_usuario = :id";
    $stmt_load = $pdo->prepare($sql_load);
    $stmt_load->execute([':id' => $id_usuario]);
    $usuario_data = $stmt_load->fetch(PDO::FETCH_ASSOC);

    if (!$usuario_data) {
        session_destroy();
        header("Location: login.php?error=userdata_fail");
        exit();
    }
} catch (PDOException $e) {
    $mensaje = "Error de carga: " . $e->getMessage(); $alerta_tipo = 'danger';
}

// 3. Procesar POST
if ($_SERVER["REQUEST_METHOD"] == "POST") {

    // --- ACCIÓN: Guardar Avatar (Base64) ---
    if (isset($_POST['action']) && $_POST['action'] == 'guardar_avatar_base64') {
        $avatar_b64 = $_POST['avatar_base64_data'] ?? '';

        if (!empty($avatar_b64)) {
            $upload_dir = 'uploads/perfiles/';
            if (!is_dir($upload_dir)) { mkdir($upload_dir, 0777, true); }

            // Limpiar Base64
            $data_parts = explode(',', $avatar_b64);
            $encoded_image = (count($data_parts) > 1) ? $data_parts[1] : $data_parts[0];
            $encoded_image = str_replace(' ', '+', $encoded_image);
            $decoded_image = base64_decode($encoded_image);

            if ($decoded_image !== false) {
                $new_filename = 'avatar_' . $id_usuario . '_' . time() . '.png';
                $ruta_completa = $upload_dir . $new_filename;

                if (file_put_contents($ruta_completa, $decoded_image)) {
                    try {
                        // Borrar anterior
                        $foto_antigua = $usuario_data['foto_perfil'] ?? 'default.png';
                        if ($foto_antigua != 'default.png' && file_exists($upload_dir . $foto_antigua)) {
                            @unlink($upload_dir . $foto_antigua);
                        }

                        // Guardar en BD
                        $sql_up = "UPDATE usuarios SET foto_perfil = :foto WHERE id_usuario = :id";
                        $stmt_up = $pdo->prepare($sql_up);
                        $stmt_up->execute([':foto' => $new_filename, ':id' => $id_usuario]);

                        $_SESSION['usuario_perfil'] = $new_filename;
                        $usuario_data['foto_perfil'] = $new_filename;
                        $mensaje = "¡Avatar seleccionado correctamente!";
                        $alerta_tipo = 'success';

                        if (isset($_GET['modo']) && $_GET['modo'] == 'bienvenida') {
                            header("Location: dashboard.php");
                            exit();
                        }
                    } catch (PDOException $e) {
                        $mensaje = "Error BD: " . $e->getMessage(); $alerta_tipo = 'danger';
                    }
                } else {
                    $mensaje = "Error al guardar archivo."; $alerta_tipo = 'danger';
                }
            } else {
                $mensaje = "Imagen corrupta."; $alerta_tipo = 'danger';
            }
        }
    }
    
    // --- ACCIÓN: Actualizar Info ---
    if (isset($_POST['action']) && $_POST['action'] == 'actualizar_info') {
        $nombre = trim($_POST['nombre_completo']);
        $email = trim($_POST['email']);
        $tel = trim($_POST['telefono']);
        $gen = $_POST['genero'] ?? 'otro';
        $firma_b64 = $_POST['firma_base64_hidden'] ?? '';

        $pdo->beginTransaction();
        try {
            $pdo->prepare("UPDATE usuarios SET nombre_completo=?, email=?, telefono=?, genero=? WHERE id_usuario=?")
                ->execute([$nombre, $email, $tel, $gen, $id_usuario]);

            if (!empty($firma_b64)) {
                $dir_f = 'uploads/firmas/';
                if (!is_dir($dir_f)) mkdir($dir_f, 0777, true);
                $parts = explode(',', $firma_b64);
                $decoded = base64_decode(count($parts)>1 ? $parts[1] : $parts[0]);
                $fname = 'firma_' . $id_usuario . '_' . time() . '.png';
                if (file_put_contents($dir_f . $fname, $decoded)) {
                    $old = $usuario_data['firma_imagen_path'] ?? null;
                    if ($old && file_exists($dir_f.$old)) @unlink($dir_f.$old);
                    $pdo->prepare("UPDATE usuarios SET firma_imagen_path=? WHERE id_usuario=?")->execute([$fname, $id_usuario]);
                    $usuario_data['firma_imagen_path'] = $fname;
                }
            }
            $pdo->commit();
            $_SESSION['usuario_nombre'] = $nombre; 
            $mensaje = "Datos guardados."; $alerta_tipo = 'success';
        } catch (Exception $e) {
            $pdo->rollBack();
            $mensaje = "Error: " . $e->getMessage(); $alerta_tipo = 'danger';
        }
    }

    // --- ACCIÓN: Subir Foto Manual ---
    if (isset($_POST['action']) && $_POST['action'] == 'actualizar_foto') {
        if (isset($_FILES['foto_perfil']) && $_FILES['foto_perfil']['error'] === UPLOAD_ERR_OK) {
            $file = $_FILES['foto_perfil'];
            $dir = 'uploads/perfiles/';
            if (!is_dir($dir)) mkdir($dir, 0777, true);
            $ext = pathinfo($file['name'], PATHINFO_EXTENSION);
            if (in_array(strtolower($ext), ['jpg','jpeg','png','gif','webp']) && $file['size'] < 5000000) {
                $new = 'perfil_' . $id_usuario . '_' . time() . '.' . $ext;
                if (move_uploaded_file($file['tmp_name'], $dir . $new)) {
                    $old = $usuario_data['foto_perfil'] ?? 'default.png';
                    if ($old != 'default.png' && file_exists($dir.$old)) @unlink($dir.$old);
                    $pdo->prepare("UPDATE usuarios SET foto_perfil=? WHERE id_usuario=?")->execute([$new, $id_usuario]);
                    $_SESSION['usuario_perfil'] = $new;
                    $usuario_data['foto_perfil'] = $new;
                    $mensaje = "Foto subida."; $alerta_tipo = 'success';
                    if (isset($_GET['modo']) && $_GET['modo'] == 'bienvenida') { header("Location: dashboard.php"); exit(); }
                }
            } else { $mensaje = "Archivo inválido."; $alerta_tipo = 'warning'; }
        }
    }
    
    // --- ACCIÓN: Password ---
    if (isset($_POST['action']) && $_POST['action'] == 'actualizar_pass') {
        $act = $_POST['password_actual']; $nue = $_POST['password_nueva']; $conf = $_POST['password_confirmar'];
        $stmt = $pdo->prepare("SELECT password FROM usuarios WHERE id_usuario = ?");
        $stmt->execute([$id_usuario]);
        if (password_verify($act, $stmt->fetchColumn())) {
            if (strlen($nue)>=6 && $nue===$conf) {
                $pdo->prepare("UPDATE usuarios SET password=? WHERE id_usuario=?")->execute([password_hash($nue, PASSWORD_DEFAULT), $id_usuario]);
                $mensaje = "Contraseña cambiada."; $alerta_tipo = 'success';
            } else { $mensaje = "Error en nueva contraseña."; $alerta_tipo = 'warning'; }
        } else { $mensaje = "Contraseña actual incorrecta."; $alerta_tipo = 'danger'; }
    }

    if ($alerta_tipo === 'success') {
         $stmt_load->execute([':id' => $id_usuario]);
         $usuario_data = $stmt_load->fetch(PDO::FETCH_ASSOC);
    }
}
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
        .signature-pad-container { border: 2px dashed #ccc; border-radius: 0.375rem; position: relative; height: 200px; background: #fff; }
        #signature-canvas { width: 100%; height: 100%; cursor: crosshair; }
        .signature-pad-actions { position: absolute; top: 5px; right: 5px; }
        
        .avatar-option { 
            transition: transform 0.2s; cursor: pointer; 
            background: #fff; border: 1px solid #eee;
        }
        .avatar-option:hover { 
            transform: scale(1.05); border-color: #0d6efd; box-shadow: 0 4px 12px rgba(0,0,0,0.1); z-index: 10;
        }
        /* Scroll para categorías */
        .nav-pills-scroll {
            display: flex;
            flex-wrap: nowrap;
            overflow-x: auto;
            padding-bottom: 5px;
        }
        .nav-pills-scroll::-webkit-scrollbar { height: 4px; }
        .nav-pills-scroll::-webkit-scrollbar-thumb { background: #ccc; border-radius: 4px; }
        
        .avatar-grid-container {
            height: 500px;
            overflow-y: auto;
            padding: 10px;
            background-color: #f8f9fa;
        }
    </style>
</head>
<body>

<div class="container mt-4 mb-5">
    
    <?php if (isset($_GET['modo']) && $_GET['modo'] == 'bienvenida'): ?>
        <div class="alert alert-success text-center shadow-sm border-0">
            <h4><i class="fas fa-user-check"></i> ¡Bienvenido!</h4>
            <p class="mb-0">Por favor, selecciona un avatar para identificarte.</p>
        </div>
    <?php endif; ?>

    <?php if ($mensaje): ?>
        <div class="alert alert-<?php echo $alerta_tipo; ?> alert-dismissible fade show"><?php echo htmlspecialchars($mensaje); ?> <button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>
    <?php endif; ?>
    
    <div class="row">
        <div class="col-lg-4 mb-4">
            <div class="card shadow-sm border-0">
                <div class="card-header bg-primary text-white fw-bold"><i class="fas fa-image me-2"></i> Tu Avatar</div>
                <div class="card-body text-center">
                    <img src="uploads/perfiles/<?php echo htmlspecialchars($usuario_data['foto_perfil'] ?? 'default.png'); ?>" 
                         class="img-thumbnail rounded-circle mb-3 shadow-sm" 
                         style="width: 180px; height: 180px; object-fit: cover; border: 4px solid #fff;">
                    
                    <div class="d-grid gap-2">
                        <button type="button" class="btn btn-primary fw-bold shadow-sm" data-bs-toggle="modal" data-bs-target="#avatarModal">
                            <i class="fas fa-th me-1"></i> Abrir Galería
                        </button>
                        
                        <div class="text-muted my-1 small">- O subir foto -</div>
                        
                        <form action="perfil.php<?php echo (isset($_GET['modo']) ? '?modo='.$_GET['modo'] : ''); ?>" method="POST" enctype="multipart/form-data">
                            <input type="hidden" name="action" value="actualizar_foto">
                            <div class="input-group">
                                <input class="form-control form-control-sm" type="file" name="foto_perfil" accept="image/*" required>
                                <button type="submit" class="btn btn-sm btn-secondary"><i class="fas fa-upload"></i></button>
                            </div>
                        </form>
                    </div>
                    <?php if (isset($_GET['modo']) && $_GET['modo'] == 'bienvenida'): ?>
                         <div class="mt-3"><a href="dashboard.php" class="text-decoration-none small text-muted">Omitir ></a></div>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <div class="col-lg-8">
            <div class="card shadow-sm mb-4 border-0">
                <div class="card-header bg-white fw-bold border-bottom"><i class="fas fa-user-cog me-2 text-primary"></i> Datos Personales</div>
                <div class="card-body">
                    <form action="perfil.php" method="POST" id="main-profile-form">
                        <input type="hidden" name="action" value="actualizar_info">
                        <div class="row">
                            <div class="col-md-6 mb-3"><label class="form-label fw-bold small">Nombre</label><input type="text" class="form-control" name="nombre_completo" value="<?php echo htmlspecialchars($usuario_data['nombre_completo']); ?>" required></div>
                            <div class="col-md-6 mb-3"><label class="form-label fw-bold small">Usuario</label><input type="text" class="form-control bg-light" value="<?php echo htmlspecialchars($usuario_data['usuario']); ?>" disabled readonly></div>
                        </div>
                        <div class="row">
                            <div class="col-md-6 mb-3"><label class="form-label fw-bold small">Email</label><input type="email" class="form-control" name="email" value="<?php echo htmlspecialchars($usuario_data['email'] ?? ''); ?>"></div>
                            <div class="col-md-6 mb-3"><label class="form-label fw-bold small">Teléfono</label><input type="text" class="form-control" name="telefono" value="<?php echo htmlspecialchars($usuario_data['telefono'] ?? ''); ?>"></div>
                        </div>
                        <div class="mb-3"><label class="form-label fw-bold small">Género</label>
                            <select class="form-select" name="genero">
                                <option value="masculino" <?php echo ($usuario_data['genero']=='masculino')?'selected':''; ?>>Masculino</option>
                                <option value="femenino" <?php echo ($usuario_data['genero']=='femenino')?'selected':''; ?>>Femenino</option>
                                <option value="otro" <?php echo ($usuario_data['genero']=='otro')?'selected':''; ?>>Otro</option>
                            </select>
                        </div>
                        
                        <h6 class="mt-4 mb-3 border-bottom pb-2 text-primary">Firma Digital</h6>
                        <div class="row">
                            <div class="col-md-6 mb-3"><div class="p-2 border rounded text-center bg-light d-flex align-items-center justify-content-center" style="height: 150px;">
                                <?php if (!empty($usuario_data['firma_imagen_path'])): ?>
                                    <img src="uploads/firmas/<?php echo htmlspecialchars($usuario_data['firma_imagen_path']); ?>" style="max-width:100%; max-height:130px;">
                                <?php else: ?> <span class="text-muted small fst-italic">Sin firma</span> <?php endif; ?>
                            </div></div>
                            <div class="col-md-6 mb-3">
                                <div class="signature-pad-container" style="height:150px;">
                                    <canvas id="signature-canvas"></canvas>
                                    <div class="signature-pad-actions"><button class="btn btn-sm btn-danger" id="clear-signature"><i class="fas fa-eraser"></i></button></div>
                                </div>
                            </div>
                        </div>
                        <input type="hidden" name="firma_base64_hidden" id="firma_base64_hidden">
                        <div class="text-end"><button type="submit" class="btn btn-success px-4"><i class="fas fa-save me-2"></i> Guardar</button></div>
                    </form>
                </div>
            </div>

            <div class="card shadow-sm mb-4 border-0">
                <div class="card-header bg-white fw-bold border-bottom"><i class="fas fa-key me-2 text-warning"></i> Seguridad</div>
                <div class="card-body">
                    <form action="perfil.php" method="POST">
                        <input type="hidden" name="action" value="actualizar_pass">
                        <div class="row">
                            <div class="col-md-4 mb-3"><label class="small fw-bold">Actual</label><input type="password" class="form-control" name="password_actual" required></div>
                            <div class="col-md-4 mb-3"><label class="small fw-bold">Nueva</label><input type="password" class="form-control" name="password_nueva" minlength="6"></div>
                            <div class="col-md-4 mb-3"><label class="small fw-bold">Repetir</label><input type="password" class="form-control" name="password_confirmar" minlength="6"></div>
                        </div>
                        <div class="text-end"><button type="submit" class="btn btn-warning px-4">Cambiar Pass</button></div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="modal fade" id="avatarModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-xl modal-dialog-scrollable">
        <div class="modal-content">
            <div class="modal-header bg-dark text-white">
                <h5 class="modal-title"><i class="fas fa-users me-2"></i>Galería de Avatares</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body bg-light p-0">
                
                <div class="bg-white p-2 border-bottom sticky-top" style="z-index: 100;">
                    <div class="nav nav-pills nav-pills-scroll" id="pills-tab" role="tablist">
                        <button class="nav-link active btn-sm me-1" onclick="renderizarAvatares('moderno')">👔 Moderno</button>
                        <button class="nav-link btn-sm me-1" onclick="renderizarAvatares('formal')">💼 Formal</button>
                        <button class="nav-link btn-sm me-1" onclick="renderizarAvatares('casual')">👕 Casual</button>
                        <button class="nav-link btn-sm me-1" onclick="renderizarAvatares('personas')">👥 Personas</button>
                        <button class="nav-link btn-sm me-1" onclick="renderizarAvatares('robots')">🤖 Robots</button>
                        <button class="nav-link btn-sm me-1" onclick="renderizarAvatares('anime')">⚔️ Anime</button>
                        <button class="nav-link btn-sm me-1" onclick="renderizarAvatares('pixel')">👾 Retro</button>
                        <button class="nav-link btn-sm me-1" onclick="renderizarAvatares('bocetos')">✏️ Bocetos</button>
                        <button class="nav-link btn-sm me-1" onclick="renderizarAvatares('dibujado')">🖍️ Dibujado</button>
                        <button class="nav-link btn-sm me-1" onclick="renderizarAvatares('arte')">🎨 Arte</button>
                    </div>
                </div>

                <div class="avatar-grid-container">
                    <div class="row g-2" id="avatarGrid">
                        <div class="col-12 text-center py-5">
                            <div class="spinner-border text-secondary"></div>
                            <p class="mt-2 text-muted">Cargando galería...</p>
                        </div>
                    </div>
                </div>

                <form id="formAvatarSelect" action="perfil.php<?php echo (isset($_GET['modo']) ? '?modo='.$_GET['modo'] : ''); ?>" method="POST">
                    <input type="hidden" name="action" value="guardar_avatar_base64">
                    <input type="hidden" name="avatar_base64_data" id="avatar_base64_data">
                </form>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/signature_pad/1.5.3/signature_pad.min.js"></script>

<script>
    // --- BASE DE DATOS DE AVATARES CURADOS ---
    // Nota: 'p' son parámetros extra para forzar ropa/estilo
    const avataresDB = [
        // 1. MODERNO (Micah - Estilo limpio)
        {t:'micah',s:'Alan',c:'moderno'}, {t:'micah',s:'Betty',c:'moderno'}, {t:'micah',s:'Charlie',c:'moderno'},
        {t:'micah',s:'Diana',c:'moderno'}, {t:'micah',s:'Evan',c:'moderno'}, {t:'micah',s:'Fiona',c:'moderno'},
        {t:'micah',s:'George',c:'moderno'}, {t:'micah',s:'Hannah',c:'moderno'}, {t:'micah',s:'Ian',c:'moderno'},
        {t:'micah',s:'Julia',c:'moderno'}, {t:'micah',s:'Kevin',c:'moderno'}, {t:'micah',s:'Laura',c:'moderno'},

        // 2. FORMAL (Avataaars FORZANDO TRAJE)
        // clothing=blazerAndShirt (Traje)
        {t:'avataaars',s:'Boss1',c:'formal',p:'&clothing=blazerAndShirt'}, 
        {t:'avataaars',s:'Boss2',c:'formal',p:'&clothing=blazerAndShirt'}, 
        {t:'avataaars',s:'Boss3',c:'formal',p:'&clothing=blazerAndShirt'},
        {t:'avataaars',s:'Boss4',c:'formal',p:'&clothing=blazerAndShirt'},
        {t:'avataaars',s:'Boss5',c:'formal',p:'&clothing=blazerAndShirt'},
        {t:'avataaars',s:'Boss6',c:'formal',p:'&clothing=blazerAndShirt'},
        {t:'avataaars',s:'Boss7',c:'formal',p:'&clothing=blazerAndShirt'},
        {t:'avataaars',s:'Boss8',c:'formal',p:'&clothing=blazerAndShirt'},
        {t:'avataaars',s:'Boss9',c:'formal',p:'&clothing=blazerAndShirt'},
        {t:'avataaars',s:'Boss10',c:'formal',p:'&clothing=blazerAndShirt'},

        // 3. CASUAL (Avataaars FORZANDO ROPA CASUAL)
        // clothing=hoodie, shirtCrewNeck
        {t:'avataaars',s:'Cas1',c:'casual',p:'&clothing=hoodie'}, 
        {t:'avataaars',s:'Cas2',c:'casual',p:'&clothing=shirtCrewNeck'}, 
        {t:'avataaars',s:'Cas3',c:'casual',p:'&clothing=collarAndSweater'},
        {t:'avataaars',s:'Cas4',c:'casual',p:'&clothing=shirtScoopNeck'},
        {t:'avataaars',s:'Cas5',c:'casual',p:'&clothing=hoodie'},
        {t:'avataaars',s:'Cas6',c:'casual',p:'&clothing=shirtCrewNeck'},
        {t:'avataaars',s:'Cas7',c:'casual',p:'&clothing=overall'},
        {t:'avataaars',s:'Cas8',c:'casual',p:'&clothing=shirtVNeck'},
        {t:'avataaars',s:'Cas9',c:'casual',p:'&clothing=hoodie'},
        {t:'avataaars',s:'Cas10',c:'casual',p:'&clothing=graphicShirt'},

        // 4. PERSONAS (Estilo Plano)
        {t:'personas',s:'Admin',c:'personas'}, {t:'personas',s:'User',c:'personas'}, {t:'personas',s:'Guest',c:'personas'},
        {t:'personas',s:'Dev',c:'personas'}, {t:'personas',s:'Manager',c:'personas'}, {t:'personas',s:'Sales',c:'personas'},
        {t:'personas',s:'Tech',c:'personas'}, {t:'personas',s:'Boss',c:'personas'}, {t:'personas',s:'Team',c:'personas'},
        {t:'personas',s:'Staff',c:'personas'}, {t:'personas',s:'Client',c:'personas'}, {t:'personas',s:'Help',c:'personas'},

        // 5. ROBOTS (Bottts)
        {t:'bottts',s:'A1',c:'robots'}, {t:'bottts',s:'B2',c:'robots'}, {t:'bottts',s:'C3',c:'robots'},
        {t:'bottts',s:'D4',c:'robots'}, {t:'bottts',s:'E5',c:'robots'}, {t:'bottts',s:'F6',c:'robots'},
        {t:'bottts',s:'G7',c:'robots'}, {t:'bottts',s:'H8',c:'robots'}, {t:'bottts',s:'I9',c:'robots'},
        {t:'bottts',s:'J10',c:'robots'}, {t:'bottts',s:'K11',c:'robots'}, {t:'bottts',s:'L12',c:'robots'},

        // 6. ANIME / RPG (Adventurer)
        {t:'adventurer',s:'Hero',c:'anime'}, {t:'adventurer',s:'Mage',c:'anime'}, {t:'adventurer',s:'Rogue',c:'anime'},
        {t:'adventurer',s:'Warrior',c:'anime'}, {t:'adventurer',s:'Elf',c:'anime'}, {t:'adventurer',s:'Orc',c:'anime'},
        {t:'adventurer',s:'Paladin',c:'anime'}, {t:'adventurer',s:'Archer',c:'anime'}, {t:'adventurer',s:'Cleric',c:'anime'},
        {t:'adventurer',s:'Ninja',c:'anime'}, {t:'adventurer',s:'King',c:'anime'}, {t:'adventurer',s:'Queen',c:'anime'},

        // 7. RETRO (Pixel Art)
        {t:'pixel-art',s:'Mario',c:'pixel'}, {t:'pixel-art',s:'Luigi',c:'pixel'}, {t:'pixel-art',s:'Peach',c:'pixel'},
        {t:'pixel-art',s:'Link',c:'pixel'}, {t:'pixel-art',s:'Zelda',c:'pixel'}, {t:'pixel-art',s:'Sonic',c:'pixel'},
        {t:'pixel-art',s:'Mega',c:'pixel'}, {t:'pixel-art',s:'Pac',c:'pixel'}, {t:'pixel-art',s:'Space',c:'pixel'},
        {t:'pixel-art',s:'Invader',c:'pixel'},

        // 8. BOCETOS (Notionists)
        {t:'notionists',s:'Idea',c:'bocetos'}, {t:'notionists',s:'Sketch',c:'bocetos'}, {t:'notionists',s:'Draw',c:'bocetos'},
        {t:'notionists',s:'Plan',c:'bocetos'}, {t:'notionists',s:'Work',c:'bocetos'}, {t:'notionists',s:'Team',c:'bocetos'},
        {t:'notionists',s:'Code',c:'bocetos'}, {t:'notionists',s:'Data',c:'bocetos'}, {t:'notionists',s:'Cloud',c:'bocetos'},
        {t:'notionists',s:'Coffee',c:'bocetos'},

        // 9. DIBUJADO (Open Peeps)
        {t:'open-peeps',s:'One',c:'dibujado'}, {t:'open-peeps',s:'Two',c:'dibujado'}, {t:'open-peeps',s:'Three',c:'dibujado'},
        {t:'open-peeps',s:'Four',c:'dibujado'}, {t:'open-peeps',s:'Five',c:'dibujado'}, {t:'open-peeps',s:'Six',c:'dibujado'},
        {t:'open-peeps',s:'Seven',c:'dibujado'}, {t:'open-peeps',s:'Eight',c:'dibujado'}, {t:'open-peeps',s:'Nine',c:'dibujado'},

        // 10. ARTÍSTICO (Lorelei)
        {t:'lorelei',s:'Art',c:'arte'}, {t:'lorelei',s:'Muse',c:'arte'}, {t:'lorelei',s:'Vinci',c:'arte'},
        {t:'lorelei',s:'Dali',c:'arte'}, {t:'lorelei',s:'Picasso',c:'arte'}, {t:'lorelei',s:'Frida',c:'arte'},
        {t:'lorelei',s:'Goya',c:'arte'}, {t:'lorelei',s:'Miro',c:'arte'}, {t:'lorelei',s:'Monet',c:'arte'}
    ];

    document.addEventListener('DOMContentLoaded', function() {
        // Inicializar Firma
        var canvas = document.getElementById('signature-canvas');
        if (canvas) {
            var signaturePad = new SignaturePad(canvas);
            function resizeCanvas() {
                var data = !signaturePad.isEmpty() ? signaturePad.toDataURL() : null;
                var ratio = Math.max(window.devicePixelRatio || 1, 1);
                canvas.width = canvas.offsetWidth * ratio;
                canvas.height = canvas.offsetHeight * ratio;
                canvas.getContext("2d").scale(ratio, ratio);
                signaturePad.clear();
                if (data) signaturePad.fromDataURL(data);
            }
            window.addEventListener("resize", resizeCanvas);
            resizeCanvas();
            document.getElementById('clear-signature').addEventListener('click', function (e) { e.preventDefault(); signaturePad.clear(); });
            document.getElementById('main-profile-form').addEventListener('submit', function () {
                if (!signaturePad.isEmpty()) document.getElementById('firma_base64_hidden').value = signaturePad.toDataURL();
            });
        }

        // Renderizar inicial
        var avatarModal = document.getElementById('avatarModal');
        if(avatarModal){
            avatarModal.addEventListener('show.bs.modal', function () { 
                renderizarAvatares('moderno'); // Default
            });
        }
    });

    function renderizarAvatares(filtro) {
        const grid = document.getElementById('avatarGrid');
        grid.innerHTML = '';
        
        // Actualizar botones
        document.querySelectorAll('.nav-pills-scroll .nav-link').forEach(btn => {
            btn.classList.remove('active', 'bg-primary', 'text-white');
            btn.classList.add('text-dark');
            if(btn.onclick.toString().includes(filtro)) {
                btn.classList.add('active', 'bg-primary', 'text-white');
                btn.classList.remove('text-dark');
            }
        });

        const filtrados = avataresDB.filter(a => a.c === filtro);

        filtrados.forEach((av, index) => {
            // Construcción de URL con parámetros extra si existen (para forzar ropa)
            let extraParams = av.p ? av.p : '';
            const url = `https://api.dicebear.com/7.x/${av.t}/png?seed=${av.s}&backgroundColor=b6e3f4,c0aede,d1d4f9,ffdfbf${extraParams}`;
            const imgId = `av-${filtro}-${index}`;
            
            const col = document.createElement('div');
            col.className = 'col-4 col-md-3 col-lg-2';
            col.innerHTML = `
                <div class="card h-100 avatar-option border-0 shadow-sm text-center" onclick="guardarAvatar('${imgId}')">
                    <img id="${imgId}" src="${url}" class="card-img-top bg-white rounded p-1" alt="Avatar" crossorigin="anonymous" loading="lazy" style="height:100px; object-fit:contain;">
                    <small class="d-block text-muted my-1" style="font-size:0.7rem">${av.s}</small>
                </div>
            `;
            grid.appendChild(col);
        });
    }

    function guardarAvatar(imgId) {
        const img = document.getElementById(imgId);
        if(confirm('¿Elegir este avatar?')) {
            try {
                const canvas = document.createElement('canvas');
                canvas.width = img.naturalWidth || 200;
                canvas.height = img.naturalHeight || 200;
                const ctx = canvas.getContext('2d');
                ctx.drawImage(img, 0, 0);
                const dataURL = canvas.toDataURL('image/png');
                
                document.getElementById('avatar_base64_data').value = dataURL;
                document.getElementById('formAvatarSelect').submit();
            } catch (e) {
                alert("Error: Navegador no compatible.");
            }
        }
    }
</script>
<?php include 'footer.php'; ?>
</body>
</html>