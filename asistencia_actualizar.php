<?php
// Archivo: asistencia_actualizar.php (CORREGIDO: ZONA HORARIA ARGENTINA)
session_start();
date_default_timezone_set('America/Argentina/Buenos_Aires'); // <--- CLAVE PARA LA HORA

include 'conexion.php';
include_once 'funciones_permisos.php';

if (!isset($_SESSION['usuario_id']) || !tiene_permiso('tomar_asistencia', $pdo)) {
    header("Location: dashboard.php"); exit();
}

$id_parte = (int)($_GET['id'] ?? 0);
if ($id_parte <= 0) { header("Location: asistencia_listado_general.php"); exit(); }

// Cargar Datos
$stmt = $pdo->prepare("SELECT * FROM asistencia_partes WHERE id_parte = :id");
$stmt->execute([':id' => $id_parte]);
$parte = $stmt->fetch(PDO::FETCH_ASSOC);

// Cargar Detalles
$stmt_det = $pdo->prepare("SELECT d.*, u.nombre_completo, u.grado FROM asistencia_detalles d JOIN usuarios u ON d.id_usuario = u.id_usuario WHERE d.id_parte = :id ORDER BY u.grado DESC, u.nombre_completo ASC");
$stmt_det->execute([':id' => $id_parte]);
$detalles = $stmt_det->fetchAll(PDO::FETCH_ASSOC);

// --- PROCESAR GUARDADO ---
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $bitacora_manual = trim($_POST['bitacora']);
    $datos = $_POST['datos'] ?? [];
    $usuario_editor = $_SESSION['usuario_nombre']; 
    
    $nuevos_eventos = "";
    $hora_actual = date("H:i"); // Ahora sí tomará la hora de Argentina

    try {
        $pdo->beginTransaction();
        
        $sql_det_upd = "UPDATE asistencia_detalles SET presente = :pres, tipo_asistencia = :tipo, observacion_individual = :obs WHERE id_detalle = :idd";
        $stmt_det_upd = $pdo->prepare($sql_det_upd);

        foreach ($detalles as $d) {
            $id = $d['id_detalle'];
            
            if (isset($datos[$id])) {
                $nuevo_tipo = $datos[$id]['tipo'];
                $nueva_obs = trim($datos[$id]['obs']);
                
                $tipo_anterior = $d['tipo_asistencia'];
                $obs_anterior = $d['observacion_individual'];
                
                if ($nuevo_tipo !== $tipo_anterior) {
                    $nombre_pers = $d['grado'] . " " . $d['nombre_completo'];
                    // NOTA: Usamos un marcador ">>" para identificar fácil en el PDF
                    $nuevos_eventos .= "\n>> [$hora_actual] CAMBIO DE ESTADO: $nombre_pers pasó de " . strtoupper($tipo_anterior) . " a " . strtoupper($nuevo_tipo) . ". (Editado por: $usuario_editor)";
                    if ($nueva_obs !== $obs_anterior && $nueva_obs != "") {
                        $nuevos_eventos .= " | Obs: $nueva_obs";
                    }
                }
                
                $es_presente = ($nuevo_tipo === 'ausente') ? 0 : 1;
                
                $stmt_det_upd->execute([
                    ':pres' => $es_presente,
                    ':tipo' => $nuevo_tipo,
                    ':obs'  => $nueva_obs,
                    ':idd'  => $id
                ]);
            }
        }
        
        $bitacora_final = $bitacora_manual . $nuevos_eventos;

        $stmt_upd = $pdo->prepare("UPDATE asistencia_partes SET bitacora = :bit WHERE id_parte = :id");
        $stmt_upd->execute([':bit' => $bitacora_final, ':id' => $id_parte]);

        $pdo->commit();
        header("Location: asistencia_listado_general.php?msg=actualizado");
        exit();

    } catch (Exception $e) {
        $pdo->rollBack();
        die("Error: " . $e->getMessage());
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Actualizar Parte</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">
    <style>
        .tipo-presente { color: #198754; font-weight: bold; }
        .tipo-ausente { color: #dc3545; font-weight: bold; }
        .tipo-tarde { color: #0d6efd; font-weight: bold; }
        .tipo-comision { color: #ffc107; font-weight: bold; }
    </style>
</head>
<body>
    <?php include 'navbar.php'; ?>
    <div class="container mt-4 mb-5">
        <div class="card shadow">
            <div class="card-header bg-warning text-dark d-flex justify-content-between align-items-center">
                <h5 class="mb-0 fw-bold"><i class="fas fa-pen-to-square me-2"></i>ACTUALIZAR PARTE Y NOVEDADES</h5>
                <span class="badge bg-light text-dark border"><?php echo date('d/m/Y', strtotime($parte['fecha'])); ?></span>
            </div>
            <div class="card-body">
                <form method="POST">
                    <div class="row">
                        <div class="col-lg-8">
                            <h6 class="fw-bold mb-3 border-bottom pb-2"><i class="fas fa-users me-2"></i>Estado del Personal</h6>
                            <div class="table-responsive" style="max-height: 500px; overflow-y: auto;">
                                <table class="table table-sm table-bordered align-middle">
                                    <thead class="table-light sticky-top">
                                        <tr>
                                            <th>Personal</th>
                                            <th style="width: 140px;">Estado Actual</th>
                                            <th>Observación / Destino</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($detalles as $d): ?>
                                        <tr>
                                            <td>
                                                <span class="badge bg-secondary me-1"><?php echo $d['grado']; ?></span>
                                                <?php echo $d['nombre_completo']; ?>
                                            </td>
                                            <td>
                                                <select name="datos[<?php echo $d['id_detalle']; ?>][tipo]" class="form-select form-select-sm <?php echo 'tipo-'.$d['tipo_asistencia']; ?>" onchange="this.className='form-select form-select-sm tipo-'+this.value">
                                                    <option value="presente" <?php echo $d['tipo_asistencia']=='presente'?'selected':''; ?>>Presente</option>
                                                    <option value="ausente" <?php echo $d['tipo_asistencia']=='ausente'?'selected':''; ?>>Ausente</option>
                                                    <option value="tarde" <?php echo $d['tipo_asistencia']=='tarde'?'selected':''; ?>>T. Tarde</option>
                                                    <option value="comision" <?php echo $d['tipo_asistencia']=='comision'?'selected':''; ?>>Comisión</option>
                                                </select>
                                            </td>
                                            <td>
                                                <input type="text" name="datos[<?php echo $d['id_detalle']; ?>][obs]" class="form-control form-control-sm" value="<?php echo htmlspecialchars($d['observacion_individual']); ?>">
                                            </td>
                                        </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                        <div class="col-lg-4">
                            <div class="bg-light p-3 rounded border h-100">
                                <label class="form-label fw-bold text-primary"><i class="fas fa-book-open me-2"></i>BITÁCORA / HISTORIAL</label>
                                <div class="form-text small mb-2">
                                    Escribí aquí los movimientos.<br>
                                    <em>El sistema agregará automáticamente los cambios de estado.</em>
                                </div>
                                <textarea name="bitacora" class="form-control font-monospace" rows="15"><?php echo htmlspecialchars($parte['bitacora']); ?></textarea>
                            </div>
                        </div>
                    </div>
                    <div class="d-grid gap-2 d-md-flex justify-content-md-end mt-4 border-top pt-3">
                        <a href="asistencia_listado_general.php" class="btn btn-secondary me-md-2">Cancelar</a>
                        <button type="submit" class="btn btn-success fw-bold px-5"><i class="fas fa-save me-2"></i>GUARDAR CAMBIOS</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    <?php include 'footer.php'; ?>
</body>
</html>