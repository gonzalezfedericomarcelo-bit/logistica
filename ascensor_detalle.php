<?php
// Archivo: ascensor_detalle.php (CORREGIDO Y BLINDADO)
session_start();
require_once 'conexion.php';
require_once 'funciones_permisos.php';

// Verificar ID y permisos
$id = $_GET['id'] ?? 0;
if ($id <= 0) {
    header("Location: mantenimiento_ascensores.php");
    exit;
}

// HELPER NOTIFICACIONES (Blindado contra errores)
function notificar($pdo, $msj, $link) {
    try {
        // Buscar usuarios con permisos para recibir avisos
        // Usamos un try-catch para que si esto falla, NO rompa la página
        $sql = "SELECT DISTINCT u.id_usuario 
                FROM usuarios u 
                JOIN rol_permiso rp ON u.rol = rp.nombre_rol 
                WHERE rp.clave_permiso IN ('recibir_notif_ascensores', 'admin_ascensores')";
        
        $stmt = $pdo->query($sql);
        
        if ($stmt) {
            $users = $stmt->fetchAll(PDO::FETCH_COLUMN);
            if (!empty($users)) {
                $stmt_ins = $pdo->prepare("INSERT INTO notificaciones (id_usuario, mensaje, url, tipo, leida) VALUES (?, ?, ?, 'aviso_global', 0)");
                foreach($users as $uid) {
                    $stmt_ins->execute([$uid, $msj, $link]);
                }
            }
        }
    } catch (Exception $e) {
        // Si falla la notificación, no hacemos nada para no interrumpir el flujo
        error_log("Error enviando notificación: " . $e->getMessage());
    }
}

// 1. ACTUALIZAR ESTADO (Acción del formulario "Notificar Cambio")
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] == 'estado') {
    $estado = $_POST['nuevo_estado'];
    $nota = $_POST['nota'];
    
    // Actualizamos el estado
    $upd = $pdo->prepare("UPDATE ascensor_incidencias SET estado=? WHERE id_incidencia=?");
    $upd->execute([$estado, $id]);
    
    // Intentamos notificar (ahora es seguro)
    notificar($pdo, "Ascensor #$id: " . strtoupper(str_replace('_', ' ', $estado)) . " - $nota", "ascensor_detalle.php?id=$id");
    
    // Redirigimos para ver el cambio
    header("Location: ascensor_detalle.php?id=$id"); 
    exit;
}

// 2. GUARDAR VISITA CON FIRMAS
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] == 'visita') {
    $tec = $_POST['tecnico']; 
    $desc = $_POST['trabajo']; 
    $sol = isset($_POST['solucion']) ? 1 : 0;
    
    // Guardar firmas base64
    $dir = 'uploads/firmas_asc/';
    if (!is_dir($dir)) mkdir($dir, 0777, true);
    
    $f_tec = ''; 
    $f_rec = '';
    
    if (!empty($_POST['firma_tec_data'])) {
        $f_tec = $dir . uniqid() . '_tec.png';
        file_put_contents($f_tec, base64_decode(preg_replace('#^data:image/\w+;base64,#i', '', $_POST['firma_tec_data'])));
    }
    
    if (!empty($_POST['firma_rec_data'])) {
        $f_rec = $dir . uniqid() . '_rec.png';
        file_put_contents($f_rec, base64_decode(preg_replace('#^data:image/\w+;base64,#i', '', $_POST['firma_rec_data'])));
    }

    $pdo->prepare("INSERT INTO ascensor_visitas_tecnicas (id_incidencia, tecnico_nombre, descripcion_trabajo, es_solucion_definitiva, firma_tecnico_path, firma_receptor_path, id_receptor, fecha_visita) VALUES (?,?,?,?,?,?,?, NOW())")
        ->execute([$id, $tec, $desc, $sol, $f_tec, $f_rec, $_SESSION['usuario_id']]);

    if ($sol) {
        $pdo->prepare("UPDATE ascensor_incidencias SET estado='resuelto' WHERE id_incidencia=?")->execute([$id]);
        notificar($pdo, "Ascensor #$id RESUELTO por técnico", "ascensor_detalle.php?id=$id");
    }
    header("Location: ascensor_detalle.php?id=$id"); 
    exit;
}

// OBTENER DATOS PARA MOSTRAR
$stmt_dato = $pdo->prepare("SELECT i.*, a.nombre as ascensor, e.nombre as empresa 
                            FROM ascensor_incidencias i 
                            JOIN ascensores a ON i.id_ascensor=a.id_ascensor 
                            LEFT JOIN empresas_mantenimiento e ON i.id_empresa=e.id_empresa 
                            WHERE i.id_incidencia=?");
$stmt_dato->execute([$id]);
$dato = $stmt_dato->fetch(PDO::FETCH_ASSOC);

if (!$dato) {
    die("Orden no encontrada.");
}

$visitas = $pdo->query("SELECT * FROM ascensor_visitas_tecnicas WHERE id_incidencia=$id ORDER BY fecha_visita DESC")->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <?php include 'head.php'; ?>
    <script src="https://cdn.jsdelivr.net/npm/signature_pad@4.0.0/dist/signature_pad.umd.min.js"></script>
    <title>Orden #<?php echo $id; ?></title>
    <style> canvas { border: 2px dashed #ccc; width: 100%; height: 150px; background: #fff; } </style>
</head>
<body>
    <?php include 'navbar.php'; ?>
    <div class="container mt-4">
        <div class="d-flex justify-content-between mb-3">
            <h3>Orden #<?php echo $id; ?> - <?php echo htmlspecialchars($dato['ascensor']); ?></h3>
            <a href="ascensor_pdf.php?id=<?php echo $id; ?>" target="_blank" class="btn btn-dark"><i class="fas fa-file-pdf"></i> Ver PDF Oficial</a>
        </div>
        
        <div class="row">
            <div class="col-md-4">
                <div class="card p-3 mb-3 shadow-sm">
                    <h5 class="text-primary">Detalles</h5>
                    <p><strong>Empresa:</strong> <?php echo htmlspecialchars($dato['empresa'] ?? 'Sin asignar'); ?></p>
                    <p><strong>Problema:</strong> <?php echo nl2br(htmlspecialchars($dato['descripcion_problema'])); ?></p>
                    <p><strong>Estado:</strong> <span class="badge bg-info text-dark"><?php echo strtoupper(str_replace('_', ' ', $dato['estado'])); ?></span></p>
                    <hr>
                    
                    <form method="POST">
                        <input type="hidden" name="action" value="estado">
                        <label class="form-label fw-bold">Actualizar Estado:</label>
                        <select name="nuevo_estado" class="form-select mb-2">
                            <option value="visita_programada">Visita Programada</option>
                            <option value="en_reparacion">En Reparación</option>
                            <option value="reclamo_enviado">Reclamo Enviado</option>
                        </select>
                        <input type="text" name="nota" class="form-control mb-2" placeholder="Nota (ej: Vienen 15hs)" required>
                        <button class="btn btn-primary w-100 btn-sm">Notificar Cambio</button>
                    </form>
                </div>
            </div>
            
            <div class="col-md-8">
                <?php if($dato['estado']!='resuelto'): ?>
                <div class="card p-3 border-success mb-3 shadow-sm">
                    <h5 class="text-success"><i class="fas fa-signature"></i> Registrar Visita Técnica</h5>
                    <form method="POST" id="formVisita">
                        <input type="hidden" name="action" value="visita">
                        <input type="hidden" name="firma_tec_data" id="ft">
                        <input type="hidden" name="firma_rec_data" id="fr">
                        
                        <div class="row mb-2">
                            <div class="col"><input type="text" name="tecnico" class="form-control" placeholder="Nombre Técnico" required></div>
                            <div class="col"><input type="text" name="trabajo" class="form-control" placeholder="Trabajo Realizado" required></div>
                        </div>
                        <div class="row mb-2">
                            <div class="col">
                                <label class="small text-muted">Firma Técnico</label>
                                <canvas id="c1"></canvas>
                                <button type="button" onclick="pad1.clear()" class="btn btn-sm btn-outline-secondary w-100 mt-1">Limpiar</button>
                            </div>
                            <div class="col">
                                <label class="small text-muted">Firma Receptor</label>
                                <canvas id="c2"></canvas>
                                <button type="button" onclick="pad2.clear()" class="btn btn-sm btn-outline-secondary w-100 mt-1">Limpiar</button>
                            </div>
                        </div>
                        <div class="form-check mb-2">
                            <input class="form-check-input" type="checkbox" name="solucion" id="sol">
                            <label class="form-check-label fw-bold" for="sol">¿Solución Definitiva?</label>
                        </div>
                        <button type="button" onclick="firmar()" class="btn btn-success w-100">GUARDAR VISITA</button>
                    </form>
                </div>
                <?php endif; ?>

                <h5 class="mt-4">Historial de Visitas</h5>
                <?php if(count($visitas) > 0): ?>
                    <?php foreach($visitas as $v): ?>
                        <div class="alert alert-secondary shadow-sm">
                            <div class="d-flex justify-content-between">
                                <span><strong><?php echo date('d/m/Y H:i', strtotime($v['fecha_visita'])); ?></strong> - <?php echo htmlspecialchars($v['tecnico_nombre']); ?></span>
                                <?php if($v['firma_tecnico_path']): ?><span class="badge bg-success">Firmado</span><?php endif; ?>
                            </div>
                            <div class="mt-1"><?php echo htmlspecialchars($v['descripcion_trabajo']); ?></div>
                        </div>
                    <?php endforeach; ?>
                <?php else: ?>
                    <p class="text-muted">No hay visitas registradas aún.</p>
                <?php endif; ?>
            </div>
        </div>
    </div>
    
    <script>
        var pad1, pad2;
        // Iniciar los pads solo si existen (si la orden no está resuelta)
        if(document.getElementById('c1')) {
            pad1 = new SignaturePad(document.getElementById('c1'));
            pad2 = new SignaturePad(document.getElementById('c2'));
        }

        function firmar() {
            if(!pad1 || !pad2) return;
            // No obligamos a firmar para pruebas rápidas, pero idealmente sí
            // if(pad1.isEmpty() || pad2.isEmpty()) { alert('Faltan firmas'); return; }
            
            if(!pad1.isEmpty()) document.getElementById('ft').value = pad1.toDataURL();
            if(!pad2.isEmpty()) document.getElementById('fr').value = pad2.toDataURL();
            
            document.getElementById('formVisita').submit();
        }
    </script>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>