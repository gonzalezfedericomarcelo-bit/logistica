<?php
// Archivo: asistencia_guardar.php
session_start();
include 'conexion.php';
include_once 'funciones_permisos.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header("Location: dashboard.php");
    exit();
}

$id_creador = $_SESSION['usuario_id'];
$nombre_creador = $_SESSION['usuario_nombre'];
$fecha = $_POST['fecha'];
$titulo = $_POST['titulo_parte'] ?? '';
$datos = $_POST['datos'] ?? [];

// Determinar estado inicial
if (tiene_permiso('asistencia_aprobar_directo', $pdo)) {
    $estado_inicial = 'aprobado';
} else {
    $estado_inicial = 'pendiente';
}

try {
    $pdo->beginTransaction();

    // 1. Crear cabecera del parte
    $stmt = $pdo->prepare("INSERT INTO asistencia_partes (fecha, id_creador, observaciones_generales, estado) VALUES (?, ?, ?, ?)");
    $stmt->execute([$fecha, $id_creador, $titulo, $estado_inicial]);
    $id_parte = $pdo->lastInsertId();

    // 2. Guardar detalles
    $stmt_detalle = $pdo->prepare("INSERT INTO asistencia_detalles 
        (id_parte, id_usuario, presente, tipo_asistencia, observacion_individual) 
        VALUES (?, ?, ?, ?, ?)");

    foreach ($datos as $id_usuario => $info) {
        $tipo = $info['tipo'] ?? 'presente';
        $observacion = trim($info['obs'] ?? '');
        
        // Lógica de presente (1 o 0) para estadísticas simples
        $es_presente = ($tipo === 'ausente') ? 0 : 1;

        $stmt_detalle->execute([
            $id_parte,
            $id_usuario,
            $es_presente,
            $tipo,
            $observacion // Se guarda EXACTAMENTE lo que escribiste
        ]);
    }

    // 3. Notificar si es necesario
    if ($estado_inicial === 'pendiente') {
        // (Lógica de notificación existente...)
        $sql_auditores = "SELECT u.id_usuario FROM usuarios u 
                         JOIN rol_permiso rp ON u.rol = rp.nombre_rol 
                         WHERE rp.clave_permiso = 'asistencia_auditar_pendientes'";
        $auditores = $pdo->query($sql_auditores)->fetchAll(PDO::FETCH_COLUMN);
        
        if ($auditores) {
            $msg = "⚠️ Parte pendiente de revisión ($fecha) enviado por $nombre_creador.";
            $url = "asistencia_listado_general.php?resaltar=$id_parte";
            $stmt_notif = $pdo->prepare("INSERT INTO notificaciones (id_usuario_destino, tipo, mensaje, url, leida, fecha_creacion) VALUES (?, 'tarea_verificada', ?, ?, 0, NOW())");
            foreach ($auditores as $id_auditor) {
                $stmt_notif->execute([$id_auditor, $msg, $url]);
            }
        }
    }

    $pdo->commit();
    header("Location: asistencia_listado_general.php?msg=guardado_ok");

} catch (Exception $e) {
    $pdo->rollBack();
    die("Error al guardar: " . $e->getMessage());
}
?>