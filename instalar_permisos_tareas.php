<?php
// Archivo: instalar_permisos_menu.php
include 'conexion.php';

echo "<h2>Instalando permisos del Menú...</h2><hr>";

$permisos = [
    ['clave' => 'ver_lista_tareas', 'nombre' => 'Menú: Ver Tareas (Acceso básico)'],
    ['clave' => 'acceso_chat',      'nombre' => 'Menú: Acceso al Chat Global'],
    ['clave' => 'ver_avisos',       'nombre' => 'Menú: Ver Blog/Avisos'],
    ['clave' => 'acceso_ascensores','nombre' => 'Menú: Acceso Ascensores']
];

try {
    foreach ($permisos as $p) {
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM permisos WHERE clave_permiso = :c");
        $stmt->execute([':c' => $p['clave']]);
        if ($stmt->fetchColumn() == 0) {
            $pdo->prepare("INSERT INTO permisos (clave_permiso, nombre_mostrar) VALUES (:c, :n)")
                ->execute([':c' => $p['clave'], ':n' => $p['nombre']]);
            echo "<p style='color:green'>✅ Creado: {$p['nombre']}</p>";
        } else {
            echo "<p style='color:blue'>ℹ️ Ya existe: {$p['nombre']}</p>";
        }
    }
    echo "<hr><p>Listo. Ahora ve a <b>Admin Roles</b> y configura quién ve qué.</p>";
} catch (Exception $e) {
    echo "Error: " . $e->getMessage();
}
?>