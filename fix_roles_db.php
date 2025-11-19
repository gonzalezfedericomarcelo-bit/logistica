<?php
// fix_roles_db.php - HERRAMIENTA DE REPARACIÓN DE BASE DE DATOS
// Ejecutar una sola vez para desbloquear la creación de roles nuevos.
session_start();
include 'conexion.php';

echo "<h2>🛠️ Iniciando reparación de la estructura de roles...</h2>";

try {
    // 1. Modificar la columna 'rol' en la tabla 'usuarios'
    // Cambiamos de ENUM (lista cerrada) a VARCHAR (texto abierto)
    $sql = "ALTER TABLE usuarios MODIFY COLUMN rol VARCHAR(50) NOT NULL DEFAULT 'empleado'";
    $pdo->exec($sql);
    
    echo "<div style='padding:15px; background:#d4edda; color:#155724; border:1px solid #c3e6cb; border-radius:5px; margin:10px 0;'>
            <strong>✅ ÉXITO:</strong> La base de datos ha sido desbloqueada. Ahora acepta roles personalizados como 'encargado_suplente'.
          </div>";

    // 2. Verificación rápida
    echo "<p>Verificando roles existentes...</p>";
    $stmt = $pdo->query("SELECT nombre_rol FROM roles");
    $roles = $stmt->fetchAll(PDO::FETCH_COLUMN);
    
    echo "<ul>";
    foreach($roles as $r) {
        echo "<li>Rol detectado: <strong>$r</strong></li>";
    }
    echo "</ul>";

    echo "<br><a href='admin_usuarios.php' style='padding:10px 20px; background:#007bff; color:white; text-decoration:none; border-radius:5px;'>Volver a Gestión de Usuarios y Probar</a>";

} catch (PDOException $e) {
    echo "<div style='padding:15px; background:#f8d7da; color:#721c24; border:1px solid #f5c6cb; border-radius:5px;'>
            <strong>❌ ERROR:</strong> No se pudo modificar la tabla.<br>
            Detalle técnico: " . $e->getMessage() . "
          </div>";
}
?>