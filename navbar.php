<?php
// Archivo: navbar.php (RESTAURADO: ÍCONOS, SUBMENÚS Y CENTRADO DE CAMPANA CORREGIDO)
if (session_status() == PHP_SESSION_NONE) { session_start(); }

if (!isset($pdo)) { include_once 'conexion.php'; }
if (!function_exists('tiene_permiso')) { include_once 'funciones_permisos.php'; }

$mostrar_novedades = false;
if (function_exists('tiene_permiso') && isset($pdo)) {
    $mostrar_novedades = tiene_permiso('acceso_asistencia', $pdo);
}

if (!function_exists('tiene_algun_permiso')) {
    function tiene_algun_permiso($claves_permiso, $pdo_conn) {
        if (isset($pdo_conn) && function_exists('tiene_permiso')) { 
            foreach ($claves_permiso as $clave) {
                if (tiene_permiso($clave, $pdo_conn)) return true;
            }
        }
        return false;
    }
}

$permisos_del_menu_admin = ['acceso_pedidos_lista_encargado', 'admin_usuarios', 'admin_roles', 'admin_categorias', 'admin_areas', 'admin_destinos', 'admin_remitos'];
$mostrar_menu_admin = tiene_algun_permiso($permisos_del_menu_admin, $pdo);

$rol_usuario_nav = $_SESSION['usuario_rol'] ?? 'empleado'; 
$nombre_usuario_nav = $_SESSION['usuario_nombre'] ?? 'Invitado'; 
$foto_perfil_nav = $_SESSION['usuario_perfil'] ?? 'default.png'; 
$id_usuario_nav = $_SESSION['usuario_id'] ?? 0;

$notificaciones_no_leidas = 0; 
if ($id_usuario_nav > 0 && isset($pdo)) { 
    try { 
        $sql_notif = "SELECT COUNT(*) FROM notificaciones WHERE (id_usuario = :id_user OR id_usuario_destino = :id_user) AND leida = 0"; 
        $stmt_notif = $pdo->prepare($sql_notif); 
        $stmt_notif->execute([':id_user' => $id_usuario_nav]); 
        $notificaciones_no_leidas = $stmt_notif->fetchColumn(); 
    } catch (PDOException $e) { 
        try {
            $sql_notif = "SELECT COUNT(*) FROM notificaciones WHERE id_usuario_destino = :id_user AND leida = 0"; 
            $stmt_notif = $pdo->prepare($sql_notif); 
            $stmt_notif->execute([':id_user' => $id_usuario_nav]); 
            $notificaciones_no_leidas = $stmt_notif->fetchColumn(); 
        } catch (Exception $ex) {}
    } 
}
?>
<style> 
    .logo-invertido { height: 32px; margin-right: 10px; filter: invert(100%) grayscale(100%) brightness(200%); } 
    #notifications-list { max-height: 400px; overflow-y: auto; } 
    .toast-container { z-index: 1090; }
    
    /* Estilos Móvil (Default) */
    .navbar-nav .nav-link { display: flex !important; flex-direction: row; align-items: center; justify-content: flex-start; text-align: left; font-size: 0.9rem; padding: 0.8rem 1rem !important; transition: background-color 0.2s ease, color 0.2s ease; border-radius: 5px; }
    .navbar-nav .nav-link i { margin-bottom: 0; font-size: 1.1rem; margin-right: 10px !important; width: 20px; text-align: center; }
    .navbar-nav .nav-link:hover { background-color: rgba(255,255,255,0.1); }
    #notification-badge { font-size: 0.6em; padding: 0.2em 0.4em; position: absolute; }
    .dropdown-toggle::after { margin-left: auto !important; }

    /* --- ESCRITORIO (LG > 992px) --- */
    @media (min-width: 992px) {
        .navbar-nav .nav-link { flex-direction: column; justify-content: center; text-align: center; font-size: 0.8rem; padding: 0.5rem 1rem !important; }
        .navbar-nav .nav-link i { margin-bottom: 4px; font-size: 1.2rem; margin-right: 0 !important; width: auto; }
        .dropdown-toggle::after { margin-left: 0 !important; margin-top: 2px; }
        .navbar-nav.ms-auto { align-items: center; }

        /* CENTRADO VISUAL DE LA CAMPANA SIN ROMPER EL DROPDOWN */
        /* No usamos flex en el 'a', sino margin en el 'i' para bajarlo visualmente */
        #notificationsDropdown i {
            margin-top: 15px; /* Empuja el icono hacia abajo para centrarlo */
            margin-bottom: 0 !important;
        }
        #notifications-list {
            /* Usa números NEGATIVOS para SUBIR el menú y pegarlo a la campana. */
            margin-top: -15px !important; /* <--- AGREGAR O MODIFICAR ESTA LÍNEA (Prueba -10px, -20px) */
        }
        /* Ajuste del badge para acompañar al icono bajado */
        #notificationsDropdown #notification-badge {
            top: 10px !important; 
            left: 40% !important; 
            transform: translateX(10px);
        }
    }

    @media (max-width: 991px) {
        #notification-badge { top: 10px !important; left: 25px !important; }
        .navbar-collapse { background-color: #2c3034; padding: 10px; border-radius: 0 0 10px 10px; margin-top: 10px; }
        .dropdown-menu { border: none; background-color: #3a3f44; }
        .dropdown-item { color: #e0e0e0; }
        .dropdown-item:hover { background-color: #4a5056; color: #fff; }
        .dropdown-divider { border-top: 1px solid #555; }
    }
</style>

<nav class="navbar navbar-expand-lg navbar-dark bg-dark">
    <div class="container-fluid">
        <a class="navbar-brand" href="dashboard.php">
            <img src="assets/img/sglap.png" alt="Logo" class="logo-invertido">
        </a>
        <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNavDropdown">
            <span class="navbar-toggler-icon"></span>
        </button>
        <div class="collapse navbar-collapse" id="navbarNavDropdown">
            
            <ul class="navbar-nav me-auto mb-2 mb-lg-0">
                
                <?php if (tiene_permiso('acceso_pedidos_crear', $pdo) || tiene_permiso('acceso_pedidos_lista_encargado', $pdo)): ?>
                    <li class="nav-item dropdown">
                        <a class="nav-link dropdown-toggle <?php echo (strpos(basename($_SERVER['PHP_SELF']), 'pedido_') !== false || strpos(basename($_SERVER['PHP_SELF']), 'encargado_pedidos_') !== false) ? 'active' : ''; ?>"
                            href="#" id="pedidosDropdown" role="button" data-bs-toggle="dropdown">
                            <i class="fas fa-file-signature"></i> Pedidos
                        </a>
                        <ul class="dropdown-menu">
                            <?php if (tiene_permiso('acceso_pedidos_crear', $pdo)): ?>
                                <li><a class="dropdown-item" href="pedido_crear.php"><i class="fas fa-plus-circle me-2 text-success"></i> Crear Pedido</a></li>
                            <?php endif; ?>
                            <li><a class="dropdown-item" href="pedidos_lista_usuario.php"><i class="fas fa-history me-2"></i> Ver Mis Pedidos</a></li>
                             <?php if (tiene_permiso('acceso_pedidos_lista_encargado', $pdo)): ?>
                                <li><hr class="dropdown-divider"></li>
                                <li><a class="dropdown-item" href="encargado_pedidos_lista.php"><i class="fas fa-inbox me-2 text-primary"></i> Bandeja de Pedidos</a></li>
                            <?php endif; ?>
                        </ul>
                    </li>
                <?php endif; ?>
                
                <?php if ($mostrar_novedades): ?>
                <li class="nav-item dropdown">
                    <a class="nav-link dropdown-toggle <?php echo (strpos(basename($_SERVER['PHP_SELF']), 'asistencia_') !== false) ? 'active' : ''; ?>" href="#" role="button" data-bs-toggle="dropdown">
                        <i class="fas fa-clipboard-check"></i> Personal
                    </a>
                    <ul class="dropdown-menu">
                        <li><a class="dropdown-item" href="asistencia_tomar.php"><i class="fas fa-pen-square me-2"></i> Generar Parte</a></li>
                        <li><hr class="dropdown-divider"></li>
                        <li><a class="dropdown-item" href="asistencia_estadisticas.php"><i class="fas fa-chart-bar me-2"></i> Estadísticas</a></li>
                        <li><a class="dropdown-item" href="asistencia_listado_general.php"><i class="fas fa-history me-2"></i> Historial de Partes</a></li>
                    </ul>
                </li>
                <?php endif; ?>

                <li class="nav-item dropdown">
                    <a class="nav-link dropdown-toggle <?php echo (strpos(basename($_SERVER['PHP_SELF']), 'tarea_') !== false || basename($_SERVER['PHP_SELF']) == 'tareas_lista.php') ? 'active' : ''; ?>"
                        href="#" id="tareasDropdown" role="button" data-bs-toggle="dropdown">
                        <i class="fas fa-tasks"></i> Tareas
                    </a>
                    <ul class="dropdown-menu">
                        <li><a class="dropdown-item" href="tareas_lista.php"><i class="fas fa-list-ul me-2"></i> Ver Todas las Tareas</a></li>
                        <?php if (tiene_permiso('crear_tarea_directa', $pdo)): ?>
                            <li><hr class="dropdown-divider"></li>
                            <li><a class="dropdown-item" href="tarea_crear.php"><i class="fas fa-plus-circle me-2 text-success"></i> Crear Nueva Tarea</a></li>
                        <?php endif; ?>
                    </ul>
                </li>

                <?php 
                $puede_crear_avisos = tiene_permiso('acceso_avisos_crear', $pdo) || tiene_permiso('acceso_avisos_gestionar', $pdo);
                ?>
                <?php if ($puede_crear_avisos): ?>
                    <li class="nav-item dropdown">
                        <a class="nav-link dropdown-toggle <?php echo (strpos(basename($_SERVER['PHP_SELF']), 'avisos_') !== false || basename($_SERVER['PHP_SELF']) == 'avisos.php') ? 'active' : ''; ?>"
                            href="#" id="avisosDropdown" role="button" data-bs-toggle="dropdown">
                            <i class="fas fa-blog"></i> Blog
                        </a>
                        <ul class="dropdown-menu">
                            <li><a class="dropdown-item" href="avisos.php"><i class="fas fa-eye me-2"></i> Ver Blog</a></li>
                            <li><hr class="dropdown-divider"></li>
                            <?php if (tiene_permiso('acceso_avisos_crear', $pdo)): ?>
                                <li><a class="dropdown-item" href="avisos_crear.php"><i class="fas fa-plus-circle me-2 text-success"></i> Crear Entrada</a></li>
                            <?php endif; ?>
                            <?php if (tiene_permiso('acceso_avisos_gestionar', $pdo)): ?>
                                <li><a class="dropdown-item" href="avisos_lista.php"><i class="fas fa-list-alt me-2 text-warning"></i> Administrar</a></li>
                            <?php endif; ?>
                        </ul>
                    </li>
                <?php else: ?>
                    <li class="nav-item">
                        <a class="nav-link <?php echo basename($_SERVER['PHP_SELF']) == 'avisos.php' ? 'active' : ''; ?>" href="avisos.php">
                            <i class="fas fa-blog"></i> Blog
                        </a>
                    </li>
                <?php endif; ?>

                <li class="nav-item">
                    <a class="nav-link <?php echo basename($_SERVER['PHP_SELF']) == 'chat.php' ? 'active' : ''; ?>" href="chat.php">
                        <i class="fas fa-comments"></i> Chat
                    </a>
                </li>
                
                <?php if ($mostrar_menu_admin): ?>
                    <li class="nav-item dropdown">
                        <a class="nav-link dropdown-toggle <?php echo (strpos(basename($_SERVER['PHP_SELF']), 'admin_') !== false || strpos(basename($_SERVER['PHP_SELF']), 'encargado_') !== false) ? 'active' : ''; ?>"
                            href="#" id="adminDropdown" role="button" data-bs-toggle="dropdown">
                            <i class="fas fa-cog"></i> Admin
                        </a>
                        <ul class="dropdown-menu">
                            <?php if (tiene_permiso('admin_usuarios', $pdo)): ?> <li><a class="dropdown-item" href="admin_usuarios.php"><i class="fas fa-users-cog me-2"></i>Usuarios</a></li> <?php endif; ?>
                            <?php if (tiene_permiso('admin_roles', $pdo)): ?> <li><a class="dropdown-item" href="admin_roles.php"><i class="fas fa-user-shield me-2"></i>Roles y Permisos</a></li> <?php endif; ?>
                            <?php if (tiene_permiso('admin_usuarios', $pdo) || tiene_permiso('admin_roles', $pdo)): ?> <li><hr class="dropdown-divider"></li> <?php endif; ?>
                            <?php if (tiene_permiso('admin_categorias', $pdo)): ?> <li><a class="dropdown-item" href="admin_categorias.php"><i class="fas fa-tags me-2"></i>Categorías</a></li> <?php endif; ?>
                            <?php if (tiene_permiso('admin_areas', $pdo)): ?> <li><a class="dropdown-item" href="admin_areas.php"><i class="fas fa-map-marker-alt me-2"></i>Gestionar Áreas</a></li> <?php endif; ?>
                            <?php if (tiene_permiso('admin_destinos', $pdo)): ?> <li><a class="dropdown-item" href="admin_destinos.php"><i class="fas fa-compass me-2"></i>Gestionar Destinos</a></li> <?php endif; ?>
                            <?php if (tiene_permiso('admin_remitos', $pdo)): ?> <li><hr class="dropdown-divider"></li> <li><a class="dropdown-item" href="admin_remitos.php"><i class="fas fa-file-invoice-dollar me-2 text-success"></i> Ver Remitos/Facturas</a></li> <?php endif; ?>
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
                        <span class="badge bg-danger rounded-pill"
                            id="notification-badge"
                            style="display: <?php echo $notificaciones_no_leidas > 0 ? 'inline-block' : 'none'; ?>;">
                            <?php echo $notificaciones_no_leidas > 99 ? '99+' : $notificaciones_no_leidas; ?>
                        </span>
                    </a>
                    <ul class="dropdown-menu dropdown-menu-end shadow border-0" aria-labelledby="notificationsDropdown"
                        id="notifications-list" style="width: 350px;">
                        <li><a class="dropdown-item text-center text-muted p-3"><i class="fas fa-spinner fa-spin me-2"></i>Cargando...</a></li>
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
                        <li><a class="dropdown-item" href="perfil.php"><i class="fas fa-user-circle me-2 text-primary"></i> Perfil</a></li>
                        <li><hr class="dropdown-divider my-1"></li>
                        <li><a class="dropdown-item text-danger" href="logout.php"><i class="fas fa-sign-out-alt me-2"></i> Salir</a></li>
                    </ul>
                </li>
            </ul>
        </div>
    </div>
</nav>
<div id="notificationToastContainer" class="toast-container position-fixed bottom-0 end-0 p-3"></div>

<script>
    let lastNotificationId = 0; 
    const currentUserId = <?php echo json_encode($id_usuario_nav); ?>;
    const notificationSound = new Audio('assets/alert.mp3');

    document.addEventListener("DOMContentLoaded", function() {
        if(currentUserId > 0) {
            pollNotifications(true); 
            setInterval(() => pollNotifications(false), 3000);
            const dE = document.getElementById('notificationsDropdown');
            if(dE) dE.addEventListener('show.bs.dropdown', loadFullList);
        }
    });

    function pollNotifications(isFirstLoad) {
        fetch(`notificaciones_fetch.php?last_id=${lastNotificationId}`)
            .then(r => r.json())
            .then(data => {
                updateBadge(data.unread_count);
                if(data.new_notifications && data.new_notifications.length > 0) {
                    if (!isFirstLoad) {
                        data.new_notifications.forEach(notif => showToast(notif));
                        playSound();
                    }
                }
                if(data.max_id > lastNotificationId) {
                    lastNotificationId = data.max_id;
                }
            })
            .catch(e => {});
    }

    function showToast(notif) {
        if (notif.tipo === 'chat' && window.location.pathname.includes('chat.php')) {
            const urlParams = new URLSearchParams(window.location.search);
            const currentChatId = urlParams.get('chat_id');
            if(notif.url && notif.url.includes('chat_id=' + currentChatId)) {
                return; 
            }
        }
        
        const container = document.getElementById('notificationToastContainer');
        if(!container) return;

        let icon='fa-info-circle', color='bg-info', title='Notificación';
        let delay = 8000;

        if (notif.tipo === 'aviso_global') { 
            icon='fa-bullhorn'; color='bg-danger'; title='🚨 ALERTA DE NUEVO AVISO'; delay = 15000; 
        } 
        else if(notif.tipo === 'chat') { icon='fa-comment-dots'; color='bg-primary'; title='Nuevo Mensaje'; } 
        else if(notif.tipo.includes('tarea')) { icon='fa-tasks'; color='bg-success'; title='Novedad Tarea'; }
        
        const html = `
            <div class="toast align-items-center text-white ${color} border-0" role="alert" aria-live="assertive" aria-atomic="true" data-bs-autohide="true" data-bs-delay="${delay}">
                <div class="d-flex">
                    <div class="toast-body cursor-pointer" onclick="location.href='${notif.url}'" style="cursor:pointer;">
                        <i class="fas ${icon} me-2"></i> <strong>${title}</strong>
                        <div class="small mt-1">${notif.mensaje}</div>
                    </div>
                    <button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast"></button>
                </div>
            </div>`;
        container.insertAdjacentHTML('beforeend', html);
        const toastEl = container.lastElementChild;
        try { const toast = new bootstrap.Toast(toastEl); toast.show(); toastEl.addEventListener('hidden.bs.toast', () => toastEl.remove()); } catch(e){}
    }

    function loadFullList() {
        const list = document.getElementById('notifications-list');
        list.innerHTML = '<li class="text-center p-3"><i class="fas fa-spinner fa-spin me-2"></i>Cargando...</a></li>';
        fetch('notificaciones_fetch.php?last_id=0') 
            .then(r => r.json())
            .then(data => {
                list.innerHTML = '';
                if(data.notifications && data.notifications.length > 0) {
                    data.notifications.forEach(n => {
                        const cls = n.leida == 0 ? 'fw-bold bg-light' : '';
                        list.innerHTML += `<li><a class="dropdown-item ${cls} text-wrap py-2" href="${n.url}" onclick="markRead(${n.id_notificacion})"><div class="small">${n.mensaje}</div><div class="text-muted" style="font-size:0.75rem">${n.fecha_creacion}</div></a></li>`;
                    });
                } else {
                    list.innerHTML = '<li class="p-3 text-center text-muted">Sin notificaciones</li>';
                }
                list.innerHTML += '<li><hr class="dropdown-divider"></li><li><a class="dropdown-item text-center small" href="notificaciones_lista.php">Ver todas</a></li>';
            });
    }

    function updateBadge(count) {
        const b = document.getElementById('notification-badge');
        if(b) { b.textContent = count > 99 ? '99+' : count; b.style.display = count > 0 ? 'inline-block' : 'none'; }
    }
    function markRead(id) { fetch(`notificaciones_mark_read.php?id=${id}`); }
    function playSound() { try { notificationSound.play().catch(e=>{}); } catch(e){} }
</script>