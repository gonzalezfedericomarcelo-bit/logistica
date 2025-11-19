<?php
// ARCHIVO DE MIGRACIÓN DE DATOS (v4 - CORREGIDO ERROR DE COMMIT IMPLÍCITO)
// Se reemplazó TRUNCATE TABLE (que causa auto-commit) por DELETE FROM (respeta la transacción).
// http://localhost/logistica/migracion.php

set_time_limit(600);
ini_set('display_errors', 1);
error_reporting(E_ALL);

echo "<!DOCTYPE html><html lang='es'><head><meta charset='UTF-8'><title>Migración de Datos</title>";
echo "<link href='https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css' rel='stylesheet'>";
echo "<style>body { font-family: monospace; padding: 20px; background-color: #1a1a1a; color: #f1f1f1; } .log { font-size: 0.9em; } .log-success { color: #28a745; } .log-error { color: #dc3545; font-weight: bold; } .log-warn { color: #ffc107; } .log-info { color: #0dcaf0; } .log-header { font-size: 1.5em; color: #fff; margin-top: 20px; border-bottom: 1px solid #555; }</style>";
echo "</head><body><div class='container'>";
echo "<h1><i class='fas fa-sync'></i> Script de Migración de Base de Datos (v4)</h1>";
echo "<p class='log-warn'>ADVERTENCIA: No recargue esta página. La migración puede tardar varios minutos.</p>";
echo "<div class='log'>";

ob_flush();
flush();

include 'conexion.php'; // Para la función generar_nuevo_numero_orden()

$db_vieja_config = [
    'host' => 'localhost',
    'dbname' => 'gestion_logistica_vieja',
    'user' => 'root',
    'pass' => ''
];

$db_final_config = [
    'host' => 'localhost',
    'dbname' => 'gestion_logistica_final',
    'user' => 'root',
    'pass' => ''
];

$options = [
    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    PDO::MYSQL_ATTR_INIT_COMMAND => 'SET NAMES utf8mb4'
];

try {
    $dsn_vieja = "mysql:host={$db_vieja_config['host']};dbname={$db_vieja_config['dbname']};charset=utf8mb4";
    $pdo_vieja = new PDO($dsn_vieja, $db_vieja_config['user'], $db_vieja_config['pass'], $options);
    echo "<div class='log-success'>[PASO 1/7] Conectado a la BD '{$db_vieja_config['dbname']}' (VIEJA) exitosamente.</div>";

    $dsn_final = "mysql:host={$db_final_config['host']};dbname={$db_final_config['dbname']};charset=utf8mb4";
    $pdo_final = new PDO($dsn_final, $db_final_config['user'], $db_final_config['pass'], $options);
    echo "<div class='log-success'>[PASO 1/7] Conectado a la BD '{$db_final_config['dbname']}' (FINAL) exitosamente.</div>";

    $map_usuarios = [];
    $map_categorias = [];
    $map_areas = [];
    $map_destinos = [];
    $map_tareas = [];

    // --- INICIAR TRANSACCIÓN PRINCIPAL EN LA BD FINAL ---
    $pdo_final->beginTransaction();
    
    // --- 2. LIMPIEZA DE TABLAS FINALES ---
    echo "<div class='log-header'>[PASO 2/7] Limpiando tablas de destino...</div>";
    $pdo_final->exec("SET FOREIGN_KEY_CHECKS=0;");
    $tablas_a_limpiar = ['usuarios', 'categorias', 'areas', 'destinos_internos', 'pedidos_trabajo', 'tareas', 'actualizaciones_tarea', 'adjuntos_tarea', 'roles', 'permisos', 'rol_permiso'];
    foreach ($tablas_a_limpiar as $tabla) {
        // *** INICIO CORRECCIÓN (v4): Usar DELETE FROM en lugar de TRUNCATE ***
        $pdo_final->exec("DELETE FROM `{$tabla}`;");
        // *** FIN CORRECCIÓN (v4) ***
        echo "<div class='log-info'>- Tabla '{$tabla}' limpiada.</div>";
    }
    $pdo_final->exec("SET FOREIGN_KEY_CHECKS=1;");
    echo "<div class='log-success'>[PASO 2/7] Tablas de destino limpiadas.</div>";
    ob_flush(); flush();
    
    // --- 2.5 RE-POBLAR ROLES Y PERMISOS ---
    echo "<div class='log-header'>[PASO 2.5/7] Repoblando Roles y Permisos...</div>";
    
    // Roles
    $pdo_final->exec("INSERT INTO `roles` (`nombre_rol`, `descripcion`) VALUES
        ('admin', 'Acceso total al sistema'),
        ('auxiliar', 'Puede cargar pedidos y ver estado'),
        ('empleado', 'Técnico asignado a tareas'),
        ('encargado', 'Aprueba pedidos y asigna tareas');");
        
    // Permisos
    $pdo_final->exec("INSERT INTO `permisos` (`clave_permiso`, `nombre_mostrar`) VALUES
        ('acceso_avisos', 'Acceso Avisos'),
        ('acceso_dashboard', 'Acceso Dashboard'),
        ('acceso_pedidos_crear', 'Crear Pedidos'),
        ('acceso_pedidos_lista', 'Ver Lista Pedidos (Admin)'),
        ('acceso_perfil_php', 'Acceso Perfil'),
        ('acceso_tareas_lista', 'Ver Lista Tareas'),
        ('acceso_tareas_ver', 'Ver Detalle Tarea'),
        ('admin_areas', 'Admin Áreas'),
        ('admin_categorias', 'Admin Categorías'),
        ('admin_destinos', 'Admin Destinos'),
        ('admin_remitos', 'Admin Remitos'),
        ('admin_roles', 'Admin Roles'),
        ('admin_usuarios', 'Admin Usuarios'),
        ('admin_usuarios_reset_pass', 'Reset Pass Usuarios'),
        ('admin_usuarios_toggle_status', 'Act/Desact Usuarios'),
        ('crear_tarea_directa', 'Crear Tarea Directa (Admin)'),
        ('ver_chat', 'Acceso a Chat');");

    // Asignaciones de Roles (Rol_Permiso)
    $pdo_final->exec("INSERT INTO `rol_permiso` (`nombre_rol`, `clave_permiso`) VALUES
        ('auxiliar', 'acceso_dashboard'),
        ('auxiliar', 'acceso_pedidos_crear'),
        ('auxiliar', 'acceso_perfil_php'),
        ('auxiliar', 'acceso_tareas_lista'),
        ('auxiliar', 'acceso_tareas_ver'),
        ('auxiliar', 'ver_chat'),
        ('empleado', 'acceso_dashboard'),
        ('empleado', 'acceso_perfil_php'),
        ('empleado', 'acceso_tareas_lista'),
        ('empleado', 'acceso_tareas_ver'),
        ('empleado', 'ver_chat'),
        ('encargado', 'acceso_dashboard'),
        ('encargado', 'acceso_pedidos_crear'),
        ('encargado', 'acceso_pedidos_lista'),
        ('encargado', 'acceso_perfil_php'),
        ('encargado', 'acceso_tareas_lista'),
        ('encargado', 'acceso_tareas_ver'),
        ('encargado', 'crear_tarea_directa'),
        ('encargado', 'ver_chat');");

    echo "<div class='log-success'>[PASO 2.5/7] Roles y permisos de la nueva estructura cargados.</div>";
    ob_flush(); flush();


    // --- 3. MIGRAR TABLAS SIMPLES (Categorías, Áreas, Destinos) ---
    echo "<div class='log-header'>[PASO 3/7] Migrando tablas simples...</div>";
    
    // Categorías
    $stmt_v = $pdo_vieja->query("SELECT * FROM categorias");
    $stmt_n = $pdo_final->prepare("INSERT INTO categorias (id_categoria, nombre, descripcion, color) VALUES (:id, :nom, :desc, :color)");
    while ($row = $stmt_v->fetch()) {
        $stmt_n->execute([
            ':id' => $row['id_categoria'],
            ':nom' => $row['nombre'],
            ':desc' => $row['descripcion'] ?? null,
            ':color' => $row['color'] ?? '#6c757d'
        ]);
        $map_categorias[$row['id_categoria']] = $row['id_categoria'];
    }
    echo "<div class='log-success'>- Categorías migradas.</div>";

    // Áreas
    if ($pdo_vieja->query("SHOW TABLES LIKE 'areas'")->rowCount() > 0) {
        $stmt_v = $pdo_vieja->query("SELECT * FROM areas");
        $stmt_n = $pdo_final->prepare("INSERT INTO areas (id_area, nombre, descripcion) VALUES (:id, :nom, :desc)");
        while ($row = $stmt_v->fetch()) {
            $stmt_n->execute([
                ':id' => $row['id_area'],
                ':nom' => $row['nombre'],
                ':desc' => $row['descripcion'] ?? null
            ]);
            $map_areas[$row['id_area']] = $row['id_area'];
        }
        echo "<div class='log-success'>- Áreas migradas.</div>";
    } else {
        echo "<div class='log-warn'>- Tabla 'areas' no encontrada en la BD vieja. Se usará la de la BD nueva. (¡ASEGÚRATE DE TENER UN 'id_area=1' CREADO!)</div>";
        $pdo_final->exec("INSERT IGNORE INTO `areas` (`id_area`, `nombre`, `descripcion`) VALUES (1, 'Mantenimiento General', 'Área por defecto para tareas de admin')");
    }

    // Destinos
    if ($pdo_vieja->query("SHOW TABLES LIKE 'destinos_internos'")->rowCount() > 0) {
        $stmt_v = $pdo_vieja->query("SELECT * FROM destinos_internos");
        $stmt_n = $pdo_final->prepare("INSERT INTO destinos_internos (id_destino, nombre, ubicacion_referencia) VALUES (:id, :nom, :ubic)");
        while ($row = $stmt_v->fetch()) {
            $stmt_n->execute([
                ':id' => $row['id_destino'],
                ':nom' => $row['nombre'],
                ':ubic' => $row['ubicacion_referencia'] ?? null
            ]);
            $map_destinos[$row['id_destino']] = $row['id_destino'];
        }
        echo "<div class='log-success'>- Destinos Internos migrados.</div>";
    } else {
        echo "<div class='log-warn'>- Tabla 'destinos_internos' no encontrada en la BD vieja. Se omitirá.</div>";
    }
    ob_flush(); flush();

    // --- 4. MIGRAR USUARIOS ---
    echo "<div class='log-header'>[PASO 4/7] Migrando usuarios...</div>";
    $stmt_v = $pdo_vieja->query("SELECT * FROM usuarios");
    $stmt_n = $pdo_final->prepare("INSERT INTO usuarios (id_usuario, nombre_completo, usuario, password, rol, email, telefono, genero, foto_perfil, firma_imagen_path, activo, reset_pendiente) 
                                   VALUES (:id, :nom, :user, :pass, :rol, :email, :tel, :gen, :foto, :firma, 1, 0)");
    while ($row = $stmt_v->fetch()) {
        $stmt_n->execute([
            ':id' => $row['id_usuario'],
            ':nom' => $row['nombre_completo'],
            ':user' => $row['usuario'],
            ':pass' => $row['password'],
            ':rol' => $row['rol'],
            ':email' => $row['email'] ?? null,
            ':tel' => $row['telefono'] ?? null,
            ':gen' => $row['genero'] ?? 'otro',
            ':foto' => $row['foto_perfil'] ?? 'default.png',
            ':firma' => $row['firma_imagen_path'] ?? null
        ]);
        $map_usuarios[$row['id_usuario']] = $row['id_usuario'];
    }
    echo "<div class='log-success'>[PASO 4/7] Usuarios migrados.</div>";
    ob_flush(); flush();

    // --- 5. MIGRAR TAREAS (Creando Pedidos Fantasma) ---
    echo "<div class='log-header'>[PASO 5/7] Migrando tareas y creando pedidos fantasma...</div>";
    
    $stmt_get_user_name = $pdo_vieja->prepare("SELECT nombre_completo FROM usuarios WHERE id_usuario = :id");
    $stmt_get_cat_name = $pdo_vieja->prepare("SELECT nombre FROM categorias WHERE id_categoria = :id");

    $stmt_v = $pdo_vieja->query("SELECT * FROM tareas ORDER BY id_tarea ASC");
    $tareas_viejas = $stmt_v->fetchAll();
    
    $sql_insert_pedido = "INSERT INTO pedidos_trabajo
                        (id_pedido, numero_orden, titulo_pedido, id_solicitante, id_auxiliar, id_area, area_solicitante, prioridad, fecha_requerida, descripcion_sintomas, solicitante_real_nombre, fecha_emision, estado_pedido)
                    VALUES
                        (:id_ped, :num_orden, :titulo_ped, :id_solic, :id_aux, :id_area, :area_nombre, :prio, :fecha_req, :descrip, :solic_real, :fecha_creacion, 'aprobado')";
    $stmt_insert_pedido = $pdo_final->prepare($sql_insert_pedido);
    
    $sql_insert_tarea = "INSERT INTO tareas 
                        (id_tarea, titulo, descripcion, id_creador, id_asignado, id_categoria, prioridad, fecha_limite, adjunto_obligatorio, fecha_creacion, estado, nota_final, fecha_cierre, id_pedido_origen)
                    VALUES 
                        (:id_tarea, :titulo, :desc, :id_creador, :id_asig, :id_cat, :prio, :fecha_lim, :adj_ob, :fecha_crea, :estado, :nota, :fecha_cierre, :id_ped_origen)";
    $stmt_insert_tarea = $pdo_final->prepare($sql_insert_tarea);
    
    $sql_update_pedido = "UPDATE pedidos_trabajo SET id_tarea_generada = :id_tarea WHERE id_pedido = :id_pedido";
    $stmt_update_pedido = $pdo_final->prepare($sql_update_pedido);

    $default_area_id_para_pedido = 1;
    $count = 0;

    foreach ($tareas_viejas as $tarea_vieja) {
        $id_tarea_vieja = $tarea_vieja['id_tarea'];
        
        $stmt_get_user_name->execute([':id' => $tarea_vieja['id_creador']]);
        $nombre_creador = $stmt_get_user_name->fetchColumn() ?: 'Admin';
        
        $stmt_get_cat_name->execute([':id' => $tarea_vieja['id_categoria']]);
        $nombre_categoria = $stmt_get_cat_name->fetchColumn() ?: 'General';
        
        $prioridad_pedido_map = match($tarea_vieja['prioridad']) {
            'urgente' => 'urgente', 'alta' => 'importante', 'media' => 'rutina', 'baja' => 'rutina', default => 'rutina'
        };

        $numero_orden_generado = generar_nuevo_numero_orden($pdo_final);
        $id_nuevo_pedido = $id_tarea_vieja;
        
        $stmt_insert_pedido->execute([
            ':id_ped' => $id_nuevo_pedido,
            ':num_orden' => $numero_orden_generado,
            ':titulo_ped' => $tarea_vieja['titulo'],
            ':id_solic' => $map_usuarios[$tarea_vieja['id_creador']] ?? $tarea_vieja['id_creador'],
            ':id_aux' => $map_usuarios[$tarea_vieja['id_creador']] ?? $tarea_vieja['id_creador'],
            ':id_area' => $default_area_id_para_pedido,
            ':area_nombre' => $nombre_categoria,
            ':prio' => $prioridad_pedido_map,
            ':fecha_req' => $tarea_vieja['fecha_limite'],
            ':descrip' => $tarea_vieja['descripcion'],
            ':solic_real' => $nombre_creador,
            ':fecha_creacion' => $tarea_vieja['fecha_creacion']
        ]);

        $id_nueva_tarea = $id_tarea_vieja;
        $map_tareas[$id_tarea_vieja] = $id_nueva_tarea;
        
        $stmt_insert_tarea->execute([
            ':id_tarea' => $id_nueva_tarea,
            ':titulo' => $tarea_vieja['titulo'],
            ':desc' => $tarea_vieja['descripcion'],
            ':id_creador' => $map_usuarios[$tarea_vieja['id_creador']] ?? $tarea_vieja['id_creador'],
            ':id_asig' => $map_usuarios[$tarea_vieja['id_asignado']] ?? $tarea_vieja['id_asignado'],
            ':id_cat' => $map_categorias[$tarea_vieja['id_categoria']] ?? $tarea_vieja['id_categoria'],
            ':prio' => $tarea_vieja['prioridad'],
            ':fecha_lim' => $tarea_vieja['fecha_limite'],
            ':adj_ob' => $tarea_vieja['adjunto_obligatorio'],
            ':fecha_crea' => $tarea_vieja['fecha_creacion'],
            ':estado' => $tarea_vieja['estado'],
            ':nota' => $tarea_vieja['nota_final'] ?? null,
            ':fecha_cierre' => $tarea_vieja['fecha_cierre'] ?? null,
            ':id_ped_origen' => $id_nuevo_pedido
        ]);
        
        $stmt_update_pedido->execute([
            ':id_tarea' => $id_nueva_tarea,
            ':id_pedido' => $id_nuevo_pedido
        ]);

        $count++;
    }
    echo "<div class='log-success'>[PASO 5/7] {$count} tareas migradas y pedidos fantasma creados.</div>";
    ob_flush(); flush();
    
    // --- 6. MIGRAR ACTUALIZACIONES ---
    echo "<div class='log-header'>[PASO 6/7] Migrando actualizaciones...</div>";
    $stmt_v_act = $pdo_vieja->query("SELECT id_actualizacion, id_tarea, id_usuario, contenido, fecha_actualizacion FROM actualizaciones_tarea");
    $stmt_n_act = $pdo_final->prepare("INSERT INTO actualizaciones_tarea 
                                        (id_actualizacion, id_tarea, id_usuario, contenido, fecha_actualizacion, causo_reserva) 
                                      VALUES 
                                        (:id_act, :id_tarea_n, :id_user_n, :cont, :fecha, 0)"); // Hardcodeamos 0
    $count_act = 0;
    while ($row = $stmt_v_act->fetch()) {
        $id_tarea_nueva = $map_tareas[$row['id_tarea']] ?? null;
        $id_usuario_nuevo = $map_usuarios[$row['id_usuario']] ?? null;
        
        if ($id_tarea_nueva && $id_usuario_nuevo) {
            $stmt_n_act->execute([
                ':id_act' => $row['id_actualizacion'],
                ':id_tarea_n' => $id_tarea_nueva,
                ':id_user_n' => $id_usuario_nuevo,
                ':cont' => $row['contenido'],
                ':fecha' => $row['fecha_actualizacion']
            ]);
            $count_act++;
        } else {
             echo "<div class='log-warn'>- Omitiendo actualización #{$row['id_actualizacion']} (Tarea #{$row['id_tarea']} o Usuario #{$row['id_usuario']} no mapeado).</div>";
        }
    }
    echo "<div class='log-success'>- {$count_act} actualizaciones migradas.</div>";
    ob_flush(); flush();

    // --- 7. MIGRAR ADJUNTOS ---
    echo "<div class='log-header'>[PASO 7/7] Migrando adjuntos...</div>";
    $stmt_v_adj = $pdo_vieja->query("SELECT id_adjunto, id_tarea, nombre_archivo, ruta_archivo, fecha_subida, id_usuario_subida FROM adjuntos_tarea");
    
    $stmt_n_adj = $pdo_final->prepare("INSERT INTO adjuntos_tarea 
                                        (id_adjunto, id_tarea, id_actualizacion, tipo_adjunto, nombre_archivo, ruta_archivo, id_usuario_subida, fecha_subida,
                                         descripcion_compra, precio_total, numero_compra, estado_conciliacion) 
                                      VALUES 
                                        (:id_adj, :id_tarea_n, :id_act_n, :tipo, :nombre, :ruta, :id_user_n, :fecha,
                                         NULL, NULL, NULL, 'pendiente')");
    $count_adj = 0;
    while ($row = $stmt_v_adj->fetch()) {
        $id_tarea_nueva = $map_tareas[$row['id_tarea']] ?? null;
        $id_usuario_nuevo = $map_usuarios[$row['id_usuario_subida']] ?? null;
        
        // (Como la BD vieja no tiene id_actualizacion, TODOS los adjuntos se asignarán como 'inicial')
        $id_actualizacion_nueva = null; 
        $tipo_adjunto = 'inicial';

        if ($id_tarea_nueva && $id_usuario_nuevo) {
            $stmt_n_adj->execute([
                ':id_adj' => $row['id_adjunto'],
                ':id_tarea_n' => $id_tarea_nueva,
                ':id_act_n' => $id_actualizacion_nueva, // Será NULL
                ':tipo' => $tipo_adjunto, // Será 'inicial'
                ':nombre' => $row['nombre_archivo'],
                ':ruta' => $row['ruta_archivo'],
                ':id_user_n' => $id_usuario_nuevo,
                ':fecha' => $row['fecha_subida']
            ]);
            $count_adj++;
        } else {
             echo "<div class='log-warn'>- Omitiendo adjunto #{$row['id_adjunto']} (Tarea #{$row['id_tarea']} o Usuario #{$row['id_usuario_subida']} no mapeado).</div>";
        }
    }
    echo "<div class='log-success'>- {$count_adj} adjuntos migrados.</div>";
    ob_flush(); flush();

    // --- FINALIZAR ---
    $pdo_final->commit(); // <--- Ahora SÍ debería funcionar
    echo "<div class='log-header log-success'>[¡MIGRACIÓN COMPLETA!]</div>";
    echo "<p class='log-success fs-5'>Se han migrado {$count} tareas (y sus pedidos), {$count_act} actualizaciones y {$count_adj} adjuntos.</p>";
    echo "<p class='log-warn fs-4'>¡IMPORTANTE! Ahora debes actualizar tu archivo `conexion.php` para que apunte a la base de datos '{$db_final_config['dbname']}'.</p>";

} catch (Exception $e) {
    // Si algo falla, revertir todo
    if ($pdo_final->inTransaction()) {
        $pdo_final->rollBack();
    }
    echo "<div class='log-error'>[ERROR FATAL] La migración ha fallado y ha sido revertida.</div>";
    echo "<pre class='log-error'>" . $e->getMessage() . "</pre>";
    echo "<pre>" . $e->getTraceAsString() . "</pre>";
    
    // *** INICIO CORRECCIÓN (v4): Añadir exit; ***
    echo "</div></div></body></html>";
    exit;
    // *** FIN CORRECCIÓN (v4) ***
}

echo "</div></div></body></html>";
?>