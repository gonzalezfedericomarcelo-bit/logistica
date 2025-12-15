<?php
// Archivo: admin_roles.php (MEJORADO: Agrupación inteligente y gestión total)
session_start();
include 'conexion.php';
include 'funciones_permisos.php';

// 1. PROTEGER LA PÁGINA
// Solo el rol 'admin' o quien tenga el permiso explícito 'admin_roles' puede entrar aquí.
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

// 3. OBTENER Y AGRUPAR TODOS LOS PERMISOS
// Esta lógica ahora es más flexible. Busca palabras clave en lugar de frases exactas.
try {
    $permisos_sql = "SELECT clave_permiso, nombre_mostrar FROM permisos ORDER BY clave_permiso";
    $permisos_data = $pdo->query($permisos_sql)->fetchAll(PDO::FETCH_ASSOC);

    // Estructura de grupos
    $permisos_agrupados = [
        'Gestión de Avisos' => [],
        'Gestión de Tareas' => [],
        'Gestión de Pedidos y Remitos' => [],
        'Gestión de Usuarios y Roles' => [],
        'Personal y Asistencia' => [],
        'Acceso General y Tablero' => [],
        'Otras Configuraciones' => [],
    ];

    foreach ($permisos_data as $p) {
        $clave = strtolower($p['clave_permiso']); // Convertimos a minúsculas para comparar
        
        // Lógica de Agrupación Inteligente
        if (strpos($clave, 'aviso') !== false) {
            $permisos_agrupados['Gestión de Avisos'][] = $p;
        } 
        elseif (strpos($clave, 'tarea') !== false) {
            $permisos_agrupados['Gestión de Tareas'][] = $p;
        } 
        elseif (strpos($clave, 'pedido') !== false || strpos($clave, 'remito') !== false || strpos($clave, 'factura') !== false) {
            $permisos_agrupados['Gestión de Pedidos y Remitos'][] = $p;
        } 
        elseif (strpos($clave, 'usuario') !== false || strpos($clave, 'rol') !== false) {
            $permisos_agrupados['Gestión de Usuarios y Roles'][] = $p;
        }
        elseif (strpos($clave, 'asistencia') !== false || strpos($clave, 'personal') !== false) {
            $permisos_agrupados['Personal y Asistencia'][] = $p;
        }
        elseif (strpos($clave, 'dashboard') !== false || strpos($clave, 'perfil') !== false || strpos($clave, 'chat') !== false) {
            $permisos_agrupados['Acceso General y Tablero'][] = $p;
        } 
        elseif (strpos($clave, 'inventario') !== false) {
            $permisos_agrupados['Gestión de Inventario'][] = $p;
        }
        else {
             // Si no coincide con nada, va a "Otras"
             $permisos_agrupados['Otras Configuraciones'][] = $p;
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
        .permisos-card { flex: 1 1 300px; }
        .list-group-item:hover .action-buttons { visibility: visible; }
        .action-buttons { visibility: hidden; }
        .card-header.bg-secondary { background-color: #495057 !important; }
        
        /* Estilo visual para los switches */
        .form-check-input:checked {
            background-color: #198754;
            border-color: #198754;
        }
    </style>
</head>
<body>
    <?php include 'navbar.php'; ?>

    <div class="container mt-4">
        <h1 class="mb-4"><i class="fas fa-user-shield me-2"></i> Configuración de Roles</h1>
        
        <?php if ($mensaje): ?>
            <div class="alert alert-<?php echo $alerta_tipo; ?> alert-dismissible fade show shadow-sm" role="alert">
                <?php echo htmlspecialchars($mensaje); ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
        <?php endif; ?>

        <div class="row mb-4">
            <div class="col-12">
                <button type="button" class="btn btn-success shadow-sm" data-bs-toggle="modal" data-bs-target="#crearRolModal">
                    <i class="fas fa-plus-circle me-2"></i> Crear Nuevo Rol
                </button>
            </div>
        </div>

        <div class="row">
            <div class="col-md-3 mb-4">
                <div class="list-group shadow-sm" id="roleList">
                    <div class="list-group-item active bg-dark border-dark fw-bold">Roles Disponibles</div>
                    <?php if (count($roles) > 0): ?>
                        <?php foreach ($roles as $rol): ?>
                            <?php 
                            $rol_name = htmlspecialchars($rol['nombre_rol']); 
                            $rol_desc = htmlspecialchars($rol['descripcion'] ?? 'Sin descripción');
                            $is_active = ($rol_name === $rol_seleccionado) ? 'active' : '';
                            $is_admin = ($rol_name === 'admin');
                            // Estos roles por defecto tienen advertencia al borrar, pero se pueden editar sus permisos
                            $is_default = in_array($rol_name, ['empleado', 'auxiliar', 'encargado']);
                            ?>
                            <a href="?rol=<?php echo urlencode($rol_name); ?>" 
                               class="list-group-item list-group-item-action d-flex justify-content-between align-items-center <?php echo $is_active; ?>">
                                
                                <div>
                                    <h6 class="mb-0 text-capitalize fw-bold"><?php echo $rol_name; ?></h6>
                                    <small class="text-muted d-block text-truncate" style="max-width: 120px;"><?php echo $rol_desc; ?></small>
                                </div>
                                
                                <div class="action-buttons text-nowrap">
                                    <?php if (!$is_admin): ?>
                                        <button type="button" class="btn btn-sm btn-outline-primary border-0" 
                                                data-bs-toggle="modal" data-bs-target="#editarRolModal"
                                                data-rol-name="<?php echo $rol_name; ?>" 
                                                data-rol-desc="<?php echo $rol_desc; ?>" 
                                                onclick="loadEditRolModal(this); event.preventDefault();"
                                                title="Editar descripción">
                                            <i class="fas fa-edit"></i>
                                        </button>
                                        
                                        <button type="button" class="btn btn-sm btn-outline-danger border-0" 
                                                onclick="confirmDeleteRol('<?php echo $rol_name; ?>', <?php echo $is_default ? 'true' : 'false'; ?>); event.preventDefault();"
                                                title="Eliminar Rol">
                                            <i class="fas fa-trash"></i>
                                        </button>
                                    <?php else: ?>
                                        <span class="badge bg-secondary"><i class="fas fa-lock"></i></span>
                                    <?php endif; ?>
                                </div>
                            </a>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <div class="list-group-item text-center text-danger">No hay roles creados.</div>
                    <?php endif; ?>
                </div>
            </div>

            <div class="col-md-9">
                <?php if ($rol_seleccionado): ?>
                    <div class="card shadow-sm border-0">
                        <div class="card-header bg-secondary text-white d-flex justify-content-between align-items-center"> 
                            <h5 class="mb-0 text-capitalize">Permisos para: <strong><?php echo $rol_seleccionado; ?></strong></h5>
                            <small>Selecciona qué puede hacer este rol</small>
                        </div>
                        <div class="card-body bg-light">
                            
                            <?php if ($rol_seleccionado === 'admin'): ?>
                                <div class="alert alert-warning border-warning">
                                    <i class="fas fa-exclamation-triangle me-2"></i>
                                    <strong>Nota:</strong> El rol <b>admin</b> tiene acceso total al sistema. Los cambios aquí son visuales pero no limitarán al super-administrador.
                                </div>
                            <?php endif; ?>

                            <form method="POST" action="admin_roles_procesar_permisos.php">
                                <input type="hidden" name="rol_nombre" value="<?php echo htmlspecialchars($rol_seleccionado); ?>">

                                <div class="permisos-container">
                                    <?php foreach ($permisos_agrupados as $grupo => $permisos): ?>
                                        <?php if (!empty($permisos)): ?>
                                            <div class="card mb-3 shadow-sm">
                                                <div class="card-header bg-white fw-bold text-primary">
                                                    <?php echo htmlspecialchars($grupo); ?>
                                                </div>
                                                <div class="card-body p-0">
                                                    <ul class="list-group list-group-flush">
                                                        <?php foreach ($permisos as $permiso): ?>
                                                            <li class="list-group-item d-flex justify-content-between align-items-center action-row">
                                                                <div>
                                                                    <label class="form-check-label fw-semibold text-dark" for="permiso_<?php echo htmlspecialchars($permiso['clave_permiso']); ?>">
                                                                        <?php echo htmlspecialchars($permiso['nombre_mostrar']); ?>
                                                                    </label>
                                                                    <br>
                                                                    <small class="text-muted fst-italic" style="font-size: 0.75rem;">Clave: <?php echo htmlspecialchars($permiso['clave_permiso']); ?></small>
                                                                </div>
                                                                
                                                                <div class="form-check form-switch">
                                                                    <input class="form-check-input" type="checkbox" role="switch"
                                                                        name="permisos_seleccionados[]" 
                                                                        value="<?php echo htmlspecialchars($permiso['clave_permiso']); ?>"
                                                                        id="permiso_<?php echo htmlspecialchars($permiso['clave_permiso']); ?>"
                                                                        <?php echo in_array($permiso['clave_permiso'], $permisos_actuales) ? 'checked' : ''; ?>>
                                                                </div>
                                                            </li>
                                                        <?php endforeach; ?>
                                                    </ul>
                                                </div>
                                            </div>
                                        <?php endif; ?>
                                    <?php endforeach; ?>
                                </div>

                                <div class="d-grid gap-2 d-md-flex justify-content-md-end mt-4 mb-3">
                                    <button type="submit" class="btn btn-success btn-lg px-5 shadow">
                                        <i class="fas fa-save me-2"></i> Guardar Permisos
                                    </button>
                                </div>
                            </form>
                        </div>
                    </div>
                <?php else: ?>
                    <div class="alert alert-info text-center p-5 shadow-sm">
                        <h4><i class="fas fa-arrow-left me-2"></i> Selecciona un rol</h4>
                        <p>Haz clic en un rol de la lista izquierda para ver y editar sus permisos.</p>
                    </div>
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
                            <label for="nombre_rol" class="form-label">Nombre del Rol (ID)</label>
                            <input type="text" class="form-control" id="nombre_rol" name="nombre_rol" required maxlength="50" pattern="[a-zA-Z0-9_]+" title="Solo letras minúsculas, números y guiones bajos." oninput="this.value = this.value.toLowerCase().replace(/[^a-z0-9_]/g, '')">
                            <div class="form-text">Ej: supervisor_ventas. Sin espacios, solo minúsculas.</div>
                        </div>
                        <div class="mb-3">
                            <label for="descripcion_rol" class="form-label">Descripción Visible</label>
                            <input type="text" class="form-control" id="descripcion_rol" name="descripcion_rol" maxlength="255" required>
                            <div class="form-text">Ej: Supervisor de Ventas (Tiene acceso a reportes)</div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                        <button type="submit" class="btn btn-success">Crear</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    
    <div class="modal fade" id="editarRolModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header bg-primary text-white">
                    <h5 class="modal-title">Editar Descripción</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <form method="POST" action="admin_roles_editar.php">
                    <div class="modal-body">
                        <input type="hidden" name="rol_original" id="rol_original">
                        <div class="mb-3">
                            <label class="form-label">Rol (ID)</label>
                            <input type="text" class="form-control bg-light" id="nombre_rol_edit" disabled readonly>
                        </div>
                        <div class="mb-3">
                            <label for="descripcion_rol_edit" class="form-label">Descripción</label>
                            <input type="text" class="form-control" id="descripcion_rol_edit" name="descripcion_rol_edit" maxlength="255" required>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                        <button type="submit" class="btn btn-primary">Guardar Cambios</button>
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
        function loadEditRolModal(button) {
            const rolName = button.getAttribute('data-rol-name');
            const rolDesc = button.getAttribute('data-rol-desc');
            
            document.getElementById('rol_original').value = rolName;
            document.getElementById('nombre_rol_edit').value = rolName;
            document.getElementById('descripcion_rol_edit').value = rolDesc;
        }

        function confirmDeleteRol(rolName, isDefault) {
            if (rolName === 'admin') {
                alert('El rol admin no se puede eliminar.');
                return;
            }
            let msg = `¿Eliminar el rol '${rolName}'?`;
            if (isDefault) {
                msg += "\n\nATENCIÓN: Es un rol predeterminado del sistema. Asegúrate de que no haya usuarios usándolo.";
            }
            msg += "\n\nEsta acción es irreversible.";

            if (confirm(msg)) {
                document.getElementById('rol_a_eliminar').value = rolName;
                document.getElementById('deleteRolForm').submit();
            }
        }
    </script>
    <?php include 'footer.php'; ?>
</body>
</html>