<?php
// Archivo: asistencia_guardar.php
session_start();
include 'conexion.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') { header("Location: dashboard.php"); exit(); }

$id_creador = $_SESSION['usuario_id'];
$fecha = $_POST['fecha'];
$titulo = $_POST['titulo_parte'] ?? ''; 
$datos = $_POST['datos'] ?? [];

try {
    $pdo->beginTransaction();

    $sql_head = "INSERT INTO asistencia_partes (fecha, id_creador, observaciones_generales) VALUES (:fecha, :id_creador, :obs)";
    $stmt_head = $pdo->prepare($sql_head);
    $stmt_head->execute([':fecha' => $fecha, ':id_creador' => $id_creador, ':obs' => $titulo]);
    $id_parte = $pdo->lastInsertId();

    $sql_det = "INSERT INTO asistencia_detalles (id_parte, id_usuario, presente, observacion_individual) VALUES (:id_parte, :id_user, :presente, :obs)";
    $stmt_det = $pdo->prepare($sql_det);

    foreach ($datos as $id_user => $info) {
        $es_presente = isset($info['presente']) ? 1 : 0;
        $observacion = trim($info['obs'] ?? '');
        
        // Guardar incluso si está ausente, para que aparezca en el PDF
        $stmt_det->execute([
            ':id_parte' => $id_parte,
            ':id_user' => $id_user,
            ':presente' => $es_presente,
            ':obs' => $observacion
        ]);
    }

    $pdo->commit();
    
    // Redirigir al PDF
    header("Location: asistencia_pdf.php?id=" . $id_parte);
    exit();

} catch (Exception $e) {
    $pdo->rollBack();
    die("Error al guardar: " . $e->getMessage());
}
?>