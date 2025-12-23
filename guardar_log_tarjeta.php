<?php
// guardar_log_tarjeta.php
session_start();
include 'conexion.php'; 

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $accion = $_POST['accion'] ?? 'Interacción';
    $usuario = $_SESSION['usuario_nombre'] ?? 'Usuario';
    $id_usuario_actual = $_SESSION['usuario_id'] ?? 0;
    $no_mostrar = $_POST['no_mostrar'] ?? 'false';

    // 1. GENERAR EL MENSAJE
    $mensaje_notificacion = "El usuario $usuario realizó: $accion en la Tarjeta Navideña.";

    // 2. BUSCAR ADMINS Y NOTIFICARLES (Usando tu sistema existente)
    // Asumimos que tu tabla se llama 'notificaciones' y tiene campos estándar.
    // Si tu tabla tiene otros nombres (ej: 'avisos_usuarios'), ajusta el INSERT.
    try {
        $stmt_admins = $pdo->query("SELECT id_usuario FROM usuarios WHERE rol IN ('admin', 'encargado')");
        while ($row = $stmt_admins->fetch()) {
            $id_admin = $row['id_usuario'];
            
            // Insertar en la tabla de notificaciones del sistema
            // AJUSTA LOS NOMBRES DE COLUMNAS SI ES NECESARIO (ej: id_usuario_destino, texto, fecha)
            $sql_notif = "INSERT INTO notificaciones (id_usuario, mensaje, fecha, leido, tipo) 
                          VALUES (:id_dest, :msj, NOW(), 0, 'sistema')";
            $stmt_insert = $pdo->prepare($sql_notif);
            $stmt_insert->execute([
                ':id_dest' => $id_admin, 
                ':msj' => $mensaje_notificacion
            ]);
        }
    } catch (Exception $e) {
        // Si falla la notificación, no detenemos el resto
    }

    // 3. GUARDAR PREFERENCIA "NO VOLVER A MOSTRAR"
    if ($no_mostrar === 'true' && $id_usuario_actual > 0) {
        // Esto asume que creaste la columna o usas una existente
        try {
            // Si la columna no existe, este query fallará silenciosamente
            $sql_update = "UPDATE usuarios SET mostrar_tarjeta_navidad = 0 WHERE id_usuario = :id";
            $stmt_upd = $pdo->prepare($sql_update);
            $stmt_upd->execute([':id' => $id_usuario_actual]);
            
            // También lo guardamos en sesión por si acaso
            $_SESSION['navidad_mostrada'] = true;
        } catch (Exception $e) {}
    }
}
?>