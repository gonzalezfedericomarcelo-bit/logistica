<?php
// Archivo: ascensor_detalle.php (CORREGIDO: SCRIPT LOCAL)
session_start();
require_once 'conexion.php';
require_once 'funciones_permisos.php';

$id = $_GET['id'] ?? 0;
if ($id <= 0) { header("Location: mantenimiento_ascensores.php"); exit; }

// --- PARCHES DB ---
try {
    $col_check = $pdo->query("SHOW COLUMNS FROM ascensor_visitas_tecnicas LIKE 'adjunto_tecnico'");
    if ($col_check->rowCount() == 0) {
        $pdo->exec("ALTER TABLE ascensor_visitas_tecnicas ADD COLUMN adjunto_tecnico VARCHAR(255) DEFAULT NULL");
    }
    $pdo->exec("CREATE TABLE IF NOT EXISTS ascensor_historial (
        id_historial INT AUTO_INCREMENT PRIMARY KEY,
        id_incidencia INT NOT NULL,
        id_usuario INT NOT NULL,
        accion VARCHAR(50),
        detalle TEXT,
        fecha DATETIME DEFAULT CURRENT_TIMESTAMP
    )");
} catch (Exception $e) { }

// --- PROCESAR POST ---
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    
    // 1. Actualizar Estado
    if (isset($_POST['action']) && $_POST['action'] === 'actualizar_estado') {
        $nuevo_estado = $_POST['nuevo_estado'];
        $nota = $_POST['nota'];
        $fecha_programada = $_POST['fecha_programada'] ?? null;
        
        $pdo->prepare("UPDATE ascensor_incidencias SET estado = ? WHERE id_incidencia = ?")->execute([$nuevo_estado, $id]);

        $detalle_hist = "Estado: " . strtoupper(str_replace('_', ' ', $nuevo_estado)) . ". Nota: " . $nota;
        if ($fecha_programada) $detalle_hist .= " | Fecha: " . date('d/m/Y H:i', strtotime($fecha_programada));
        
        $pdo->prepare("INSERT INTO ascensor_historial (id_incidencia, id_usuario, accion, detalle) VALUES (?, ?, 'cambio_estado', ?)")->execute([$id, $_SESSION['usuario_id'], $detalle_hist]);
        
        header("Location: ascensor_detalle.php?id=$id&msg=updated");
        exit;
    }

    // 2. Registrar Visita
    if (isset($_POST['action']) && $_POST['action'] === 'registrar_visita') {
        $tecnico = $_POST['tecnico'];
        $descripcion_trabajo = $_POST['descripcion_trabajo'];
        $solucionado = isset($_POST['solucionado']) ? 1 : 0;
        
        $ruta_adjunto = null;
        if (isset($_FILES['adjunto_tecnico']) && $_FILES['adjunto_tecnico']['error'] === UPLOAD_ERR_OK) {
            $ext = pathinfo($_FILES['adjunto_tecnico']['name'], PATHINFO_EXTENSION);
            $nombre_file = 'parte_' . $id . '_' . time() . '.' . $ext;
            if (!is_dir('uploads/ascensores_partes/')) mkdir('uploads/ascensores_partes/', 0777, true);
            if (move_uploaded_file($_FILES['adjunto_tecnico']['tmp_name'], 'uploads/ascensores_partes/' . $nombre_file)) {
                $ruta_adjunto = 'uploads/ascensores_partes/' . $nombre_file;
            }
        }
        
        $firma_path = null;
        if (!empty($_POST['firma_tecnico_data'])) {
            $data = str_replace('data:image/png;base64,', '', $_POST['firma_tecnico_data']);
            $data = str_replace(' ', '+', $data);
            $firma_bin = base64_decode($data);
            if (!is_dir('uploads/firmas_asc/')) mkdir('uploads/firmas_asc/', 0777, true);
            $firma_name = 'tec_' . $id . '_' . time() . '.png';
            file_put_contents('uploads/firmas_asc/' . $firma_name, $firma_bin);
            $firma_path = 'uploads/firmas_asc/' . $firma_name;
        }

        $sql_visita = "INSERT INTO ascensor_visitas_tecnicas (id_incidencia, fecha_visita, tecnico_nombre, descripcion_trabajo, es_solucion_definitiva, firma_tecnico_path, adjunto_tecnico, id_receptor) VALUES (?, NOW(), ?, ?, ?, ?, ?, ?)";
        $pdo->prepare($sql_visita)->execute([$id, $tecnico, $descripcion_trabajo, $solucionado, $firma_path, $ruta_adjunto, $_SESSION['usuario_id']]);

        $nuevo_est = $solucionado ? 'resuelto' : 'en_reparacion';
        $pdo->prepare("UPDATE ascensor_incidencias SET estado = ? WHERE id_incidencia = ?")->execute([$nuevo_est, $id]);
        
        $pdo->prepare("INSERT INTO ascensor_historial (id_incidencia, id_usuario, accion, detalle) VALUES (?, ?, 'visita', ?)")->execute([$id, $_SESSION['usuario_id'], "Visita Técnica. Solucionado: " . ($solucionado ? 'SI' : 'NO')]);

        header("Location: ascensor_detalle.php?id=$id&msg=visita_ok");
        exit;
    }
}

// --- DATOS ---
$stmt = $pdo->prepare("SELECT i.*, a.nombre as ascensor, a.ubicacion, e.nombre as empresa, u.nombre_completo as solicitante FROM ascensor_incidencias i JOIN ascensores a ON i.id_ascensor = a.id_ascensor LEFT JOIN empresas_mantenimiento e ON i.id_empresa = e.id_empresa LEFT JOIN usuarios u ON i.id_usuario_reporta = u.id_usuario WHERE i.id_incidencia = ?");
$stmt->execute([$id]);
$incidencia = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$incidencia) { header("Location: mantenimiento_ascensores.php"); exit; }

$historial = $pdo->query("SELECT h.*, u.nombre_completo FROM ascensor_historial h JOIN usuarios u ON h.id_usuario = u.id_usuario WHERE id_incidencia = $id ORDER BY fecha DESC")->fetchAll(PDO::FETCH_ASSOC);
$visitas = $pdo->query("SELECT * FROM ascensor_visitas_tecnicas WHERE id_incidencia = $id ORDER BY fecha_visita DESC")->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <?php include 'head.php'; ?>
    <title>Detalle #<?php echo $id; ?></title>
    <script src="https://cdn.jsdelivr.net/npm/signature_pad@4.0.0/dist/signature_pad.umd.min.js"></script>
    <style>
        /* CSS ESPECÍFICO LOCAL: Asegura que el menú flote sobre el canvas */
        .dropdown-menu { z-index: 9999 !important; }
        canvas { touch-action: none; display: block; }
    </style>
</head>
<body class="bg-light">

    <?php include 'navbar.php'; ?>

    <div class="container mt-4 pb-5">
        <div class="d-flex justify-content-between align-items-center mb-3 flex-wrap">
            <h3><i class="fas fa-tools"></i> Orden #<?php echo $id; ?>: <?php echo htmlspecialchars($incidencia['ascensor']); ?></h3>
            <div>
                <a href="ascensor_pdf.php?id=<?php echo $id; ?>" target="_blank" class="btn btn-outline-danger mb-2 mb-md-0">
                    <i class="fas fa-file-pdf"></i> PDF
                </a>
                <a href="mantenimiento_ascensores.php" class="btn btn-secondary mb-2 mb-md-0">Volver</a>
            </div>
        </div>

        <?php if (isset($_GET['msg'])): ?>
            <div class="alert alert-success alert-dismissible fade show">
                <?php 
                if ($_GET['msg'] == 'updated') echo "Novedad registrada.";
                if ($_GET['msg'] == 'visita_ok') echo "Visita guardada.";
                ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>

        <div class="row">
            <div class="col-lg-8 col-12">
                <div class="card shadow-sm mb-4">
                    <div class="card-header bg-primary text-white d-flex justify-content-between">
                        <span>Detalle de Falla</span>
                        <span class="badge bg-light text-dark"><?php echo strtoupper(str_replace('_',' ',$incidencia['estado'])); ?></span>
                    </div>
                    <div class="card-body">
                        <div class="row">
                            <div class="col-md-6">
                                <p><strong>Ubicación:</strong> <?php echo htmlspecialchars($incidencia['ubicacion']); ?></p>
                                <p><strong>Empresa:</strong> <?php echo htmlspecialchars($incidencia['empresa']); ?></p>
                            </div>
                            <div class="col-md-6">
                                <p><strong>Fecha:</strong> <?php echo date('d/m/Y H:i', strtotime($incidencia['fecha_reporte'])); ?></p>
                                <p><strong>Solicitante:</strong> <?php echo htmlspecialchars($incidencia['solicitante']); ?></p>
                            </div>
                        </div>
                        <hr>
                        <p class="alert alert-secondary mb-0"><?php echo nl2br(htmlspecialchars($incidencia['descripcion_problema'])); ?></p>
                        <?php if ($incidencia['archivo_reclamo']): ?>
                            <a href="<?php echo $incidencia['archivo_reclamo']; ?>" target="_blank" class="btn btn-sm btn-info text-white mt-2">Ver Adjunto</a>
                        <?php endif; ?>
                    </div>
                </div>

                <div class="card shadow-sm mb-4">
                    <div class="card-header bg-secondary text-white"><i class="fas fa-history"></i> Historial</div>
                    <ul class="list-group list-group-flush">
                        <?php if (empty($historial)): ?>
                            <li class="list-group-item text-muted">Sin movimientos.</li>
                        <?php else: ?>
                            <?php foreach ($historial as $h): ?>
                                <li class="list-group-item">
                                    <div class="d-flex w-100 justify-content-between">
                                        <strong class="text-primary"><?php echo htmlspecialchars($h['nombre_completo']); ?></strong>
                                        <small class="text-muted"><?php echo date('d/m/Y H:i', strtotime($h['fecha'])); ?></small>
                                    </div>
                                    <p class="mb-0 small"><?php echo nl2br(htmlspecialchars($h['detalle'])); ?></p>
                                </li>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </ul>
                </div>

                <?php if (!empty($visitas)): ?>
                    <div class="card shadow-sm mb-4 border-success">
                        <div class="card-header bg-success text-white"><i class="fas fa-check-circle"></i> Intervenciones</div>
                        <div class="card-body">
                            <?php foreach ($visitas as $v): ?>
                                <div class="border-bottom mb-3 pb-3">
                                    <h5><?php echo date('d/m/Y', strtotime($v['fecha_visita'])); ?> - <?php echo htmlspecialchars($v['tecnico_nombre']); ?></h5>
                                    <p class="mb-1"><?php echo nl2br(htmlspecialchars($v['descripcion_trabajo'])); ?></p>
                                    <div class="mt-2">
                                        <?php if ($v['adjunto_tecnico']): ?>
                                            <a href="<?php echo $v['adjunto_tecnico']; ?>" target="_blank" class="btn btn-sm btn-outline-dark me-1">Ver Parte</a>
                                        <?php endif; ?>
                                        <?php if ($v['firma_tecnico_path']): ?>
                                            <span class="badge bg-light text-dark border">Firma OK</span>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                <?php endif; ?>
            </div>

            <div class="col-lg-4 col-12">
                <?php if ($incidencia['estado'] != 'resuelto'): ?>
                <div class="card shadow-sm mb-4">
                    <div class="card-header bg-info text-white">Novedades</div>
                    <div class="card-body">
                        <form method="POST">
                            <input type="hidden" name="action" value="actualizar_estado">
                            <div class="mb-3">
                                <label class="form-label">Estado</label>
                                <select name="nuevo_estado" class="form-select">
                                    <option value="reclamo_enviado" <?php if($incidencia['estado']=='reclamo_enviado') echo 'selected'; ?>>Reclamo Enviado</option>
                                    <option value="visita_programada" <?php if($incidencia['estado']=='visita_programada') echo 'selected'; ?>>Visita Programada</option>
                                    <option value="en_reparacion" <?php if($incidencia['estado']=='en_reparacion') echo 'selected'; ?>>En Espera / Reparación</option>
                                </select>
                            </div>
                            <div class="mb-3">
                                <label class="form-label">Fecha Programada (Opcional)</label>
                                <input type="datetime-local" name="fecha_programada" class="form-control">
                            </div>
                            <div class="mb-3">
                                <textarea name="nota" class="form-control" rows="2" required placeholder="Nota o comentario..."></textarea>
                            </div>
                            <button type="submit" class="btn btn-info w-100 text-white">Guardar</button>
                        </form>
                    </div>
                </div>

                <div class="card shadow-sm border-warning">
                    <div class="card-header bg-warning text-dark">Llegada Técnico</div>
                    <div class="card-body">
                        <form method="POST" enctype="multipart/form-data">
                            <input type="hidden" name="action" value="registrar_visita">
                            <input type="hidden" name="firma_tecnico_data" id="firma_tecnico_data">
                            
                            <div class="mb-2">
                                <input type="text" name="tecnico" class="form-control" placeholder="Nombre Técnico" required>
                            </div>
                            <div class="mb-2">
                                <textarea name="descripcion_trabajo" class="form-control" rows="3" placeholder="Trabajo realizado..." required></textarea>
                            </div>
                            <div class="mb-2">
                                <label class="form-label small text-primary"><i class="fas fa-camera"></i> Foto del Parte</label>
                                <input type="file" name="adjunto_tecnico" class="form-control form-control-sm" accept="image/*,application/pdf">
                            </div>
                            <div class="mb-3">
                                <label class="form-label small">Firma Técnico</label>
                                <div class="border bg-white"><canvas id="pad_tecnico" style="width: 100%; height: 150px;"></canvas></div>
                                <button type="button" class="btn btn-sm btn-light w-100 border mt-1" onclick="limpiarPad()">Limpiar</button>
                            </div>
                            <div class="form-check mb-3 p-2 bg-light border rounded">
                                <input class="form-check-input" type="checkbox" name="solucionado" id="sol">
                                <label class="form-check-label fw-bold" for="sol">¿Solucionado Definitivo?</label>
                            </div>
                            <button type="submit" class="btn btn-success w-100" onclick="guardarFirma()">Registrar Visita</button>
                        </form>
                    </div>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <?php include 'footer.php'; ?>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>

    <script>
        var canvas = document.getElementById('pad_tecnico');
        var signaturePad;
        if (canvas) {
            function resizeCanvas() {
                var ratio = Math.max(window.devicePixelRatio || 1, 1);
                canvas.width = canvas.offsetWidth * ratio;
                canvas.height = canvas.offsetHeight * ratio;
                canvas.getContext("2d").scale(ratio, ratio);
            }
            window.addEventListener("resize", resizeCanvas);
            resizeCanvas();
            signaturePad = new SignaturePad(canvas, { backgroundColor: 'rgb(255, 255, 255)' });
        }
        function limpiarPad() { if(signaturePad) signaturePad.clear(); }
        function guardarFirma() { if(signaturePad && !signaturePad.isEmpty()) document.getElementById('firma_tecnico_data').value = signaturePad.toDataURL(); }
    </script>
</body>
</html>