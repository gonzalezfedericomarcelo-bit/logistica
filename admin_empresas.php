<?php
// Archivo: admin_empresas.php (MEJORADO: GESTIÓN COMPLETA Y ERRORES)
session_start();
require_once 'conexion.php';
require_once 'funciones_permisos.php';

// Verificación de permiso
if (!tiene_permiso('admin_ascensores', $pdo)) { 
    header("Location: dashboard.php"); 
    exit; 
}

$mensaje = '';
$tipo_alerta = '';

// --- LÓGICA DE ACTUALIZAR / EDITAR ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['editar'])) {
    try {
        $sql = "UPDATE empresas_mantenimiento SET nombre = ?, email_contacto = ?, telefono = ? WHERE id_empresa = ?";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$_POST['nombre'], $_POST['email'], $_POST['telefono'], $_POST['id_empresa']]);
        
        $mensaje = "Empresa actualizada correctamente.";
        $tipo_alerta = "success";
    } catch (PDOException $e) {
        $mensaje = "Error al actualizar: " . $e->getMessage();
        $tipo_alerta = "danger";
    }
}

// --- LÓGICA DE CREAR ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['crear'])) {
    try {
        $sql = "INSERT INTO empresas_mantenimiento (nombre, email_contacto, telefono) VALUES (?,?,?)";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$_POST['nombre'], $_POST['email'], $_POST['telefono']]);
        
        $mensaje = "Empresa creada correctamente.";
        $tipo_alerta = "success";
    } catch (PDOException $e) {
        $mensaje = "Error al crear: " . $e->getMessage();
        $tipo_alerta = "danger";
    }
}

// --- LÓGICA DE BORRAR (CON PROTECCIÓN) ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['borrar'])) {
    $id_borrar = $_POST['id_borrar'];
    
    try {
        // 1. Verificar si tiene ascensores asignados antes de intentar borrar
        $check = $pdo->prepare("SELECT COUNT(*) FROM ascensores WHERE id_empresa = ?");
        $check->execute([$id_borrar]);
        $count = $check->fetchColumn();

        if ($count > 0) {
            $mensaje = "⚠️ NO se puede eliminar: Esta empresa tiene <b>$count ascensor(es)</b> asignados. Primero reasigna esos ascensores a otra empresa.";
            $tipo_alerta = "warning";
        } else {
            // 2. Si está libre, procedemos a borrar
            $pdo->prepare("DELETE FROM empresas_mantenimiento WHERE id_empresa = ?")->execute([$id_borrar]);
            $mensaje = "Empresa eliminada correctamente.";
            $tipo_alerta = "success";
        }
    } catch (PDOException $e) {
        // Captura otros errores (ej: historial de incidencias vinculado)
        $mensaje = "Error de base de datos: No se puede eliminar porque tiene historial vinculado.";
        $tipo_alerta = "danger";
    }
}

// Obtener lista
$empresas = $pdo->query("SELECT * FROM empresas_mantenimiento ORDER BY nombre ASC")->fetchAll();
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <?php include 'head.php'; ?>
    <title>Empresas de Mantenimiento</title>
</head>
<body>
    <?php include 'navbar.php'; ?>
    
    <div class="container mt-4">
        <div class="d-flex justify-content-between align-items-center mb-3">
            <h3><i class="fas fa-building text-primary"></i> Empresas de Mantenimiento</h3>
            <a href="admin_ascensores.php" class="btn btn-outline-secondary"><i class="fas fa-arrow-left"></i> Volver a Ascensores</a>
        </div>

        <?php if ($mensaje): ?>
            <div class="alert alert-<?php echo $tipo_alerta; ?> alert-dismissible fade show" role="alert">
                <?php echo $mensaje; ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>

        <div class="row">
            <div class="col-md-4 mb-4">
                <div class="card shadow-sm border-success">
                    <div class="card-header bg-success text-white fw-bold">
                        <i class="fas fa-plus-circle"></i> Nueva Empresa
                    </div>
                    <div class="card-body">
                        <form method="POST">
                            <div class="mb-3">
                                <label class="form-label fw-bold">Nombre</label>
                                <input type="text" name="nombre" class="form-control" required placeholder="Ej: ThyssenKrupp">
                            </div>
                            <div class="mb-3">
                                <label class="form-label fw-bold">Email (Para alertas)</label>
                                <input type="email" name="email" class="form-control" required placeholder="soporte@empresa.com">
                            </div>
                            <div class="mb-3">
                                <label class="form-label fw-bold">Teléfono</label>
                                <input type="text" name="telefono" class="form-control" placeholder="Ej: 0800-555-1234">
                            </div>
                            <button type="submit" name="crear" class="btn btn-success w-100 fw-bold">
                                <i class="fas fa-save"></i> Guardar Empresa
                            </button>
                        </form>
                    </div>
                </div>
            </div>

            <div class="col-md-8">
                <div class="card shadow-sm">
                    <div class="card-body p-0">
                        <table class="table table-striped table-hover mb-0 align-middle">
                            <thead class="table-dark">
                                <tr>
                                    <th>Nombre</th>
                                    <th>Contacto</th>
                                    <th class="text-end" style="width: 150px;">Acciones</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (empty($empresas)): ?>
                                    <tr><td colspan="3" class="text-center p-3">No hay empresas cargadas.</td></tr>
                                <?php else: ?>
                                    <?php foreach($empresas as $e): ?>
                                    <tr>
                                        <td class="fw-bold"><?php echo htmlspecialchars($e['nombre']); ?></td>
                                        <td>
                                            <div class="small"><i class="fas fa-envelope text-muted"></i> <?php echo htmlspecialchars($e['email_contacto']); ?></div>
                                            <?php if($e['telefono']): ?>
                                                <div class="small"><i class="fas fa-phone text-muted"></i> <?php echo htmlspecialchars($e['telefono']); ?></div>
                                            <?php endif; ?>
                                        </td>
                                        <td class="text-end">
                                            <button type="button" class="btn btn-primary btn-sm" data-bs-toggle="modal" data-bs-target="#editModal<?php echo $e['id_empresa']; ?>">
                                                <i class="fas fa-edit"></i>
                                            </button>

                                            <form method="POST" class="d-inline" onsubmit="return confirm('¿Seguro que deseas eliminar <?php echo htmlspecialchars($e['nombre']); ?>?');">
                                                <input type="hidden" name="id_borrar" value="<?php echo $e['id_empresa']; ?>">
                                                <button type="submit" name="borrar" class="btn btn-danger btn-sm">
                                                    <i class="fas fa-trash"></i>
                                                </button>
                                            </form>
                                        </td>
                                    </tr>

                                    <div class="modal fade" id="editModal<?php echo $e['id_empresa']; ?>" tabindex="-1">
                                        <div class="modal-dialog">
                                            <div class="modal-content">
                                                <div class="modal-header bg-primary text-white">
                                                    <h5 class="modal-title">Editar Empresa</h5>
                                                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                                                </div>
                                                <form method="POST">
                                                    <div class="modal-body">
                                                        <input type="hidden" name="id_empresa" value="<?php echo $e['id_empresa']; ?>">
                                                        <div class="mb-3">
                                                            <label class="form-label">Nombre</label>
                                                            <input type="text" name="nombre" class="form-control" value="<?php echo htmlspecialchars($e['nombre']); ?>" required>
                                                        </div>
                                                        <div class="mb-3">
                                                            <label class="form-label">Email</label>
                                                            <input type="email" name="email" class="form-control" value="<?php echo htmlspecialchars($e['email_contacto']); ?>" required>
                                                        </div>
                                                        <div class="mb-3">
                                                            <label class="form-label">Teléfono</label>
                                                            <input type="text" name="telefono" class="form-control" value="<?php echo htmlspecialchars($e['telefono']); ?>">
                                                        </div>
                                                    </div>
                                                    <div class="modal-footer">
                                                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                                                        <button type="submit" name="editar" class="btn btn-primary">Guardar Cambios</button>
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
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>