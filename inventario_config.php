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
    
    // CATEGORÍAS
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
        $pdo->prepare("DELETE FROM inventario_tipos_bien WHERE id_tipo_bien = ?")->execute([$_POST['id']]);
        $pdo->prepare("DELETE FROM inventario_campos_dinamicos WHERE id_tipo_bien = ?")->execute([$_POST['id']]);
    }

    // ESTADOS
    if (isset($_POST['nuevo_estado'])) { $pdo->prepare("INSERT INTO inventario_estados (nombre, color_badge) VALUES (?, ?)")->execute([$_POST['nombre'], $_POST['color']]); }
    if (isset($_POST['borrar_estado'])) { $pdo->prepare("DELETE FROM inventario_estados WHERE id_estado = ?")->execute([$_POST['id']]); }
    if (isset($_POST['editar_estado'])) { $pdo->prepare("UPDATE inventario_estados SET nombre=?, color_badge=? WHERE id_estado=?")->execute([$_POST['nombre'], $_POST['color'], $_POST['id']]); }

    // MATAFUEGOS
    if (isset($_POST['nuevo_tipo_mat'])) { $pdo->prepare("INSERT INTO inventario_config_matafuegos (tipo_carga, vida_util_anios) VALUES (?, ?)")->execute([$_POST['tipo'], $_POST['anios']]); }
    if (isset($_POST['borrar_tipo_mat'])) { $pdo->prepare("DELETE FROM inventario_config_matafuegos WHERE id_config = ?")->execute([$_POST['id']]); }
    if (isset($_POST['nueva_clase'])) { $pdo->prepare("INSERT INTO inventario_config_clases (nombre) VALUES (?)")->execute([$_POST['nombre']]); }
    if (isset($_POST['borrar_clase'])) { $pdo->prepare("DELETE FROM inventario_config_clases WHERE id_clase = ?")->execute([$_POST['id']]); }

    // MOTIVOS
    if (isset($_POST['nuevo_motivo'])) { $pdo->prepare("INSERT INTO inventario_config_motivos (motivo) VALUES (?)")->execute([$_POST['motivo']]); }
    if (isset($_POST['borrar_motivo'])) { $pdo->prepare("DELETE FROM inventario_config_motivos WHERE id_motivo = ?")->execute([$_POST['id']]); }

    // ALERTAS
    if (isset($_POST['guardar_general'])) {
        $configs = [ 'alerta_vida_util_meses' => $_POST['alerta_vida_util'], 'alerta_vencimiento_carga_dias' => $_POST['alerta_carga_dias'], 'alerta_vencimiento_ph_dias' => $_POST['alerta_ph_dias'] ];
        $stmt = $pdo->prepare("INSERT INTO inventario_config_general (clave, valor, descripcion) VALUES (?, ?, 'Config') ON DUPLICATE KEY UPDATE valor = VALUES(valor)");
        foreach($configs as $k => $v) { $stmt->execute([$k, $v]); }
    }
    header("Location: inventario_config.php"); exit();
}

// Cargas
try { $lista_tipos_bienes = $pdo->query("SELECT * FROM inventario_tipos_bien ORDER BY id_tipo_bien ASC")->fetchAll(); } catch (Exception $e) { $lista_tipos_bienes = []; }
$estados = $pdo->query("SELECT * FROM inventario_estados")->fetchAll();
$tipos_mat = $pdo->query("SELECT * FROM inventario_config_matafuegos")->fetchAll();
$clases_fuego = $pdo->query("SELECT * FROM inventario_config_clases")->fetchAll();
$motivos = $pdo->query("SELECT * FROM inventario_config_motivos")->fetchAll();
$conf_data = $pdo->query("SELECT clave, valor FROM inventario_config_general")->fetchAll(PDO::FETCH_KEY_PAIR);
$conf_vida_util = $conf_data['alerta_vida_util_meses'] ?? 12;
$conf_carga_dias = $conf_data['alerta_vencimiento_carga_dias'] ?? 30;
$conf_ph_dias = $conf_data['alerta_vencimiento_ph_dias'] ?? 30;
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Configuración</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>
<body class="bg-light">
    <?php include 'navbar.php'; ?>
    <div class="container mt-4 mb-5">
        <h3 class="mb-4"><i class="fas fa-cogs"></i> Configuración Inventario</h3>
        
        <div class="row g-4">
            
            <div class="col-md-6">
                <div class="card shadow h-100 border-primary">
                    <div class="card-header bg-primary text-white fw-bold">
                        <i class="fas fa-magic me-2"></i> 1. Crear Tipo de Bien (Estructura)
                    </div>
                    <div class="card-body">
                        <small class="text-muted d-block mb-3">Sube un CSV donde la <b>primera fila con datos</b> sean los nombres de los campos (Ej: Marca, Modelo, Serie). El sistema ignorará filas vacías.</small>
                        <form action="importar_estructura_procesar.php" method="POST" enctype="multipart/form-data" class="bg-light p-3 rounded border">
                            <div class="mb-2">
                                <input type="text" name="nombre_categoria" class="form-control" placeholder="Nombre Categoría (Ej: Cámaras)" required>
                            </div>
                            <div class="mb-2">
                                <input type="text" name="icono" class="form-control" placeholder="Icono (fas fa-camera)" value="fas fa-box">
                            </div>
                            <div class="input-group mb-2">
                                <input type="file" name="archivo_csv" class="form-control" accept=".csv" required>
                                <button class="btn btn-primary"><i class="fas fa-upload"></i> Crear</button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>

            <div class="col-md-6">
                <div class="card shadow h-100 border-success">
                    <div class="card-header bg-success text-white fw-bold">
                        <i class="fas fa-file-csv me-2"></i> 2. Carga Masiva de Datos
                    </div>
                    <div class="card-body">
                        <small class="text-muted d-block mb-3">Sube el listado completo de bienes. Las columnas del CSV deben coincidir con los campos creados en el paso 1.</small>
                        <form action="importar_datos_procesar.php" method="POST" enctype="multipart/form-data" class="bg-light p-3 rounded border">
                            <div class="mb-2">
                                <select name="id_tipo_bien" class="form-select" required>
                                    <option value="">-- Seleccionar Categoría --</option>
                                    <?php foreach($lista_tipos_bienes as $tb): ?>
                                        <?php if($tb['tiene_campos_tecnicos'] == 2): // Solo dinámicas ?>
                                            <option value="<?php echo $tb['id_tipo_bien']; ?>"><?php echo htmlspecialchars($tb['nombre']); ?></option>
                                        <?php endif; ?>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="input-group mb-2">
                                <input type="file" name="archivo_datos" class="form-control" accept=".csv" required>
                                <button class="btn btn-success"><i class="fas fa-file-import"></i> Importar Datos</button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>

        </div>

        <hr class="my-5">

        <div class="card shadow mb-4">
            <div class="card-header bg-dark text-white fw-bold">Gestión de Categorías</div>
            <div class="card-body">
                <table class="table table-bordered align-middle">
                    <thead class="table-light"><tr><th>Icono</th><th>Nombre</th><th>Tipo</th><th class="text-end">Acciones</th></tr></thead>
                    <tbody>
                        <?php foreach($lista_tipos_bienes as $tb): ?>
                        <tr>
                            <td class="text-center text-primary"><i class="<?php echo $tb['icono']; ?>"></i></td>
                            <td><?php echo $tb['nombre']; ?></td>
                            <td><?php echo ($tb['tiene_campos_tecnicos']==2) ? '<span class="badge bg-success">Dinámico</span>' : (($tb['tiene_campos_tecnicos']==1)?'<span class="badge bg-danger">Matafuego</span>':'Estándar'); ?></td>
                            <td class="text-end">
                                <button class="btn btn-sm btn-warning" onclick="abrirEditar('categoria', <?php echo $tb['id_tipo_bien']; ?>, '<?php echo $tb['nombre']; ?>', '<?php echo $tb['icono']; ?>', '<?php echo $tb['descripcion']; ?>', <?php echo $tb['tiene_campos_tecnicos']; ?>)"><i class="fas fa-edit"></i></button>
                                <form method="POST" style="display:inline;" onsubmit="return confirm('¿Borrar?');"><input type="hidden" name="borrar_tipo_bien" value="1"><input type="hidden" name="id" value="<?php echo $tb['id_tipo_bien']; ?>"><button class="btn btn-sm btn-outline-danger"><i class="fas fa-trash"></i></button></form>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
                <div class="bg-light p-2 rounded">
                    <form method="POST" class="d-flex gap-2 align-items-center">
                        <input type="hidden" name="nuevo_tipo_bien" value="1">
                        <input type="text" name="nombre" class="form-control form-control-sm" placeholder="Nueva Categoría Manual" required>
                        <input type="text" name="icono" class="form-control form-control-sm" placeholder="Icono" required>
                        <div class="form-check pt-1"><input class="form-check-input" type="checkbox" name="tiene_campos_tecnicos" value="1" id="tec"><label class="form-check-label small text-danger" for="tec">Extintor</label></div>
                        <button class="btn btn-sm btn-dark">Agregar</button>
                    </form>
                </div>
            </div>
        </div>

        <div class="row g-4">
            <div class="col-md-4">
                <div class="card h-100"><div class="card-header bg-secondary text-white">Estados</div><div class="card-body">
                    <ul class="list-group list-group-flush mb-2">
                        <?php foreach($estados as $e): ?><li class="list-group-item d-flex justify-content-between p-1"><?php echo $e['nombre']; ?> <span class="badge <?php echo $e['color_badge']; ?>">Color</span></li><?php endforeach; ?>
                    </ul>
                    <form method="POST" class="d-flex gap-1"><input type="hidden" name="nuevo_estado" value="1"><input type="text" name="nombre" class="form-control form-control-sm" placeholder="Nuevo"><select name="color" class="form-select form-select-sm"><option value="bg-success">Verde</option><option value="bg-danger">Rojo</option></select><button class="btn btn-sm btn-secondary">+</button></form>
                </div></div>
            </div>
            <div class="col-md-4">
                <div class="card h-100"><div class="card-header bg-warning text-dark">Alertas</div><div class="card-body">
                    <form method="POST">
                        <input type="hidden" name="guardar_general" value="1">
                        <div class="mb-2"><label class="small">Carga (Días)</label><input type="number" name="alerta_carga_dias" class="form-control form-control-sm" value="<?php echo $conf_carga_dias; ?>"></div>
                        <div class="mb-2"><label class="small">Vida Útil (Meses)</label><input type="number" name="alerta_vida_util" class="form-control form-control-sm" value="<?php echo $conf_vida_util; ?>"></div>
                        <button class="btn btn-sm btn-warning w-100">Guardar</button>
                    </form>
                </div></div>
            </div>
            <div class="col-md-4">
                <div class="card h-100"><div class="card-header bg-info text-white">Motivos</div><div class="card-body">
                    <ul class="list-group list-group-flush mb-2"><?php foreach($motivos as $m): ?><li class="list-group-item p-1"><?php echo $m['motivo']; ?></li><?php endforeach; ?></ul>
                    <form method="POST" class="d-flex gap-1"><input type="hidden" name="nuevo_motivo" value="1"><input type="text" name="motivo" class="form-control form-control-sm" placeholder="Nuevo"><button class="btn btn-sm btn-info">+</button></form>
                </div></div>
            </div>
        </div>

    </div>

    <div class="modal fade" id="modalEditar" tabindex="-1"><div class="modal-dialog"><div class="modal-content"><form method="POST"><div class="modal-body" id="modalBodyContent"></div><div class="modal-footer"><button class="btn btn-primary">Guardar</button></div></form></div></div></div>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        function abrirEditar(tipo, id, val1, val2='', val3='', val4='') {
            const modal = new bootstrap.Modal(document.getElementById('modalEditar'));
            let html = '';
            if(tipo==='categoria') {
                html = `<input type="hidden" name="editar_tipo_bien" value="1"><input type="hidden" name="id" value="${id}">
                        <div class="mb-2"><label>Nombre</label><input type="text" name="nombre" class="form-control" value="${val1}"></div>
                        <div class="mb-2"><label>Icono</label><input type="text" name="icono" class="form-control" value="${val2}"></div>
                        <div class="mb-2"><label>Desc.</label><input type="text" name="descripcion" class="form-control" value="${val3}"></div>`;
            }
            document.getElementById('modalBodyContent').innerHTML = html;
            modal.show();
        }
    </script>
</body>
</html>