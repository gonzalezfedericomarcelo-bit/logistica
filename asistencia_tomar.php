<?php
// Archivo: asistencia_tomar.php (CORREGIDO: AHORA FUNCIONA EL MENÚ)
session_start();
include 'conexion.php';
include 'funciones_permisos.php'; 

if (!isset($_SESSION['usuario_id']) || !in_array($_SESSION['usuario_rol'], ['admin', 'encargado', 'empleado'])) {
    header("Location: dashboard.php");
    exit();
}

// --- LÓGICA DE JERARQUÍA ESTRICTA ---
$nombre_usuario_actual = $_SESSION['usuario_nombre'] ?? '';
$es_jefe_supremo = (stripos($nombre_usuario_actual, 'Cañete') !== false);

$personal_ordenado = [];
$mensaje_error = '';

try {
    // 1. OBTENER USUARIOS
    $sql = "SELECT id_usuario, nombre_completo, grado, rol 
            FROM usuarios 
            WHERE activo = 1 
            AND nombre_completo NOT LIKE '%Alejandro Batista%'
            AND nombre_completo NOT LIKE '%Juan Pablo Hernandez%'
            AND nombre_completo NOT LIKE '%Juan Pablo Hernández%' 
            ORDER BY nombre_completo ASC";
    $todos_usuarios = $pdo->query($sql)->fetchAll(PDO::FETCH_ASSOC);

    // 2. OBTENER NOVEDADES
    $sql_nov = "SELECT descripcion FROM configuracion_novedades ORDER BY descripcion ASC";
    $novedades_db = $pdo->query($sql_nov)->fetchAll(PDO::FETCH_COLUMN);

    // 3. PLANTILLA
    $plantilla = [
        1  => 'CANETE', 2  => 'LOPEZ', 3  => 'GONZALEZ', 4  => 'PAZ',
        5  => 'BALLADARES', 6  => 'RODRIGUEZ', 7  => 'BENSO', 8  => 'VILLA',
        9  => 'CACERES', 10 => 'GARCIA', 11 => 'LAZZARI', 12 => 'BONFIGLIOLI', 13 => 'PIHUALA'
    ];

    function limpiar_txt_local($s) { 
        return str_replace(['Á','É','Í','Ó','Ú','Ñ'], ['A','E','I','O','U','N'], mb_strtoupper($s, 'UTF-8')); 
    }

    // 4. LLENAR
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

} catch (PDOException $e) {
    $mensaje_error = "Error BD: " . $e->getMessage();
}

include 'navbar.php';
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Generar Parte de Novedades</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">
    <style>
        @media (max-width: 768px) {
            .input-motivo { font-size: 16px; }
            .check-presente { transform: scale(1.6); margin-top: 5px; }
            .fw-bold-mobile { font-weight: bold; font-size: 0.95rem; display: block; }
            .grado-badge { font-size: 0.8rem; margin-bottom: 4px; }
            .table-responsive { border: 0; }
            .btn-lg-mobile { width: 100%; padding: 15px; font-size: 1.1rem; }
        }
    </style>
</head>
<body>
    <div class="container mt-4 mb-5">
        
        <?php if (!empty($mensaje_error)): ?>
            <div class="alert alert-danger"><?php echo $mensaje_error; ?></div>
        <?php endif; ?>

        <div class="card shadow">
            <div class="card-header bg-dark text-white d-flex justify-content-between align-items-center flex-wrap">
                <h5 class="mb-0 me-2"><i class="fas fa-file-alt me-2"></i> PARTE DE NOVEDADES</h5>
                <span class="badge bg-secondary"><?php echo date('d/m/Y'); ?></span>
            </div>
            <div class="card-body">
                
                <?php if (!$es_jefe_supremo): ?>
                    <div class="alert alert-info border-start border-info border-4 shadow-sm">
                        <i class="fas fa-info-circle me-2"></i> <strong>Modo Redacción:</strong> Este parte se enviará a <strong>Aprobación</strong> del Encargado (Cañete) antes de ser definitivo.
                    </div>
                <?php endif; ?>

                <form action="asistencia_guardar.php" method="POST">
                    
                    <div class="row mb-4 bg-light p-3 rounded border g-3">
                        <div class="col-12 col-md-3">
                            <label class="fw-bold small text-muted text-uppercase">Fecha del Parte</label>
                            <input type="date" name="fecha" value="<?php echo date('Y-m-d'); ?>" class="form-control fw-bold" required>
                        </div>
                        <div class="col-12 col-md-9">
                            <label class="fw-bold small text-muted text-uppercase">Título / Encabezado</label>
                            <input type="text" name="titulo_parte" class="form-control" value="PARTES DE NOVEDADES POLICLÍNICA 'GRAL DON OMAR ACTIS'">
                        </div>
                    </div>

                    <datalist id="lista_novedades_comunes">
                        <?php foreach($novedades_db as $nov): ?>
                            <option value="<?php echo htmlspecialchars($nov); ?>">
                        <?php endforeach; ?>
                    </datalist>

                    <div class="table-responsive">
                        <table class="table table-bordered table-hover align-middle caption-top">
                            <caption class="small text-muted d-md-none ps-2">
                                <i class="fas fa-hand-pointer me-1"></i> Desliza o toca los casilleros para editar.
                            </caption>
                            <thead class="table-secondary text-center small text-uppercase">
                                <tr>
                                    <th style="width: 40px;">#</th>
                                    <th style="width: 70px;">Grado</th>
                                    <th style="min-width: 180px;">Personal</th>
                                    <th style="width: 60px;">Pres.</th>
                                    <th style="min-width: 200px;">Novedad / Motivo</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php 
                                for ($i = 1; $i <= 13; $i++): 
                                    $p = $personal_ordenado[$i] ?? null;
                                    if (!$p) continue; 
                                ?>
                                <tr>
                                    <td class="text-center fw-bold text-muted"><?php echo $i; ?></td>
                                    <td class="text-center">
                                        <span class="badge bg-light text-dark border grado-badge"><?php echo htmlspecialchars($p['grado'] ?? '-'); ?></span>
                                    </td>
                                    <td>
                                        <span class="fw-bold-mobile text-dark">
                                            <?php echo htmlspecialchars($p['nombre_completo']); ?>
                                        </span>
                                    </td>
                                    
                                    <td class="text-center bg-white">
                                        <div class="form-check d-flex justify-content-center align-items-center">
                                            <input class="form-check-input border-secondary check-presente" type="checkbox" 
                                                   name="datos[<?php echo $p['id_usuario']; ?>][presente]" 
                                                   value="1" checked style="cursor:pointer;">
                                        </div>
                                    </td>
                                    
                                    <td>
                                        <input type="text" 
                                               name="datos[<?php echo $p['id_usuario']; ?>][obs]" 
                                               class="form-control form-control-sm input-motivo" 
                                               list="lista_novedades_comunes" 
                                               placeholder="Sin novedad..." 
                                               disabled autocomplete="off">
                                    </td>
                                </tr>
                                <?php endfor; ?>
                            </tbody>
                        </table>
                    </div>

                    <div class="text-end mt-4 pb-3">
                        <?php if ($es_jefe_supremo): ?>
                            <button type="submit" class="btn btn-success btn-lg btn-lg-mobile shadow">
                                <i class="fas fa-check-double me-2"></i> Guardar y Firmar Parte
                            </button>
                        <?php else: ?>
                            <button type="submit" class="btn btn-primary btn-lg btn-lg-mobile shadow">
                                <i class="fas fa-paper-plane me-2"></i> Enviar a Aprobación
                            </button>
                        <?php endif; ?>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            function actualizarEstadoFila(checkbox) {
                const fila = checkbox.closest('tr');
                const input = fila.querySelector('.input-motivo'); 

                if (checkbox.checked) {
                    input.disabled = true;
                    input.value = ""; 
                    input.classList.remove('bg-white');
                    fila.classList.remove('table-warning');
                } else {
                    input.disabled = false;
                    input.classList.add('bg-white');
                    fila.classList.add('table-warning');
                    setTimeout(() => input.focus(), 50); 
                }
            }

            document.querySelectorAll('.check-presente').forEach(function(cb) {
                cb.addEventListener('change', function() {
                    actualizarEstadoFila(this);
                });
                actualizarEstadoFila(cb);
            });
        });
    </script>
</body>
</html>