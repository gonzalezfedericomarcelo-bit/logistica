<?php
// Archivo: admin_usuarios.php (CORREGIDO: Carga dinámica de roles)
session_start();
include 'conexion.php';
include 'funciones_permisos.php'; 

// 1. Proteger la página
if (!isset($_SESSION['usuario_id']) || !tiene_permiso('admin_usuarios', $pdo)) {
    header("Location: dashboard.php");
    exit();
}
$mensaje = '';
$alerta_tipo = '';

// --- Leer mensaje de sesión ---
if (isset($_SESSION['admin_usuarios_mensaje'])) {
    $mensaje = $_SESSION['admin_usuarios_mensaje'];
    $alerta_tipo = $_SESSION['admin_usuarios_alerta'] ?? 'info';
    unset($_SESSION['admin_usuarios_mensaje']); 
    unset($_SESSION['admin_usuarios_alerta']);
}

// 2. Lógica CREAR USUARIO
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['crear_usuario'])) {
    $nombre_completo = trim($_POST['nombre_completo']); 
    $usuario = trim($_POST['usuario']); 
    $password = $_POST['password']; 
    $email = trim($_POST['email'] ?? ''); 
    $telefono = trim($_POST['telefono'] ?? ''); 
    $genero = strtolower(trim($_POST['genero'] ?? 'otro')); 
    $grado = trim($_POST['grado'] ?? ''); 
    $rol = 'empleado'; // Por defecto al crear
    
    $password_hashed = password_hash($password, PASSWORD_DEFAULT);
    try {
        $sql = "INSERT INTO usuarios (nombre_completo, usuario, password, rol, email, telefono, genero, grado, activo) VALUES (:nombre_completo, :usuario, :password_hashed, :rol, :email, :telefono, :genero, :grado, 1)";
        $stmt = $pdo->prepare($sql);
        $stmt->bindParam(':nombre_completo', $nombre_completo); 
        $stmt->bindParam(':usuario', $usuario); 
        $stmt->bindParam(':password_hashed', $password_hashed); 
        $stmt->bindParam(':rol', $rol); 
        $stmt->bindParam(':email', $email); 
        $stmt->bindParam(':telefono', $telefono); 
        $stmt->bindParam(':genero', $genero);
        $stmt->bindParam(':grado', $grado); 
        
        if ($stmt->execute()) { $mensaje = "El usuario '$usuario' ha sido creado exitosamente."; $alerta_tipo = 'success'; }
        else { $mensaje = "Error desconocido al crear el usuario."; $alerta_tipo = 'danger'; }
    } catch (PDOException $e) { if ($e->getCode() == '23000') { $mensaje = "Error: El Nombre de Usuario o Email ya está registrado."; } else { $mensaje = "Error al crear el usuario: " . $e->getMessage(); error_log("Error al crear usuario: " . $e->getMessage()); } $alerta_tipo = 'danger'; }
}

// 3. Lógica ELIMINAR USUARIO
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['eliminar_usuario'])) {
    $id_usuario_eliminar = (int)$_POST['id_usuario'];
    if ($id_usuario_eliminar === $_SESSION['usuario_id']) { $mensaje = "No puede eliminar su propia cuenta."; $alerta_tipo = 'danger'; }
    else { try { $pdo->beginTransaction(); $sql_transfer_tasks = "UPDATE tareas SET id_asignado = NULL WHERE id_asignado = :id_usuario"; $stmt_transfer_tasks = $pdo->prepare($sql_transfer_tasks); $stmt_transfer_tasks->bindParam(':id_usuario', $id_usuario_eliminar); $stmt_transfer_tasks->execute(); $sql_delete = "DELETE FROM usuarios WHERE id_usuario = :id_usuario"; $stmt_delete = $pdo->prepare($sql_delete); $stmt_delete->bindParam(':id_usuario', $id_usuario_eliminar); $stmt_delete->execute(); $pdo->commit(); $mensaje = "Usuario ID #{$id_usuario_eliminar} eliminado. Sus tareas asignadas ahora no tienen responsable."; $alerta_tipo = 'success'; } catch (PDOException $e) { $pdo->rollBack(); $mensaje = "Error al eliminar el usuario: " . $e->getMessage(); $alerta_tipo = 'danger'; error_log("Error al eliminar usuario: " . $e->getMessage()); } }
}

// 4. OBTENER LISTADO DE USUARIOS
try {
    $sql_empleados = "SELECT id_usuario, nombre_completo, usuario, email, telefono, genero, activo, rol, grado 
                      FROM usuarios 
                      WHERE id_usuario != :admin_id 
                      ORDER BY nombre_completo";
    $stmt_empleados = $pdo->prepare($sql_empleados);
    $stmt_empleados->bindParam(':admin_id', $_SESSION['usuario_id']);
    $stmt_empleados->execute();
    $usuarios_empleados = $stmt_empleados->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) { error_log("Error al cargar lista de usuarios: " . $e->getMessage()); $usuarios_empleados = []; $mensaje = "Error crítico al cargar la lista de usuarios: " . $e->getMessage(); $alerta_tipo = 'danger'; }

// 5. OBTENER LISTA DE ROLES (NUEVO: Dinámico desde la BD)
try {
    $stmt_roles = $pdo->query("SELECT nombre_rol FROM roles ORDER BY nombre_rol ASC");
    $lista_roles_db = $stmt_roles->fetchAll(PDO::FETCH_COLUMN);
} catch (PDOException $e) {
    $lista_roles_db = ['empleado', 'encargado', 'auxiliar', 'admin']; // Fallback por si falla la BD
}

// LISTA DE GRADOS MILITARES
$lista_grados = ['SM', 'SP', 'SA', 'SI', 'SS', 'SG', 'CI', 'CB', 'VP', 'VS', 'VS "ec"', 'AC'];
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Administración de Usuarios | Sistema de Logística</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style> .user-row.inactive td { opacity: 0.6; font-style: italic; } </style>
</head>
<body>
    <?php include 'navbar.php'; ?>

    <div class="container mt-4"> <h1 class="mb-4"><i class="fas fa-users-cog me-2"></i> Gestión de Usuarios</h1>

        <?php if ($mensaje): ?> <div class="alert alert-<?php echo $alerta_tipo; ?> alert-dismissible fade show" role="alert"> <?php echo htmlspecialchars($mensaje); ?> <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button> </div> <?php endif; ?>

        <div class="row mb-3 align-items-center">
            <div class="col-md-6 mb-2 mb-md-0">
                <button type="button" class="btn btn-success" data-bs-toggle="modal" data-bs-target="#crearUsuarioModal"> <i class="fas fa-user-plus me-1"></i> Crear Nuevo Empleado </button>
            </div>
            <div class="col-md-6">
                <div class="input-group"> <span class="input-group-text"><i class="fas fa-search"></i></span> <input type="text" id="searchInput" class="form-control" placeholder="Buscar por Nombre o Usuario..."> </div>
            </div>
        </div>

        <div class="modal fade" id="crearUsuarioModal" tabindex="-1"> 
            <div class="modal-dialog"> 
                <div class="modal-content"> 
                    <div class="modal-header bg-success text-white"> <h5 class="modal-title">Crear Nuevo Empleado</h5> <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button> </div> 
                    <form method="POST" action="admin_usuarios.php"> 
                        <div class="modal-body"> 
                            <input type="hidden" name="crear_usuario" value="1"> 
                            
                            <div class="mb-3"> 
                                <label for="grado" class="form-label fw-bold">Grado / Jerarquía</label> 
                                <select class="form-select" id="grado" name="grado">
                                    <option value="">-- Sin Grado --</option>
                                    <?php foreach($lista_grados as $g): ?>
                                        <option value="<?php echo $g; ?>"><?php echo $g; ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>

                            <div class="mb-3"> <label for="nombre_completo" class="form-label">Nombre Completo</label> <input type="text" class="form-control" id="nombre_completo" name="nombre_completo" required> </div> 
                            <div class="mb-3"> <label for="usuario" class="form-label">Usuario (Login)</label> <input type="text" class="form-control" id="usuario" name="usuario" required> <small class="text-muted">Nombre corto para iniciar sesión.</small> </div> 
                            <div class="mb-3"> <label for="password" class="form-label">Contraseña Inicial</label> <input type="password" class="form-control" id="password" name="password" required> </div> 
                            <div class="mb-3"> <label for="email" class="form-label">Email</label> <input type="email" class="form-control" id="email" name="email"> </div> 
                            <div class="mb-3"> <label for="telefono" class="form-label">Teléfono</label> <input type="text" class="form-control" id="telefono" name="telefono"> </div> 
                            <div class="mb-3"> <label for="genero" class="form-label">Género</label> <select class="form-select" id="genero" name="genero" required> <option value="masculino">Masculino</option> <option value="femenino">Femenino</option> <option value="otro" selected>Otro / No especificar</option> </select> </div> 
                        </div> 
                        <div class="modal-footer"> <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button> <button type="submit" class="btn btn-success"><i class="fas fa-plus"></i> Crear Usuario</button> </div> 
                    </form> 
                </div> 
            </div> 
        </div>

        <div class="modal fade" id="editUsuarioModal" tabindex="-1"> 
            <div class="modal-dialog"> 
                <div class="modal-content"> 
                    <div class="modal-header bg-info text-white"> <h5 class="modal-title" id="editUsuarioModalLabel">Editar Empleado</h5> <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button> </div> 
                    <form method="POST" action="admin_usuarios_editar_procesar.php"> 
                        <div class="modal-body"> 
                            <input type="hidden" name="modificar_usuario" value="1"> 
                            <input type="hidden" name="id_usuario_edit" id="id_usuario_edit"> 
                            
                            <div class="mb-3"> 
                                <label for="grado_edit" class="form-label fw-bold">Grado / Jerarquía</label> 
                                <select class="form-select" id="grado_edit" name="grado_edit">
                                    <option value="">-- Sin Grado --</option>
                                    <?php foreach($lista_grados as $g): ?>
                                        <option value="<?php echo $g; ?>"><?php echo $g; ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>

                            <div class="mb-3"> <label for="nombre_completo_edit" class="form-label">Nombre Completo</label> <input type="text" class="form-control" id="nombre_completo_edit" name="nombre_completo_edit" required> </div> 
                            <div class="mb-3"> <label for="usuario_edit" class="form-label">Usuario (Login)</label> <input type="text" class="form-control" id="usuario_edit" name="usuario_edit" required> </div> 
                            <div class="mb-3"> <label for="email_edit" class="form-label">Email</label> <input type="email" class="form-control" id="email_edit" name="email_edit"> </div> 
                            <div class="mb-3"> <label for="telefono_edit" class="form-label">Teléfono</label> <input type="text" class="form-control" id="telefono_edit" name="telefono_edit"> </div> 
                            <div class="mb-3"> <label for="genero_edit" class="form-label">Género</label> <select class="form-select" id="genero_edit" name="genero_edit" required> <option value="masculino">Masculino</option> <option value="femenino">Femenino</option> <option value="otro">Otro / No especificar</option> </select> </div> <hr> <p class="small text-muted">Dejar en blanco para no cambiar la contraseña.</p> <div class="mb-3"> <label for="password_edit" class="form-label">Nueva Contraseña (Opcional)</label> <input type="password" class="form-control" id="password_edit" name="password_edit"> </div> 
                        </div> 
                        <div class="modal-footer"> <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button> <button type="submit" class="btn btn-info text-white"><i class="fas fa-save"></i> Guardar Cambios</button> </div> 
                    </form> 
                </div> 
            </div> 
        </div>

        <div class="modal fade" id="actionFeedbackModal" tabindex="-1"> <div class="modal-dialog modal-dialog-centered"> <div class="modal-content"> <div class="modal-header" id="feedbackModalHeader"> <h5 class="modal-title" id="actionFeedbackModalLabel">Resultado</h5> <button type="button" class="btn-close" data-bs-dismiss="modal"></button> </div> <div class="modal-body" id="feedbackModalBody"> Mensaje... </div> <div class="modal-footer"> <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cerrar</button> </div> </div> </div> </div>

        <div class="card shadow"> <div class="card-header bg-dark text-white"> <h5 class="mb-0">Listado de Usuarios Registrados</h5> </div> <div class="card-body"> <div class="table-responsive"> <table class="table table-striped table-hover" id="userTable"> <thead> 
    <tr> 
        <th>ID</th> 
        <th>Grado</th> <th>Nombre Completo</th> 
        <th>Usuario</th> 
        <th>Email</th> 
        <th class="text-center">Estado</th> 
        <th class="text-center">Acciones</th> 
        <th class="text-center">Rol</th> </tr> 
</thead> 
<tbody> 
    <?php if (count($usuarios_empleados) > 0): ?> 
    <?php foreach ($usuarios_empleados as $usuario): ?> 
    <tr class="user-row <?php echo ($usuario['activo'] ?? 1) == 0 ? 'inactive' : ''; ?>"> 
        <td><?php echo htmlspecialchars($usuario['id_usuario']); ?></td> 
        <td class="fw-bold text-primary"><?php echo htmlspecialchars($usuario['grado'] ?? '-'); ?></td> <td class="user-name"><?php echo htmlspecialchars($usuario['nombre_completo']); ?></td> 
        <td class="user-login"><?php echo htmlspecialchars($usuario['usuario']); ?></td> 
        <td><?php echo htmlspecialchars($usuario['email'] ?? 'N/A'); ?></td> 
        <td class="text-center"> 
            <?php if (($usuario['activo'] ?? 1) == 1): ?> 
            <span class="badge bg-success">Activo</span> 
            <?php else: ?> 
            <span class="badge bg-danger">Inactivo</span> 
            <?php endif; ?> 
        </td> 
        <td class="text-center text-nowrap"> 
            <button type="button" class="btn btn-sm btn-info text-white me-1" 
                    data-bs-toggle="modal" data-bs-target="#editUsuarioModal" 
                    data-id="<?php echo htmlspecialchars($usuario['id_usuario']); ?>" 
                    data-nombre="<?php echo htmlspecialchars($usuario['nombre_completo']); ?>" 
                    data-usuario="<?php echo htmlspecialchars($usuario['usuario']); ?>" 
                    data-email="<?php echo htmlspecialchars($usuario['email'] ?? ''); ?>" 
                    data-telefono="<?php echo htmlspecialchars($usuario['telefono'] ?? ''); ?>" 
                    data-genero="<?php echo htmlspecialchars($usuario['genero'] ?? 'otro'); ?>" 
                    data-grado="<?php echo htmlspecialchars($usuario['grado'] ?? ''); ?>" 
                    onclick="loadEditUserModal(this)" title="Editar Usuario"> 
                <i class="fas fa-edit"></i> 
            </button> 
            <button type="button" class="btn btn-sm btn-warning me-1" onclick="confirmResetPassword(<?php echo $usuario['id_usuario']; ?>, '<?php echo htmlspecialchars(addslashes($usuario['usuario'])); ?>')" title="Resetear Contraseña"> <i class="fas fa-key"></i> </button> 
            <?php $isActive = ($usuario['activo'] ?? 1) == 1; ?> 
            <button type="button" class="btn btn-sm <?php echo $isActive ? 'btn-secondary' : 'btn-success'; ?> me-1" onclick="confirmToggleStatus(<?php echo $usuario['id_usuario']; ?>, '<?php echo htmlspecialchars(addslashes($usuario['usuario'])); ?>', <?php echo $isActive ? 0 : 1; ?>)" title="<?php echo $isActive ? 'Desactivar' : 'Activar'; ?> Usuario"> <i class="fas <?php echo $isActive ? 'fa-user-slash' : 'fa-user-check'; ?>"></i> </button> 
            <a href="tareas_lista.php?asignado=<?php echo $usuario['id_usuario']; ?>" class="btn btn-sm btn-outline-primary me-1" title="Ver Tareas Asignadas"> <i class="fas fa-tasks"></i> </a> 
            <form method="POST" action="admin_usuarios.php" class="d-inline" onsubmit="return confirm('ADVERTENCIA: ¿Seguro de ELIMINAR al usuario <?php echo htmlspecialchars(addslashes($usuario['nombre_completo'])); ?>? Esta acción NO SE PUEDE DESHACER.');"> <input type="hidden" name="eliminar_usuario" value="1"> <input type="hidden" name="id_usuario" value="<?php echo $usuario['id_usuario']; ?>"> <button type="submit" class="btn btn-sm btn-danger" title="Eliminar Usuario"> <i class="fas fa-trash"></i> </button> </form> 
        </td> 
        <td class="text-center">
            <form action="admin_cambiar_rol.php" method="POST" style="display:inline;" onsubmit="return confirm('¿Confirmar cambio de rol para <?php echo htmlspecialchars($usuario['nombre_completo']); ?>?');">
                <input type="hidden" name="id_usuario" value="<?php echo htmlspecialchars($usuario['id_usuario']); ?>">
                <select name="nuevo_rol" class="form-select form-select-sm d-inline-block" style="width: 130px; max-width: 100%;">
                    <?php
                    // AQUI USAMOS LA LISTA DINÁMICA DE LA BD
                    $current_rol = $usuario['rol'] ?? 'empleado'; 
                    foreach ($lista_roles_db as $rol_db) {
                        $selected = ($current_rol == $rol_db) ? 'selected' : '';
                        echo '<option value="' . htmlspecialchars($rol_db) . '" ' . $selected . '>' . ucfirst($rol_db) . '</option>';
                    }
                    ?>
                </select>
                <button type="submit" name="cambiar_rol" class="btn btn-sm btn-primary mt-1 mt-md-0" title="Guardar Rol">
                    <i class="fas fa-arrow-right-arrow-left"></i>
                </button>
            </form>
        </td>
    </tr> 
    <?php endforeach; ?> 
    <?php else: ?> 
    <tr> <td colspan="9" class="text-center">No hay usuarios registrados que no sean usted.</td> </tr> 
    <?php endif; ?> 
</tbody> 
</table> </div> </div> </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        function loadEditUserModal(button) { 
            const id=button.getAttribute('data-id'); 
            const n=button.getAttribute('data-nombre'); 
            const u=button.getAttribute('data-usuario'); 
            const e=button.getAttribute('data-email'); 
            const t=button.getAttribute('data-telefono'); 
            const g=button.getAttribute('data-genero')||'otro';
            const grado=button.getAttribute('data-grado')||''; 

            document.getElementById('id_usuario_edit').value=id; 
            document.getElementById('nombre_completo_edit').value=n; 
            document.getElementById('usuario_edit').value=u; 
            document.getElementById('email_edit').value=e; 
            document.getElementById('telefono_edit').value=t; 
            document.getElementById('genero_edit').value=g; 
            document.getElementById('grado_edit').value=grado; 
            
            document.getElementById('password_edit').value=''; 
            document.getElementById('editUsuarioModalLabel').textContent=`Editar Empleado #${id} (${u})`; 
        }
        const editModal = document.getElementById('editUsuarioModal'); if (editModal) { editModal.addEventListener('hidden.bs.modal', function () { document.querySelector('#editUsuarioModal form').reset(); document.getElementById('id_usuario_edit').value=''; document.getElementById('editUsuarioModalLabel').textContent='Editar Empleado'; }); }

        const searchInput=document.getElementById('searchInput'); const userTable=document.getElementById('userTable'); const tableRows=userTable?userTable.querySelectorAll('tbody tr.user-row'):[]; if(searchInput&&tableRows.length>0){searchInput.addEventListener('keyup',function(){const s=this.value.toLowerCase().trim(); tableRows.forEach(r=>{const nE=r.querySelector('td.user-name'); const lE=r.querySelector('td.user-login'); const n=nE?nE.textContent.toLowerCase():''; const l=lE?lE.textContent.toLowerCase():''; if(n.includes(s)||l.includes(s)){r.style.display='';}else{r.style.display='none';}});});}
        const feedbackModalEl = document.getElementById('actionFeedbackModal'); const feedbackModal = feedbackModalEl ? new bootstrap.Modal(feedbackModalEl) : null; const feedbackHeader = document.getElementById('feedbackModalHeader'); const feedbackTitle = document.getElementById('actionFeedbackModalLabel'); const feedbackBody = document.getElementById('feedbackModalBody');
        function showFeedbackModal(title, message, type = 'info') { if (!feedbackModal || !feedbackHeader || !feedbackTitle || !feedbackBody) { alert(message); return; } feedbackTitle.textContent = title; feedbackBody.textContent = message; feedbackHeader.className = 'modal-header'; feedbackTitle.querySelector('i')?.remove(); const btnClose = feedbackHeader.querySelector('.btn-close'); if (type === 'success') { feedbackHeader.classList.add('bg-success', 'text-white'); feedbackTitle.insertAdjacentHTML('afterbegin', '<i class="fas fa-check-circle me-2"></i>'); if(btnClose) btnClose.classList.add('btn-close-white'); } else if (type === 'danger') { feedbackHeader.classList.add('bg-danger', 'text-white'); feedbackTitle.insertAdjacentHTML('afterbegin', '<i class="fas fa-exclamation-triangle me-2"></i>'); if(btnClose) btnClose.classList.add('btn-close-white'); } else if (type === 'warning') { feedbackHeader.classList.add('bg-warning', 'text-dark'); feedbackTitle.insertAdjacentHTML('afterbegin', '<i class="fas fa-exclamation-triangle me-2"></i>'); if(btnClose) btnClose.classList.remove('btn-close-white'); } else { feedbackHeader.classList.add('bg-info', 'text-white'); feedbackTitle.insertAdjacentHTML('afterbegin', '<i class="fas fa-info-circle me-2"></i>'); if(btnClose) btnClose.classList.add('btn-close-white'); } feedbackModal.show(); }
        function confirmResetPassword(userId, userName) { if (confirm(`¿Está seguro de resetear la contraseña para el usuario '${userName}' (ID: ${userId})? Se enviará una nueva contraseña temporal por correo.`)) { const originalButton = event.target.closest('button'); if (originalButton) { originalButton.disabled = true; originalButton.innerHTML = '<i class="fas fa-spinner fa-spin"></i>'; } fetch('admin_usuarios_reset_password.php', { method: 'POST', headers: { 'Content-Type': 'application/x-www-form-urlencoded', 'X-Requested-With': 'XMLHttpRequest'}, body: `id_usuario=${userId}` }).then(response => response.json().then(data => ({ status: response.status, body: data }))).then(({ status, body }) => { if (body.success) { showFeedbackModal('Éxito', body.message || "Correo con contraseña temporal enviado.", 'success'); } else { showFeedbackModal('Error', body.message || `Error ${status}`, 'danger'); } }).catch(error => { console.error("Error en fetch reset password:", error); showFeedbackModal('Error de Conexión', "No se pudo conectar con el servidor para resetear la contraseña.", 'danger'); }).finally(() => { if (originalButton) { originalButton.disabled = false; originalButton.innerHTML = '<i class="fas fa-key"></i>'; } }); } }
        function confirmToggleStatus(userId, userName, newStatus) { const actionText = newStatus === 1 ? 'ACTIVAR' : 'DESACTIVAR'; const confirmationMessage = `¿Está seguro de ${actionText} al usuario '${userName}' (ID: ${userId})?`; if (confirm(confirmationMessage)) { const originalButton = event.target.closest('button'); if (originalButton) { originalButton.disabled = true; originalButton.innerHTML = '<i class="fas fa-spinner fa-spin"></i>'; } fetch('admin_usuarios_toggle_status.php', { method: 'POST', headers: { 'Content-Type': 'application/x-www-form-urlencoded', 'X-Requested-With': 'XMLHttpRequest'}, body: `id_usuario=${userId}&nuevo_estado=${newStatus}` }).then(response => response.json().then(data => ({ status: response.status, body: data }))).then(({ status, body }) => { if (body.success) { showFeedbackModal('Éxito', body.message || `Usuario ${actionText.toLowerCase()}do.`, 'success'); if(feedbackModalEl) { feedbackModalEl.addEventListener('hidden.bs.modal', () => { window.location.reload(); }, { once: true }); } else { window.location.reload(); } } else { showFeedbackModal('Error', body.message || `Error ${status}`, 'danger'); if (originalButton) { originalButton.disabled = false; originalButton.innerHTML = `<i class="fas ${newStatus === 0 ? 'fa-user-slash' : 'fa-user-check'}"></i>`; } } }).catch(error => { console.error("Error en fetch toggle status:", error); showFeedbackModal('Error de Conexión', "No se pudo conectar con el servidor para cambiar el estado.", 'danger'); if (originalButton) { originalButton.disabled = false; originalButton.innerHTML = `<i class="fas ${newStatus === 0 ? 'fa-user-slash' : 'fa-user-check'}"></i>`; } }); } }
    </script>
    <div class="toast-container position-fixed bottom-0 end-0 p-3" id="notificationToastContainer" style="z-index: 1080;"></div>
</body>
</html>