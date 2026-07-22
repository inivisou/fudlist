<?php
/**
 * LOGOUT.PHP
 * ==========
 * Cierra la sesión del usuario y redirige al login.
 * Requiere POST + token CSRF (Decisión 45: evita logout forzado por CSRF).
 */

// 1. Cargar configuraciones y funciones necesarias
require_once 'config/config.php';
require_once 'includes/functions.php'; // ← IMPORTANTE: Cargar funciones antes de usarlas
require_once 'includes/auth.php';      // ← Cargar auth (que usa functions)

// 2. Validar método y token CSRF
if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !validateCSRFToken($_POST['csrf_token'] ?? '')) {
    // Si es un GET simple (enlace antiguo) o token inválido, redirigir al inicio sin cerrar sesión
    redirect(url('index.php'));
}

// 3. Llamar a la función de logout definida en auth.php
logoutUser();

// 4. Redirigir al login con un parámetro para mostrar mensaje
redirect(url('login.php?logout=1'));
?>