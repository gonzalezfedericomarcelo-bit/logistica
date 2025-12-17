<?php
// Archivo: admin_ascensores.php (DISEÑO MEJORADO + EDICIÓN)
session_start();
require_once 'conexion.php';
require_once 'funciones_permisos.php';

// Verificación de permiso
if (!tiene_permiso('admin_ascensores', $pdo)) {
    header("Location: mantenimiento_ascensores.php");
    exit;
}

$mensaje = '';
$tipo_alerta = '';

// --- LÓGICA DE CREAR ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['crear'])) {
    try {
        $nombre = $_POST['nombre'];
        $ubicacion = $_POST['ubicacion'];
        $serie = $_POST['serie'];
        $id_empresa = !empty($_POST['id_empresa']) ? $_POST['id_empresa'] : null;
        
        $stmt = $pdo->prepare("INSERT INTO ascensores (nombre, ubicacion, nro_serie, id_empresa) VALUES (?,?,?,?)");
        $stmt->execute([$nombre, $ubicacion, $serie, $id_empresa]);
        
        $mensaje = "Unidad creada exitosamente.";
        $tipo_alerta = "success";
    } catch (PDOException $e) {
        $mensaje = "Error al crear: " . $e->getMessage();
        $tipo_alerta = "danger";
    }
}

// --- LÓGICA DE EDITAR (NUEVO) ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['editar'])) {
    try {
        $id_ascensor = $_POST['id_ascensor'];
        $nombre = $_POST['nombre'];
        $ubicacion = $_POST['ubicacion'];
        $serie = $_POST['serie'];
        $id_empresa = !empty($_POST['id_empresa']) ? $_POST['id_empresa'] : null;

        $sql = "UPDATE ascensores SET nombre = ?, ubicacion = ?, nro_serie = ?, id_empresa = ? WHERE id_ascensor = ?";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$nombre, $ubicacion, $serie, $id_empresa, $id_ascensor]);

        $mensaje = "Unidad actualizada correctamente.";
        $tipo_alerta = "success";
    } catch (PDOException $e) {
        $mensaje = "Error al actualizar: " . $e->getMessage();
        $tipo_alerta = "danger";
    }
}

// --- LÓGICA DE BORRAR ---
if (isset($_POST['borrar_id'])) {
    try {
        $pdo->prepare("DELETE FROM ascensores WHERE id_ascensor = ?")->execute([$_POST['borrar_id']]);
        $mensaje = "Unidad eliminada.";
        $tipo_alerta = "warning";
    } catch (PDOException $e) {
        $mensaje = "No se puede eliminar (probablemente tenga historial asociado).";
        $tipo_alerta = "danger";
    }
}

// --- CONSULTAS ---
// 1. Lista de Ascensores
$sql = "SELECT a.*, e.nombre as nombre_empresa 
        FROM ascensores a 
        LEFT JOIN empresas_mantenimiento e ON a.id_empresa = e.id_empresa
        ORDER BY a.nombre";
$lista = $pdo->query($sql)->fetchAll(PDO::FETCH_ASSOC);

// 2. Lista de Empresas (Para selects)
$empresas = $pdo->query("SELECT * FROM empresas_mantenimiento WHERE activo = 1 ORDER BY nombre")->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <?php include 'head.php'; ?>
    <title>Administrar Unidades</title>
</head>
<body style="background-color: #f8f9fa;">
    <?php include 'navbar.php'; ?>
    
    <div class="container mt-5 mb-5">
        
        <div class="d-flex justify-content-between align-items-center mb-4">
            <div>
                <h2 class="mb-0 fw-bold text-dark"><i class="fas fa-cogs text-primary me-2"></i> Configuración de Unidades</h2>
                <p class="text-muted mb-0">Gestiona los ascensores y equipos del edificio.</p>
            </div>
            <div>
                <a href="admin_empresas.php" class="btn btn-outline-dark me-2"><i class="fas fa-building me-1"></i> Empresas</a>
                <a href="mantenimiento_ascensores.php" class="btn btn-primary"><i class="fas fa-arrow-left me-1"></i> Volver al Tablero</a>
            </div>
        </div>

        <?php if ($mensaje): ?>
            <div class="alert alert-<?php echo $tipo_alerta; ?> alert-dismissible fade show shadow-sm" role="alert">
                <i class="fas fa-info-circle me-2"></i> <?php echo $mensaje; ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>

        <div class="row g-4">
            
            <div class="col-lg-4">
                <div class="card shadow border-0 h-100">
                    <div class="card-header bg-primary text-white fw-bold py-3">
                        <i class="fas fa-plus-circle me-2"></i> Registrar Nueva Unidad
                    </div>
                    <div class="card-body bg-light">
                        <form method="POST">
                            <div class="mb-3">
                                <label class="form-label fw-bold small text-uppercase text-muted">Nombre / ID</label>
                                <div class="input-group">
                                    <span class="input-group-text bg-white"><i class="fas fa-tag text-secondary"></i></span>
                                    <input type="text" name="nombre" class="form-control" required placeholder="Ej: Ascensor Principal">
                                </div>
                            </div>
                            <div class="mb-3">
                                <label class="form-label fw-bold small text-uppercase text-muted">Ubicación</label>
                                <div class="input-group">
                                    <span class="input-group-text bg-white"><i class="fas fa-map-marker-alt text-secondary"></i></span>
                                    <input type="text" name="ubicacion" class="form-control" placeholder="Ej: Hall Central">
                                </div>
                            </div>
                            <div class="mb-3">
                                <label class="form-label fw-bold small text-uppercase text-muted">Nro. Serie / Modelo</label>
                                <div class="input-group">
                                    <span class="input-group-text bg-white"><i class="fas fa-barcode text-secondary"></i></span>
                                    <input type="text" name="serie" class="form-control" placeholder="Opcional">
                                </div>
                            </div>
                            
                            <div class="mb-4">
                                <label class="form-label fw-bold small text-uppercase text-muted">Empresa Asignada</label>
                                <select name="id_empresa" class="form-select">
                                    <option value="">-- Sin asignar --</option>
                                    <?php foreach($empresas as $e): ?>
                                        <option value="<?php echo $e['id_empresa']; ?>">
                                            <?php echo htmlspecialchars($e['nombre']); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>

                            <button type="submit" name="crear" class="btn btn-success w-100 fw-bold py-2 shadow-sm">
                                <i class="fas fa-save me-2"></i> Guardar Unidad
                            </button>
                        </form>
                    </div>
                </div>
            </div>

            <div class="col-lg-8">
                <div class="card shadow border-0">
                    <div class="card-header bg-white py-3 d-flex justify-content-between align-items-center">
                        <h5 class="mb-0 text-primary fw-bold"><i class="fas fa-list-ul me-2"></i> Unidades Registradas</h5>
                        <span class="badge bg-light text-dark border"><?php echo count($lista); ?> Equipos</span>
                    </div>
                    <div class="card-body p-0">
                        <div class="table-responsive">
                            <table class="table table-hover align-middle mb-0">
                                <thead class="table-light">
                                    <tr>
                                        <th class="ps-4">Nombre / Ubicación</th>
                                        <th>Serie</th>
                                        <th>Empresa</th>
                                        <th class="text-end pe-4">Acciones</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if (empty($lista)): ?>
                                        <tr><td colspan="4" class="text-center py-5 text-muted">No hay ascensores registrados aún.</td></tr>
                                    <?php else: ?>
                                        <?php foreach($lista as $l): ?>
                                        <tr>
                                            <td class="ps-4">
                                                <div class="fw-bold text-dark"><?php echo htmlspecialchars($l['nombre']); ?></div>
                                                <small class="text-muted"><i class="fas fa-map-pin me-1"></i> <?php echo htmlspecialchars($l['ubicacion']); ?></small>
                                            </td>
                                            <td><span class="badge bg-secondary font-monospace"><?php echo htmlspecialchars($l['nro_serie'] ?: 'S/N'); ?></span></td>
                                            <td>
                                                <?php if($l['nombre_empresa']): ?>
                                                    <span class="badge bg-info text-dark"><i class="fas fa-hard-hat me-1"></i> <?php echo htmlspecialchars($l['nombre_empresa']); ?></span>
                                                <?php else: ?>
                                                    <span class="badge bg-light text-muted border">Sin empresa</span>
                                                <?php endif; ?>
                                            </td>
                                            <td class="text-end pe-4">
                                                <div class="btn-group">
                                                    <button class="btn btn-sm btn-outline-primary" data-bs-toggle="modal" data-bs-target="#editModal<?php echo $l['id_ascensor']; ?>" title="Editar">
                                                        <i class="fas fa-edit"></i>
                                                    </button>
                                                    
                                                    <form method="POST" class="d-inline" onsubmit="return confirm('¿Estás seguro de eliminar \'<?php echo htmlspecialchars($l['nombre']); ?>\'?');">
                                                        <input type="hidden" name="borrar_id" value="<?php echo $l['id_ascensor']; ?>">
                                                        <button type="submit" class="btn btn-sm btn-outline-danger" title="Eliminar">
                                                            <i class="fas fa-trash"></i>
                                                        </button>
                                                    </form>
                                                </div>
                                            </td>
                                        </tr>

                                        <div class="modal fade" id="editModal<?php echo $l['id_ascensor']; ?>" tabindex="-1">
                                            <div class="modal-dialog modal-dialog-centered">
                                                <div class="modal-content border-0 shadow">
                                                    <div class="modal-header bg-primary text-white">
                                                        <h5 class="modal-title"><i class="fas fa-edit me-2"></i> Editar Unidad</h5>
                                                        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                                                    </div>
                                                    <form method="POST">
                                                        <div class="modal-body p-4">
                                                            <input type="hidden" name="id_ascensor" value="<?php echo $l['id_ascensor']; ?>">
                                                            
                                                            <div class="mb-3">
                                                                <label class="form-label fw-bold">Nombre</label>
                                                                <input type="text" name="nombre" class="form-control" value="<?php echo htmlspecialchars($l['nombre']); ?>" required>
                                                            </div>
                                                            <div class="row">
                                                                <div class="col-md-6 mb-3">
                                                                    <label class="form-label fw-bold">Ubicación</label>
                                                                    <input type="text" name="ubicacion" class="form-control" value="<?php echo htmlspecialchars($l['ubicacion']); ?>">
                                                                </div>
                                                                <div class="col-md-6 mb-3">
                                                                    <label class="form-label fw-bold">Serie</label>
                                                                    <input type="text" name="serie" class="form-control" value="<?php echo htmlspecialchars($l['nro_serie']); ?>">
                                                                </div>
                                                            </div>
                                                            <div class="mb-3">
                                                                <label class="form-label fw-bold">Empresa Asignada</label>
                                                                <select name="id_empresa" class="form-select">
                                                                    <option value="">-- Sin asignar --</option>
                                                                    <?php foreach($empresas as $e): ?>
                                                                        <option value="<?php echo $e['id_empresa']; ?>" <?php echo ($l['id_empresa'] == $e['id_empresa']) ? 'selected' : ''; ?>>
                                                                            <?php echo htmlspecialchars($e['nombre']); ?>
                                                                        </option>
                                                                    <?php endforeach; ?>
                                                                </select>
                                                            </div>
                                                        </div>
                                                        <div class="modal-footer bg-light">
                                                            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                                                            <button type="submit" name="editar" class="btn btn-primary px-4 fw-bold">Guardar Cambios</button>
                                                        </div>
                                                    </form>
                                                </div>
                                            </div>
                                        </div>
                                        <?php endforeach; ?>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>