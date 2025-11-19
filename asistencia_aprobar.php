<?php
// Archivo: asistencia_aprobar.php
session_start();
include 'conexion.php';

// Solo Cañete o Admin pueden aprobar
$u_nombre = $_SESSION['usuario_nombre'] ?? '';
if (stripos($u_nombre, 'Cañete') === false && $_SESSION['usuario_rol'] !== 'admin') {
    die("Acceso denegado.");
}

$id_parte = $_GET['id'] ?? 0;

if ($id_parte > 0) {
    try {
        // 1. Cambiar estado a aprobado
        $stmt = $pdo->prepare("UPDATE asistencia_partes SET estado = 'aprobado' WHERE id_parte = :id");
        $stmt->execute([':id' => $id_parte]);

        // 2. Obtener creador para notificarle
        $stmt_info = $pdo->prepare("SELECT id_creador FROM asistencia_partes WHERE id_parte = :id");
        $stmt_info->execute([':id' => $id_parte]);
        $id_creador = $stmt_info->fetchColumn();

        // 3. Notificar al creador (si no es el mismo que aprueba)
        if ($id_creador && $id_creador != $_SESSION['usuario_id']) {
            $msg = "Tu parte de novedades ha sido APROBADO por el Encargado.";
            $url = "asistencia_pdf.php?id=" . $id_parte; // Link directo al PDF final
            
            $sql_notif = "INSERT INTO notificaciones (id_usuario_destino, tipo, mensaje, url, leida, fecha_creacion) VALUES (:dest, 'tarea_verificada', :msg, :url, 0, NOW())";
            $pdo->prepare($sql_notif)->execute([':dest' => $id_creador, ':msg' => $msg, ':url' => $url]);
        }

        // Redirigir al PDF final
        header("Location: asistencia_pdf.php?id=" . $id_parte);
        exit();

    } catch (Exception $e) {
        die("Error al aprobar: " . $e->getMessage());
    }
} else {
    header("Location: asistencia_listado_general.php");
}
?>
