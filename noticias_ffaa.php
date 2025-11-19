<?php
// Archivo: noticias_ffaa.php (VERSIÓN DINÁMICA - CONEXIÓN A NEWSAPI Y PLACEHOLDER CONDICIONAL)
session_start();
include 'conexion.php'; 

if (!isset($_SESSION['usuario_id'])) {
    header("Location: login.php");
    exit();
}

// ----------------------------------------------------------------------------------
// LÓGICA DE BÚSQUEDA DINÁMICA (cURL) - SU CLAVE DE API INTEGRADA
// ----------------------------------------------------------------------------------

function obtener_noticias_dinamicas($query, $pageSize = 25) {
    
    // SU CLAVE DE API
    $api_key = 'a0f6f034208f438aba2b1c36917d148f'; 
    $base_url = 'https://newsapi.org/v2/everything';
    $search_terms = urlencode($query); 
    
    // Ordenar por fecha de publicación
    $url = "{$base_url}?q={$search_terms}&language=es&sortBy=publishedAt&pageSize={$pageSize}&apiKey={$api_key}";

    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
    curl_setopt($ch, CURLOPT_USERAGENT, 'LogisticaAppNewsScraper/1.0'); 

    $response = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curl_error = curl_error($ch);
    
    if (curl_errno($ch)) {
        error_log("Error cURL al obtener noticias: " . $curl_error);
        curl_close($ch);
        return ['error' => 'Error de conexión cURL: ' . $curl_error]; 
    }

    curl_close($ch);

    $data = json_decode($response, true);

    if ($http_code !== 200 || (isset($data['status']) && $data['status'] !== 'ok')) {
        $api_message = $data['message'] ?? 'Error desconocido de la API.';
        error_log("API de Noticias devolvió código HTTP: " . $http_code . " - Mensaje: " . $api_message);
        return ['error' => 'La API falló o su clave es incorrecta/superó el límite: ' . $api_message]; 
    }

    if (empty($data['articles'])) {
        return ['error' => 'La API devolvió un resultado OK, pero no hay artículos para los términos de búsqueda.']; 
    }

    $noticias = [];
    foreach ($data['articles'] as $article) {
        // Omitir si no tiene el enlace principal (URL de la noticia)
        if (empty($article['url'])) continue; 
        
        $noticias[] = [
            'titulo' => htmlspecialchars($article['title'] ?? 'Sin título'), 
            'snippet' => htmlspecialchars($article['description'] ?? 'No hay resumen disponible.'),
            'fuente' => htmlspecialchars($article['source']['name'] ?? 'Fuente Desconocida'),
            'url' => htmlspecialchars($article['url']), 
            // Si urlToImage es null, pasamos una cadena vacía.
            'imagen' => htmlspecialchars($article['urlToImage'] ?? ''), 
            'fecha_publicacion' => (new DateTime($article['publishedAt']))->format('d/m/Y H:i')
        ];
    }
    
    // Aleatorizar el array de noticias
    shuffle($noticias);
    
    return $noticias;
}

// Búsqueda de noticias al cargar la página 
//$query_tematica = "MILEY OR milei OR iosfa OR IOSFA OR Colegio Militar de la Nacion OR Hospital Militar Central OR Policlinica Actis OR Policlínica actis OR Ejercito Argentino OR Armada Argentina OR Fuerza Aérea Argentina OR Hospital Aeronáutico OR Hospital Naval";
$query_tematica = "MILEY OR milei OR iosfa OR IOSFA OR Policlinica Actis OR Policlínica actis OR ACTIS";
$result = obtener_noticias_dinamicas($query_tematica);

$error_api = isset($result['error']) ? $result['error'] : false;
$noticias_raw = $error_api ? [] : $result;

// PLACEHOLDER (Asegúrese que la ruta 'assets/img/placeholder_news.jpg' es correcta)
$default_image_src = 'assets/img/placeholder_news.jpg'; 

// Lógica de Paginación
$noticias_por_pagina = 12; 
$total_noticias = count($noticias_raw);
$total_paginas = $total_noticias > 0 ? ceil($total_noticias / $noticias_por_pagina) : 0;
$pagina_actual = isset($_GET['p']) && is_numeric($_GET['p']) ? (int)$_GET['p'] : 1;

if ($pagina_actual < 1) $pagina_actual = 1;
if ($pagina_actual > $total_paginas && $total_paginas > 0) $pagina_actual = $total_paginas;

$inicio = ($pagina_actual - 1) * $noticias_por_pagina;
$noticias_paginadas = array_slice($noticias_raw, $inicio, $noticias_por_pagina);

// ----------------------------------------------------------------------------------
// INTERFAZ DE USUARIO (HTML) - LÓGICA CONDICIONAL EN EL BUCLE
// ----------------------------------------------------------------------------------
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Noticias Relevantes - Logística</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">
    <style>
        .news-card-img {
            height: 180px;
            object-fit: cover;
            width: 100%;
        }
        .news-card-body {
            /* Ajustado para mejor visualización del snippet */
            min-height: 170px; 
            display: flex;
            flex-direction: column;
            justify-content: space-between;
        }
        .card-title a {
            color: var(--bs-primary);
            text-decoration: none;
        }
        .card-title a:hover {
            text-decoration: underline;
        }
    </style>
</head>
<body>
    <?php include 'navbar.php'; // Incluye la barra de navegación ?>

    <div class="container mt-5">
        <h2 class="mb-4"><i class="fas fa-newspaper me-2 text-primary"></i> Noticias Dinámicas de Fuentes Oficiales</h2>
        <hr>

        <?php if ($error_api): ?>
             <div class="alert alert-danger" role="alert">
                <i class="fas fa-exclamation-triangle me-2"></i> **ERROR DE CONEXIÓN O LÍMITE DE API:**
                <p class="mt-2 mb-0"><?php echo htmlspecialchars($error_api); ?></p>
            </div>
        <?php elseif (empty($noticias_paginadas)): ?>
             <div class="alert alert-info" role="alert">
                <i class="fas fa-info-circle me-2"></i> La API no arrojó noticias relevantes para los términos especificados.
            </div>
        <?php else: ?>
            <div class="row">
                <?php 
                $i = $inicio + 1; 
                foreach ($noticias_paginadas as $noticia): 
                    
                    // 1. Detección de término "IOSFA" (Case-insensitive en título o snippet)
                    $is_iosfa_news = (
                        stripos($noticia['titulo'], 'IOSFA') !== false ||
                        stripos($noticia['snippet'], 'IOSFA') !== false
                    );
                    
                    // 2. Determinar la fuente de imagen inicial (placeholder si la API está vacía)
                    $final_image_src = empty(trim($noticia['imagen'])) ? $default_image_src : $noticia['imagen'];

                    // 3. Determinar la acción 'onerror'
                    if ($is_iosfa_news) {
                        // IOSFA: Si la imagen falla, cambia la fuente al placeholder
                        $onerror_action = "this.onerror=null; this.src='{$default_image_src}';";
                    } else {
                        // NO IOSFA: Si la imagen falla, elimina la columna completamente
                        $onerror_action = "this.onerror=null; document.getElementById('news-col-{$i}').remove();";
                    }
                ?>
                    <div class="col-lg-3 col-md-4 col-sm-6 mb-4" id="news-col-<?php echo $i; ?>">
                        <div class="card shadow-sm h-100">
                            
                            <img src="<?php echo htmlspecialchars($final_image_src); ?>" 
                                 class="card-img-top news-card-img" 
                                 alt="Imagen de la noticia"
                                 title="ID: <?php echo $i; ?> (IOSFA: <?php echo $is_iosfa_news ? 'SI' : 'NO'; ?>)"
                                 
                                 onerror="<?php echo htmlspecialchars($onerror_action); ?>">
                                 
                            <div class="card-body news-card-body">
                                <h5 class="card-title fw-bold">
                                    <a href="<?php echo $noticia['url']; ?>" target="_blank" title="Leer Noticia Completa">
                                        <?php echo $noticia['titulo']; ?>
                                    </a>
                                </h5>
                                <p class="card-text small text-muted flex-grow-1"><?php echo $noticia['snippet']; ?></p>
                                
                                <div class="mt-2 d-flex justify-content-between align-items-center">
                                    <div>
                                        <span class="badge bg-secondary me-2"><?php echo $noticia['fuente']; ?></span>
                                        <span class="badge bg-info text-dark"><?php echo $noticia['fecha_publicacion']; ?></span>
                                    </div>
                                    
                                    <a href="<?php echo $noticia['url']; ?>" target="_blank" class="btn btn-sm btn-success" title="Ver Noticia Completa en la Fuente Original">
                                        <i class="fas fa-external-link-alt"></i> Ver Fuente
                                    </a>
                                </div>
                            </div>
                        </div>
                    </div>
                <?php 
                $i++;
                endforeach; ?>
            </div>
        <?php endif; ?>

        <?php if ($total_paginas > 1): ?>
        <nav aria-label="Navegación de Noticias" class="mt-4">
            <ul class="pagination justify-content-center">
                <li class="page-item <?php echo ($pagina_actual <= 1) ? 'disabled' : ''; ?>">
                    <a class="page-link" href="?p=<?php echo $pagina_actual - 1; ?>" aria-label="Anterior">
                        <span aria-hidden="true">&laquo;</span>
                    </a>
                </li>

                <?php for ($i = 1; $i <= $total_paginas; $i++): ?>
                    <li class="page-item <?php echo ($i == $pagina_actual) ? 'active' : ''; ?>">
                        <a class="page-link" href="?p=<?php echo $i; ?>"><?php echo $i; ?></a>
                    </li>
                <?php endfor; ?>

                <li class="page-item <?php echo ($pagina_actual >= $total_paginas) ? 'disabled' : ''; ?>">
                    <a class="page-link" href="?p=<?php echo $pagina_actual + 1; ?>" aria-label="Siguiente">
                        <span aria-hidden="true">&raquo;</span>
                    </a>
                </li>
            </ul>
        </nav>
        <?php endif; ?>

    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>