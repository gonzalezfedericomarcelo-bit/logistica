<?php
// Archivo: admin_areas.php
// MODIFICADO: Incluye Herramienta de Asignación Masiva de Destinos
session_start();
include 'conexion.php';
include 'funciones_permisos.php';

if (!isset($_SESSION['usuario_id']) || !tiene_permiso('admin_areas', $pdo)) {
    $_SESSION['action_error_message'] = "Acceso denegado.";
    header("Location: dashboard.php");
    exit();
}

$mensaje = '';
$alerta_tipo = '';

// Obtener Destinos para los desplegables
$destinos_db = $pdo->query("SELECT id_destino, nombre FROM destinos_internos ORDER BY nombre ASC")->fetchAll(PDO::FETCH_ASSOC);

// --- LÓGICA POST ---
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    
    // 1. CREAR ÁREA
    if (isset($_POST['crear_area'])) {
        $nombre = trim($_POST['nombre_area']);
        $descripcion = trim($_POST['descripcion_area']);
        $id_destino = !empty($_POST['id_destino']) ? $_POST['id_destino'] : NULL;

        if (!empty($nombre)) {
            try {
                $sql = "INSERT INTO areas (nombre, descripcion, id_destino) VALUES (:nombre, :descripcion, :id_destino)";
                $stmt = $pdo->prepare($sql);
                $stmt->execute([':nombre' => $nombre, ':descripcion' => $descripcion, ':id_destino' => $id_destino]);
                $mensaje = "Área creada exitosamente.";
                $alerta_tipo = 'success';
            } catch (PDOException $e) {
                $mensaje = "Error al crear: " . $e->getMessage();
                $alerta_tipo = 'danger';
            }
        }
    }
    
    // 2. EDITAR ÁREA INDIVIDUAL
    elseif (isset($_POST['editar_area'])) {
        $id_area = (int)$_POST['id_area_edit'];
        $nombre = trim($_POST['nombre_area_edit']);
        $descripcion = trim($_POST['descripcion_area_edit']);
        $id_destino = !empty($_POST['id_destino_edit']) ? $_POST['id_destino_edit'] : NULL;

        if ($id_area > 0 && !empty($nombre)) {
            try {
                $sql = "UPDATE areas SET nombre = :nombre, descripcion = :descripcion, id_destino = :id_destino WHERE id_area = :id";
                $stmt = $pdo->prepare($sql);
                $stmt->execute([':nombre' => $nombre, ':descripcion' => $descripcion, ':id_destino' => $id_destino, ':id' => $id_area]);
                $mensaje = "Área actualizada.";
                $alerta_tipo = 'success';
            } catch (PDOException $e) {
                $mensaje = "Error al editar: " . $e->getMessage();
                $alerta_tipo = 'danger';
            }
        }
    }

    // 3. ASIGNACIÓN MASIVA (NUEVO)
    elseif (isset($_POST['accion_masiva']) && $_POST['accion_masiva'] == 'asignar_destino') {
        if (!empty($_POST['ids_seleccionados']) && is_array($_POST['ids_seleccionados']) && !empty($_POST['id_destino_masivo'])) {
            $ids = array_map('intval', $_POST['ids_seleccionados']);
            $id_destino = (int)$_POST['id_destino_masivo'];
            $placeholders = implode(',', array_fill(0, count($ids), '?'));
            
            try {
                // Preparamos la consulta con IN (...)
                $sql = "UPDATE areas SET id_destino = ? WHERE id_area IN ($placeholders)";
                $stmt = $pdo->prepare($sql);
                
                // El primer parámetro es el destino, luego unimos los IDs
                $params = array_merge([$id_destino], $ids);
                $stmt->execute($params);
                
                $count = $stmt->rowCount();
                $mensaje = "Se asignó el destino a $count áreas correctamente.";
                $alerta_tipo = 'success';
            } catch (PDOException $e) {
                $mensaje = "Error en asignación masiva: " . $e->getMessage();
                $alerta_tipo = 'danger';
            }
        } else {
            $mensaje = "Debe seleccionar al menos un área y un destino válido.";
            $alerta_tipo = 'warning';
        }
    }

    // 4. ELIMINAR ÁREA
    elseif (isset($_POST['eliminar_area'])) {
        $id_area = (int)$_POST['id_area_delete'];
        try {
            $stmt = $pdo->prepare("DELETE FROM areas WHERE id_area = :id");
            $stmt->execute([':id' => $id_area]);
            $mensaje = "Área eliminada.";
            $alerta_tipo = 'success';
        } catch (PDOException $e) {
            $mensaje = "Error al eliminar.";
            $alerta_tipo = 'danger';
        }
    }
}

// --- LISTADO ---
$sql_areas = "SELECT a.*, d.nombre as nombre_destino 
              FROM areas a 
              LEFT JOIN destinos_internos d ON a.id_destino = d.id_destino 
              ORDER BY d.nombre ASC, a.nombre ASC";
$areas = $pdo->query($sql_areas)->fetchAll(PDO::FETCH_ASSOC);

include 'navbar.php';
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Gestionar Áreas</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">
    <style>
        .check-column { width: 40px; text-align: center; }
        .bg-selected { background-color: #e8f0fe !important; }
    </style>
</head>
<body>
<div class="container mt-4 mb-5">
    <h1 class="mb-4"><i class="fas fa-map-marker-alt me-2"></i> Configuración de Áreas</h1>

    <?php if ($mensaje): ?>
    <div class="alert alert-<?php echo $alerta_tipo; ?> alert-dismissible fade show">
        <?php echo htmlspecialchars($mensaje); ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
    <?php endif; ?>

    <div class="card mb-4 shadow-sm border-success">
        <div class="card-header bg-success text-white"><i class="fas fa-plus-circle me-1"></i> Nueva Área Individual</div>
        <div class="card-body">
            <form method="POST" action="admin_areas.php">
                <input type="hidden" name="crear_area" value="1">
                <div class="row g-2 align-items-end">
                    <div class="col-md-3">
                        <label class="form-label small fw-bold">Destino (Padre)</label>
                        <select name="id_destino" class="form-select form-select-sm" required>
                            <option value="">-- Seleccione --</option>
                            <?php foreach ($destinos_db as $dest): ?>
                                <option value="<?php echo $dest['id_destino']; ?>"><?php echo htmlspecialchars($dest['nombre']); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label small fw-bold">Nombre Área</label>
                        <input type="text" class="form-control form-select-sm" name="nombre_area" required>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label small fw-bold">Descripción</label>
                        <input type="text" class="form-control form-select-sm" name="descripcion_area">
                    </div>
                    <div class="col-md-2">
                        <button type="submit" class="btn btn-sm btn-success w-100 fw-bold">Guardar</button>
                    </div>
                </div>
            </form>
        </div>
    </div>

    <form method="POST" action="admin_areas.php" id="formMasivo">
        <input type="hidden" name="accion_masiva" value="asignar_destino">
        
        <div class="card shadow-sm">
            <div class="card-header bg-secondary text-white d-flex justify-content-between align-items-center flex-wrap">
                <div><i class="fas fa-list me-1"></i> Áreas Existentes</div>
                
                <div class="d-flex gap-2 align-items-center bg-white p-1 rounded text-dark mt-2 mt-md-0">
                    <div class="form-check ms-2">
                        <input class="form-check-input" type="checkbox" id="checkAll">
                        <label class="form-check-label small fw-bold" for="checkAll">Todas</label>
                    </div>
                    <div class="vr"></div>
                    <small class="fw-bold">Asignar a:</small>
                    <select name="id_destino_masivo" class="form-select form-select-sm w-auto" style="min-width: 150px;">
                        <option value="">-- Elegir Destino --</option>
                        <?php foreach ($destinos_db as $dest): ?>
                            <option value="<?php echo $dest['id_destino']; ?>"><?php echo htmlspecialchars($dest['nombre']); ?></option>
                        <?php endforeach; ?>
                    </select>
                    <button type="submit" class="btn btn-sm btn-primary" onclick="return confirm('¿Asignar el destino seleccionado a todas las áreas marcadas?')">
                        <i class="fas fa-check-double"></i> Aplicar
                    </button>
                </div>
            </div>

            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-striped table-hover mb-0 align-middle">
                        <thead class="table-light">
                            <tr>
                                <th class="check-column">#</th>
                                <th>Destino (Padre)</th>
                                <th>Área (Hijo)</th>
                                <th>Descripción</th>
                                <th class="text-end">Acciones</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($areas as $area): ?>
                            <tr id="row_<?php echo $area['id_area']; ?>">
                                <td class="check-column">
                                    <input class="form-check-input area-check" type="checkbox" name="ids_seleccionados[]" value="<?php echo $area['id_area']; ?>">
                                </td>
                                <td>
                                    <?php if($area['nombre_destino']): ?>
                                        <span class="badge bg-info text-dark bg-opacity-10 border border-info px-2 py-1">
                                            <i class="fas fa-building me-1 text-muted"></i><?php echo htmlspecialchars($area['nombre_destino']); ?>
                                        </span>
                                    <?php else: ?>
                                        <span class="badge bg-danger text-dark bg-opacity-10 border border-danger">
                                            <i class="fas fa-exclamation-circle me-1 text-danger"></i> Sin Asignar
                                        </span>
                                    <?php endif; ?>
                                </td>
                                <td class="fw-bold text-dark"><?php echo htmlspecialchars($area['nombre']); ?></td>
                                <td class="text-muted small"><?php echo htmlspecialchars($area['descripcion'] ?? '-'); ?></td>
                                <td class="text-end">
                                    <button type="button" class="btn btn-sm btn-outline-primary border-0"
                                            data-bs-toggle="modal" data-bs-target="#editAreaModal"
                                            data-id="<?php echo $area['id_area']; ?>"
                                            data-nombre="<?php echo htmlspecialchars($area['nombre']); ?>"
                                            data-desc="<?php echo htmlspecialchars($area['descripcion']); ?>"
                                            data-destino="<?php echo $area['id_destino']; ?>"
                                            onclick="loadEdit(this)">
                                        <i class="fas fa-edit"></i>
                                    </button>
                                    
                                    <button type="button" class="btn btn-sm btn-outline-danger border-0" 
                                            onclick="confirmDelete(<?php echo $area['id_area']; ?>)">
                                        <i class="fas fa-trash"></i>
                                    </button>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </form>
</div>

<form id="deleteForm" method="POST" action="admin_areas.php">
    <input type="hidden" name="eliminar_area" value="1">
    <input type="hidden" name="id_area_delete" id="delete_id_input">
</form>

<div class="modal fade" id="editAreaModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog">
    <div class="modal-content">
      <form method="POST" action="admin_areas.php">
        <input type="hidden" name="editar_area" value="1">
        <input type="hidden" name="id_area_edit" id="id_area_edit">
        <div class="modal-header bg-info text-white">
          <h5 class="modal-title">Editar Área</h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
        </div>
        <div class="modal-body">
            <div class="mb-3">
                <label class="form-label">Destino (Padre)</label>
                <select name="id_destino_edit" id="id_destino_edit" class="form-select" required>
                    <option value="">-- Seleccione --</option>
                    <?php foreach ($destinos_db as $dest): ?>
                        <option value="<?php echo $dest['id_destino']; ?>"><?php echo htmlspecialchars($dest['nombre']); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="mb-3">
                <label class="form-label">Nombre Área</label>
                <input type="text" class="form-control" id="nombre_area_edit" name="nombre_area_edit" required>
            </div>
            <div class="mb-3">
                <label class="form-label">Descripción</label>
                <input type="text" class="form-control" id="descripcion_area_edit" name="descripcion_area_edit">
            </div>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
          <button type="submit" class="btn btn-info text-white">Guardar Cambios</button>
        </div>
      </form>
    </div>
  </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script>
    // Cargar datos al modal
    function loadEdit(btn) {
        document.getElementById('id_area_edit').value = btn.getAttribute('data-id');
        document.getElementById('nombre_area_edit').value = btn.getAttribute('data-nombre');
        document.getElementById('descripcion_area_edit').value = btn.getAttribute('data-desc');
        document.getElementById('id_destino_edit').value = btn.getAttribute('data-destino');
    }

    // Eliminar individual
    function confirmDelete(id) {
        if(confirm('¿Seguro que desea eliminar esta área?')) {
            document.getElementById('delete_id_input').value = id;
            document.getElementById('deleteForm').submit();
        }
    }

    // Selección masiva (Check All)
    document.getElementById('checkAll').addEventListener('change', function() {
        var checkboxes = document.querySelectorAll('.area-check');
        var isChecked = this.checked;
        checkboxes.forEach(function(cb) {
            cb.checked = isChecked;
            toggleRowColor(cb);
        });
    });

    // Colorear fila seleccionada
    var checkboxes = document.querySelectorAll('.area-check');
    checkboxes.forEach(function(cb) {
        cb.addEventListener('change', function() { toggleRowColor(this); });
    });

    function toggleRowColor(checkbox) {
        var row = document.getElementById('row_' + checkbox.value);
        if(checkbox.checked) { row.classList.add('bg-selected'); } 
        else { row.classList.remove('bg-selected'); }
    }
</script>
<?php include 'footer.php'; ?>
</body>
</html>