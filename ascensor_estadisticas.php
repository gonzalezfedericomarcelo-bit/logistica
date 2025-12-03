<?php
// Archivo: ascensor_estadisticas.php
session_start();
require_once 'conexion.php';
require_once 'funciones_permisos.php';

if (!tiene_permiso('acceso_ascensores', $pdo)) { header("Location: dashboard.php"); exit; }

// DATOS PARA GRÁFICOS
// 1. Incidencias por Ascensor
$sql1 = "SELECT a.nombre, COUNT(i.id_incidencia) as total 
         FROM ascensores a 
         LEFT JOIN ascensor_incidencias i ON a.id_ascensor = i.id_ascensor 
         GROUP BY a.id_ascensor";
$data1 = $pdo->query($sql1)->fetchAll(PDO::FETCH_ASSOC);

// 2. Estado de Incidencias
$sql2 = "SELECT estado, COUNT(*) as total FROM ascensor_incidencias GROUP BY estado";
$data2 = $pdo->query($sql2)->fetchAll(PDO::FETCH_ASSOC);

$labels1 = []; $values1 = [];
foreach($data1 as $d) { $labels1[] = $d['nombre']; $values1[] = $d['total']; }

$labels2 = []; $values2 = [];
foreach($data2 as $d) { $labels2[] = strtoupper(str_replace('_',' ',$d['estado'])); $values2[] = $d['total']; }
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <?php include 'head.php'; ?>
    <title>Estadísticas de Ascensores</title>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
</head>
<body>
    <?php include 'navbar.php'; ?>
    
    <div class="container mt-4">
        <h2 class="mb-4"><i class="fas fa-chart-pie"></i> Estadísticas de Mantenimiento</h2>
        
        <div class="row">
            <div class="col-md-6 mb-4">
                <div class="card shadow">
                    <div class="card-header bg-primary text-white">Fallas por Equipo</div>
                    <div class="card-body">
                        <canvas id="chartEquipos"></canvas>
                    </div>
                </div>
            </div>
            <div class="col-md-6 mb-4">
                <div class="card shadow">
                    <div class="card-header bg-success text-white">Estado de Reclamos</div>
                    <div class="card-body">
                        <canvas id="chartEstados"></canvas>
                    </div>
                </div>
            </div>
        </div>
        
        <div class="text-center mt-3">
            <a href="mantenimiento_ascensores.php" class="btn btn-secondary">Volver al Listado</a>
        </div>
    </div>

    <script>
        // Gráfico 1: Barras
        new Chart(document.getElementById('chartEquipos'), {
            type: 'bar',
            data: {
                labels: <?php echo json_encode($labels1); ?>,
                datasets: [{
                    label: 'Cantidad de Fallas Reportadas',
                    data: <?php echo json_encode($values1); ?>,
                    backgroundColor: 'rgba(54, 162, 235, 0.6)',
                    borderColor: 'rgba(54, 162, 235, 1)',
                    borderWidth: 1
                }]
            },
            options: { scales: { y: { beginAtZero: true, ticks: {stepSize: 1} } } }
        });

        // Gráfico 2: Torta
        new Chart(document.getElementById('chartEstados'), {
            type: 'doughnut',
            data: {
                labels: <?php echo json_encode($labels2); ?>,
                datasets: [{
                    data: <?php echo json_encode($values2); ?>,
                    backgroundColor: ['#ffc107', '#17a2b8', '#007bff', '#28a745', '#dc3545']
                }]
            }
        });
    </script>
    <?php include 'footer.php'; ?>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>