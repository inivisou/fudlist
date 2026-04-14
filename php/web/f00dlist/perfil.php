<?php
/**
 * PERFIL.PHP
 * ==========
 * Gestión de preferencias del usuario:
 * - Ingredientes a evitar
 * - Restricciones dietéticas
 * - Platos exclusivos
 * - Platos preferidos
 */

require_once 'config/config.php';
require_once 'config/db.php';
require_once 'includes/auth.php';
require_once 'includes/functions.php';
require_once 'classes/Ingredient.php';
require_once 'classes/Tool.php';

// 1. Verificar autenticación
requireLogin();

$userId = getCurrentUserId();
$mensaje = '';
$tipoMensaje = '';

// 2. Obtener preferencias actuales
$preferencias = [];
$sql = "SELECT clave, valor FROM preferencias_usuario WHERE usuario_id = ?";
$rows = fetchAll($sql, [$userId]);
foreach ($rows as $row) {
    $preferencias[$row['clave']] = $row['valor'];
}

// Valores por defecto si no existen
$ingredientesAvoid = json_decode($preferencias['ingredientes_avitar'] ?? '[]', true) ?: [];
$restriccion = $preferencias['restriccion_dietetica'] ?? 'normal';
$exclusivos = json_decode($preferencias['platos_exclusivos'] ?? '[]', true) ?: [];
$preferidos = json_decode($preferencias['platos_preferidos'] ?? '[]', true) ?: [];

// 3. Obtener datos para los selects
$allIngredients = Ingredient::getAllActive();
$allPlatos = fetchAll("SELECT id, nombre FROM platos WHERE activo = 1 ORDER BY nombre ASC");

// 4. Procesar formulario POST
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!validateCSRFToken($_POST['csrf_token'] ?? '')) {
        $tipoMensaje = 'danger';
        $mensaje = 'Token de seguridad inválido.';
    } else {
        try {
            beginTransaction();

            // Guardar Ingredientes a evitar
            $avoidIds = isset($_POST['ingredientes_avitar']) ? array_map('intval', $_POST['ingredientes_avitar']) : [];
            $sql = "INSERT INTO preferencias_usuario (usuario_id, clave, valor, tipo_dato) 
                    VALUES (?, 'ingredientes_avitar', ?, 'json')
                    ON DUPLICATE KEY UPDATE valor = ?";
            executeQuery($sql, [$userId, json_encode($avoidIds), json_encode($avoidIds)]);

            // Guardar Restricción Dietética
            $restriccionInput = $_POST['restriccion_dietetica'] ?? 'normal';
            $sql = "INSERT INTO preferencias_usuario (usuario_id, clave, valor, tipo_dato) 
                    VALUES (?, 'restriccion_dietetica', ?, 'string')
                    ON DUPLICATE KEY UPDATE valor = ?";
            executeQuery($sql, [$userId, $restriccionInput, $restriccionInput]);

            // Guardar Platos Exclusivos
            $exclIds = isset($_POST['platos_exclusivos']) ? array_map('intval', $_POST['platos_exclusivos']) : [];
            $sql = "INSERT INTO preferencias_usuario (usuario_id, clave, valor, tipo_dato) 
                    VALUES (?, 'platos_exclusivos', ?, 'json')
                    ON DUPLICATE KEY UPDATE valor = ?";
            executeQuery($sql, [$userId, json_encode($exclIds), json_encode($exclIds)]);

            // Guardar Platos Preferidos
            $prefIds = isset($_POST['platos_preferidos']) ? array_map('intval', $_POST['platos_preferidos']) : [];
            $sql = "INSERT INTO preferencias_usuario (usuario_id, clave, valor, tipo_dato) 
                    VALUES (?, 'platos_preferidos', ?, 'json')
                    ON DUPLICATE KEY UPDATE valor = ?";
            executeQuery($sql, [$userId, json_encode($prefIds), json_encode($prefIds)]);

            commit();
            $tipoMensaje = 'success';
            $mensaje = 'Preferencias guardadas correctamente.';

            // Recargar datos
            $ingredientesAvoid = $avoidIds;
            $restriccion = $restriccionInput;
            $exclusivos = $exclIds;
            $preferidos = $prefIds;

        } catch (Exception $e) {
            rollback();
            if (DEBUG_MODE) {
                error_log("Error en perfil: " . $e->getMessage());
            }
            $tipoMensaje = 'danger';
            $mensaje = 'Error al guardar preferencias.';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Mi Perfil - f00dlist</title>
    <link rel="stylesheet" href="<?= asset('css/style.css') ?>">
    <link rel="stylesheet" href="<?= asset('css/responsive.css') ?>">
    <style>
        .profile-container { max-width: 800px; margin: 0 auto; padding: 20px; }
        .header-panel { background: #fff; padding: 20px; border-radius: 8px; box-shadow: 0 2px 5px rgba(0,0,0,0.05); margin-bottom: 20px; }
        .section-card { background: #fff; padding: 20px; border-radius: 8px; box-shadow: 0 2px 5px rgba(0,0,0,0.05); margin-bottom: 20px; }
        .section-title { font-size: 1.3rem; color: #34495e; margin-bottom: 15px; border-bottom: 2px solid #3498db; padding-bottom: 5px; }
        .form-group { margin-bottom: 15px; }
        .form-label { display: block; margin-bottom: 5px; font-weight: 600; color: #34495e; }
        .form-select, .form-control { width: 100%; padding: 8px; border: 1px solid #ddd; border-radius: 4px; box-sizing: border-box; }
        .checkbox-group { max-height: 150px; overflow-y: auto; border: 1px solid #eee; padding: 10px; border-radius: 4px; }
        .checkbox-item { display: flex; align-items: center; margin-bottom: 5px; }
        .checkbox-item input { margin-right: 8px; }
        .btn-primary { background: #3498db; color: white; border: none; padding: 10px 20px; border-radius: 4px; cursor: pointer; font-size: 1rem; }
        .btn-secondary { background: #95a5a6; color: white; border: none; padding: 10px 20px; border-radius: 4px; cursor: pointer; font-size: 1rem; text-decoration: none; display: inline-block; }
        .alert { padding: 15px; margin-bottom: 20px; border-radius: 5px; }
        .alert-success { background: #d4edda; color: #155724; border: 1px solid #c3e6cb; }
        .alert-danger { background: #f8d7da; color: #721c24; border: 1px solid #f5c6cb; }
    </style>
</head>
<body>

<div class="profile-container">
    <div class="header-panel">
        <h1>Mi Perfil y Preferencias</h1>
        <p>Gestiona tus gustos para que el generador de menús se adapte a ti.</p>
        <a href="<?= url('index.php') ?>" class="btn-secondary">← Volver al Dashboard</a>
    </div>

    <?php if ($mensaje): ?>
        <div class="alert alert-<?= $tipoMensaje ?>"><?= sanitize($mensaje) ?></div>
    <?php endif; ?>

    <form method="POST" action="">
        <?= csrfField() ?>

        <!-- Restricción Dietética -->
        <div class="section-card">
            <h2 class="section-title">Restricción Dietética</h2>
            <div class="form-group">
                <label class="form-label">Tipo de dieta:</label>
                <select name="restriccion_dietetica" class="form-select">
                    <option value="normal" <?= $restriccion === 'normal' ? 'selected' : '' ?>>Normal (Sin restricciones)</option>
                    <option value="vegetariano" <?= $restriccion === 'vegetariano' ? 'selected' : '' ?>>Vegetariano</option>
                    <option value="vegan" <?= $restriccion === 'vegan' ? 'selected' : '' ?>>Vegano</option>
                    <option value="celiaco" <?= $restriccion === 'celiaco' ? 'selected' : '' ?>>Sin Gluten (Celiaco)</option>
                    <option value="sin_lactosa" <?= $restriccion === 'sin_lactosa' ? 'selected' : '' ?>>Sin Lactosa</option>
                </select>
                <small style="color:#666;">Esto filtrará automáticamente los platos que no sean compatibles.</small>
            </div>
        </div>

        <!-- Ingredientes a Evitar -->
        <div class="section-card">
            <h2 class="section-title">Ingredientes a Evitar</h2>
            <div class="form-group">
                <label class="form-label">Selecciona los ingredientes que NO quieres consumir:</label>
                <div class="checkbox-group">
                    <?php foreach ($allIngredients as $ing): ?>
                        <label class="checkbox-item">
                            <input type="checkbox" name="ingredientes_avitar[]" value="<?= $ing['id'] ?>" 
                                   <?= in_array($ing['id'], $ingredientesAvoid) ? 'checked' : '' ?>>
                            <?= sanitize($ing['nombre']) ?>
                        </label>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>

        <!-- Platos Exclusivos -->
        <div class="section-card">
            <h2 class="section-title">Platos Exclusivos</h2>
            <div class="form-group">
                <label class="form-label">Platos que solo TÚ puedes comer (no se compartirán con otros comensales):</label>
                <div class="checkbox-group">
                    <?php foreach ($allPlatos as $plato): ?>
                        <label class="checkbox-item">
                            <input type="checkbox" name="platos_exclusivos[]" value="<?= $plato['id'] ?>" 
                                   <?= in_array($plato['id'], $exclusivos) ? 'checked' : '' ?>>
                            <?= sanitize($plato['nombre']) ?>
                        </label>
                    <?php endforeach; ?>
                </div>
                <small style="color:#666;">Si seleccionas un plato exclusivo, solo aparecerá en tu menú si eres el único comensal o si se genera específicamente para ti.</small>
            </div>
        </div>

        <!-- Platos Preferidos -->
        <div class="section-card">
            <h2 class="section-title">Platos Preferidos</h2>
            <div class="form-group">
                <label class="form-label">Platos que te gustan mucho (tendrán prioridad al generar):</label>
                <div class="checkbox-group">
                    <?php foreach ($allPlatos as $plato): ?>
                        <label class="checkbox-item">
                            <input type="checkbox" name="platos_preferidos[]" value="<?= $plato['id'] ?>" 
                                   <?= in_array($plato['id'], $preferidos) ? 'checked' : '' ?>>
                            <?= sanitize($plato['nombre']) ?>
                        </label>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>

        <div style="text-align: right; margin-top: 20px;">
            <button type="submit" class="btn-primary">Guardar Preferencias</button>
        </div>
    </form>
</div>

</body>
</html>