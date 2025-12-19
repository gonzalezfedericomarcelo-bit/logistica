<?php
// Archivo: inventario_guardar.php (CORREGIDO: MANUAL SI GUARDA FECHAS)
session_start();
include 'conexion.php';
date_default_timezone_set('America/Argentina/Buenos_Aires');

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $id_usuario = $_SESSION['usuario_id'];
    $elemento = $_POST['elemento'];
    $ubicacion = $_POST['servicio_ubicacion'];
    $responsable = $_POST['nombre_responsable'];
    $jefe = $_POST['nombre_jefe_servicio'];
    $destino_principal = $_POST['destino_principal'] ?? '';
    
    // Variables para matafuegos (se llenan si se detectan en los dinámicos)
    $mat_fecha_carga = null;
    $mat_fecha_ph = null;
    $mat_numero_grabado = null;
    $mat_capacidad = null;
    $mat_clase_id = null;
    $mat_tipo_carga_id = null;
    $fecha_fabricacion = null;
    $vida_util = 20;

    // INSERTAR CARGO INICIAL
    $stmt = $pdo->prepare("INSERT INTO inventario_cargos (id_usuario_relevador, id_estado_fk, elemento, servicio_ubicacion, destino_principal, nombre_responsable, nombre_jefe_servicio, fecha_creacion) VALUES (?, 1, ?, ?, ?, ?, ?, NOW())");
    $stmt->execute([$id_usuario, $elemento, $ubicacion, $destino_principal, $responsable, $jefe]);
    $id_cargo = $pdo->lastInsertId();

    // PROCESAR DINÁMICOS Y DETECTAR FECHAS
    if (isset($_POST['dinamico']) && is_array($_POST['dinamico'])) {
        foreach ($_POST['dinamico'] as $id_campo => $valor) {
            if (!empty($valor)) {
                // Guardar valor dinámico
                $pdo->prepare("INSERT INTO inventario_valores_dinamicos (id_cargo, id_campo, valor) VALUES (?, ?, ?)")
                    ->execute([$id_cargo, $id_campo, $valor]);

                // Averiguar qué campo es (Etiqueta) para copiarlo a la columna oficial
                $stmtC = $pdo->prepare("SELECT etiqueta FROM inventario_campos_dinamicos WHERE id_campo = ?");
                $stmtC->execute([$id_campo]);
                $etiqueta = mb_strtoupper(trim($stmtC->fetchColumn()), 'UTF-8');

                // MAPEO MANUAL -> COLUMNA OFICIAL
                if (strpos($etiqueta, 'ULTIMA CARGA') !== false || strpos($etiqueta, 'VENCIMIENTO CARGA') !== false) {
                    $mat_fecha_carga = $valor;
                }
                if (strpos($etiqueta, 'ULTIMA PH') !== false || strpos($etiqueta, 'ULTIMA P.H.') !== false || strpos($etiqueta, 'VENCIMIENTO PH') !== false) {
                    $mat_fecha_ph = $valor;
                }
                if (strpos($etiqueta, 'GRABADO') !== false || strpos($etiqueta, 'SERIE') !== false) {
                    $mat_numero_grabado = $valor;
                }
                if (strpos($etiqueta, 'FABRICACION') !== false) {
                    $fecha_fabricacion = $valor;
                }
                if (strpos($etiqueta, 'CAPACIDAD') !== false) {
                    $mat_capacidad = $valor;
                    $mat_tipo_carga_id = 1; // Asumimos matafuego si pone capacidad
                }
            }
        }
        
        // Si detectamos fechas, actualizamos el registro principal
        if ($mat_fecha_carga || $mat_fecha_ph || $mat_numero_grabado || $fecha_fabricacion) {
            $sqlUpd = "UPDATE inventario_cargos SET 
                        mat_fecha_carga = ?, 
                        mat_fecha_ph = ?, 
                        mat_numero_grabado = ?, 
                        fecha_fabricacion = ?,
                        mat_capacidad = ?,
                        mat_tipo_carga_id = IFNULL(?, mat_tipo_carga_id)
                       WHERE id_cargo = ?";
            $pdo->prepare($sqlUpd)->execute([$mat_fecha_carga, $mat_fecha_ph, $mat_numero_grabado, $fecha_fabricacion, $mat_capacidad, $mat_tipo_carga_id, $id_cargo]);
        }
    }

    header("Location: inventario_lista.php?msg=guardado_ok");
    exit();
}
?>