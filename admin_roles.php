<?php
// Archivo: admin_roles.php (DISEÑO FINAL: Categorizado Visualmente + Lista Completa)
session_start();
include 'conexion.php';
include 'funciones_permisos.php';

// 1. Seguridad
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

// 2. Obtener Roles
try {
    $roles = $pdo->query("SELECT nombre_rol, descripcion FROM roles ORDER BY nombre_rol")->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) { $roles = []; }

// 3. Obtener Permisos y Categorizar
$permisos_por_categoria = [
    'Usuarios y Accesos' => [],
    'Tareas y Operaciones' => [],
    'Inventario y Stock' => [],
    'Personal y Asistencia' => [],
    'Comunicación' => [],
    'Configuración Sistema' => [],
    'Otros' => []
];

try {
    $sql_p = "SELECT clave_permiso, nombre_mostrar FROM permisos ORDER BY clave_permiso ASC";
    $todos_permisos = $pdo->query($sql_p)->fetchAll(PDO::FETCH_ASSOC);

    foreach ($todos_permisos as $p) {
        $k = strtolower($p['clave_permiso']);
        $n = strtolower($p['nombre_mostrar']);
        
        // Lógica de agrupación visual
        if (strpos($k, 'usuario')!==false || strpos($k, 'rol')!==false || strpos($k, 'login')!==false) {
            $permisos_por_categoria['Usuarios y Accesos'][] = $p;
        } elseif (strpos($k, 'tarea')!==false || strpos($k, 'pedido')!==false || strpos($k, 'remito')!==false || strpos($k, 'ascensor')!==false) {
            $permisos_por_categoria['Tareas y Operaciones'][] = $p;
        } elseif (strpos($k, 'inventario')!==false || strpos($k, 'stock')!==false || strpos($k, 'categoria')!==false) {
            $permisos_por_categoria['Inventario y Stock'][] = $p;
        } elseif (strpos($k, 'asistencia')!==false || strpos($k, 'personal')!==false) {
            $permisos_por_categoria['Personal y Asistencia'][] = $p;
        } elseif (strpos($k, 'chat')!==false || strpos($k, 'aviso')!==false || strpos($k, 'noticia')!==false) {
            $permisos_por_categoria['Comunicación'][] = $p;
        } elseif (strpos($k, 'admin')!==false || strpos($k, 'config')!==false || strpos($k, 'db')!==false) {
            $permisos_por_categoria['Configuración Sistema'][] = $p;
        } else {
            $permisos_por_categoria['Otros'][] = $p;
        }
    }
} catch (PDOException $e) { $todos_permisos = []; }

// 4. Cargar permisos del rol actual
$rol_seleccionado = $_GET['rol'] ?? ($roles[0]['nombre_rol'] ?? null);
$permisos_activos = [];
if ($rol_seleccionado) {
    try {
        $stmt = $pdo->prepare("SELECT clave_permiso FROM rol_permiso WHERE nombre_rol = ?");
        $stmt->execute([$rol_seleccionado]);
        $permisos_activos = $stmt->fetchAll(PDO::FETCH_COLUMN);
    } catch (Exception $e) {}
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Roles y Permisos</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        .card-permiso { height: 100%; border: 1px solid #e3e6f0; }
        .card-permiso .card-header { font-weight: bold; text-transform: uppercase; font-size: 0.85rem; }
        .switch-wrapper:hover { background-color: #f8f9fc; }
        .form-check-input:checked { background-color: #1cc88a; border-color: #1cc88a; }
    </style>
</head>
<body class="bg-light">
    <?php include 'navbar.php'; ?>
    <div class="container py-4">
        
        <?php if ($mensaje): ?>
            <div class="alert alert-<?php echo $alerta_tipo; ?> alert-dismissible fade show" role="alert">
                <?php echo $mensaje; ?> <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>

        <div class="row">
            <div class="col-lg-3 mb-4">
                <div class="card shadow-sm border-0">
                    <div class="card-header bg-dark text-white d-flex justify-content-between align-items-center">
                        <span class="fw-bold">ROLES</span>
                        <button class="btn btn-sm btn-light py-0" data-bs-toggle="modal" data-bs-target="#crearRolModal">+</button>
                    </div>
                    <div class="list-group list-group-flush">
                        <?php foreach($roles as $r): 
                            $act = ($r['nombre_rol'] === $rol_seleccionado) ? 'active bg-primary border-primary' : ''; 
                        ?>
                        <a href="?rol=<?php echo urlencode($r['nombre_rol']); ?>" class="list-group-item list-group-item-action d-flex justify-content-between align-items-center <?php echo $act; ?>">
                            <div class="overflow-hidden">
                                <div class="fw-bold"><?php echo ucfirst($r['nombre_rol']); ?></div>
                                <small class="opacity-75 text-truncate d-block" style="font-size:0.75rem;"><?php echo $r['descripcion']; ?></small>
                            </div>
                            <?php if($r['nombre_rol'] !== 'admin' && !$act): ?>
                                <span class="badge bg-secondary rounded-pill" style="font-size:0.6rem"><i class="fas fa-edit"></i></span>
                            <?php endif; ?>
                        </a>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>

            <div class="col-lg-9">
                <?php if($rol_seleccionado): ?>
                <form method="POST" action="admin_roles_procesar_permisos.php">
                    <input type="hidden" name="rol_nombre" value="<?php echo htmlspecialchars($rol_seleccionado); ?>">
                    
                    <div class="d-flex justify-content-between align-items-center mb-3">
                        <h4 class="mb-0 text-dark">Permisos de: <span class="text-primary fw-bold text-uppercase"><?php echo $rol_seleccionado; ?></span></h4>
                        <button type="submit" class="btn btn-success shadow-sm px-4"><i class="fas fa-save me-2"></i>Guardar Cambios</button>
                    </div>

                    <?php if($rol_seleccionado === 'admin'): ?>
                        <div class="alert alert-warning"><i class="fas fa-crown me-2"></i> El rol <b>admin</b> tiene acceso total siempre. Estos interruptores son solo visuales.</div>
                    <?php endif; ?>

                    <div class="row g-3">
                        <?php foreach($permisos_por_categoria as $cat => $lista): if(empty($lista)) continue; ?>
                        <div class="col-md-6 col-xl-4">
                            <div class="card card-permiso shadow-sm">
                                <div class="card-header bg-white text-secondary border-bottom">
                                    <?php echo $cat; ?>
                                </div>
                                <div class="card-body p-0">
                                    <?php foreach($lista as $p): 
                                        $chk = in_array($p['clave_permiso'], $permisos_activos) ? 'checked' : '';
                                    ?>
                                    <div class="switch-wrapper px-3 py-2 border-bottom d-flex align-items-center justify-content-between">
                                        <label class="form-check-label small w-100 cursor-pointer" for="perm_<?php echo $p['clave_permiso']; ?>">
                                            <div class="fw-bold text-dark"><?php echo $p['nombre_mostrar']; ?></div>
                                            <div class="text-muted" style="font-size:0.7rem;"><?php echo $p['clave_permiso']; ?></div>
                                        </label>
                                        <div class="form-check form-switch ms-2">
                                            <input class="form-check-input" type="checkbox" name="permisos_seleccionados[]" value="<?php echo $p['clave_permiso']; ?>" id="perm_<?php echo $p['clave_permiso']; ?>" <?php echo $chk; ?>>
                                        </div>
                                    </div>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                    
                    <div class="mt-4 text-end">
                        <?php if($rol_seleccionado !== 'admin'): ?>
                        <button type="button" class="btn btn-outline-danger btn-sm float-start" onclick="borrarRol('<?php echo $rol_seleccionado; ?>')"><i class="fas fa-trash me-1"></i> Eliminar Rol</button>
                        <?php endif; ?>
                        <button type="submit" class="btn btn-success btn-lg px-5 shadow"><i class="fas fa-save me-2"></i>Guardar Cambios</button>
                    </div>
                </form>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <div class="modal fade" id="crearRolModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <form action="admin_roles_crear.php" method="POST">
                    <div class="modal-header bg-success text-white"><h5 class="modal-title">Nuevo Rol</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
                    <div class="modal-body">
                        <div class="mb-3"><label>ID Rol (sin espacios):</label><input type="text" name="nombre_rol" class="form-control" required pattern="[a-z0-9_]+"></div>
                        <div class="mb-3"><label>Descripción:</label><input type="text" name="descripcion_rol" class="form-control" required></div>
                    </div>
                    <div class="modal-footer"><button type="submit" class="btn btn-success">Crear</button></div>
                </form>
            </div>
        </div>
    </div>
    
    <form id="formBorrar" action="admin_roles_eliminar.php" method="POST"><input type="hidden" name="rol_a_eliminar" id="rolBorrarInput"></form>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <script> function borrarRol(r){ if(confirm('¿Eliminar rol '+r+'?')) { document.getElementById('rolBorrarInput').value=r; document.getElementById('formBorrar').submit(); } } </script>
    <?php include 'footer.php'; ?>
</body>
</html>