<?php
// chat_marcar_leido.php
session_start();
include 'conexion.php'; // Asegúrate de que esta ruta sea correcta

if (isset($_SESSION['usuario_id']) && isset($_POST['remitente_id'])) {
    $mi_id = $_SESSION['usuario_id'];
    $remitente_id = intval($_POST['remitente_id']);

    try {
        // Marca como leídos los mensajes que me envió el remitente
        $sql = "UPDATE chat SET leido = 1 WHERE id_usuario = :remitente_id AND id_destino = :mi_id AND leido = 0";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([':remitente_id' => $remitente_id, ':mi_id' => $mi_id]);
    } catch (PDOException $e) {
        // Para depuración: error_log("Error al marcar como leído: " . $e->getMessage());
    }
}
?>