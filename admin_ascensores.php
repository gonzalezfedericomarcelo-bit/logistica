<?php
// Archivo: admin_ascensores.php (CORREGIDO: ASIGNACIÓN DE EMPRESA)
session_start();
require_once 'conexion.php';
require_once 'funciones_permisos.php';

if (!tiene_permiso('admin_ascensores', $pdo)) {
    header("Location: mantenimiento_ascensores.php");
    exit;
}

// --- PROCESAR FORMULARIO (CREAR) ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['crear'])) {
    $nombre = $_POST['nombre'];
    $ubicacion = $_POST['ubicacion'];
    $serie = $_POST['serie'];
    $id_empresa = $_POST['id_empresa']; // Nuevo campo
    
    $stmt = $pdo->prepare("INSERT INTO ascensores (nombre, ubicacion, nro_serie, id_empresa) VALUES (?,?,?,?)");
    $stmt->execute([$nombre, $ubicacion, $serie, $id_empresa]);
    header("Location: admin_ascensores.php"); exit;
}

// --- BORRAR ---
if (isset($_GET['borrar'])) {
    $pdo->prepare("DELETE FROM ascensores WHERE id_ascensor = ?")->execute([$_GET['borrar']]);
    header("Location: admin_ascensores.php"); exit;
}

// --- CONSULTAS ---
// 1. Lista de Ascensores con su Empresa
$sql = "SELECT a.*, e.nombre as nombre_empresa 
        FROM ascensores a 
        LEFT JOIN empresas_mantenimiento e ON a.id_empresa = e.id_empresa
        ORDER BY a.nombre";
$lista = $pdo->query($sql)->fetchAll(PDO::FETCH_ASSOC);

// 2. Lista de Empresas (Para el select)
$empresas = $pdo->query("SELECT * FROM empresas_mantenimiento WHERE activo = 1")->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <?php include 'head.php'; ?>
    <title>Administrar Unidades</title>
</head>
<body>
    <?php include 'navbar.php'; ?>
    
    <div class="container mt-5">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h2><i class="fas fa-cogs"></i> Configuración de Unidades</h2>
            <a href="admin_empresas.php" class="btn btn-secondary"><i class="fas fa-building"></i> Gestionar Empresas</a>
        </div>

        <div class="row">
            <div class="col-md-4">
                <div class="card shadow-sm">
                    <div class="card-header bg-primary text-white">Agregar Nuevo Ascensor</div>
                    <div class="card-body">
                        <form method="POST">
                            <div class="mb-3">
                                <label>Nombre / Identificador</label>
                                <input type="text" name="nombre" class="form-control" required placeholder="Ej: Ascensor Central">
                            </div>
                            <div class="mb-3">
                                <label>Ubicación Física</label>
                                <input type="text" name="ubicacion" class="form-control" placeholder="Ej: Ala Norte, Guardia">
                            </div>
                            <div class="mb-3">
                                <label>Nro. Serie / Modelo</label>
                                <input type="text" name="serie" class="form-control">
                            </div>
                            
                            <div class="mb-3">
                                <label class="fw-bold">Empresa de Mantenimiento</label>
                                <select name="id_empresa" class="form-select" required>
                                    <option value="">-- Seleccionar Empresa --</option>
                                    <?php foreach($empresas as $e): ?>
                                        <option value="<?php echo $e['id_empresa']; ?>">
                                            <?php echo htmlspecialchars($e['nombre']); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                                <div class="form-text text-muted">
                                    Los reclamos de este ascensor irán a esta empresa.
                                </div>
                            </div>

                            <button type="submit" name="crear" class="btn btn-success w-100">Guardar Unidad</button>
                        </form>
                    </div>
                </div>
            </div>

            <div class="col-md-8">
                <table class="table table-bordered table-hover bg-white shadow-sm">
                    <thead class="table-light">
                        <tr>
                            <th>Nombre</th>
                            <th>Ubicación</th>
                            <th>Empresa Asignada</th>
                            <th>Acción</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach($lista as $l): ?>
                        <tr>
                            <td><?php echo htmlspecialchars($l['nombre']); ?></td>
                            <td><?php echo htmlspecialchars($l['ubicacion']); ?></td>
                            <td class="fw-bold text-primary">
                                <?php echo htmlspecialchars($l['nombre_empresa'] ?? 'Sin Asignar'); ?>
                            </td>
                            <td>
                                <a href="?borrar=<?php echo $l['id_ascensor']; ?>" class="btn btn-danger btn-sm" onclick="return confirm('¿Borrar? Se perderá el historial.')"><i class="fas fa-trash"></i></a>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
                <div class="text-end mt-3">
                    <a href="mantenimiento_ascensores.php" class="btn btn-link">Volver al Tablero Principal</a>
                </div>
            </div>
        </div>
    </div>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>