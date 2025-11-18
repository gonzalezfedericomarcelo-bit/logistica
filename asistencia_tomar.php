<?php
// Archivo: asistencia_tomar.php (CON LISTA INTELIGENTE Y AUTO-APRENDIZAJE)
session_start();
include 'conexion.php';
include 'funciones_permisos.php'; 

if (!isset($_SESSION['usuario_id']) || !in_array($_SESSION['usuario_rol'], ['admin', 'encargado', 'empleado'])) {
    header("Location: dashboard.php");
    exit();
}

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

    // 2. OBTENER LISTA DE NOVEDADES (Desde la nueva tabla)
    $sql_nov = "SELECT descripcion FROM configuracion_novedades ORDER BY descripcion ASC";
    $novedades_db = $pdo->query($sql_nov)->fetchAll(PDO::FETCH_COLUMN);

    // 3. PLANTILLA ORDEN
    $plantilla = [
        1  => 'CANETE', 2  => 'LOPEZ', 3  => 'GONZALEZ', 4  => 'PAZ',
        5  => 'BALLADARES', 6  => 'RODRIGUEZ', 7  => 'BENSO', 8  => 'VILLA',
        9  => 'CACERES', 10 => 'GARCIA', 11 => 'LAZZARI', 12 => 'BONFIGLIOLI', 13 => 'PIHUALA'
    ];

    function limpiar_txt_local($s) { 
        return str_replace(['Á','É','Í','Ó','Ú','Ñ'], ['A','E','I','O','U','N'], mb_strtoupper($s, 'UTF-8')); 
    }

    // 4. LLENAR CASILLEROS
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
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">
</head>
<body>
    <div class="container mt-4 mb-5">
        
        <?php if (!empty($mensaje_error)): ?>
            <div class="alert alert-danger"><?php echo $mensaje_error; ?></div>
        <?php endif; ?>

        <div class="card shadow">
            <div class="card-header bg-dark text-white d-flex justify-content-between align-items-center">
                <h5 class="mb-0"><i class="fas fa-file-alt me-2"></i> NUEVO PARTE DE NOVEDADES</h5>
                <span><?php echo date('d/m/Y'); ?></span>
            </div>
            <div class="card-body">
                <form action="asistencia_guardar.php" method="POST">
                    
                    <div class="row mb-4 bg-light p-3 rounded border">
                        <div class="col-md-3">
                            <label class="fw-bold">Fecha</label>
                            <input type="date" name="fecha" value="<?php echo date('Y-m-d'); ?>" class="form-control" required>
                        </div>
                        <div class="col-md-9">
                            <label class="fw-bold">Título / Observaciones Generales</label>
                            <input type="text" name="titulo_parte" class="form-control" value="PARTES DE NOVEDADES POLICLÍNICA 'GRAL DON OMAR ACTIS'">
                        </div>
                    </div>

                    <datalist id="lista_novedades_comunes">
                        <?php foreach($novedades_db as $nov): ?>
                            <option value="<?php echo htmlspecialchars($nov); ?>">
                        <?php endforeach; ?>
                    </datalist>

                    <div class="table-responsive">
                        <table class="table table-bordered table-hover align-middle">
                            <thead class="table-secondary text-center">
                                <tr>
                                    <th width="5%">Nro</th>
                                    <th width="15%">GRADO</th>
                                    <th width="45%">APELLIDO Y NOMBRE</th>
                                    <th width="10%">PRESENTE</th>
                                    <th width="25%">NOVEDAD / MOTIVO</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php 
                                for ($i = 1; $i <= 13; $i++): 
                                    $p = $personal_ordenado[$i] ?? null;
                                    if (!$p) continue; 
                                ?>
                                <tr>
                                    <td class="text-center fw-bold"><?php echo $i; ?></td>
                                    <td class="text-center fw-bold text-primary">
                                        <?php echo htmlspecialchars($p['grado'] ?? '-'); ?>
                                    </td>
                                    <td class="fw-bold">
                                        <?php echo htmlspecialchars($p['nombre_completo']); ?>
                                    </td>
                                    
                                    <td class="text-center">
                                        <div class="form-check d-flex justify-content-center">
                                            <input class="form-check-input border-dark check-presente" type="checkbox" 
                                                   name="datos[<?php echo $p['id_usuario']; ?>][presente]" 
                                                   value="1" checked style="transform: scale(1.3);">
                                        </div>
                                    </td>
                                    
                                    <td>
                                        <input type="text" 
                                               name="datos[<?php echo $p['id_usuario']; ?>][obs]" 
                                               class="form-control form-control-sm input-motivo" 
                                               list="lista_novedades_comunes" 
                                               placeholder="Escriba o seleccione..." 
                                               disabled>
                                    </td>
                                </tr>
                                <?php endfor; ?>
                            </tbody>
                        </table>
                    </div>

                    <div class="text-end mt-3">
                        <button type="submit" class="btn btn-success btn-lg">
                            <i class="fas fa-save me-2"></i> Guardar y Generar PDF
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            function actualizarEstadoFila(checkbox) {
                const fila = checkbox.closest('tr');
                const input = fila.querySelector('.input-motivo'); // Cambiado a input-motivo

                if (checkbox.checked) {
                    // SI PRESENTE
                    input.disabled = true;
                    input.value = ""; 
                    fila.classList.remove('table-warning');
                } else {
                    // NO PRESENTE
                    input.disabled = false;
                    fila.classList.add('table-warning');
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