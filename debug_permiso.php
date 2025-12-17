<?php
// Archivo: debug_permiso.php
session_start();
include 'conexion.php';
include 'funciones_permisos.php';

echo "<h1>Diagnóstico de Permisos</h1>";
echo "ID Usuario en Sesión: " . ($_SESSION['usuario_id'] ?? 'NO DEFINIDO') . "<br>";
echo "Rol en Sesión: " . ($_SESSION['usuario_rol'] ?? 'NO DEFINIDO') . "<br><hr>";

// Prueba 1: Permiso General
$tiene_historial = tiene_permiso('inventario_historial', $pdo);
echo "Prueba permiso 'inventario_historial': " . ($tiene_historial ? '<b style="color:green">SI (TRUE)</b>' : '<b style="color:red">NO (FALSE)</b>') . "<br>";

// Prueba 2: Permiso Transferencias
$tiene_transf = tiene_permiso('inventario_ver_transferencias', $pdo);
echo "Prueba permiso 'inventario_ver_transferencias': " . ($tiene_transf ? '<b style="color:green">SI (TRUE)</b>' : '<b style="color:red">NO (FALSE)</b>') . "<br>";

echo "<hr>";
echo "<h3>Análisis:</h3>";
if (!$tiene_historial && !$tiene_transf) {
    echo "ATENCIÓN: El sistema detecta que NO tenés ninguno de los dos permisos necesarios para ver el PDF. Por eso te redirige.";
} else {
    echo "Los permisos parecen estar bien detectados. Si te sigue redirigiendo, el error está en el archivo del PDF.";
}
?>