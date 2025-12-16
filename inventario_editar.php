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
            ':serv' => $_POST['servicio_ubicacion'],
            ':n_resp' => $_POST['nombre_responsable'],
            ':n_jefe' => $_POST['nombre_jefe_servicio'],
            ':obs' => $_POST['observaciones'],
            ':id' => $id
        ]);
        
        // Si se subieron nuevas firmas (opcional, implementar si se requiere cambiar firmas digitalmente aquí)
        // Por ahora solo editamos datos.
        
        header("Location: inventario_lista.php"); exit();
    } catch (PDOException $e) {
        $error = "Error al actualizar: " . $e->getMessage();
    }
}

// Obtener datos actuales
$stmt = $pdo->prepare("SELECT * FROM inventario_cargos WHERE id_cargo = ?");
$stmt->execute([$id]);
$item = $stmt->fetch(PDO::FETCH_ASSOC);
if (!$item) die("Bien no encontrado");

// Obtener Destinos para select
$destinos = $pdo->query("SELECT nombre FROM destinos_internos ORDER BY nombre")->fetchAll(PDO::FETCH_COLUMN);
// Obtener Areas
$areas = $pdo->query("SELECT nombre FROM areas ORDER BY nombre")->fetchAll(PDO::FETCH_COLUMN);
$ubicaciones = array_merge($destinos, $areas); // Unir todo para dar opciones
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Editar Bien</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body>
    <?php include 'navbar.php'; ?>
    <div class="container mt-5">
        <div class="card shadow">
            <div class="card-header bg-warning text-dark">
                <h4 class="mb-0">Editar / Transferir Bien #<?php echo $id; ?></h4>
            </div>
            <div class="card-body">
                <?php if(isset($error)) echo "<div class='alert alert-danger'>$error</div>"; ?>
                
                <form method="POST">
                    <div class="row g-3">
                        <div class="col-md-8">
                            <label class="form-label fw-bold">Elemento</label>
                            <input type="text" name="elemento" class="form-control" value="<?php echo htmlspecialchars($item['elemento']); ?>" required>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label fw-bold">Código</label>
                            <input type="text" name="codigo_inventario" class="form-control" value="<?php echo htmlspecialchars($item['codigo_inventario']); ?>">
                        </div>
                        
                        <div class="col-md-12">
                            <label class="form-label fw-bold">Ubicación Actual (Destino/Área)</label>
                            <input type="text" name="servicio_ubicacion" list="listaUbicaciones" class="form-control" value="<?php echo htmlspecialchars($item['servicio_ubicacion']); ?>" required>
                            <datalist id="listaUbicaciones">
                                <?php foreach($ubicaciones as $u): ?>
                                    <option value="<?php echo htmlspecialchars($u); ?>">
                                <?php endforeach; ?>
                            </datalist>
                            <small class="text-muted">Escriba o seleccione la nueva ubicación para transferir.</small>
                        </div>

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
    <?php include 'footer.php'; ?>
</body>
</html>