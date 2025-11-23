<?php
// Archivo: admin_empresas.php
session_start();
require_once 'conexion.php';
require_once 'funciones_permisos.php';

if (!tiene_permiso('admin_ascensores', $pdo)) { header("Location: dashboard.php"); exit; }

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['crear'])) {
        $pdo->prepare("INSERT INTO empresas_mantenimiento (nombre, email_contacto, telefono) VALUES (?,?,?)")
            ->execute([$_POST['nombre'], $_POST['email'], $_POST['telefono']]);
    }
    if (isset($_POST['borrar'])) {
        $pdo->prepare("DELETE FROM empresas_mantenimiento WHERE id_empresa = ?")->execute([$_POST['id_borrar']]);
    }
}
$empresas = $pdo->query("SELECT * FROM empresas_mantenimiento")->fetchAll();
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <?php include 'head.php'; ?>
    <title>Empresas de Mantenimiento</title>
</head>
<body>
    <?php include 'navbar.php'; ?>
    <div class="container mt-4">
        <h3><i class="fas fa-building"></i> Empresas de Mantenimiento</h3>
        <div class="row">
            <div class="col-md-4">
                <div class="card p-3">
                    <h5>Nueva Empresa</h5>
                    <form method="POST">
                        <div class="mb-2"><label>Nombre</label><input type="text" name="nombre" class="form-control" required></div>
                        <div class="mb-2"><label>Email (Alertas)</label><input type="email" name="email" class="form-control" required></div>
                        <div class="mb-3"><label>Teléfono</label><input type="text" name="telefono" class="form-control"></div>
                        <button type="submit" name="crear" class="btn btn-success w-100">Guardar</button>
                    </form>
                </div>
            </div>
            <div class="col-md-8">
                <table class="table table-bordered bg-white">
                    <thead class="table-dark"><tr><th>Nombre</th><th>Email</th><th>Teléfono</th><th></th></tr></thead>
                    <tbody>
                        <?php foreach($empresas as $e): ?>
                        <tr>
                            <td><?php echo htmlspecialchars($e['nombre']); ?></td>
                            <td><?php echo htmlspecialchars($e['email_contacto']); ?></td>
                            <td><?php echo htmlspecialchars($e['telefono']); ?></td>
                            <td>
                                <form method="POST" onsubmit="return confirm('¿Borrar?');">
                                    <input type="hidden" name="id_borrar" value="<?php echo $e['id_empresa']; ?>">
                                    <button class="btn btn-danger btn-sm"><i class="fas fa-trash"></i></button>
                                </form>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>