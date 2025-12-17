<?php
// Archivo: instalar_permisos_inventario.php
session_start();
include 'conexion.php';

// Lista de permisos a crear
$nuevos_permisos = [
    ['clave' => 'acceso_inventario',        'nombre' => 'Inventario: Acceso y Lectura'],
    ['clave' => 'inventario_nuevo',         'nombre' => 'Inventario: Cargar Nuevos Bienes'],
    ['clave' => 'inventario_editar',        'nombre' => 'Inventario: Editar Bienes'],
    ['clave' => 'inventario_eliminar',      'nombre' => 'Inventario: Eliminar Definitivamente'],
    ['clave' => 'inventario_baja',          'nombre' => 'Inventario: Dar de Baja (Lógica)'],
    ['clave' => 'inventario_transferir',    'nombre' => 'Inventario: Transferir de Área'],
    ['clave' => 'inventario_mantenimiento', 'nombre' => 'Inventario: Registrar Mantenimiento'],
    ['clave' => 'inventario_historial',     'nombre' => 'Inventario: Ver Historial Completo'],
    ['clave' => 'inventario_reportes',      'nombre' => 'Inventario: Generar Reportes PDF'],
    ['clave' => 'inventario_config',        'nombre' => 'Inventario: Configuración Avanzada']
];

echo "<h3>Instalando Permisos de Inventario...</h3><ul>";

try {
    $sql = "INSERT INTO permisos (clave_permiso, nombre_mostrar) VALUES (:clave, :nombre) 
            ON DUPLICATE KEY UPDATE nombre_mostrar = VALUES(nombre_mostrar)";
    $stmt = $pdo->prepare($sql);

    foreach ($nuevos_permisos as $p) {
        $stmt->execute([':clave' => $p['clave'], ':nombre' => $p['nombre']]);
        echo "<li>Permiso <b>" . $p['clave'] . "</b> procesado.</li>";
    }
    
    // Asignar todo al admin por defecto para que no pierdas acceso
    $pdo->exec("INSERT IGNORE INTO rol_permiso (nombre_rol, clave_permiso) 
                SELECT 'admin', clave_permiso FROM permisos WHERE clave_permiso LIKE 'inventario_%' OR clave_permiso = 'acceso_inventario'");
    
    echo "</ul><h4 style='color:green'>¡Éxito! Ahora andá a 'Admin Roles' y configurá qué rol hace qué.</h4>";
    echo "<a href='admin_roles.php'>Ir a Admin Roles</a>";

} catch (PDOException $e) {
    echo "</ul><h4 style='color:red'>Error: " . $e->getMessage() . "</h4>";
}
?>