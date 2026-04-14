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
require_once 'classes/User.php';
require_once 'classes/Menu.php';
require_once 'classes/MenuGenerator.php';
require_once 'classes/Recipe.php';
require_once 'classes/Ingredient.php';
require_once 'classes/Tool.php';

// 1. Verificar autenticación
requireLogin();

$userId = getCurrentUserId();
$userData = getCurrentUserData();

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
$allUsers = fetchAll("SELECT id, username, nombre_completo FROM users WHERE activo = 1 ORDER BY nombre_completo ASC");
$allTools = Tool::getAllActive();
$favoritos = Menu::getFavoritesForUser($userId);

// 5. Obtener datos del Menú Efectivo (persistente)
$diasEfectivos = $menuActual->getDiasData();
$comensalesActuales = $menuActual->getComensalesData();

// 6. Obtener lista de ingredientes para el menú efectivo
$listaIngredientes = [];
if (!empty($diasEfectivos)) {
    $sql = "SELECT i.id, i.nombre, i.supermercado, SUM(ri.cantidad) as total_cantidad, ri.unidad
            FROM menu_dias md
            JOIN platos p ON md.id_plato = p.id
            JOIN recetas r ON p.id = r.id_plato
            JOIN recetas_ingredientes ri ON r.id = ri.id_receta
            JOIN ingredientes i ON ri.id_ingrediente = i.id
            WHERE md.id_menu = ? AND i.activo = 1
            GROUP BY i.id, i.nombre, i.supermercado, ri.unidad
            ORDER BY i.supermercado, i.nombre";
    
    $rawIngredients = fetchAll($sql, [$menuActual->getId()]);
    
    // Marcar cuáles están comprados
    $compradosSql = "SELECT id_ingrediente FROM ingredientes_comprados WHERE id_menu = ? AND comprado = 1";
    $comprados = fetchAll($compradosSql, [$menuActual->getId()]);
    $compradosIds = array_column($comprados, 'id_ingrediente');

    foreach ($rawIngredients as $ing) {
        $ing['comprado'] = in_array($ing['id'], $compradosIds);
        $listaIngredientes[] = $ing;
    }
}

// Definir título de la página
$pageTitle = 'Dashboard';

// Incluir cabecera
require_once 'includes/header.php';
?>

<style>
    /* Estilos específicos del Dashboard */
    .dashboard-container { max-width: 1200px; margin: 0 auto; padding: 20px; }
    .header-panel { background: #fff; padding: 20px; border-radius: 8px; box-shadow: 0 2px 5px rgba(0,0,0,0.05); margin-bottom: 20px; }
    .section-title { font-size: 1.4rem; color: #2c3e50; margin-bottom: 15px; border-bottom: 2px solid #3498db; padding-bottom: 5px; }
    .table-responsive { overflow-x: auto; }
    table { width: 100%; border-collapse: collapse; margin-bottom: 20px; background: white; }
    th, td { padding: 12px; text-align: left; border-bottom: 1px solid #ddd; }
    th { background-color: #f8f9fa; font-weight: 600; color: #34495e; }
    tr:hover { background-color: #f1f1f1; }
    .btn { padding: 8px 15px; border: none; border-radius: 4px; cursor: pointer; font-size: 0.9rem; text-decoration: none; display: inline-block; }
    .btn-primary { background: #3498db; color: white; }
    .btn-success { background: #27ae60; color: white; }
    .btn-warning { background: #f39c12; color: white; }
    .btn-danger { background: #e74c3c; color: white; }
    .btn-sm { padding: 5px 10px; font-size: 0.8rem; }
    .cal-bajo { color: #27ae60; font-weight: bold; }
    .cal-medio { color: #f39c12; font-weight: bold; }
    .cal-alto { color: #e74c3c; font-weight: bold; }
    .ingredient-row { opacity: 1; transition: opacity 0.3s; }
    .ingredient-row.comprado { opacity: 0.5; text-decoration: line-through; }
    .alert { padding: 15px; margin-bottom: 20px; border-radius: 5px; }
    .alert-success { background: #d4edda; color: #155724; border: 1px solid #c3e6cb; }
    .alert-danger { background: #f8d7da; color: #721c24; border: 1px solid #f5c6cb; }
    .form-check { display: flex; align-items: center; margin-bottom: 5px; }
    .form-check input { margin-right: 8px; }
    .loading { display: none; color: #3498db; font-weight: bold; margin-left: 10px; }
    .tentative-item { cursor: pointer; padding: 5px; background: #e8f6f3; border-radius: 4px; transition: background 0.2s; }
    .tentative-item:hover { background: #d1f2eb; }
</style>

<div class="dashboard-container">
    <!-- Mensajes Flash -->
    <?php if ($mensajeExito): ?>
        <div class="alert alert-success"><?= sanitize($mensajeExito) ?></div>
    <?php endif; ?>
    <?php if ($mensajeError): ?>
        <div class="alert alert-danger"><?= sanitize($mensajeError) ?></div>
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
                                <td>
                                    <?php if ($diaData['comida']): ?>
                                        <div class="tentative-item" data-dia="<?= $i ?>" data-momento="comida" data-plato="<?= $diaData['comida']['id_plato'] ?>" onclick="addToEffective(this)">
                                            <strong><?= sanitize($diaData['comida']['plato_nombre']) ?></strong>
                                            <br><small class="<?= getCaloricClass($diaData['comida']['nivel_calorico']) ?>">
                                                <?= formatCalories($diaData['comida']['calorias'] ?? 0) ?>
                                            </small>
                                        </div>
                                    <?php else: ?>
                                        <span style="color:#ccc;">-</span>
                                    <?php endif; ?>
                                </td>

                                <!-- Para Comida -->
                                <td><?php echo "Todos"; ?></td>

                                <!-- Cena -->
                                <td>
                                    <?php if ($diaData['cena']): ?>
                                        <div class="tentative-item" data-dia="<?= $i ?>" data-momento="cena" data-plato="<?= $diaData['cena']['id_plato'] ?>" onclick="addToEffective(this)">
                                            <strong><?= sanitize($diaData['cena']['plato_nombre']) ?></strong>
                                            <br><small class="<?= getCaloricClass($diaData['cena']['nivel_calorico']) ?>">
                                                <?= formatCalories($diaData['cena']['calorias'] ?? 0) ?>
                                            </small>
                                        </div>
                                    <?php else: ?>
                                        <span style="color:#ccc;">-</span>
                                    <?php endif; ?>
                                </td>

                                <!-- Para Cena -->
                                <td><?php echo "Todos"; ?></td>
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
                    $maxDiasEfectivo = max(array_keys($diasEfectivos)) ?? 0;
                    $totalRows = max($maxDiasEfectivo, $menuTentativoData ? count($menuTentativoData) : 0);
                    
                    for ($d = 1; $d <= $totalRows; $d++): 
                        $comida = $diasEfectivos[$d]['comida'] ?? null;
                        $cena = $diasEfectivos[$d]['cena'] ?? null;
                        
                        // Calcular herramientas del día (simplificado para visualización)
                        $herramientasDia = [];
                        if ($comida && $comida['id_plato']) {
                            // En una versión completa, cargaríamos la receta aquí
                            $herramientasDia[] = "Calculando..."; 
                        }
                        if ($cena && $cena['id_plato']) {
                            $herramientasDia[] = "Calculando...";
                        }
                        $herramientasStr = implode(", ", array_unique($herramientasDia));
                    ?>
                    <tr data-dia="<?= $d ?>">
                        <td><?= $d ?></td>
                        
                        <!-- Comida -->
                        <td>
                            <?php if ($comida): ?>
                                <a href="<?= url('receta.php?id=' . $comida['id_plato']) ?>" target="_blank">
                                    <?= sanitize($comida['nombre']) ?>
                                </a>
                                <br><small class="<?= getCaloricClass($comida['nivel_calorico']) ?>">
                                    <?= formatCalories(0) ?>
                                </small>
                            <?php else: ?>
                                <span style="color:#ccc;">Hueco libre</span>
                            <?php endif; ?>
                        </td>
                        
                        <!-- Quitar Comida -->
                        <td style="text-align:center;">
                            <?php if ($comida): ?>
                                <input type="checkbox" onchange="removeFromEffective(<?= $d ?>, 'comida')" style="transform: scale(1.5);">
                            <?php endif; ?>
                        </td>

                        <!-- Cena -->
                        <td>
                            <?php if ($cena): ?>
                                <a href="<?= url('receta.php?id=' . $cena['id_plato']) ?>" target="_blank">
                                    <?= sanitize($cena['nombre']) ?>
                                </a>
                                <br><small class="<?= getCaloricClass($cena['nivel_calorico']) ?>">
                                    <?= formatCalories(0) ?>
                                </small>
                            <?php else: ?>
                                <span style="color:#ccc;">Hueco libre</span>
                            <?php endif; ?>
                        </td>

                        <!-- Quitar Cena -->
                        <td style="text-align:center;">
                            <?php if ($cena): ?>
                                <input type="checkbox" onchange="removeFromEffective(<?= $d ?>, 'cena')" style="transform: scale(1.5);">
                            <?php endif; ?>
                        </td>

                        <!-- Herramientas -->
                        <td><small><?= $herramientasStr ?></small></td>
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
                                <td><?= sanitize($ing['nombre']) ?></td>
                                <td><?= number_format($ing['total_cantidad'], 0) ?> <?= sanitize($ing['unidad']) ?></td>
                                <td><?= sanitize($ing['supermercado']) ?></td>
                                <td>
                                    <input type="checkbox" 
                                           onchange="toggleIngredient(<?= $ing['id'] ?>, this.checked)"
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

<!-- Scripts JS -->
<script>
    const MENU_ID = <?= $menuActual->getId() ?>;
    const CSRF_TOKEN = '<?= generateCSRFToken() ?>';

    // Añadir plato al menú efectivo (desde el tentativo)
    function addToEffective(element) {
        const dia = element.dataset.dia;
        const momento = element.dataset.momento;
        const platoId = element.dataset.plato;

        if(!confirm('¿Añadir "' + element.querySelector('strong').innerText + '" al día ' + dia + ' (' + momento + ')?')) return;

        fetch('api/add_to_effective.php', {
            method: 'POST',
            headers: {'Content-Type': 'application/x-www-form-urlencoded'},
            body: `csrf_token=${CSRF_TOKEN}&menu_id=${MENU_ID}&dia=${dia}&momento=${momento}&plato_id=${platoId}`
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                alert('Plato añadido correctamente.');
                location.reload(); 
            } else {
                alert('Error: ' + data.message);
            }
        })
        .catch(err => {
            console.error(err);
            alert('Error de conexión.');
        });
    }

    // Quitar plato del menú efectivo
    function removeFromEffective(dia, momento) {
        if (!confirm('¿Quitar este plato?')) return;

        fetch('api/remove_from_effective.php', {
            method: 'POST',
            headers: {'Content-Type': 'application/x-www-form-urlencoded'},
            body: `csrf_token=${CSRF_TOKEN}&menu_id=${MENU_ID}&dia=${dia}&momento=${momento}`
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                location.reload();
            } else {
                alert('Error: ' + data.message);
            }
        });
    }

    // Marcar ingrediente como comprado
    function toggleIngredient(ingredienteId, comprado) {
        fetch('api/toggle_ingredient.php', {
            method: 'POST',
            headers: {'Content-Type': 'application/x-www-form-urlencoded'},
            body: `csrf_token=${CSRF_TOKEN}&menu_id=${MENU_ID}&ingrediente_id=${ingredienteId}&comprado=${comprado ? 1 : 0}`
        })
        .then(response => response.json())
        .then(data => {
            if (!data.success) alert('Error al actualizar');
            // Actualizar visualmente si falla o tiene retraso
            if(!data.success) location.reload();
        });
    }

    // Guardar como favorito
    function saveAsFavorite() {
        const nombre = prompt('Nombre para el menú favorito:');
        if (!nombre) return;

        fetch('api/save_as_favorite.php', {
            method: 'POST',
            headers: {'Content-Type': 'application/x-www-form-urlencoded'},
            body: `csrf_token=${CSRF_TOKEN}&menu_id=${MENU_ID}&nombre=${encodeURIComponent(nombre)}`
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                alert('Menú guardado como favorito: ' + nombre);
                location.href = 'favoritos.php';
            } else {
                alert('Error: ' + data.message);
            }
        });
    }
</script>

<?php require_once 'includes/footer.php'; ?>