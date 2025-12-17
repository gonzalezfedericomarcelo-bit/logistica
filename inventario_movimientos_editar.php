<?php
// Archivo: inventario_movimientos_editar.php
session_start();
include 'conexion.php';
include 'funciones_permisos.php';

// Verificar permiso específico de editar historial
if (!isset($_SESSION['usuario_id']) || !tiene_permiso('inventario_historial_editar', $pdo)) {
    die("Acceso denegado.");
}

$id = $_GET['id'] ?? 0;
$mensaje = '';

// Procesar Formulario
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $fecha = $_POST['fecha_movimiento'];
    $obs = $_POST['observacion_movimiento'];
    $ubi_ant = $_POST['ubicacion_anterior'];
    $ubi_nue = $_POST['ubicacion_nueva'];
    
    $stmtUpd = $pdo->prepare("UPDATE historial_movimientos SET fecha_movimiento = ?, observacion_movimiento = ?, ubicacion_anterior = ?, ubicacion_nueva = ? WHERE id_movimiento = ?");
    if ($stmtUpd->execute([$fecha, $obs, $ubi_ant, $ubi_nue, $id])) {
        header("Location: inventario_movimientos.php"); exit();
    } else {
        $mensaje = "Error al actualizar.";
    }
}

// Obtener datos actuales
$stmt = $pdo->prepare("SELECT * FROM historial_movimientos WHERE id_movimiento = ?");
$stmt->execute([$id]);
$mov = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$mov) die("Movimiento no encontrado.");
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Editar Movimiento</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body class="bg-light">
    <div class="container mt-5">
        <div class="card shadow mw-100" style="max-width: 600px; margin: auto;">
            <div class="card-header bg-primary text-white fw-bold">Editar Registro de Historial #<?php echo $id; ?></div>
            <div class="card-body">
                <form method="POST">
                    <div class="mb-3">
                        <label class="form-label fw-bold">Fecha y Hora</label>
                        <input type="datetime-local" name="fecha_movimiento" class="form-control" value="<?php echo date('Y-m-d\TH:i', strtotime($mov['fecha_movimiento'])); ?>" required>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label fw-bold">Ubicación Anterior (Texto)</label>
                        <input type="text" name="ubicacion_anterior" class="form-control" value="<?php echo htmlspecialchars($mov['ubicacion_anterior']); ?>">
                    </div>

                    <div class="mb-3">
                        <label class="form-label fw-bold">Ubicación Nueva (Texto)</label>
                        <input type="text" name="ubicacion_nueva" class="form-control" value="<?php echo htmlspecialchars($mov['ubicacion_nueva']); ?>">
                    </div>

                    <div class="mb-3">
                        <label class="form-label fw-bold">Observaciones / Detalle</label>
                        <textarea name="observacion_movimiento" class="form-control" rows="4"><?php echo htmlspecialchars($mov['observacion_movimiento']); ?></textarea>
                    </div>

                    <div class="d-flex justify-content-between">
                        <a href="inventario_movimientos.php" class="btn btn-secondary">Cancelar</a>
                        <button type="submit" class="btn btn-primary">Guardar Cambios</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</body>
</html>