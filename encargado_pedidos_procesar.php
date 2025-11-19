<?php
// Archivo: encargado_pedidos_procesar.php (NUEVO)
// Procesa acciones desde la bandeja del encargado (ej. rechazar pedido)

include 'acceso_protegido.php'; // Incluye sesión y conexión $pdo

// 1. Asegurar que solo Admin o Encargado y sea POST
if (!isset($_SESSION['usuario_rol']) || !in_array($_SESSION['usuario_rol'], ['admin', 'encargado']) || $_SERVER['REQUEST_METHOD'] !== 'POST') {
    header("Location: dashboard.php");
    exit();
}

// 2. Identificar la acción y obtener datos
$action = $_POST['action'] ?? '';
$id_pedido = 0;
$mensaje_resultado = '';
$alerta_tipo_resultado = 'danger'; // Default a error

if ($action === 'rechazar') {
    $id_pedido = (int)($_POST['id_pedido_reject'] ?? 0);
    $motivo_rechazo = trim($_POST['motivo_rechazo'] ?? '');

    if ($id_pedido > 0) {
        $pdo->beginTransaction();
        try {
            // Obtener datos del pedido ANTES de rechazar (para notificación)
            $sql_get_pedido = "SELECT numero_orden, id_auxiliar FROM pedidos_trabajo WHERE id_pedido = :id";
            $stmt_get = $pdo->prepare($sql_get_pedido);
            $stmt_get->execute([':id' => $id_pedido]);
            $pedido_info = $stmt_get->fetch(PDO::FETCH_ASSOC);

            if ($pedido_info) {
                // Cambiar estado a 'rechazado'
                $sql_update = "UPDATE pedidos_trabajo SET estado_pedido = 'rechazado'
                               WHERE id_pedido = :id AND estado_pedido = 'pendiente_encargado'"; // Solo si estaba pendiente
                $stmt_update = $pdo->prepare($sql_update);
                $stmt_update->execute([':id' => $id_pedido]);

                if ($stmt_update->rowCount() > 0) {
                    $mensaje_resultado = "Pedido #" . ($pedido_info['numero_orden'] ?? $id_pedido) . " rechazado correctamente.";
                    $alerta_tipo_resultado = 'success';

                    // Notificar al Auxiliar que lo creó
                    if (!empty($pedido_info['id_auxiliar'])) {
                        $id_auxiliar_destino = $pedido_info['id_auxiliar'];
                        $mensaje_notif = "El pedido {$pedido_info['numero_orden']} que registraste ha sido RECHAZADO.";
                        if (!empty($motivo_rechazo)) {
                            $mensaje_notif .= " Motivo: " . $motivo_rechazo;
                        }
                        // La URL puede llevar a la lista de pedidos del auxiliar, resaltando el rechazado
                        $url_notif = "pedidos_lista_usuario.php?highlight_pedido={$id_pedido}";

                        $sql_insert_notif = "INSERT INTO notificaciones (id_usuario_destino, mensaje, url, tipo, leida, fecha_creacion)
                                             VALUES (:id_destino, :mensaje, :url, 'pedido_rechazado', 0, NOW())";
                        $stmt_notif = $pdo->prepare($sql_insert_notif);
                        $stmt_notif->execute([
                            ':id_destino' => $id_auxiliar_destino,
                            ':mensaje' => $mensaje_notif,
                            ':url' => $url_notif
                        ]);
                    }
                    $pdo->commit();
                } else {
                    $pdo->rollBack(); // No se cambió nada (quizás ya estaba rechazado o no existía)
                    $mensaje_resultado = "Error: El pedido #" . ($pedido_info['numero_orden'] ?? $id_pedido) . " no se pudo rechazar (posiblemente ya fue procesado o no existe).";
                    $alerta_tipo_resultado = 'warning';
                }
            } else {
                 $pdo->rollBack();
                 $mensaje_resultado = "Error: No se encontró el pedido #{$id_pedido}.";
                 $alerta_tipo_resultado = 'danger';
            }

        } catch (PDOException $e) {
            $pdo->rollBack();
            $mensaje_resultado = "Error de base de datos al rechazar el pedido: " . $e->getMessage();
            $alerta_tipo_resultado = 'danger';
            error_log("Error DB al rechazar pedido #{$id_pedido}: " . $e->getMessage());
        }
    } else {
        $mensaje_resultado = "ID de pedido inválido para rechazar.";
        $alerta_tipo_resultado = 'warning';
    }

} else {
    // Acción desconocida
    $mensaje_resultado = "Acción no reconocida.";
    $alerta_tipo_resultado = 'danger';
}

// 3. Guardar mensaje en sesión y redirigir de vuelta a la bandeja
$_SESSION['encargado_pedido_mensaje'] = $mensaje_resultado;
$_SESSION['encargado_pedido_alerta_tipo'] = $alerta_tipo_resultado;
header("Location: encargado_pedidos_lista.php");
exit();
?>