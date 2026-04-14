<?php
/**
 * LOGOUT.PHP
 * ==========
 * Cierra la sesión del usuario y redirige al login.
 */

// 1. Cargar configuraciones y funciones necesarias
require_once 'config/config.php';
require_once 'includes/functions.php'; // ← IMPORTANTE: Cargar funciones antes de usarlas
require_once 'includes/auth.php';      // ← Cargar auth (que usa functions)

// 2. Llamar a la función de logout definida en auth.php
logoutUser();

// 3. Redirigir al login con un parámetro para mostrar mensaje
redirect(url('login.php?logout=1'));
?>