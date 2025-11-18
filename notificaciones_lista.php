<?php
session_start();
include 'conexion.php';

// 1. Verificar si el usuario está logueado
if (!isset($_SESSION['usuario_id'])) {
    header("Location: login.php");
    exit();
}

$id_usuario = $_SESSION['usuario_id'];
$notifications = [];
$error = null;

try {
    // 2. Marcar todas las notificaciones pendientes como leídas al acceder a esta lista
    $sql_mark_all_read = "UPDATE notificaciones SET leida = 1 WHERE id_usuario_destino = :id_user AND leida = 0";
    $stmt_mark = $pdo->prepare($sql_mark_all_read);
    $stmt_mark->execute([':id_user' => $id_usuario]);
    
    // 3. Obtener TODAS las notificaciones (limitando a un número razonable, ej. 50, para no sobrecargar)
    $sql_fetch = "SELECT id_notificacion, mensaje, url, tipo, fecha_creacion, leida 
                  FROM notificaciones 
                  WHERE id_usuario_destino = :id_user 
                  ORDER BY fecha_creacion DESC 
                  LIMIT 50"; // Limitar a las 50 más recientes
    
    $stmt_fetch = $pdo->prepare($sql_fetch);
    $stmt_fetch->execute([':id_user' => $id_usuario]);
    $notifications = $stmt_fetch->fetchAll(PDO::FETCH_ASSOC);

} catch (PDOException $e) {
    $error = "Error al cargar notificaciones: " . $e->getMessage();
    error_log($error);
}

include 'navbar.php'; 
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Todas las Notificaciones</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>
<body>

<div class="container mt-4">
    <h1 class="mb-4">
        <i class="fas fa-bell me-2"></i> Todas las Notificaciones
    </h1>

    <?php if ($error): ?>
        <div class="alert alert-danger" role="alert">
            <?php echo $error; ?>
        </div>
    <?php endif; ?>

    <?php if (empty($notifications)): ?>
        <div class="alert alert-info text-center" role="alert">
            No tienes notificaciones registradas.
        </div>
    <?php else: ?>
        <div class="list-group">
            <?php foreach ($notifications as $notif): ?>
                <?php
                    $is_read = $notif['leida'] == 1;
                    $class = $is_read ? 'list-group-item-light text-muted' : 'list-group-item-primary fw-bold';
                    $icon = $notif['tipo'] === 'chat' ? 'fas fa-comment' : 'fas fa-info-circle';
                    $message_link = htmlspecialchars($notif['url']);
                ?>
                <a href="<?php echo $message_link; ?>" class="list-group-item list-group-item-action <?php echo $class; ?>">
                    <div class="d-flex w-100 justify-content-between">
                        <h6 class="mb-1">
                            <i class="<?php echo $icon; ?> me-2"></i> 
                            <?php echo htmlspecialchars($notif['mensaje']); ?>
                        </h6>
                        <small class="text-end <?php echo $is_read ? '' : 'text-primary'; ?>">
                            <?php echo (new DateTime($notif['fecha_creacion']))->format('d/m/Y H:i'); ?>
                        </small>
                    </div>
                    <small class="<?php echo $is_read ? 'text-muted' : ''; ?>">
                         Haz clic para ver el detalle.
                    </small>
                </a>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>

</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>