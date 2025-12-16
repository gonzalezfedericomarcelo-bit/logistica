<?php
// Archivo: inventario_transferir.php
session_start();
include 'conexion.php';
if (!isset($_SESSION['usuario_id'])) die("Acceso denegado");

$id = (int)$_GET['id'];
$bien = $pdo->query("SELECT * FROM inventario_cargos WHERE id_cargo = $id")->fetch(PDO::FETCH_ASSOC);

if($_SERVER['REQUEST_METHOD']=='POST'){
    $nueva_ubi = $_POST['nueva_ubicacion'];
    $nuevo_resp = $_POST['nuevo_responsable'];
    $obs = $_POST['observacion'];
    
    // Historial
    $sqlH = "INSERT INTO historial_movimientos (id_bien, tipo_movimiento, ubicacion_anterior, ubicacion_nueva, responsable_anterior, responsable_nuevo, usuario_registro, observacion_movimiento) VALUES (?, 'Transferencia', ?, ?, ?, ?, ?, ?)";
    $pdo->prepare($sqlH)->execute([$id, $bien['servicio_ubicacion'], $nueva_ubi, $bien['nombre_responsable'], $nuevo_resp, $_SESSION['usuario_id'], $obs]);
    
    // Update
    $pdo->prepare("UPDATE inventario_cargos SET servicio_ubicacion=?, nombre_responsable=? WHERE id_cargo=?")->execute([$nueva_ubi, $nuevo_resp, $id]);
    header("Location: inventario_lista.php"); exit();
}
// Obtener lista destinos+areas para el datalist
$lugares = $pdo->query("SELECT nombre FROM destinos_internos UNION SELECT nombre FROM areas ORDER BY 1")->fetchAll(PDO::FETCH_COLUMN);
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Transferir</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body>
    <div class="container mt-5">
        <div class="card shadow border-primary">
            <div class="card-header bg-primary text-white">Transferir: <?php echo $bien['elemento']; ?></div>
            <div class="card-body">
                <p><strong>Actual:</strong> <?php echo $bien['servicio_ubicacion']; ?> (<?php echo $bien['nombre_responsable']; ?>)</p>
                <form method="POST">
                    <div class="mb-3">
                        <label>Nueva Ubicación</label>
                        <input type="text" name="nueva_ubicacion" list="dl_lugares" class="form-control" required>
                        <datalist id="dl_lugares"><?php foreach($lugares as $l) echo "<option value='$l'>"; ?></datalist>
                    </div>
                    <div class="mb-3">
                        <label>Nuevo Responsable</label>
                        <input type="text" name="nuevo_responsable" class="form-control" required>
                    </div>
                    <div class="mb-3"><label>Motivo</label><input type="text" name="observacion" class="form-control" required></div>
                    <button class="btn btn-primary w-100">Confirmar Transferencia</button>
                </form>
            </div>
        </div>
    </div>
</body>
</html>