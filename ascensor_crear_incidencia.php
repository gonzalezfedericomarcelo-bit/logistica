<?php
// Archivo: ascensor_crear_incidencia.php
session_start();
require_once 'conexion.php';
require_once 'funciones_permisos.php';

// Verificar permisos
if (!tiene_permiso('crear_incidencia_ascensor', $pdo)) {
    header("Location: mantenimiento_ascensores.php");
    exit;
}

$error = '';

// --- PROCESAR FORMULARIO ---
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    
    $id_empresa = $_POST['id_empresa'];
    $id_ascensor = $_POST['id_ascensor'];
    $prioridad = $_POST['prioridad'];
    $descripcion = $_POST['descripcion'];
    $id_usuario = $_SESSION['usuario_id'];
    
    // 1. Subir Adjunto
    $ruta_adjunto = null;
    if (isset($_FILES['adjunto']) && $_FILES['adjunto']['error'] === UPLOAD_ERR_OK) {
        $ext = pathinfo($_FILES['adjunto']['name'], PATHINFO_EXTENSION);
        $nombre = 'reclamo_' . time() . '.' . $ext;
        if (!is_dir('uploads/ascensores')) mkdir('uploads/ascensores', 0777, true);
        if (move_uploaded_file($_FILES['adjunto']['tmp_name'], 'uploads/ascensores/' . $nombre)) {
            $ruta_adjunto = 'uploads/ascensores/' . $nombre;
        }
    }

    // 2. Guardar en BD
    try {
        $sql = "INSERT INTO ascensor_incidencias 
                (id_ascensor, id_empresa, id_usuario_reporta, descripcion_problema, prioridad, archivo_reclamo, estado, fecha_reporte) 
                VALUES (?, ?, ?, ?, ?, ?, 'reclamo_enviado', NOW())";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$id_ascensor, $id_empresa, $id_usuario, $descripcion, $prioridad, $ruta_adjunto]);
        $id_reclamo = $pdo->lastInsertId();

        // 3. ENVIAR CORREOS
        
        // A) Obtener datos para el mail
        $stmt_info = $pdo->prepare("SELECT e.nombre as nom_empresa, e.email_contacto, a.nombre as nom_ascensor, a.ubicacion 
                                    FROM empresas_mantenimiento e 
                                    JOIN ascensores a ON a.id_ascensor = ? 
                                    WHERE e.id_empresa = ?");
        $stmt_info->execute([$id_ascensor, $id_empresa]);
        $info = $stmt_info->fetch(PDO::FETCH_ASSOC);

        // B) Obtener TU email para la copia
        $stmt_user = $pdo->prepare("SELECT email, nombre_completo FROM usuarios WHERE id_usuario = ?");
        $stmt_user->execute([$id_usuario]);
        $user_data = $stmt_user->fetch(PDO::FETCH_ASSOC);
        $tu_email = $user_data['email'];

        // C) Enviar si hay datos
        if ($info && !empty($info['email_contacto'])) {
            $asunto = "RECLAMO URGENTE - Orden #$id_reclamo - " . $info['nom_ascensor'];
            
            $mensaje  = "Estimados " . $info['nom_empresa'] . ":\n\n";
            $mensaje .= "Solicitud de servicio técnico:\n";
            $mensaje .= "--------------------------------------\n";
            $mensaje .= "EQUIPO: " . $info['nom_ascensor'] . " (" . $info['ubicacion'] . ")\n";
            $mensaje .= "PRIORIDAD: " . strtoupper($prioridad) . "\n";
            $mensaje .= "SOLICITANTE: " . $user_data['nombre_completo'] . "\n";
            $mensaje .= "--------------------------------------\n\n";
            $mensaje .= "FALLA: " . $descripcion . "\n";
            
            // Cabecera simple (Funciona en Hostinger)
            $headers = "From: sistema@logistica.com";

            // ENVÍO 1: A LA EMPRESA
            @mail($info['email_contacto'], $asunto, $mensaje, $headers);

            // ENVÍO 2: A TU CORREO (COPIA)
            if (!empty($tu_email)) {
                @mail($tu_email, "COPIA: " . $asunto, $mensaje, $headers);
            }
        }

        header("Location: mantenimiento_ascensores.php?msg=creado");
        exit;

    } catch (Exception $e) {
        $error = "Error: " . $e->getMessage();
    }
}

// --- DATOS PARA EL FORMULARIO ---
$empresas = $pdo->query("SELECT * FROM empresas_mantenimiento WHERE activo=1 ORDER BY nombre")->fetchAll(PDO::FETCH_ASSOC);
$ascensores = $pdo->query("SELECT id_ascensor, nombre, ubicacion, id_empresa FROM ascensores WHERE estado!='inactivo'")->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <?php include 'head.php'; ?>
    <title>Nuevo Reclamo</title>
</head>
<body>
    <?php include 'navbar.php'; ?>
    
    <div class="container mt-5">
        <div class="row justify-content-center">
            <div class="col-md-8">
                <div class="card shadow">
                    <div class="card-header bg-danger text-white">
                        <h4 class="mb-0">Nuevo Reclamo de Ascensor</h4>
                    </div>
                    <div class="card-body">
                        <?php if($error): ?><div class="alert alert-danger"><?php echo $error; ?></div><?php endif; ?>

                        <form method="POST" enctype="multipart/form-data">
                            <div class="mb-3">
                                <label class="fw-bold">1. Empresa</label>
                                <select name="id_empresa" id="selectEmpresa" class="form-select" required onchange="filtrar()">
                                    <option value="">-- Seleccionar --</option>
                                    <?php foreach($empresas as $e): ?>
                                        <option value="<?php echo $e['id_empresa']; ?>"><?php echo htmlspecialchars($e['nombre']); ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>

                            <div class="mb-3">
                                <label class="fw-bold">2. Equipo</label>
                                <select name="id_ascensor" id="selectAscensor" class="form-select" required disabled>
                                    <option value="">-- Seleccione Empresa Primero --</option>
                                </select>
                            </div>

                            <div class="mb-3">
                                <label class="fw-bold">3. Prioridad</label>
                                <select name="prioridad" class="form-select">
                                    <option value="media">Media</option>
                                    <option value="alta">Alta</option>
                                    <option value="emergencia">Emergencia</option>
                                    <option value="baja">Baja</option>
                                </select>
                            </div>
                            <div class="mb-3">
                                <label class="fw-bold">4. Detalle</label>
                                <textarea name="descripcion" class="form-control" rows="4" required></textarea>
                            </div>
                            <div class="mb-3">
                                <label>Adjunto (Opcional)</label>
                                <input type="file" name="adjunto" class="form-control">
                            </div>

                            <div class="text-end">
                                <a href="mantenimiento_ascensores.php" class="btn btn-secondary">Cancelar</a>
                                <button type="submit" class="btn btn-danger">Enviar Reclamo</button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script>
        const equipos = <?php echo json_encode($ascensores); ?>;
        function filtrar() {
            const idEmp = document.getElementById('selectEmpresa').value;
            const selAsc = document.getElementById('selectAscensor');
            selAsc.innerHTML = '<option value="">-- Seleccione Equipo --</option>';
            
            if(idEmp === "") { selAsc.disabled = true; return; }

            const filtrados = equipos.filter(eq => eq.id_empresa == idEmp);
            if(filtrados.length > 0) {
                selAsc.disabled = false;
                filtrados.forEach(eq => {
                    const opt = document.createElement('option');
                    opt.value = eq.id_ascensor;
                    opt.textContent = eq.nombre + " (" + eq.ubicacion + ")";
                    selAsc.appendChild(opt);
                });
            } else {
                selAsc.disabled = true;
                selAsc.innerHTML = '<option>No hay equipos para esta empresa</option>';
            }
        }
    </script>
    <?php include 'footer.php'; ?>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>