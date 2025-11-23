<?php
// Archivo: mantenimiento_ascensores.php (FINAL: Asociación automática y Envío de Mail)
session_start();
require_once 'conexion.php';
require_once 'funciones_permisos.php';

if (!isset($_SESSION['usuario_id']) || !tiene_permiso('acceso_ascensores', $pdo)) {
    header("Location: dashboard.php"); exit;
}
$id_usuario = $_SESSION['usuario_id'];

// --- PROCESAR RECLAMO ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'crear_incidencia') {
    
    $id_ascensor = $_POST['id_ascensor'];
    $prioridad = $_POST['prioridad'];
    $descripcion = $_POST['descripcion'];
    
    // 1. AVERIGUAR AUTOMÁTICAMENTE LA EMPRESA Y SU MAIL
    // Ya no le preguntamos al usuario, lo sacamos de la base de datos.
    $stmt_datos = $pdo->prepare("
        SELECT a.id_empresa, e.nombre as nombre_empresa, e.email_contacto, a.nombre as nombre_ascensor, a.ubicacion 
        FROM ascensores a 
        JOIN empresas_mantenimiento e ON a.id_empresa = e.id_empresa 
        WHERE a.id_ascensor = ?
    ");
    $stmt_datos->execute([$id_ascensor]);
    $info_equipo = $stmt_datos->fetch(PDO::FETCH_ASSOC);
    
    if ($info_equipo) {
        $id_empresa = $info_equipo['id_empresa'];
        $email_destino = $info_equipo['email_contacto'];
        
        // 2. SUBIR ADJUNTO (Si hay)
        $ruta_adjunto = null;
        if (isset($_FILES['adjunto']) && $_FILES['adjunto']['error'] === UPLOAD_ERR_OK) {
            $ext = pathinfo($_FILES['adjunto']['name'], PATHINFO_EXTENSION);
            $nombre_archivo = 'reclamo_' . time() . '.' . $ext;
            $ruta_destino = 'uploads/ascensores/' . $nombre_archivo;
            if (!is_dir('uploads/ascensores')) mkdir('uploads/ascensores', 0777, true);
            if (move_uploaded_file($_FILES['adjunto']['tmp_name'], $ruta_destino)) {
                $ruta_adjunto = $ruta_destino;
            }
        }

        // 3. GUARDAR EN BD
        $sql = "INSERT INTO ascensor_incidencias 
                (id_ascensor, id_empresa, id_usuario_reporta, descripcion_problema, prioridad, archivo_reclamo, estado, fecha_reporte) 
                VALUES (?, ?, ?, ?, ?, ?, 'reclamo_enviado', NOW())";
        $pdo->prepare($sql)->execute([$id_ascensor, $id_empresa, $id_usuario, $descripcion, $prioridad, $ruta_adjunto]);
        $id_reclamo = $pdo->lastInsertId();

        // 4. ENVIAR MAIL (Configuración Hostinger)
        if (!empty($email_destino)) {
            $asunto = "URGENTE: Reclamo Ascensor #" . $info_equipo['nombre_ascensor'] . " (Orden $id_reclamo)";
            
            $mensaje  = "Estimados " . $info_equipo['nombre_empresa'] . ",\n\n";
            $mensaje .= "Se ha reportado una falla en el siguiente equipo:\n";
            $mensaje .= "------------------------------------------------\n";
            $mensaje .= "Equipo: " . $info_equipo['nombre_ascensor'] . "\n";
            $mensaje .= "Ubicación: " . $info_equipo['ubicacion'] . "\n";
            $mensaje .= "Prioridad: " . strtoupper($prioridad) . "\n";
            $mensaje .= "------------------------------------------------\n";
            $mensaje .= "DESCRIPCIÓN DE FALLA:\n" . $descripcion . "\n\n";
            $mensaje .= "Por favor confirmar recepción y visita técnica.\n";
            $mensaje .= "Sistema de Gestión Logística.";

            // Cabeceras para evitar SPAM
            $headers = "From: no-reply@tudominio.com" . "\r\n" . // CAMBIA ESTO POR UN MAIL REAL DE TU DOMINIO HOSTINGER
                       "Reply-To: " . $_SESSION['usuario_email'] . "\r\n" .
                       "X-Mailer: PHP/" . phpversion();

            // Enviar
            @mail($email_destino, $asunto, $mensaje, $headers);
        }
        
        header("Location: mantenimiento_ascensores.php?msg=creado");
        exit;
    } else {
        $error = "Error: El ascensor seleccionado no tiene una empresa de mantenimiento asignada. Contacte al administrador.";
    }
}

// --- DATOS PARA VISTA ---
$lista_ascensores = $pdo->query("SELECT * FROM ascensores WHERE estado != 'inactivo'")->fetchAll(PDO::FETCH_ASSOC);

$sql_incidencias = "
    SELECT i.*, a.nombre as nombre_ascensor, e.nombre as nombre_empresa, u.nombre_completo as usuario_reporta
    FROM ascensor_incidencias i
    JOIN ascensores a ON i.id_ascensor = a.id_ascensor
    LEFT JOIN empresas_mantenimiento e ON i.id_empresa = e.id_empresa
    JOIN usuarios u ON i.id_usuario_reporta = u.id_usuario
    ORDER BY FIELD(i.estado, 'reportado', 'reclamo_enviado', 'visita_programada', 'en_reparacion', 'resuelto'), i.fecha_reporte DESC
";
$incidencias = $pdo->query($sql_incidencias)->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <?php include 'head.php'; ?>
    <title>Mantenimiento de Ascensores</title>
</head>
<body>
    <?php include 'navbar.php'; ?>
    
    <div class="container mt-4">
        <div class="d-flex justify-content-between align-items-center mb-4 p-3 bg-white rounded shadow-sm">
            <h2 class="mb-0 text-primary"><i class="fas fa-elevator"></i> Gestión de Ascensores</h2>
            <div>
                <?php if(tiene_permiso('admin_ascensores', $pdo)): ?>
                    <a href="admin_ascensores.php" class="btn btn-outline-secondary"><i class="fas fa-cogs"></i> Unidades</a>
                    <a href="admin_empresas.php" class="btn btn-outline-secondary"><i class="fas fa-building"></i> Empresas</a>
                <?php endif; ?>
                <button class="btn btn-danger" data-bs-toggle="modal" data-bs-target="#modalReportar">
                    <i class="fas fa-exclamation-circle"></i> Reportar Falla
                </button>
            </div>
        </div>

        <?php if(isset($_GET['msg'])): ?>
            <div class="alert alert-success alert-dismissible fade show">
                ¡Reclamo enviado y notificado a la empresa!
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>
        <?php if(isset($error)): ?>
            <div class="alert alert-danger"><?php echo $error; ?></div>
        <?php endif; ?>

        <div class="card shadow">
            <div class="card-header bg-dark text-white">
                <h5 class="mb-0">Tablero de Reclamos</h5>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover table-striped mb-0">
                        <thead class="table-light">
                            <tr>
                                <th>ID</th>
                                <th>Ascensor</th>
                                <th>Empresa Notificada</th>
                                <th>Fecha</th>
                                <th>Prioridad</th>
                                <th>Estado</th>
                                <th>Acción</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach($incidencias as $row): ?>
                            <tr>
                                <td>#<?php echo $row['id_incidencia']; ?></td>
                                <td class="fw-bold"><?php echo htmlspecialchars($row['nombre_ascensor']); ?></td>
                                <td><?php echo htmlspecialchars($row['nombre_empresa'] ?? 'N/A'); ?></td>
                                <td><?php echo date('d/m/Y H:i', strtotime($row['fecha_reporte'])); ?></td>
                                <td>
                                    <?php 
                                    $prio = $row['prioridad']; 
                                    $bg = ($prio=='emergencia')?'dark':(($prio=='alta')?'danger':'warning'); 
                                    ?>
                                    <span class="badge bg-<?php echo $bg; ?>"><?php echo strtoupper($prio); ?></span>
                                </td>
                                <td>
                                    <span class="badge bg-<?php echo ($row['estado']=='resuelto')?'success':'primary'; ?>">
                                        <?php echo strtoupper(str_replace('_', ' ', $row['estado'])); ?>
                                    </span>
                                </td>
                                <td>
                                    <a href="ascensor_detalle.php?id=<?php echo $row['id_incidencia']; ?>" class="btn btn-sm btn-primary">Ver</a>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <div class="modal fade" id="modalReportar" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <form method="POST" enctype="multipart/form-data">
                    <div class="modal-header bg-danger text-white">
                        <h5 class="modal-title">Reportar Falla</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">
                        <input type="hidden" name="action" value="crear_incidencia">
                        
                        <div class="alert alert-info small">
                            <i class="fas fa-info-circle"></i> Al seleccionar el equipo, se notificará automáticamente a la empresa asignada.
                        </div>

                        <div class="mb-3">
                            <label class="form-label fw-bold">Equipo con Falla</label>
                            <select name="id_ascensor" class="form-select" required>
                                <option value="">-- Seleccione Ascensor --</option>
                                <?php foreach($lista_ascensores as $a): ?>
                                <option value="<?php echo $a['id_ascensor']; ?>">
                                    <?php echo htmlspecialchars($a['nombre'] . " - " . $a['ubicacion']); ?>
                                </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="mb-3">
                            <label class="form-label fw-bold">Urgencia</label>
                            <select name="prioridad" class="form-select">
                                <option value="media">Media (Funcional con fallas)</option>
                                <option value="alta">Alta (Fuera de servicio)</option>
                                <option value="emergencia">🚨 Emergencia (Atrapamiento)</option>
                            </select>
                        </div>

                        <div class="mb-3">
                            <label class="form-label fw-bold">Descripción</label>
                            <textarea name="descripcion" class="form-control" rows="3" required placeholder="Detalle el problema..."></textarea>
                        </div>

                        <div class="mb-3">
                            <label class="form-label fw-bold">Adjunto (Opcional)</label>
                            <input type="file" name="adjunto" class="form-control">
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                        <button type="submit" class="btn btn-danger">Enviar y Notificar</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <?php include 'footer.php'; ?>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>