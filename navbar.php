<?php
// Archivo: navbar.php (COMPLETO - MODIFICADO POR GEMINI v2 PARA ROLES DINÁMICOS)
// *** MODIFICADO (v3) POR GEMINI PARA OCULTAR "MIS PEDIDOS" AL ROL EMPLEADO ***
if (session_status() == PHP_SESSION_NONE) { session_start(); }




// --- INICIO BLOQUE MODIFICADO POR GEMINI (v2) ---
$mostrar_novedades = false;
if (isset($_SESSION['usuario_rol']) && $_SESSION['usuario_rol'] === 'admin') {
    $mostrar_novedades = true;
} else {
    $u_nav_nombre = $_SESSION['usuario_nombre'] ?? '';
    $lista_aut = ['Cañete', 'Ezequiel Paz', 'Federico González', 'Federico Gonzalez'];
    foreach ($lista_aut as $persona) {
        if (stripos($u_nav_nombre, $persona) !== false) {
            $mostrar_novedades = true;
            break;
        }
    }
}
// Verificamos si $pdo y funciones_permisos.php ya están definidos antes de incluir
if (!isset($pdo)) {
    include_once 'conexion.php';
}
if (!function_exists('tiene_permiso')) {
    // ¡Importante! funciones_permisos.php AHORA ES REQUERIDO para el navbar
    include_once 'funciones_permisos.php'; 
}

// Helper para verificar si el usuario tiene AL MENOS UNO de los permisos de una lista
// Esto es para decidir si mostramos el dropdown "Admin"
if (!function_exists('tiene_algun_permiso')) {
    function tiene_algun_permiso($claves_permiso, $pdo_conn) {
        // El admin siempre tiene todo, no necesitamos chequear la BD
        if (isset($_SESSION['usuario_rol']) && $_SESSION['usuario_rol'] === 'admin') {
            return true;
        }
        // Para otros roles, chequear cada permiso
        if (isset($pdo_conn)) { // Asegurarse que $pdo esté disponible
            foreach ($claves_permiso as $clave) {
                if (tiene_permiso($clave, $pdo_conn)) {
                    return true;
                }
            }
        }
        return false;
    }
}

// Lista de permisos que dan acceso al menú "Admin"
$permisos_del_menu_admin = [
    'acceso_pedidos_lista_encargado',
    'admin_usuarios',
    'admin_roles', // Añadido enlace a roles
    'admin_categorias',
    'admin_areas',
    'admin_destinos',
    'admin_remitos'
];

// Variable para decidir si mostrar el dropdown de Admin
// Usamos $pdo que viene de conexion.php
$mostrar_menu_admin = tiene_algun_permiso($permisos_del_menu_admin, $pdo);

// --- FIN BLOQUE MODIFICADO POR GEMINI (v2) ---


$rol_usuario_nav = $_SESSION['usuario_rol'] ?? 'empleado'; 
$nombre_usuario_nav = $_SESSION['usuario_nombre'] ?? 'Invitado'; 
$foto_perfil_nav = $_SESSION['usuario_perfil'] ?? 'default.png'; 
$id_usuario_nav = $_SESSION['usuario_id'] ?? 0;

// Conteo Inicial Notificaciones (SIN CAMBIOS)
$notificaciones_no_leidas = 0; 
if ($id_usuario_nav > 0 && isset($pdo)) { 
    try { 
        $sql_notif = "SELECT COUNT(*) FROM notificaciones WHERE id_usuario_destino = :id_user AND leida = 0"; 
        $stmt_notif = $pdo->prepare($sql_notif); 
        $stmt_notif->execute([':id_user' => $id_usuario_nav]); 
        $notificaciones_no_leidas = $stmt_notif->fetchColumn(); 
    } catch (PDOException $e) { 
        error_log("NAVBAR Error conteo inicial: " . $e->getMessage()); 
    } 
}
?>

<style> 
    .logo-invertido { height: 32px; margin-right: 10px; filter: invert(100%) grayscale(100%) brightness(200%); } 
    #notifications-list { max-height: 400px; overflow-y: auto; } 
    #notification-badge { font-size: 0.6em; padding: 0.2em 0.4em; position: absolute; top: 0.25rem; left: 1.2rem; transform: translate(-50%, -50%); } 
    .nav-link i.fa-bell { position: relative; } 
</style>

<nav class="navbar navbar-expand-lg navbar-dark bg-dark">
    <div class="container-fluid">
        <a class="navbar-brand" href="dashboard.php">
            <img src="assets/log.png" alt="Logo" class="logo-invertido"> Logística | ACTIS
        </a>
        <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNavDropdown">
            <span class="navbar-toggler-icon"></span>
        </button>
        <div class="collapse navbar-collapse" id="navbarNavDropdown">

            <ul class="navbar-nav me-auto mb-2 mb-lg-0">
                <?php
// --- LOGICA DE PERMISOS (Puedes pegar esto justo antes del HTML del menú) ---
$u_nombre_nav = $_SESSION['usuario_nombre'] ?? '';
$u_rol_nav    = $_SESSION['usuario_rol'] ?? '';

$ver_parte = false;
$ver_firma = false;

// 1. Admin ve todo
if ($u_rol_nav === 'admin') {
    $ver_parte = true;
    $ver_firma = true;
} 
// 2. Cañete ve todo (Generar y Firmar)
elseif (stripos($u_nombre_nav, 'Cañete') !== false) {
    $ver_parte = true;
    $ver_firma = true;
}
// 3. Federico y Ezequiel (Solo Generar)
elseif (stripos($u_nombre_nav, 'Federico') !== false || stripos($u_nombre_nav, 'Ezequiel Paz') !== false) {
    $ver_parte = true;
    $ver_firma = false;
}
?>


                
                <?php if (tiene_permiso('acceso_pedidos_crear', $pdo) || tiene_permiso('acceso_pedidos_lista_encargado', $pdo)): ?>
                    <li class="nav-item dropdown">
                        <a class="nav-link dropdown-toggle <?php echo (strpos(basename($_SERVER['PHP_SELF']), 'pedido_') !== false || strpos(basename($_SERVER['PHP_SELF']), 'encargado_pedidos_') !== false) ? 'active' : ''; ?>"
                            href="#" id="pedidosDropdown" role="button" data-bs-toggle="dropdown" aria-expanded="false">
                            <i class="fas fa-file-signature"></i> Pedidos
                        </a>
                        <ul class="dropdown-menu" aria-labelledby="pedidosDropdown">
                            <?php if (tiene_permiso('acceso_pedidos_crear', $pdo)): ?>
                                <li>
                                    <a class="dropdown-item" href="pedido_crear.php">
                                        <i class="fas fa-plus-circle me-2 text-success"></i> Crear Pedido
                                    </a>
                                </li>
                            <?php endif; ?>
                            <li>
                                <a class="dropdown-item" href="pedidos_lista_usuario.php">
                                    <i class="fas fa-history me-2"></i> Ver Mis Pedidos
                                </a>
                            </li>
                             <?php if (tiene_permiso('acceso_pedidos_lista_encargado', $pdo)): ?>
                                <li><hr class="dropdown-divider"></li>
                                <li>
                                    <a class="dropdown-item" href="encargado_pedidos_lista.php">
                                        <i class="fas fa-inbox me-2 text-primary"></i> Bandeja de Pedidos
                                    </a>
                                </li>
                            <?php endif; ?>
                        </ul>
                    </li>
                
                <?php endif; ?>
                <?php if ($mostrar_novedades): ?>
<li class="nav-item dropdown">
    <a class="nav-link dropdown-toggle" href="#" role="button" data-bs-toggle="dropdown">
        Novedades
    </a>
    <ul class="dropdown-menu">
        <li><a class="dropdown-item" href="asistencia_tomar.php">Generar Parte</a></li>
    </ul>
</li>
<?php endif; ?>
                <li class="nav-item dropdown">
                    <a class="nav-link dropdown-toggle <?php echo (strpos(basename($_SERVER['PHP_SELF']), 'tarea_') !== false || basename($_SERVER['PHP_SELF']) == 'tareas_lista.php') ? 'active' : ''; ?>"
                        href="#" id="tareasDropdown" role="button" data-bs-toggle="dropdown" aria-expanded="false">
                        <i class="fas fa-tasks"></i> Tareas
                    </a>
                    <ul class="dropdown-menu" aria-labelledby="tareasDropdown">
                        <li>
                            <a class="dropdown-item" href="tareas_lista.php">
                                <i class="fas fa-list-ul me-2"></i> Ver Todas las Tareas
                            </a>
                        </li>
                        <?php if (tiene_permiso('crear_tarea_directa', $pdo)): ?>
                            <li><hr class="dropdown-divider"></li>
                            <li>
                                <a class="dropdown-item" href="tarea_crear.php">
                                    <i class="fas fa-plus-circle me-2 text-success"></i> Crear Nueva Tarea
                                </a>
                            </li>
                        <?php endif; ?>
                    </ul>
                </li>

                <?php if (tiene_permiso('acceso_avisos_admin', $pdo)): ?>
                    <li class="nav-item dropdown">
                        <a class="nav-link dropdown-toggle <?php echo (strpos(basename($_SERVER['PHP_SELF']), 'avisos_') !== false || basename($_SERVER['PHP_SELF']) == 'avisos.php') ? 'active' : ''; ?>"
                            href="#" id="avisosDropdown" role="button" data-bs-toggle="dropdown">
                            <i class="fas fa-bullhorn"></i> Avisos
                        </a>
                        <ul class="dropdown-menu">
                            <li><a class="dropdown-item" href="avisos.php"><i class="fas fa-eye me-2"></i> Ver</a></li>
                            <li><a class="dropdown-item" href="avisos_lista.php"><i class="fas fa-list-alt me-2"></i> Administrar</a></li>
                            <li><a class="dropdown-item" href="avisos_crear.php"><i class="fas fa-plus-circle me-2"></i> Crear</a></li>
                        </ul>
                    </li>
                <?php else: ?>
                    <li class="nav-item">
                        <a class="nav-link <?php echo basename($_SERVER['PHP_SELF']) == 'avisos.php' ? 'active' : ''; ?>" href="avisos.php">
                            <i class="fas fa-bullhorn"></i> Avisos
                        </a>
                    </li>
                <?php endif; ?>


                <li class="nav-item">
                    <a class="nav-link <?php echo basename($_SERVER['PHP_SELF']) == 'chat.php' ? 'active' : ''; ?>" href="chat.php">
                        <i class="fas fa-comments"></i> Chat
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link <?php echo basename($_SERVER['PHP_SELF']) == 'noticias_ffaa.php' ? 'active' : ''; ?>" href="noticias_ffaa.php">
                        <i class="fas fa-bullhorn"></i> Noticias
                    </a>
                </li>
                
                <?php if ($mostrar_menu_admin): ?>
                    <li class="nav-item dropdown">
                        <a class="nav-link dropdown-toggle <?php echo (strpos(basename($_SERVER['PHP_SELF']), 'admin_') !== false || strpos(basename($_SERVER['PHP_SELF']), 'encargado_') !== false) ? 'active' : ''; ?>"
                            href="#" id="adminDropdown" role="button" data-bs-toggle="dropdown">
                            <i class="fas fa-cog"></i> Admin
                        </a>
                        
                        <ul class="dropdown-menu">
                            <?php if (tiene_permiso('admin_usuarios', $pdo)): ?>
                                <li><a class="dropdown-item" href="admin_usuarios.php"><i class="fas fa-users-cog me-2"></i>Usuarios</a></li>
                            <?php endif; ?>
                            
                            <?php if (tiene_permiso('admin_roles', $pdo)): ?>
                                <li><a class="dropdown-item" href="admin_roles.php"><i class="fas fa-user-shield me-2"></i>Roles y Permisos</a></li>
                            <?php endif; ?>

                            <?php if (tiene_permiso('admin_usuarios', $pdo) || tiene_permiso('admin_roles', $pdo)): ?>
                                <li><hr class="dropdown-divider"></li>
                            <?php endif; ?>

                            <?php if (tiene_permiso('admin_categorias', $pdo)): ?>
                                <li><a class="dropdown-item" href="admin_categorias.php"><i class="fas fa-tags me-2"></i>Categorías</a></li>
                            <?php endif; ?>

                            <?php if (tiene_permiso('admin_areas', $pdo)): ?>
                                <li><a class="dropdown-item" href="admin_areas.php"><i class="fas fa-map-marker-alt me-2"></i>Gestionar Áreas</a></li>
                            <?php endif; ?>

                            <?php if (tiene_permiso('admin_destinos', $pdo)): ?>
                                <li><a class="dropdown-item" href="admin_destinos.php"><i class="fas fa-compass me-2"></i>Gestionar Destinos</a></li>
                            <?php endif; ?>
                            
                            <?php if (tiene_permiso('admin_remitos', $pdo)): ?>
                                <li><hr class="dropdown-divider"></li>
                                <li><a class="dropdown-item" href="admin_remitos.php"><i class="fas fa-file-invoice-dollar me-2 text-success"></i> Ver Remitos/Facturas</a></li>
                            <?php endif; ?>
                        </ul>
                    </li>
                <?php endif; ?>
            </ul>
            
            <ul class="navbar-nav">
                <li class="nav-item dropdown me-3">
                    <a class="nav-link position-relative" href="#" id="notificationsDropdown" role="button"
                        data-bs-toggle="dropdown" aria-expanded="false" data-bs-auto-close="outside">
                        <i class="fas fa-bell"></i>
                        <span class="d-lg-none ms-2">Notif.</span>
                        <span class="badge bg-danger rounded-pill position-absolute top-0 start-100 translate-middle"
                            id="notification-badge"
                            style="display: <?php echo $notificaciones_no_leidas > 0 ? 'inline-block' : 'none'; ?>;">
                            <?php echo $notificaciones_no_leidas > 99 ? '99+' : $notificaciones_no_leidas; ?>
                        </span>
                    </a>
                    <ul class="dropdown-menu dropdown-menu-end shadow border-0" aria-labelledby="notificationsDropdown"
                        id="notifications-list" style="width: 350px;">
                        <li>
                            <a class="dropdown-item text-center text-muted p-3">
                                <i class="fas fa-spinner fa-spin me-2"></i>Cargando...
                            </a>
                        </li>
                    </ul>
                </li>

                <li class="nav-item dropdown">
                    <a class="nav-link dropdown-toggle d-flex align-items-center" href="#" id="navbarDropdownMenuLink"
                        role="button" data-bs-toggle="dropdown">
                        <img src="uploads/perfiles/<?php echo htmlspecialchars($foto_perfil_nav); ?>"
                            alt="Perfil" class="rounded-circle me-2"
                            style="width: 30px; height: 30px; object-fit: cover;">
                        <?php echo htmlspecialchars($nombre_usuario_nav); ?>
                    </a>
                    <ul class="dropdown-menu dropdown-menu-end shadow border-0">
                        <li>
                            <a class="dropdown-item" href="perfil.php">
                                <i class="fas fa-user-circle me-2 text-primary"></i> Perfil
                            </a>
                        </li>
                        <li><hr class="dropdown-divider my-1"></li>
                        <li>
                            <a class="dropdown-item text-danger" href="logout.php">
                                <i class="fas fa-sign-out-alt me-2"></i> Salir
                            </a>
                        </li>
                    </ul>
                </li>
            </ul>
            </div>
    </div>
</nav>

<script>
    // ... (Tu JS de navbar existente para polling, toasts, etc.) ...
    let lastCheckTime = Date.now();
    const currentUserId = <?php echo json_encode($id_usuario_nav); ?>;
    const notificationSound = new Audio('assets/alert.mp3');
    function playNotificationSound() { try { notificationSound.volume = 0.8; notificationSound.pause(); notificationSound.currentTime = 0; notificationSound.play().catch(e => { console.warn("Sonido bloqueado por navegador:", e); }); } catch(e) {} }
    function updateNotificationBadge(count) { const b = document.getElementById('notification-badge'); if (b) { b.textContent = count > 99 ? '99+' : count; b.style.display = count > 0 ? 'inline-block' : 'none'; } }

    // --- FUNCIÓN loadNotificationsList ---
    function loadNotificationsList() { const list = document.getElementById('notifications-list'); if (!list) return; list.innerHTML = '<li><a class="dropdown-item text-center text-primary p-3"><i class="fas fa-spinner fa-spin me-2"></i> Cargando...</a></li>'; fetch(`notificaciones_fetch.php?last=null`).then(r => r.ok ? r.json() : r.text().then(t => {throw new Error(`HTTP ${r.status}: ${t||r.statusText}`);})).then(data => { list.innerHTML = ''; if (data?.notifications?.length > 0) { data.notifications.forEach(notif => { let icon = 'fa-info-circle', tc = ''; if (notif.tipo === 'chat') icon = 'fa-comment text-primary'; else if (notif.tipo === 'tarea_asignada') icon = 'fa-clipboard-list text-success'; else if (notif.tipo === 'tarea_terminada') { icon = 'fa-exclamation-triangle'; tc = 'text-warning'; } else if (notif.tipo === 'tarea_verificada') { icon = 'fa-check-double'; tc = 'text-success'; } else if (notif.tipo === 'tarea_modificacion') { icon = 'fa-undo'; tc = 'text-danger'; } else if (notif.tipo === 'tarea_iniciada') { icon = 'fa-play'; tc = 'text-info'; } else if (notif.tipo === 'tarea_actualizacion') { icon = 'fa-pen'; tc = 'text-secondary'; } else if (notif.tipo === 'tarea_recall_request') { icon = 'fa-undo-alt'; tc = 'text-warning'; } else if (notif.tipo === 'tarea_reasignada_nueva') { icon = 'fa-random text-success'; tc = 'text-success'; } else if (notif.tipo === 'tarea_reasignada_anterior') { icon = 'fa-random text-info'; tc = 'text-info'; } else if (notif.tipo === 'tarea_reanudad') { icon = 'fa-play-circle text-info'; } else if (notif.tipo === 'remito_rechazado') { icon = 'fa-file-invoice-dollar'; tc = 'text-danger'; } /* NUEVO TIPO */ const sc = notif.leida == 0 ? 'fw-bold' : 'text-muted'; const fecha = notif.fecha_creacion || 'Fecha inválida'; const targetUrl = notif.url || '#'; list.innerHTML += `<li><a class="dropdown-item ${sc} ${tc} text-wrap py-2" href="${targetUrl}" data-notif-id="${notif.id_notificacion}" data-notification-type="${notif.tipo || 'unknown'}" data-target-url="${targetUrl}" onclick="handleNotificationClick(this, event)"><div class="d-flex align-items-start"><i class="fas ${icon} mt-1 me-2 fa-fw"></i><div>${notif.mensaje}<span class="small d-block fw-normal text-muted">${fecha}</span></div></div></a></li>`; }); } else { list.innerHTML = '<li><a class="dropdown-item text-center text-muted p-3">No tienes notificaciones.</a></li>'; } list.innerHTML += '<li><hr class="dropdown-divider my-1"></li><li><a class="dropdown-item text-center small py-2" href="notificaciones_lista.php">Ver todas las notificaciones</a></li>'; }).catch(error => { list.innerHTML = '<li><a class="dropdown-item text-center text-danger p-3"><i class="fas fa-exclamation-circle me-2"></i>Error al cargar.</a></li>'; console.error('[loadNotificationsList] Catch - Error:', error); }); }

    // --- FUNCIÓN handleNotificationClick (Corregida para leer parámetros de URL) ---
    function handleNotificationClick(element, event) {
        event.preventDefault();
        const notifId = element.getAttribute('data-notif-id');
        const notifType = element.getAttribute('data-notification-type');
        const targetUrl = element.getAttribute('data-target-url');
        console.log(`[handleNotificationClick] ID: ${notifId}, Tipo: ${notifType}, Target: ${targetUrl}`);
        markAsRead(element);

        if (notifType === 'tarea_reasignada_nueva' || notifType === 'tarea_reasignada_anterior') {
            const reassignModalEl = document.getElementById('reassignInfoModal');
            const modalMessageEl = document.getElementById('reassignModalMessage');
            const newAssigneeInfoEl = document.getElementById('reassignNewAssigneeInfo');
            const newAssigneeNameEl = document.getElementById('reassignNewAssigneeName');
            const confirmButton = document.getElementById('reassignModalConfirmButton');
            const taskIdEl = document.getElementById('reassignTaskId');
            const taskTitleEl = document.getElementById('reassignTaskTitle');
            const subtextEl = document.getElementById('reassignModalSubtext');
            const taskDescEl = document.getElementById('reassignTaskDescription');
            const taskCatEl = document.getElementById('reassignTaskCategory');
            const taskPrioEl = document.getElementById('reassignTaskPriority');
            const taskStateEl = document.getElementById('reassignTaskState');

            if (!reassignModalEl || !modalMessageEl || !newAssigneeInfoEl || !newAssigneeNameEl || !confirmButton || !taskIdEl || !taskTitleEl || !subtextEl || !taskDescEl || !taskCatEl || !taskPrioEl || !taskStateEl) {
                console.error("Elementos del modal de reasignación no encontrados. Redirigiendo directamente.");
                window.location.href = targetUrl;
                return;
            }

            let modalMessage = ''; let subtextMessage = ''; let finalRedirectUrl = targetUrl; const urlParams = new URLSearchParams(targetUrl.split('?')[1] || '');

            const taskId = urlParams.get('id') || 'N/A';
            const taskTitle = urlParams.get('task_title') || 'No especificado';
            const taskDesc = urlParams.get('task_desc') || '';
            const taskCat = urlParams.get('task_cat') || 'N/A';
            const taskPrio = urlParams.get('task_prio') || 'N/A';
            const taskState = urlParams.get('task_state') || 'N/A';
            const newAssigneeName = urlParams.get('new_assignee_name') || 'Desconocido';

            taskIdEl.textContent = '#' + taskId;
            taskTitleEl.textContent = decodeURIComponent(taskTitle.replace(/\+/g, ' '));
            taskDescEl.textContent = decodeURIComponent(taskDesc.replace(/\+/g, ' '));
            taskCatEl.textContent = decodeURIComponent(taskCat.replace(/\+/g, ' '));
            taskPrioEl.textContent = decodeURIComponent(taskPrio.replace(/\+/g, ' '));
            taskStateEl.textContent = decodeURIComponent(taskState.replace(/\+/g, ' '));

            if (notifType === 'tarea_reasignada_nueva') {
                modalMessage = "Se te ha asignado esta tarea recientemente.";
                subtextMessage = "Haz clic en 'Entendido' para ver los detalles completos y gestionarla.";
                newAssigneeInfoEl.style.display = 'none';
                finalRedirectUrl = `tarea_ver.php?id=${taskId}`;
            } else { // tarea_reasignada_anterior
                modalMessage = "Esta tarea ha sido reasignada por el administrador.";
                subtextMessage = "Ya no está bajo tu responsabilidad. Haz clic en 'Entendido' para ver tus tareas actuales.";
                newAssigneeNameEl.textContent = decodeURIComponent(newAssigneeName.replace(/\+/g, ' '));
                newAssigneeInfoEl.style.display = 'block';
                finalRedirectUrl = `tareas_lista.php`;
            }

            confirmButton.onclick = function() { window.location.href = finalRedirectUrl; };
            const reassignModal = bootstrap.Modal.getOrCreateInstance(reassignModalEl);
            reassignModal.show();
        } else {
            window.location.href = targetUrl;
        }
    }

    // --- FUNCIÓN markAsRead ---
    function markAsRead(element) { let id = element ? element.getAttribute('data-notif-id') : null; if (!id) return; console.log(`[markAsRead] Marcando ID: ${id}`); fetch(`notificaciones_mark_read.php?id=${id}`).then(r => { if(r.ok) { console.log(`[markAsRead] ID: ${id} marcada OK.`); if (element) { element.classList.remove('fw-bold'); element.classList.add('text-muted'); } setTimeout(checkNewNotifications, 200); } else { console.warn(`[markAsRead] Falló marcar ID: ${id}. Status: ${r.status}`); } }).catch(e => { console.error('[markAsRead] Error fetch:', e); }); }

    // --- checkNewNotifications ---
    function checkNewNotifications() { const n=Date.now(), tS=lastCheckTime, u=`notificaciones_fetch.php?last=${tS}`; fetch(u).then(r=>{if(!r.ok)throw new Error(`[Polling] HTTP error ${r.status}`); return r.json();}).then(d=>{if(d&&typeof d.unread_count==='number')updateNotificationBadge(d.unread_count); if(d&&d.new_notifications&&d.new_notifications.length>0){console.log(`[Polling] ${d.new_notifications.length} nuevas.`); showNewNotificationToasts(d.new_notifications); playNotificationSound();} lastCheckTime=n;}).catch(e=>{console.error('[Polling] Fetch falló:',e);});}

    // --- showNewNotificationToasts ---
    function showNewNotificationToasts(newNotifications) {
        if(typeof bootstrap==='undefined'||typeof bootstrap.Toast==='undefined'){console.error('Bootstrap Toast no cargado.');return;} const tc=document.getElementById('notificationToastContainer'); if(!tc){console.warn('Contenedor Toast no encontrado.'); return;}
        newNotifications.forEach(notif=>{let ic='fas fa-info-circle',tt='Notificación',bg='bg-info',lt='Ver',txc='text-white'; if(notif.tipo==='tarea_asignada'){ic='fas fa-clipboard-list';tt='Nueva Tarea';bg='bg-success';lt='Ver Tarea';}else if(notif.tipo==='chat'){ic='fas fa-comment';tt='Nuevo Mensaje';bg='bg-primary';lt='Ver Mensaje';}else if(notif.tipo==='tarea_terminada'){ic='fas fa-exclamation-triangle';tt='Tarea p/ Revisión';bg='bg-warning';lt='Revisar';txc='text-dark';}else if(notif.tipo==='tarea_verificada'){ic='fas fa-check-double';tt='Tarea Aprobada';bg='bg-success';lt='Ver';}else if(notif.tipo==='tarea_modificacion'){ic='fas fa-undo';tt='Modif. Solicitada';bg='bg-danger';lt='Ver';}else if(notif.tipo==='tarea_iniciada'){ic='fas fa-play';tt='Tarea Iniciada';bg='bg-info';lt='Ver';}else if(notif.tipo==='tarea_actualizacion'){ic='fas fa-pen';tt='Actualiz. Tarea';bg='bg-secondary';lt='Ver';}else if(notif.tipo === 'remito_rechazado') {ic='fas fa-file-invoice-dollar';tt='Remito Rechazado';bg='bg-danger';lt='Corregir';} else if (notif.tipo === 'tarea_recall_request'){ic='fas fa-undo-alt';tt='Solicitud Tarea';bg='bg-warning';lt='Revisar';txc='text-dark';}else if(notif.tipo === 'tarea_reasignada_nueva'){ic='fas fa-random';tt='Tarea Reasignada';bg='bg-success';lt='Ver Tarea';}else if(notif.tipo === 'tarea_reasignada_anterior'){ic='fas fa-random';tt='Tarea Reasignada';bg='bg-info';lt='Ver Info';} const targetUrl=notif.url||'#'; const th=`<div class="toast align-items-center ${txc} ${bg} border-0" role="alert" aria-live="assertive" aria-atomic="true" data-bs-autohide="true" data-bs-delay="10000"><div class="d-flex"><div class="toast-body"><i class="${ic} me-2"></i><strong>${tt}</strong><span class="d-block small mt-1">${notif.mensaje.substring(0,60)}${notif.mensaje.length>60?'...':''}</span><a href="${targetUrl}" class="btn btn-sm btn-light ms-auto text-primary mt-1 fw-bold" style="text-decoration: none;" data-notif-id="${notif.id_notificacion}" data-notification-type="${notif.tipo||'unknown'}" data-target-url="${targetUrl}" onclick="handleNotificationClick(this, event)">${lt} <i class="fas fa-arrow-right small"></i></a></div><button type="button" class="btn-close me-2 m-auto ${txc==='text-white'?'btn-close-white':''}" data-bs-dismiss="toast"></button></div></div>`; tc.insertAdjacentHTML('beforeend',th); const nte=tc.lastElementChild; try{const t=new bootstrap.Toast(nte); nte.addEventListener('hidden.bs.toast',()=>{nte.remove();}); t.show();}catch(e){console.error("[Toast] Error mostrar:",e,nte); if(nte)nte.remove();}});
    }

    // *** INICIALIZACIÓN (CORREGIDA) ***
    (function() {
        if (currentUserId > 0) {
            const dE = document.getElementById('notificationsDropdown');
            if (dE) {
                setTimeout(() => {
                    try {
                        dE.addEventListener('show.bs.dropdown', loadNotificationsList);
                    } catch(e) { console.error("[Init] Error listener dropdown:", e); }
                }, 200);
            }
            
            // *** LÍNEAS RESTAURADAS PARA EL POLLING EN TIEMPO REAL ***
            checkNewNotifications(); // Primera llamada inmediata
            setInterval(checkNewNotifications, 6000); // Luego cada 6 segundos
            // *** FIN LÍNEAS RESTAURADAS ***
        }
    })();
</script>