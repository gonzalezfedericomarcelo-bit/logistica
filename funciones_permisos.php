<?php
// Archivo: funciones_permisos.php

/**
 * Función para verificar si el usuario logueado tiene un permiso específico.
 * @param string $clave_permiso La clave del permiso a verificar (ej: 'admin_usuarios').
 * @param PDO $pdo La conexión PDO.
 * @return bool True si tiene permiso, False en caso contrario.
 */
function tiene_permiso(string $clave_permiso, PDO $pdo): bool {
    // 1. Verificar si la sesión y el rol existen
    if (!isset($_SESSION['usuario_id']) || !isset($_SESSION['usuario_rol'])) {
        return false;
    }

    $rol_usuario = $_SESSION['usuario_rol'];
    
    // 2. SEGURIDAD CRÍTICA: El rol 'admin' siempre tiene acceso total.
    // Esto es necesario porque si la tabla de permisos se vacía o hay un error,
    // el administrador nunca debe perder el acceso para poder arreglarlo.
    if ($rol_usuario === 'admin') {
        return true;
    }

    // 3. Consultar la base de datos para ver si el rol tiene el permiso asignado
    try {
        $sql = "SELECT COUNT(*) 
                FROM rol_permiso 
                WHERE nombre_rol = :rol AND clave_permiso = :permiso";
        $stmt = $pdo->prepare($sql);
        $stmt->bindParam(':rol', $rol_usuario);
        $stmt->bindParam(':permiso', $clave_permiso);
        $stmt->execute();

        return $stmt->fetchColumn() > 0;

    } catch (PDOException $e) {
        // En caso de error de BD, por seguridad denegamos, pero el admin (paso 2) ya pasó.
        error_log("Error de BD al verificar permiso: " . $e->getMessage());
        return false;
    }
}
?>