<?php
/**
 * ADMIN/INDEX.PHP
 * ===============
 * Panel de control del administrador.
 * Estadísticas rápidas y enlaces de gestión.
 */

require_once '../config/config.php';
require_once '../config/db.php';
require_once '../includes/auth.php';
require_once '../includes/functions.php';

// Solo admins
requireAdmin();

// Estadísticas
$totalUsers = rowCount("SELECT COUNT(*) as c FROM users WHERE activo = 1");
$totalPlatos = rowCount("SELECT COUNT(*) as c FROM platos WHERE activo = 1");
$totalHerramientas = rowCount("SELECT COUNT(*) as c FROM herramientas WHERE activo = 1");
$totalIngredientes = rowCount("SELECT COUNT(*) as c FROM ingredientes WHERE activo = 1");
$totalMenus = rowCount("SELECT COUNT(*) as c FROM menus WHERE tipo = 'actual'"); // Menús activos en uso

$pageTitle = 'Panel de Administración';
require_once '../includes/header.php';
?>

<div class="admin-page">
    <h1 class="mb-4">Panel de Administración</h1>

    <!-- Tarjetas de Estadísticas -->
    <div class="row mb-5">
        <div class="col-md-6 col-lg-3 mb-4">
            <div class="card text-center">
                <div class="card-body">
                    <h3 class="card-title text-primary"><?= $totalUsers ?></h3>
                    <p class="card-text text-muted">Usuarios Activos</p>
                </div>
            </div>
        </div>
        <div class="col-md-6 col-lg-3 mb-4">
            <div class="card text-center">
                <div class="card-body">
                    <h3 class="card-title text-success"><?= $totalPlatos ?></h3>
                    <p class="card-text text-muted">Platos Disponibles</p>
                </div>
            </div>
        </div>
        <div class="col-md-6 col-lg-3 mb-4">
            <div class="card text-center">
                <div class="card-body">
                    <h3 class="card-title text-warning"><?= $totalHerramientas ?></h3>
                    <p class="card-text text-muted">Herramientas</p>
                </div>
            </div>
        </div>
        <div class="col-md-6 col-lg-3 mb-4">
            <div class="card text-center">
                <div class="card-body">
                    <h3 class="card-title text-danger"><?= $totalIngredientes ?></h3>
                    <p class="card-text text-muted">Ingredientes</p>
                </div>
            </div>
        </div>
    </div>

    <!-- Acciones Rápidas -->
    <div class="card">
        <div class="card-body">
            <h2 class="card-title border-bottom pb-3 mb-4">Gestión de Contenido</h2>

            <div class="row">
                <div class="col-md-6 col-lg-3 mb-4">
                    <a href="tools.php" class="card text-decoration-none h-100">
                        <div class="card-body">
                            <h5 class="card-title">🛠️ Gestionar Herramientas</h5>
                            <p class="card-text text-muted small">Añadir, editar o eliminar herramientas (Olla, Sartén, etc.).</p>
                        </div>
                    </a>
                </div>

                <div class="col-md-6 col-lg-3 mb-4">
                    <a href="ingredients.php" class="card text-decoration-none h-100">
                        <div class="card-body">
                            <h5 class="card-title">🥕 Gestionar Ingredientes</h5>
                            <p class="card-text text-muted small">Catálogo de ingredientes, calorías y supermercados.</p>
                        </div>
                    </a>
                </div>

                <div class="col-md-6 col-lg-3 mb-4">
                    <a href="recipes.php" class="card text-decoration-none h-100">
                        <div class="card-body">
                            <h5 class="card-title">🍲 Gestionar Recetas y Platos</h5>
                            <p class="card-text text-muted small">Crear platos, asignar ingredientes y herramientas.</p>
                        </div>
                    </a>
                </div>

                <div class="col-md-6 col-lg-3 mb-4">
                    <a href="users.php" class="card text-decoration-none h-100">
                        <div class="card-body">
                            <h5 class="card-title">👥 Gestionar Usuarios</h5>
                            <p class="card-text text-muted small">Ver usuarios, asignar roles y permisos.</p>
                        </div>
                    </a>
                </div>
            </div>
        </div>
    </div>
</div>

<?php require_once '../includes/footer.php'; ?>
