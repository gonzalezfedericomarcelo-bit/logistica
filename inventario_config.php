<?php
// Archivo: inventario_config.php
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

// CARGA DE DATOS
$tipos_bien = getTable($pdo, 'inventario_tipos_bien', 'id_tipo_bien');
$agentes = $pdo->query("SELECT * FROM inventario_config_matafuegos ORDER BY tipo_carga ASC")->fetchAll(PDO::FETCH_ASSOC);
$clases = getTable($pdo, 'inventario_config_clases', 'nombre');
$capacidades = getTable($pdo, 'inventario_config_capacidades', 'capacidad');
$tipos_it = getTable($pdo, 'inventario_config_tipos_it');
$estados = getTable($pdo, 'inventario_estados');

// Para los selects de los modales, necesitamos todas las marcas disponibles por ámbito
$all_marcas_it = getMarcas($pdo, 'informatica');
$all_marcas_cam = getMarcas($pdo, 'camara');
$all_marcas_tel = getMarcas($pdo, 'telefono');
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0"> <title>Configuración Inventario</title>
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
            <li class="nav-item"><button class="nav-link" data-bs-toggle="tab" data-bs-target="#herramientas">Herramientas</button></li>
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
                            <thead class="table-light"><tr><th>Icono</th><th>Nombre</th><th>Descripción</th><th>Técnico</th><th>Acción</th></tr></thead>
                            <tbody>
                                <?php foreach ($tipos_bien as $t): ?>
                                <tr>
                                    <td><i class="<?php echo $t['icono']; ?>"></i></td>
                                    <td><?php echo $t['nombre']; ?></td>
                                    <td><?php echo $t['descripcion']; ?></td>
                                    <td><?php echo $t['tiene_campos_tecnicos']?'Si':'No'; ?></td>
                                    <td>
                                        <i class="fas fa-pen text-primary action-btn" onclick="modalEditFicha(<?php echo $t['id_tipo_bien']; ?>, '<?php echo $t['nombre']; ?>', '<?php echo $t['icono']; ?>', '<?php echo $t['descripcion']; ?>', <?php echo $t['tiene_campos_tecnicos']; ?>)"></i>
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
                <div class="row">
                    <div class="col-md-6"><div class="card border-info"><div class="card-header bg-info text-white">Importar Estructura</div><div class="card-body"><form action="importar_estructura_procesar.php" method="POST" enctype="multipart/form-data"><input type="file" name="archivo_csv" class="form-control mb-2"><button class="btn btn-info text-white">Subir</button></form></div></div></div>
                    <div class="col-md-6"><div class="card border-success"><div class="card-header bg-success text-white">Importar Datos</div><div class="card-body"><form action="importar_datos_procesar.php" method="POST" enctype="multipart/form-data"><input type="file" name="archivo_datos" class="form-control mb-2"><button class="btn btn-success">Importar</button></form></div></div></div>
                </div>
            </div>
        </div>
    </div>

    <div class="modal fade" id="modAgente" tabindex="-1"><div class="modal-dialog"><div class="modal-content"><div class="modal-header bg-danger text-white"><h5 class="modal-title">Editar Agente</h5><button class="btn-close" data-bs-dismiss="modal"></button></div><div class="modal-body">
        <input type="hidden" id="edit_age_id">
        <label>Nombre:</label><input type="text" id="edit_age_nom" class="form-control mb-2">
        <label>Vida Útil (Años):</label><input type="number" id="edit_age_vida" class="form-control">
    </div><div class="modal-footer"><button class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button><button class="btn btn-danger" onclick="saveAgente()">Guardar</button></div></div></div></div>

    <div class="modal fade" id="modSimple" tabindex="-1"><div class="modal-dialog"><div class="modal-content"><div class="modal-header bg-secondary text-white"><h5 class="modal-title">Editar</h5><button class="btn-close" data-bs-dismiss="modal"></button></div><div class="modal-body">
        <input type="hidden" id="simp_accion"><input type="hidden" id="simp_tabla"><input type="hidden" id="simp_col_id"><input type="hidden" id="simp_col_val"><input type="hidden" id="simp_id">
        <label>Nombre/Valor:</label><input type="text" id="simp_val" class="form-control">
    </div><div class="modal-footer"><button class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button><button class="btn btn-primary" onclick="saveSimple()">Guardar</button></div></div></div></div>

    <div class="modal fade" id="modModelo" tabindex="-1"><div class="modal-dialog"><div class="modal-content"><div class="modal-header bg-primary text-white"><h5 class="modal-title">Editar Modelo</h5><button class="btn-close" data-bs-dismiss="modal"></button></div><div class="modal-body">
        <input type="hidden" id="mod_id">
        <label>Nombre Modelo:</label><input type="text" id="mod_nom" class="form-control mb-2">
        <label>Pertenece a Marca:</label><select id="mod_marca" class="form-select"></select>
    </div><div class="modal-footer"><button class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button><button class="btn btn-primary" onclick="saveModelo()">Guardar</button></div></div></div></div>

    <div class="modal fade" id="modEstado" tabindex="-1"><div class="modal-dialog"><div class="modal-content"><div class="modal-header bg-primary text-white"><h5 class="modal-title">Editar Estado</h5><button class="btn-close" data-bs-dismiss="modal"></button></div><div class="modal-body">
        <input type="hidden" id="est_id">
        <label>Nombre:</label><input type="text" id="est_nom" class="form-control mb-2">
        <label>Ámbito:</label><select id="est_amb" class="form-select"><option value="general">General</option><option value="matafuego">Matafuego</option><option value="ambos">Ambos</option></select>
    </div><div class="modal-footer"><button class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button><button class="btn btn-primary" onclick="saveEstado()">Guardar</button></div></div></div></div>

    <div class="modal fade" id="modFicha" tabindex="-1"><div class="modal-dialog"><div class="modal-content"><div class="modal-header bg-dark text-white"><h5 class="modal-title">Ficha</h5><button class="btn-close" data-bs-dismiss="modal"></button></div><div class="modal-body">
        <input type="hidden" id="fic_id"><input type="hidden" id="fic_action">
        <label>Nombre:</label><input type="text" id="fic_nom" class="form-control mb-2">
        <label>Icono (FontAwesome):</label><input type="text" id="fic_ico" class="form-control mb-2" placeholder="fas fa-box">
        <label>Descripción:</label><input type="text" id="fic_desc" class="form-control mb-2">
        <div class="form-check"><input class="form-check-input" type="checkbox" id="fic_tec"><label class="form-check-label">Es Matafuego (Campos Técnicos)</label></div>
    </div><div class="modal-footer"><button class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button><button class="btn btn-primary" onclick="saveFicha()">Guardar</button></div></div></div></div>

    <?php 
    // Función Helper para renderizar secciones repetitivas (Info, Camara, Tel)
    function renderSection($pdo, $ambito, $tipos, $marcas, $color) {
        $modelos = getModelos($pdo, $ambito);
        echo '<div class="row g-4">';
        if($ambito == 'informatica') {
            echo '<div class="col-md-4"><div class="card card-custom h-100 border-'.$color.'"><div class="card-header bg-'.$color.' text-white fw-bold">Tipos Equipo</div><div class="card-body">';
            echo '<div class="input-group mb-2"><input type="text" id="add_it_nom" class="form-control"><button class="btn btn-'.$color.'" onclick="addSimple(\'add_tipo_it\',\'add_it_nom\')">+</button></div><ul class="list-group list-scrollable">';
            foreach($tipos as $t) {
                echo '<li class="list-group-item d-flex justify-content-between align-items-center"><span>'.$t['nombre'].'</span>';
                echo '<div><i class="fas fa-pen text-primary action-btn" onclick="modalEditSimple(\'edit_simple\',\'inventario_config_tipos_it\',\'id_tipo_it\',\'nombre\','.$t['id_tipo_it'].',\''.$t['nombre'].'\')"></i><i class="fas fa-trash text-danger action-btn" onclick="delItem(\'del_tipo_it\','.$t['id_tipo_it'].')"></i></div></li>';
            }
            echo '</ul></div></div></div>';
        }
        
        $colWidth = ($ambito == 'informatica') ? '4' : '6';
        
        // Marcas
        echo '<div class="col-md-'.$colWidth.'"><div class="card card-custom h-100 border-'.$color.'"><div class="card-header bg-'.$color.' text-white fw-bold">Marcas</div><div class="card-body">';
        echo '<div class="input-group mb-2"><input type="text" id="add_m_'.$ambito.'" class="form-control"><button class="btn btn-'.$color.'" onclick="addMarca(\''.$ambito.'\',\'add_m_'.$ambito.'\')">+</button></div><ul class="list-group list-scrollable">';
        foreach($marcas as $m) {
            echo '<li class="list-group-item d-flex justify-content-between align-items-center"><span>'.$m['nombre'].'</span>';
            echo '<div><i class="fas fa-pen text-primary action-btn" onclick="modalEditSimple(\'edit_marca\',\'inventario_config_marcas\',\'id_marca\',\'nombre\','.$m['id_marca'].',\''.$m['nombre'].'\')"></i><i class="fas fa-trash text-danger action-btn" onclick="delItem(\'del_marca\','.$m['id_marca'].')"></i></div></li>';
        }
        echo '</ul></div></div></div>';

        // Modelos
        echo '<div class="col-md-'.$colWidth.'"><div class="card card-custom h-100 border-'.$color.'"><div class="card-header bg-'.$color.' text-white fw-bold">Modelos</div><div class="card-body">';
        echo '<select id="sel_m_'.$ambito.'" class="form-select mb-2"><option value="">Marca...</option>';
        foreach($marcas as $m) echo "<option value='".$m['id_marca']."'>".$m['nombre']."</option>";
        echo '</select><div class="input-group mb-2"><input type="text" id="add_mod_'.$ambito.'" class="form-control"><button class="btn btn-'.$color.'" onclick="addModelo(\'sel_m_'.$ambito.'\',\'add_mod_'.$ambito.'\')">+</button></div>';
        
        // Generamos JSON de marcas para el modal de edición de modelos de este ámbito
        $jsonMarcas = htmlspecialchars(json_encode($marcas), ENT_QUOTES, 'UTF-8');
        
        echo '<ul class="list-group list-scrollable">';
        foreach($modelos as $mo) {
            echo '<li class="list-group-item d-flex justify-content-between align-items-center"><span>'.$mo['nombre'].' <small class="text-muted">('.$mo['nombre_marca'].')</small></span>';
            // Pasamos JSON de marcas al onclick para llenar el select del modal
            echo '<div><i class="fas fa-pen text-primary action-btn" onclick="modalEditModelo('.$mo['id_modelo'].',\''.$mo['nombre'].'\','.$mo['id_marca'].', '.$jsonMarcas.')"></i><i class="fas fa-trash text-danger action-btn" onclick="delItem(\'del_modelo\','.$mo['id_modelo'].')"></i></div></li>';
        }
        echo '</ul></div></div></div></div>';
    }
    ?>

    <script>
    // --- LÓGICA AGENTES ---
    function addAgente() {
        let n=$('#add_agente_nom').val(), v=$('#add_agente_vida').val();
        if(!n||!v) return alert('Datos incompletos');
        $.post('ajax_config_admin.php',{accion:'add_agente',valor:n,vida_util:v},res=>{ if(res.status=='ok') location.reload(); },'json');
    }
    function modalEditAgente(id, nom, vida) {
        $('#edit_age_id').val(id); $('#edit_age_nom').val(nom); $('#edit_age_vida').val(vida);
        new bootstrap.Modal('#modAgente').show();
    }
    function saveAgente() {
        $.post('ajax_config_admin.php',{accion:'edit_agente',id:$('#edit_age_id').val(),valor:$('#edit_age_nom').val(),vida_util:$('#edit_age_vida').val()},res=>{ if(res.status=='ok') location.reload(); },'json');
    }

    // --- LÓGICA SIMPLE (Clases, Caps, Marcas, Tipos) ---
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

    // --- LÓGICA MARCAS Y MODELOS ---
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

    // --- LÓGICA ESTADOS ---
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

    // --- LÓGICA FICHAS ---
    function modalAddFicha() {
        $('#fic_action').val('add_ficha'); $('#fic_id').val(''); $('#fic_nom').val(''); $('#fic_ico').val(''); $('#fic_desc').val(''); $('#fic_tec').prop('checked',false);
        new bootstrap.Modal('#modFicha').show();
    }
    function modalEditFicha(id, nom, ico, desc, tec) {
        $('#fic_action').val('edit_ficha'); $('#fic_id').val(id); $('#fic_nom').val(nom); $('#fic_ico').val(ico); $('#fic_desc').val(desc); $('#fic_tec').prop('checked', tec==1);
        new bootstrap.Modal('#modFicha').show();
    }
    function saveFicha() {
        $.post('ajax_config_admin.php',{
            accion: $('#fic_action').val(), id: $('#fic_id').val(), nombre: $('#fic_nom').val(), icono: $('#fic_ico').val(), descripcion: $('#fic_desc').val(), es_tecnico: $('#fic_tec').is(':checked')
        },res=>{ if(res.status=='ok') location.reload(); },'json');
    }

    function delItem(acc, id) { if(confirm('¿Eliminar?')) $.post('ajax_config_admin.php',{accion:acc,id:id},res=>{ if(res.status=='ok') location.reload(); },'json'); }
    </script>
</body>
</html>