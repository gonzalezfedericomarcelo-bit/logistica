<?php
// Archivo: asistencia_guardar.php (ACTUALIZADO PARA TIPOS DE ASISTENCIA)
session_start();
include 'conexion.php';
include_once 'funciones_permisos.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') { header("Location: dashboard.php"); exit(); }

$id_creador = $_SESSION['usuario_id'];
$nombre_creador = $_SESSION['usuario_nombre'];
$fecha = $_POST['fecha'];
$titulo = $_POST['titulo_parte'] ?? ''; 
$datos = $_POST['datos'] ?? [];

// Estado del parte
if (tiene_permiso('asistencia_aprobar_directo', $pdo)) {
    $estado_inicial = 'aprobado';
} else {
    $estado_inicial = 'pendiente';
}

try {
    $pdo->beginTransaction();

    // 1. Cabecera
    $stmt_head = $pdo->prepare("INSERT INTO asistencia_partes (fecha, id_creador, observaciones_generales, estado) VALUES (:f, :c, :o, :e)");
    $stmt_head->execute([':f'=>$fecha, ':c'=>$id_creador, ':o'=>$titulo, ':e'=>$estado_inicial]);
    $id_parte = $pdo->lastInsertId();

    // 2. Detalles
    $sql_det = "INSERT INTO asistencia_detalles (id_parte, id_usuario, presente, tipo_asistencia, observacion_individual) 
                VALUES (:idp, :idu, :pres, :tipo, :obs)";
    $stmt_det = $pdo->prepare($sql_det);

    foreach ($datos as $id_user => $info) {
        $tipo = $info['tipo'] ?? 'presente';
        $observacion = trim($info['obs'] ?? '');
        
        // Lógica de 'presente' (booleano para estadísticas simples)
        // 'ausente' es el único que cuenta como falta (0).
        // 'tarde' y 'comision' cuentan como presente (1) para el servicio.
        $es_presente = ($tipo === 'ausente') ? 0 : 1;

        $stmt_det->execute([
            ':idp' => $id_parte,
            ':idu' => $id_user,
            ':pres' => $es_presente,
            ':tipo' => $tipo,
            ':obs' => $observacion
        ]);
    }

    // 3. Notificación
    if ($estado_inicial === 'pendiente') {
        $sql_auditores = "SELECT u.id_usuario FROM usuarios u JOIN rol_permiso rp ON u.rol = rp.nombre_rol WHERE rp.clave_permiso = 'asistencia_auditar_pendientes'";
        $auditores = $pdo->query($sql_auditores)->fetchAll(PDO::FETCH_COLUMN);
        
        if ($auditores) {
            $msg = "⚠️ Parte pendiente de revisión ($fecha) enviado por $nombre_creador.";
            $url = "asistencia_listado_general.php";
            $stmt_n = $pdo->prepare("INSERT INTO notificaciones (id_usuario_destino, tipo, mensaje, url, leida, fecha_creacion) VALUES (?, 'tarea_verificada', ?, ?, 0, NOW())");
            foreach ($auditores as $au) $stmt_n->execute([$au, $msg, $url]);
        }
    }

    $pdo->commit();
    header("Location: asistencia_listado_general.php?msg=" . ($estado_inicial==='pendiente' ? 'Parte enviado' : 'Parte guardado'));

} catch (Exception $e) {
    $pdo->rollBack();
    echo "Error: " . $e->getMessage();
}
?>