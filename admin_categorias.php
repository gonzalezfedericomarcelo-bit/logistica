<?php
// Archivo: admin_categorias.php (CON EDICIÓN, CREACIÓN Y ELIMINACIÓN)
// *** MODIFICADO (v2) POR GEMINI PARA APLICAR PERMISO 'admin_categorias' ***
session_start();
include 'conexion.php';
include 'funciones_permisos.php'; // <-- AÑADIDO POR GEMINI

// --- INICIO BLOQUE DE SEGURIDAD AÑADIDO POR GEMINI ---
// 1. Proteger la página
// Solo usuarios con el permiso 'admin_categorias' (ej: admin, auxiliar, encargado) pueden acceder.
if (!isset($_SESSION['usuario_id']) || !tiene_permiso('admin_categorias', $pdo)) {
    $_SESSION['action_error_message'] = "Acceso denegado. No tiene permiso para gestionar categorías.";
    header("Location: dashboard.php");
    exit();
}
// --- FIN BLOQUE DE SEGURIDAD ---


$mensaje = '';
$alerta_tipo = '';

// --- Lógica para CREAR/EDITAR/ELIMINAR Categoría ---
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    
    // CREAR CATEGORÍA
    if (isset($_POST['crear_categoria'])) {
        $nombre = trim($_POST['nombre']);
        $descripcion = trim($_POST['descripcion']);
        $color = trim($_POST['color'] ?? '#6c757d');

        try {
            $sql = "INSERT INTO categorias (nombre, descripcion, color) VALUES (:nombre, :descripcion, :color)";
            $stmt = $pdo->prepare($sql);
            $stmt->bindParam(':nombre', $nombre);
            $stmt->bindParam(':descripcion', $descripcion);
            $stmt->bindParam(':color', $color);
            $stmt->execute();
            $mensaje = "Categoría '{$nombre}' creada exitosamente.";
            $alerta_tipo = 'success';
        } catch (PDOException $e) {
            $mensaje = "Error al crear la categoría. Podría ya existir o ser un problema de BD.";
            $alerta_tipo = 'danger';
        }
    }

    // MODIFICAR CATEGORÍA
    if (isset($_POST['modificar_categoria'])) {
        $id_categoria = (int)$_POST['id_categoria'];
        $nombre = trim($_POST['nombre_edit']);
        $descripcion = trim($_POST['descripcion_edit']);
        $color = trim($_POST['color_edit'] ?? '#6c757d');

        if ($id_categoria > 0) {
            try {
                $sql = "UPDATE categorias SET nombre = :nombre, descripcion = :descripcion, color = :color WHERE id_categoria = :id_categoria";
                $stmt = $pdo->prepare($sql);
                $stmt->bindParam(':nombre', $nombre);
                $stmt->bindParam(':descripcion', $descripcion);
                $stmt->bindParam(':color', $color);
                $stmt->bindParam(':id_categoria', $id_categoria, PDO::PARAM_INT);
                $stmt->execute();
                
                if ($stmt->rowCount() > 0) {
                    $mensaje = "Categoría #{$id_categoria} ('{$nombre}') modificada exitosamente.";
                    $alerta_tipo = 'success';
                } else {
                    $mensaje = "No se realizaron cambios o la categoría no existe.";
                    $alerta_tipo = 'warning';
                }
            } catch (PDOException $e) {
                $mensaje = "Error al modificar la categoría: " . $e->getMessage();
                $alerta_tipo = 'danger';
            }
        } else {
            $mensaje = "ID de categoría no válido para modificar.";
            $alerta_tipo = 'danger';
        }
    }

    // ELIMINAR CATEGORÍA
    if (isset($_POST['eliminar_categoria'])) {
        $id_categoria = (int)$_POST['id_categoria'];

        try {
            $sql = "DELETE FROM categorias WHERE id_categoria = :id_categoria";
            $stmt = $pdo->prepare($sql);
            $stmt->bindParam(':id_categoria', $id_categoria, PDO::PARAM_INT);
            $stmt->execute();
            if ($stmt->rowCount() > 0) {
                 $mensaje = "Categoría eliminada exitosamente.";
                 $alerta_tipo = 'success';
            } else {
                 $mensaje = "No se pudo encontrar o eliminar la categoría.";
                 $alerta_tipo = 'warning';
            }
        } catch (PDOException $e) {
            // Si hay tareas vinculadas, la clave foránea impedirá la eliminación
            $mensaje = "Error al eliminar la categoría. Asegúrese de que no tenga tareas asignadas.";
            $alerta_tipo = 'danger';
        }
    }
}

// --- Obtener la lista de categorías ---
try {
    $stmt = $pdo->query("SELECT id_categoria, nombre, descripcion, color FROM categorias ORDER BY nombre ASC");
    $categorias = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $categorias = [];
    $mensaje = "Error al cargar categorías: " . $e->getMessage();
    $alerta_tipo = 'danger';
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Administración de Categorías</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>
<body>

    <?php include 'navbar.php'; ?>

    <div class="container mt-4">
        <h1 class="mb-4"><i class="fas fa-tools me-2"></i>Administración de Categorías</h1>

        <?php if ($mensaje): ?>
        <div class="alert alert-<?php echo $alerta_tipo; ?> alert-dismissible fade show" role="alert">
            <?php echo $mensaje; ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
        <?php endif; ?>

        <div class="card mb-4">
            <div class="card-header bg-primary text-white">
                <i class="fas fa-plus-circle me-1"></i> Crear Nueva Categoría
            </div>
            <div class="card-body">
                <form method="POST" action="admin_categorias.php">
                    <input type="hidden" name="crear_categoria" value="1">
                    
                    <div class="mb-3">
                        <label for="nombre" class="form-label">Nombre de la Categoría</label>
                        <input type="text" class="form-control" id="nombre" name="nombre" required maxlength="100">
                    </div>

                    <div class="mb-3">
                        <label for="descripcion" classa="form-label">Descripción</label>
                        <textarea class="form-control" id="descripcion" name="descripcion" rows="3" maxlength="255"></textarea>
                    </div>

                    <div class="mb-3">
                        <label for="color" class="form-label">Color de Identificación (HEX)</label>
                        <input type="color" class="form-control form-control-color" id="color" name="color" value="#6c757d" title="Selecciona un color para identificar la categoría">
                        <small class="form-text text-muted">Este color puede usarse en gráficas o etiquetas.</small>
                    </div>

                    <button type="submit" class="btn btn-primary"><i class="fas fa-save me-1"></i> Guardar Categoría</button>
                </form>
            </div>
        </div>

        <div class="card">
            <div class="card-header bg-secondary text-white">
                <i class="fas fa-list me-1"></i> Listado de Categorías
            </div>
            <div class="card-body">
                <table class="table table-striped table-hover">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Nombre</th>
                            <th>Descripción</th>
                            <th>Color</th>
                            <th>Acciones</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (count($categorias) > 0): ?>
                        <?php foreach ($categorias as $cat): ?>
                        <tr>
                            <td><?php echo htmlspecialchars($cat['id_categoria']); ?></td>
                            <td><?php echo htmlspecialchars($cat['nombre']); ?></td>
                            <td><?php echo htmlspecialchars($cat['descripcion']); ?></td>
                            <td>
                                <span class="badge" style="background-color: <?php echo htmlspecialchars($cat['color'] ?? '#6c757d'); ?>; color: white;">
                                    <?php echo htmlspecialchars($cat['color'] ?? '#6c757d'); ?>
                                </span>
                            </td>
                            <td>
                                <button 
                                    type="button" 
                                    class="btn btn-sm btn-info me-2 text-white" 
                                    data-bs-toggle="modal" 
                                    data-bs-target="#editCategoryModal"
                                    data-id="<?php echo htmlspecialchars($cat['id_categoria']); ?>"
                                    data-nombre="<?php echo htmlspecialchars($cat['nombre']); ?>"
                                    data-descripcion="<?php echo htmlspecialchars($cat['descripcion']); ?>"
                                    data-color="<?php echo htmlspecialchars($cat['color'] ?? '#6c757d'); ?>"
                                    onclick="loadEditModal(this)"
                                >
                                    <i class="fas fa-edit"></i> Editar
                                </button>
                                
                                <form method="POST" action="admin_categorias.php" class="d-inline" onsubmit="return confirm('¿Está seguro de eliminar esta categoría? Si tiene tareas asignadas, la acción fallará.');">
                                    <input type="hidden" name="eliminar_categoria" value="1">
                                    <input type="hidden" name="id_categoria" value="<?php echo $cat['id_categoria']; ?>">
                                    <button type="submit" class="btn btn-sm btn-danger"><i class="fas fa-trash"></i> Eliminar</button>
                                </form>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                        <?php else: ?>
                        <tr>
                            <td colspan="5" class="text-center">No hay categorías registradas.</td>
                        </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <div class="modal fade" id="editCategoryModal" tabindex="-1" aria-labelledby="editCategoryModalLabel" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <form method="POST" action="admin_categorias.php">
                    <div class="modal-header bg-info text-white">
                        <h5 class="modal-title" id="editCategoryModalLabel"><i class="fas fa-edit me-1"></i> Editar Categoría</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <div class="modal-body">
                        <input type="hidden" name="modificar_categoria" value="1">
                        <input type="hidden" name="id_categoria" id="id_categoria_edit">
                        
                        <div class="mb-3">
                            <label for="nombre_edit" class="form-label">Nombre de la Categoría</label>
                            <input type="text" class="form-control" id="nombre_edit" name="nombre_edit" required maxlength="100">
                        </div>

                        <div class="mb-3">
                            <label for="descripcion_edit" class="form-label">Descripción</label>
                            <textarea class="form-control" id="descripcion_edit" name="descripcion_edit" rows="3" maxlength="255"></textarea>
                        </div>

                        <div class="mb-3">
                            <label for="color_edit" class="form-label">Color de Identificación (HEX)</label>
                            <input type="color" class="form-control form-control-color" id="color_edit" name="color_edit" value="#6c757d" title="Selecciona un color para identificar la categoría">
                            <small class="form-text text-muted">Este color se actualizará en las listas y el dashboard.</small>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                        <button type="submit" class="btn btn-info text-white"><i class="fas fa-save me-1"></i> Guardar Cambios</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
    /**
     * Rellena el modal de edición con los datos de la categoría seleccionada.
     * Es llamado por el onclick del botón "Editar".
     */
    function loadEditModal(button) {
        const id = button.getAttribute('data-id');
        const nombre = button.getAttribute('data-nombre');
        const descripcion = button.getAttribute('data-descripcion');
        const color = button.getAttribute('data-color');
        
        // Asignar valores a los campos del modal
        document.getElementById('id_categoria_edit').value = id;
        document.getElementById('nombre_edit').value = nombre;
        document.getElementById('descripcion_edit').value = descripcion;
        document.getElementById('color_edit').value = color;
        
        // Opcional: actualizar el título del modal
        const modalTitle = document.getElementById('editCategoryModalLabel');
        modalTitle.innerHTML = `<i class="fas fa-edit me-1"></i> Editar Categoría #${id} (${nombre})`;
    }
    
    // Asegurar que el modal se limpie al cerrarse si fuera necesario, aunque el submit lo recarga.
    const editModal = document.getElementById('editCategoryModal');
    if (editModal) {
        editModal.addEventListener('hidden.bs.modal', function (event) {
            document.getElementById('id_categoria_edit').value = '';
            document.getElementById('nombre_edit').value = '';
            document.getElementById('descripcion_edit').value = '';
            document.getElementById('color_edit').value = '#6c757d'; 
        });
    }

    </script>
    <div class="toast-container position-fixed bottom-0 end-0 p-3" id="notificationToastContainer" style="z-index: 1080;">
</div>
<?php include 'footer.php'; ?>
</body>
</html>