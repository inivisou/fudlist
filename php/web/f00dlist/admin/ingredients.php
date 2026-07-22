<?php
/**
 * ADMIN/INGREDIENTES.PHP
 * ======================
 * CRUD de Ingredientes.
 * Permite: Crear, Editar, Desactivar ingredientes.
 */

require_once '../config/config.php';
require_once '../config/db.php';
require_once '../includes/auth.php';
require_once '../includes/functions.php';
require_once '../classes/Ingredient.php';

requireRole('admin');

$mensaje = '';
$tipoMensaje = '';
$editingId = (int)($_GET['edit'] ?? 0);
$ingData = null;

// Procesar formulario
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!validateCSRFToken($_POST['csrf_token'] ?? '')) {
        $tipoMensaje = 'danger';
        $mensaje = 'Token inválido.';
    } else {
        $id = (int)($_POST['id'] ?? 0);
        $nombre = trim($_POST['nombre'] ?? '');
        $calorias = (float)($_POST['calorias'] ?? 0);
        $supermercado = trim($_POST['supermercado'] ?? 'General');
        $categoria = trim($_POST['categoria'] ?? 'general');
        $activo = isset($_POST['activo']) ? 1 : 0;

        if (empty($nombre)) {
            $tipoMensaje = 'danger';
            $mensaje = 'El nombre es obligatorio.';
        } else {
            $ing = new Ingredient($id > 0 ? $id : null, $nombre, $calorias, $supermercado, $categoria, (bool)$activo);
            
            if ($ing->save()) {
                $tipoMensaje = 'success';
                $mensaje = $id > 0 ? 'Ingrediente actualizado.' : 'Ingrediente creado.';
                $editingId = 0;
                $ingData = null;
            } else {
                $tipoMensaje = 'danger';
                $mensaje = 'Error al guardar. ¿El nombre ya existe?';
            }
        }
    }
}

// Cargar datos para edición
if ($editingId > 0) {
    $ingData = new Ingredient($editingId);
    if (!$ingData->getId()) {
        $editingId = 0;
    }
}

// Listar ingredientes
$ingredients = Ingredient::getAllActive(); // Solo activos por defecto, o usar getAll() si quieres ver inactivos
// Para ver todos (activos e inactivos) en admin:
$sqlAll = "SELECT * FROM ingredientes ORDER BY nombre ASC";
$allIngredients = fetchAll($sqlAll);

$pageTitle = 'Gestionar Ingredientes';
require_once '../includes/header.php';
?>

<div class="admin-page">
    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px;">
        <h1>Gestión de Ingredientes</h1>
        <a href="index.php" class="btn btn-secondary">← Volver</a>
    </div>

    <?php if ($mensaje): ?>
        <div class="alert alert-<?= $tipoMensaje ?>"><?= sanitize($mensaje) ?></div>
    <?php endif; ?>

    <!-- Formulario -->
    <div style="background: white; padding: 20px; border-radius: 8px; box-shadow: 0 2px 5px rgba(0,0,0,0.05); margin-bottom: 30px;">
        <h3><?= $editingId > 0 ? 'Editar Ingrediente' : 'Nuevo Ingrediente' ?></h3>
        <form method="POST" action="">
            <?= csrfField() ?>
            <input type="hidden" name="id" value="<?= $editingId ?>">
            
            <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 15px; align-items: end;">
                <div>
                    <label style="display: block; margin-bottom: 5px; font-weight: bold;">Nombre:</label>
                    <input type="text" name="nombre" class="form-control" value="<?= $editingId > 0 ? sanitize($ingData->getNombre()) : '' ?>" required style="width: 100%; padding: 8px; border: 1px solid #ddd; border-radius: 4px;">
                </div>
                <div>
                    <label style="display: block; margin-bottom: 5px; font-weight: bold;">Calorías (por 100g):</label>
                    <input type="number" step="0.01" name="calorias" class="form-control" value="<?= $editingId > 0 ? $ingData->getCaloriasX100g() : 0 ?>" required style="width: 100%; padding: 8px; border: 1px solid #ddd; border-radius: 4px;">
                </div>
                <div>
                    <label style="display: block; margin-bottom: 5px; font-weight: bold;">Supermercado:</label>
                    <input type="text" name="supermercado" class="form-control" value="<?= $editingId > 0 ? sanitize($ingData->getSupermercado()) : 'General' ?>" style="width: 100%; padding: 8px; border: 1px solid #ddd; border-radius: 4px;">
                </div>
                <div>
                    <label style="display: block; margin-bottom: 5px; font-weight: bold;">Categoría:</label>
                    <input type="text" name="categoria" class="form-control" value="<?= $editingId > 0 ? sanitize($ingData->getCategoria()) : 'general' ?>" style="width: 100%; padding: 8px; border: 1px solid #ddd; border-radius: 4px;">
                </div>
                <div style="display: flex; align-items: center; height: 42px;">
                    <label style="display: flex; align-items: center; cursor: pointer;">
                        <input type="checkbox" name="activo" value="1" <?= ($editingId == 0 || ($editingId > 0 && $ingData->isActivo())) ? 'checked' : '' ?> style="margin-right: 8px; transform: scale(1.2);">
                        Activo
                    </label>
                </div>
                <div>
                    <button type="submit" class="btn btn-primary"><?= $editingId > 0 ? 'Actualizar' : 'Crear' ?></button>
                    <?php if ($editingId > 0): ?>
                        <a href="?reset=1" class="btn btn-secondary" style="margin-left: 5px;">Cancelar</a>
                    <?php endif; ?>
                </div>
            </div>
        </form>
    </div>

    <!-- Lista -->
    <div style="background: white; padding: 20px; border-radius: 8px; box-shadow: 0 2px 5px rgba(0,0,0,0.05);">
        <table style="width: 100%; border-collapse: collapse;">
            <thead>
                <tr style="background: #f8f9fa;">
                    <th style="padding: 10px; text-align: left; border-bottom: 2px solid #ddd;">Nombre</th>
                    <th style="padding: 10px; text-align: center; border-bottom: 2px solid #ddd;">Cal/100g</th>
                    <th style="padding: 10px; text-align: left; border-bottom: 2px solid #ddd;">Supermercado</th>
                    <th style="padding: 10px; text-align: left; border-bottom: 2px solid #ddd;">Categoría</th>
                    <th style="padding: 10px; text-align: center; border-bottom: 2px solid #ddd;">Estado</th>
                    <th style="padding: 10px; text-align: center; border-bottom: 2px solid #ddd;">Acciones</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($allIngredients as $ing): ?>
                    <tr>
                        <td style="padding: 10px; border-bottom: 1px solid #eee;"><?= sanitize($ing['nombre']) ?></td>
                        <td style="padding: 10px; text-align: center; border-bottom: 1px solid #eee;"><?= number_format($ing['calorias_x_100g'], 2) ?></td>
                        <td style="padding: 10px; border-bottom: 1px solid #eee;"><?= sanitize($ing['supermercado']) ?></td>
                        <td style="padding: 10px; border-bottom: 1px solid #eee;"><?= sanitize($ing['categoria']) ?></td>
                        <td style="padding: 10px; text-align: center; border-bottom: 1px solid #eee;">
                            <span style="padding: 3px 8px; border-radius: 10px; font-size: 0.8rem; background: <?= $ing['activo'] ? '#d4edda' : '#f8d7da' ?>; color: <?= $ing['activo'] ? '#155724' : '#721c24' ?>;">
                                <?= $ing['activo'] ? 'Activo' : 'Inactivo' ?>
                            </span>
                        </td>
                        <td style="padding: 10px; text-align: center; border-bottom: 1px solid #eee;">
                            <a href="?edit=<?= $ing['id'] ?>" class="btn btn-warning btn-sm" style="padding: 5px 10px; font-size: 0.8rem;">Editar</a>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<?php require_once '../includes/footer.php'; ?>