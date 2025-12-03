<?php
// Archivo: admin_remitos.php
session_start();
include 'conexion.php'; 
// 🛑 PASO CRÍTICO 1: DEBE INCLUIR ESTE ARCHIVO
include 'funciones_permisos.php'; 
// include 'navbar.php'; // Si tienes tu navbar aquí

// 1. Verificar acceso y permiso para ver Remitos
// 🛑 PASO CRÍTICO 2: USAR LA FUNCIÓN tiene_permiso()
if (!isset($_SESSION['usuario_id']) || !tiene_permiso('admin_remitos', $pdo)) {
    // Si no tiene el permiso, lo enviamos al dashboard.
    header("Location: dashboard.php");
    exit();
}

// --- Leer y Validar Parámetros GET para Filtros y Búsqueda (Para la tabla) ---
$filtro_usuario = $_GET['usuario'] ?? '';
$filtro_fecha_desde = $_GET['fecha_desde'] ?? '';
$filtro_fecha_hasta = $_GET['fecha_hasta'] ?? '';
$filtro_busqueda = trim($_GET['buscar'] ?? ''); // Para el buscador PHP

// *** INICIO DE LA CORRECCIÓN ***
$error_msg = ''; // <-- INICIALIZAR LA VARIABLE DE ERROR AQUÍ
// *** FIN DE LA CORRECCIÓN ***

// Validar fechas (formato YYYY-MM-DD)
if ($filtro_fecha_desde && !preg_match("/^[0-9]{4}-(0[1-9]|1[0-2])-(0[1-9]|[1-2][0-9]|3[0-1])$/", $filtro_fecha_desde)) {
    $filtro_fecha_desde = ''; // Ignorar fecha inválida
}
if ($filtro_fecha_hasta && !preg_match("/^[0-9]{4}-(0[1-9]|1[0-2])-(0[1-9]|[1-2][0-9]|3[0-1])$/", $filtro_fecha_hasta)) {
    $filtro_fecha_hasta = ''; // Ignorar fecha inválida
}

// --- Construir Consulta SQL con Filtros (Para la tabla) ---
try {
    $sql_base = "SELECT
                    adj.id_adjunto,
                    adj.id_tarea,
                    adj.nombre_archivo,
                    adj.ruta_archivo,
                    adj.fecha_subida,
                    adj.descripcion_compra,
                    adj.precio_total,
                    adj.numero_compra,
                    adj.estado_conciliacion, 
                    t.titulo AS tarea_titulo,
                    u.nombre_completo AS subido_por,
                    u.id_usuario
                 FROM
                    adjuntos_tarea AS adj
                 JOIN
                    tareas AS t ON adj.id_tarea = t.id_tarea
                 JOIN
                    usuarios AS u ON adj.id_usuario_subida = u.id_usuario
                 WHERE
                    adj.tipo_adjunto = 'remito'";

    $where_clauses = [];
    $params = [];

    // Añadir filtros a la consulta
    if (!empty($filtro_usuario) && is_numeric($filtro_usuario)) {
        $where_clauses[] = "adj.id_usuario_subida = :id_usuario";
        $params[':id_usuario'] = (int)$filtro_usuario;
    }
    if (!empty($filtro_fecha_desde)) {
        $where_clauses[] = "DATE(adj.fecha_subida) >= :fecha_desde";
        $params[':fecha_desde'] = $filtro_fecha_desde;
    }
    if (!empty($filtro_fecha_hasta)) {
        $where_clauses[] = "DATE(adj.fecha_subida) <= :fecha_hasta";
        $params[':fecha_hasta'] = $filtro_fecha_hasta;
    }
    // Añadir filtro de búsqueda (busca en varios campos)
    if (!empty($filtro_busqueda)) {
        $where_clauses[] = "(adj.nombre_archivo LIKE :buscar OR
                             adj.descripcion_compra LIKE :buscar OR
                             adj.numero_compra LIKE :buscar OR
                             t.titulo LIKE :buscar OR
                             u.nombre_completo LIKE :buscar)";
        $params[':buscar'] = '%' . $filtro_busqueda . '%';
    }


    if (!empty($where_clauses)) {
        $sql = $sql_base . " AND " . implode(" AND ", $where_clauses);
    } else {
        $sql = $sql_base;
    }

    $sql .= " ORDER BY adj.fecha_subida DESC"; // Ordenar siempre

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $remitos = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // 🛑 SOLUCIÓN: Inicializar la variable $total_gastos antes de usarla
    $total_gastos = 0; // Agrega esta línea

    // Calcular total SOLO de los remitos filtrados
    foreach ($remitos as $remito) {
        if (!empty($remito['precio_total']) && is_numeric($remito['precio_total'])) {
            $total_gastos += (float)$remito['precio_total'];
        }
    }
    
    // Conteo de pendientes (sin filtros de tabla)
    $sql_pendientes = "SELECT COUNT(id_adjunto) FROM adjuntos_tarea WHERE tipo_adjunto = 'remito' AND estado_conciliacion = 'pendiente'";
    $remitos_pendientes = $pdo->query($sql_pendientes)->fetchColumn();
    
    // Conteo de rechazados (sin filtros de tabla)
    $sql_rechazados = "SELECT COUNT(id_adjunto) FROM adjuntos_tarea WHERE tipo_adjunto = 'remito' AND estado_conciliacion = 'rechazado'";
    $remitos_rechazados = $pdo->query($sql_rechazados)->fetchColumn();
    
    
    // Obtener la URL base para el fetch de estadísticas
    $current_url_params = http_build_query([
        'usuario' => $filtro_usuario,
        'fecha_desde' => $filtro_fecha_desde,
        'fecha_hasta' => $filtro_fecha_hasta,
        'buscar' => $filtro_busqueda
    ]);
    $stats_fetch_url = "fetch_remitos_stats.php?" . $current_url_params;


} catch (PDOException $e) {
    $error_msg = "Error al cargar la lista de remitos: " . $e->getMessage();
    error_log($error_msg);
    $stats_fetch_url = "";
    $remitos = []; // Asegurarse de que $remitos sea un array vacío en caso de error
    $total_gastos = 0; // Asegurarse de que $total_gastos sea 0 en caso de error
    $remitos_pendientes = 0; // Asegurarse de que $remitos_pendientes sea 0 en caso de error
    $remitos_rechazados = 0; // Asegurarse de que $remitos_rechazados sea 0 en caso de error
}

// Determinar la URL absoluta para el script AJAX
$protocolo = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? "https" : "http");
$host = $_SERVER['HTTP_HOST'];
$ruta_base = rtrim(dirname($_SERVER['PHP_SELF']), '/\\');
$conciliar_url_base = $protocolo . '://' . $host . $ruta_base . '/admin_remitos_conciliar.php';

// Incluir navbar DESPUÉS de la lógica principal
include 'navbar.php';
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Historial de Remitos y Facturas</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.3/dist/chart.umd.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf/2.5.1/jspdf.umd.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf-autotable/3.5.25/jspdf.plugin.autotable.min.js"></script>
    <style>
        .table th { white-space: nowrap; }
        .table td a { text-decoration: none; }
        .table td a:hover { text-decoration: underline; }
        .total-gastos { font-size: 1.2rem; font-weight: bold; }
        .chart-container { position: relative; height: 300px; }
        .chart-placeholder { height: 100%; display: flex; align-items: center; justify-content: center; background-color: #f8f9fa; border-radius: 0.3rem; }
        /* CSS para el ajuste de margen entre tabla y gráficos */
        .spacing-mt-lg {
            margin-top: 1.5rem !important; /* Margen superior moderado */
        }
        /* Estilo para el widget de pendientes */
        .widget-pendiente {
            background-color: #ffcccc; /* Rojo claro para alarma */
            color: #dc3545;
            border: 1px solid #dc3545;
            padding: 1rem;
            border-radius: 0.5rem;
            text-align: center;
        }
        .widget-rechazado {
            background-color: #fce8e8;
            color: #dc3545;
            border: 1px solid #dc3545;
            padding: 1rem;
            border-radius: 0.5rem;
            text-align: center;
        }
        
        /* ******** INICIO MODIFICACIÓN: ESTILO PARA LA TABLA CON SCROLL ******** */
        .table-scroll-container {
            max-height: 600px; /* Define la altura máxima de la tabla (puedes cambiar este valor) */
            overflow-y: auto;  /* Agrega el scroll vertical */
        }
        /* ******** FIN MODIFICACIÓN ******** */
    </style>
</head>
<body>

<div class="container mt-4">
    <h1 class="mb-4"><i class="fas fa-file-invoice-dollar me-2 text-success"></i> Historial de Remitos y Facturas</h1>

    <p class="text-muted mb-4">Listado filtrable y searchable de archivos marcados como "Remito" o "Factura".</p>

    <?php if ($error_msg): ?>
        <div class="alert alert-danger" role="alert">
            <?php echo htmlspecialchars($error_msg); ?>
        </div>
    <?php endif; ?>

    <div class="row align-items-center mb-4">
        <div class="col-md-4">
            <div class="widget-pendiente">
                <h5 class="mb-0"><i class="fas fa-exclamation-triangle me-2"></i> Pendientes: <span class="fw-bold fs-4"><?php echo $remitos_pendientes; ?></span></h5>
                <small class="d-block">Documentos esperando verificación.</small>
            </div>
        </div>
        <div class="col-md-4">
             <div class="widget-rechazado">
                <h5 class="mb-0"><i class="fas fa-times-circle me-2"></i> Rechazados: <span class="fw-bold fs-4"><?php echo $remitos_rechazados; ?></span></h5>
                <small class="d-block">Documentos con errores que deben corregirse.</small>
            </div>
        </div>
        <div class="col-md-4 text-end">
             <span class="badge bg-dark p-2 total-gastos">
                TOTAL GASTADO (Filtrado): $ <?php 
                    // Lógica de formato de precio aplicada al total
                    if ($total_gastos == floor($total_gastos)) {
                        echo number_format($total_gastos, 0, ',', '.');
                    } else {
                        echo number_format($total_gastos, 2, ',', '.');
                    }
                ?>
             </span>
        </div>
    </div>


    <h3 class="mb-3 mt-4">Historial y Filtros</h3>
    
    <div class="row mb-3">
        <div class="col-md-6">
            <input type="text" class="form-control" id="tableSearchInput" placeholder="Buscar remito por archivo, descripción o tarea...">
        </div>
    </div>
    <div class="card shadow-sm mb-4">
        <div class="card-body bg-light">
            <form action="admin_remitos.php" method="GET" class="row g-3 align-items-end">
                <div class="col-md-3">
                    <label for="buscar" class="form-label">Buscar (Filtro Avanzado)</label>
                    <input type="text" class="form-control" id="buscar" name="buscar" placeholder="Nombre, Tarea, Usuario..." value="<?php echo htmlspecialchars($filtro_busqueda); ?>">
                </div>
                <div class="col-md-3">
                    <label for="usuario" class="form-label">Subido por</label>
                    <select class="form-select" id="usuario" name="usuario">
                        <option value="">-- Todos --</option>
                        <?php foreach ($usuarios_filtro as $user): ?>
                            <option value="<?php echo $user['id_usuario']; ?>" <?php echo ($filtro_usuario == $user['id_usuario']) ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($user['nombre_completo']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-2">
                    <label for="fecha_desde" class="form-label">Desde</label>
                    <input type="date" class="form-control" id="fecha_desde" name="fecha_desde" value="<?php echo htmlspecialchars($filtro_fecha_desde); ?>">
                </div>
                <div class="col-md-2">
                    <label for="fecha_hasta" class="form-label">Hasta</label>
                    <input type="date" class="form-control" id="fecha_hasta" name="fecha_hasta" value="<?php echo htmlspecialchars($filtro_fecha_hasta); ?>">
                </div>
                <div class="col-md-2 d-flex">
                    <button type="submit" class="btn btn-primary me-2 flex-grow-1"><i class="fas fa-filter"></i> Filtrar</button>
                    <a href="admin_remitos.php" class="btn btn-outline-secondary" title="Limpiar Filtros"><i class="fas fa-times"></i></a>
                </div>
            </form>
        </div>
    </div>
    
    <?php if (!empty($remitos)): ?>
    <div class="d-flex justify-content-start gap-3 mb-3">
        <button id="markVerifiedBtn" class="btn btn-success btn-sm" disabled><i class="fas fa-check-double me-1"></i> Marcar como Verificado</button>
        <button id="markPendingBtn" class="btn btn-warning btn-sm" disabled><i class="fas fa-undo me-1"></i> Marcar como Pendiente</button>
        <button id="markRejectedBtn" class="btn btn-danger btn-sm" disabled><i class="fas fa-times me-1"></i> Marcar como Rechazado</button>
        <button id="deselectAllBtn" class="btn btn-secondary btn-sm" disabled><i class="fas fa-times-circle me-1"></i> Deseleccionar</button>
    </div>
    <?php endif; ?>


    <?php if (empty($remitos) && !$error_msg): ?>
        <div class="alert alert-info text-center" role="alert">
            <i class="fas fa-search me-2"></i> No se encontraron remitos o facturas con los filtros aplicados.
        </div>
    <?php elseif (!empty($remitos)): ?>
        
        <div class="card shadow-sm">
            <div class="card-body">
                <div class="table-responsive table-scroll-container">
                <table class="table table-hover table-striped align-middle" id="remitosTable">
                        <thead class="table-dark">
                            <tr>
                                <th style="width: 20px;"><input type="checkbox" id="selectAllCheck"></th> <th><i class="fas fa-calendar-alt"></i> Fecha Subida</th>
                                <th><i class="fas fa-file-alt"></i> Archivo</th>
                                <th class="text-center"><i class="fas fa-clipboard-list"></i> Estado</th> <th><i class="fas fa-info-circle"></i> Descripción</th>
                                <th class="text-end"><i class="fas fa-dollar-sign"></i> Precio</th>
                                <th><i class="fas fa-hashtag"></i> N° Compra</th>
                                <th><i class="fas fa-user"></i> Subido por</th>
                                <th><i class="fas fa-link"></i> Tarea</th>
                                <th class="text-center"><i class="fas fa-cogs"></i> Acción</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($remitos as $remito): ?>
                            <tr class="remito-row" data-remito-id="<?php echo $remito['id_adjunto']; ?>">
                                <td><input type="checkbox" class="remito-check" value="<?php echo $remito['id_adjunto']; ?>" data-status="<?php echo $remito['estado_conciliacion']; ?>"></td> <td class="text-nowrap"><?php echo date('d/m/Y H:i', strtotime($remito['fecha_subida'])); ?></td>
                                <td class="fw-bold file-name">
                                    <i class="fas fa-file-invoice text-success me-1"></i>
                                    <?php echo htmlspecialchars($remito['nombre_archivo']); ?>
                                </td>
                                <td class="text-center">
                                    <?php
                                        $estado = $remito['estado_conciliacion'];
                                        $badge_class = match($estado) {
                                            'verificado' => 'bg-success',
                                            'rechazado' => 'bg-danger',
                                            default => 'bg-warning text-dark',
                                        };
                                        $estado_text = match($estado) {
                                            'verificado' => 'Verificado',
                                            'rechazado' => 'Rechazado',
                                            default => 'Pendiente',
                                        };
                                    ?>
                                    <span class="badge <?php echo $badge_class; ?> status-badge" data-status="<?php echo $estado; ?>"><?php echo $estado_text; ?></span>
                                </td>
                                <td class="description"><?php echo htmlspecialchars($remito['descripcion_compra'] ?? '-'); ?></td>
                                <td class="text-end price">
                                    <?php 
                                        // ******** INICIO MODIFICACIÓN: FORMATO DE PRECIO ********
                                        if ($remito['precio_total'] !== null) {
                                            $precio = (float)$remito['precio_total'];
                                            // Si el precio es igual a su versión de entero (ej: 3000.00 == 3000)
                                            if ($precio == floor($precio)) {
                                                echo '$ ' . number_format($precio, 0, ',', '.');
                                            } else {
                                                // Si tiene decimales (ej: 3000.84)
                                                echo '$ ' . number_format($precio, 2, ',', '.');
                                            }
                                        } else {
                                            echo '-';
                                        }
                                        // ******** FIN MODIFICACIÓN ********
                                    ?>
                                </td>
                                <td class="purchase-number"><?php echo htmlspecialchars($remito['numero_compra'] ?? '-'); ?></td>
                                <td class="uploader-name"><?php echo htmlspecialchars($remito['subido_por']); ?></td>
                                <td class="task-link">
                                    <a href="tarea_ver.php?id=<?php echo $remito['id_tarea']; ?>#actualizaciones" title="Ver Tarea #<?php echo $remito['id_tarea']; ?>">
                                        <?php echo htmlspecialchars(substr($remito['tarea_titulo'], 0, 30) . (strlen($remito['tarea_titulo']) > 30 ? '...' : '')); ?>
                                    </a>
                                </td>
                                <td class="text-center text-nowrap">
                                    <a href="ver_adjunto.php?id=<?php echo $remito['id_adjunto']; ?>" 
                                       target="_blank" 
                                       class="btn btn-sm btn-outline-info me-1" 
                                       title="Ver Archivo">
                                        <i class="fas fa-eye"></i> Ver
                                    </a>
                                    <a href="descargar_adjunto.php?id=<?php echo $remito['id_adjunto']; ?>" 
                                       class="btn btn-sm btn-outline-success" 
                                       title="Descargar">
                                        <i class="fas fa-download"></i>
                                    </a>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    <?php endif; ?>

</div>

<div class="modal fade" id="confirmConciliationModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-start border-5" id="conciliationModalContent">
            <div class="modal-header bg-warning text-dark" id="conciliationModalHeader">
                <h5 class="modal-title"><i class="fas fa-exclamation-triangle me-2"></i> Confirmar Acción Masiva</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div id="modalBodyContent">
                    <p class="lead">¿Está seguro de aplicar el estado <strong id="actionStatusText">PENDIENTE</strong> a <span class="fw-bold" id="actionCountText">0</span> remitos seleccionados?</p>
                    <div class="alert alert-info small mt-3">
                        <i class="fas fa-info-circle me-1"></i> Esta acción no se puede deshacer fácilmente y afectará el conteo de pendientes.
                    </div>
                </div>
                </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                <button type="button" class="btn btn-primary" id="confirmConciliationActionBtn">
                    <i class="fas fa-check-circle me-1"></i> Confirmar
                </button>
            </div>
        </div>
    </div>
</div>
<div class="container spacing-mt-lg"> <div class="d-flex justify-content-between align-items-center mb-3">
        <h3 class="mb-0">Análisis Estadístico de Gastos</h3>

        <div class="d-flex gap-2">
            <button id="exportExcelBtn" class="btn btn-sm btn-outline-success" title="Exportar a Excel">
                <i class="fas fa-file-excel me-1"></i> Exportar
            </button>
            <button id="exportPdfBtn" class="btn btn-sm btn-outline-danger" title="Descargar como PDF">
                <i class="fas fa-file-pdf me-1"></i> PDF
            </button>
        </div>
    </div>
    
    <div class="card shadow-sm mb-4">
        <div class="card-body bg-light p-3">
            <form id="stats-filter-form" class="row g-2 align-items-center">
                 <div class="col-auto">
                    <label for="group-by" class="col-form-label col-form-label-sm">Agrupar por:</label>
                 </div>
                 <div class="col-md-3">
                     <select id="group-by" class="form-select form-select-sm">
                        <option value="user" selected>Usuario (Por defecto)</option>
                        <option value="category">Categoría de Tarea</option>
                        <option value="week">Semana</option>
                        <option value="month">Mes</option>
                        <option value="quarter">Semestre (Trimestre)</option>
                        <option value="year">Año</option>
                     </select>
                 </div>
                 <div class="col-auto">
                    <label for="date-from" class="col-form-label col-form-label-sm">Desde:</label>
                 </div>
                 <div class="col-md-2">
                     <input type="date" id="date-from" class="form-control form-control-sm">
                 </div>
                 <div class="col-auto">
                    <label for="date-to" class="col-form-label col-form-label-sm">Hasta:</label>
                 </div>
                 <div class="col-md-2">
                     <input type="date" id="date-to" class="form-control form-control-sm">
                 </div>
                 <div class="col-auto">
                     <button type="button" id="applyStatsFilterBtn" class="btn btn-sm btn-primary">
                        <i class="fas fa-chart-line me-1"></i> Actualizar Gráficos
                     </button>
                 </div>
            </form>
        </div>
    </div>
    
    <div class="row mb-5">
        <div class="col-lg-6">
            <div class="card shadow h-100">
                <div class="card-header bg-primary text-white">
                    <i class="fas fa-chart-bar me-1"></i> Gasto Total por Agrupación
                </div>
                <div class="card-body">
                     <div class="chart-container">
                        <canvas id="spendingBarChart"></canvas>
                        <div id="loadingSpending" class="chart-placeholder" style="display: none;"><div class="spinner-border text-primary" role="status"></div></div>
                        <div id="noDataSpending" class="chart-placeholder alert alert-info" style="display: none;">No hay datos de gastos para mostrar.</div>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-lg-6">
            <div class="card shadow h-100">
                <div class="card-header bg-success text-white">
                    <i class="fas fa-chart-pie me-1"></i> Cantidad de Remitos/Facturas por Agrupación
                </div>
                <div class="card-body">
                     <div class="chart-container">
                        <canvas id="countDoughnutChart"></canvas>
                        <div id="loadingCount" class="chart-placeholder" style="display: none;"><div class="spinner-border text-success" role="status"></div></div>
                        <div id="noDataCount" class="chart-placeholder alert alert-info" style="display: none;">No hay datos de remitos para mostrar.</div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<div class="toast-container position-fixed bottom-0 end-0 p-3" id="notificationToastContainer" style="z-index: 1080;"></div>

<script>
    // --- Lógica Chart.js ---
    let spendingBarChart;
    let countDoughnutChart;
    let currentStatsData = { labels: [], spent_data: [], count_data: [] };
    
    // URL AJAX para la función de conciliación masiva
    const CONCILIAR_URL = '<?php echo $conciliar_url_base; ?>';
    
    let pendingActionStatus = null; // Variable global para almacenar el estado de la acción pendiente

    // Función para decodificar entidades HTML como &quot; a "
    function decodeHtmlEntities(text) {
        if (typeof text !== 'string') return text;
        const textArea = document.createElement('textarea');
        textArea.innerHTML = text;
        return textArea.value;
    }
    
    // Funcion para mostrar Toasts (Bootstrap)
    function showToast(title, message, type = 'success') {
        let i = '', c = '', delay = 4000;
        if (type === 'success') { i = '<i class="fas fa-check-circle me-2"></i>'; c = 'bg-success text-white'; delay = 4000; }
        else if (type === 'warning') { i = '<i class="fas fa-exclamation-triangle me-2"></i>'; c = 'bg-warning text-dark'; delay = 6000; }
        else if (type === 'danger') { i = '<i class="fas fa-times-circle me-2"></i>'; c = 'bg-danger text-white'; delay = 8000; }
        else { i = '<i class="fas fa-info-circle me-2"></i>'; c = 'bg-info text-white'; }

        const t = `
            <div class="toast align-items-center ${c} border-0" role="alert" aria-live="assertive" aria-atomic="true" data-bs-delay="${delay}">
                <div class="d-flex">
                    <div class="toast-body"><strong>${i}${title}</strong><br>${message}</div>
                    <button type="button" class="btn-close me-2 m-auto ${c.includes('text-white') ? 'btn-close-white' : ''}" data-bs-dismiss="toast"></button>
                </div>
            </div>`;
        const tc = document.getElementById('notificationToastContainer');
        if (tc) {
            tc.insertAdjacentHTML('beforeend', t);
            const nt = tc.lastElementChild;
            const tb = bootstrap.Toast.getOrCreateInstance(nt);
            tb.show();
        }
    }

    // Función para obtener los parámetros de filtro del formulario de la TABLA
    function getTableFilterParams() {
        const params = new URLSearchParams(window.location.search);
        let query = '';
        if (params.get('usuario')) query += `&usuario=${params.get('usuario')}`;
        if (params.get('fecha_desde')) query += `&fecha_desde=${params.get('fecha_desde')}`;
        if (params.get('fecha_hasta')) query += `&fecha_hasta=${params.get('fecha_hasta')}`;
        if (params.get('buscar')) query += `&buscar=${params.get('buscar')}`;
        return query;
    }

    // Función de carga y filtrado de gráficos
    function loadStats() {
        const groupBy = document.getElementById('group-by').value;
        const dateFrom = document.getElementById('date-from').value;
        const dateTo = document.getElementById('date-to').value;
        
        let params = `groupBy=${groupBy}`;

        // Incluimos los filtros de la tabla solo si la agrupación es por usuario (user) o categoría
        if (groupBy === 'user' || groupBy === 'category') {
            params += getTableFilterParams();
        } 
        
        // Si se agrupa por tiempo (week, month, etc.), usamos el rango de fecha del filtro avanzado
        if ((groupBy !== 'user' && groupBy !== 'category') && dateFrom && dateTo) {
             params += `&date_from=${dateFrom}&date_to=${dateTo}`;
        }
        
        const url = `fetch_remitos_stats.php?${params}`;

        document.getElementById('loadingSpending').style.display = 'flex';
        document.getElementById('loadingCount').style.display = 'flex';
        document.getElementById('noDataSpending').style.display = 'none';
        document.getElementById('noDataCount').style.display = 'none';

        fetch(url)
            .then(response => response.json())
            .then(data => {
                document.getElementById('loadingSpending').style.display = 'none';
                document.getElementById('loadingCount').style.display = 'none';

                if (data.success && data.labels && data.labels.length > 0) {
                    
                    currentStatsData.labels = data.labels;
                    currentStatsData.spent_data = data.spent_data;
                    currentStatsData.count_data = data.count_data;

                    initSpendingBarChart(data.labels, data.spent_data);
                    initCountDoughnutChart(data.labels, data.count_data);
                    
                } else {
                    document.getElementById('noDataSpending').style.display = 'flex';
                    document.getElementById('noDataCount').style.display = 'flex';
                    if (spendingBarChart) spendingBarChart.destroy();
                    if (countDoughnutChart) countDoughnutChart.destroy();
                    
                    currentStatsData = { labels: [], spent_data: [], count_data: [] };
                }
            })
            .catch(error => {
                console.error('Error al cargar estadísticas:', error);
                document.getElementById('loadingSpending').style.display = 'none';
                document.getElementById('loadingCount').style.display = 'none';
                document.getElementById('noDataSpending').style.display = 'flex';
                document.getElementById('noDataCount').style.display = 'flex';
                document.getElementById('noDataSpending').textContent = 'Error de conexión o servidor.';
                document.getElementById('noDataCount').textContent = 'Error de conexión o servidor.';
            });
    }

    function initSpendingBarChart(labels, data) {
        const ctx = document.getElementById('spendingBarChart').getContext('2d');
        if (spendingBarChart) spendingBarChart.destroy();
        spendingBarChart = new Chart(ctx, {
            type: 'bar',
            data: {
                labels: labels.map(decodeHtmlEntities), 
                datasets: [{
                    label: 'Total Gastado (CLP/USD/ARS)',
                    data: data,
                    backgroundColor: 'rgba(13, 110, 253, 0.7)',
                    borderColor: 'rgba(13, 110, 253, 1)',
                    borderWidth: 1
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                indexAxis: 'y',
                scales: {
                    x: {
                        beginAtZero: true,
                        title: { display: true, text: 'Monto Total' }
                    }
                },
                plugins: {
                    legend: { display: false },
                    tooltip: {
                        callbacks: {
                            label: function(context) {
                                let label = context.dataset.label || '';
                                if (label) label += ': ';
                                if (context.parsed.x !== null) label += '$ ' .padStart(0, 1) + context.parsed.x.toFixed(2).replace(/\B(?=(\d{3})+(?!\d))/g, ".");
                                return label;
                            }
                        }
                    }
                }
            }
        });
    }

    function initCountDoughnutChart(labels, data) {
        const ctx = document.getElementById('countDoughnutChart').getContext('2d');
        if (countDoughnutChart) countDoughnutChart.destroy();
        
        const baseColors = ['#198754', '#6f42c1', '#ffc107', '#dc3545', '#0dcaf0', '#6c757d'];
        const backgroundColors = labels.map((_, i) => baseColors[i % baseColors.length]);
        
        countDoughnutChart = new Chart(ctx, {
            type: 'doughnut',
            data: {
                labels: labels.map(decodeHtmlEntities),
                datasets: [{
                    label: 'Cantidad de Remitos/Facturas',
                    data: data,
                    backgroundColor: backgroundColors,
                    hoverOffset: 4
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: { position: 'bottom' },
                    tooltip: {
                        callbacks: {
                            label: function(context) {
                                let label = context.label || '';
                                let total = context.dataset.data.reduce((a, b) => a + b, 0);
                                let value = context.parsed || 0;
                                let percentage = (value / total * 100).toFixed(1) + '%';
                                return `${label}: ${value} (${percentage})`;
                            }
                        }
                    }
                }
            }
        });
    }

    // --- Lógica de Conciliación Masiva ---

    function updateConciliationButtons() {
        // Recopila solo los remitos VISIBLES en la tabla
        const visibleRows = Array.from(document.querySelectorAll('#remitosTable tbody tr.remito-row')).filter(tr => tr.style.display !== 'none');
        const checkedBoxes = visibleRows.filter(tr => tr.querySelector('.remito-check').checked);
        const checkedCount = checkedBoxes.length;
        const totalCount = document.querySelectorAll('.remito-check').length;
        
        // 1. Determinar si hay remitos PENDIENTES o RECHAZADOS (para Mark Verified)
        const canMarkVerified = checkedBoxes.some(tr => {
            const status = tr.querySelector('.remito-check').getAttribute('data-status');
            return status !== 'verificado';
        });

        // 2. Determinar si hay remitos NO verificado seleccionados (para Mark Pending/Rejected)
        const canMarkNonFinal = checkedBoxes.some(tr => {
            const status = tr.querySelector('.remito-check').getAttribute('data-status');
            return status !== 'verificado';
        });

        // Habilitar/Deshabilitar botones de acción masiva
        document.getElementById('markVerifiedBtn').disabled = checkedCount === 0 || !canMarkVerified; 
        document.getElementById('markPendingBtn').disabled = checkedCount === 0 || !canMarkNonFinal; 
        document.getElementById('markRejectedBtn').disabled = checkedCount === 0 || !canMarkNonFinal; 
        document.getElementById('deselectAllBtn').disabled = checkedCount === 0;
        
        // Lógica del checkbox maestro
        document.getElementById('selectAllCheck').checked = (checkedCount > 0 && checkedCount === totalCount);
        document.getElementById('selectAllCheck').indeterminate = (checkedCount > 0 && checkedCount < totalCount);
    }
    
    function conciliateRemitos(estado) {
        const checkedBoxes = document.querySelectorAll('.remito-check:checked');
        
        // 1. FILTRAR: Solo enviar IDs que sean válidos para la acción
        const ids = Array.from(checkedBoxes)
            .filter(cb => {
                const status = cb.getAttribute('data-status');
                
                // Si la acción NO es 'verificado', no se pueden revertir los 'verificado'.
                if (estado !== 'verificado' && status === 'verificado') {
                    return false;
                }
                // Si la acción es 'verificado', no se incluyen los que YA son 'verificado'.
                if (estado === 'verificado' && status === 'verificado') {
                    return false; 
                }
                
                return true; 
            })
            .map(cb => cb.value);

        
        if (ids.length === 0) {
            showToast('Acción Innecesaria', 'No hay remitos seleccionados que puedan ser actualizados a este estado.', 'warning');
            return;
        }

        // Serializa los IDs para enviar en el cuerpo
        const data = new URLSearchParams();
        data.append('estado', estado);
        ids.forEach(id => data.append('ids[]', id)); // Envía los IDs como un array

        fetch(CONCILIAR_URL, { 
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: data
        })
        .then(response => {
             if (!response.ok) {
                 throw new Error(`Error de servidor: ${response.status} ${response.statusText}`);
             }
             return response.json();
        })
        .then(data => {
            if (data.success) {
                // ÉXITO: Mostrar Toast y recargar después de 1.5 segundos
                showToast('Éxito de Conciliación', data.message, 'success');
                setTimeout(() => window.location.reload(), 1500); 
            } else {
                // FALLO LÓGICO: Mostrar Toast de error
                showToast('Error al Conciliar', data.message, 'danger');
            }
        })
        .catch(error => {
            // FALLO DE RED: Mostrar Toast de error de red
            console.error('Error de conexión en conciliación:', error);
            showToast('Error de Red', 'Fallo de conexión al servidor. Revise la consola para detalles.', 'danger');
        });
    }

    // Función que muestra el modal de Bootstrap
    function showConciliationModal(estado) {
        const checkedBoxes = document.querySelectorAll('.remito-check:checked');
        const checkedCount = checkedBoxes.length;
        const modalElement = document.getElementById('confirmConciliationModal');
        const modal = new bootstrap.Modal(modalElement);
        
        if (checkedCount === 0) {
             showToast('Atención', 'Seleccione al menos un remito para actualizar.', 'warning');
             return;
        }

        const statusText = estado === 'verificado' ? 'VERIFICADO' : (estado === 'pendiente' ? 'PENDIENTE' : 'RECHAZADO');
        const modalHeader = document.getElementById('conciliationModalHeader');
        const confirmButton = document.getElementById('confirmConciliationActionBtn');
        const modalBodyContent = document.getElementById('modalBodyContent');
        
        let validIdsCount = checkedCount;
        let isActionBlocked = false;
        let blockedMessage = '';
        
        // --- Validación Previa de Lógica de Negocio ---
        const checkedStatuses = Array.from(checkedBoxes).map(cb => cb.getAttribute('data-status'));
        const verifiedCount = checkedStatuses.filter(s => s === 'verificado').length;
        const rejectedCount = checkedStatuses.filter(s => s === 'rechazado').length;
        
        
        if (estado === 'pendiente' || estado === 'rechazado') {
            // Bloqueo 1: Irreversible de VERIFICADO a PENDIENTE/RECHAZADO
            if (verifiedCount > 0) {
                validIdsCount = checkedCount - verifiedCount;
                if (validIdsCount === 0) {
                     // Caso 1: Todos son verificados y se intenta revertir
                     isActionBlocked = true;
                     blockedMessage = `<p class="text-danger fw-bold"><i class="fas fa-lock me-1"></i> ACCIÓN BLOQUEADA.</p><p>No se puede revertir el estado de los ${checkedCount} remitos. Ya están en estado VERIFICADO (Final).</p>`;
                } else {
                     // Caso 2: Hay una mezcla
                     blockedMessage = `<p class="text-warning fw-bold"><i class="fas fa-exclamation-triangle me-1"></i> Advertencia: ${verifiedCount} remito(s) Verificado(s) se omitirán.</p><p>Solo se marcarán como ${statusText} los ${validIdsCount} remitos restantes.</p>`;
                }
            }
        }
        
        if (estado === 'verificado') {
             // Bloqueo 2: Flujo: RECHAZADO NO puede saltar a VERIFICADO.
             if (rejectedCount > 0) {
                 isActionBlocked = true;
                 blockedMessage = `<p class="text-danger fw-bold"><i class="fas fa-exclamation-circle me-1"></i> ERROR DE FLUJO.</p><p>Los remitos RECHAZADOS (${rejectedCount}) no pueden saltar directamente a VERIFICADO. Primero deben ser marcados como PENDIENTE.</p>`;
             } 
             // Bloqueo 3: Redundancia
             else if (verifiedCount === checkedCount) {
                 isActionBlocked = true;
                 blockedMessage = `<p class="text-info fw-bold"><i class="fas fa-info-circle me-1"></i> ACCIÓN REDUNDANTE.</p><p>Los ${checkedCount} remitos seleccionados ya han sido marcados como Verificado.</p>`;
             }
             
             // Si hay una mezcla de Pendientes y Verificados, advertimos que solo los pendientes se actualizarán
             if (!isActionBlocked && verifiedCount > 0) {
                 const pendingToVerifyCount = checkedCount - verifiedCount;
                 blockedMessage = `<p class="text-warning fw-bold"><i class="fas fa-exclamation-triangle me-1"></i> Advertencia: ${verifiedCount} remito(s) ya están Verificados.</p><p>Solo se marcarán como VERIFICADO los ${pendingToVerifyCount} remitos restantes.</p>`;
             }
        }


        // 2. Configurar el modal basado en la validación
        if (isActionBlocked) {
            modalHeader.className = 'modal-header bg-danger text-white';
            document.getElementById('conciliationModalContent').className = 'modal-content border-start border-5 border-danger';
            modalBodyContent.innerHTML = blockedMessage;
            confirmButton.style.display = 'none'; // Ocultar botón de confirmación
        } else {
            // Configuración estándar (muestra la acción que SÍ se ejecutará)
            let colorClass = estado === 'verificado' ? 'success' : (estado === 'pendiente' ? 'warning' : 'danger');
            
            modalHeader.className = `modal-header bg-${colorClass} ${colorClass === 'warning' ? 'text-dark' : 'text-white'}`;
            document.getElementById('conciliationModalContent').className = `modal-content border-start border-5 border-${colorClass}`;
            confirmButton.className = `btn btn-${colorClass}`;
            
            // Usamos el mensaje de advertencia si existe
            const finalMessage = blockedMessage ? blockedMessage : `
                <p class="lead">¿Está seguro de aplicar el estado <strong id="actionStatusText">${statusText}</strong> a <span class="fw-bold" id="actionCountText">${validIdsCount}</span> remito(s)?</p>
            `;
            
            modalBodyContent.innerHTML = finalMessage + `
                <div class="alert alert-info small mt-3">
                    <i class="fas fa-info-circle me-1"></i> Esta acción no se puede deshacer (si el destino es 'Verificado').
                </div>
            `;
            confirmButton.style.display = 'inline-block'; // Mostrar botón de confirmación
        }


        // 3. Asignar la función de envío al botón de confirmación del modal
        const confirmActionBtn = document.getElementById('confirmConciliationActionBtn');
        
        // ** SOLUCIÓN ESTABLE: Quitar y re-agregar el listener **
        confirmActionBtn.onclick = null; // Quitar cualquier listener anterior
        confirmActionBtn.addEventListener('click', function handler() {
            // Cerrar el modal antes de ejecutar la acción
            const modalInstance = bootstrap.Modal.getInstance(modalElement);
            if (modalInstance) modalInstance.hide();
            
            // Ejecutar la acción
            conciliateRemitos(estado); 
        }, { once: true }); // Usamos { once: true } para que el listener se dispare solo una vez y no haya duplicación.

        modal.show();
    }


    // --- Lógica de Exportación ---
    
    function escapeCsvField(field) {
        if (field == null) return '';
        let str = String(field).trim();
        // Limpiar saltos de línea y comillas dobles
        str = str.replace(/"/g, '""').replace(/\r?\n|\r/g, " ");
        // Si contiene comas o ya tiene comillas, envolver
        if (str.includes(',') || str.includes('"')) {
            return `"${str}"`;
        }
        return str;
    }

    function exportToExcel() {
        const tableRows = document.querySelectorAll('#remitosTable tbody tr.remito-row');
        const visibleRows = Array.from(tableRows).filter(tr => tr.style.display !== 'none');

        if (visibleRows.length === 0) {
            showToast('Sin datos', 'No hay remitos visibles en la tabla para exportar.', 'warning');
            return;
        }

        let csvContent = "data:text/csv;charset=utf-8,";
        
        // Encabezados
        csvContent += "Fecha Subida,Archivo,Estado,Descripcion,Precio,Nro Compra,Subido Por,Tarea\n";

        // Datos
        visibleRows.forEach(row => {
            const cells = [
                row.cells[1].textContent.trim(), // Fecha
                row.querySelector('.file-name').textContent.trim(), // Archivo
                row.querySelector('.status-badge').textContent.trim(), // Estado
                row.querySelector('.description').textContent.trim(), // Descripción
                row.querySelector('.price').textContent.trim(), // Precio
                row.querySelector('.purchase-number').textContent.trim(), // N° Compra
                row.querySelector('.uploader-name').textContent.trim(), // Subido por
                row.querySelector('.task-link a').textContent.trim() // Tarea
            ];
            csvContent += cells.map(escapeCsvField).join(",") + "\n";
        });

        const encodedUri = encodeURI(csvContent);
        const link = document.createElement("a");
        link.setAttribute("href", encodedUri);
        link.setAttribute("download", `Reporte_Remitos_Detallado_${new Date().toISOString().slice(0, 10)}.csv`);
        document.body.appendChild(link);
        link.click();
        document.body.removeChild(link);
    }
    
    function exportToPdf() {
        const tableRows = document.querySelectorAll('#remitosTable tbody tr.remito-row');
        const visibleRows = Array.from(tableRows).filter(tr => tr.style.display !== 'none');

        if (visibleRows.length === 0) {
            showToast('Sin datos', 'No hay remitos visibles en la tabla para exportar.', 'warning');
            return;
        }

        const { jsPDF } = window.jspdf;
        const doc = new jsPDF({ orientation: 'landscape' });
        
        doc.text("Reporte Detallado de Remitos y Facturas", 14, 15);
        
        const tableColumn = ["Fecha", "Archivo", "Estado", "Descripción", "Precio", "N° Compra", "Subido Por", "Tarea"];
        const tablePdfRows = [];

        visibleRows.forEach(row => {
            const rowData = [
                row.cells[1].textContent.trim(), // Fecha
                row.querySelector('.file-name').textContent.trim(), // Archivo
                row.querySelector('.status-badge').textContent.trim(), // Estado
                row.querySelector('.description').textContent.trim(), // Descripción
                row.querySelector('.price').textContent.trim(), // Precio
                row.querySelector('.purchase-number').textContent.trim(), // N° Compa
                row.querySelector('.uploader-name').textContent.trim(), // Subido por
                row.querySelector('.task-link a').textContent.trim() // Tarea
            ];
            tablePdfRows.push(rowData);
        });

        doc.autoTable({
            head: [tableColumn],
            body: tablePdfRows,
            startY: 20,
            styles: { fontSize: 8 },
            headStyles: { fillColor: [13, 110, 253] }
        });

        doc.save(`Reporte_Remitos_Detallado_${new Date().toISOString().slice(0, 10)}.pdf`);
    }

    // --- Inicialización y Listeners ---
    
    document.addEventListener('DOMContentLoaded', () => {
        
        // Cargar datos iniciales de gráficos
        loadStats();
        
        // Listeners para el filtro de gráficos
        document.getElementById('applyStatsFilterBtn').addEventListener('click', loadStats);
        
        // Listeners para exportación
        document.getElementById('exportExcelBtn').addEventListener('click', exportToExcel);
        document.getElementById('exportPdfBtn').addEventListener('click', exportToPdf);

        // --- Lógica de Conciliación Masiva (Eventos) ---
        const selectAllCheck = document.getElementById('selectAllCheck');
        const remitoChecks = document.querySelectorAll('.remito-check');
        
        // Listener para el checkbox maestro
        if (selectAllCheck) {
            selectAllCheck.addEventListener('change', function() {
                remitoChecks.forEach(cb => {
                    if (cb.closest('tr').style.display !== 'none') {
                        cb.checked = this.checked;
                    }
                });
                updateConciliationButtons();
            });
        }
        
        // Listeners para checkboxes individuales
        remitoChecks.forEach(cb => {
            cb.addEventListener('change', updateConciliationButtons);
        });
        
        // Asignar showConciliationModal a los botones
        document.getElementById('markVerifiedBtn').addEventListener('click', () => showConciliationModal('verificado'));
        document.getElementById('markPendingBtn').addEventListener('click', () => showConciliationModal('pendiente'));
        document.getElementById('markRejectedBtn').addEventListener('click', () => showConciliationModal('rechazado')); // Nuevo botón
        
        document.getElementById('deselectAllBtn').addEventListener('click', () => {
            remitoChecks.forEach(cb => { cb.checked = false; });
            updateConciliationButtons();
        });


        // --- Script simple para buscador en cliente ---
        const searchInputClient = document.getElementById('tableSearchInput');
        const tableRows = document.querySelectorAll('#remitosTable tbody tr.remito-row');

        if (searchInputClient && tableRows.length > 0) {
            searchInputClient.addEventListener('keyup', function() {
                const searchTerm = this.value.toLowerCase().trim();

                tableRows.forEach(row => {
                    const fileName = row.querySelector('.file-name')?.textContent.toLowerCase() || '';
                    const description = row.querySelector('.description')?.textContent.toLowerCase() || '';
                    const purchaseNumber = row.querySelector('.purchase-number')?.textContent.toLowerCase() || '';
                    const uploaderName = row.querySelector('.uploader-name')?.textContent.toLowerCase() || '';
                    const taskLink = row.querySelector('.task-link a')?.textContent.toLowerCase() || '';

                    let display = 'none';
                    if (fileName.includes(searchTerm) ||
                        description.includes(searchTerm) ||
                        purchaseNumber.includes(searchTerm) ||
                        uploaderName.includes(searchTerm) ||
                        taskLink.includes(searchTerm) ) {
                        display = '';
                    }
                    row.style.display = display;
                });
                updateConciliationButtons(); // Actualizar botones después de filtrar
            });
             // Ejecutar filtro inicial si el campo de búsqueda ya tiene valor (por el filtro PHP)
             if (searchInputClient.value) {
                 searchInputClient.dispatchEvent(new Event('keyup'));
             }
        }
        // --- Fin Script simple para buscador ---
    });

</script>
<?php include 'footer.php'; ?>
</body>
</html>