<?php
// Archivo: inventario_baja.php
session_start();
include 'conexion.php';
if (!isset($_SESSION['usuario_id'])) die("Acceso denegado");

$id = (int)$_GET['id'];
$bien = $pdo->query("SELECT * FROM inventario_cargos WHERE id_cargo = $id")->fetch(PDO::FETCH_ASSOC);

if($_SERVER['REQUEST_METHOD']=='POST'){
    $motivo = $_POST['motivo'];
    // Historial
    $pdo->prepare("INSERT INTO historial_movimientos (id_bien, tipo_movimiento, usuario_registro, observacion_movimiento) VALUES (?, 'Baja', ?, ?)")->execute([$id, $_SESSION['usuario_id'], $motivo]);
    // Update Estado
    $pdo->prepare("UPDATE inventario_cargos SET estado='Baja' WHERE id_cargo=?")->execute([$id]);
    header("Location: inventario_lista.php"); exit();
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Baja</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body>
    <div class="container mt-5">
        <div class="card shadow border-danger">
            <div class="card-header bg-danger text-white">Dar de Baja: <?php echo $bien['elemento']; ?></div>
            <div class="card-body">
                <form method="POST">
                    <div class="mb-3"><label>Motivo de Baja</label><textarea name="motivo" class="form-control" required></textarea></div>
                    <button class="btn btn-danger w-100">Confirmar Baja</button>
                </form>
            </div>
        </div>
    </div>
</body>
</html>