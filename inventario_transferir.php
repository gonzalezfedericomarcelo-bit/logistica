<?php
// Archivo: inventario_transferir.php
session_start();
include 'conexion.php';
if (!isset($_SESSION['usuario_id'])) die("Acceso denegado");

$id = (int)$_GET['id'];
$bien = $pdo->query("SELECT * FROM inventario_cargos WHERE id_cargo = $id")->fetch(PDO::FETCH_ASSOC);

if($_SERVER['REQUEST_METHOD']=='POST'){
    $nueva_ubi = $_POST['nombre_area_final']; // Viene del JS
    $nuevo_resp = $_POST['nuevo_responsable'];
    $obs = $_POST['observacion'];
    
    // Historial
    $sqlH = "INSERT INTO historial_movimientos (id_bien, tipo_movimiento, ubicacion_anterior, ubicacion_nueva, responsable_anterior, responsable_nuevo, usuario_registro, observacion_movimiento) VALUES (?, 'Transferencia', ?, ?, ?, ?, ?, ?)";
    $pdo->prepare($sqlH)->execute([$id, $bien['servicio_ubicacion'], $nueva_ubi, $bien['nombre_responsable'], $nuevo_resp, $_SESSION['usuario_id'], $obs]);
    
    // Update
    $pdo->prepare("UPDATE inventario_cargos SET servicio_ubicacion=?, nombre_responsable=? WHERE id_cargo=?")->execute([$nueva_ubi, $nuevo_resp, $id]);
    header("Location: inventario_lista.php"); exit();
}

// Cargar Destinos
$destinos = $pdo->query("SELECT * FROM destinos_internos ORDER BY nombre ASC")->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Transferir Bien</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <script src="https://code.jquery.com/jquery-3.7.0.min.js"></script>
</head>
<body>
    <div class="container mt-5">
        <div class="card shadow border-primary">
            <div class="card-header bg-primary text-white">Transferir: <?php echo $bien['elemento']; ?></div>
            <div class="card-body">
                <p><strong>Actual:</strong> <?php echo $bien['servicio_ubicacion']; ?> (<?php echo $bien['nombre_responsable']; ?>)</p>
                
                <form method="POST">
                    <div class="mb-3">
                        <label class="fw-bold">1. Nuevo Destino</label>
                        <select id="select_destino" class="form-select" required>
                            <option value="">-- Seleccione --</option>
                            <?php foreach($destinos as $d): ?>
                                <option value="<?php echo $d['id_destino']; ?>"><?php echo htmlspecialchars($d['nombre']); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="fw-bold">2. Nueva Área</label>
                        <select id="select_area" class="form-select" required>
                             <option value="">-- Seleccione Destino Primero --</option>
                        </select>
                    </div>
                    <input type="hidden" name="nombre_area_final" id="nombre_area_final">

                    <div class="mb-3">
                        <label>Nuevo Responsable</label>
                        <input type="text" name="nuevo_responsable" class="form-control" required>
                    </div>
                    <div class="mb-3"><label>Motivo</label><input type="text" name="observacion" class="form-control" required></div>
                    <button class="btn btn-primary w-100">Confirmar Transferencia</button>
                    <a href="inventario_lista.php" class="btn btn-light w-100 mt-2">Cancelar</a>
                </form>
            </div>
        </div>
    </div>

    <script>
    $(document).ready(function() {
        $('#select_destino').change(function() {
            var idDestino = $(this).val();
            $('#select_area').html('<option>Cargando...</option>');
            
            $.ajax({
                url: 'ajax_obtener_areas.php',
                type: 'GET',
                data: { id_destino: idDestino },
                dataType: 'json',
                success: function(areas) {
                    let options = '<option value="">-- Seleccione Área --</option>';
                    areas.forEach(function(area) {
                        options += `<option value="${area.nombre}">${area.nombre}</option>`;
                    });
                    $('#select_area').html(options);
                    $('#nombre_area_final').val(""); // Resetear
                }
            });
        });

        $('#select_area').change(function() {
            $('#nombre_area_final').val($(this).val());
        });
    });
    </script>
</body>
</html>