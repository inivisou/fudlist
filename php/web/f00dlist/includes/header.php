<?php
/**
 * HEADER.PHP
 * ==========
 * Cabecera común para todas las páginas.
 * Incluye: Meta tags, CSS, Navegación y cierre de divs de contenedor.
 */

// Asegurarse de que config.php esté cargado (si no se cargó antes)
if (!defined('APP_NAME')) {
    require_once __DIR__ . '/../config/config.php';
}
if (!function_exists('isLoggedIn')) {
    require_once __DIR__ . '/auth.php';
}
if (!function_exists('asset')) {
    require_once __DIR__ . '/functions.php';
}

// Variables opcionales para personalizar el título por página
$pageTitle = $pageTitle ?? 'f00dlist';
$currentPath = $_SERVER['SCRIPT_NAME'] ?? '';
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= sanitize($pageTitle) ?> - f00dlist</title>
    
    <!-- CSS -->
    <link rel="stylesheet" href="<?= asset('css/style.css') ?>">
    <link rel="stylesheet" href="<?= asset('css/responsive.css') ?>">
    <link rel="stylesheet" href="<?= asset('css/caloric.css') ?>">
    
    <!-- Favicon (opcional, puedes añadirlo después) -->
    <link rel="icon" href="<?= asset('img/favicon.ico') ?>" type="image/x-icon">

    <style>
        /* Estilos para el Menú Hamburguesa */
        .navbar { background: #2c3e50; padding: 15px 0; box-shadow: 0 2px 5px rgba(0,0,0,0.1); position: relative; z-index: 1000; }
        .nav-container { max-width: 1200px; margin: 0 auto; display: flex; justify-content: space-between; align-items: center; padding: 0 20px; }
        .nav-logo { color: white; text-decoration: none; font-size: 1.5rem; font-weight: bold; }
        .nav-menu { display: flex; gap: 15px; align-items: center; }
        .nav-menu a { color: #ecf0f1; text-decoration: none; transition: color 0.3s; }
        .nav-menu a:hover { color: #3498db; }

        /* Checkbox oculto para el toggle */
        #menu-toggle { display: none; }
        .hamburger { display: none; flex-direction: column; cursor: pointer; gap: 4px; }
        .hamburger span { width: 25px; height: 3px; background: white; border-radius: 2px; transition: 0.3s; }

        /* Responsive */
        @media screen and (max-width: 768px) {
            .hamburger { display: flex; }
            .nav-menu {
                display: none;
                position: absolute;
                top: 100%;
                left: 0;
                width: 100%;
                background: #2c3e50;
                flex-direction: column;
                padding: 20px;
                gap: 20px;
                box-shadow: 0 4px 5px rgba(0,0,0,0.2);
            }
            /* Mostrar menú cuando el checkbox está activo */
            #menu-toggle:checked ~ .nav-menu { display: flex; }
            
            .nav-menu a, .nav-menu span { 
                width: 100%; 
                text-align: left; 
                padding: 5px 0;
                font-size: 1.1rem;
            }
            .nav-user-info { border-top: 1px solid #34495e; padding-top: 15px; margin-top: 5px; }
        }
    </style>
</head>
<body>

<!-- Navegación Superior -->
<nav class="navbar">
    <div class="nav-container">
        <a href="<?= url('index.php') ?>" class="nav-logo">f00dlist</a>
        
        <!-- Toggle para móvil -->
        <input type="checkbox" id="menu-toggle">
        <label for="menu-toggle" class="hamburger">
            <span></span>
            <span></span>
            <span></span>
        </label>

        <div class="nav-menu">
            <?php if (isLoggedIn()): ?>
                <a href="<?= url('index.php') ?>" style="<?= $currentPath === '/f00dlist/index.php' ? 'font-weight:bold;' : '' ?>">Dashboard</a>
                <a href="<?= url('favoritos.php') ?>" style="<?= strpos($currentPath, 'favoritos') !== false ? 'font-weight:bold;' : '' ?>">Favoritos</a>
                <a href="<?= url('perfil.php') ?>" style="<?= strpos($currentPath, 'perfil') !== false ? 'font-weight:bold;' : '' ?>">Perfil</a>
                
                <?php if (isAdmin()): ?>
                    <a href="<?= url('admin/index.php') ?>" style="color: #f39c12; text-decoration: none; font-weight: bold; border: 1px solid #f39c12; padding: 5px 10px; border-radius: 4px;">Admin</a>
                <?php endif; ?>

                <span class="nav-user-info" style="color: #bdc3c7; font-size: 0.9rem;">Hola, <?= sanitize(getCurrentUsername()) ?></span>
                <a href="<?= url('logout.php') ?>" style="color: #e74c3c; text-decoration: none; font-weight: bold;">Salir</a>
            <?php else: ?>
                <a href="<?= url('login.php') ?>">Login</a>
                <a href="<?= url('register.php') ?>" style="background: #3498db; color: white; padding: 8px 15px; border-radius: 4px; text-decoration: none;">Registrarse</a>
            <?php endif; ?>
        </div>
    </div>
</nav>

<!-- Contenedor Principal -->
<div style="max-width: 1200px; margin: 20px auto; padding: 0 20px;">