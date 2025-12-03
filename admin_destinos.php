<?php
// Archivo: admin_destinos.php (NUEVO - Basado en admin_areas.php)
// *** MODIFICADO (v2) POR GEMINI PARA APLICAR PERMISO 'admin_destinos' ***
session_start();
include 'conexion.php';
include 'funciones_permisos.php'; // <-- AÑADIDO POR GEMINI

// --- INICIO BLOQUE DE SEGURIDAD AÑADIDO POR GEMINI ---
// 1. Proteger la página
// Solo usuarios con el permiso 'admin_destinos' pueden acceder.
if (!isset($_SESSION['usuario_id']) || !tiene_permiso('admin_destinos', $pdo)) {
    $_SESSION['action_error_message'] = "Acceso denegado. No tiene permiso para gestionar destinos.";
    header("Location: dashboard.php");
    exit();
}
// --- FIN BLOQUE DE SEGURIDAD ---


$mensaje = '';
$alerta_tipo = '';

// --- Lógica POST para Crear/Editar/Eliminar ---
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    // --- CREAR DESTINO ---
    if (isset($_POST['crear_destino'])) {
        $nombre = trim($_POST['nombre_destino']);
        $ubicacion = trim($_POST['ubicacion_destino']); // Cambiado de descripcion
        if (!empty($nombre)) {
            try {
                // Cambiado: tabla destinos_internos, columna ubicacion_referencia
                $sql = "INSERT INTO destinos_internos (nombre, ubicacion_referencia) VALUES (:nombre, :ubicacion)";
                $stmt = $pdo->prepare($sql);
                $stmt->execute([':nombre' => $nombre, ':ubicacion' => $ubicacion]);
                $mensaje = "Destino Interno '{$nombre}' creado exitosamente.";
                $alerta_tipo = 'success';
            } catch (PDOException $e) {
                $mensaje = ($e->getCode() == '23000') ? "Error: El nombre del destino ya existe." : "Error al crear destino: " . $e->getMessage();
                $alerta_tipo = 'danger';
            }
        } else {
            $mensaje = "El nombre del destino no puede estar vacío.";
            $alerta_tipo = 'warning';
        }
    }
    // --- EDITAR DESTINO ---
    elseif (isset($_POST['editar_destino'])) {
        $id_destino = (int)$_POST['id_destino_edit']; // Cambiado de id_area_edit
        $nombre = trim($_POST['nombre_destino_edit']); // Cambiado
        $ubicacion = trim($_POST['ubicacion_destino_edit']); // Cambiado
        if ($id_destino > 0 && !empty($nombre)) {
            try {
                 // Cambiado: tabla, columnas y id
                $sql = "UPDATE destinos_internos SET nombre = :nombre, ubicacion_referencia = :ubicacion WHERE id_destino = :id";
                $stmt = $pdo->prepare($sql);
                $stmt->execute([':nombre' => $nombre, ':ubicacion' => $ubicacion, ':id' => $id_destino]);
                $mensaje = ($stmt->rowCount() > 0) ? "Destino Interno #{$id_destino} actualizado." : "No se realizaron cambios.";
                $alerta_tipo = ($stmt->rowCount() > 0) ? 'success' : 'info';
            } catch (PDOException $e) {
                $mensaje = ($e->getCode() == '23000') ? "Error: El nombre del destino ya existe." : "Error al editar destino: " . $e->getMessage();
                $alerta_tipo = 'danger';
            }
        } else {
            $mensaje = "Datos inválidos para editar el destino.";
            $alerta_tipo = 'warning';
        }
    }
    // --- ELIMINAR DESTINO ---
    elseif (isset($_POST['eliminar_destino'])) {
        $id_destino = (int)$_POST['id_destino_delete']; // Cambiado
        if ($id_destino > 0) {
            try {
                // Cambiado: tabla y id
                $sql = "DELETE FROM destinos_internos WHERE id_destino = :id";
                $stmt = $pdo->prepare($sql);
                $stmt->execute([':id' => $id_destino]);
                $mensaje = ($stmt->rowCount() > 0) ? "Destino Interno #{$id_destino} eliminado." : "El destino no se encontró.";
                $alerta_tipo = ($stmt->rowCount() > 0) ? 'success' : 'warning';
            } catch (PDOException $e) {
                $mensaje = "Error al eliminar destino. Asegúrese de que no esté en uso activo.";
                $alerta_tipo = 'danger';
            }
        } else {
            $mensaje = "ID de destino inválido para eliminar.";
            $alerta_tipo = 'warning';
        }
    }
}

// --- Obtener lista de destinos ---
$destinos = [];
try {
    // Cambiado: tabla y columnas
    $stmt = $pdo->query("SELECT id_destino, nombre, ubicacion_referencia FROM destinos_internos ORDER BY nombre ASC");
    $destinos = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $mensaje = "Error al cargar la lista de destinos: " . $e->getMessage();
    $alerta_tipo = 'danger';
}

include 'navbar.php'; // Incluir navbar después de la lógica
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gestionar Destinos Internos</title> <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">
</head>
<body>
<div class="container mt-4">
    <h1 class="mb-4"><i class="fas fa-compass me-2"></i> Gestionar Destinos Internos</h1>

    <?php if ($mensaje): ?>
    <div class="alert alert-<?php echo $alerta_tipo; ?> alert-dismissible fade show" role="alert">
        <?php echo htmlspecialchars($mensaje); ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
    </div>
    <?php endif; ?>

    <div class="card mb-4 shadow-sm">
         <div class="card-header bg-success text-white"><i class="fas fa-plus-circle me-1"></i> Crear Nuevo Destino Interno</div>
        <div class="card-body">
            <form method="POST" action="admin_destinos.php">
                <input type="hidden" name="crear_destino" value="1"> <div class="row g-3 align-items-end">
                    <div class="col-md-4">
                         <label for="nombre_destino" class="form-label">Nombre Destino (*)</label>
                        <input type="text" class="form-control" id="nombre_destino" name="nombre_destino" required maxlength="100">
                    </div>
                    <div class="col-md-6">
                         <label for="ubicacion_destino" class="form-label">Ubicación/Referencia (Opcional)</label>
                        <input type="text" class="form-control" id="ubicacion_destino" name="ubicacion_destino" maxlength="255">
                    </div>
                    <div class="col-md-2">
                        <button type="submit" class="btn btn-success w-100"><i class="fas fa-save"></i> Crear</button>
                    </div>
                </div>
            </form>
        </div>
    </div>

    <div class="card shadow-sm">
         <div class="card-header bg-secondary text-white"><i class="fas fa-list me-1"></i> Destinos Existentes</div>
        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-striped table-hover">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Nombre</th>
                             <th>Ubicación/Referencia</th>
                            <th class="text-end">Acciones</th>
                        </tr>
                    </thead>
                    <tbody>
                         <?php if (empty($destinos)): ?>
                            <tr><td colspan="4" class="text-center text-muted">No hay destinos creados.</td></tr>
                        <?php else: ?>
                             <?php foreach ($destinos as $destino): ?>
                            <tr>
                                <td><?php echo $destino['id_destino']; ?></td>
                                <td><?php echo htmlspecialchars($destino['nombre']); ?></td>
                                <td><?php echo htmlspecialchars($destino['ubicacion_referencia'] ?? '-'); ?></td>
                                <td class="text-end text-nowrap">
                                    <button class="btn btn-sm btn-info text-white me-1"
                                            data-bs-toggle="modal" data-bs-target="#editDestinoModal"
                                             data-id="<?php echo $destino['id_destino']; ?>"
                                            data-nombre="<?php echo htmlspecialchars($destino['nombre']); ?>"
                                            data-ubicacion="<?php echo htmlspecialchars($destino['ubicacion_referencia'] ?? ''); ?>"
                                            onclick="loadEditDestinoData(this)">
                                        <i class="fas fa-edit"></i>
                                    </button>
                                    <form method="POST" action="admin_destinos.php" class="d-inline" onsubmit="return confirm('¿Seguro que desea eliminar el destino \'<?php echo htmlspecialchars(addslashes($destino['nombre'])); ?>\'? Los pedidos asociados quedarán sin destino.');">
                                         <input type="hidden" name="eliminar_destino" value="1">
                                        <input type="hidden" name="id_destino_delete" value="<?php echo $destino['id_destino']; ?>">
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

<div class="modal fade" id="editDestinoModal" tabindex="-1" aria-labelledby="editDestinoModalLabel" aria-hidden="true">
  <div class="modal-dialog">
    <div class="modal-content">
      <form method="POST" action="admin_destinos.php">
         <input type="hidden" name="editar_destino" value="1">
        <input type="hidden" name="id_destino_edit" id="id_destino_edit">
        <div class="modal-header bg-info text-white">
          <h5 class="modal-title" id="editDestinoModalLabel"><i class="fas fa-edit me-1"></i> Editar Destino Interno</h5>
          <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
        </div>
        <div class="modal-body">
            <div class="mb-3">
                 <label for="nombre_destino_edit" class="form-label">Nombre Destino (*)</label>
                <input type="text" class="form-control" id="nombre_destino_edit" name="nombre_destino_edit" required maxlength="100">
            </div>
            <div class="mb-3">
                 <label for="ubicacion_destino_edit" class="form-label">Ubicación/Referencia (Opcional)</label>
                <input type="text" class="form-control" id="ubicacion_destino_edit" name="ubicacion_destino_edit" maxlength="255">
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
    // Cambiado: nombre función y atributos data-
    function loadEditDestinoData(button) {
        document.getElementById('id_destino_edit').value = button.getAttribute('data-id');
        document.getElementById('nombre_destino_edit').value = button.getAttribute('data-nombre');
        document.getElementById('ubicacion_destino_edit').value = button.getAttribute('data-ubicacion');
    }
</script>
<?php include 'footer.php'; ?>
</body>
</html>