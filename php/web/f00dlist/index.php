<?php
/**
 * INDEX.PHP (DASHBOARD PRINCIPAL)
 * ================================
 * Pantalla principal donde se visualizan y gestionan los menús.
 * Incluye:
 * - Control de sesión y menú actual.
 * - Generación del menú tentativo.
 * - Visualización de Menú Tentativo y Efectivo.
 * - Lista de ingredientes dinámica.
 * - Gestión de favoritos.
 */

require_once 'config/config.php';
require_once 'config/db.php';
require_once 'includes/auth.php';
require_once 'includes/functions.php';
require_once 'classes/Menu.php';
require_once 'classes/MenuGenerator.php';
require_once 'classes/Recipe.php';
require_once 'classes/Ingredient.php';
require_once 'classes/Tool.php';

// 1. Verificar autenticación
requireLogin();

$userId = getCurrentUserId();
$userData = getCurrentUserData();

// Debugging: Ensure hasRole() is defined
if (!function_exists('hasRole')) {
    die("Error crítico: La función hasRole() no está definida. Verifique la carga de includes/auth.php en el servidor.");
}

// RBAC: Definición de capacidades
$isColaborador = hasRole('admin') || hasRole('colaborador');
$isAdmin = hasRole('admin');

// 2. Obtener o crear el Menú Actual
$menuActual = Menu::getActualForUser($userId);
if (!$menuActual) {
    $menuActual = new Menu(null, $userId, 'Menú Actual', TIPO_ACTUAL);
    $menuActual->save();
}

// 3. Procesar acciones POST (Generar menú)
$menuTentativoData = null;
$mensajeError = '';
$mensajeExito = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    if (!validateCSRFToken($_POST['csrf_token'] ?? '')) {
        $mensajeError = 'Token de seguridad inválido.';
    } else {
        $action = $_POST['action'];

        if ($action === 'generar') {
            $numDias = (int)($_POST['dias'] ?? 7);
            $excludedTools = isset($_POST['herramientas_excluir']) ? array_map('intval', $_POST['herramientas_excluir']) : [];
            $comensales = isset($_POST['comensales']) ? array_map('intval', $_POST['comensales']) : [];

            if (empty($comensales)) {
                $mensajeError = 'Debes seleccionar al menos un comensal.';
            } else {
                // Actualizar comensales del menú actual
                $menuActual->setComensales($comensales);

                // Generar menú tentativo
                $generator = new MenuGenerator($userId, $numDias, $excludedTools, $comensales);
                $result = $generator->generate();

                if ($result['success']) {
                    $menuTentativoData = $result['data'];
                    $mensajeExito = 'Menú tentativo generado correctamente.';
                } else {
                    $mensajeError = $result['message'];
                }
            }
        }
    }
}

// 4. Obtener datos para los formularios
$allUsers = getEligibleComensals();
$allTools = Tool::getAllActive();
$favoritos = Menu::getFavoritesForUser($userId);

// 5. Obtener datos del Menú Efectivo (persistente)
$diasEfectivos = $menuActual->getDiasData();
$comensalesActuales = $menuActual->getComensalesData();

// 6. Obtener lista de ingredientes para el menú efectivo
$listaIngredientes = Ingredient::getShoppingListByMenu($menuActual->getId());

// Definir título de la página
$pageTitle = 'Dashboard';

// Incluir cabecera
require_once 'includes/header.php';
?>

<div class="dashboard-container">
    <!-- Mensajes Flash -->
    <?php if ($mensajeExito): ?>
        <div class="alert alert-success"><?= sanitize($mensajeExito) ?></div>
    <?php endif; ?>
    <?php if ($mensajeError): ?>
        <div class="alert alert-danger"><?= sanitize($mensajeError) ?></div>
    <?php endif; ?>

    <!-- Accesos Administrativos (RBAC) -->
    <?php if ($isColaborador): ?>
        <div class="header-panel" style="border-bottom: 3px solid #3498db; background: #ebf5fb;">
            <h3 style="margin-top:0; color:#2980b9;">🛠️ Panel de Gestión</h3>
            <div style="display:flex; gap:10px; flex-wrap:wrap;">
                <a href="<?= url('admin/recipes.php') ?>" class="btn btn-primary btn-sm">Gestionar Platos</a>
                <a href="<?= url('admin/ingredients.php') ?>" class="btn btn-primary btn-sm">Ingredientes</a>
                <?php if ($isAdmin): ?>
                    <a href="<?= url('admin/users.php') ?>" class="btn btn-danger btn-sm">Gestión de Usuarios (Admin)</a>
                <?php endif; ?>
            </div>
        </div>
    <?php endif; ?>

    <!-- Panel de Control -->
    <div class="header-panel">
        <h2 class="section-title">Generar Nuevo Menú</h2>
        <form method="POST" action="" id="generarForm">
            <?= csrfField() ?>
            <input type="hidden" name="action" value="generar">
            
            <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 20px;">
                <!-- Días -->
                <div>
                    <label><strong>Días a generar:</strong></label>
                    <input type="number" name="dias" min="1" max="14" value="7" class="form-control" style="width:100%; padding:8px;" required>
                </div>

                <!-- Herramientas a excluir -->
                <div>
                    <label><strong>No contenga herramientas:</strong></label>
                    <div style="max-height: 100px; overflow-y: auto; border: 1px solid #ddd; padding: 5px;">
                        <?php foreach ($allTools as $tool): ?>
                            <label class="form-check">
                                <input type="checkbox" name="herramientas_excluir[]" value="<?= $tool['id'] ?>">
                                <?= sanitize($tool['nombre']) ?>
                            </label>
                        <?php endforeach; ?>
                    </div>
                </div>

                <!-- Comensales -->
                <div>
                    <label><strong>Comensales:</strong></label>
                    <div style="max-height: 100px; overflow-y: auto; border: 1px solid #ddd; padding: 5px;">
                        <?php foreach ($allUsers as $user): ?>
                            <label class="form-check">
                                <input type="checkbox" name="comensales[]" value="<?= $user['id'] ?>" 
                                       <?= in_array($user['id'], array_column($comensalesActuales, 'id')) ? 'checked' : '' ?>>
                                <?= sanitize($user['nombre_completo'] ?? $user['username']) ?>
                            </label>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>

            <div style="margin-top: 20px; text-align: right;">
                <button type="submit" class="btn btn-primary" onclick="document.getElementById('loading').style.display='inline'">
                    Generar Menú Tentativo
                </button>
                <span id="loading" class="loading">Generando...</span>
            </div>
        </form>
    </div>

    <!-- Menú Tentativo (Si se generó) -->
    <?php if ($menuTentativoData): ?>
        <div class="header-panel" style="border-left: 5px solid #f39c12;">
            <h2 class="section-title">Menú Tentativo (Propuesta)</h2>
            <p style="font-size:0.9rem; color:#666;">Haz clic en un plato para añadirlo al menú efectivo.</p>
            
            <div class="table-responsive">
                <table>
                    <thead>
                        <tr>
                            <th style="width: 50px;">Nº</th>
                            <th>Comida</th>
                            <th style="width: 150px;">Para</th>
                            <th>Cena</th>
                            <th style="width: 150px;">Para</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php 
                        $maxDias = count($menuTentativoData);
                        for ($i = 1; $i <= $maxDias; $i++): 
                            $diaData = $menuTentativoData[$i] ?? ['comida' => null, 'cena' => null];
                        ?>
                            <tr>
                                <td><?= $i ?></td>
                                
                                <!-- Comida -->
                                <td data-label="Comida">
                                    <?php if ($diaData['comida']): ?>
                                        <div class="tentative-item" data-plato="<?= $diaData['comida']['id_plato'] ?>" onclick="addToEffective(this)">
                                            <strong><?= sanitize($diaData['comida']['plato_nombre']) ?></strong>
                                            <br><small class="<?= getCaloricClass($diaData['comida']['nivel_calorico']) ?>">
                                                <?= formatCalories($diaData['comida']['calorias'] ?? 0) ?>
                                            </small>
                                        </div>
                                    <?php else: ?>
                                        <span style="color:#ccc;">-</span>
                                    <?php endif; ?>
                                </td>

                                <!-- Para Comida (Comensales compatibles) -->
                                <td data-label="Para"><?= !empty($diaData['comida']['para_comensales']) ? implode(', ', $diaData['comida']['para_comensales']) : 'Nadie' ?></td>

                                <!-- Cena -->
                                <td data-label="Cena">
                                    <?php if ($diaData['cena']): ?>
                                        <div class="tentative-item" data-plato="<?= $diaData['cena']['id_plato'] ?>" onclick="addToEffective(this)">
                                            <strong><?= sanitize($diaData['cena']['plato_nombre']) ?></strong>
                                            <br><small class="<?= getCaloricClass($diaData['cena']['nivel_calorico']) ?>">
                                                <?= formatCalories($diaData['cena']['calorias'] ?? 0) ?>
                                            </small>
                                        </div>
                                    <?php else: ?>
                                        <span style="color:#ccc;">-</span>
                                    <?php endif; ?>
                                </td>

                                <!-- Para Cena (Comensales compatibles) -->
                                <td data-label="Para"><?= !empty($diaData['cena']['para_comensales']) ? implode(', ', $diaData['cena']['para_comensales']) : 'Nadie' ?></td>
                            </tr>
                        <?php endfor; ?>
                    </tbody>
                </table>
            </div>
        </div>
    <?php endif; ?>

    <!-- Menú Efectivo -->
    <div class="header-panel" style="border-left: 5px solid #27ae60;">
        <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:15px;">
            <h2 class="section-title" style="margin:0;">Menú Efectivo</h2>
            <div>
                <button class="btn btn-warning btn-sm" onclick="saveAsFavorite()">Guardar como Favorito</button>
                <a href="<?= url('favoritos.php') ?>" class="btn btn-primary btn-sm">Ver Favoritos</a>
            </div>
        </div>
        
        <div class="table-responsive">
            <table id="menuEfectivoTable">
                <thead>
                    <tr>
                        <th style="width: 50px;">Día</th>
                        <th>Comida</th>
                        <th style="width: 60px;">Quitar</th>
                        <th>Cena</th>
                        <th style="width: 60px;">Quitar</th>
                        <th>Herramientas</th>
                    </tr>
                </thead>
                <tbody>
                    <?php 
                    $maxDiasEfectivo = !empty($diasEfectivos) ? max(array_keys($diasEfectivos)) : 0;
                    $totalRows = max($maxDiasEfectivo, $menuTentativoData ? count($menuTentativoData) : 0);
                    
                    for ($d = 1; $d <= $totalRows; $d++): 
                        $comida = $diasEfectivos[$d]['comida'] ?? null;
                        $cena = $diasEfectivos[$d]['cena'] ?? null;

                        $comidaCalorias = 0;
                        $cenaCalorias = 0;
                        
                        // Calcular herramientas del día (simplificado para visualización)
                        $herramientasDiaNombres = [];
                        if ($comida && $comida['id_plato']) {
                            $recipeComida = Recipe::getByPlatoId($comida['id_plato']);
                            if ($recipeComida) {
                                $comidaCalorias = $recipeComida->calculateTotalCalories();
                                foreach ($recipeComida->getTools() as $tool) {
                                    $herramientasDiaNombres[] = $tool->getNombre();
                                }
                            }
                        }
                        if ($cena && $cena['id_plato']) {
                            $recipeCena = Recipe::getByPlatoId($cena['id_plato']);
                            if ($recipeCena) {
                                $cenaCalorias = $recipeCena->calculateTotalCalories();
                                foreach ($recipeCena->getTools() as $tool) {
                                    $herramientasDiaNombres[] = $tool->getNombre();
                                }
                            }
                        }
                        $herramientasStr = implode(", ", array_unique($herramientasDiaNombres));
                        if (empty($herramientasStr)) {
                            $herramientasStr = "N/A";
                        }
                    ?>
                    <tr data-dia="<?= $d ?>">
                        <td><?= $d ?></td>
                        
                        <!-- Comida -->
                        <td data-label="Comida">
                            <?php if ($comida): ?>
                                <a href="<?= url('receta.php?id=' . $comida['id_plato']) ?>" target="_blank">
                                    <?= sanitize($comida['nombre']) ?>
                                </a>
                                <br><small class="<?= getCaloricClass($comida['nivel_calorico']) ?>">
                                    <?= formatCalories($comidaCalorias) ?>
                                </small>
                            <?php else: ?>
                                <span style="color:#ccc;">Hueco libre</span>
                            <?php endif; ?>
                        </td>
                        
                        <!-- Quitar Comida -->
                        <td data-label="Quitar Comida" style="text-align:center;">
                            <?php if ($comida): ?>
                                <button type="button" class="btn-remove-plato" onclick="removeFromEffective(<?= $d ?>, 'comida')">❌</button>
                            <?php endif; ?>
                        </td>

                        <!-- Cena -->
                        <td data-label="Cena">
                            <?php if ($cena): ?>
                                <a href="<?= url('receta.php?id=' . $cena['id_plato']) ?>" target="_blank">
                                    <?= sanitize($cena['nombre']) ?>
                                </a>
                                <br><small class="<?= getCaloricClass($cena['nivel_calorico']) ?>">
                                    <?= formatCalories($cenaCalorias) ?>
                                </small>
                            <?php else: ?>
                                <span style="color:#ccc;">Hueco libre</span>
                            <?php endif; ?>
                        </td>

                        <!-- Quitar Cena -->
                        <td data-label="Quitar Cena" style="text-align:center;">
                            <?php if ($cena): ?>
                                <button type="button" class="btn-remove-plato" onclick="removeFromEffective(<?= $d ?>, 'cena')">❌</button>
                            <?php endif; ?>
                        </td>

                        <!-- Herramientas -->
                        <td data-label="Herramientas"><small><?= $herramientasStr ?></small></td>
                    </tr>
                    <?php endfor; ?>
                </tbody>
            </table>
        </div>
    </div>

    <!-- Lista de Ingredientes -->
    <div class="header-panel">
        <h2 class="section-title">Lista de la Compra</h2>
        <div class="table-responsive">
            <table>
                <thead>
                    <tr>
                        <th>Ingrediente</th>
                        <th style="width: 100px;">Cantidad</th>
                        <th>Supermercado</th>
                        <th style="width: 150px;">Ya en casa</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($listaIngredientes)): ?>
                        <tr><td colspan="4" style="text-align:center;">No hay ingredientes en el menú efectivo.</td></tr>
                    <?php else: ?>
                        <?php foreach ($listaIngredientes as $ing): ?>
                            <tr class="ingredient-row <?= $ing['comprado'] ? 'comprado' : '' ?>">
                                <td data-label="Ingrediente"><?= sanitize($ing['nombre']) ?></td>
                                <td data-label="Cantidad"><?= number_format($ing['total_cantidad'], 0) ?> <?= sanitize($ing['unidad']) ?></td>
                                <td data-label="Supermercado"><?= sanitize($ing['supermercado']) ?></td>
                                <td data-label="Ya en casa">
                                    <input type="checkbox" 
                                           onchange="toggleIngredient(<?= $ing['id'] ?>, this.checked, this)"
                                           <?= $ing['comprado'] ? 'checked' : '' ?>>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
        <div style="text-align:right; margin-top:10px;">
            <a href="<?= url('pdf/generate_list.php') ?>" target="_blank" class="btn btn-success btn-sm">Descargar PDF</a>
        </div>
    </div>

</div>

<!-- Elemento Toast para notificaciones -->
<div id="toast" class="toast-feedback">Actualizado con éxito</div>

<!-- Scripts JS -->
<script>const MENU_ID = <?= $menuActual->getId() ?>; const CSRF_TOKEN = '<?= generateCSRFToken() ?>';</script>
<script src="<?= asset('js/dashboard.js') ?>"></script>

<?php require_once 'includes/footer.php'; ?>