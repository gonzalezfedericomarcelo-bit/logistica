<?php
// Archivo: admin_areas.php (NUEVO)
// *** MODIFICADO (v2) POR GEMINI PARA APLICAR PERMISO 'admin_areas' ***
session_start();
include 'conexion.php';
include 'funciones_permisos.php'; // <-- AÑADIDO POR GEMINI

// --- INICIO BLOQUE DE SEGURIDAD AÑADIDO POR GEMINI ---
// 1. Proteger la página
// Solo usuarios con el permiso 'admin_areas' pueden acceder.
if (!isset($_SESSION['usuario_id']) || !tiene_permiso('admin_areas', $pdo)) {
    $_SESSION['action_error_message'] = "Acceso denegado. No tiene permiso para gestionar áreas.";
    header("Location: dashboard.php");
    exit();
}
// --- FIN BLOQUE DE SEGURIDAD ---


$mensaje = '';
$alerta_tipo = '';

// --- Lógica POST para Crear/Editar/Eliminar ---
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    // --- CREAR AREA ---
    if (isset($_POST['crear_area'])) {
        $nombre = trim($_POST['nombre_area']);
        $descripcion = trim($_POST['descripcion_area']);
        if (!empty($nombre)) {
            try {
                $sql = "INSERT INTO areas (nombre, descripcion) VALUES (:nombre, :descripcion)";
                $stmt = $pdo->prepare($sql);
                $stmt->execute([':nombre' => $nombre, ':descripcion' => $descripcion]);
                $mensaje = "Área '{$nombre}' creada exitosamente.";
                $alerta_tipo = 'success';
            } catch (PDOException $e) {
                $mensaje = ($e->getCode() == '23000') ? "Error: El nombre del área ya existe." : "Error al crear área: ". $e->getMessage();
                $alerta_tipo = 'danger';
            }
        } else {
            $mensaje = "El nombre del área no puede estar vacío.";
            $alerta_tipo = 'warning';
        }
    }
    // --- EDITAR AREA ---
    elseif (isset($_POST['editar_area'])) {
        $id_area = (int)$_POST['id_area_edit'];
        $nombre = trim($_POST['nombre_area_edit']);
        $descripcion = trim($_POST['descripcion_area_edit']);
        if ($id_area > 0 && !empty($nombre)) {
            try {
                $sql = "UPDATE areas SET nombre = :nombre, descripcion = :descripcion WHERE id_area = :id";
                $stmt = $pdo->prepare($sql);
                $stmt->execute([':nombre' => $nombre, ':descripcion' => $descripcion, ':id' => $id_area]);
                $mensaje = ($stmt->rowCount() > 0) ? "Área #{$id_area} actualizada." : "No se realizaron cambios.";
                $alerta_tipo = ($stmt->rowCount() > 0) ? 'success' : 'info';
            } catch (PDOException $e) {
                $mensaje = ($e->getCode() == '23000') ? "Error: El nombre del área ya existe." : "Error al editar área: ". $e->getMessage();
                $alerta_tipo = 'danger';
            }
        } else {
            $mensaje = "Datos inválidos para editar el área.";
            $alerta_tipo = 'warning';
        }
    }
    // --- ELIMINAR AREA ---
    elseif (isset($_POST['eliminar_area'])) {
        $id_area = (int)$_POST['id_area_delete'];
        if ($id_area > 0) {
            try {
                // Verificar si hay pedidos asociados antes de borrar (debido a ON DELETE SET NULL)
                // Opcional: Podrías simplemente borrar y los pedidos quedarían con id_area=NULL
                $sql = "DELETE FROM areas WHERE id_area = :id";
                $stmt = $pdo->prepare($sql);
                $stmt->execute([':id' => $id_area]);
                $mensaje = ($stmt->rowCount() > 0) ? "Área #{$id_area} eliminada." : "El área no se encontró.";
                $alerta_tipo = ($stmt->rowCount() > 0) ? 'success' : 'warning';
            } catch (PDOException $e) {
                // Este error no debería ocurrir con ON DELETE SET NULL, pero lo dejamos por si acaso
                $mensaje = "Error al eliminar área. Asegúrese de que no esté en uso activo.";
                $alerta_tipo = 'danger';
            }
        } else {
            $mensaje = "ID de área inválido para eliminar.";
            $alerta_tipo = 'warning';
        }
    }
}

// --- Obtener lista de áreas ---
$areas = [];
try {
    $stmt = $pdo->query("SELECT id_area, nombre, descripcion FROM areas ORDER BY nombre ASC");
    $areas = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $mensaje = "Error al cargar la lista de áreas: ". $e->getMessage();
    $alerta_tipo = 'danger';
}

include 'navbar.php'; // Incluir navbar después de la lógica
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gestionar Áreas</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">
</head>
<body>
<div class="container mt-4">
    <h1 class="mb-4"><i class="fas fa-map-marker-alt me-2"></i> Gestionar Áreas</h1>

    <?php if ($mensaje): ?>
    <div class="alert alert-<?php echo $alerta_tipo; ?> alert-dismissible fade show" role="alert">
        <?php echo htmlspecialchars($mensaje); ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
    </div>
    <?php endif; ?>

    <div class="card mb-4 shadow-sm">
        <div class="card-header bg-success text-white"><i class="fas fa-plus-circle me-1"></i> Crear Nueva Área</div>
        <div class="card-body">
            <form method="POST" action="admin_areas.php">
                <input type="hidden" name="crear_area" value="1">
                <div class="row g-3 align-items-end">
                    <div class="col-md-4">
                        <label for="nombre_area" class="form-label">Nombre Área (*)</label>
                        <input type="text" class="form-control" id="nombre_area" name="nombre_area" required maxlength="100">
                    </div>
                    <div class="col-md-6">
                        <label for="descripcion_area" class="form-label">Descripción (Opcional)</label>
                        <input type="text" class="form-control" id="descripcion_area" name="descripcion_area" maxlength="255">
                    </div>
                    <div class="col-md-2">
                        <button type="submit" class="btn btn-success w-100"><i class="fas fa-save"></i> Crear</button>
                    </div>
                </div>
            </form>
        </div>
    </div>

    <div class="card shadow-sm">
        <div class="card-header bg-secondary text-white"><i class="fas fa-list me-1"></i> Áreas Existentes</div>
        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-striped table-hover">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Nombre</th>
                            <th>Descripción</th>
                            <th class="text-end">Acciones</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($areas)): ?>
                            <tr><td colspan="4" class="text-center text-muted">No hay áreas creadas.</td></tr>
                        <?php else: ?>
                            <?php foreach ($areas as $area): ?>
                            <tr>
                                <td><?php echo $area['id_area']; ?></td>
                                <td><?php echo htmlspecialchars($area['nombre']); ?></td>
                                <td><?php echo htmlspecialchars($area['descripcion'] ?? '-'); ?></td>
                                <td class="text-end text-nowrap">
                                    <button class="btn btn-sm btn-info text-white me-1"
                                            data-bs-toggle="modal" data-bs-target="#editAreaModal"
                                            data-id="<?php echo $area['id_area']; ?>"
                                            data-nombre="<?php echo htmlspecialchars($area['nombre']); ?>"
                                            data-descripcion="<?php echo htmlspecialchars($area['descripcion'] ?? ''); ?>"
                                            onclick="loadEditAreaData(this)">
                                        <i class="fas fa-edit"></i>
                                    </button>
                                    <form method="POST" action="admin_areas.php" class="d-inline" onsubmit="return confirm('¿Seguro que desea eliminar el área \'<?php echo htmlspecialchars(addslashes($area['nombre'])); ?>\'? Los pedidos asociados quedarán sin área.');">
                                        <input type="hidden" name="eliminar_area" value="1">
                                        <input type="hidden" name="id_area_delete" value="<?php echo $area['id_area']; ?>">
                                        <button type="submit" class="btn btn-sm btn-danger">
                                            <i class="fas fa-trash"></i>
                                        </button>
                                    </form>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<div class="modal fade" id="editAreaModal" tabindex="-1" aria-labelledby="editAreaModalLabel" aria-hidden="true">
  <div class="modal-dialog">
    <div class="modal-content">
      <form method="POST" action="admin_areas.php">
        <input type="hidden" name="editar_area" value="1">
        <input type="hidden" name="id_area_edit" id="id_area_edit">
        <div class="modal-header bg-info text-white">
          <h5 class="modal-title" id="editAreaModalLabel"><i class="fas fa-edit me-1"></i> Editar Área</h5>
          <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
        </div>
        <div class="modal-body">
            <div class="mb-3">
                <label for="nombre_area_edit" class="form-label">Nombre Área (*)</label>
                <input type="text" class="form-control" id="nombre_area_edit" name="nombre_area_edit" required maxlength="100">
            </div>
            <div class="mb-3">
                <label for="descripcion_area_edit" class="form-label">Descripción (Opcional)</label>
                <input type="text" class="form-control" id="descripcion_area_edit" name="descripcion_area_edit" maxlength="255">
            </div>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
          <button type="submit" class="btn btn-info text-white"><i class="fas fa-save"></i> Guardar Cambios</button>
        </div>
      </form>
    </div>
  </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script>
    function loadEditAreaData(button) {
        document.getElementById('id_area_edit').value = button.getAttribute('data-id');
        document.getElementById('nombre_area_edit').value = button.getAttribute('data-nombre');
        document.getElementById('descripcion_area_edit').value = button.getAttribute('data-descripcion');
    }
</script>
</body>
</html>