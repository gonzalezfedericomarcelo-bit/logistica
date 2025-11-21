<?php
// Archivo: helper_efemerides.php (ACTUALIZACIÓN CADA 12 HORAS)

function obtenerEfemerideHoy() {
    $dia = date('d');
    $mes = date('m');
    // Usamos un nombre fijo para el día, pero validaremos la hora abajo
    $cache_file = __DIR__ . "/efemeride_cache_{$dia}_{$mes}.json";

    // 1. VALIDAR CACHÉ (Solo si el archivo tiene menos de 12 HORAS de antigüedad)
    // 43200 segundos = 12 horas
    if (file_exists($cache_file) && (time() - filemtime($cache_file) < 43200)) {
        $data = json_decode(file_get_contents($cache_file), true);
        // Verificar que la data sea válida y del tipo militar
        if ($data && !empty($data['titulo']) && $data['tipo'] === 'militar') return $data;
    }

    // DATOS POR DEFECTO
    $efemeride = [
        'titulo' => 'Gloria y Honor',
        'descripcion' => 'En este día recordamos a los hombres y mujeres de las Fuerzas Armadas que forjaron la soberanía de nuestra Nación.',
        'link' => 'https://es.wikipedia.org/wiki/Historia_de_la_Argentina',
        'tipo' => 'militar', 
        'icono' => 'fas fa-flag'
    ];

    // 2. PALABRAS CLAVE (FILTRO BLINDADO)
    $keywords_tematicas = [
        'Batalla', 'Combate', 'Guerra', 'Revolución', 'Independencia', 'Ejército', 'Armada', 
        'Fuerza Aérea', 'Regimiento', 'Comando', 'General', 'Coronel', 'Almirante', 'Brigadier', 
        'Teniente', 'Sargento', 'Cabo', 'Soldado', 'Presidente', 'Decreto', 'Ley', 'Constitución',
        'Cabildo', 'Virreinato', 'Confederación', 'Campaña', 'Expedición', 'Soberanía', 'Tratado',
        'Fundación', 'Creación', 'Inauguración', 'Malvinas', 'Antártida', 'Prócer', 'Héroe'
    ];

    $keywords_geo = [
        'Argentina', 'Argentino', 'Buenos Aires', 'Río de la Plata', 'San Martín', 'Belgrano', 
        'Sarmiento', 'Roca', 'Rosas', 'Perón', 'Yrigoyen', 'Mitre', 'Urquiza', 'Güemes', 
        'Brown', 'Azopardo', 'Bouchard', 'Savio', 'Mosconi', 'Moreno', 'Castelli', 'Saavedra'
    ];

    $blacklist = [
        'fútbol', 'futbolista', 'jugador', 'partido', 'gol', 'entrenador', 'DT', 'club', 'liga', 
        'copa', 'mundial', 'olímpico', 'deporte', 'tenis', 'rugby', 'boxeo', 'automovilismo',
        'actor', 'actriz', 'cantante', 'música', 'canción', 'álbum', 'disco', 'banda', 'rock', 
        'telenovela', 'película', 'cine', 'teatro', 'show', 'concierto', 'modelo', 'vedette'
    ];

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
                    $texto = $item['text'];
                    if (contienePalabra($texto, $blacklist)) continue; 
                    if (!contienePalabra($texto, $keywords_geo)) continue;
                    if (!contienePalabra($texto, $keywords_tematicas)) continue;

                    $desc = "Información histórica.";
                    $link = "https://es.wikipedia.org";
                    
                    if (isset($item['pages'][0])) {
                        if (!empty($item['pages'][0]['extract'])) $desc = $item['pages'][0]['extract'];
                        if (!empty($item['pages'][0]['content_urls']['desktop']['page'])) $link = $item['pages'][0]['content_urls']['desktop']['page'];
                    }

                    $candidatos[] = [
                        'titulo' => $item['text'],
                        'descripcion' => $desc,
                        'link' => $link,
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

    // 4. GUARDAR (Se sobrescribirá cada 12hs gracias a la validación del inicio)
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