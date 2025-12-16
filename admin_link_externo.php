<?php
// Archivo: admin_link_externo.php
// MODIFICADO: Generación de links diferenciados (Responsable y Jefe) con selección de Área.
session_start();
include 'conexion.php';
include 'funciones_permisos.php';

if (!isset($_SESSION['usuario_id'])) { header("Location: dashboard.php"); exit(); }

$mensaje = "";
$links_generados = [];

// 1. Obtener Destinos para el primer select
$destinos = $pdo->query("SELECT id_destino, nombre FROM destinos_internos ORDER BY nombre ASC")->fetchAll(PDO::FETCH_ASSOC);

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    // Recuperar nombres para armar el objetivo
    $id_destino = $_POST['id_destino'];
    $nombre_destino = "";
    
    // Buscar nombre del destino
    foreach($destinos as $d) { if($d['id_destino'] == $id_destino) $nombre_destino = $d['nombre']; }
    
    // Determinar el "Objetivo" (La ubicación exacta que se guardó en inventario)
    // Si eligió área, usamos el nombre del área. Si no, el del destino.
    $objetivo_firma = $nombre_destino;
    
    if (!empty($_POST['nombre_area'])) {
        $objetivo_firma = $_POST['nombre_area']; // En inventario guardamos el nombre del área
    }
    
    // Función helper para generar link
    function generarLink($pdo, $objetivo, $rol) {
        $token = bin2hex(random_bytes(16));
        $stmt = $pdo->prepare("INSERT INTO verificaciones_externas (token_acceso, destino_objetivo, rol_firmante, estado) VALUES (?, ?, ?, 'pendiente')");
        if ($stmt->execute([$token, $objetivo, $rol])) {
            $protocol = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? "https" : "http");
            return $protocol . "://$_SERVER[HTTP_HOST]" . dirname($_SERVER['PHP_SELF']) . "/externo_login.php?t=" . $token;
        }
        return false;
    }

    // Generar LOS DOS links
    $link_resp = generarLink($pdo, $objetivo_firma, 'responsable');
    $link_jefe = generarLink($pdo, $objetivo_firma, 'jefe');
    
    if ($link_resp && $link_jefe) {
        $links_generados = [
            'ubicacion' => $objetivo_firma,
            'responsable' => $link_resp,
            'jefe' => $link_jefe
        ];
    } else {
        $mensaje = "Error al generar los enlaces en la base de datos.";
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Generar Links de Firma</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />
    <link href="https://cdn.jsdelivr.net/npm/select2-bootstrap-5-theme@1.3.0/dist/select2-bootstrap-5-theme.min.css" rel="stylesheet" />
</head>
<body class="bg-light">
    <?php include 'navbar.php'; ?>
    
    <div class="container mt-5">
        <div class="row justify-content-center">
            <div class="col-md-8">
                <div class="card shadow border-0">
                    <div class="card-header bg-dark text-white">
                        <h5 class="mb-0"><i class="fas fa-qrcode me-2"></i> Generador de Firma Remota</h5>
                    </div>
                    <div class="card-body p-4">
                        <div class="alert alert-info border-0 shadow-sm mb-4">
                            <small><i class="fas fa-info-circle me-2"></i> Seleccione la ubicación exacta. El sistema generará dos enlaces únicos: uno para el <strong>Responsable</strong> del cargo y otro para el <strong>Jefe de Servicio</strong> que avala.</small>
                        </div>
                        
                        <form method="POST">
                            <div class="row g-3">
                                <div class="col-md-6">
                                    <label class="form-label fw-bold">1. Centro / Destino</label>
                                    <select name="id_destino" id="select_destino" class="form-select" required>
                                        <option value="">-- Seleccione --</option>
                                        <?php foreach($destinos as $d): ?>
                                            <option value="<?php echo $d['id_destino']; ?>"><?php echo htmlspecialchars($d['nombre']); ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                
                                <div class="col-md-6">
                                    <label class="form-label fw-bold">2. Área / Servicio</label>
                                    <select name="nombre_area" id="select_area" class="form-select" disabled>
                                        <option value="">-- Seleccione Destino Primero --</option>
                                    </select>
                                    <div class="form-text small">Si no selecciona área, se usará el nombre del Destino.</div>
                                </div>
                            </div>
                            
                            <div class="d-grid mt-4">
                                <button type="submit" class="btn btn-primary fw-bold py-2">
                                    <i class="fas fa-link me-2"></i> GENERAR ENLACES DE FIRMA
                                </button>
                            </div>
                        </form>

                        <?php if (!empty($links_generados)): ?>
                            <hr class="my-4">
                            <div class="bg-white p-3 rounded border border-success">
                                <h5 class="text-success fw-bold mb-3"><i class="fas fa-check-circle me-2"></i> Enlaces para: <?php echo htmlspecialchars($links_generados['ubicacion']); ?></h5>
                                
                                <div class="mb-4">
                                    <label class="fw-bold text-primary mb-1">Para el RESPONSABLE (Quien tiene el bien):</label>
                                    <div class="input-group">
                                        <input type="text" class="form-control" value="<?php echo $links_generados['responsable']; ?>" id="linkResp" readonly>
                                        <button class="btn btn-outline-primary" onclick="copiar('linkResp')"><i class="fas fa-copy"></i> Copiar</button>
                                        <a href="https://wa.me/?text=Hola,%20necesito%20tu%20firma%20para%20el%20inventario%20de%20Responsable:%20<?php echo urlencode($links_generados['responsable']); ?>" target="_blank" class="btn btn-success"><i class="fab fa-whatsapp"></i> Enviar</a>
                                    </div>
                                </div>

                                <div>
                                    <label class="fw-bold text-dark mb-1">Para el JEFE DE SERVICIO (Quien avala):</label>
                                    <div class="input-group">
                                        <input type="text" class="form-control" value="<?php echo $links_generados['jefe']; ?>" id="linkJefe" readonly>
                                        <button class="btn btn-outline-dark" onclick="copiar('linkJefe')"><i class="fas fa-copy"></i> Copiar</button>
                                        <a href="https://wa.me/?text=Hola,%20necesito%20tu%20firma%20como%20Jefe%20para%20el%20inventario:%20<?php echo urlencode($links_generados['jefe']); ?>" target="_blank" class="btn btn-success"><i class="fab fa-whatsapp"></i> Enviar</a>
                                    </div>
                                </div>
                            </div>
                        <?php endif; ?>
                        
                        <?php if($mensaje): ?><div class="alert alert-danger mt-3"><?php echo $mensaje; ?></div><?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://code.jquery.com/jquery-3.7.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>
    
    <script>
        $(document).ready(function() {
            $('#select_destino').select2({ theme: 'bootstrap-5' });
            // El select de área no lo inicializamos como select2 aún para manipularlo fácil, o sí:
            $('#select_area').select2({ theme: 'bootstrap-5' });

            // Lógica AJAX igual a inventario_nuevo.php
            $('#select_destino').on('change', function() {
                var idDestino = $(this).val();
                var $selectArea = $('#select_area');
                
                $selectArea.empty().append('<option value="">Cargando...</option>').prop('disabled', true);

                if (idDestino) {
                    $.ajax({
                        url: 'ajax_obtener_areas.php', // Reutilizamos tu archivo existente
                        type: 'GET',
                        data: { id_destino: idDestino },
                        dataType: 'json',
                        success: function(areas) {
                            $selectArea.empty().append('<option value="">-- Seleccione (Opcional) --</option>');
                            if (areas.length > 0) {
                                $.each(areas, function(i, area) {
                                    // Usamos el NOMBRE como value porque así se guarda en inventario_cargos
                                    $selectArea.append('<option value="' + area.nombre + '">' + area.nombre + '</option>');
                                });
                                $selectArea.prop('disabled', false);
                            } else {
                                $selectArea.append('<option value="">Este destino no tiene áreas registradas</option>');
                            }
                        }
                    });
                } else {
                    $selectArea.empty().append('<option value="">-- Seleccione Destino Primero --</option>');
                }
            });
        });

        function copiar(id) {
            var copyText = document.getElementById(id);
            copyText.select();
            document.execCommand("copy");
            alert("Enlace copiado al portapapeles");
        }
    </script>
    <?php include 'footer.php'; ?>
</body>
</html>