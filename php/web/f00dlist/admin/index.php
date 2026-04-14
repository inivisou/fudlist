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

<div class="admin-dashboard">
    <h1 style="margin-bottom: 30px;">Panel de Administración</h1>

    <!-- Tarjetas de Estadísticas -->
    <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 20px; margin-bottom: 40px;">
        <div style="background: white; padding: 20px; border-radius: 8px; box-shadow: 0 2px 5px rgba(0,0,0,0.05); text-align: center;">
            <h3 style="margin: 0; color: #3498db; font-size: 2rem;"><?= $totalUsers ?></h3>
            <p style="margin: 5px 0 0; color: #7f8c8d;">Usuarios Activos</p>
        </div>
        <div style="background: white; padding: 20px; border-radius: 8px; box-shadow: 0 2px 5px rgba(0,0,0,0.05); text-align: center;">
            <h3 style="margin: 0; color: #27ae60; font-size: 2rem;"><?= $totalPlatos ?></h3>
            <p style="margin: 5px 0 0; color: #7f8c8d;">Platos Disponibles</p>
        </div>
        <div style="background: white; padding: 20px; border-radius: 8px; box-shadow: 0 2px 5px rgba(0,0,0,0.05); text-align: center;">
            <h3 style="margin: 0; color: #f39c12; font-size: 2rem;"><?= $totalHerramientas ?></h3>
            <p style="margin: 5px 0 0; color: #7f8c8d;">Herramientas</p>
        </div>
        <div style="background: white; padding: 20px; border-radius: 8px; box-shadow: 0 2px 5px rgba(0,0,0,0.05); text-align: center;">
            <h3 style="margin: 0; color: #9b59b6; font-size: 2rem;"><?= $totalIngredientes ?></h3>
            <p style="margin: 5px 0 0; color: #7f8c8d;">Ingredientes</p>
        </div>
    </div>

    <!-- Acciones Rápidas -->
    <div style="background: white; padding: 20px; border-radius: 8px; box-shadow: 0 2px 5px rgba(0,0,0,0.05);">
        <h2 style="border-bottom: 2px solid #3498db; padding-bottom: 10px; margin-bottom: 20px;">Gestión de Contenido</h2>
        
        <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(250px, 1fr)); gap: 20px;">
            <a href="tools.php" style="display: block; padding: 15px; background: #f8f9fa; border: 1px solid #ddd; border-radius: 5px; text-decoration: none; color: #333; transition: all 0.3s;">
                <strong>🛠️ Gestionar Herramientas</strong>
                <p style="margin: 5px 0 0; font-size: 0.9rem; color: #666;">Añadir, editar o eliminar herramientas (Olla, Sartén, etc.).</p>
            </a>

            <a href="ingredients.php" style="display: block; padding: 15px; background: #f8f9fa; border: 1px solid #ddd; border-radius: 5px; text-decoration: none; color: #333; transition: all 0.3s;">
                <strong>🥕 Gestionar Ingredientes</strong>
                <p style="margin: 5px 0 0; font-size: 0.9rem; color: #666;">Catálogo de ingredientes, calorías y supermercados.</p>
            </a>

            <a href="recipes.php" style="display: block; padding: 15px; background: #f8f9fa; border: 1px solid #ddd; border-radius: 5px; text-decoration: none; color: #333; transition: all 0.3s;">
                <strong>🍲 Gestionar Recetas y Platos</strong>
                <p style="margin: 5px 0 0; font-size: 0.9rem; color: #666;">Crear platos, asignar ingredientes y herramientas.</p>
            </a>

            <a href="users.php" style="display: block; padding: 15px; background: #f8f9fa; border: 1px solid #ddd; border-radius: 5px; text-decoration: none; color: #333; transition: all 0.3s;">
                <strong>👥 Gestionar Usuarios</strong>
                <p style="margin: 5px 0 0; font-size: 0.9rem; color: #666;">Ver usuarios, asignar roles y permisos.</p>
            </a>
        </div>
    </div>
</div>

<?php require_once '../includes/footer.php'; ?>