<?php
// Archivo: inventario_config.php (CON ICON PICKER + LIMPIEZA)
session_start();
include 'conexion.php';
include 'funciones_permisos.php';

if (!isset($_SESSION['usuario_id']) || !tiene_permiso('configuracion_acceso', $pdo)) {
    header("Location: dashboard.php"); exit();
}

// --- HELPERS ---
function getTable($pdo, $table, $order='nombre') {
    try { return $pdo->query("SELECT * FROM $table ORDER BY $order ASC")->fetchAll(PDO::FETCH_ASSOC); } 
    catch (PDOException $e) { return []; }
}
function getMarcas($pdo, $ambito) {
    $stmt = $pdo->prepare("SELECT * FROM inventario_config_marcas WHERE ambito = ? ORDER BY nombre ASC");
    $stmt->execute([$ambito]);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}
function getModelos($pdo, $ambito) {
    $sql = "SELECT m.*, ma.nombre as nombre_marca, ma.id_marca 
            FROM inventario_config_modelos m 
            JOIN inventario_config_marcas ma ON m.id_marca = ma.id_marca 
            WHERE ma.ambito = ? ORDER BY ma.nombre, m.nombre ASC";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$ambito]);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

// CARGA DE DATOS COMPLETA
$tipos_bien = getTable($pdo, 'inventario_tipos_bien', 'id_tipo_bien');
$agentes = $pdo->query("SELECT * FROM inventario_config_matafuegos ORDER BY tipo_carga ASC")->fetchAll(PDO::FETCH_ASSOC);
$clases = getTable($pdo, 'inventario_config_clases', 'nombre');
$capacidades = getTable($pdo, 'inventario_config_capacidades', 'capacidad');
$tipos_it = getTable($pdo, 'inventario_config_tipos_it');
$estados = getTable($pdo, 'inventario_estados');

// Para los selects de los modales
$all_marcas_it = getMarcas($pdo, 'informatica');
$all_marcas_cam = getMarcas($pdo, 'camara');
$all_marcas_tel = getMarcas($pdo, 'telefono');
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0"> 
    <title>Configuración Inventario</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <style>
        .card-custom { border: none; border-radius: 8px; box-shadow: 0 2px 4px rgba(0,0,0,0.05); margin-bottom: 20px; }
        .list-scrollable { max-height: 350px; overflow-y: auto; }
        .action-btn { cursor: pointer; margin-left: 8px; transition: transform 0.2s; }
        .action-btn:hover { transform: scale(1.2); }
        .nav-pills .nav-link { color: #555; border-radius: 5px; margin: 0 2px; }
        .nav-pills .nav-link.active { background-color: #0d6efd; color: white; font-weight: 600; }
        
        /* Estilos Icon Picker */
        .icon-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(50px, 1fr)); gap: 10px; max-height: 400px; overflow-y: auto; padding: 10px; }
        .icon-btn { border: 1px solid #ddd; background: white; border-radius: 5px; padding: 10px; cursor: pointer; transition: 0.2s; text-align: center; }
        .icon-btn:hover { background: #f0f0f0; transform: scale(1.1); border-color: #0d6efd; }
        .icon-btn i { font-size: 1.5rem; color: #333; }
    </style>
</head>
<body class="bg-light">
    <?php include 'navbar.php'; ?>

    <div class="container mt-4 mb-5">
        <div class="d-flex justify-content-between align-items-center mb-4 flex-wrap">
            <h3 class="fw-bold text-dark"><i class="fas fa-sliders-h me-2"></i>Configuración</h3>
            <a href="inventario_lista.php" class="btn btn-outline-secondary btn-sm"><i class="fas fa-arrow-left me-2"></i>Volver</a>
        </div>

        <ul class="nav nav-pills nav-fill mb-4 shadow-sm bg-white rounded p-2 overflow-auto" id="myTab" role="tablist">
            <li class="nav-item"><button class="nav-link active" data-bs-toggle="tab" data-bs-target="#matafuegos">Matafuegos</button></li>
            <li class="nav-item"><button class="nav-link" data-bs-toggle="tab" data-bs-target="#informatico">Informática</button></li>
            <li class="nav-item"><button class="nav-link" data-bs-toggle="tab" data-bs-target="#camaras">Cámaras</button></li>
            <li class="nav-item"><button class="nav-link" data-bs-toggle="tab" data-bs-target="#telefonos">Teléfonos</button></li>
            <li class="nav-item"><button class="nav-link" data-bs-toggle="tab" data-bs-target="#estados">Estados</button></li>
            <li class="nav-item"><button class="nav-link" data-bs-toggle="tab" data-bs-target="#fichas">Fichas</button></li>
            <li class="nav-item"><button class="nav-link bg-dark text-white" data-bs-toggle="tab" data-bs-target="#herramientas"><i class="fas fa-tools me-1"></i> Herramientas</button></li>
        </ul>

        <div class="tab-content" id="myTabContent">

            <div class="tab-pane fade show active" id="matafuegos">
                <div class="row g-4">
                    <div class="col-md-5">
                        <div class="card card-custom h-100 border-danger border-top border-3">
                            <div class="card-header bg-white fw-bold text-danger">Agentes y Vida Útil</div>
                            <div class="card-body">
                                <div class="input-group mb-3">
                                    <input type="text" id="add_agente_nom" class="form-control" placeholder="Nombre">
                                    <input type="number" id="add_agente_vida" class="form-control" placeholder="Años" style="max-width:80px;" value="20">
                                    <button class="btn btn-danger" onclick="addAgente()">+</button>
                                </div>
                                <ul class="list-group list-group-flush list-scrollable">
                                    <?php foreach($agentes as $a): ?>
                                    <li class="list-group-item d-flex justify-content-between align-items-center">
                                        <div>
                                            <span class="fw-bold"><?php echo $a['tipo_carga']; ?></span>
                                            <span class="badge bg-light text-dark border ms-2"><?php echo $a['vida_util']; ?> Años</span>
                                        </div>
                                        <div>
                                            <i class="fas fa-pen text-primary action-btn" onclick="modalEditAgente(<?php echo $a['id_config']; ?>, '<?php echo $a['tipo_carga']; ?>', <?php echo $a['vida_util']; ?>)"></i>
                                            <i class="fas fa-trash text-danger action-btn" onclick="delItem('del_agente', <?php echo $a['id_config']; ?>)"></i>
                                        </div>
                                    </li>
                                    <?php endforeach; ?>
                                </ul>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-7">
                        <div class="row g-4">
                            <div class="col-12">
                                <div class="card card-custom border-warning border-top border-3">
                                    <div class="card-header bg-white fw-bold text-warning">Clases</div>
                                    <div class="card-body">
                                        <div class="input-group mb-2"><input type="text" id="add_clase_nom" class="form-control" placeholder="Clase"><button class="btn btn-warning" onclick="addSimple('add_clase','add_clase_nom')">+</button></div>
                                        <ul class="list-group list-scrollable">
                                            <?php foreach($clases as $c): ?>
                                            <li class="list-group-item d-flex justify-content-between align-items-center">
                                                <span><?php echo $c['nombre']; ?></span>
                                                <div>
                                                    <i class="fas fa-pen text-primary action-btn" onclick="modalEditSimple('edit_simple','inventario_config_clases','id_clase','nombre', <?php echo $c['id_clase']; ?>, '<?php echo $c['nombre']; ?>')"></i>
                                                    <i class="fas fa-trash text-danger action-btn" onclick="delItem('del_clase', <?php echo $c['id_clase']; ?>)"></i>
                                                </div>
                                            </li>
                                            <?php endforeach; ?>
                                        </ul>
                                    </div>
                                </div>
                            </div>
                            <div class="col-12">
                                <div class="card card-custom border-secondary border-top border-3">
                                    <div class="card-header bg-white fw-bold text-secondary">Capacidades</div>
                                    <div class="card-body">
                                        <div class="input-group mb-2"><input type="text" id="add_cap_nom" class="form-control" placeholder="Capacidad"><button class="btn btn-secondary" onclick="addSimple('add_capacidad','add_cap_nom')">+</button></div>
                                        <ul class="list-group list-scrollable">
                                            <?php foreach($capacidades as $c): ?>
                                            <li class="list-group-item d-flex justify-content-between align-items-center">
                                                <span><?php echo $c['capacidad']; ?></span>
                                                <div>
                                                    <i class="fas fa-pen text-primary action-btn" onclick="modalEditSimple('edit_simple','inventario_config_capacidades','id_capacidad','capacidad', <?php echo $c['id_capacidad']; ?>, '<?php echo $c['capacidad']; ?>')"></i>
                                                    <i class="fas fa-trash text-danger action-btn" onclick="delItem('del_capacidad', <?php echo $c['id_capacidad']; ?>)"></i>
                                                </div>
                                            </li>
                                            <?php endforeach; ?>
                                        </ul>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="tab-pane fade" id="informatico">
                <?php renderSection($pdo, 'informatica', $tipos_it, $all_marcas_it, 'dark'); ?>
            </div>
            <div class="tab-pane fade" id="camaras">
                <?php renderSection($pdo, 'camara', [], $all_marcas_cam, 'info'); ?>
            </div>
            <div class="tab-pane fade" id="telefonos">
                <?php renderSection($pdo, 'telefono', [], $all_marcas_tel, 'success'); ?>
            </div>

            <div class="tab-pane fade" id="estados">
                <div class="card card-custom border-primary border-top border-3">
                    <div class="card-header bg-white fw-bold text-primary">Estados Operativos</div>
                    <div class="card-body">
                        <div class="input-group mb-3">
                            <input type="text" id="add_est_nom" class="form-control" placeholder="Nombre Estado">
                            <select id="add_est_amb" class="form-select">
                                <option value="general">General</option>
                                <option value="matafuego">Matafuegos</option>
                                <option value="ambos">Ambos</option>
                            </select>
                            <button class="btn btn-primary" onclick="addEstado()">+</button>
                        </div>
                        <ul class="list-group list-group-flush">
                            <?php foreach($estados as $e): ?>
                            <li class="list-group-item d-flex justify-content-between align-items-center">
                                <div>
                                    <span class="fw-bold"><?php echo $e['nombre']; ?></span>
                                    <span class="badge bg-secondary ms-2"><?php echo ucfirst($e['ambito']); ?></span>
                                </div>
                                <div>
                                    <i class="fas fa-pen text-primary action-btn" onclick="modalEditEstado(<?php echo $e['id_estado']; ?>, '<?php echo $e['nombre']; ?>', '<?php echo $e['ambito']; ?>')"></i>
                                    <i class="fas fa-trash text-danger action-btn" onclick="delItem('del_estado', <?php echo $e['id_estado']; ?>)"></i>
                                </div>
                            </li>
                            <?php endforeach; ?>
                        </ul>
                    </div>
                </div>
            </div>

            <div class="tab-pane fade" id="fichas">
                <div class="card card-custom border-dark border-top border-3">
                    <div class="card-header bg-white fw-bold">Fichas / Categorías</div>
                    <div class="card-body">
                        <button class="btn btn-dark mb-3" onclick="modalAddFicha()">+ Nueva Ficha</button>
                        <table class="table table-hover align-middle">
                            <thead class="table-light"><tr><th>Icono</th><th>Nombre</th><th>Descripción</th><th>Acción</th></tr></thead>
                            <tbody>
                                <?php foreach ($tipos_bien as $t): ?>
                                <tr>
                                    <td><i class="<?php echo $t['icono']; ?> fa-lg text-primary"></i></td>
                                    <td><?php echo $t['nombre']; ?></td>
                                    <td><?php echo $t['descripcion']; ?></td>
                                    <td>
                                        <i class="fas fa-pen text-primary action-btn" onclick="modalEditFicha(<?php echo $t['id_tipo_bien']; ?>, '<?php echo $t['nombre']; ?>', '<?php echo $t['icono']; ?>', '<?php echo $t['descripcion']; ?>')"></i>
                                        <i class="fas fa-trash text-danger action-btn" onclick="delItem('del_ficha', <?php echo $t['id_tipo_bien']; ?>)"></i>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            <div class="tab-pane fade" id="herramientas">
                
                <div class="card border-primary mb-4 shadow-sm">
                    <div class="card-header bg-primary text-white fw-bold">
                        <i class="fas fa-magic me-2"></i> Creador de Fichas (Manual)
                    </div>
                    <div class="card-body">
                        <p class="text-muted small">Crea una categoría y define sus columnas sin usar Excel.</p>
                        
                        <form id="formCrearManual">
                            <div class="mb-3">
                                <label class="fw-bold">Nombre de la Categoría (ej: Climatización)</label>
                                <input type="text" id="manual_nombre" class="form-control" required placeholder="Nombre de la familia de productos">
                            </div>
                            
                            <label class="fw-bold mb-2">Columnas Técnicas (ej: Marca, Modelo, Frigorías)</label>
                            <div id="lista_campos_manual">
                                <div class="input-group mb-2">
                                    <input type="text" name="campos[]" class="form-control" placeholder="Nombre del Campo (ej: Marca)">
                                    <button type="button" class="btn btn-outline-danger" onclick="this.parentElement.remove()"><i class="fas fa-trash"></i></button>
                                </div>
                            </div>
                            
                            <button type="button" class="btn btn-outline-secondary btn-sm mb-3" onclick="agregarCampoManual()">
                                <i class="fas fa-plus"></i> Agregar otra columna
                            </button>

                            <div class="d-grid">
                                <button type="submit" class="btn btn-primary fw-bold">Guardar Estructura</button>
                            </div>
                        </form>
                    </div>
                </div>
                
                <div class="row g-4">
                    <div class="col-md-6">
                        <div class="card h-100 border-info">
                            <div class="card-header bg-info text-white fw-bold"><i class="fas fa-folder-plus"></i> Importar Estructura (CSV)</div>
                            <div class="card-body">
                                <form action="importar_estructura_procesar.php" method="POST" enctype="multipart/form-data">
                                    <div class="mb-3"><label class="fw-bold">Nombre Categoría</label><input type="text" name="nombre_categoria" class="form-control" required></div>
                                    <div class="mb-3"><label class="fw-bold">Archivo CSV</label><input type="file" name="archivo_estructura" class="form-control" accept=".csv" required></div>
                                    <button class="btn btn-info text-white w-100 fw-bold">Subir Estructura</button>
                                </form>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="card h-100 border-success">
                            <div class="card-header bg-success text-white fw-bold"><i class="fas fa-file-upload"></i> Importar Datos (CSV)</div>
                            <div class="card-body">
                                <form action="importar_datos_procesar.php" method="POST" enctype="multipart/form-data">
                                    <div class="mb-3"><label class="fw-bold">Categoría Destino</label><select name="id_tipo_bien" class="form-select" required><option value="">-- Seleccionar --</option><?php foreach($tipos_bien as $tb): ?><option value="<?php echo $tb['id_tipo_bien']; ?>"><?php echo htmlspecialchars($tb['nombre']); ?></option><?php endforeach; ?></select></div>
                                    <div class="mb-3"><label class="fw-bold">Archivo CSV</label><input type="file" name="archivo_datos" class="form-control" accept=".csv" required></div>
                                    <button class="btn btn-success w-100 fw-bold">Importar Bienes</button>
                                </form>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="modal fade" id="modAgente" tabindex="-1"><div class="modal-dialog"><div class="modal-content"><div class="modal-header bg-danger text-white"><h5 class="modal-title">Editar Agente</h5><button class="btn-close" data-bs-dismiss="modal"></button></div><div class="modal-body"><input type="hidden" id="edit_age_id"><label>Nombre:</label><input type="text" id="edit_age_nom" class="form-control mb-2"><label>Vida Útil (Años):</label><input type="number" id="edit_age_vida" class="form-control"></div><div class="modal-footer"><button class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button><button class="btn btn-danger" onclick="saveAgente()">Guardar</button></div></div></div></div>

    <div class="modal fade" id="modSimple" tabindex="-1"><div class="modal-dialog"><div class="modal-content"><div class="modal-header bg-secondary text-white"><h5 class="modal-title">Editar</h5><button class="btn-close" data-bs-dismiss="modal"></button></div><div class="modal-body"><input type="hidden" id="simp_accion"><input type="hidden" id="simp_tabla"><input type="hidden" id="simp_col_id"><input type="hidden" id="simp_col_val"><input type="hidden" id="simp_id"><label>Nombre/Valor:</label><input type="text" id="simp_val" class="form-control"></div><div class="modal-footer"><button class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button><button class="btn btn-primary" onclick="saveSimple()">Guardar</button></div></div></div></div>

    <div class="modal fade" id="modModelo" tabindex="-1"><div class="modal-dialog"><div class="modal-content"><div class="modal-header bg-primary text-white"><h5 class="modal-title">Editar Modelo</h5><button class="btn-close" data-bs-dismiss="modal"></button></div><div class="modal-body"><input type="hidden" id="mod_id"><label>Nombre Modelo:</label><input type="text" id="mod_nom" class="form-control mb-2"><label>Pertenece a Marca:</label><select id="mod_marca" class="form-select"></select></div><div class="modal-footer"><button class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button><button class="btn btn-primary" onclick="saveModelo()">Guardar</button></div></div></div></div>

    <div class="modal fade" id="modEstado" tabindex="-1"><div class="modal-dialog"><div class="modal-content"><div class="modal-header bg-primary text-white"><h5 class="modal-title">Editar Estado</h5><button class="btn-close" data-bs-dismiss="modal"></button></div><div class="modal-body"><input type="hidden" id="est_id"><label>Nombre:</label><input type="text" id="est_nom" class="form-control mb-2"><label>Ámbito:</label><select id="est_amb" class="form-select"><option value="general">General</option><option value="matafuego">Matafuego</option><option value="ambos">Ambos</option></select></div><div class="modal-footer"><button class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button><button class="btn btn-primary" onclick="saveEstado()">Guardar</button></div></div></div></div>

    <div class="modal fade" id="modFicha" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header bg-dark text-white">
                    <h5 class="modal-title">Editar Ficha / Categoría</h5>
                    <button class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
    <input type="hidden" id="fic_id">
    <input type="hidden" id="fic_action">
    
    <label class="fw-bold">Nombre de la Categoría:</label>
    <input type="text" id="fic_nom" class="form-control mb-3">
    
    <label class="fw-bold">Icono:</label>
    <div class="input-group mb-3">
        <span class="input-group-text"><i id="icon_preview" class="fas fa-box"></i></span>
        <input type="text" id="fic_ico" class="form-control" placeholder="fas fa-box">
        <button class="btn btn-outline-secondary" type="button" onclick="openIconPicker()">Seleccionar</button>
    </div>
    
    <label class="fw-bold">Descripción:</label>
    <input type="text" id="fic_desc" class="form-control mb-4">
    
    <div class="card bg-light border-0">
        <div class="card-header bg-secondary text-white fw-bold d-flex justify-content-between align-items-center">
            <span><i class="fas fa-columns me-1"></i> Columnas / Datos Técnicos</span>
            <button class="btn btn-sm btn-light text-dark fw-bold" onclick="addCampoEdit(null, '')"><i class="fas fa-plus"></i> Agregar</button>
        </div>
        <div class="card-body p-2" id="container_campos_edit" style="max-height: 200px; overflow-y: auto;">
            </div>
        <div class="card-footer text-muted small p-1 text-center">
            Si eliminas una columna, perderás los datos cargados en ella.
        </div>
    </div>
</div>
                <div class="modal-footer">
                    <button class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                    <button class="btn btn-primary" onclick="saveFicha()">Guardar</button>
                </div>
            </div>
        </div>
    </div>

    <div class="modal fade" id="modIconos" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Galería de Iconos</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <input type="text" id="filtro_iconos" class="form-control" placeholder="Buscar icono (ej: medico, pc, herramienta)..." onkeyup="filtrarIconos()">
                    </div>
                    <div class="icon-grid" id="gridIconos">
                        </div>
                </div>
            </div>
        </div>
    </div>

    <?php 
    // Función Helper Render
    function renderSection($pdo, $ambito, $tipos, $marcas, $color) {
        $modelos = getModelos($pdo, $ambito);
        echo '<div class="row g-4">';
        if($ambito == 'informatica') {
            echo '<div class="col-md-4"><div class="card card-custom h-100 border-'.$color.'"><div class="card-header bg-'.$color.' text-white fw-bold">Tipos Equipo</div><div class="card-body">';
            echo '<div class="input-group mb-2"><input type="text" id="add_it_nom" class="form-control"><button class="btn btn-'.$color.'" onclick="addSimple(\'add_tipo_it\',\'add_it_nom\')">+</button></div><ul class="list-group list-scrollable">';
            foreach($tipos as $t) echo '<li class="list-group-item d-flex justify-content-between align-items-center"><span>'.$t['nombre'].'</span><div><i class="fas fa-pen text-primary action-btn" onclick="modalEditSimple(\'edit_simple\',\'inventario_config_tipos_it\',\'id_tipo_it\',\'nombre\','.$t['id_tipo_it'].',\''.$t['nombre'].'\')"></i><i class="fas fa-trash text-danger action-btn" onclick="delItem(\'del_tipo_it\','.$t['id_tipo_it'].')"></i></div></li>';
            echo '</ul></div></div></div>';
        }
        $colWidth = ($ambito == 'informatica') ? '4' : '6';
        echo '<div class="col-md-'.$colWidth.'"><div class="card card-custom h-100 border-'.$color.'"><div class="card-header bg-'.$color.' text-white fw-bold">Marcas</div><div class="card-body">';
        echo '<div class="input-group mb-2"><input type="text" id="add_m_'.$ambito.'" class="form-control"><button class="btn btn-'.$color.'" onclick="addMarca(\''.$ambito.'\',\'add_m_'.$ambito.'\')">+</button></div><ul class="list-group list-scrollable">';
        foreach($marcas as $m) echo '<li class="list-group-item d-flex justify-content-between align-items-center"><span>'.$m['nombre'].'</span><div><i class="fas fa-pen text-primary action-btn" onclick="modalEditSimple(\'edit_marca\',\'inventario_config_marcas\',\'id_marca\',\'nombre\','.$m['id_marca'].',\''.$m['nombre'].'\')"></i><i class="fas fa-trash text-danger action-btn" onclick="delItem(\'del_marca\','.$m['id_marca'].')"></i></div></li>';
        echo '</ul></div></div></div>';
        echo '<div class="col-md-'.$colWidth.'"><div class="card card-custom h-100 border-'.$color.'"><div class="card-header bg-'.$color.' text-white fw-bold">Modelos</div><div class="card-body">';
        echo '<select id="sel_m_'.$ambito.'" class="form-select mb-2"><option value="">Marca...</option>'; foreach($marcas as $m) echo "<option value='".$m['id_marca']."'>".$m['nombre']."</option>";
        echo '</select><div class="input-group mb-2"><input type="text" id="add_mod_'.$ambito.'" class="form-control"><button class="btn btn-'.$color.'" onclick="addModelo(\'sel_m_'.$ambito.'\',\'add_mod_'.$ambito.'\')">+</button></div>';
        $jsonMarcas = htmlspecialchars(json_encode($marcas), ENT_QUOTES, 'UTF-8');
        echo '<ul class="list-group list-scrollable">';
        foreach($modelos as $mo) echo '<li class="list-group-item d-flex justify-content-between align-items-center"><span>'.$mo['nombre'].' <small class="text-muted">('.$mo['nombre_marca'].')</small></span><div><i class="fas fa-pen text-primary action-btn" onclick="modalEditModelo('.$mo['id_modelo'].',\''.$mo['nombre'].'\','.$mo['id_marca'].', '.$jsonMarcas.')"></i><i class="fas fa-trash text-danger action-btn" onclick="delItem(\'del_modelo\','.$mo['id_modelo'].')"></i></div></li>';
        echo '</ul></div></div></div></div>';
    }
    ?>

    <script>
    // --- LÓGICA ICON PICKER ---
    const commonIcons = [
        'fas fa-box', 'fas fa-box-open', 'fas fa-boxes', 'fas fa-pallet', 'fas fa-dolly', 'fas fa-truck-loading', 
        'fas fa-desktop', 'fas fa-laptop', 'fas fa-server', 'fas fa-print', 'fas fa-wifi', 'fas fa-keyboard', 'fas fa-mouse', 
        'fas fa-stethoscope', 'fas fa-syringe', 'fas fa-pills', 'fas fa-hospital', 'fas fa-user-md', 'fas fa-x-ray', 'fas fa-notes-medical', 'fas fa-heartbeat', 
        'fas fa-wrench', 'fas fa-tools', 'fas fa-hammer', 'fas fa-screwdriver', 'fas fa-ruler', 
        'fas fa-snowflake', 'fas fa-fire', 'fas fa-wind', 'fas fa-fan', 'fas fa-temperature-low', 
        'fas fa-chair', 'fas fa-couch', 'fas fa-table', 'fas fa-door-closed', 'fas fa-lightbulb', 
        'fas fa-video', 'fas fa-camera', 'fas fa-lock', 'fas fa-key', 'fas fa-shield-alt', 
        'fas fa-phone', 'fas fa-mobile-alt', 'fas fa-headset', 'fas fa-envelope', 
        'fas fa-car', 'fas fa-ambulance', 'fas fa-truck', 'fas fa-bus', 
        'fas fa-fire-extinguisher', 'fas fa-clipboard-list', 'fas fa-archive', 'fas fa-trash-alt'
    ];

    function openIconPicker() {
        let grid = document.getElementById('gridIconos');
        grid.innerHTML = '';
        commonIcons.forEach(icon => {
            let btn = document.createElement('div');
            btn.className = 'icon-btn';
            btn.innerHTML = `<i class="${icon}"></i>`;
            btn.onclick = function() {
                document.getElementById('fic_ico').value = icon;
                document.getElementById('icon_preview').className = icon;
                bootstrap.Modal.getInstance(document.getElementById('modIconos')).hide();
            };
            grid.appendChild(btn);
        });
        new bootstrap.Modal(document.getElementById('modIconos')).show();
    }

    function filtrarIconos() {
        let filtro = document.getElementById('filtro_iconos').value.toLowerCase();
        let btns = document.querySelectorAll('.icon-btn');
        btns.forEach(btn => {
            let iconClass = btn.querySelector('i').className;
            if(iconClass.includes(filtro)) btn.style.display = 'block';
            else btn.style.display = 'none';
        });
    }

    // --- LÓGICA CREADOR MANUAL ---
    function agregarCampoManual() {
        let html = `<div class="input-group mb-2"><input type="text" name="campos[]" class="form-control" placeholder="Nombre del Campo"><button type="button" class="btn btn-outline-danger" onclick="this.parentElement.remove()"><i class="fas fa-trash"></i></button></div>`;
        document.getElementById('lista_campos_manual').insertAdjacentHTML('beforeend', html);
    }

    if(document.getElementById('formCrearManual')) {
        document.getElementById('formCrearManual').addEventListener('submit', function(e) {
            e.preventDefault();
            let nombre = document.getElementById('manual_nombre').value;
            let campos = [];
            document.querySelectorAll('input[name="campos[]"]').forEach(i => { if(i.value.trim()) campos.push(i.value.trim()); });

            if(campos.length === 0) if(!confirm("No agregaste columnas. ¿Crear vacía?")) return;

            $.post('ajax_crear_estructura_manual.php', {nombre_categoria: nombre, campos: campos}, function(res) {
                let data = (typeof res === 'object') ? res : JSON.parse(res);
                if(data.status === 'ok') { alert('¡Estructura creada!'); window.location.reload(); } 
                else { alert('Error: ' + data.msg); }
            });
        });
    }

    // --- LÓGICA FICHAS (CORREGIDA Y BLINDADA) ---
    function modalAddFicha() {
        $('#fic_action').val('add_ficha'); 
        $('#fic_id').val(''); 
        $('#fic_nom').val(''); 
        $('#fic_ico').val(''); 
        $('#fic_desc').val('');
        $('#icon_preview').attr('class', 'fas fa-box');
        $('#container_campos_edit').html('<div class="text-center text-muted small py-2">Guarda primero para agregar columnas.</div>');
        $('#btnGuardarFicha').prop('disabled', false); // Habilitar guardar
        new bootstrap.Modal('#modFicha').show();
    }

    function modalEditFicha(id, nom, ico, desc) {
        $('#fic_action').val('edit_ficha'); 
        $('#fic_id').val(id); 
        $('#fic_nom').val(nom); 
        $('#fic_ico').val(ico); 
        $('#fic_desc').val(desc);
        $('#icon_preview').attr('class', ico);
        
        // BLOQUEAMOS EL BOTÓN GUARDAR HASTA QUE CARGUE
        let btnGuardar = $('#modFicha .modal-footer button.btn-primary');
        btnGuardar.prop('disabled', true).text('Cargando...');
        
        $('#container_campos_edit').html('<div class="text-center"><i class="fas fa-spinner fa-spin"></i> Cargando estructura...</div>');
        
        // Petición AJAX Segura
        $.ajax({
            url: 'ajax_config_admin.php',
            type: 'POST',
            dataType: 'json', // Forzamos JSON
            data: {accion: 'get_ficha_campos', id: id},
            success: function(campos) {
                $('#container_campos_edit').empty();
                
                // Si viene vacío o con datos, iteramos
                if(Array.isArray(campos)) {
                    if(campos.length === 0) {
                       // Opcional: mostrar mensaje de vacío
                    } else {
                        campos.forEach(c => {
                            addCampoEdit(c.id_campo, c.etiqueta);
                        });
                    }
                    // HABILITAMOS GUARDAR SOLO SI FUE ÉXITO
                    btnGuardar.prop('disabled', false).text('Guardar');
                } else {
                    $('#container_campos_edit').html('<div class="text-danger">Error: Respuesta inválida del servidor.</div>');
                }
            },
            error: function(xhr, status, error) {
                console.error(error);
                $('#container_campos_edit').html('<div class="text-danger fw-bold">Error al cargar columnas. NO GUARDAR.</div>');
                // El botón sigue deshabilitado por seguridad
            }
        });

        new bootstrap.Modal('#modFicha').show();
    }

    function addCampoEdit(id, valor) {
        let inputId = id ? id : 'new_' + Math.floor(Math.random() * 10000);
        let html = `
        <div class="input-group mb-2 item-campo" data-db-id="${id || ''}">
            <span class="input-group-text"><i class="fas fa-tag"></i></span>
            <input type="text" class="form-control input-etiqueta" value="${valor}" placeholder="Nombre Columna">
            <button class="btn btn-outline-danger" onclick="this.parentElement.remove()" title="Eliminar"><i class="fas fa-times"></i></button>
        </div>`;
        $('#container_campos_edit').append(html);
    }

    function saveFicha() {
        let accion = $('#fic_action').val();
        let datos = {
            accion: accion,
            id: $('#fic_id').val(),
            nombre: $('#fic_nom').val(),
            icono: $('#fic_ico').val(),
            descripcion: $('#fic_desc').val()
        };

        if (accion === 'edit_ficha') {
            let listaCampos = [];
            $('.item-campo').each(function() {
                let id_db = $(this).data('db-id');
                let val = $(this).find('.input-etiqueta').val();
                if(val && val.trim()) {
                    listaCampos.push({id: id_db, valor: val.trim()});
                }
            });
            datos.campos = listaCampos;
        }

        $.post('ajax_config_admin.php', datos, function(res) {
            // Manejo robusto de respuesta (string o json)
            let data = (typeof res === 'object') ? res : JSON.parse(res);
            
            if(data.status === 'ok') {
                location.reload(); 
            } else {
                alert('Error al guardar: ' + (data.msg || 'Desconocido'));
            }
        }, 'json');
    }

    // --- FUNCIONES AUXILIARES VIEJAS (Matafuegos, etc) ---
    function delItem(acc, id) { if(confirm('¿Eliminar?')) $.post('ajax_config_admin.php',{accion:acc,id:id},res=>{ if(res.status=='ok') location.reload(); },'json'); }
    
    function addAgente() {
        let n=$('#add_agente_nom').val(), v=$('#add_agente_vida').val();
        $.post('ajax_config_admin.php',{accion:'add_agente',valor:n,vida_util:v},res=>{ if(res.status=='ok') location.reload(); },'json');
    }
    function modalEditAgente(id, nom, vida) {
        $('#edit_age_id').val(id); $('#edit_age_nom').val(nom); $('#edit_age_vida').val(vida);
        new bootstrap.Modal('#modAgente').show();
    }
    function saveAgente() {
        $.post('ajax_config_admin.php',{accion:'edit_agente',id:$('#edit_age_id').val(),valor:$('#edit_age_nom').val(),vida_util:$('#edit_age_vida').val()},res=>{ if(res.status=='ok') location.reload(); },'json');
    }
    
    function addSimple(acc, inputId) {
        let val=$('#'+inputId).val(); if(!val) return;
        $.post('ajax_config_admin.php',{accion:acc,valor:val},res=>{ if(res.status=='ok') location.reload(); },'json');
    }
    function modalEditSimple(acc, tabla, colId, colVal, id, val) {
        $('#simp_accion').val(acc); $('#simp_tabla').val(tabla); $('#simp_col_id').val(colId); $('#simp_col_val').val(colVal); $('#simp_id').val(id); $('#simp_val').val(val);
        new bootstrap.Modal('#modSimple').show();
    }
    function saveSimple() {
        $.post('ajax_config_admin.php',{accion:$('#simp_accion').val(),tabla:$('#simp_tabla').val(),campo_id:$('#simp_col_id').val(),campo_val:$('#simp_col_val').val(),id:$('#simp_id').val(),valor:$('#simp_val').val()},res=>{ if(res.status=='ok') location.reload(); },'json');
    }
    
    function addMarca(ambito, inputId) {
        let val=$('#'+inputId).val(); if(!val) return;
        $.post('ajax_config_admin.php',{accion:'add_marca',valor:val,ambito:ambito},res=>{ if(res.status=='ok') location.reload(); },'json');
    }
    function addModelo(selId, inputId) {
        let idMarca=$('#'+selId).val(), val=$('#'+inputId).val(); if(!val||!idMarca) return alert('Falta marca');
        $.post('ajax_config_admin.php',{accion:'add_modelo',valor:val,id_marca:idMarca},res=>{ if(res.status=='ok') location.reload(); },'json');
    }
    function modalEditModelo(id, nom, idMarcaActual, listaMarcas) {
        $('#mod_id').val(id); $('#mod_nom').val(nom);
        let sel = $('#mod_marca').empty();
        listaMarcas.forEach(m => {
            let selected = (m.id_marca == idMarcaActual) ? 'selected' : '';
            sel.append(`<option value="${m.id_marca}" ${selected}>${m.nombre}</option>`);
        });
        new bootstrap.Modal('#modModelo').show();
    }
    function saveModelo() {
        $.post('ajax_config_admin.php',{accion:'edit_modelo',id:$('#mod_id').val(),valor:$('#mod_nom').val(),id_marca:$('#mod_marca').val()},res=>{ if(res.status=='ok') location.reload(); },'json');
    }
    
    function addEstado() {
        let n=$('#add_est_nom').val(), a=$('#add_est_amb').val(); if(!n) return;
        $.post('ajax_config_admin.php',{accion:'add_estado',valor:n,ambito:a},res=>{ if(res.status=='ok') location.reload(); },'json');
    }
    function modalEditEstado(id, nom, amb) {
        $('#est_id').val(id); $('#est_nom').val(nom); $('#est_amb').val(amb);
        new bootstrap.Modal('#modEstado').show();
    }
    function saveEstado() {
        $.post('ajax_config_admin.php',{accion:'edit_estado',id:$('#est_id').val(),valor:$('#est_nom').val(),ambito:$('#est_amb').val()},res=>{ if(res.status=='ok') location.reload(); },'json');
    }
</script>
</body>
</html>