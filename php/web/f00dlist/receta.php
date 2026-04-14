<?php
/**
 * RECETA.PHP
 * ==========
 * Vista detallada de un plato individual.
 * Muestra: Ingredientes, Herramientas, Calorías, Nivel Calórico y Botón Favorito.
 */

require_once 'config/config.php';
require_once 'config/db.php';
require_once 'includes/auth.php';
require_once 'includes/functions.php';
require_once 'classes/Recipe.php';
require_once 'classes/Ingredient.php';
require_once 'classes/Tool.php';

// 1. Obtener ID del plato
$idPlato = (int)($_GET['id'] ?? 0);

if ($idPlato <= 0) {
    redirect(url('index.php'));
}

// 2. Buscar la receta asociada a este plato (asumimos 1 receta por plato para simplificar, o tomamos la primera)
$sql = "SELECT r.*, p.nombre as plato_nombre, p.nivel_calorico, p.tipo, p.categoria
        FROM recetas r
        JOIN platos p ON r.id_plato = p.id
        WHERE p.id = ? AND p.activo = 1
        LIMIT 1";
$recipeData = fetchOne($sql, [$idPlato]);

if (!$recipeData) {
    // Si no hay receta, mostrar error o redirigir
    die("Plato no encontrado o sin receta asociada.");
}

// 3. Instanciar objeto Recipe para cargar datos relacionados
$recipe = new Recipe($recipeData['id']);
$ingredients = $recipe->getIngredients();
$tools = $recipe->getTools();
$totalCalories = $recipe->calculateTotalCalories();

// 4. Verificar si es favorito del usuario actual
$isFavorite = false;
if (isLoggedIn()) {
    $userId = getCurrentUserId();
    $favSql = "SELECT id FROM platos_favoritos WHERE usuario_id = ? AND id_plato = ?";
    $favRow = fetchOne($favSql, [$userId, $idPlato]);
    $isFavorite = (bool)$favRow;
}

// 5. Procesar acción de favorito (AJAX o POST simple)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'toggle_favorite' && isLoggedIn()) {
    if (!validateCSRFToken($_POST['csrf_token'] ?? '')) {
        die("Token inválido.");
    }
    
    $userId = getCurrentUserId();
    if ($isFavorite) {
        // Quitar favorito
        $sql = "DELETE FROM platos_favoritos WHERE usuario_id = ? AND id_plato = ?";
        executeQuery($sql, [$userId, $idPlato]);
        $isFavorite = false;
    } else {
        // Añadir favorito
        $sql = "INSERT INTO platos_favoritos (usuario_id, id_plato) VALUES (?, ?)";
        executeQuery($sql, [$userId, $idPlato]);
        $isFavorite = true;
    }
    // Redirigir para evitar reenvío
    redirect(url('receta.php?id=' . $idPlato));
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= sanitize($recipeData['plato_nombre']) ?> - f00dlist</title>
    <link rel="stylesheet" href="<?= asset('css/style.css') ?>">
    <link rel="stylesheet" href="<?= asset('css/responsive.css') ?>">
    <link rel="stylesheet" href="<?= asset('css/caloric.css') ?>">
    <style>
        .recipe-container { max-width: 900px; margin: 0 auto; padding: 20px; }
        .recipe-header { background: #fff; padding: 20px; border-radius: 8px; box-shadow: 0 2px 5px rgba(0,0,0,0.05); margin-bottom: 20px; display: flex; justify-content: space-between; align-items: start; }
        .recipe-title h1 { margin: 0; color: #2c3e50; }
        .recipe-meta { margin-top: 10px; color: #7f8c8d; font-size: 0.9rem; }
        .cal-badge { display: inline-block; padding: 5px 10px; border-radius: 15px; font-weight: bold; margin-left: 10px; }
        .cal-bajo { background: #d4edda; color: #155724; }
        .cal-medio { background: #fff3cd; color: #856404; }
        .cal-alto { background: #f8d7da; color: #721c24; }
        .btn-fav { background: none; border: 2px solid #e74c3c; color: #e74c3c; padding: 8px 15px; border-radius: 20px; cursor: pointer; font-weight: bold; transition: all 0.3s; }
        .btn-fav.active { background: #e74c3c; color: white; }
        .btn-fav:hover { transform: scale(1.05); }
        .section-card { background: #fff; padding: 20px; border-radius: 8px; box-shadow: 0 2px 5px rgba(0,0,0,0.05); margin-bottom: 20px; }
        .section-title { font-size: 1.3rem; color: #34495e; margin-bottom: 15px; border-bottom: 2px solid #3498db; padding-bottom: 5px; }
        .ingredient-list, .tool-list { list-style: none; padding: 0; }
        .ingredient-list li, .tool-list li { padding: 8px 0; border-bottom: 1px solid #eee; display: flex; justify-content: space-between; }
        .ingredient-list li:last-child { border-bottom: none; }
        .qty { font-weight: bold; color: #3498db; }
        .recipe-content { line-height: 1.6; color: #333; }
        .back-link { display: inline-block; margin-bottom: 20px; color: #3498db; text-decoration: none; }
        .back-link:hover { text-decoration: underline; }
    </style>
</head>
<body>

<div class="recipe-container">
    <a href="<?= url('index.php') ?>" class="back-link">← Volver al Menú</a>

    <!-- Cabecera del Plato -->
    <div class="recipe-header">
        <div class="recipe-title">
            <h1><?= sanitize($recipeData['plato_nombre']) ?></h1>
            <div class="recipe-meta">
                <span>Tipo: <?= sanitize($recipeData['tipo']) ?></span> | 
                <span>Categoría: <?= sanitize($recipeData['categoria']) ?></span>
                <span class="cal-badge <?= getCaloricClass($recipeData['nivel_calorico']) ?>">
                    <?= getCaloricIcon($recipeData['nivel_calorico']) ?> <?= formatCalories($totalCalories) ?>
                </span>
            </div>
        </div>
        
        <?php if (isLoggedIn()): ?>
            <form method="POST" action="">
                <?= csrfField() ?>
                <input type="hidden" name="action" value="toggle_favorite">
                <button type="submit" class="btn-fav <?= $isFavorite ? 'active' : '' ?>">
                    <?= $isFavorite ? '★ Favorito' : '☆ Añadir a Favoritos' ?>
                </button>
            </form>
        <?php else: ?>
            <a href="<?= url('login.php') ?>" class="btn-fav">Inicia sesión para favoritar</a>
        <?php endif; ?>
    </div>

    <!-- Contenido de la Receta -->
    <?php if ($recipeData['titulo_html']): ?>
        <div class="section-card">
            <div class="recipe-content">
                <?= $recipeData['titulo_html'] ?>
                <?php if ($recipeData['subtitulo_html']): ?>
                    <h3><?= $recipeData['subtitulo_html'] ?></h3>
                <?php endif; ?>
                <?= $recipeData['texto_html'] ?>
                <?php if ($recipeData['enlace']): ?>
                    <p><a href="<?= sanitize($recipeData['enlace']) ?>" target="_blank" style="color:#3498db;">Ver receta original →</a></p>
                <?php endif; ?>
            </div>
        </div>
    <?php endif; ?>

    <!-- Ingredientes -->
    <div class="section-card">
        <h2 class="section-title">Ingredientes</h2>
        <?php if (empty($ingredients)): ?>
            <p>No hay ingredientes registrados para esta receta.</p>
        <?php else: ?>
            <ul class="ingredient-list">
                <?php foreach ($ingredients as $ing): ?>
                    <li>
                        <span><?= sanitize($ing->getNombre()) ?></span>
                        <span class="qty"><?= number_format($ing->cantidad, 0) ?> <?= sanitize($ing->unidad) ?></span>
                    </li>
                <?php endforeach; ?>
            </ul>
        <?php endif; ?>
    </div>

    <!-- Herramientas -->
    <div class="section-card">
        <h2 class="section-title">Herramientas Necesarias</h2>
        <?php if (empty($tools)): ?>
            <p>No se requieren herramientas especiales.</p>
        <?php else: ?>
            <ul class="tool-list">
                <?php foreach ($tools as $tool): ?>
                    <li>
                        <span><?= sanitize($tool->getNombre()) ?></span>
                        <small style="color:#7f8c8d;"><?= sanitize($tool->getDescripcion()) ?></small>
                    </li>
                <?php endforeach; ?>
            </ul>
        <?php endif; ?>
    </div>

</div>

</body>
</html>