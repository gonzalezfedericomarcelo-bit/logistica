<?php
// Archivo: asistencia_listado_general.php (CON ELIMINACIÓN SEGURA)
session_start();
include 'conexion.php';
include_once 'funciones_permisos.php';

if (!isset($_SESSION['usuario_id']) || !tiene_permiso('ver_historial_asistencia', $pdo)) {
    header("Location: dashboard.php");
    exit();
}

// Filtros
$fecha_inicio = $_GET['fecha_inicio'] ?? date('Y-m-d', strtotime('-30 days'));
$fecha_fin    = $_GET['fecha_fin'] ?? date('Y-m-d');
$filtro_mes   = $_GET['filtro_mes'] ?? '';

$where_clause = " WHERE p.fecha BETWEEN :start AND :end ";
$params = [':start' => $fecha_inicio, ':end' => $fecha_fin];

if (!empty($filtro_mes)) {
    $parts = explode('-', $filtro_mes);
    $fecha_inicio = date("Y-m-01", strtotime($filtro_mes . "-01"));
    $fecha_fin = date("Y-m-t", strtotime($filtro_mes . "-01"));
    $where_clause = " WHERE YEAR(p.fecha) = :year AND MONTH(p.fecha) = :month ";
    $params = [':year' => $parts[0], ':month' => $parts[1]];
}

// Consulta
$sql = "
    SELECT 
        p.id_parte,
        p.fecha,
        p.observaciones_generales,
        p.estado,
        p.id_creador,
        u.nombre_completo as creador,
        u.grado as grado_creador
    FROM asistencia_partes p
    JOIN usuarios u ON p.id_creador = u.id_usuario
    $where_clause
    ORDER BY p.fecha DESC, p.id_parte DESC
";
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$partes = $stmt->fetchAll(PDO::FETCH_ASSOC);

include 'navbar.php';
$meses_disponibles = $pdo->query("SELECT DISTINCT DATE_FORMAT(fecha, '%Y-%m') as mes_anio FROM asistencia_partes ORDER BY mes_anio DESC")->fetchAll(PDO::FETCH_COLUMN);
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Historial de Partes</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">
</head>
<body>
    <div class="container mt-4 mb-5">
        
        <div class="card shadow">
            <div class="card-header bg-dark text-white">
                <h5 class="mb-0"><i class="fas fa-history me-2"></i> HISTORIAL DE PARTES</h5>
            </div>
            <div class="card-body">
                
                <form method="GET" class="row g-3 align-items-end mb-4 bg-light p-3 rounded border">
                    <div class="col-12 col-md-3">
                        <label class="small">Mes</label>
                        <select name="filtro_mes" class="form-select form-select-sm">
                            <option value="">-- Rango --</option>
                            <?php foreach ($meses_disponibles as $ma) echo "<option value='$ma' ".($filtro_mes==$ma?'selected':'').">".date("F Y", strtotime($ma."-01"))."</option>"; ?>
                        </select>
                    </div>
                    <div class="col-6 col-md-3"><label class="small">Inicio</label><input type="date" name="fecha_inicio" value="<?php echo $fecha_inicio; ?>" class="form-control form-control-sm"></div>
                    <div class="col-6 col-md-3"><label class="small">Fin</label><input type="date" name="fecha_fin" value="<?php echo $fecha_fin; ?>" class="form-control form-control-sm"></div>
                    <div class="col-12 col-md-3"><button type="submit" class="btn btn-primary btn-sm w-100"><i class="fas fa-search"></i> Filtrar</button></div>
                </form>

                <div class="table-responsive">
                    <table class="table table-hover align-middle shadow-sm" style="background:white; border-radius:10px; overflow:hidden;">
                        <thead class="bg-primary text-white">
                            <tr>
                                <th>Fecha</th>
                                <th>Creador</th>
                                <th>Estado</th>
                                <th class="text-end">Acciones</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($partes as $p): 
                                $fecha_fmt = date('d/m/Y', strtotime($p['fecha']));
                                $estado_color = ($p['estado']=='aprobado') ? 'success' : 'warning text-dark';
                                $estado_icono = ($p['estado']=='aprobado') ? 'check-circle' : 'clock';
                            ?>
                            <tr>
                                <td class="fw-bold"><?php echo $fecha_fmt; ?></td>
                                <td><?php echo htmlspecialchars($p['creador']); ?></td>
                                <td><span class="badge bg-<?php echo $estado_color; ?>"><i class="fas fa-<?php echo $estado_icono; ?> me-1"></i> <?php echo strtoupper($p['estado']); ?></span></td>
                                <td class="text-end">
                                    <a href="asistencia_actualizar.php?id=<?php echo $p['id_parte']; ?>" class="btn btn-sm btn-warning text-dark fw-bold" title="Agregar Novedades/Editar">
                                        <i class="fas fa-edit"></i>
                                    </a>

                                    <a href="asistencia_pdf.php?id=<?php echo $p['id_parte']; ?>" target="_blank" class="btn btn-sm btn-secondary fw-bold" title="Ver PDF">
                                        <i class="fas fa-file-pdf"></i>
                                    </a>
                                    
                                    <?php if (tiene_permiso('admin_usuarios', $pdo)): // Usamos un permiso alto ?>
                                        <button class="btn btn-sm btn-danger fw-bold ms-1" onclick="iniciarEliminacion(<?php echo $p['id_parte']; ?>, '<?php echo $fecha_fmt; ?>')">
                                            <i class="fas fa-trash"></i>
                                        </button>
                                    <?php endif; ?>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <div class="modal fade" id="modalConfirm1" tabindex="-1">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header bg-danger text-white"><h5 class="modal-title">Eliminar Parte</h5></div>
                <div class="modal-body">¿Estás seguro que quieres eliminar el parte del día <strong id="fechaParteDel"></strong>?</div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                    <button type="button" class="btn btn-danger" onclick="paso2()">Sí, estoy seguro</button>
                </div>
            </div>
        </div>
    </div>

    <div class="modal fade" id="modalConfirm2" tabindex="-1">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header bg-danger text-white"><h5 class="modal-title">⚠️ ADVERTENCIA</h5></div>
                <div class="modal-body fw-bold text-center">¿Realmente estás seguro?<br>Esta acción NO se puede deshacer.</div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                    <button type="button" class="btn btn-danger" onclick="paso3()">Sí, segurísimo</button>
                </div>
            </div>
        </div>
    </div>

    <div class="modal fade" id="modalPass" tabindex="-1">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header bg-dark text-white"><h5 class="modal-title">Seguridad</h5></div>
                <div class="modal-body">
                    <label>Ingresa tu contraseña para confirmar:</label>
                    <input type="password" id="delPassword" class="form-control mt-2" placeholder="Contraseña...">
                    <input type="hidden" id="idParteDel">
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                    <button type="button" class="btn btn-dark" onclick="ejecutarEliminacion()">CONFIRMAR ELIMINACIÓN</button>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        let modal1, modal2, modal3;
        
        document.addEventListener('DOMContentLoaded', () => {
            modal1 = new bootstrap.Modal(document.getElementById('modalConfirm1'));
            modal2 = new bootstrap.Modal(document.getElementById('modalConfirm2'));
            modal3 = new bootstrap.Modal(document.getElementById('modalPass'));
        });

        function iniciarEliminacion(id, fecha) {
            document.getElementById('idParteDel').value = id;
            document.getElementById('fechaParteDel').innerText = fecha;
            modal1.show();
        }

        function paso2() {
            modal1.hide();
            modal2.show();
        }

        function paso3() {
            modal2.hide();
            modal3.show();
        }

        function ejecutarEliminacion() {
            const id = document.getElementById('idParteDel').value;
            const pass = document.getElementById('delPassword').value;
            
            if(!pass) { alert("Debes ingresar la contraseña."); return; }

            fetch('asistencia_eliminar.php', {
                method: 'POST',
                headers: {'Content-Type': 'application/json'},
                body: JSON.stringify({id_parte: id, password: pass})
            })
            .then(r => r.json())
            .then(data => {
                if(data.success) {
                    alert("Parte eliminado.");
                    window.location.reload();
                } else {
                    alert("Error: " + data.msg);
                }
            });
        }
    </script>
    <?php include 'footer.php'; ?>
</body>
</html>