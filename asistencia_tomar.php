<?php
// Archivo: asistencia_tomar.php (VERSIÓN AVANZADA: TARDE / COMISIÓN)
session_start();
include 'conexion.php';
include_once 'funciones_permisos.php';

if (!isset($_SESSION['usuario_id']) || !tiene_permiso('tomar_asistencia', $pdo)) {
    header("Location: dashboard.php");
    exit();
}

$nombre_usuario_actual = $_SESSION['usuario_nombre'] ?? '';
$es_jefe_supremo = (stripos($nombre_usuario_actual, 'Cañete') !== false);
$personal_ordenado = [];
$mensaje_error = '';

try {
    $sql = "SELECT id_usuario, nombre_completo, grado, rol FROM usuarios WHERE activo = 1 
            AND nombre_completo NOT LIKE '%Alejandro Batista%'
            AND nombre_completo NOT LIKE '%Juan Pablo Hernandez%'
            AND nombre_completo NOT LIKE '%Juan Pablo Hernández%' 
            ORDER BY nombre_completo ASC";
    $todos_usuarios = $pdo->query($sql)->fetchAll(PDO::FETCH_ASSOC);

    $sql_nov = "SELECT descripcion FROM configuracion_novedades ORDER BY descripcion ASC";
    $novedades_db = $pdo->query($sql_nov)->fetchAll(PDO::FETCH_COLUMN);

    $plantilla = [
        1=>'CANETE', 2=>'LOPEZ', 3=>'GONZALEZ', 4=>'PAZ', 5=>'BALLADARES', 6=>'RODRIGUEZ',
        7=>'BENSO', 8=>'VILLA', 9=>'CACERES', 10=>'GARCIA', 11=>'LAZZARI', 12=>'BONFIGLIOLI', 13=>'PIHUALA'
    ];

    function limpiar_txt_local($s) { return str_replace(['Á','É','Í','Ó','Ú','Ñ'], ['A','E','I','O','U','N'], mb_strtoupper($s, 'UTF-8')); }

    $usuarios_encontrados_ids = [];
    foreach ($plantilla as $posicion => $keyword) {
        foreach ($todos_usuarios as $u) {
            if (strpos(limpiar_txt_local($u['nombre_completo']), $keyword) !== false) {
                if (!in_array($u['id_usuario'], $usuarios_encontrados_ids)) {
                    $personal_ordenado[$posicion] = $u;
                    $usuarios_encontrados_ids[] = $u['id_usuario'];
                    break; 
                }
            }
        }
    }
} catch (PDOException $e) { $mensaje_error = "Error BD: " . $e->getMessage(); }

include 'navbar.php';
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Parte de Novedades</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">
    <style>
        .fila-presente { background-color: #f0fff4; }
        .fila-ausente { background-color: #fff5f5; }
        .fila-tarde { background-color: #e7f5ff; }
        .fila-comision { background-color: #fff9db; }
        
        .selector-estado { font-weight: bold; border: 1px solid #ced4da; }
        .selector-estado.presente { color: #198754; border-color: #198754; }
        .selector-estado.ausente { color: #dc3545; border-color: #dc3545; }
        .selector-estado.tarde { color: #0d6efd; border-color: #0d6efd; }
        .selector-estado.comision { color: #ffc107; border-color: #ffc107; }
    </style>
</head>
<body>
    <div class="container mt-4 mb-5">
        <?php if (!empty($mensaje_error)): ?><div class="alert alert-danger"><?php echo $mensaje_error; ?></div><?php endif; ?>

        <div class="card shadow">
            <div class="card-header bg-dark text-white d-flex justify-content-between align-items-center">
                <h5 class="mb-0"><i class="fas fa-clipboard-list me-2"></i> PARTE DIARIO</h5>
                <span class="badge bg-secondary"><?php echo date('d/m/Y'); ?></span>
            </div>
            <div class="card-body">
                <?php if (!$es_jefe_supremo): ?>
                    <div class="alert alert-info small p-2"><i class="fas fa-info-circle"></i> Modo Redacción (Requiere aprobación).</div>
                <?php endif; ?>

                <form action="asistencia_guardar.php" method="POST">
                    <div class="row mb-3 g-2">
                        <div class="col-md-3"><input type="date" name="fecha" value="<?php echo date('Y-m-d'); ?>" class="form-control fw-bold" required></div>
                        <div class="col-md-9"><input type="text" name="titulo_parte" class="form-control" value="PARTES DE NOVEDADES POLICLÍNICA 'GRAL DON OMAR ACTIS'"></div>
                    </div>

                    <datalist id="lista_novedades_comunes">
                        <?php foreach($novedades_db as $nov): ?><option value="<?php echo htmlspecialchars($nov); ?>"><?php endforeach; ?>
                    </datalist>

                    <div class="table-responsive">
                        <table class="table table-bordered align-middle mb-0">
                            <thead class="table-light text-center small text-uppercase">
                                <tr>
                                    <th style="width:40px">#</th>
                                    <th>Grado/Nombre</th>
                                    <th style="width:160px">Estado</th>
                                    <th>Observación / Destino / Horario</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php 
                                for ($i = 1; $i <= 13; $i++): 
                                    $p = $personal_ordenado[$i] ?? null;
                                    if (!$p) continue; 
                                ?>
                                <tr class="fila-persona fila-presente">
                                    <td class="text-center fw-bold text-muted"><?php echo $i; ?></td>
                                    <td>
                                        <span class="badge bg-light text-dark border me-1"><?php echo htmlspecialchars($p['grado'] ?? '-'); ?></span>
                                        <span class="fw-bold text-dark"><?php echo htmlspecialchars($p['nombre_completo']); ?></span>
                                    </td>
                                    <td>
                                        <select name="datos[<?php echo $p['id_usuario']; ?>][tipo]" class="form-select form-select-sm selector-estado presente" onchange="cambiarEstado(this)">
                                            <option value="presente">🟢 Presente</option>
                                            <option value="ausente">🔴 Ausente</option>
                                            <option value="tarde">🔵 Turno Tarde</option>
                                            <option value="comision">🟡 Comisión</option>
                                        </select>
                                    </td>
                                    <td>
                                        <input type="text" 
                                               name="datos[<?php echo $p['id_usuario']; ?>][obs]" 
                                               class="form-control form-control-sm input-motivo" 
                                               list="lista_novedades_comunes" 
                                               placeholder="Sin novedad..." disabled>
                                    </td>
                                </tr>
                                <?php endfor; ?>
                            </tbody>
                        </table>
                    </div>

                    <div class="text-end mt-4">
                        <button type="submit" class="btn <?php echo $es_jefe_supremo ? 'btn-success' : 'btn-primary'; ?> btn-lg shadow">
                            <i class="fas fa-save me-2"></i> <?php echo $es_jefe_supremo ? 'Guardar y Firmar' : 'Enviar Parte'; ?>
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        function cambiarEstado(select) {
            const tr = select.closest('tr');
            const input = tr.querySelector('.input-motivo');
            const val = select.value;

            // Reset clases
            tr.className = 'fila-persona';
            select.className = 'form-select form-select-sm selector-estado';
            
            // Aplicar nuevas clases
            tr.classList.add('fila-' + val);
            select.classList.add(val);

            // Lógica del input
            if (val === 'presente') {
                input.disabled = true;
                input.value = '';
                input.placeholder = 'Sin novedad...';
            } else {
                input.disabled = false;
                if (val === 'ausente') input.placeholder = 'Motivo (Ej: Licencia, Enfermo)...';
                if (val === 'tarde') input.placeholder = 'Horario ingreso (Ej: 13:00hs)...';
                if (val === 'comision') input.placeholder = 'Lugar y Hora (Ej: A Martelli 08:00hs)...';
                input.focus();
            }
        }
    </script>
    <?php include 'footer.php'; ?>
</body>
</html>