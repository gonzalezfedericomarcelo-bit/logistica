<?php
include 'conexion.php';

// Datos del administrador inicial
$nombre = "Administrador Principal";
$usuario = "admin";
$password = "admin123"; 
$rol = "admin";

// Hashing de la contraseña (¡Automático y correcto!)
$password_hashed = password_hash($password, PASSWORD_DEFAULT);

try {
    $sql = "INSERT INTO usuarios (nombre_completo, usuario, password, rol) 
            VALUES (:nombre, :usuario, :password_hashed, :rol)";
    $stmt = $pdo->prepare($sql);
    $stmt->bindParam(':nombre', $nombre);
    $stmt->bindParam(':usuario', $usuario);
    $stmt->bindParam(':password_hashed', $password_hashed);
    $stmt->bindParam(':rol', $rol);

    if ($stmt->execute()) {
        echo "¡Administrador inicial creado correctamente!<br>";
        echo "Usuario: admin, Contraseña: admin123";
    } else {
        echo "Error al insertar el administrador.";
    }
} catch (PDOException $e) {
    echo "Error de BD: " . $e->getMessage();
}
?>