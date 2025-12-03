<?php
// Archivo: admin_motivos.php
session_start();
include 'conexion.php';
include_once 'funciones_permisos.php';

// VERIFICACIÓN DE SEGURIDAD
// Si no es admin o no tiene permiso, lo saca.
if (!isset($_SESSION['usuario_id'])) {
    header("Location: login.php");
    exit();
}
// Ajusta 'administrar_usuarios' al permiso real de admin que uses, o usa tu lógica de roles
if (!tiene_permiso('administrar_usuarios', $pdo)) { 
    header("Location: dashboard.php");
    exit();
}

$mensaje = '';

// LÓGICA: AGREGAR O ELIMINAR
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    
    // 1. Agregar Nuevo Motivo Fijo
    if (isset($_POST['nuevo_motivo'])) {
        $nuevo = trim($_POST['nuevo_motivo']);
        if (!empty($nuevo)) {
            // Verificar si ya existe para no duplicar
            $stmt_check = $pdo->prepare("SELECT COUNT(*) FROM configuracion_novedades WHERE descripcion = ?");
            $stmt_check->execute([$nuevo]);
            if ($stmt_check->fetchColumn() == 0) {
                $stmt_add = $pdo->prepare("INSERT INTO configuracion_novedades (descripcion) VALUES (?)");
                if ($stmt_add->execute([$nuevo])) {
                    $mensaje = '<div class="alert alert-success">Motivo agregado correctamente.</div>';
                }
            } else {
                $mensaje = '<div class="alert alert-warning">Ese motivo ya existe en la lista.</div>';
            }
        }
    }

    // 2. Eliminar Motivo
    if (isset($_POST['eliminar_motivo'])) {
        $borrar = $_POST['eliminar_motivo'];
        // Borramos por descripción
        $stmt_del = $pdo->prepare("DELETE FROM configuracion_novedades WHERE descripcion = ?");
        if ($stmt_del->execute([$borrar])) {
            $mensaje = '<div class="alert alert-info">Motivo eliminado de la lista fija.</div>';
        }
    }
}

// OBTENER LISTA ACTUAL
try {
    $lista_fija = $pdo->query("SELECT descripcion FROM configuracion_novedades ORDER BY descripcion ASC")->fetchAll(PDO::FETCH_COLUMN);
} catch (Exception $e) {
    // Si la tabla no existe, la creamos al vuelo para que no de error
    $pdo->exec("CREATE TABLE IF NOT EXISTS configuracion_novedades (id INT AUTO_INCREMENT PRIMARY KEY, descripcion VARCHAR(255))");
    $lista_fija = [];
}

include 'navbar.php';
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Administrar Motivos de Asistencia</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">
</head>
<body>
    <div class="container mt-4">
        <div class="row justify-content-center">
            <div class="col-md-8">
                <div class="card shadow">
                    <div class="card-header bg-dark text-white d-flex justify-content-between align-items-center">
                        <h5 class="mb-0"><i class="fas fa-list-alt me-2"></i> Configurar Lista de Motivos</h5>
                        <a href="asistencia_tomar.php" class="btn btn-sm btn-outline-light">Ir a Tomar Asistencia</a>
                    </div>
                    <div class="card-body">
                        <?php echo $mensaje; ?>

                        <p class="text-muted small">
                            Estos son los motivos <b>FIJOS</b> que siempre aparecerán en el desplegable. 
                            <br>Recuerda que el sistema también aprende y muestra automáticamente los motivos que escribas manualmente en el parte diario.
                        </p>

                        <form method="POST" class="row g-2 mb-4 align-items-end">
                            <div class="col-md-9">
                                <label class="form-label fw-bold">Nuevo Motivo</label>
                                <input type="text" name="nuevo_motivo" class="form-control" placeholder="Ej: Donación de Sangre" required>
                            </div>
                            <div class="col-md-3">
                                <button type="submit" class="btn btn-success w-100"><i class="fas fa-plus"></i> Agregar</button>
                            </div>
                        </form>

                        <hr>

                        <h6 class="fw-bold">Motivos Fijos Actuales:</h6>
                        <?php if (empty($lista_fija)): ?>
                            <div class="alert alert-light border">No hay motivos fijos configurados.</div>
                        <?php else: ?>
                            <ul class="list-group">
                                <?php foreach ($lista_fija as $motivo): ?>
                                    <li class="list-group-item d-flex justify-content-between align-items-center">
                                        <?php echo htmlspecialchars($motivo); ?>
                                        <form method="POST" style="display:inline;" onsubmit="return confirm('¿Seguro de eliminar este motivo fijo?');">
                                            <input type="hidden" name="eliminar_motivo" value="<?php echo htmlspecialchars($motivo); ?>">
                                            <button type="submit" class="btn btn-sm btn-outline-danger" title="Eliminar"><i class="fas fa-trash"></i></button>
                                        </form>
                                    </li>
                                <?php endforeach; ?>
                            </ul>
                        <?php endif; ?>
                        
                    </div>
                </div>
            </div>
        </div>
    </div>
    <?php include 'footer.php'; ?>
</body>
</html>