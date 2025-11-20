<?php
// Archivo: asistencia_guardar.php (LOGICA BASADA EN ROLES Y PERMISOS)
session_start();
include 'conexion.php';
include_once 'funciones_permisos.php'; // Necesario para verificar permisos

if ($_SERVER['REQUEST_METHOD'] !== 'POST') { header("Location: dashboard.php"); exit(); }

$id_creador = $_SESSION['usuario_id'];
$nombre_creador = $_SESSION['usuario_nombre'];
$fecha = $_POST['fecha'];
$titulo = $_POST['titulo_parte'] ?? ''; 
$datos = $_POST['datos'] ?? [];

// 1. DETERMINAR ESTADO DEL PARTE (Rol vs Nombre fijo)
// Antes: Se aprobaba solo si el nombre era "Cañete".
// Ahora: Se aprueba si el rol tiene el permiso 'asistencia_aprobar_directo'.
if (tiene_permiso('asistencia_aprobar_directo', $pdo)) {
    $estado_inicial = 'aprobado';
} else {
    $estado_inicial = 'pendiente';
}

try {
    $pdo->beginTransaction();

    // 2. Insertar Cabecera
    $sql_head = "INSERT INTO asistencia_partes (fecha, id_creador, observaciones_generales, estado) VALUES (:fecha, :id_creador, :obs, :estado)";
    $stmt_head = $pdo->prepare($sql_head);
    $stmt_head->execute([
        ':fecha' => $fecha, 
        ':id_creador' => $id_creador, 
        ':obs' => $titulo,
        ':estado' => $estado_inicial
    ]);
    
    $id_parte = $pdo->lastInsertId();

    // 3. Insertar Detalles
    $sql_det = "INSERT INTO asistencia_detalles (id_parte, id_usuario, presente, observacion_individual) VALUES (:id_parte, :id_user, :presente, :obs)";
    $stmt_det = $pdo->prepare($sql_det);

    foreach ($datos as $id_user => $info) {
        $es_presente = isset($info['presente']) ? 1 : 0;
        $observacion = trim($info['obs'] ?? '');
        $stmt_det->execute([':id_parte' => $id_parte, ':id_user' => $id_user, ':presente' => $es_presente, ':obs' => $observacion]);
    }

    // 4. Notificar al Auditor si quedó pendiente
    if ($estado_inicial === 'pendiente') {
        // Buscamos dinámicamente a los usuarios que tengan permiso de auditar (NO por nombre fijo)
        // Esta consulta busca usuarios cuyo ROL tenga el permiso 'asistencia_auditar_pendientes'
        $sql_auditores = "
            SELECT u.id_usuario 
            FROM usuarios u
            JOIN rol_permiso rp ON u.rol = rp.nombre_rol
            WHERE rp.clave_permiso = 'asistencia_auditar_pendientes'
        ";
        $stmt_auditores = $pdo->query($sql_auditores);
        $auditores = $stmt_auditores->fetchAll(PDO::FETCH_COLUMN);

        if ($auditores) {
            $mensaje = "⚠️ Parte pendiente de revisión enviado por $nombre_creador.";
            // Usamos 'asistencia_aprobar.php' o el listado general para revisar
            $url_destino = "asistencia_listado_general.php?filtro=pendientes"; 
            
            $sql_notif = "INSERT INTO notificaciones (id_usuario_destino, tipo, mensaje, url, leida, fecha_creacion) VALUES (:dest, 'tarea_verificada', :msg, :url, 0, NOW())";
            $stmt_notif = $pdo->prepare($sql_notif);

            foreach ($auditores as $id_auditor) {
                $stmt_notif->execute([':dest' => $id_auditor, ':msg' => $mensaje, ':url' => $url_destino]);
            }
        }
    }

    $pdo->commit();
    
    // Redirección según resultado
    if ($estado_inicial === 'pendiente') {
        header("Location: asistencia_listado_general.php?msg=Parte enviado a revisión");
    } else {
        header("Location: asistencia_listado_general.php?msg=Parte guardado y aprobado");
    }

} catch (Exception $e) {
    $pdo->rollBack();
    echo "Error al guardar: " . $e->getMessage(); 
    exit;
}
?>