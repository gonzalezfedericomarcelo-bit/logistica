<?php
// Este archivo centraliza la lógica de inicio de sesión y conexión a la BD
// para todas las páginas protegidas.

// 1. Inicia la sesión de forma segura si aún no está iniciada
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// 2. Verifica si el usuario está logueado
if (!isset($_SESSION['usuario_id'])) {
    // Redirige al login si no está autenticado
    header("Location: login.php");
    exit();
}

// 3. Incluye la conexión a la base de datos (siempre necesaria en las páginas protegidas)
include 'conexion.php'; 

// Después de esta inclusión, ya tendrá disponibles:
// - $_SESSION con los datos del usuario.
// - La variable $pdo (para consultas a la BD).
?>