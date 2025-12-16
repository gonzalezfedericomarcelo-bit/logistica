<?php
// Archivo: externo_firmar.php
session_start();
include 'conexion.php';

$token = $_GET['t'] ?? '';

// 1. Validar Token
$stmt = $pdo->prepare("SELECT * FROM verificaciones_externas WHERE token_acceso = ? AND estado = 'verificado'");
$stmt->execute([$token]);
$solicitud = $stmt->fetch();

if (!$solicitud) die("<h1>Error: Enlace inválido, expirado o ya utilizado.</h1>");

$destino = $solicitud['destino_objetivo']; // Ej: "Centro Medico"
$rol = $solicitud['rol_firmante'];

// Títulos para mostrar
$titulo_rol = ($rol == 'responsable') ? "Responsable a Cargo" : "Jefe de Servicio (Aval)";

// 2. Procesar Firma
$mensaje = "";
$tipo_alerta = "";

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $nombre = strtoupper(trim($_POST['nombre_real']));
    $firma_base64 = $_POST['firma_base64'];
    
    if ($nombre && $firma_base64) {
        // A. Guardar Imagen Físicamente
        if (!file_exists('uploads/firmas')) mkdir('uploads/firmas', 0777, true);
        
        $img = base64_decode(str_replace(' ', '+', str_replace('data:image/png;base64,', '', $firma_base64)));
        $nombre_archivo = 'ext_' . time() . '_' . uniqid() . '.png';
        $path = 'uploads/firmas/' . $nombre_archivo;
        
        if(file_put_contents($path, $img)) {
            
            // B. Actualizar Base de Datos (EL PUNTO CRÍTICO)
            $col_firma = ($rol == 'responsable') ? 'firma_responsable' : 'firma_jefe';
            $col_nombre = ($rol == 'responsable') ? 'nombre_responsable' : 'nombre_jefe_servicio';
            
            // Usamos TRIM para evitar errores por espacios vacíos
            $sql = "UPDATE inventario_cargos 
                    SET $col_firma = ?, $col_nombre = ? 
                    WHERE TRIM(servicio_ubicacion) = TRIM(?)";
                    
            $upd = $pdo->prepare($sql);
            $upd->execute([$path, $nombre, $destino]);
            
            $cant_actualizada = $upd->rowCount(); // ¿A cuántas filas le pegó?
            
            if ($cant_actualizada > 0) {
                // Éxito Real
                $pdo->prepare("UPDATE verificaciones_externas SET estado='firmado', nombre_usuario=? WHERE id_verificacion=?")->execute([$nombre, $solicitud['id_verificacion']]);
                
                $mensaje = "¡ÉXITO REAL! Se actualizaron $cant_actualizada bienes en la base de datos.<br>La firma ya debería aparecer en el PDF.";
                $tipo_alerta = "success";
            } else {
                // Fracaso Silencioso detectado
                $mensaje = "⚠️ ALERTA: La imagen se guardó, pero <strong>NO SE ENCONTRARON BIENES</strong> para el destino: '<strong>$destino</strong>'.<br>
                            Posibles causas:<br>
                            1. El nombre en 'destinos_internos' es diferente al de 'inventario_cargos'.<br>
                            2. Hay un acento o espacio diferente (Ej: 'Medíco' vs 'Medico').";
                $tipo_alerta = "danger";
            }
            
        } else {
            $mensaje = "Error: No se pudo guardar el archivo de imagen en la carpeta uploads/firmas. Verifique permisos.";
            $tipo_alerta = "danger";
        }
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Firmar</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/signature_pad@4.0.0/dist/signature_pad.umd.min.js"></script>
    <style> .sig-pad { border: 2px dashed #ccc; height: 250px; background: #fff; } canvas { width: 100%; height: 100%; } </style>
</head>
<body class="bg-light">
    <div class="container mt-4">
        
        <?php if($mensaje): ?>
            <div class="alert alert-<?php echo $tipo_alerta; ?> text-center p-4">
                <h3><?php echo $mensaje; ?></h3>
                <?php if($tipo_alerta == 'danger'): ?>
                    <p>Avise al administrador para corregir el nombre del destino.</p>
                <?php endif; ?>
            </div>
        <?php endif; ?>

        <?php if($tipo_alerta !== 'success'): ?>
        <div class="card shadow">
            <div class="card-header bg-dark text-white text-center">
                <h4>Firmando: <?php echo htmlspecialchars($destino); ?></h4>
                <span class="badge bg-warning text-dark"><?php echo $titulo_rol; ?></span>
            </div>
            <div class="card-body p-4">
                <form method="POST" id="formFirma">
                    <div class="mb-3">
                        <label>Nombre y Apellido</label>
                        <input type="text" name="nombre_real" class="form-control" required>
                    </div>
                    <label>Su Firma</label>
                    <div class="sig-pad mb-3"><canvas id="canvas"></canvas></div>
                    <input type="hidden" name="firma_base64" id="firma64">
                    <button type="button" class="btn btn-outline-secondary btn-sm" id="limpiar">Borrar</button>
                    <button type="submit" class="btn btn-success w-100 fw-bold mt-3">GUARDAR FIRMA</button>
                </form>
            </div>
        </div>
        
        <script>
            var c = document.getElementById('canvas');
            var pad = new SignaturePad(c);
            function resize() { 
                var r = Math.max(window.devicePixelRatio || 1, 1);
                c.width = c.offsetWidth * r; c.height = c.offsetHeight * r;
                c.getContext("2d").scale(r, r); pad.clear();
            }
            window.addEventListener("resize", resize); resize();
            document.getElementById('limpiar').onclick = function() { pad.clear(); };
            document.getElementById('formFirma').onsubmit = function(e) {
                if(pad.isEmpty()) { alert("Firme primero."); e.preventDefault(); }
                else { document.getElementById('firma64').value = pad.toDataURL(); }
            };
        </script>
        <?php endif; ?>
    </div>
</body>
</html>