<?php
// Archivo: helper_efemerides.php (VERSIÓN FINAL: DOBLE BOTÓN)

function obtenerEfemerideHoy() {
    $dia = date('d');
    $mes = date('m');
    
    // CAMBIO CLAVE: Nuevo nombre para forzar actualización inmediata
    $cache_file = __DIR__ . "/efemeride_vFinal_{$dia}_{$mes}.json";

    // 1. VALIDAR CACHÉ
    if (file_exists($cache_file) && (time() - filemtime($cache_file) < 43200)) {
        $data = json_decode(file_get_contents($cache_file), true);
        if ($data && !empty($data['titulo']) && $data['tipo'] === 'militar') return $data;
    }

    // DATOS POR DEFECTO
    $efemeride = [
        'titulo' => 'Gloria y Honor',
        'descripcion' => 'En este día recordamos a los hombres y mujeres de las Fuerzas Armadas.',
        'link_google' => 'https://www.google.com/search?q=Historia+Militar+Argentina',
        'link_wiki' => 'https://es.wikipedia.org/wiki/Historia_de_la_Argentina',
        'tipo' => 'militar', 
        'icono' => 'fas fa-flag'
    ];

    // 2. CONFIGURACIÓN
    $keywords_tematicas = ['Batalla', 'Combate', 'Guerra', 'Revolución', 'Ejército', 'Armada', 'Fuerza Aérea', 'Regimiento', 'General', 'Coronel', 'Presidente', 'Decreto', 'Ley', 'Constitución', 'Cabildo', 'Campaña', 'Expedición', 'Soberanía', 'Fundación', 'Malvinas', 'Antártida', 'Golpe', 'Dictadura', 'Proceso', 'Junta', 'Renuncia', 'Asume'];
    $keywords_geo = ['Argentina', 'Argentino', 'Buenos Aires', 'Río de la Plata', 'San Martín', 'Belgrano', 'Sarmiento', 'Roca', 'Perón', 'Brown', 'Azopardo', 'Bouchard', 'Savio', 'Mosconi'];
    $blacklist = ['fútbol', 'futbolista', 'jugador', 'partido', 'gol', 'entrenador', 'club', 'copa', 'mundial', 'olímpico', 'tenis', 'actor', 'actriz', 'cantante', 'música', 'disco', 'banda', 'telenovela', 'cine'];

    // 3. CONSULTAR WIKIPEDIA
    $url = "https://es.wikipedia.org/api/rest_v1/feed/onthisday/all/$mes/$dia";
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
    curl_setopt($ch, CURLOPT_USERAGENT, 'SistemaMilitar/1.0 (admin@tusitio.com)');
    curl_setopt($ch, CURLOPT_TIMEOUT, 5);
    $json = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($httpCode === 200 && $json) {
        $data = json_decode($json, true);
        $candidatos = [];
        $grupos = ['events', 'births', 'deaths'];

        foreach ($grupos as $grupo) {
            if (!empty($data[$grupo])) {
                foreach ($data[$grupo] as $item) {
                    $texto_crudo = $item['text'];

                    // LIMPIEZA
                    $texto_limpio = str_replace([' - - -', '---', ' – ', ' - '], ' ', $texto_crudo);
                    $texto_limpio = ltrim($texto_limpio, "- \t\n\r\0\x0B");
                    $texto_limpio = trim(preg_replace('/\s+/', ' ', $texto_limpio));

                    if (contienePalabra($texto_limpio, $blacklist)) continue; 
                    if (!contienePalabra($texto_limpio, $keywords_geo)) continue;
                    if (!contienePalabra($texto_limpio, $keywords_tematicas)) continue;

                    $year = $item['year'] ?? '';
                    $busqueda = $texto_limpio . " " . $year;

                    // --- GENERAMOS AMBOS LINKS ---
                    // 1. Google: Búsqueda exacta (Ideal resumen IA)
                    $link_google = "https://www.google.com/search?q=" . urlencode($busqueda);
                    
                    // 2. Wikipedia: Búsqueda interna (Más seguro que el link directo roto)
                    $link_wiki = "https://es.wikipedia.org/w/index.php?search=" . urlencode($busqueda);

                    $desc = "Información histórica del año $year.";
                    if (isset($item['pages'][0]['extract'])) {
                        $desc = $item['pages'][0]['extract'];
                    }

                    $candidatos[] = [
                        'titulo' => $texto_limpio,
                        'descripcion' => $desc,
                        'link_google' => $link_google, // Link 1
                        'link_wiki' => $link_wiki,     // Link 2
                        'tipo' => 'militar',
                        'icono' => 'fas fa-landmark'
                    ];
                }
            }
        }

        if (!empty($candidatos)) {
            $efemeride = $candidatos[array_rand($candidatos)];
        }
    }

    file_put_contents($cache_file, json_encode($efemeride));
    return $efemeride;
}

function contienePalabra($texto, $lista) {
    foreach ($lista as $palabra) {
        if (stripos($texto, $palabra) !== false) return true;
    }
    return false;
}
?>