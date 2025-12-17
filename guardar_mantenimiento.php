<?php
// Incluir conexión
include 'conexion.php'; 

// Recibir datos
$id_bien = $_POST['id'];
$tipo_accion = $_POST['tipo_accion'];
$observaciones = $_POST['observaciones'];
$fecha_hoy = date('Y-m-d H:i:s'); 

// Manejo de fechas: Si están vacías, las definimos como NULL para SQL
$nueva_fecha_carga = !empty($_POST['fecha_carga']) ? $_POST['fecha_carga'] : null;
$nueva_fecha_ph = !empty($_POST['fecha_ph']) ? $_POST['fecha_ph'] : null;

// --- PASO 1: ACTUALIZAR EL BIEN (Tabla inventario) ---
// La lógica COALESCE(?, fecha_carga) significa: si le paso un valor, usa ese. Si le paso NULL, mantén el que ya existe.

$sql_update = "UPDATE inventario SET 
               estado = 'Activo', 
               fecha_carga = COALESCE(?, fecha_carga), 
               fecha_ph = COALESCE(?, fecha_ph),
               ultima_modificacion = ? 
               WHERE id = ?";

$stmt = $conn->prepare($sql_update);
// "sssi" corresponde a: string (fecha), string (fecha), string (fecha), integer (id)
$stmt->bind_param("sssi", $nueva_fecha_carga, $nueva_fecha_ph, $fecha_hoy, $id_bien);
$actualizo_bien = $stmt->execute();


// --- PASO 2: GUARDAR EL HISTORIAL (Tabla inventario_movimientos) ---
// Ajusta 'UsuarioSistema' si tienes una variable de sesión tipo $_SESSION['nombre_usuario']

$detalle_completo = "Tipo: " . strtoupper($tipo_accion) . ". " . $observaciones;

$sql_historial = "INSERT INTO inventario_movimientos (id_bien, tipo_movimiento, fecha_movimiento, detalle, usuario_responsable) 
                  VALUES (?, 'Mantenimiento', NOW(), ?, 'Admin')"; 

$stmt_hist = $conn->prepare($sql_historial);
$stmt_hist->bind_param("is", $id_bien, $detalle_completo);
$guardo_historial = $stmt_hist->execute();

?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link href="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.min.css" rel="stylesheet">
    <title>Guardando...</title>
    <style>body { font-family: sans-serif; background-color: #f8f9fa; }</style>
</head>
<body>

<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

<script>
    <?php if ($actualizo_bien && $guardo_historial): ?>
        // CASO ÉXITO
        Swal.fire({
            title: '¡Mantenimiento Registrado!',
            html: 'Se actualizó el bien y se guardó en el historial correctamente.',
            icon: 'success',
            confirmButtonColor: '#3085d6',
            confirmButtonText: 'Ir al Historial de Movimientos',
            allowOutsideClick: false
        }).then((result) => {
            if (result.isConfirmed) {
                window.location.href = 'inventario_movimientos.php';
            }
        });

    <?php else: ?>
        // CASO ERROR
        Swal.fire({
            title: 'Error',
            text: 'No se pudo guardar la información. Intenta nuevamente.',
            icon: 'error',
            confirmButtonText: 'Volver'
        }).then((result) => {
            window.history.back();
        });
    <?php endif; ?>
</script>

</body>
</html>