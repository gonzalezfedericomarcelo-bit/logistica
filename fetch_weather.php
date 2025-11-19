<?php
// Archivo: fetch_weather.php
// Endpoint para obtener el clima basado en Lat/Lon del usuario.

header('Content-Type: application/json');

// La clave ha sido validada por el usuario. La dejamos aquí para el uso del script.
const OPENWEATHER_API_KEY = 'b05ad9aed65b005e113093b6a3fdecf6'; 

$lat = $_GET['lat'] ?? null;
$lon = $_GET['lon'] ?? null;

if (empty($lat) || empty($lon)) {
    http_response_code(400);
    echo json_encode([
        'success' => false, 
        'temp' => 'N/A',
        'desc' => 'Error: Coordenadas no proporcionadas.',
        'icon' => 'fas fa-times-circle text-danger',
        'location' => 'Ubicación'
    ]);
    exit;
}

// ----------------------------------------------------------------------
// 1. Obtener datos del clima de OpenWeatherMap
// ----------------------------------------------------------------------
$url = "https://api.openweathermap.org/data/2.5/weather?lat={$lat}&lon={$lon}&units=metric&lang=es&appid=" . OPENWEATHER_API_KEY;

$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, $url);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
$weather_data = curl_exec($ch);
$http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

$data = json_decode($weather_data, true);

if ($http_code !== 200 || $data === null) {
    $error_message = $data['message'] ?? 'Error desconocido al conectar con el servicio de clima.';
    
    // Si el error es una clave inválida (código 401), se reporta claramente.
    if ($http_code === 401) {
        $error_message = 'Clave API Inválida (401). Verifique su clave en OpenWeatherMap.';
    }
    
    http_response_code($http_code);
    echo json_encode([
        'success' => false,
        'temp' => 'N/A',
        'desc' => "API Error: " . $error_message,
        'icon' => 'fas fa-unlink text-danger',
        'location' => 'Servidor'
    ]);
    exit;
}


// ----------------------------------------------------------------------
// 2. Procesar los datos y mapear iconos
// ----------------------------------------------------------------------

$temp = round($data['main']['temp']) . '°C';
$description = ucfirst($data['weather'][0]['description']);
$icon_code = $data['weather'][0]['icon'];

// Mapeo de códigos de iconos de OpenWeatherMap a iconos de Font Awesome
$icon_map = [
    '01d' => 'fas fa-sun text-warning', '01n' => 'fas fa-moon text-light',
    '02d' => 'fas fa-cloud-sun text-warning', '02n' => 'fas fa-cloud-moon text-light',
    '03d' => 'fas fa-cloud text-secondary', '03n' => 'fas fa-cloud text-secondary',
    '04d' => 'fas fa-cloud-meatball text-secondary', '04n' => 'fas fa-cloud-meatball text-secondary',
    '09d' => 'fas fa-cloud-showers-heavy text-info', '09n' => 'fas fa-cloud-showers-heavy text-info',
    '10d' => 'fas fa-cloud-sun-rain text-info', '10n' => 'fas fa-cloud-moon-rain text-info',
    '11d' => 'fas fa-bolt text-warning', '11n' => 'fas fa-bolt text-warning',
    '13d' => 'fas fa-snowflake text-primary', '13n' => 'fas fa-snowflake text-primary',
    '50d' => 'fas fa-smog text-muted', '50n' => 'fas fa-smog text-muted',
];
$fa_icon = $icon_map[$icon_code] ?? 'fas fa-thermometer-half text-primary';

// Obtener el nombre de la ciudad
$city = $data['name'] ?? 'Ubicación Desconocida';
$country = $data['sys']['country'] ?? '';
$location_name = trim("{$city} ({$country})");

// ----------------------------------------------------------------------
// 3. Devolver la respuesta JSON
// ----------------------------------------------------------------------
echo json_encode([
    'success' => true,
    'temp' => $temp,
    'desc' => $description,
    'icon' => $fa_icon,
    'location' => $location_name
]);

?>