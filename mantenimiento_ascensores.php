<?php
// Archivo: mantenimiento_ascensores.php
// ESTADO: Navegación OK | Borrado Masivo OK | Reportar Falla -> Redirecciona (Sin Modal)
session_start();
require_once 'conexion.php';
require_once 'funciones_permisos.php';

// 1. Verificar Acceso
if (!isset($_SESSION['usuario_id']) || !tiene_permiso('acceso_ascensores', $pdo)) {
    header("Location: dashboard.php"); exit;
}
$id_usuario = $_SESSION['usuario_id'];

// 2. Verificar si es Admin
$es_admin = tiene_permiso('admin_ascensores', $pdo) || ($_SESSION['usuario_rol'] ?? '') === 'admin';

// --- LÓGICA DE ELIMINACIÓN INDIVIDUAL (GET) ---
if (isset($_GET['borrar_id']) && $es_admin) {
    $id_borrar = (int)$_GET['borrar_id'];
    try {
        // Borramos en orden para mantener integridad
        $pdo->prepare("DELETE FROM ascensor_visitas_tecnicas WHERE id_incidencia = ?")->execute([$id_borrar]);
        try { $pdo->prepare("DELETE FROM ascensor_historial WHERE id_incidencia = ?")->execute([$id_borrar]); } catch (Exception $e) {}
        $pdo->prepare("DELETE FROM ascensor_incidencias WHERE id_incidencia = ?")->execute([$id_borrar]);
        
        header("Location: mantenimiento_ascensores.php?msg=borrado");
        exit;
    } catch (Exception $e) {
        $error = "Error al eliminar: " . $e->getMessage();
    }
}

// --- LÓGICA DE ELIMINACIÓN MASIVA (POST) ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'borrar_masivo') {
    if ($es_admin && isset($_POST['ids']) && is_array($_POST['ids'])) {
        $ids = $_POST['ids'];
        $placeholders = implode(',', array_fill(0, count($ids), '?'));
        
        try {
            $pdo->beginTransaction();
            $stmt = $pdo->prepare("DELETE FROM ascensor_visitas_tecnicas WHERE id_incidencia IN ($placeholders)");
            $stmt->execute($ids);
            
            try {
                $stmt = $pdo->prepare("DELETE FROM ascensor_historial WHERE id_incidencia IN ($placeholders)");
                $stmt->execute($ids);
            } catch (Exception $e) {}

            $stmt = $pdo->prepare("DELETE FROM ascensor_incidencias WHERE id_incidencia IN ($placeholders)");
            $stmt->execute($ids);
            
            $pdo->commit();
            header("Location: mantenimiento_ascensores.php?msg=borrado_masivo");
            exit;
        } catch (Exception $e) {
            $pdo->rollBack();
            $error = "Error al borrar masivamente: " . $e->getMessage();
        }
    }
}

// --- DATOS VISTA ---
$lista_ascensores = $pdo->query("SELECT a.*, e.nombre as empresa_nombre FROM ascensores a LEFT JOIN empresas_mantenimiento e ON a.id_empresa = e.id_empresa WHERE a.estado != 'inactivo'")->fetchAll(PDO::FETCH_ASSOC);

$sql_incidencias = "SELECT i.*, a.nombre as nombre_ascensor, e.nombre as nombre_empresa, u.nombre_completo as usuario_reporta FROM ascensor_incidencias i JOIN ascensores a ON i.id_ascensor = a.id_ascensor LEFT JOIN empresas_mantenimiento e ON i.id_empresa = e.id_empresa JOIN usuarios u ON i.id_usuario_reporta = u.id_usuario ORDER BY FIELD(i.estado, 'reportado', 'reclamo_enviado', 'visita_programada', 'en_reparacion', 'resuelto'), i.fecha_reporte DESC";
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
    
    <div class="container mt-4 pb-5">
        
        <div class="d-flex justify-content-between align-items-center mb-4 p-3 bg-white rounded shadow-sm">
            <h2 class="mb-0 text-primary"><i class="fas fa-elevator"></i> Gestión de Ascensores</h2>
            <div class="d-flex gap-2">
                <?php if($es_admin): ?>
                    <a href="admin_ascensores.php" class="btn btn-outline-secondary"><i class="fas fa-cogs"></i> Unidades</a>
                    <a href="admin_empresas.php" class="btn btn-outline-secondary"><i class="fas fa-building"></i> Empresas</a>
                <?php endif; ?>
                
                <a href="ascensor_crear_incidencia.php" class="btn btn-danger">
                    <i class="fas fa-exclamation-circle"></i> Reportar Falla
                </a>
            </div>
        </div>

        <?php if(isset($_GET['msg'])): ?>
            <div class="alert alert-success alert-dismissible fade show">
                <?php 
                if ($_GET['msg'] == 'borrado') echo 'Reclamo eliminado.';
                elseif ($_GET['msg'] == 'borrado_masivo') echo 'Se eliminaron los reclamos seleccionados.';
                else echo 'Operación exitosa.';
                ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>
        <?php if(isset($error)): ?>
            <div class="alert alert-danger"><?php echo $error; ?></div>
        <?php endif; ?>

        <div class="row mb-4">
            <?php foreach ($lista_ascensores as $asc): ?>
                <div class="col-md-3 mb-2">
                    <div class="card h-100 border-<?php echo ($asc['estado'] == 'activo') ? 'success' : 'warning'; ?> shadow-sm">
                        <div class="card-body p-2 text-center">
                            <h6 class="card-title mb-1"><?php echo htmlspecialchars($asc['nombre']); ?></h6>
                            <small class="text-muted"><?php echo htmlspecialchars($asc['ubicacion']); ?></small>
                            <div class="mt-1">
                                <span class="badge bg-<?php echo ($asc['estado'] == 'activo') ? 'success' : 'warning'; ?> text-dark">
                                    <?php echo ucfirst($asc['estado']); ?>
                                </span>
                            </div>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>

        <div class="card shadow">
            <div class="card-header bg-dark text-white d-flex justify-content-between align-items-center">
                <h5 class="mb-0">Tablero de Reclamos</h5>
                <?php if($es_admin): ?>
                    <button type="submit" form="formBorrarMasivo" class="btn btn-sm btn-danger" onclick="return confirm('¿Eliminar TODOS los seleccionados? Esta acción no se deshace.');">
                        <i class="fas fa-trash"></i> Eliminar Seleccionados
                    </button>
                <?php endif; ?>
            </div>
            <div class="card-body p-0">
                <form id="formBorrarMasivo" method="POST">
                    <input type="hidden" name="action" value="borrar_masivo">
                    <div class="table-responsive">
                        <table class="table table-hover table-striped mb-0 align-middle">
                            <thead class="table-light">
                                <tr>
                                    <?php if($es_admin): ?>
                                        <th style="width: 40px;"><input type="checkbox" id="checkTodos" class="form-check-input"></th>
                                    <?php endif; ?>
                                    <th>ID</th>
                                    <th>Ascensor</th>
                                    <th>Empresa</th>
                                    <th>Fecha</th>
                                    <th>Prioridad</th>
                                    <th>Estado</th>
                                    <th class="text-end">Acciones</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (count($incidencias) > 0): ?>
                                    <?php foreach($incidencias as $row): ?>
                                    <tr>
                                        <?php if($es_admin): ?>
                                            <td><input type="checkbox" name="ids[]" value="<?php echo $row['id_incidencia']; ?>" class="form-check-input check-item"></td>
                                        <?php endif; ?>
                                        
                                        <td>#<?php echo $row['id_incidencia']; ?></td>
                                        <td class="fw-bold"><?php echo htmlspecialchars($row['nombre_ascensor']); ?></td>
                                        <td><?php echo htmlspecialchars($row['nombre_empresa'] ?? 'N/A'); ?></td>
                                        <td><?php echo date('d/m H:i', strtotime($row['fecha_reporte'])); ?></td>
                                        <td>
                                            <?php $bg = ($row['prioridad']=='emergencia')?'dark':(($row['prioridad']=='alta')?'danger':'warning'); ?>
                                            <span class="badge bg-<?php echo $bg; ?>"><?php echo strtoupper($row['prioridad']); ?></span>
                                        </td>
                                        <td>
                                            <span class="badge bg-<?php echo ($row['estado']=='resuelto')?'success':'primary'; ?>">
                                                <?php echo strtoupper(str_replace('_', ' ', $row['estado'])); ?>
                                            </span>
                                        </td>
                                        <td class="text-end">
                                            <a href="ascensor_detalle.php?id=<?php echo $row['id_incidencia']; ?>" class="btn btn-sm btn-info text-white" title="Ver">
                                                <i class="fas fa-eye"></i>
                                            </a>
                                            <?php if ($es_admin): ?>
                                                <a href="mantenimiento_ascensores.php?borrar_id=<?php echo $row['id_incidencia']; ?>" class="btn btn-sm btn-danger ms-1" onclick="return confirm('¿Eliminar este reclamo?');">
                                                    <i class="fas fa-trash-alt"></i>
                                                </a>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <tr><td colspan="<?php echo $es_admin ? '8' : '7'; ?>" class="text-center py-3 text-muted">Sin reclamos activos.</td></tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <?php include 'footer.php'; ?>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        document.getElementById('checkTodos')?.addEventListener('change', function() {
            var checkboxes = document.querySelectorAll('.check-item');
            for (var checkbox of checkboxes) {
                checkbox.checked = this.checked;
            }
        });
    </script>
</body>
</html>