<?php
// Archivo: inventario_guardar.php
// MODIFICADO: Captura automática de firma del perfil (Logística)
session_start();
include 'conexion.php';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    
    // Carpeta de firmas
    if (!file_exists('uploads/firmas')) {
        mkdir('uploads/firmas', 0777, true);
    }

    // 1. Guardar firmas del formulario (Responsable y Jefe)
    function guardarFirmaFormulario($base64, $prefijo) {
        $base64 = str_replace('data:image/png;base64,', '', $base64);
        $base64 = str_replace(' ', '+', $base64);
        $data = base64_decode($base64);
        $nombre = 'uploads/firmas/' . $prefijo . '_' . time() . '_' . uniqid() . '.png';
        file_put_contents($nombre, $data);
        return $nombre;
    }

    $ruta_resp = guardarFirmaFormulario($_POST['base64_responsable'], 'resp');
    $ruta_jefe = guardarFirmaFormulario($_POST['base64_jefe'], 'jefe');

    // 2. Obtener firma del Relevador (Usuario Logueado) desde su perfil
    $id_relevador = $_SESSION['usuario_id'];
    $ruta_rel = null;

    try {
        // Buscar la firma en la tabla usuarios
        $stmt_user = $pdo->prepare("SELECT firma_imagen_path FROM usuarios WHERE id_usuario = ?");
        $stmt_user->execute([$id_relevador]);
        $firma_perfil = $stmt_user->fetchColumn();

        if ($firma_perfil) {
            // Ruta original del perfil
            $origen = 'uploads/firmas/' . $firma_perfil;
            
            if (file_exists($origen)) {
                // OPCIÓN A: Usar la misma referencia (ahorra espacio, pero si cambia el perfil cambia aquí)
                // $ruta_rel = $firma_perfil; 

                // OPCIÓN B (RECOMENDADA): Crear una copia "snapshot" para este inventario
                // Así si el usuario cambia su firma mañana, este documento histórico no cambia.
                $ext = pathinfo($firma_perfil, PATHINFO_EXTENSION);
                $nuevo_nombre_rel = 'rel_' . time() . '_' . uniqid() . '.' . $ext;
                $destino = 'uploads/firmas/' . $nuevo_nombre_rel;
                
                if (copy($origen, $destino)) {
                    $ruta_rel = $destino; // Guardamos la ruta de la copia
                } else {
                    // Si falla copia, usamos la original como fallback (mejor que nada)
                    $ruta_rel = $origen; 
                }
            }
        }
    } catch (Exception $e) {
        error_log("Error obteniendo firma perfil: " . $e->getMessage());
    }

    // 3. Guardar Datos en BD
    try {
        $sql = "INSERT INTO inventario_cargos 
                (id_usuario_relevador, elemento, codigo_inventario, servicio_ubicacion, observaciones, 
                 nombre_responsable, nombre_jefe_servicio, firma_responsable, firma_relevador, firma_jefe, fecha_creacion) 
                VALUES (:id_rel, :elem, :cod, :serv, :obs, :nom_resp, :nom_jefe, :f_resp, :f_rel, :f_jefe, NOW())";
        
        $stmt = $pdo->prepare($sql);
        $stmt->execute([
            ':id_rel' => $id_relevador,
            ':elem' => $_POST['elemento'],
            ':cod' => $_POST['codigo_inventario'],
            ':serv' => $_POST['servicio_ubicacion'], // Viene del select dinámico (nombre del área)
            ':obs' => $_POST['observaciones'],
            ':nom_resp' => $_POST['nombre_responsable'],
            ':nom_jefe' => $_POST['nombre_jefe_servicio'],
            ':f_resp' => $ruta_resp,
            ':f_rel' => $ruta_rel, // Puede ser null si no tiene perfil, pero el PDF lo maneja
            ':f_jefe' => $ruta_jefe
        ]);

        $id_generado = $pdo->lastInsertId();
        
        // Redirigir al PDF
        header("Location: inventario_pdf.php?id=" . $id_generado);
        exit;

    } catch (PDOException $e) {
        die("Error al guardar en base de datos: " . $e->getMessage());
    }
}
?>