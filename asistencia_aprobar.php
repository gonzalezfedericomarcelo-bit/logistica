<?php
// Archivo: asistencia_aprobar.php (CORREGIDO PARA USAR PERMISOS REALES)
session_start();
include 'conexion.php';
include_once 'funciones_permisos.php';

// 1. VERIFICACIÓN POR PERMISO (Ya no por nombre "Cañete")
// Si no es admin y no tiene el permiso específico, lo saca.
if (!isset($_SESSION['usuario_rol']) || 
   ($_SESSION['usuario_rol'] !== 'admin' && !tiene_permiso('asistencia_aprobar_directo', $pdo))) {
    die("Acceso denegado: No tienes permiso para aprobar partes.");
}

$id_parte = (int)($_GET['id'] ?? 0);

if ($id_parte > 0) {
    try {
        // 2. Cambiar estado a aprobado
        $stmt = $pdo->prepare("UPDATE asistencia_partes SET estado = 'aprobado' WHERE id_parte = :id");
        $stmt->execute([':id' => $id_parte]);

        // 3. Obtener creador para notificarle
        $stmt_info = $pdo->prepare("SELECT id_creador FROM asistencia_partes WHERE id_parte = :id");
        $stmt_info->execute([':id' => $id_parte]);
        $id_creador = $stmt_info->fetchColumn();

        // 4. Notificar al creador (si no es el mismo que aprueba)
        if ($id_creador && $id_creador != $_SESSION['usuario_id']) {
            $msg = "Tu parte de novedades ha sido APROBADO.";
            $url = "asistencia_pdf.php?id=" . $id_parte; 
            
            $sql_notif = "INSERT INTO notificaciones (id_usuario_destino, tipo, mensaje, url, leida, fecha_creacion) VALUES (:dest, 'tarea_verificada', :msg, :url, 0, NOW())";
            $stmt_n = $pdo->prepare($sql_notif);
            $stmt_n->execute([':dest' => $id_creador, ':msg' => $msg, ':url' => $url]);
        }
        
        // Redirigir con éxito
        header("Location: asistencia_listado_general.php?msg=aprobado_ok");

    } catch (PDOException $e) {
        die("Error de base de datos: " . $e->getMessage());
    }
} else {
    header("Location: asistencia_listado_general.php");
}
?>