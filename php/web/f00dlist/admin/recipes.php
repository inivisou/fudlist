<?php
/**
 * ADMIN/RECIPES.PHP
 * =================
 * CRUD de Recetas y Platos.
 * Gestión compleja: Datos del plato + Datos de la receta + Ingredientes (con cant.) + Herramientas.
 */

require_once '../config/config.php';
require_once '../config/db.php';
require_once '../includes/auth.php';
require_once '../includes/functions.php';
require_once '../classes/Recipe.php';
require_once '../classes/Ingredient.php';
require_once '../classes/Tool.php';

requireAdmin();

$mensaje = '';
$tipoMensaje = '';
$editingId = (int)($_GET['edit'] ?? 0);
$recipeData = null;
$platosData = fetchAll("SELECT id, nombre FROM platos WHERE activo = 1 ORDER BY nombre ASC");

// Procesar formulario
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!validateCSRFToken($_POST['csrf_token'] ?? '')) {
        $tipoMensaje = 'danger';
        $mensaje = 'Token inválido.';
    } else {
        try {
            beginTransaction();

            // 1. Datos del Plato
            $idPlato = (int)($_POST['id_plato'] ?? 0);
            $nombrePlato = trim($_POST['nombre_plato'] ?? '');
            $tipo = $_POST['tipo'] ?? 'principal';
            $categoria = trim($_POST['categoria'] ?? '');
            $esComida = isset($_POST['es_comida']) ? 1 : 0;
            $esCena = isset($_POST['es_cena']) ? 1 : 0;
            $nivelCalorico = $_POST['nivel_calorico'] ?? 'medio';

            if (empty($nombrePlato) || $idPlato <= 0) {
                throw new Exception("Debe seleccionar o crear un plato válido.");
            }

            // Si el plato no existe, crearlo (o actualizarlo si se cambia el nombre)
            // Para simplificar: asumimos que el ID del plato ya existe y solo actualizamos sus datos básicos
            // O si es nuevo, lo creamos primero.
            // En este flujo: El usuario selecciona un plato existente o el admin lo crea antes.
            // Aquí actualizamos los datos del plato seleccionado.
            $sqlPlato = "UPDATE platos SET nombre = ?, tipo = ?, categoria = ?, es_comida = ?, es_cena = ?, nivel_calorico = ? WHERE id = ?";
            executeQuery($sqlPlato, [$nombrePlato, $tipo, $categoria, $esComida, $esCena, $nivelCalorico, $idPlato]);

            // 2. Datos de la Receta
            $idReceta = (int)($_POST['id_receta'] ?? 0);
            $titulo = trim($_POST['titulo'] ?? '');
            $subtitulo = trim($_POST['subtitulo'] ?? '');
            $texto = $_POST['texto'] ?? ''; // HTML
            $enlace = trim($_POST['enlace'] ?? '');

            $recipe = new Recipe($idReceta > 0 ? $idReceta : null, $idPlato, $titulo, $subtitulo, $texto, $enlace);
            
            if (!$recipe->save()) {
                throw new Exception("Error al guardar la receta.");
            }
            $idReceta = $recipe->getId(); // Obtener ID si era nuevo

            // 3. Gestionar Ingredientes
            // Recibir arrays: ingredientes_ids[], cantidades[], unidades[]
            $ingIds = isset($_POST['ingredientes_ids']) ? $_POST['ingredientes_ids'] : [];
            $cantidades = isset($_POST['cantidades']) ? $_POST['cantidades'] : [];
            $unidades = isset($_POST['unidades']) ? $_POST['unidades'] : [];

            // Limpiar relaciones anteriores
            $recipe->clearRelations();

            // Insertar nuevos
            if (!empty($ingIds)) {
                foreach ($ingIds as $index => $ingId) {
                    $ingId = (int)$ingId;
                    if ($ingId > 0) {
                        $cant = floatval($cantidades[$index] ?? 1);
                        $uni = trim($unidades[$index] ?? 'g');
                        $recipe->addIngredient($ingId, $cant, $uni);
                    }
                }
            }

            // 4. Gestionar Herramientas
            $toolIds = isset($_POST['herramientas_ids']) ? array_map('intval', $_POST['herramientas_ids']) : [];
            
            // Limpiar herramientas anteriores (ya hecho en clearRelations, pero aseguramos)
            // Insertar nuevas
            foreach ($toolIds as $toolId) {
                if ($toolId > 0) {
                    $recipe->addTool($toolId);
                }
            }

            commit();
            $tipoMensaje = 'success';
            $mensaje = 'Receta y Plato guardados correctamente.';
            $editingId = 0;

        } catch (Exception $e) {
            rollback();
            $tipoMensaje = 'danger';
            $mensaje = $e->getMessage();
        }
    }
}

// Cargar datos para edición
if ($editingId > 0) {
    $recipe = new Recipe($editingId);
    if ($recipe->getId()) {
        $recipeData = [
            'id' => $recipe->getId(),
            'id_plato' => $recipe->getIdPlato(),
            'titulo' => $recipe->getTituloHtml(),
            'subtitulo' => $recipe->getSubtituloHtml(),
            'texto' => $recipe->getTextoHtml(),
            'enlace' => $recipe->getEnlace(),
            'plato_nombre' => $recipe->getPlatoData()['nombre'] ?? '',
            'tipo' => $recipe->getPlatoData()['tipo'] ?? 'principal',
            'categoria' => $recipe->getPlatoData()['categoria'] ?? '',
            'es_comida' => $recipe->getPlatoData()['es_comida'] ?? 1,
            'es_cena' => $recipe->getPlatoData()['es_cena'] ?? 0,
            'nivel_calorico' => $recipe->getPlatoData()['nivel_calorico'] ?? 'medio',
            'ingredientes' => $recipe->getIngredients(),
            'herramientas' => $recipe->getTools()
        ];
    } else {
        $editingId = 0;
    }
}

// Listar recetas
$sqlList = "SELECT r.id, r.id_plato, p.nombre as plato_nombre, p.tipo, p.categoria, p.nivel_calorico
            FROM recetas r
            JOIN platos p ON r.id_plato = p.id
            WHERE p.activo = 1
            ORDER BY p.nombre ASC";
$recipes = fetchAll($sqlList);

// Obtener todos los ingredientes y herramientas para los selects
$allIngredients = Ingredient::getAllActive();
$allTools = Tool::getAllActive();

$pageTitle = 'Gestionar Recetas';
require_once '../includes/header.php';
?>

<div class="admin-page">
    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px;">
        <h1>Gestión de Recetas y Platos</h1>
        <a href="index.php" class="btn btn-secondary">← Volver</a>
    </div>

    <?php if ($mensaje): ?>
        <div class="alert alert-<?= $tipoMensaje ?>"><?= sanitize($mensaje) ?></div>
    <?php endif; ?>

    <!-- Formulario -->
    <div style="background: white; padding: 20px; border-radius: 8px; box-shadow: 0 2px 5px rgba(0,0,0,0.05); margin-bottom: 30px;">
        <h3><?= $editingId > 0 ? 'Editar Receta' : 'Nueva Receta' ?></h3>
        <form method="POST" action="">
            <?= csrfField() ?>
            <input type="hidden" name="id_receta" value="<?= $recipeData['id'] ?? '' ?>">

            <!-- Datos del Plato -->
            <div style="border-bottom: 1px solid #eee; padding-bottom: 20px; margin-bottom: 20px;">
                <h4 style="color: #3498db; margin-top: 0;">1. Datos del Plato</h4>
                <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 15px;">
                    <div>
                        <label style="display: block; margin-bottom: 5px; font-weight: bold;">Nombre del Plato:</label>
                        <input type="text" name="nombre_plato" class="form-control" value="<?= $recipeData['plato_nombre'] ?? '' ?>" required style="width: 100%; padding: 8px;">
                    </div>
                    <div>
                        <label style="display: block; margin-bottom: 5px; font-weight: bold;">Tipo:</label>
                        <select name="tipo" class="form-control" style="width: 100%; padding: 8px;">
                            <option value="sopa" <?= ($recipeData['tipo'] ?? '') === 'sopa' ? 'selected' : '' ?>>Sopa</option>
                            <option value="principal" <?= ($recipeData['tipo'] ?? '') === 'principal' ? 'selected' : '' ?>>Principal</option>
                            <option value="postre" <?= ($recipeData['tipo'] ?? '') === 'postre' ? 'selected' : '' ?>>Postre</option>
                            <option value="ensalada" <?= ($recipeData['tipo'] ?? '') === 'ensalada' ? 'selected' : '' ?>>Ensalada</option>
                            <option value="guarnicion" <?= ($recipeData['tipo'] ?? '') === 'guarnicion' ? 'selected' : '' ?>>Guarnición</option>
                        </select>
                    </div>
                    <div>
                        <label style="display: block; margin-bottom: 5px; font-weight: bold;">Categoría (para reglas):</label>
                        <input type="text" name="categoria" class="form-control" value="<?= $recipeData['categoria'] ?? '' ?>" placeholder="Ej: Pasta, Fajitas, Tortilla" style="width: 100%; padding: 8px;">
                    </div>
                    <div>
                        <label style="display: block; margin-bottom: 5px; font-weight: bold;">Nivel Calórico:</label>
                        <select name="nivel_calorico" class="form-control" style="width: 100%; padding: 8px;">
                            <option value="bajo" <?= ($recipeData['nivel_calorico'] ?? '') === 'bajo' ? 'selected' : '' ?>>Bajo</option>
                            <option value="medio" <?= ($recipeData['nivel_calorico'] ?? '') === 'medio' ? 'selected' : '' ?>>Medio</option>
                            <option value="alto" <?= ($recipeData['nivel_calorico'] ?? '') === 'alto' ? 'selected' : '' ?>>Alto</option>
                        </select>
                    </div>
                    <div style="display: flex; align-items: center; gap: 15px;">
                        <label><input type="checkbox" name="es_comida" value="1" <?= ($recipeData['es_comida'] ?? 1) ? 'checked' : '' ?>> Comida</label>
                        <label><input type="checkbox" name="es_cena" value="1" <?= ($recipeData['es_cena'] ?? 0) ? 'checked' : '' ?>> Cena</label>
                    </div>
                </div>
            </div>

            <!-- Datos de la Receta -->
            <div style="border-bottom: 1px solid #eee; padding-bottom: 20px; margin-bottom: 20px;">
                <h4 style="color: #3498db; margin-top: 0;">2. Contenido de la Receta</h4>
                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 15px;">
                    <div>
                        <label style="display: block; margin-bottom: 5px; font-weight: bold;">Título (HTML):</label>
                        <input type="text" name="titulo" class="form-control" value="<?= $recipeData['titulo'] ?? '' ?>" style="width: 100%; padding: 8px;">
                        <small>Se mostrará como enlace externo si tiene URL.</small>
                    </div>
                    <div>
                        <label style="display: block; margin-bottom: 5px; font-weight: bold;">Subtítulo (HTML):</label>
                        <input type="text" name="subtitulo" class="form-control" value="<?= $recipeData['subtitulo'] ?? '' ?>" style="width: 100%; padding: 8px;">
                    </div>
                    <div style="grid-column: span 2;">
                        <label style="display: block; margin-bottom: 5px; font-weight: bold;">Instrucciones (HTML):</label>
                        <textarea name="texto" rows="4" class="form-control" style="width: 100%; padding: 8px;"><?= $recipeData['texto'] ?? '' ?></textarea>
                        <small>Puede usar etiquetas HTML básicas (&lt;p&gt;, &lt;b&gt;, &lt;ul&gt;, etc.).</small>
                    </div>
                    <div>
                        <label style="display: block; margin-bottom: 5px; font-weight: bold;">Enlace externo:</label>
                        <input type="url" name="enlace" class="form-control" value="<?= $recipeData['enlace'] ?? '' ?>" style="width: 100%; padding: 8px;">
                    </div>
                </div>
            </div>

            <!-- Ingredientes -->
            <div style="border-bottom: 1px solid #eee; padding-bottom: 20px; margin-bottom: 20px;">
                <h4 style="color: #3498db; margin-top: 0;">3. Ingredientes</h4>
                <div id="ingredientes-container">
                    <?php 
                    $ingList = $recipeData['ingredientes'] ?? [];
                    if (empty($ingList)) {
                        // Mostrar una fila vacía inicial
                        echo '<div class="ing-row" style="display: grid; grid-template-columns: 2fr 1fr 1fr 30px; gap: 10px; margin-bottom: 10px;">';
                        echo '<select name="ingredientes_ids[]" class="form-control"><option value="">Seleccionar ingrediente...</option>';
                        foreach ($allIngredients as $ing) {
                            echo '<option value="'.$ing['id'].'">'.$ing['nombre'].'</option>';
                        }
                        echo '</select>';
                        echo '<input type="number" step="0.1" name="cantidades[]" class="form-control" placeholder="Cant." value="100">';
                        echo '<select name="unidades[]" class="form-control"><option value="g">g</option><option value="kg">kg</option><option value="ml">ml</option><option value="unid">unid</option></select>';
                        echo '<button type="button" class="btn btn-danger btn-sm" onclick="this.parentElement.remove()">X</button>';
                        echo '</div>';
                    } else {
                        foreach ($ingList as $ing) {
                            echo '<div class="ing-row" style="display: grid; grid-template-columns: 2fr 1fr 1fr 30px; gap: 10px; margin-bottom: 10px;">';
                            echo '<select name="ingredientes_ids[]" class="form-control"><option value="">Seleccionar...</option>';
                            foreach ($allIngredients as $aIng) {
                                $selected = ($aIng['id'] == $ing->getId()) ? 'selected' : '';
                                echo '<option value="'.$aIng['id'].'" '.$selected.'>'.$aIng['nombre'].'</option>';
                            }
                            echo '</select>';
                            echo '<input type="number" step="0.1" name="cantidades[]" class="form-control" value="'.$ing->cantidad.'">';
                            echo '<select name="unidades[]" class="form-control">';
                            foreach (['g','kg','ml','unid'] as $u) {
                                echo '<option value="'.$u.'" '.($ing->unidad==$u?'selected':'').'>'.$u.'</option>';
                            }
                            echo '</select>';
                            echo '<button type="button" class="btn btn-danger btn-sm" onclick="this.parentElement.remove()">X</button>';
                            echo '</div>';
                        }
                    }
                    ?>
                </div>
                <button type="button" class="btn btn-secondary btn-sm" onclick="addIngredientRow()" style="margin-top: 10px;">+ Añadir Ingrediente</button>
            </div>

            <!-- Herramientas -->
            <div style="margin-bottom: 20px;">
                <h4 style="color: #3498db; margin-top: 0;">4. Herramientas Necesarias</h4>
                <div style="max-height: 150px; overflow-y: auto; border: 1px solid #eee; padding: 10px;">
                    <?php 
                    $selectedTools = array_column($recipeData['herramientas'] ?? [], 'id');
                    foreach ($allTools as $tool): 
                        $checked = in_array($tool['id'], $selectedTools) ? 'checked' : '';
                    ?>
                        <label class="form-check" style="display: flex; align-items: center; margin-bottom: 5px;">
                            <input type="checkbox" name="herramientas_ids[]" value="<?= $tool['id'] ?>" <?= $checked ?>>
                            <strong><?= sanitize($tool['nombre']) ?></strong>
                            <small style="margin-left: 10px; color: #666;"><?= sanitize($tool['descripcion']) ?></small>
                        </label>
                    <?php endforeach; ?>
                </div>
            </div>

            <div style="text-align: right;">
                <button type="submit" class="btn btn-primary"><?= $editingId > 0 ? 'Actualizar Receta' : 'Crear Receta' ?></button>
                <?php if ($editingId > 0): ?>
                    <a href="?reset=1" class="btn btn-secondary" style="margin-left: 10px;">Cancelar</a>
                <?php endif; ?>
            </div>
        </form>
    </div>

    <!-- Lista -->
    <div style="background: white; padding: 20px; border-radius: 8px; box-shadow: 0 2px 5px rgba(0,0,0,0.05);">
        <table style="width: 100%; border-collapse: collapse;">
            <thead>
                <tr style="background: #f8f9fa;">
                    <th style="padding: 10px; text-align: left;">Plato</th>
                    <th style="padding: 10px; text-align: center;">Tipo</th>
                    <th style="padding: 10px; text-align: center;">Calórico</th>
                    <th style="padding: 10px; text-align: center;">Comida/Cena</th>
                    <th style="padding: 10px; text-align: center;">Acciones</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($recipes as $rec): ?>
                    <tr>
                        <td style="padding: 10px; border-bottom: 1px solid #eee;"><?= sanitize($rec['plato_nombre']) ?></td>
                        <td style="padding: 10px; text-align: center; border-bottom: 1px solid #eee;"><?= sanitize($rec['tipo']) ?></td>
                        <td style="padding: 10px; text-align: center; border-bottom: 1px solid #eee;"><?= sanitize($rec['nivel_calorico']) ?></td>
                        <td style="padding: 10px; text-align: center; border-bottom: 1px solid #eee;">
                            <?= $rec['es_comida'] ? '🍽️' : '' ?> <?= $rec['es_cena'] ? '🌙' : '' ?>
                        </td>
                        <td style="padding: 10px; text-align: center; border-bottom: 1px solid #eee;">
                            <a href="?edit=<?= $rec['id'] ?>" class="btn btn-warning btn-sm" style="padding: 5px 10px; font-size: 0.8rem;">Editar</a>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<script>
    function addIngredientRow() {
        const container = document.getElementById('ingredientes-container');
        const div = document.createElement('div');
        div.className = 'ing-row';
        div.style.cssText = 'display: grid; grid-template-columns: 2fr 1fr 1fr 30px; gap: 10px; margin-bottom: 10px;';
        
        let options = '<option value="">Seleccionar ingrediente...</option>';
        <?php foreach ($allIngredients as $ing): ?>
            options += '<option value="<?= $ing['id'] ?>"><?= $ing['nombre'] ?></option>';
        <?php endforeach; ?>

        div.innerHTML = `
            <select name="ingredientes_ids[]" class="form-control">${options}</select>
            <input type="number" step="0.1" name="cantidades[]" class="form-control" placeholder="Cant." value="100">
            <select name="unidades[]" class="form-control">
                <option value="g">g</option><option value="kg">kg</option><option value="ml">ml</option><option value="unid">unid</option>
            </select>
            <button type="button" class="btn btn-danger btn-sm" onclick="this.parentElement.remove()">X</button>
        `;
        container.appendChild(div);
    }
</script>

<?php require_once '../includes/footer.php'; ?>