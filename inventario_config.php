<?php
// Archivo: inventario_config.php (GESTIÓN INTEGRAL: CATEGORÍAS + LISTAS)
session_start();
include 'conexion.php';
include 'funciones_permisos.php';

if (!isset($_SESSION['usuario_id']) || !tiene_permiso('configuracion_acceso', $pdo)) {
    header("Location: dashboard.php"); exit();
}

// --- PROCESAR FORMULARIOS ---
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    
    // 1. GESTIÓN DE CATEGORÍAS (Tu código original restaurado)
    if (isset($_POST['nuevo_tipo_bien'])) {
        $es_tecnico = isset($_POST['tiene_campos_tecnicos']) ? 1 : 0;
        $icono = !empty($_POST['icono']) ? $_POST['icono'] : 'fas fa-box';
        $pdo->prepare("INSERT INTO inventario_tipos_bien (nombre, icono, descripcion, tiene_campos_tecnicos, categoria_agrupadora) VALUES (?, ?, ?, ?, 'General')")
            ->execute([$_POST['nombre'], $icono, $_POST['descripcion'], $es_tecnico]);
    }
    if (isset($_POST['editar_tipo_bien'])) {
        $es_tecnico = isset($_POST['tiene_campos_tecnicos']) ? 1 : 0;
        $icono = !empty($_POST['icono']) ? $_POST['icono'] : 'fas fa-box';
        $pdo->prepare("UPDATE inventario_tipos_bien SET nombre=?, icono=?, descripcion=?, tiene_campos_tecnicos=? WHERE id_tipo_bien=?")
            ->execute([$_POST['nombre'], $icono, $_POST['descripcion'], $es_tecnico, $_POST['id']]);
    }
    if (isset($_POST['borrar_tipo_bien'])) {
        $pdo->prepare("DELETE FROM inventario_tipos_bien WHERE id_tipo_bien=?")->execute([$_POST['id']]);
    }

    // 2. GESTIÓN MATAFUEGOS (Lo nuevo, agregado sin borrar lo anterior)
    if (isset($_POST['nuevo_agente'])) {
        $agente = trim($_POST['nuevo_agente']);
        if (!empty($agente)) {
            // Evitar duplicados
            $existe = $pdo->query("SELECT COUNT(*) FROM inventario_config_matafuegos WHERE tipo_carga = '$agente'")->fetchColumn();
            if ($existe == 0) $pdo->prepare("INSERT INTO inventario_config_matafuegos (tipo_carga) VALUES (?)")->execute([$agente]);
        }
    }
    if (isset($_POST['borrar_agente_id'])) {
        $pdo->query("DELETE FROM inventario_config_matafuegos WHERE id_config = " . (int)$_POST['borrar_agente_id']);
    }

    if (isset($_POST['nueva_clase'])) {
        $clase = trim($_POST['nueva_clase']);
        if (!empty($clase)) {
            $existe = $pdo->query("SELECT COUNT(*) FROM inventario_config_clases WHERE nombre = '$clase'")->fetchColumn();
            if ($existe == 0) $pdo->prepare("INSERT INTO inventario_config_clases (nombre, descripcion) VALUES (?, '')")->execute([$clase]);
        }
    }
    if (isset($_POST['borrar_clase_id'])) {
        $pdo->query("DELETE FROM inventario_config_clases WHERE id_clase = " . (int)$_POST['borrar_clase_id']);
    }

    header("Location: inventario_config.php"); exit();
}

// LEER DATOS
$tipos_bien = $pdo->query("SELECT * FROM inventario_tipos_bien ORDER BY id_tipo_bien ASC")->fetchAll(PDO::FETCH_ASSOC);
$agentes = $pdo->query("SELECT * FROM inventario_config_matafuegos ORDER BY tipo_carga ASC")->fetchAll(PDO::FETCH_ASSOC);
$clases = $pdo->query("SELECT * FROM inventario_config_clases ORDER BY nombre ASC")->fetchAll(PDO::FETCH_ASSOC);
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
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h3><i class="fas fa-cogs"></i> Configuración General</h3>
            <a href="inventario_lista.php" class="btn btn-outline-secondary">Volver</a>
        </div>

        <div class="card shadow-sm mb-5">
            <div class="card-header bg-primary text-white fw-bold">
                <i class="fas fa-boxes"></i> Categorías de Bienes (Fichas)
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-hover align-middle">
                        <thead class="table-light">
                            <tr>
                                <th>Icono</th>
                                <th>Nombre</th>
                                <th>Descripción</th>
                                <th>Tipo Ficha</th>
                                <th>Acciones</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($tipos_bien as $tipo): ?>
                            <tr>
                                <form method="POST">
                                    <input type="hidden" name="id" value="<?php echo $tipo['id_tipo_bien']; ?>">
                                    <td><input type="text" name="icono" class="form-control form-control-sm" value="<?php echo htmlspecialchars($tipo['icono']); ?>" style="width: 100px;"></td>
                                    <td><input type="text" name="nombre" class="form-control form-control-sm fw-bold" value="<?php echo htmlspecialchars($tipo['nombre']); ?>"></td>
                                    <td><input type="text" name="descripcion" class="form-control form-control-sm" value="<?php echo htmlspecialchars($tipo['descripcion']); ?>"></td>
                                    <td>
                                        <div class="form-check form-switch">
                                            <input class="form-check-input" type="checkbox" name="tiene_campos_tecnicos" value="1" <?php echo $tipo['tiene_campos_tecnicos'] ? 'checked' : ''; ?>>
                                            <label class="form-check-label small text-muted">Es Matafuego</label>
                                        </div>
                                    </td>
                                    <td>
                                        <button type="submit" name="editar_tipo_bien" class="btn btn-sm btn-success"><i class="fas fa-save"></i></button>
                                        <button type="submit" name="borrar_tipo_bien" class="btn btn-sm btn-danger" onclick="return confirm('¿Borrar categoría?');"><i class="fas fa-trash"></i></button>
                                    </td>
                                </form>
                            </tr>
                            <?php endforeach; ?>
                            <tr class="table-info">
                                <form method="POST">
                                    <td><input type="text" name="icono" class="form-control form-control-sm" placeholder="fas fa-box"></td>
                                    <td><input type="text" name="nombre" class="form-control form-control-sm" placeholder="Nueva Categoría" required></td>
                                    <td><input type="text" name="descripcion" class="form-control form-control-sm" placeholder="Descripción"></td>
                                    <td>
                                        <div class="form-check form-switch">
                                            <input class="form-check-input" type="checkbox" name="tiene_campos_tecnicos" value="1">
                                            <label class="form-check-label small">Es Matafuego</label>
                                        </div>
                                    </td>
                                    <td><button type="submit" name="nuevo_tipo_bien" class="btn btn-sm btn-primary"><i class="fas fa-plus"></i> Crear</button></td>
                                </form>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <div class="row">
            <div class="col-md-6">
                <div class="card shadow-sm h-100">
                    <div class="card-header bg-danger text-white fw-bold"><i class="fas fa-fire-extinguisher"></i> Tipos de Agente</div>
                    <div class="card-body">
                        <ul class="list-group list-group-flush mb-3">
                            <?php foreach($agentes as $a): ?>
                                <li class="list-group-item d-flex justify-content-between align-items-center p-2">
                                    <?php echo htmlspecialchars($a['tipo_carga']); ?>
                                    <form method="POST" class="d-inline"><input type="hidden" name="borrar_agente_id" value="<?php echo $a['id_config']; ?>"><button class="btn btn-sm text-danger"><i class="fas fa-times"></i></button></form>
                                </li>
                            <?php endforeach; ?>
                        </ul>
                        <form method="POST" class="input-group"><input type="text" name="nuevo_agente" class="form-control" placeholder="Nuevo Agente"><button class="btn btn-outline-danger" type="submit">Agregar</button></form>
                    </div>
                </div>
            </div>
            <div class="col-md-6">
                <div class="card shadow-sm h-100">
                    <div class="card-header bg-warning text-dark fw-bold"><i class="fas fa-fire"></i> Clases de Fuego</div>
                    <div class="card-body">
                        <ul class="list-group list-group-flush mb-3">
                            <?php foreach($clases as $c): ?>
                                <li class="list-group-item d-flex justify-content-between align-items-center p-2">
                                    <?php echo htmlspecialchars($c['nombre']); ?>
                                    <form method="POST" class="d-inline"><input type="hidden" name="borrar_clase_id" value="<?php echo $c['id_clase']; ?>"><button class="btn btn-sm text-danger"><i class="fas fa-times"></i></button></form>
                                </li>
                            <?php endforeach; ?>
                        </ul>
                        <form method="POST" class="input-group"><input type="text" name="nueva_clase" class="form-control" placeholder="Nueva Clase"><button class="btn btn-outline-warning" type="submit">Agregar</button></form>
                    </div>
                </div>
            </div>
        </div>
    </div>
</body>
</html>