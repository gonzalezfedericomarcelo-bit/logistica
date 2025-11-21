<?php
// Archivo: helper_frases.php (FUENTE: ARCHIVO LOCAL JSON)

function obtenerFraseMotivadora() {
    $cache_file = __DIR__ . "/frase_cache.json";
    $local_db = __DIR__ . "/frases.json";
    
    // 1. VALIDAR CACHÉ: 12 Horas (43200 seg)
    // Si existe el caché y es reciente, lo usamos para no cambiar la frase en cada recarga
    if (file_exists($cache_file) && (time() - filemtime($cache_file) < 43200)) {
        $data = json_decode(file_get_contents($cache_file), true);
        if ($data) return $data;
    }

    // 2. LEER DEL ARCHIVO LOCAL (frases.json)
    $frase_final = ["frase" => "Sistema Logístico Operativo", "autor" => "Admin"]; // Default técnico

    if (file_exists($local_db)) {
        $contenido = file_get_contents($local_db);
        $lista_frases = json_decode($contenido, true);

        if (is_array($lista_frases) && count($lista_frases) > 0) {
            // Elegir una al azar
            $random = $lista_frases[array_rand($lista_frases)];
            
            $frase_final = [
                "frase" => $random['frase'],
                "autor" => $random['autor']
            ];
        }
    }

    // 3. GUARDAR EN CACHÉ
    // Esto "congela" la frase elegida por 12 horas (o hasta que borres el caché)
    file_put_contents($cache_file, json_encode($frase_final));
    
    return $frase_final;
}
?>