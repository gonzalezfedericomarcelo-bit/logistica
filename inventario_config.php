<?php
// Archivo: inventario_config.php
session_start();
include 'conexion.php';
include 'funciones_permisos.php';

if (!isset($_SESSION['usuario_id']) || !tiene_permiso('acceso_inventario', $pdo)) {
    header("Location: dashboard.php"); exit();
}

// --- PROCESAR FORMULARIOS ---
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    
    // 1. ESTADOS
    if (isset($_POST['nuevo_estado'])) {
        $pdo->prepare("INSERT INTO inventario_estados (nombre, color_badge) VALUES (?, ?)")
            ->execute([$_POST['nombre'], $_POST['color']]);
    }
    if (isset($_POST['editar_estado'])) {
        $pdo->prepare("UPDATE inventario_estados SET nombre=?, color_badge=? WHERE id_estado=?")
            ->execute([$_POST['nombre'], $_POST['color'], $_POST['id']]);
    }
    if (isset($_POST['borrar_estado'])) {
        $pdo->prepare("DELETE FROM inventario_estados WHERE id_estado = ?")->execute([$_POST['id']]);
    }

    // 2. MATAFUEGOS - TIPOS
    if (isset($_POST['nuevo_tipo_mat'])) {
        $pdo->prepare("INSERT INTO inventario_config_matafuegos (tipo_carga, vida_util_anios) VALUES (?, ?)")
            ->execute([$_POST['tipo'], $_POST['anios']]);
    }
    if (isset($_POST['editar_tipo_mat'])) {
        $pdo->prepare("UPDATE inventario_config_matafuegos SET tipo_carga=?, vida_util_anios=? WHERE id_config=?")
            ->execute([$_POST['tipo'], $_POST['anios'], $_POST['id']]);
    }
    if (isset($_POST['borrar_tipo_mat'])) {
        $pdo->prepare("DELETE FROM inventario_config_matafuegos WHERE id_config = ?")->execute([$_POST['id']]);
    }

    // 3. MATAFUEGOS - CLASES DE FUEGO
    if (isset($_POST['nueva_clase'])) {
        $pdo->prepare("INSERT INTO inventario_config_clases (nombre) VALUES (?)")
            ->execute([$_POST['nombre']]);
    }
    if (isset($_POST['editar_clase'])) {
        $pdo->prepare("UPDATE inventario_config_clases SET nombre=? WHERE id_clase=?")
            ->execute([$_POST['nombre'], $_POST['id']]);
    }
    if (isset($_POST['borrar_clase'])) {
        $pdo->prepare("DELETE FROM inventario_config_clases WHERE id_clase = ?")->execute([$_POST['id']]);
    }

    // 4. CONFIGURACIÓN GENERAL (NUEVO - ALERTAS)
    if (isset($_POST['guardar_general'])) {
        // Usamos ON DUPLICATE KEY UPDATE por seguridad, aunque el script db ya lo insertó
        $sql = "INSERT INTO inventario_config_general (clave, valor, descripcion) VALUES ('alerta_vida_util_meses', ?, 'Meses alerta') 
                ON DUPLICATE KEY UPDATE valor = VALUES(valor)";
        $pdo->prepare($sql)->execute([$_POST['alerta_vida_util']]);
    }

    header("Location: inventario_config.php"); exit();
}

// Cargar Datos
$estados = $pdo->query("SELECT * FROM inventario_estados")->fetchAll();
$tipos_mat = $pdo->query("SELECT * FROM inventario_config_matafuegos")->fetchAll();
$clases_fuego = $pdo->query("SELECT * FROM inventario_config_clases")->fetchAll();

// Cargar config alerta (si no existe, default 12)
$conf_vida_stmt = $pdo->prepare("SELECT valor FROM inventario_config_general WHERE clave='alerta_vida_util_meses'");
$conf_vida_stmt->execute();
$conf_vida_util = $conf_vida_stmt->fetchColumn() ?: 12;

?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Configuración Inventario</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>
<body class="bg-light">
    <?php include 'navbar.php'; ?>
    <div class="container mt-4 mb-5">
        <h3 class="mb-4"><i class="fas fa-cogs"></i> Configuración de Inventario</h3>
        
        <div class="card shadow-sm mb-4 border-primary">
            <div class="card-header bg-primary text-white"><i class="fas fa-bell me-2"></i> Configuración de Alertas</div>
            <div class="card-body">
                <form method="POST" class="row align-items-end">
                    <input type="hidden" name="guardar_general" value="1">
                    <div class="col-md-8">
                        <label class="form-label fw-bold">Alerta Fin Vida Útil (Meses antes)</label>
                        <div class="input-group">
                            <span class="input-group-text"><i class="fas fa-hourglass-half"></i></span>
                            <input type="number" name="alerta_vida_util" class="form-control" value="<?php echo htmlspecialchars($conf_vida_util); ?>" required min="1">
                            <span class="input-group-text">Meses</span>
                        </div>
                        <small class="text-muted">El sistema mostrará una tarjeta de alerta cuando falten estos meses para el vencimiento de vida útil.</small>
                    </div>
                    <div class="col-md-4">
                        <button class="btn btn-primary w-100 fw-bold">Guardar Configuración</button>
                    </div>
                </form>
            </div>
        </div>

        <div class="row g-4">
            
            <div class="col-md-4">
                <div class="card shadow-sm h-100">
                    <div class="card-header bg-dark text-white">Estados</div>
                    <div class="card-body">
                        <table class="table table-sm align-middle table-hover">
                            <thead><tr><th>Nombre</th><th>Color</th><th class="text-end"></th></tr></thead>
                            <tbody>
                                <?php foreach($estados as $e): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($e['nombre']); ?></td>
                                    <td><span class="badge <?php echo $e['color_badge']; ?>">Tag</span></td>
                                    <td class="text-end">
                                        <button class="btn btn-sm btn-warning py-0 me-1" onclick="abrirEditar('estado', <?php echo $e['id_estado']; ?>, '<?php echo $e['nombre']; ?>', '<?php echo $e['color_badge']; ?>')"><i class="fas fa-edit"></i></button>
                                        <form method="POST" style="display:inline;" onsubmit="return confirm('¿Borrar?');">
                                            <input type="hidden" name="borrar_estado" value="1">
                                            <input type="hidden" name="id" value="<?php echo $e['id_estado']; ?>">
                                            <button class="btn btn-sm btn-danger py-0"><i class="fas fa-trash"></i></button>
                                        </form>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                        <hr>
                        <form method="POST" class="d-flex gap-2">
                            <input type="hidden" name="nuevo_estado" value="1">
                            <input type="text" name="nombre" class="form-control form-control-sm" placeholder="Nuevo Estado" required>
                            <select name="color" class="form-select form-select-sm" style="width:80px;">
                                <option value="bg-success">Verde</option>
                                <option value="bg-warning">Amarillo</option>
                                <option value="bg-danger">Rojo</option>
                                <option value="bg-primary">Azul</option>
                                <option value="bg-secondary">Gris</option>
                                <option value="bg-dark">Negro</option>
                            </select>
                            <button class="btn btn-sm btn-success"><i class="fas fa-plus"></i></button>
                        </form>
                    </div>
                </div>
            </div>

            <div class="col-md-4">
                <div class="card shadow-sm h-100">
                    <div class="card-header bg-danger text-white">Tipos Carga (Vida Útil)</div>
                    <div class="card-body">
                        <table class="table table-sm align-middle table-hover">
                            <thead><tr><th>Tipo</th><th>Años</th><th class="text-end"></th></tr></thead>
                            <tbody>
                                <?php foreach($tipos_mat as $t): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($t['tipo_carga']); ?></td>
                                    <td><?php echo $t['vida_util_anios']; ?></td>
                                    <td class="text-end">
                                        <button class="btn btn-sm btn-warning py-0 me-1" onclick="abrirEditar('tipo', <?php echo $t['id_config']; ?>, '<?php echo $t['tipo_carga']; ?>', '<?php echo $t['vida_util_anios']; ?>')"><i class="fas fa-edit"></i></button>
                                        <form method="POST" style="display:inline;" onsubmit="return confirm('¿Borrar?');">
                                            <input type="hidden" name="borrar_tipo_mat" value="1">
                                            <input type="hidden" name="id" value="<?php echo $t['id_config']; ?>">
                                            <button class="btn btn-sm btn-danger py-0"><i class="fas fa-trash"></i></button>
                                        </form>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                        <hr>
                        <form method="POST" class="d-flex gap-2">
                            <input type="hidden" name="nuevo_tipo_mat" value="1">
                            <input type="text" name="tipo" class="form-control form-control-sm" placeholder="Nuevo Tipo" required>
                            <input type="number" name="anios" class="form-control form-control-sm" placeholder="Años" style="width:60px;" required>
                            <button class="btn btn-sm btn-success"><i class="fas fa-plus"></i></button>
                        </form>
                    </div>
                </div>
            </div>

            <div class="col-md-4">
                <div class="card shadow-sm h-100">
                    <div class="card-header bg-warning text-dark">Clases de Fuego</div>
                    <div class="card-body">
                        <table class="table table-sm align-middle table-hover">
                            <thead><tr><th>Clase</th><th class="text-end"></th></tr></thead>
                            <tbody>
                                <?php foreach($clases_fuego as $c): ?>
                                <tr>
                                    <td class="fw-bold"><?php echo htmlspecialchars($c['nombre']); ?></td>
                                    <td class="text-end">
                                        <button class="btn btn-sm btn-outline-dark py-0 me-1" onclick="abrirEditar('clase', <?php echo $c['id_clase']; ?>, '<?php echo $c['nombre']; ?>')"><i class="fas fa-edit"></i></button>
                                        <form method="POST" style="display:inline;" onsubmit="return confirm('¿Borrar?');">
                                            <input type="hidden" name="borrar_clase" value="1">
                                            <input type="hidden" name="id" value="<?php echo $c['id_clase']; ?>">
                                            <button class="btn btn-sm btn-danger py-0"><i class="fas fa-trash"></i></button>
                                        </form>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                        <hr>
                        <form method="POST" class="d-flex gap-2">
                            <input type="hidden" name="nueva_clase" value="1">
                            <input type="text" name="nombre" class="form-control form-control-sm" placeholder="Ej: ABC, K, D" required>
                            <button class="btn btn-sm btn-success"><i class="fas fa-plus"></i></button>
                        </form>
                    </div>
                </div>
            </div>

        </div>
        <div class="mt-4"><a href="inventario_nuevo.php" class="btn btn-outline-secondary"><i class="fas fa-arrow-left me-2"></i> Volver a Nuevo Bien</a></div>
    </div>

    <div class="modal fade" id="modalEditar" tabindex="-1">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header py-2 bg-light">
                    <h5 class="modal-title fs-6">Editar</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form method="POST">
                    <div class="modal-body" id="modalBodyContent">
                        </div>
                    <div class="modal-footer py-1">
                        <button type="submit" class="btn btn-sm btn-primary">Guardar Cambios</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        function abrirEditar(tipo, id, val1, val2='') {
            const modal = new bootstrap.Modal(document.getElementById('modalEditar'));
            let html = '';
            
            if(tipo === 'estado') {
                html = `<input type="hidden" name="editar_estado" value="1">
                        <input type="hidden" name="id" value="${id}">
                        <div class="mb-2">
                            <label class="form-label small fw-bold">Nombre</label>
                            <input type="text" name="nombre" class="form-control" value="${val1}" required>
                        </div>
                        <div class="mb-2">
                            <label class="form-label small fw-bold">Color</label>
                            <select name="color" class="form-select">
                                <option value="bg-success" ${val2=='bg-success'?'selected':''}>Verde (Success)</option>
                                <option value="bg-warning" ${val2=='bg-warning'?'selected':''}>Amarillo (Warning)</option>
                                <option value="bg-danger" ${val2=='bg-danger'?'selected':''}>Rojo (Danger)</option>
                                <option value="bg-primary" ${val2=='bg-primary'?'selected':''}>Azul (Primary)</option>
                                <option value="bg-secondary" ${val2=='bg-secondary'?'selected':''}>Gris (Secondary)</option>
                                <option value="bg-dark" ${val2=='bg-dark'?'selected':''}>Negro (Dark)</option>
                            </select>
                        </div>`;
            } 
            else if(tipo === 'tipo') {
                html = `<input type="hidden" name="editar_tipo_mat" value="1">
                        <input type="hidden" name="id" value="${id}">
                        <div class="mb-2">
                            <label class="form-label small fw-bold">Tipo Carga</label>
                            <input type="text" name="tipo" class="form-control" value="${val1}" required>
                        </div>
                        <div class="mb-2">
                            <label class="form-label small fw-bold">Vida Útil (Años)</label>
                            <input type="number" name="anios" class="form-control" value="${val2}" required>
                        </div>`;
            } 
            else if(tipo === 'clase') {
                html = `<input type="hidden" name="editar_clase" value="1">
                        <input type="hidden" name="id" value="${id}">
                        <div class="mb-2">
                            <label class="form-label small fw-bold">Nombre Clase</label>
                            <input type="text" name="nombre" class="form-control" value="${val1}" required>
                        </div>`;
            }

            document.getElementById('modalBodyContent').innerHTML = html;
            modal.show();
        }
    </script>
</body>
</html>