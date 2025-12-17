<?php
// Archivo: inventario_mantenimiento.php
session_start();
include 'conexion.php';
include 'funciones_permisos.php';

if (!isset($_SESSION['usuario_id']) || !tiene_permiso('acceso_inventario', $pdo)) {
    header("Location: dashboard.php"); exit();
}

$bien = null;
$mensaje = '';

// --- NUEVA LÓGICA DE BÚSQUEDA ---
if (isset($_GET['id'])) {
    // 1. Prioridad: Buscar por ID directo (desde el botón del listado)
    $stmt = $pdo->prepare("SELECT i.*, e.nombre as estado_actual 
                           FROM inventario_cargos i 
                           LEFT JOIN inventario_estados e ON i.id_estado_fk = e.id_estado
                           WHERE i.id_cargo = ? AND i.mat_tipo_carga_id IS NOT NULL LIMIT 1");
    $stmt->execute([$_GET['id']]);
    $bien = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$bien) $mensaje = "Bien no encontrado o no es un matafuego.";

} elseif (isset($_GET['buscar_codigo'])) {
    // 2. Búsqueda manual por texto
    $q = trim($_GET['buscar_codigo']);
    $stmt = $pdo->prepare("SELECT i.*, e.nombre as estado_actual 
                           FROM inventario_cargos i 
                           LEFT JOIN inventario_estados e ON i.id_estado_fk = e.id_estado
                           WHERE (i.codigo_inventario = ? OR i.mat_numero_grabado = ? OR i.elemento LIKE ?) 
                           AND i.mat_tipo_carga_id IS NOT NULL LIMIT 1");
    $stmt->execute([$q, $q, "%$q%"]);
    $bien = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$bien) $mensaje = "No se encontró ningún MATAFUEGO con ese código o numeración.";
}

// PROCESAR GUARDADO SERVICIO
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['id_cargo'])) {
    
    $id = $_POST['id_cargo'];
    
    function subirAdjunto($input) {
        if (isset($_FILES[$input]) && $_FILES[$input]['error'] === UPLOAD_ERR_OK) {
            $ext = pathinfo($_FILES[$input]['name'], PATHINFO_EXTENSION);
            $nombre = 'serv_' . time() . '_' . uniqid() . '.' . $ext;
            if (!file_exists('uploads/documentacion')) mkdir('uploads/documentacion', 0777, true);
            move_uploaded_file($_FILES[$input]['tmp_name'], 'uploads/documentacion/' . $nombre);
            return $nombre;
        }
        return null; 
    }

    $remito = subirAdjunto('archivo_remito');
    $comp = subirAdjunto('archivo_comprobante');

    $fecha_carga = !empty($_POST['mat_fecha_carga']) ? $_POST['mat_fecha_carga'] : null;
    $fecha_ph = !empty($_POST['mat_fecha_ph']) ? $_POST['mat_fecha_ph'] : null;

    // Lógica para detectar qué se hizo
    $acciones = [];
    if($fecha_carga) $acciones[] = "Carga";
    if($fecha_ph) $acciones[] = "PH";
    $tipo_mov = empty($acciones) ? "Mantenimiento" : implode(" y ", $acciones);

    $sql = "UPDATE inventario_cargos SET 
            mat_fecha_carga = :mvc, 
            mat_fecha_ph = :mvph, 
            nombre_tecnico = :ntec,
            id_estado_fk = (SELECT id_estado FROM inventario_estados WHERE nombre = 'Activo' LIMIT 1)"; 
    
    if($remito) $sql .= ", archivo_remito = '$remito'";
    if($comp) $sql .= ", archivo_comprobante = '$comp'";
    
    $sql .= " WHERE id_cargo = :id";

    $stmt = $pdo->prepare($sql);
    $stmt->execute([
        ':mvc' => $fecha_carga,
        ':mvph' => $fecha_ph,
        ':ntec' => $_POST['nombre_tecnico'],
        ':id' => $id
    ]);

    // Registrar Historial con el detalle exacto (Carga, PH, etc)
    $pdo->prepare("INSERT INTO historial_movimientos (id_bien, tipo_movimiento, usuario_registro, observacion_movimiento, fecha_movimiento) VALUES (?, ?, ?, 'Servicio técnico externo realizado', NOW())")
        ->execute([$id, $tipo_mov, $_SESSION['usuario_id']]);

    $mensaje = "✅ Mantenimiento registrado correctamente. El bien volvió a estado ACTIVO.";
    $bien = null; 
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Servicio Técnico Matafuegos</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>
<body class="bg-light">
    <?php include 'navbar.php'; ?>
    <div class="container mt-5">
        
        <div class="row justify-content-center">
            <div class="col-md-8">
                <div class="card shadow-sm border-0 mb-4">
                    <div class="card-body p-4 text-center">
                        <h3 class="fw-bold text-primary"><i class="fas fa-tools"></i> Orden de Trabajo / Servicio</h3>
                        <p class="text-muted">Buscar matafuego por <b>Código Interno</b> o <b>N° Grabado</b></p>
                        
                        <form method="GET" class="d-flex gap-2 justify-content-center mt-4">
                            <input type="text" name="buscar_codigo" class="form-control form-control-lg w-50" placeholder="Ingrese Código..." autofocus>
                            <button class="btn btn-primary btn-lg"><i class="fas fa-search"></i> Buscar</button>
                        </form>
                        
                        <div class="mt-2 text-muted small">
                            <a href="inventario_lista.php" class="text-decoration-none"><i class="fas fa-arrow-left"></i> Volver al listado</a>
                        </div>

                        <?php if($mensaje): ?>
                            <div class="alert alert-info mt-3 fw-bold"><?php echo $mensaje; ?></div>
                        <?php endif; ?>
                    </div>
                </div>

                <?php if($bien): ?>
                <div class="card shadow border-0 border-top border-4 border-success">
                    <div class="card-header bg-white py-3">
                        <h5 class="mb-0"><i class="fas fa-fire-extinguisher text-danger me-2"></i> <?php echo htmlspecialchars($bien['elemento']); ?></h5>
                        <div class="mt-2">
                            <span class="badge bg-primary">Interno: <?php echo htmlspecialchars($bien['codigo_inventario']); ?></span>
                            <span class="badge bg-danger">Grabado: <?php echo htmlspecialchars($bien['mat_numero_grabado']); ?></span>
                            <span class="badge bg-dark">Estado: <?php echo htmlspecialchars($bien['estado_actual']); ?></span>
                        </div>
                    </div>
                    <div class="card-body p-4">
                        <form method="POST" enctype="multipart/form-data">
                            <input type="hidden" name="id_cargo" value="<?php echo $bien['id_cargo']; ?>">
                            
                            <h6 class="fw-bold text-decoration-underline mb-3">1. Actualizar Vencimientos</h6>
                            <div class="alert alert-warning small"><i class="fas fa-info-circle"></i> Ingrese la fecha SÓLO del trabajo realizado. Puede dejar vacía la que no corresponda.</div>
                            
                            <div class="row g-3 mb-4">
                                <div class="col-md-6">
                                    <label class="form-label fw-bold">Fecha Realización Carga</label>
                                    <input type="date" name="mat_fecha_carga" class="form-control border-success" value="<?php echo $bien['mat_fecha_carga']; ?>">
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label fw-bold">Fecha Realización PH</label>
                                    <input type="date" name="mat_fecha_ph" class="form-control border-success" value="<?php echo $bien['mat_fecha_ph']; ?>">
                                </div>
                            </div>

                            <h6 class="fw-bold text-decoration-underline mb-3">2. Documentación Empresa</h6>
                            <div class="row g-3 mb-4">
                                <div class="col-md-6">
                                    <label class="form-label">Remito (Foto/PDF)</label>
                                    <input type="file" name="archivo_remito" class="form-control">
                                    <?php if($bien['archivo_remito']): ?><small class="text-success"><i class="fas fa-check"></i> Ya existe uno cargado</small><?php endif; ?>
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label">Comprobante Trabajo</label>
                                    <input type="file" name="archivo_comprobante" class="form-control">
                                    <?php if($bien['archivo_comprobante']): ?><small class="text-success"><i class="fas fa-check"></i> Ya existe uno cargado</small><?php endif; ?>
                                </div>
                                <div class="col-12">
                                    <label class="form-label fw-bold">Nombre Técnico</label>
                                    <input type="text" name="nombre_tecnico" class="form-control" placeholder="Nombre del técnico que realizó el trabajo" value="<?php echo htmlspecialchars($bien['nombre_tecnico']); ?>">
                                </div>
                            </div>

                            <div class="d-grid">
                                <button type="submit" class="btn btn-success btn-lg fw-bold">
                                    <i class="fas fa-save me-2"></i> GUARDAR MANTENIMIENTO
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
                <?php endif; ?>

            </div>
        </div>
    </div>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>