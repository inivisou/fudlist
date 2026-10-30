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

// 4.1 Verificar roles para edición (RBAC)
// Asumimos las funciones hasRole() definidas en auth.php según el roadmap
$canEdit = isLoggedIn() && (hasRole('admin') || hasRole('colaborador'));
$isAdmin = isLoggedIn() && hasRole('admin');

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

$pageTitle = sanitize($recipeData['plato_nombre']);
require_once 'includes/header.php';
?>

<div class="recipe-container">
    <a href="<?= url('index.php') ?>" class="btn btn-secondary mb-3">← Volver al Menú</a>

    <!-- Cabecera del Plato -->
    <div class="card mb-4">
        <div class="card-body">
            <div class="row">
                <div class="col-md-8">
                    <h1 class="card-title"><?= sanitize($recipeData['plato_nombre']) ?></h1>
                    <p class="text-muted">
                        <strong>Tipo:</strong> <?= sanitize($recipeData['tipo']) ?> | 
                        <strong>Categoría:</strong> <?= sanitize($recipeData['categoria']) ?>
                    </p>
                    <span class="cal-badge <?= getCaloricClass($recipeData['nivel_calorico']) ?>">
                        <?= getCaloricIcon($recipeData['nivel_calorico']) ?> <?= formatCalories($totalCalories) ?>
                    </span>
                </div>
                <div class="col-md-4 text-end">
                    <?php if (isLoggedIn()): ?>
                        <form method="POST" action="" class="d-inline">
                            <?= csrfField() ?>
                            <input type="hidden" name="action" value="toggle_favorite">
                            <button type="submit" class="btn btn-lg <?= $isFavorite ? 'btn-danger' : 'btn-outline-danger' ?>">
                                <?= $isFavorite ? '★ Favorito' : '☆ Añadir a Favoritos' ?>
                            </button>
                        </form>
                        
                        <?php if ($canEdit): ?>
                            <a href="<?= url('admin/editar_receta.php?id=' . $recipe->getId()) ?>" class="btn btn-warning">📝 Editar</a>
                        <?php endif; ?>
                    <?php else: ?>
                        <p class="text-muted"><a href="<?= url('login.php') ?>">Inicia sesión</a> para añadir a favoritos.</p>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <!-- Ingredientes -->
    <div class="card mb-4">
        <div class="card-body">
            <h5 class="card-title">Ingredientes</h5>
            <?php if (empty($ingredients)): ?>
                <p class="text-muted">No hay ingredientes registrados.</p>
            <?php else: ?>
                <ul class="list-unstyled">
                    <?php foreach ($ingredients as $ing): ?>
                        <li class="mb-2">
                            <strong><?= sanitize($ing['nombre']) ?></strong>
                            <span class="text-muted">- <?= number_format($ing['cantidad'], 2) ?> <?= sanitize($ing['unidad']) ?></span>
                        </li>
                    <?php endforeach; ?>
                </ul>
            <?php endif; ?>
        </div>
    </div>

    <!-- Herramientas -->
    <div class="card mb-4">
        <div class="card-body">
            <h5 class="card-title">Herramientas Necesarias</h5>
            <?php if (empty($tools)): ?>
                <p class="text-muted">No hay herramientas registradas.</p>
            <?php else: ?>
                <ul class="list-unstyled">
                    <?php foreach ($tools as $tool): ?>
                        <li class="mb-2">
                            <strong><?= sanitize($tool->getNombre()) ?></strong>
                            <br><small class="text-muted"><?= sanitize($tool->getDescripcion()) ?></small>
                        </li>
                    <?php endforeach; ?>
                </ul>
            <?php endif; ?>
        </div>
    </div>

    <!-- Instrucciones -->
    <div class="card mb-4">
        <div class="card-body">
            <h5 class="card-title">Instrucciones</h5>
            <div class="recipe-content">
                <?php if (!empty($recipeData['texto'])): ?>
                    <?= $recipeData['texto'] ?>
                <?php else: ?>
                    <p class="text-muted">No hay instrucciones registradas.</p>
                <?php endif; ?>
            </div>
            
            <?php if (!empty($recipeData['enlace'])): ?>
                <p class="mt-3">
                    <a href="<?= sanitize($recipeData['enlace']) ?>" target="_blank" class="btn btn-info btn-sm">
                        🔗 Ver receta original
                    </a>
                </p>
            <?php endif; ?>
        </div>
    </div>
</div>

<?php require_once 'includes/footer.php'; ?>
