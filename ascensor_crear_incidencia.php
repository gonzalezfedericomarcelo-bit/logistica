<?php
// Archivo: ascensor_crear_incidencia.php
session_start();
include 'conexion.php';
include 'funciones_permisos.php';

// 1. Verificar sesión
if (!isset($_SESSION['usuario_id']) || !tiene_permiso('crear_incidencia_ascensor', $pdo)) {
    header("Location: dashboard.php");
    exit();
}

$mensaje = '';
$tipo_alerta = '';

// 2. Cargar ascensores
try {
    $stmt_asc = $pdo->query("SELECT id_ascensor, nombre, ubicacion FROM ascensores WHERE estado = 'activo' ORDER BY nombre");
    $ascensores = $stmt_asc->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    die("Error DB: " . $e->getMessage());
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!empty($_POST['website_check'])) die(); 
    
    if (!isset($_POST['soy_humano'])) {
        $mensaje = "Confirma que no eres un robot.";
        $tipo_alerta = "warning";
    } else {
        $id_ascensor = $_POST['id_ascensor'];
        $titulo = trim($_POST['titulo']);
        $descripcion = trim($_POST['descripcion']);
        $id_usuario = $_SESSION['usuario_id'];

        if (empty($id_ascensor) || empty($titulo) || empty($descripcion)) {
            $mensaje = "Faltan datos obligatorios.";
            $tipo_alerta = "danger";
        } else {
            try {
                // A) DATOS
                $sql_datos = "
                    SELECT 
                        a.id_empresa, a.nombre as asc_nombre, a.ubicacion,
                        e.nombre as emp_nombre, e.email_contacto as emp_email,
                        u.email as user_email, u.nombre_completo as user_nombre
                    FROM ascensores a 
                    JOIN empresas_mantenimiento e ON a.id_empresa = e.id_empresa
                    JOIN usuarios u ON u.id_usuario = :id_user
                    WHERE a.id_ascensor = :id_asc
                ";
                $stmt_datos = $pdo->prepare($sql_datos);
                $stmt_datos->execute([':id_asc' => $id_ascensor, ':id_user' => $id_usuario]);
                $datos = $stmt_datos->fetch(PDO::FETCH_ASSOC);

                if (!$datos) throw new Exception("Error datos ascensor.");

                // B) INSERTAR
                $problema_completo = $titulo . " - " . $descripcion;
                $sql_insert = "INSERT INTO ascensor_incidencias (id_ascensor, id_empresa, id_usuario_reporta, descripcion_problema, prioridad, estado, fecha_reporte) VALUES (?, ?, ?, ?, 'media', 'reportado', NOW())";
                $stmt = $pdo->prepare($sql_insert);
                $stmt->execute([$id_ascensor, $datos['id_empresa'], $id_usuario, $problema_completo]);
                
                $mensaje = "Reclamo guardado correctamente.";
                $tipo_alerta = "success";

                // C) ENVIAR CORREO (SIN PARAMETRO -f QUE BLOQUEA)
                if (!empty($datos['emp_email'])) {
                    $para = $datos['emp_email'];
                    $asunto = "RECLAMO: " . $datos['asc_nombre'];
                    
                    // CUENTA REAL OBLIGATORIA
                    $remitente_real = "ascensores_actis@federicogonzalez.net"; 
                    
                    // Reply-To dinámico: Si el usuario tiene mail, se usa. Si no, usa el del sistema.
                    $reply_to = (!empty($datos['user_email'])) ? $datos['user_email'] : $remitente_real;

                    $cuerpo  = "Estimados " . $datos['emp_nombre'] . ",\n\n";
                    $cuerpo .= "Solicitud de asistencia:\n";
                    $cuerpo .= "Equipo: " . $datos['asc_nombre'] . " (" . $datos['ubicacion'] . ")\n";
                    $cuerpo .= "Falla: " . $titulo . "\n";
                    $cuerpo .= "Detalle: " . $descripcion . "\n";
                    $cuerpo .= "Solicita: " . $datos['user_nombre'];

                    // CABECERAS LIMPIAS (Sin -f)
                    $headers = "From: " . $remitente_real . "\r\n" .
                               "Reply-To: " . $reply_to . "\r\n" .
                               "X-Mailer: PHP/" . phpversion();

                    // Envío estándar. Si el SPF está bien, esto debe salir.
                    if(mail($para, $asunto, $cuerpo, $headers)) {
                        $mensaje .= " Y enviado a la empresa.";
                    } else {
                        // Si falla aquí, logueamos el error técnico real.
                        $error = error_get_last()['message'] ?? 'Desconocido';
                        $mensaje .= " (Error envío: $error)";
                    }
                }

            } catch (Exception $e) {
                $mensaje = "Error: " . $e->getMessage();
                $tipo_alerta = "danger";
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <?php include 'head.php'; ?>
    <title>Reportar Falla</title>
    <style> .website-check-field { display: none; } </style>
</head>
<body style="background-color: #f8f9fa;">
    <?php include 'navbar.php'; ?>
    <div class="container mt-5">
        <div class="row justify-content-center">
            <div class="col-md-6">
                <div class="card shadow">
                    <div class="card-header bg-danger text-white"><h4>Reportar Falla</h4></div>
                    <div class="card-body">
                        <?php if($mensaje): ?>
                            <div class="alert alert-<?php echo $tipo_alerta; ?>"><?php echo $mensaje; ?></div>
                        <?php endif; ?>
                        
                        <form method="POST">
                            <input type="text" name="website_check" class="website-check-field">
                            
                            <div class="mb-3">
                                <label>Ascensor</label>
                                <select name="id_ascensor" class="form-select" required>
                                    <option value="">Seleccionar...</option>
                                    <?php foreach($ascensores as $a): ?>
                                        <option value="<?php echo $a['id_ascensor']; ?>">
                                            <?php echo htmlspecialchars($a['nombre'] . ' - ' . $a['ubicacion']); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="mb-3">
                                <label>Título</label>
                                <input type="text" name="titulo" class="form-control" required>
                            </div>
                            <div class="mb-3">
                                <label>Descripción</label>
                                <textarea name="descripcion" class="form-control" rows="3" required></textarea>
                            </div>
                            <div class="mb-3 form-check">
                                <input type="checkbox" class="form-check-input" name="soy_humano" required id="chkBot">
                                <label class="form-check-label" for="chkBot">No soy un robot</label>
                            </div>
                            <button type="submit" class="btn btn-danger w-100">Enviar</button>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <?php include 'footer.php'; ?>
</body>
</html>