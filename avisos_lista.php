<?php
// Archivo: admin_remitos.php
session_start();
include 'conexion.php'; 
// 🛑 PASO CRÍTICO 1: DEBE INCLUIR ESTE ARCHIVO
include 'funciones_permisos.php'; 
// include 'navbar.php'; // Si tienes tu navbar aquí
// 2. Proteger la página
if (!isset($_SESSION['usuario_id']) || !tiene_permiso('acceso_avisos_gestionar', $pdo)) {
    $_SESSION['action_error_message'] = "No tiene permiso para gestionar avisos.";
    header("Location: avisos.php");
    exit();
}
$mensaje = '';
$alerta_tipo = '';

// --- Lógica de Manejo de Acciones (POST: Eliminar o Cambiar Estado) ---
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action'])) {
        $id_aviso = (int)($_POST['id_aviso'] ?? 0);
        
        try {
            if ($_POST['action'] === 'eliminar') {
                $sql = "DELETE FROM avisos WHERE id_aviso = :id_aviso";
                $stmt = $pdo->prepare($sql);
                $stmt->execute([':id_aviso' => $id_aviso]);
                $mensaje = "Aviso #{$id_aviso} eliminado exitosamente.";
                $alerta_tipo = 'success';

            } elseif ($_POST['action'] === 'toggle_status') {
                $current_status = (int)($_POST['current_status'] ?? 0);
                $new_status = $current_status === 1 ? 0 : 1;
                $sql = "UPDATE avisos SET es_activo = :new_status WHERE id_aviso = :id_aviso";
                $stmt = $pdo->prepare($sql);
                $stmt->execute([
                    ':new_status' => $new_status,
                    ':id_aviso' => $id_aviso
                ]);
                $estado_texto = $new_status === 1 ? 'activado' : 'desactivado';
                $mensaje = "Aviso #{$id_aviso} ha sido **{$estado_texto}** exitosamente.";
                $alerta_tipo = 'success';
            }

        } catch (PDOException $e) {
            $mensaje = 'Error al ejecutar la acción: ' . $e->getMessage();
            $alerta_tipo = 'danger';
            error_log("Error de DB en avisos_lista: " . $e->getMessage());
        }
    }
}

// --- Lógica para Obtener la Lista de Avisos (siempre se ejecuta después de cualquier POST) ---
$avisos = [];
try {
    $sql_avisos = "
        SELECT a.*, u.nombre_completo AS creador_nombre
        FROM avisos a
        JOIN usuarios u ON a.id_creador = u.id_usuario
        ORDER BY a.fecha_publicacion DESC
    ";
    $stmt_avisos = $pdo->query($sql_avisos);
    $avisos = $stmt_avisos->fetchAll(PDO::FETCH_ASSOC);

} catch (PDOException $e) {
    $mensaje = 'Error al cargar la lista de avisos: ' . $e->getMessage();
    $alerta_tipo = 'danger';
    error_log("Error de DB en avisos_lista (fetch): " . $e->getMessage());
}

?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Administrar Avisos Internos</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">
</head>
<body>
    <?php include 'navbar.php'; ?>

    <div class="container mt-5">
        <h1 class="mb-4"><i class="fas fa-bullhorn"></i> Administración de Avisos Internos</h1>
        
        <div class="d-flex justify-content-between align-items-center mb-4">
            <p class="text-muted mb-0">Listado completo de todos los avisos publicados e inactivos.</p>
            <a href="avisos_crear.php" class="btn btn-primary">
                <i class="fas fa-plus-circle"></i> Crear Nuevo Aviso
            </a>
        </div>

        <?php if ($mensaje): ?>
            <div class="alert alert-<?php echo $alerta_tipo; ?> alert-dismissible fade show" role="alert">
                <?php echo $mensaje; ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
        <?php endif; ?>

        <?php if (empty($avisos)): ?>
            <div class="alert alert-info text-center">
                No hay avisos internos registrados. ¡Cree el primero!
            </div>
        <?php else: ?>
            <div class="table-responsive">
                <table class="table table-hover table-striped shadow-sm">
                    <thead class="table-dark">
                        <tr>
                            <th>ID</th>
                            <th>Título</th>
                            <th>Creador</th>
                            <th>Publicación</th>
                            <th class="text-center">Estado</th>
                            <th class="text-center">Acciones</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($avisos as $aviso): ?>
                            <tr>
                                <td class="align-middle">#<?php echo htmlspecialchars($aviso['id_aviso']); ?></td>
                                <td class="align-middle fw-bold"><?php echo htmlspecialchars($aviso['titulo']); ?></td>
                                <td class="align-middle small text-muted"><?php echo htmlspecialchars($aviso['creador_nombre']); ?></td>
                                <td class="align-middle small"><?php echo date('d/m/Y H:i', strtotime($aviso['fecha_publicacion'])); ?></td>
                                
                                <td class="align-middle text-center">
                                    <?php 
                                        $estado_class = $aviso['es_activo'] ? 'bg-success' : 'bg-danger';
                                        $estado_text = $aviso['es_activo'] ? 'Activo' : 'Inactivo';
                                    ?>
                                    <span class="badge <?php echo $estado_class; ?>"><?php echo $estado_text; ?></span>
                                </td>

                                <td class="align-middle text-center">
                                    <div class="d-flex justify-content-center flex-wrap gap-2">
                                        <a href="avisos_editar.php?id=<?php echo $aviso['id_aviso']; ?>" class="btn btn-sm btn-info text-white" title="Editar Aviso">
                                            <i class="fas fa-edit"></i>
                                        </a>

                                        <form method="POST" class="d-inline" onsubmit="return confirm('¿Está seguro de cambiar el estado del aviso #<?php echo $aviso['id_aviso']; ?>?');">
                                            <input type="hidden" name="id_aviso" value="<?php echo $aviso['id_aviso']; ?>">
                                            <input type="hidden" name="action" value="toggle_status">
                                            <input type="hidden" name="current_status" value="<?php echo $aviso['es_activo']; ?>">
                                            <button type="submit" class="btn btn-sm <?php echo $aviso['es_activo'] ? 'btn-warning' : 'btn-success'; ?>" title="<?php echo $aviso['es_activo'] ? 'Desactivar Aviso' : 'Activar Aviso'; ?>">
                                                <i class="fas <?php echo $aviso['es_activo'] ? 'fa-eye-slash' : 'fa-eye'; ?>"></i>
                                            </button>
                                        </form>

                                        <form method="POST" class="d-inline" onsubmit="return confirm('ADVERTENCIA: ¿Está seguro de ELIMINAR permanentemente el aviso #<?php echo $aviso['id_aviso']; ?>?');">
                                            <input type="hidden" name="id_aviso" value="<?php echo $aviso['id_aviso']; ?>">
                                            <input type="hidden" name="action" value="eliminar">
                                            <button type="submit" class="btn btn-sm btn-danger" title="Eliminar Aviso">
                                                <i class="fas fa-trash-alt"></i>
                                            </button>
                                        </form>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>