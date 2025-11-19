<?php
// Archivo: dashboard.php (VERSIÓN FINAL CON GRÁFICOS CLICABLES Y FILTRO SANEADO)
session_start();
// Asegúrate de que este archivo 'conexion.php' exista y provea $pdo
include 'conexion.php'; 

// 1. Verificar si el usuario está logueado
if (!isset($_SESSION['usuario_id'])) {
    header("Location: login.php");
    exit();
}

$id_usuario = $_SESSION['usuario_id'];
$rol_usuario = $_SESSION['usuario_rol'];
$nombre_usuario = $_SESSION['usuario_nombre'] ?? 'Usuario';

// --- Funciones Helper ---
function getGreeting() {
    $hour = date('H');
    if ($hour >= 5 && $hour < 12) {
        return 'Buenos días';
    } elseif ($hour >= 12 && $hour < 19) {
        return 'Buenas tardes';
    } else {
        return 'Buenas noches';
    }
}
$saludo = getGreeting();

// 2. Lista de Frases Motivadoras
$frases_motivadoras = [
    "¡El éxito es la suma de pequeños esfuerzos repetidos día tras día!",
    "La logística no es solo mover cosas, es mover el futuro. ¡Excelente trabajo!",
    "Mantén la calma y continúa. Cada tarea es un paso hacia el gran objetivo.",
    "La calidad no es un acto, es un hábito. ¡Vamos por ello!",
    "Somos lo que hacemos repetidamente. La excelencia, entonces, no es un hábito.",
];
$frase_del_dia = $frases_motivadoras[array_rand($frases_motivadoras)];

// --- 3. Lógica para Contadores de Tareas (6 Widgets) ---

$total_tareas_activas = 0; // Widget 1: Activas
$tareas_pendientes = 0; // Widget 2: Pendientes
$tareas_en_curso = 0; // Widget 3: En Curso (Nota: Ajustado a 'en_curso' de tu DB)
$tareas_en_revision = 0; // Widget 4: En Revisión
$tareas_urgentes_atrasadas = 0; // Widget 5: Atrasadas (Alarma)
$tareas_finalizadas_total = 0; // Widget 6: Verificadas (Histórico)
$mensaje_error_widget = '';

// *** LÓGICA DE FILTRADO POR ROL para Widgets ***
$user_filter_clause = "";
$bind_user_id = false;
if ($rol_usuario === 'empleado') {
    $user_filter_clause = "id_asignado = :id_user AND ";
    $bind_user_id = true;
}


try {
    // 3.1. Tareas Activas (Total, no cerradas/verificadas)
    $sql_activas = "SELECT COUNT(*) FROM tareas WHERE {$user_filter_clause} estado NOT IN ('cerrada', 'verificada_admin')";
    $stmt_activas = $pdo->prepare($sql_activas);
    if ($bind_user_id) { $stmt_activas->bindParam(':id_user', $id_usuario); }
    $stmt_activas->execute();
    $total_tareas_activas = $stmt_activas->fetchColumn();

    // 3.2. Tareas Pendientes (Estado 'pendiente')
    $sql_pendientes = "SELECT COUNT(*) FROM tareas WHERE {$user_filter_clause} estado = 'pendiente'";
    $stmt_pendientes = $pdo->prepare($sql_pendientes);
    if ($bind_user_id) { $stmt_pendientes->bindParam(':id_user', $id_usuario); }
    $stmt_pendientes->execute();
    $tareas_pendientes = $stmt_pendientes->fetchColumn();

    // 3.3. Tareas En Curso (Estado 'en_curso')
    $sql_curso = "SELECT COUNT(*) FROM tareas WHERE {$user_filter_clause} estado = 'en_curso'";
    $stmt_curso = $pdo->prepare($sql_curso);
    if ($bind_user_id) { $stmt_curso->bindParam(':id_user', $id_usuario); }
    $stmt_curso->execute();
    $tareas_en_curso = $stmt_curso->fetchColumn();
    
    // 3.4. Tareas En Revisión (Estado 'finalizada_tecnico')
    $sql_revision = "SELECT COUNT(*) FROM tareas WHERE {$user_filter_clause} estado = 'finalizada_tecnico'";
    $stmt_revision = $pdo->prepare($sql_revision);
    if ($bind_user_id) { $stmt_revision->bindParam(':id_user', $id_usuario); }
    $stmt_revision->execute();
    $tareas_en_revision = $stmt_revision->fetchColumn();

    // 3.5. Tareas Verificadas (TOTAL HISTÓRICO)
    $sql_cerradas_total = "SELECT COUNT(*) FROM tareas WHERE {$user_filter_clause} estado IN ('cerrada', 'verificada_admin')";
    $stmt_cerradas_total = $pdo->prepare($sql_cerradas_total);
    if ($bind_user_id) { $stmt_cerradas_total->bindParam(':id_user', $id_usuario); }
    $stmt_cerradas_total->execute();
    $tareas_finalizadas_total = $stmt_cerradas_total->fetchColumn();
    
    // 3.6. Tareas Atrasadas (Fecha límite vencida y no finalizada)
    $sql_atrasadas = "
        SELECT COUNT(*) 
        FROM tareas 
        WHERE {$user_filter_clause} 
        fecha_limite < CURDATE() 
        AND estado NOT IN ('cerrada', 'verificada_admin', 'finalizada_tecnico')
    ";
    $stmt_atrasadas = $pdo->prepare($sql_atrasadas);
    if ($bind_user_id) { $stmt_atrasadas->bindParam(':id_user', $id_usuario); }
    $stmt_atrasadas->execute();
    $tareas_urgentes_atrasadas = $stmt_atrasadas->fetchColumn();

} catch (PDOException $e) {
    error_log("Error al cargar contadores de tareas: " . $e->getMessage());
    $mensaje_error_widget = "Error: No se pudieron cargar los datos de los widgets. Revise la conexión a la BD.";
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard | Sistema de Logística</title>
    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.3/dist/chart.umd.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/sortablejs@1.15.2/Sortable.min.js"></script>

    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">
    <style>
        .card a.stretched-link {
            transition: transform 0.2s;
        }
        .card:hover a.stretched-link {
            transform: translateX(5px);
        }
        /* Cursor de puntero para indicar que los gráficos son clicables */
        #categoryDoughnutChart, #priorityDoughnutChart {
            cursor: pointer;
        }
    </style>
</head>
<body>
    
    <?php include 'navbar.php'; ?> 

    <div class="container mt-5">
        
        <div class="alert alert-primary shadow-sm" role="alert">
            <h4 class="alert-heading mb-2">
                <i class="fas fa-hand-paper me-2"></i> 
                <?php echo $saludo; ?>, <?php echo htmlspecialchars($nombre_usuario); ?>.
            </h4>
            
            <h5 class="mb-0 text-muted fst-italic">
                <i class="fas fa-quote-left me-2"></i>
                <?php echo htmlspecialchars($frase_del_dia); ?>
            </h5>
            
            <?php if (!empty($mensaje_error_widget)): ?>
                 <hr><p class="mb-0 text-danger"><?php echo $mensaje_error_widget; ?></p>
            <?php endif; ?>
        </div>
        
        <h3 class="mb-4 mt-4">Estado del Flujo de Trabajo (6 Indicadores Clave)</h3>
        
        <div class="row row-cols-1 row-cols-md-3 row-cols-lg-6 g-4 mb-5">
            
            <div class="col">
                <div class="card bg-primary text-white h-100 shadow-sm">
                    <div class="card-body">
                        <div class="d-flex justify-content-between align-items-center">
                            <div>
                                <h6 class="text-uppercase mb-2" style="font-size: 0.75rem;">Total Activas</h6>
                                <h2 class="display-5 fw-bold mb-0"><?php echo $total_tareas_activas; ?></h2>
                            </div>
                            <i class="fas fa-list-check fa-2x opacity-50"></i>
                        </div>
                        <hr class="mt-2 mb-2">
                        <a href="tareas_lista.php?estado=todas" class="text-white small fw-bold text-decoration-none stretched-link">Ver Tareas Activas <i class="fas fa-arrow-circle-right ms-1"></i></a>
                    </div>
                </div>
            </div>
            
            <div class="col">
                <div class="card bg-info text-white h-100 shadow-sm">
                    <div class="card-body">
                        <div class="d-flex justify-content-between align-items-center">
                            <div>
                                <h6 class="text-uppercase mb-2" style="font-size: 0.75rem;">Pendientes</h6>
                                <h2 class="display-5 fw-bold mb-0"><?php echo $tareas_pendientes; ?></h2>
                            </div>
                            <i class="fas fa-hourglass-start fa-2x opacity-50"></i>
                        </div>
                        <hr class="mt-2 mb-2">
                        <a href="tareas_lista.php?estado=pendiente" class="text-white small fw-bold text-decoration-none stretched-link">Tareas sin Iniciar <i class="fas fa-arrow-circle-right ms-1"></i></a>
                    </div>
                </div>
            </div>

            <div class="col">
                <div class="card bg-warning text-dark h-100 shadow-sm">
                    <div class="card-body">
                        <div class="d-flex justify-content-between align-items-center">
                            <div>
                                <h6 class="text-uppercase mb-2" style="font-size: 0.75rem;">En Curso</h6>
                                <h2 class="display-5 fw-bold mb-0"><?php echo $tareas_en_curso; ?></h2>
                            </div>
                            <i class="fas fa-tools fa-2x opacity-50"></i>
                        </div>
                        <hr class="mt-2 mb-2">
                        <a href="tareas_lista.php?estado=en_curso" class="text-dark small fw-bold text-decoration-none stretched-link">Tareas en Curso <i class="fas fa-arrow-circle-right ms-1"></i></a>
                    </div>
                </div>
            </div>
            
            <div class="col">
                <div class="card bg-secondary text-white h-100 shadow-sm">
                    <div class="card-body">
                        <div class="d-flex justify-content-between align-items-center">
                            <div>
                                <h6 class="text-uppercase mb-2" style="font-size: 0.75rem;">En Revisión</h6>
                                <h2 class="display-5 fw-bold mb-0"><?php echo $tareas_en_revision; ?></h2>
                            </div>
                            <i class="fas fa-search-plus fa-2x opacity-50"></i>
                        </div>
                        <hr class="mt-2 mb-2">
                        <a href="tareas_lista.php?estado=finalizada_tecnico" class="text-white small fw-bold text-decoration-none stretched-link">Tareas Para Aprobar <i class="fas fa-arrow-circle-right ms-1"></i></a>
                    </div>
                </div>
            </div>
            
            <div class="col">
                <div class="card bg-danger text-white h-100 shadow-sm">
                    <div class="card-body">
                        <div class="d-flex justify-content-between align-items-center">
                            <div>
                                <h6 class="text-uppercase mb-2" style="font-size: 0.75rem;">Atrasadas</h6>
                                <h2 class="display-5 fw-bold mb-0"><?php echo $tareas_urgentes_atrasadas; ?></h2>
                            </div>
                            <i class="fas fa-clock fa-2x opacity-50"></i>
                        </div>
                        <hr class="mt-2 mb-2">
                        <a href="tareas_lista.php?estado=atrasadas" class="text-white small fw-bold text-decoration-none stretched-link">Revisar Alarma <i class="fas fa-arrow-circle-right ms-1"></i></a>
                    </div>
                </div>
            </div>

            <div class="col">
                <div class="card bg-success text-white h-100 shadow-sm">
                    <div class="card-body">
                        <div class="d-flex justify-content-between align-items-center">
                            <div>
                                <h6 class="text-uppercase mb-2" style="font-size: 0.75rem;">Verificadas</h6>
                                <h2 class="display-5 fw-bold mb-0"><?php echo $tareas_finalizadas_total; ?></h2>
                            </div>
                            <i class="fas fa-calendar-check fa-2x opacity-50"></i>
                        </div>
                        <hr class="mt-2 mb-2">
                        <a href="tareas_lista.php?estado=verificadas" class="text-white small fw-bold text-decoration-none stretched-link">Ver Historial Cierre <i class="fas fa-arrow-circle-right ms-1"></i></a>
                    </div>
                </div>
            </div>

        </div>
        <h3 class="mb-4">Estadísticas Avanzadas de Tareas</h3>

        <div class="row">
            
            <div class="col-lg-4">
                <div class="card shadow mb-4 h-100">
                    <div class="card-header bg-dark text-white">
                        <i class="fas fa-chart-bar me-2"></i> **Rendimiento Histórico**
                    </div>
                    <div class="card-body">
                        <h5 class="card-title text-primary">Ingreso vs. Tasa de Cierre</h5>
                        
                        <div class="row align-items-end mb-3 bg-light p-2 rounded shadow-sm">
                            <div class="col-6">
                                <label for="groupByFilter" class="form-label fw-bold small">Agrupar por:</label>
                                <select class="form-select form-select-sm" id="groupByFilter">
                                    <option value="day">Día</option>
                                    <option value="week" selected>Semana</option>
                                    <option value="month">Mes</option>
                                </select>
                            </div>
                            <div class="col-6">
                                <button class="btn btn-primary btn-sm w-100 mt-2" id="applyLineFiltersBtn">
                                    <i class="fas fa-search me-1"></i> Filtrar
                                </button>
                            </div>
                            <div class="col-12 mt-2">
                                <label for="startDateFilter" class="form-label fw-bold small mb-0">Rango de Fechas:</label>
                                <input type="date" class="form-control form-control-sm mb-1" id="startDateFilter" value="<?php echo date('Y-m-d', strtotime('-3 months')); ?>">
                                <input type="date" class="form-control form-control-sm" id="endDateFilter" value="<?php echo date('Y-m-d'); ?>">
                            </div>
                        </div>
                        
                        <div class="position-relative" style="height: 250px;">
                            <canvas id="performanceLineChart"></canvas>
                            <div id="loadingLineIndicator" class="position-absolute top-50 start-50 translate-middle" style="display: none;">
                                <div class="spinner-border text-primary" role="status"><span class="visually-hidden">Cargando...</span></div>
                            </div>
                            <div id="errorLineIndicator" class="alert alert-danger position-absolute top-50 start-50 translate-middle" style="display: none;">Error al cargar los datos.</div>
                        </div>
                    </div>
                </div>
            </div>
            
            <div class="col-lg-4">
                <div class="card shadow mb-4 h-100">
                    <div class="card-header bg-dark text-white">
                        <i class="fas fa-chart-pie me-2"></i> **Distribución por Categoría**
                    </div>
                    <div class="card-body d-flex flex-column justify-content-center">
                        <h5 class="card-title text-success text-center">Tareas Activas por Categoría</h5>
                        <div class="position-relative" style="height: 350px;">
                            <canvas id="categoryDoughnutChart"></canvas>
                            <div id="loadingDoughnutIndicator" class="position-absolute top-50 start-50 translate-middle" style="display: none;">
                                <div class="spinner-border text-success" role="status"><span class="visually-hidden">Cargando...</span></div>
                            </div>
                            <div id="errorDoughnutIndicator" class="alert alert-danger position-absolute top-50 start-50 translate-middle" style="display: none;">Error al cargar datos de categorías.</div>
                        </div>
                    </div>
                </div>
            </div>
            
            <div class="col-lg-4">
                <div class="card shadow mb-4 h-100">
                    <div class="card-header bg-dark text-white">
                        <i class="fas fa-exclamation-triangle me-2"></i> **Carga Pendiente por Prioridad**
                    </div>
                    <div class="card-body d-flex flex-column justify-content-center">
                        <h5 class="card-title text-danger text-center">Tareas Activas por Nivel de Riesgo</h5>
                        <div class="position-relative" style="height: 350px;">
                            <canvas id="priorityDoughnutChart"></canvas>
                            <div id="loadingPriorityIndicator" class="position-absolute top-50 start-50 translate-middle" style="display: none;">
                                <div class="spinner-border text-danger" role="status"><span class="visually-hidden">Cargando...</span></div>
                            </div>
                            <div id="errorPriorityIndicator" class="alert alert-danger position-absolute top-50 start-50 translate-middle" style="display: none;">Error al cargar datos de prioridad.</div>
                        </div>
                    </div>
                </div>
            </div>

        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        document.addEventListener('DOMContentLoaded', () => {
            
            // --- ELEMENTOS DE LA PÁGINA ---
            const applyLineFiltersBtn = document.getElementById('applyLineFiltersBtn');
            const groupByFilter = document.getElementById('groupByFilter');
            const startDateFilter = document.getElementById('startDateFilter');
            const endDateFilter = document.getElementById('endDateFilter');
            const loadingLineIndicator = document.getElementById('loadingLineIndicator');
            const errorLineIndicator = document.getElementById('errorLineIndicator');
            const loadingDoughnutIndicator = document.getElementById('loadingDoughnutIndicator');
            const errorDoughnutIndicator = document.getElementById('errorDoughnutIndicator');
            const loadingPriorityIndicator = document.getElementById('loadingPriorityIndicator');
            const errorPriorityIndicator = document.getElementById('errorPriorityIndicator');
            
            // --- INICIALIZACIÓN DE GRÁFICOS ---
            let performanceBarChart;
            let categoryDoughnutChart;
            let priorityDoughnutChart; 

            // *** FUNCIÓN DE SANEAMIENTO DE JAVASCRIPT (CRUCIAL) ***
            // Debe replicar la lógica de PHP para asegurar la coincidencia del filtro.
            function sanitizeForUrl(text) {
                // 1. Minúsculas y reemplazar espacios
                text = text.toLowerCase().replace(/ /g, '_');
                
                // 2. Eliminar acentos de forma robusta
                // Normaliza (separa el acento del carácter) y luego quita todos los diacríticos
                text = text.normalize("NFD").replace(/[\u0300-\u036f]/g, ""); 
                
                // 3. Manejar ñ (reemplaza 'ñ' por 'n')
                text = text.replace(/ñ/g, 'n');
                
                return text;
            }
            
            
            // Función para manejar el clic en los gráficos circulares
            function handleDoughnutClick(chartInstance, event, type) {
                // Obtener el elemento clicado
                const activePoints = chartInstance.getElementsAtEventForMode(event, 'nearest', { intersect: true }, false);
                
                if (activePoints.length > 0) {
                    const firstPoint = activePoints[0];
                    // Obtener la etiqueta (ej: 'Urgente' o 'Logística')
                    const label = chartInstance.data.labels[firstPoint.index];
                    
                    let url = 'tareas_lista.php?';
                    // SANEAMIENTO: Usar la función unificada para generar el valor limpio de la URL
                    let filterValue = sanitizeForUrl(label);
                    
                    if (type === 'category') {
                        // Construir URL para Categoría: ?filtro_tipo=categoria&filtro_valor=logistica
                        url += 'filtro_tipo=categoria&filtro_valor=' + encodeURIComponent(filterValue);
                    } else if (type === 'priority') {
                        // Construir URL para Prioridad: ?filtro_tipo=prioridad&filtro_valor=urgente
                        url += 'filtro_tipo=prioridad&filtro_valor=' + encodeURIComponent(filterValue);
                    }

                    // Redirigir
                    window.location.href = url;
                }
            }


            // Gráfico 1: Rendimiento (BARRA)
            function initAdvancedChart() {
                const ctx = document.getElementById('performanceLineChart').getContext('2d');
                performanceBarChart = new Chart(ctx, {
                    type: 'bar', 
                    data: {
                        labels: [],
                        datasets: [
                            {
                                label: 'Ingreso (Tareas Activas)',
                                data: [],
                                backgroundColor: 'rgba(54, 162, 235, 0.8)',
                                borderColor: 'rgba(54, 162, 235, 1)',
                                borderWidth: 1,
                            },
                            {
                                label: 'Cierre (Tareas Verificadas)',
                                data: [],
                                backgroundColor: 'rgba(75, 192, 192, 0.8)',
                                borderColor: 'rgba(75, 192, 192, 1)',
                                borderWidth: 1,
                            }
                        ]
                    },
                    options: {
                        responsive: true,
                        maintainAspectRatio: false,
                        scales: {
                            x: { stacked: false, title: { display: true, text: 'Periodo' } },
                            y: { stacked: false, beginAtZero: true, title: { display: true, text: 'Número de Tareas' } }
                        },
                        plugins: { legend: { position: 'top' }, tooltip: { mode: 'index', intersect: false } }
                    }
                });
            }

            // Gráfico 2: Categorías (Dona) - AÑADIDO ONCLICK
            function initCategoryDoughnutChart() {
                const ctx = document.getElementById('categoryDoughnutChart').getContext('2d');
                categoryDoughnutChart = new Chart(ctx, {
                    type: 'doughnut',
                    data: {
                        labels: [],
                        datasets: [{ label: 'Tareas Activas', data: [], backgroundColor: [], hoverOffset: 4 }]
                    },
                    options: {
                        responsive: true,
                        maintainAspectRatio: false,
                        onClick: (e) => handleDoughnutClick(categoryDoughnutChart, e, 'category'), // *** MANEJADOR DE CLIC AÑADIDO ***
                        plugins: {
                            legend: { position: 'bottom' },
                            tooltip: { callbacks: { label: function(context) { 
                                return context.label + ': ' + context.parsed + ' (' + ((context.parsed / context.dataset.data.reduce((a, b) => a + b, 0)) * 100).toFixed(1) + '%)';
                            } } }
                        }
                    }
                });
            }

            // Gráfico 3: Prioridad (Dona) - AÑADIDO ONCLICK
            function initPriorityDoughnutChart() {
                const ctx = document.getElementById('priorityDoughnutChart').getContext('2d');
                priorityDoughnutChart = new Chart(ctx, {
                    type: 'doughnut',
                    data: {
                        labels: [],
                        datasets: [{ label: 'Tareas Activas por Prioridad', data: [], backgroundColor: [], hoverOffset: 4 }]
                    },
                    options: {
                        responsive: true,
                        maintainAspectRatio: false,
                        onClick: (e) => handleDoughnutClick(priorityDoughnutChart, e, 'priority'), // *** MANEJADOR DE CLIC AÑADIDO ***
                        plugins: {
                            legend: { position: 'bottom' },
                            tooltip: { callbacks: { label: function(context) { 
                                return context.label + ': ' + context.parsed + ' (' + ((context.parsed / context.dataset.data.reduce((a, b) => a + b, 0)) * 100).toFixed(1) + '%)';
                            } } }
                        }
                    }
                });
            }

            // --- FUNCIONES DE CARGA DE DATOS (MANTENIDAS) ---

            // Carga 1: Rendimiento (Barra)
            function loadAdvancedChartData() {
                loadingLineIndicator.style.display = 'block';
                errorLineIndicator.style.display = 'none';

                const groupBy = groupByFilter.value;
                const startDate = startDateFilter.value;
                const endDate = endDateFilter.value;
                
                const url = `fetch_advanced_stats.php?groupBy=${groupBy}&startDate=${startDate}&endDate=${endDate}`; 

                fetch(url)
                    .then(response => {
                        if (!response.ok) { throw new Error('Respuesta de red no fue ok'); }
                        return response.json();
                    })
                    .then(data => {
                        loadingLineIndicator.style.display = 'none';
                        if (data.success) {
                            performanceBarChart.data.labels = data.labels;
                            performanceBarChart.data.datasets[0].data = data.dataTotal;
                            performanceBarChart.data.datasets[1].data = data.dataCerradas;
                            performanceBarChart.update();
                        } else {
                            errorLineIndicator.textContent = data.message || 'No se pudieron obtener los datos.';
                            errorLineIndicator.style.display = 'block';
                        }
                    })
                    .catch(error => {
                        console.error('Error fetching line stats:', error);
                        loadingLineIndicator.style.display = 'none';
                        errorLineIndicator.textContent = 'Error de conexión o servidor.';
                        errorLineIndicator.style.display = 'block';
                    });
            }

            // Carga 2: Categorías (Dona)
            function loadCategoryDoughnutData() {
                loadingDoughnutIndicator.style.display = 'block';
                errorDoughnutIndicator.style.display = 'none';

                const url = `fetch_category_stats.php`; 

                fetch(url)
                    .then(response => {
                        if (!response.ok) { throw new Error('Respuesta de red no fue ok'); }
                        return response.json();
                    })
                    .then(data => {
                        loadingDoughnutIndicator.style.display = 'none';
                        if (data.success) {
                            categoryDoughnutChart.data.labels = data.labels;
                            categoryDoughnutChart.data.datasets[0].data = data.data;
                            categoryDoughnutChart.data.datasets[0].backgroundColor = data.colors;
                            categoryDoughnutChart.update();
                        } else {
                            errorDoughnutIndicator.textContent = data.message || 'No se pudieron obtener los datos de categorías.';
                            errorDoughnutIndicator.style.display = 'block';
                        }
                    })
                    .catch(error => {
                        console.error('Error fetching doughnut stats:', error);
                        loadingDoughnutIndicator.style.display = 'none';
                        errorDoughnutIndicator.textContent = 'Error de conexión o servidor.';
                        errorDoughnutIndicator.style.display = 'block';
                    });
            }

            // Carga 3: Prioridad (Dona)
            function loadPriorityDoughnutData() {
                loadingPriorityIndicator.style.display = 'block';
                errorPriorityIndicator.style.display = 'none';

                const url = `fetch_priority_stats.php`; 

                fetch(url)
                    .then(response => {
                        if (!response.ok) { throw new Error('Respuesta de red no fue ok'); }
                        return response.json();
                    })
                    .then(data => {
                        loadingPriorityIndicator.style.display = 'none';
                        if (data.success) {
                            priorityDoughnutChart.data.labels = data.labels;
                            priorityDoughnutChart.data.datasets[0].data = data.data;
                            priorityDoughnutChart.data.datasets[0].backgroundColor = data.colors;
                            priorityDoughnutChart.update();
                        } else {
                            errorPriorityIndicator.textContent = data.message || 'No se pudieron obtener los datos de prioridad.';
                            errorPriorityIndicator.style.display = 'block';
                        }
                    })
                    .catch(error => {
                        console.error('Error fetching priority stats:', error);
                        loadingPriorityIndicator.style.display = 'none';
                        errorPriorityIndicator.textContent = 'Error de conexión o servidor.';
                        errorPriorityIndicator.style.display = 'block';
                    });
            }


            // --- INICIALIZACIÓN Y LISTENERS ---

            initAdvancedChart();
            initCategoryDoughnutChart();
            initPriorityDoughnutChart(); 
            
            loadAdvancedChartData(); 
            loadCategoryDoughnutData();
            loadPriorityDoughnutData(); 
            
            applyLineFiltersBtn.addEventListener('click', loadAdvancedChartData);
        });
    </script>
    <div class="toast-container position-fixed bottom-0 end-0 p-3" id="notificationToastContainer"></div>
</body>
</html>