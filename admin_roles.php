<?php
// Archivo: admin_roles.php (VERSION CON CRUD y COLORES NEUTROS)
session_start();
include 'conexion.php';
include 'funciones_permisos.php';

// 1. PROTEGER LA PÁGINA
if (!isset($_SESSION['usuario_id']) || !tiene_permiso('admin_roles', $pdo)) {
    header("Location: dashboard.php");
    exit();
}

$mensaje = '';
$alerta_tipo = '';

if (isset($_SESSION['admin_roles_mensaje'])) {
    $mensaje = $_SESSION['admin_roles_mensaje'];
    $alerta_tipo = $_SESSION['admin_roles_alerta'] ?? 'info';
    unset($_SESSION['admin_roles_mensaje']);
    unset($_SESSION['admin_roles_alerta']);
}

// 2. OBTENER TODOS LOS ROLES DISPONIBLES
try {
    $roles = $pdo->query("SELECT nombre_rol, descripcion FROM roles ORDER BY nombre_rol")->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $mensaje = "Error al cargar roles: " . $e->getMessage();
    $alerta_tipo = 'danger';
    $roles = [];
}

// 3. OBTENER TODOS LOS PERMISOS DEFINIDOS
try {
    // Agrupación manual para la UI
    $permisos_sql = "SELECT clave_permiso, nombre_mostrar FROM permisos ORDER BY clave_permiso";
    $permisos_data = $pdo->query($permisos_sql)->fetchAll(PDO::FETCH_ASSOC);

    // ESTRUCTURA DE AGRUPACIÓN ACTUALIZADA
    $permisos_agrupados = [
        'Acceso Básico y Perfil' => [],
        'Pedidos y Documentos' => [],
        'Tareas y Logística' => [],
        'Módulo de Avisos' => [],
        'Configuración del Sistema (CRUD)' => [], 
        'Configuración General (Listados)' => [],
    ];

    foreach ($permisos_data as $p) {
        $clave = $p['clave_permiso'];
        
        // Criterio de Agrupación
        if (strpos($clave, 'acceso_dashboard') !== false || strpos($clave, 'acceso_perfil_php') !== false || $clave === 'ver_chat') {
            $permisos_agrupados['Acceso Básico y Perfil'][] = $p;
        } 
        // --- MODIFICADO: Agrupa los nuevos permisos de Pedidos ---
        elseif (strpos($clave, 'acceso_pedidos_') !== false || $clave === 'admin_remitos') {
            $permisos_agrupados['Pedidos y Documentos'][] = $p;
        }
        elseif (strpos($clave, 'acceso_tareas_') !== false || $clave === 'crear_tarea_directa') {
            $permisos_agrupados['Tareas y Logística'][] = $p;
        } 
        // --- MODIFICADO: Agrupa los nuevos permisos de Avisos ---
        elseif (strpos($clave, 'acceso_avisos') !== false) {
            $permisos_agrupados['Módulo de Avisos'][] = $p;
        } 
        elseif (in_array($clave, ['admin_usuarios', 'admin_roles'])) {
            $permisos_agrupados['Configuración del Sistema (CRUD)'][] = $p;
        } 
        elseif (strpos($clave, 'admin_') !== false) {
            $permisos_agrupados['Configuración General (Listados)'][] = $p;
        } 
        else {
             // Fallback para permisos no clasificados
             $permisos_agrupados['Configuración General (Listados)'][] = $p;
        }
    }

} catch (PDOException $e) {
    $mensaje = "Error al cargar permisos: " . $e->getMessage();
    $alerta_tipo = 'danger';
    $permisos_agrupados = [];
}


// 4. LÓGICA PARA CARGAR PERMISOS DEL ROL SELECCIONADO
$rol_seleccionado = $_GET['rol'] ?? ($roles[0]['nombre_rol'] ?? null); 

$permisos_actuales = [];
if ($rol_seleccionado) {
    try {
        $sql_permisos = "SELECT clave_permiso FROM rol_permiso WHERE nombre_rol = :rol";
        $stmt_permisos = $pdo->prepare($sql_permisos);
        $stmt_permisos->bindParam(':rol', $rol_seleccionado);
        $stmt_permisos->execute();
        
        $permisos_actuales = $stmt_permisos->fetchAll(PDO::FETCH_COLUMN, 0); 

    } catch (PDOException $e) {
        $permisos_actuales = [];
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gestión de Roles y Permisos | Admin</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        .permisos-card-group { display: flex; flex-wrap: wrap; gap: 15px; }
        .permisos-card { flex: 1 1 300px; /* Base width */ }
        .list-group-item:hover .action-buttons { visibility: visible; }
        .action-buttons { visibility: hidden; }
        /* AJUSTE DE COLOR: primary pasa a secondary, info o dark */
        .card-header.bg-primary { background-color: var(--bs-secondary) !important; color: white !important; }
        .border-info { border-color: var(--bs-info) !important; }
    </style>
</head>
<body>
    <?php include 'navbar.php'; ?>

    <div class="container mt-4">
        <h1 class="mb-4"><i class="fas fa-user-shield me-2"></i> Gestión de Roles y Permisos</h1>
        
        <?php if ($mensaje): ?>
            <div class="alert alert-<?php echo $alerta_tipo; ?> alert-dismissible fade show" role="alert">
                <?php echo htmlspecialchars($mensaje); ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
        <?php endif; ?>

        <div class="row mb-3">
            <div class="col-12">
                <button type="button" class="btn btn-success" data-bs-toggle="modal" data-bs-target="#crearRolModal">
                    <i class="fas fa-plus me-1"></i> Crear Nuevo Rol
                </button>
            </div>
        </div>

        <div class="row">
            <div class="col-md-3">
                <div class="list-group shadow-sm" id="roleList">
                    <div class="list-group-item active bg-dark border-dark">Roles del Sistema</div>
                    <?php if (count($roles) > 0): ?>
                        <?php foreach ($roles as $rol): ?>
                            <?php 
                            $rol_name = htmlspecialchars($rol['nombre_rol']); 
                            $rol_desc = htmlspecialchars($rol['descripcion'] ?? 'Sin descripción');
                            $is_active = ($rol_name === $rol_seleccionado) ? 'active' : '';
                            $is_admin = ($rol_name === 'admin');
                            $is_default = in_array($rol_name, ['empleado', 'auxiliar', 'encargado']);
                            ?>
                            <a href="?rol=<?php echo urlencode($rol_name); ?>" 
                               class="list-group-item list-group-item-action d-flex justify-content-between align-items-center <?php echo $is_active; ?>">
                                
                                <div>
                                    <h6 class="mb-0 text-capitalize"><?php echo $rol_name; ?></h6>
                                    <small class="text-muted"><?php echo $rol_desc; ?></small>
                                </div>
                                
                                <div class="action-buttons text-nowrap">
                                    <?php if (!$is_admin): ?>
                                        <button type="button" class="btn btn-sm btn-info text-white me-1" 
                                                data-bs-toggle="modal" data-bs-target="#editarRolModal"
                                                data-rol-name="<?php echo $rol_name; ?>" 
                                                data-rol-desc="<?php echo $rol_desc; ?>" 
                                                onclick="loadEditRolModal(this)"
                                                title="Editar Rol">
                                            <i class="fas fa-edit"></i>
                                        </button>
                                        
                                        <button type="button" class="btn btn-sm btn-danger" 
                                                onclick="confirmDeleteRol('<?php echo $rol_name; ?>', <?php echo $is_default ? 'true' : 'false'; ?>)"
                                                title="Eliminar Rol">
                                            <i class="fas fa-trash"></i>
                                        </button>
                                    <?php else: ?>
                                        <span class="badge bg-danger">Sistema</span>
                                    <?php endif; ?>
                                </div>
                            </a>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <div class="list-group-item text-center text-danger">No se encontraron roles.</div>
                    <?php endif; ?>
                </div>
            </div>

            <div class="col-md-9">
                <?php if ($rol_seleccionado): ?>
                    <div class="card shadow">
                        <div class="card-header bg-secondary text-white"> 
                            <h4 class="mb-0 text-capitalize">Permisos para el Rol: "<?php echo $rol_seleccionado; ?>"</h4>
                            <?php if ($rol_seleccionado === 'admin'): ?>
                                <p class="mb-0 small bg-warning p-1 rounded text-dark mt-2">⚠️ El rol **admin** siempre tiene acceso a **TODOS** los permisos.</p>
                            <?php endif; ?>
                        </div>
                        <div class="card-body">
                            <form method="POST" action="admin_roles_procesar_permisos.php">

                             <input type="hidden" name="rol_nombre" value="<?php echo htmlspecialchars($rol_seleccionado); ?>">

    
                            <input type="checkbox" name="permisos_seleccionados[]" value="<?php echo htmlspecialchars($permiso['clave_permiso']); ?>" ... >
                            <input type="hidden" name="rol_nombre" value="<?php echo htmlspecialchars($rol_seleccionado); ?>">

                            <?php foreach ($permisos_agrupados as $grupo => $permisos): ?>
                                <div class="card permisos-card border-secondary mb-3">
                                    <ul class="list-group list-group-flush">
                                        <?php foreach ($permisos as $permiso): ?>
                                            <li class="list-group-item d-flex justify-content-between align-items-center">
                                                <div class="form-check">
                                                    <input class="form-check-input" type="checkbox"
                                                        name="permisos_seleccionados[]" 
                                                        value="<?php echo htmlspecialchars($permiso['clave_permiso']); ?>"
                                                        id="permiso_<?php echo htmlspecialchars($permiso['clave_permiso']); ?>"
                                                        <?php echo in_array($permiso['clave_permiso'], $permisos_actuales) ? 'checked' : ''; ?>>
                                                    <label class="form-check-label" for="permiso_<?php echo htmlspecialchars($permiso['clave_permiso']); ?>">
                                                        <?php echo htmlspecialchars($permiso['nombre_mostrar']); ?>
                                                    </label>
                                                </div>
                                            </li>
                                        <?php endforeach; ?>
                                    </ul>
                                </div>
                            <?php endforeach; ?>

                            <div class="text-end mt-4">
                                <button type="submit" class="btn btn-success btn-lg"><i class="fas fa-save"></i> Guardar Cambios de Permisos</button>
                            </div>
</form>
                        </div>
                    </div>
                <?php else: ?>
                    <div class="alert alert-info text-center">Por favor, cree un rol o seleccione uno de la lista para gestionar sus permisos.</div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <div class="modal fade" id="crearRolModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header bg-success text-white">
                    <h5 class="modal-title">Crear Nuevo Rol</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <form method="POST" action="admin_roles_crear.php">
                    <div class="modal-body">
                        <div class="mb-3">
                            <label for="nombre_rol" class="form-label">Nombre del Rol (Clave)</label>
                            <input type="text" class="form-control" id="nombre_rol" name="nombre_rol" required maxlength="50" pattern="[a-zA-Z0-9_]+" title="Solo letras, números y guiones bajos." oninput="this.value = this.value.toLowerCase()">
                            <small class="text-muted">Ej: encargado, supervisor_logistica. Solo minúsculas, sin espacios.</small>
                        </div>
                        <div class="mb-3">
                            <label for="descripcion_rol" class="form-label">Descripción</label>
                            <input type="text" class="form-control" id="descripcion_rol" name="descripcion_rol" maxlength="255">
                            <small class="text-muted">Breve descripción para identificar el rol.</small>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                        <button type="submit" class="btn btn-success"><i class="fas fa-plus"></i> Crear Rol</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    
    <div class="modal fade" id="editarRolModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header bg-info text-white">
                    <h5 class="modal-title">Editar Rol: <span id="rol_actual_title"></span></h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <form method="POST" action="admin_roles_editar.php">
                    <div class="modal-body">
                        <input type="hidden" name="rol_original" id="rol_original">
                        <div class="mb-3">
                            <label for="nombre_rol_edit" class="form-label">Nombre del Rol (Clave)</label>
                            <input type="text" class="form-control" id="nombre_rol_edit" name="nombre_rol_edit" required readonly disabled>
                            <small class="text-danger">El nombre clave del rol no puede ser modificado.</small>
                        </div>
                        <div class="mb-3">
                            <label for="descripcion_rol_edit" class="form-label">Descripción</label>
                            <input type="text" class="form-control" id="descripcion_rol_edit" name="descripcion_rol_edit" maxlength="255">
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

    <form id="deleteRolForm" method="POST" action="admin_roles_eliminar.php">
        <input type="hidden" name="rol_a_eliminar" id="rol_a_eliminar">
    </form>


    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Función para cargar los datos en el modal de edición
        function loadEditRolModal(button) {
            const rolName = button.getAttribute('data-rol-name');
            const rolDesc = button.getAttribute('data-rol-desc');
            
            document.getElementById('rol_actual_title').textContent = rolName;
            document.getElementById('rol_original').value = rolName;
            document.getElementById('nombre_rol_edit').value = rolName;
            document.getElementById('descripcion_rol_edit').value = rolDesc;
        }

        // Función para confirmar la eliminación de un rol
        function confirmDeleteRol(rolName, isDefault) {
            if (rolName === 'admin') {
                alert('No se puede eliminar el rol "admin" por seguridad del sistema.');
                return;
            }
            if (isDefault) {
                alert('Advertencia: Este es un rol por defecto ("empleado", "auxiliar", "encargado"). Eliminarlo puede causar problemas si hay usuarios asignados. Solo se recomienda eliminar roles personalizados.');
            }

            if (confirm(`ADVERTENCIA: ¿Está seguro de ELIMINAR el rol '${rolName}'? Todos los usuarios asignados a este rol serán reasignados a 'empleado'. ESTA ACCIÓN NO SE PUEDE DESHACER.`)) {
                document.getElementById('rol_a_eliminar').value = rolName;
                document.getElementById('deleteRolForm').submit();
            }
        }
    </script>
</body>
</html>