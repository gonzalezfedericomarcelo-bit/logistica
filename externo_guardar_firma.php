<?php
// Archivo: externo_guardar_firma.php (SIN DEPENDER DE USUARIO SOLICITANTE)
session_start();
include 'conexion.php';
date_default_timezone_set('America/Argentina/Buenos_Aires');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') die("Acceso incorrecto");

$token = $_POST['token'] ?? '';
$firma_data = $_POST['firma_base64'] ?? '';

if (empty($token) || empty($firma_data)) die("Datos incompletos.");

try {
    $pdo->beginTransaction();

    // 1. Validar Token (Usando id_token)
    $stmt = $pdo->prepare("SELECT * FROM inventario_transferencias_pendientes WHERE token_hash = ? AND estado = 'pendiente'");
    $stmt->execute([$token]);
    $solicitud = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$solicitud) die("Solicitud inválida.");

    $id_cargo = $solicitud['id_bien'];

    // 2. Guardar Imagen
    $ruta_carpeta = 'uploads/firmas/';
    if (!file_exists($ruta_carpeta)) mkdir($ruta_carpeta, 0777, true);
    
    $nombre_archivo = 'firma_ext_' . $id_cargo . '_' . time() . '.png';
    $ruta_final = $ruta_carpeta . $nombre_archivo;
    
    $data = base64_decode(explode(',', $firma_data)[1]);
    file_put_contents($ruta_final, $data);

    // 3. Actualizar Bien
    $sqlUpd = "UPDATE inventario_cargos SET 
               destino_principal = ?, servicio_ubicacion = ?, 
               nombre_responsable = ?, nombre_jefe_servicio = ?,
               firma_responsable_path = ?  
               WHERE id_cargo = ?";
    $pdo->prepare($sqlUpd)->execute([
        $solicitud['nuevo_destino_id'],
        $solicitud['nuevo_destino_nombre'], 
        $solicitud['nuevo_responsable_nombre'],
        $solicitud['nuevo_jefe_nombre'],
        $ruta_final, 
        $id_cargo
    ]);

    // 4. Historial (CORREGIDO: Usamos ID 1 o 0 porque la columna id_usuario_solicitante no existe)
    $usuario_historial = 1; // Usamos 1 por defecto para que no falle la FK de usuarios
    $detalle = "Transferencia Externa. Recibe: " . $solicitud['nuevo_responsable_nombre'];
    
    $pdo->prepare("INSERT INTO historial_movimientos (id_bien, usuario_registro, tipo_movimiento, detalles, fecha_movimiento) VALUES (?, ?, 'TRANSFERENCIA', ?, NOW())")
        ->execute([$id_cargo, $usuario_historial, $detalle]);

    // 5. CERRAR SOLICITUD (Usando id_token)
    $id_pk = $solicitud['id_token'];
    $pdo->prepare("UPDATE inventario_transferencias_pendientes SET estado = 'completado', fecha_firma = NOW() WHERE id_token = ?")->execute([$id_pk]);

    $pdo->commit();

    echo "<h1>Transferencia Exitosa</h1><p>Puede cerrar esta ventana.</p>";

} catch (Exception $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    die("Error: " . $e->getMessage());
}
?>