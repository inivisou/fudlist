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

            // Guardar Restricción Dietética (Decisión 44: validar contra whitelist)
            $restriccionInput = $_POST['restriccion_dietetica'] ?? 'normal';
            $dietasValidas = ['normal', 'vegetariano', 'vegan', 'celiaco', 'sin_lactosa'];
            if (!in_array($restriccionInput, $dietasValidas, true)) {
                $restriccionInput = 'normal';
            }
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
            
            // Recargar preferencias
            $ingredientesAvoid = $avoidIds;
            $restriccion = $restriccionInput;
            $exclusivos = $exclIds;
            $preferidos = $prefIds;
        } catch (Exception $e) {
            rollBack();
            $tipoMensaje = 'danger';
            $mensaje = 'Error al guardar preferencias.';
        }
    }
}

$pageTitle = 'Mi Perfil';
require_once 'includes/header.php';
?>

<div class="profile-container">
    <div class="row mb-4">
        <div class="col-12">
            <h1 class="mb-3">Mi Perfil y Preferencias</h1>
            <p class="text-muted">Gestiona tus gustos para que el generador de menús se adapte a ti.</p>
        </div>
    </div>

    <?php if ($mensaje): ?>
        <div class="alert alert-<?= $tipoMensaje ?>"><?= sanitize($mensaje) ?></div>
    <?php endif; ?>

    <form method="POST" action="">
        <?= csrfField() ?>

        <!-- Restricción Dietética -->
        <div class="card mb-4">
            <div class="card-body">
                <h5 class="card-title">Restricción Dietética</h5>
                <div class="mb-3">
                    <label for="restriccion" class="form-label">Tipo de dieta:</label>
                    <select id="restriccion" name="restriccion_dietetica" class="form-select">
                        <option value="normal" <?= $restriccion === 'normal' ? 'selected' : '' ?>>Normal (Sin restricciones)</option>
                        <option value="vegetariano" <?= $restriccion === 'vegetariano' ? 'selected' : '' ?>>Vegetariano</option>
                        <option value="vegan" <?= $restriccion === 'vegan' ? 'selected' : '' ?>>Vegano</option>
                        <option value="celiaco" <?= $restriccion === 'celiaco' ? 'selected' : '' ?>>Sin Gluten (Celiaco)</option>
                        <option value="sin_lactosa" <?= $restriccion === 'sin_lactosa' ? 'selected' : '' ?>>Sin Lactosa</option>
                    </select>
                    <small class="text-muted d-block mt-2">Esto filtrará automáticamente los platos que no sean compatibles.</small>
                </div>
            </div>
        </div>

        <!-- Ingredientes a Evitar -->
        <div class="card mb-4">
            <div class="card-body">
                <h5 class="card-title">Ingredientes a Evitar</h5>
                <div class="checkbox-group">
                    <?php foreach ($allIngredients as $ing): ?>
                        <div class="form-check">
                            <input type="checkbox" id="ing_<?= $ing['id'] ?>" name="ingredientes_avitar[]" value="<?= $ing['id'] ?>" 
                                   class="form-check-input" <?= in_array($ing['id'], $ingredientesAvoid) ? 'checked' : '' ?>>
                            <label class="form-check-label" for="ing_<?= $ing['id'] ?>">
                                <?= sanitize($ing['nombre']) ?>
                            </label>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>

        <!-- Platos Exclusivos -->
        <div class="card mb-4">
            <div class="card-body">
                <h5 class="card-title">Platos Exclusivos (Nunca generar)</h5>
                <div class="checkbox-group">
                    <?php foreach ($allPlatos as $plato): ?>
                        <div class="form-check">
                            <input type="checkbox" id="excl_<?= $plato['id'] ?>" name="platos_exclusivos[]" value="<?= $plato['id'] ?>" 
                                   class="form-check-input" <?= in_array($plato['id'], $exclusivos) ? 'checked' : '' ?>>
                            <label class="form-check-label" for="excl_<?= $plato['id'] ?>">
                                <?= sanitize($plato['nombre']) ?>
                            </label>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>

        <!-- Platos Preferidos -->
        <div class="card mb-4">
            <div class="card-body">
                <h5 class="card-title">Platos Preferidos (Priorizar)</h5>
                <div class="checkbox-group">
                    <?php foreach ($allPlatos as $plato): ?>
                        <div class="form-check">
                            <input type="checkbox" id="pref_<?= $plato['id'] ?>" name="platos_preferidos[]" value="<?= $plato['id'] ?>" 
                                   class="form-check-input" <?= in_array($plato['id'], $preferidos) ? 'checked' : '' ?>>
                            <label class="form-check-label" for="pref_<?= $plato['id'] ?>">
                                <?= sanitize($plato['nombre']) ?>
                            </label>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>

        <!-- Botones -->
        <div class="row">
            <div class="col-12">
                <button type="submit" class="btn btn-primary">Guardar Preferencias</button>
                <a href="<?= url('index.php') ?>" class="btn btn-secondary">Volver al Dashboard</a>
            </div>
        </div>
    </form>
</div>

<?php require_once 'includes/footer.php'; ?>
