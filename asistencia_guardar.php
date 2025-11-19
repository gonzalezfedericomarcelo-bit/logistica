<?php
// Archivo: asistencia_guardar.php (JERARQUÍA ESTRICTA POR NOMBRE)
session_start();
include 'conexion.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') { header("Location: dashboard.php"); exit(); }

$id_creador = $_SESSION['usuario_id'];
$nombre_creador = $_SESSION['usuario_nombre'];
$fecha = $_POST['fecha'];
$titulo = $_POST['titulo_parte'] ?? ''; 
$datos = $_POST['datos'] ?? [];

// --- CORRECCIÓN AQUÍ: JERARQUÍA ESTRICTA ---
// Solo Cañete se aprueba a sí mismo. El resto (incluso admins) van a aprobación.
$es_jefe_supremo = (stripos($nombre_creador, 'Cañete') !== false);

$estado_inicial = $es_jefe_supremo ? 'aprobado' : 'pendiente';

try {
    $pdo->beginTransaction();

    // 1. Insertar Cabecera
    $sql_head = "INSERT INTO asistencia_partes (fecha, id_creador, observaciones_generales, estado) VALUES (:fecha, :id_creador, :obs, :estado)";
    $stmt_head = $pdo->prepare($sql_head);
    $stmt_head->execute([
        ':fecha' => $fecha, 
        ':id_creador' => $id_creador, 
        ':obs' => $titulo,
        ':estado' => $estado_inicial
    ]);
    $id_parte = $pdo->lastInsertId();

    // 2. Insertar Detalles
    $sql_det = "INSERT INTO asistencia_detalles (id_parte, id_usuario, presente, observacion_individual) VALUES (:id_parte, :id_user, :presente, :obs)";
    $stmt_det = $pdo->prepare($sql_det);

    foreach ($datos as $id_user => $info) {
        $es_presente = isset($info['presente']) ? 1 : 0;
        $observacion = trim($info['obs'] ?? '');
        $stmt_det->execute([':id_parte' => $id_parte, ':id_user' => $id_user, ':presente' => $es_presente, ':obs' => $observacion]);
    }

    // 3. Notificar a Cañete si es pendiente
    if ($estado_inicial === 'pendiente') {
        // Busca a Cañete para avisarle
        $sql_jefe = "SELECT id_usuario FROM usuarios WHERE nombre_completo LIKE '%Cañete%' LIMIT 1";
        $stmt_jefe = $pdo->query($sql_jefe);
        $id_jefe = $stmt_jefe->fetchColumn();

        if ($id_jefe) {
            $mensaje = "⚠️ Parte pendiente de aprobación enviado por $nombre_creador.";
            $url_destino = "asistencia_listado_general.php?resaltar=" . $id_parte;
            
            $sql_notif = "INSERT INTO notificaciones (id_usuario_destino, tipo, mensaje, url, leida, fecha_creacion) VALUES (:dest, 'tarea_verificada', :msg, :url, 0, NOW())";
            $stmt_notif = $pdo->prepare($sql_notif);
            $stmt_notif->execute([':dest' => $id_jefe, ':msg' => $mensaje, ':url' => $url_destino]);
        }
    }

    $pdo->commit();
    
    // Redirección inteligente
    if ($estado_inicial === 'aprobado') {
        header("Location: asistencia_pdf.php?id=" . $id_parte);
    } else {
        header("Location: asistencia_listado_general.php?msg=pendiente");
    }
    exit();

} catch (Exception $e) {
    $pdo->rollBack();
    die("Error al guardar: " . $e->getMessage());
}
?>
