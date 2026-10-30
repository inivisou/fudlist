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
$currentScript = basename($currentPath);
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= sanitize($pageTitle) ?> - f00dlist</title>
    
    <!-- Bootstrap 5.3 CDN -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    
    <!-- CSS Propios -->
    <link rel="stylesheet" href="<?= asset('css/style.css') ?>">
    <link rel="stylesheet" href="<?= asset('css/responsive.css') ?>">
    <link rel="stylesheet" href="<?= asset('css/caloric.css') ?>">
    <link rel="stylesheet" href="<?= asset('css/index.css') ?>">
    
    <!-- Favicon -->
    <link rel="icon" href="<?= asset('img/favicon.ico') ?>" type="image/x-icon">

    <style>
        .navbar-dark .nav-link.active-page { font-weight: bold; }
    </style>
</head>
<body>

<!-- Navbar Bootstrap -->
<nav class="navbar navbar-expand-md navbar-dark bg-dark">
    <div class="container">
        <a class="navbar-brand" href="<?= url('index.php') ?>">f00dlist</a>
        
        <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navMenu">
            <span class="navbar-toggler-icon"></span>
        </button>

        <div class="collapse navbar-collapse" id="navMenu">
            <?php if (isLoggedIn()): ?>
                <ul class="navbar-nav me-auto">
                    <li class="nav-item">
                        <a class="nav-link <?= $currentScript === 'index.php' ? 'active-page' : '' ?>" href="<?= url('index.php') ?>">Dashboard</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link <?= $currentScript === 'favoritos.php' ? 'active-page' : '' ?>" href="<?= url('favoritos.php') ?>">Favoritos</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link <?= $currentScript === 'perfil.php' ? 'active-page' : '' ?>" href="<?= url('perfil.php') ?>">Perfil</a>
                    </li>
                    <?php if (isAdmin()): ?>
                        <li class="nav-item">
                            <a class="nav-link text-warning fw-bold" href="<?= url('admin/index.php') ?>">Admin</a>
                        </li>
                    <?php endif; ?>
                </ul>
                <ul class="navbar-nav">
                    <li class="nav-item">
                        <span class="nav-link disabled text-secondary small">Hola, <?= sanitize(getCurrentUsername()) ?></span>
                    </li>
                    <li class="nav-item">
                        <form method="POST" action="<?= url('logout.php') ?>" class="d-inline">
                            <?= csrfField() ?>
                            <button type="submit" class="btn btn-link nav-link text-danger fw-bold">Salir</button>
                        </form>
                    </li>
                </ul>
            <?php else: ?>
                <ul class="navbar-nav ms-auto">
                    <li class="nav-item">
                        <a class="nav-link" href="<?= url('login.php') ?>">Login</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link btn btn-primary text-white px-3 ms-2" href="<?= url('register.php') ?>">Registrarse</a>
                    </li>
                </ul>
            <?php endif; ?>
        </div>
    </div>
</nav>

<!-- Contenedor Principal -->
<div class="container my-4">