<?php
// Archivo: inventario_editar.php
session_start();
include 'conexion.php';
include 'funciones_permisos.php';

if (!isset($_SESSION['usuario_id']) || !tiene_permiso('acceso_inventario', $pdo)) {
    header("Location: dashboard.php"); exit();
}

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if ($id <= 0) die("ID Inválido");

// Procesar Guardado
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    try {
        // Obtenemos el nombre final de la ubicación
        // Si seleccionó área, usamos el nombre del área. Si no (o es 'Otro'), usamos lo que venga.
        $ubicacion_final = $_POST['nombre_area_final']; // Campo oculto llenado por JS o PHP

        // Validación simple
        if (empty($ubicacion_final)) {
            $ubicacion_final = "Sin Asignar";
        }

        $sql = "UPDATE inventario_cargos SET 
                elemento = :elem, 
                codigo_inventario = :cod,
                servicio_ubicacion = :serv,
                nombre_responsable = :n_resp,
                nombre_jefe_servicio = :n_jefe,
                observaciones = :obs
                WHERE id_cargo = :id";
        
        $stmt = $pdo->prepare($sql);
        $stmt->execute([
            ':elem' => $_POST['elemento'],
            ':cod' => $_POST['codigo_inventario'],
            ':serv' => $ubicacion_final,
            ':n_resp' => $_POST['nombre_responsable'],
            ':n_jefe' => $_POST['nombre_jefe_servicio'],
            ':obs' => $_POST['observaciones'],
            ':id' => $id
        ]);
        
        header("Location: inventario_lista.php"); exit();
    } catch (PDOException $e) {
        $error = "Error al actualizar: " . $e->getMessage();
    }
}

// Obtener datos actuales del bien
$stmt = $pdo->prepare("SELECT * FROM inventario_cargos WHERE id_cargo = ?");
$stmt->execute([$id]);
$item = $stmt->fetch(PDO::FETCH_ASSOC);
if (!$item) die("Bien no encontrado");

// --- LÓGICA INTELIGENTE PARA PRE-SELECCIONAR LOS DROPDOWNS ---
$ubi_actual = $item['servicio_ubicacion'];
$id_destino_pre = 0;
$id_area_pre = 0;

// 1. Buscar si el nombre actual coincide con un ÁREA
$stmt_area = $pdo->prepare("SELECT id_area, id_destino FROM areas WHERE nombre = ? LIMIT 1");
$stmt_area->execute([$ubi_actual]);
$res_area = $stmt_area->fetch(PDO::FETCH_ASSOC);

if ($res_area) {
    $id_area_pre = $res_area['id_area'];
    $id_destino_pre = $res_area['id_destino'];
} else {
    // 2. Si no, buscar si coincide con un DESTINO (caso items viejos o asignados directo al destino)
    $stmt_dest = $pdo->prepare("SELECT id_destino FROM destinos_internos WHERE nombre = ? LIMIT 1");
    $stmt_dest->execute([$ubi_actual]);
    $res_dest = $stmt_dest->fetchColumn();
    if ($res_dest) {
        $id_destino_pre = $res_dest;
    }
}

// Obtener TODOS los destinos para el primer select
$destinos = $pdo->query("SELECT * FROM destinos_internos ORDER BY nombre ASC")->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Editar Bien</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <script src="https://code.jquery.com/jquery-3.7.0.min.js"></script>
</head>
<body>
    <?php include 'navbar.php'; ?>
    <div class="container mt-5">
        <div class="card shadow">
            <div class="card-header bg-warning text-dark">
                <h4 class="mb-0">Editar Bien #<?php echo $id; ?></h4>
            </div>
            <div class="card-body">
                <?php if(isset($error)) echo "<div class='alert alert-danger'>$error</div>"; ?>
                
                <form method="POST" id="formEditar">
                    <div class="row g-3">
                        <div class="col-md-8">
                            <label class="form-label fw-bold">Elemento</label>
                            <input type="text" name="elemento" class="form-control" value="<?php echo htmlspecialchars($item['elemento']); ?>" required>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label fw-bold">Código</label>
                            <input type="text" name="codigo_inventario" class="form-control" value="<?php echo htmlspecialchars($item['codigo_inventario']); ?>">
                        </div>
                        
                        <div class="col-12"><hr class="text-muted"></div>
                        <div class="col-md-6">
                            <label class="form-label fw-bold text-primary">1. Destino (Sede/Edificio)</label>
                            <select id="select_destino" class="form-select" required>
                                <option value="">-- Seleccione Destino --</option>
                                <?php foreach($destinos as $d): ?>
                                    <option value="<?php echo $d['id_destino']; ?>" <?php echo ($d['id_destino'] == $id_destino_pre) ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($d['nombre']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-bold text-primary">2. Área (Oficina/Servicio)</label>
                            <select id="select_area" class="form-select" required>
                                <option value="">-- Seleccione Destino Primero --</option>
                            </select>
                        </div>
                        <input type="hidden" name="nombre_area_final" id="nombre_area_final" value="<?php echo htmlspecialchars($item['servicio_ubicacion']); ?>">
                        <div class="col-12"><hr class="text-muted"></div>

                        <div class="col-md-6">
                            <label class="form-label fw-bold">Responsable</label>
                            <input type="text" name="nombre_responsable" class="form-control" value="<?php echo htmlspecialchars($item['nombre_responsable']); ?>">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-bold">Jefe Servicio</label>
                            <input type="text" name="nombre_jefe_servicio" class="form-control" value="<?php echo htmlspecialchars($item['nombre_jefe_servicio']); ?>">
                        </div>

                        <div class="col-12">
                            <label class="form-label">Observaciones</label>
                            <textarea name="observaciones" class="form-control" rows="3"><?php echo htmlspecialchars($item['observaciones']); ?></textarea>
                        </div>
                    </div>

                    <div class="mt-4 text-end">
                        <a href="inventario_lista.php" class="btn btn-secondary">Cancelar</a>
                        <button type="submit" class="btn btn-primary">Guardar Cambios</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script>
    $(document).ready(function() {
        const preSelectedDestino = "<?php echo $id_destino_pre; ?>";
        const preSelectedArea = "<?php echo $id_area_pre; ?>";

        // Función para cargar áreas
        function cargarAreas(idDestino, idAreaSeleccionada = null) {
            if (!idDestino) {
                $('#select_area').html('<option value="">-- Seleccione Destino Primero --</option>');
                return;
            }

            $.ajax({
                url: 'ajax_obtener_areas.php',
                type: 'GET',
                data: { id_destino: idDestino },
                dataType: 'json',
                success: function(areas) {
                    let options = '<option value="">-- Seleccione Área --</option>';
                    areas.forEach(function(area) {
                        const selected = (idAreaSeleccionada && area.id_area == idAreaSeleccionada) ? 'selected' : '';
                        options += `<option value="${area.nombre}" ${selected}>${area.nombre}</option>`; // Usamos el nombre como valor
                    });
                    $('#select_area').html(options);
                    
                    // Si no hay áreas, permitir que el destino sea la ubicación
                    if (areas.length === 0) {
                        const nombreDestino = $("#select_destino option:selected").text().trim();
                        $('#nombre_area_final').val(nombreDestino);
                         $('#select_area').html('<option value="' + nombreDestino + '" selected>Solo Destino General</option>');
                    } else {
                         // Actualizar el input hidden cuando cambia el área
                         actualizarInputOculto();
                    }
                }
            });
        }

        // Carga inicial
        if (preSelectedDestino) {
            cargarAreas(preSelectedDestino, preSelectedArea);
        }

        // Cambio de Destino
        $('#select_destino').change(function() {
            cargarAreas($(this).val());
        });

        // Cambio de Área (Actualizar input oculto para enviar al POST)
        $('#select_area').change(function() {
            actualizarInputOculto();
        });

        function actualizarInputOculto() {
            const area = $('#select_area').val();
            if (area) {
                $('#nombre_area_final').val(area);
            } else {
                // Si no selecciona área, toma el nombre del destino
                const destinoTxt = $("#select_destino option:selected").text().trim();
                if($("#select_destino").val()) $('#nombre_area_final').val(destinoTxt);
            }
        }
    });
    </script>

    <?php include 'footer.php'; ?>
</body>
</html>