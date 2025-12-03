<?php
// Archivo: helper_frases.php (FRASES DIFERENTES PARA CADA USUARIO)

function obtenerFraseMotivadora($id_usuario) {
    // Usamos el ID del usuario en el nombre del archivo para que sea único
    $cache_file = __DIR__ . "/frase_cache_{$id_usuario}.json";
    $local_db = __DIR__ . "/frases.json";
    
    // 1. VALIDAR CACHÉ INDIVIDUAL: 12 Horas (43200 seg)
    if (file_exists($cache_file) && (time() - filemtime($cache_file) < 43200)) {
        $data = json_decode(file_get_contents($cache_file), true);
        if ($data) return $data;
    }

    // 2. LEER DEL ARCHIVO LOCAL
    $frase_final = ["frase" => "Sistema Logístico Operativo", "autor" => "Admin"];

    if (file_exists($local_db)) {
        $contenido = file_get_contents($local_db);
        $lista_frases = json_decode($contenido, true);

        if (is_array($lista_frases) && count($lista_frases) > 0) {
            // Elegir una al azar DE ESTE USUARIO para este momento
            $random = $lista_frases[array_rand($lista_frases)];
            
            $frase_final = [
                "frase" => $random['frase'],
                "autor" => $random['autor']
            ];
        }
    }

    // 3. GUARDAR CACHÉ DEL USUARIO
    file_put_contents($cache_file, json_encode($frase_final));
    
    return $frase_final;
}
?>