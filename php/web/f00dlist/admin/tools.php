<?php
/**
 * ADMIN/TOOLS.PHP
 * ===============
 * CRUD de Herramientas.
 */

require_once '../config/config.php';
require_once '../config/db.php';
require_once '../includes/auth.php';
require_once '../includes/functions.php';
require_once '../classes/Tool.php';

requireAdmin();

$mensaje = '';
$tipoMensaje = '';
$editingId = (int)($_GET['edit'] ?? 0);
$toolData = null;

// Procesar formulario
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!validateCSRFToken($_POST['csrf_token'] ?? '')) {
        $tipoMensaje = 'danger';
        $mensaje = 'Token inválido.';
    } else {
        $id = (int)($_POST['id'] ?? 0);
        $nombre = trim($_POST['nombre'] ?? '');
        $descripcion = trim($_POST['descripcion'] ?? '');
        $activo = isset($_POST['activo']) ? 1 : 0;

        if (empty($nombre)) {
            $tipoMensaje = 'danger';
            $mensaje = 'El nombre es obligatorio.';
        } else {
            $tool = new Tool($id > 0 ? $id : null, $nombre, $descripcion, (bool)$activo);
            
            if ($tool->save()) {
                $tipoMensaje = 'success';
                $mensaje = $id > 0 ? 'Herramienta actualizada.' : 'Herramienta creada.';
                $editingId = 0;
                $toolData = null;
            } else {
                $tipoMensaje = 'danger';
                $mensaje = 'Error al guardar.';
            }
        }
    }
}

// Cargar datos para edición
if ($editingId > 0) {
    $toolData = new Tool($editingId);
    if (!$toolData->getId()) {
        $editingId = 0;
    }
}

// Listar herramientas
$tools = Tool::getAll();

$pageTitle = 'Gestionar Herramientas';
require_once '../includes/header.php';
?>

<div class="admin-page">
    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px;">
        <h1>Gestión de Herramientas</h1>
        <a href="index.php" class="btn btn-secondary">← Volver</a>
    </div>

    <?php if ($mensaje): ?>
        <div class="alert alert-<?= $tipoMensaje ?>"><?= sanitize($mensaje) ?></div>
    <?php endif; ?>

    <!-- Formulario -->
    <div style="background: white; padding: 20px; border-radius: 8px; box-shadow: 0 2px 5px rgba(0,0,0,0.05); margin-bottom: 30px;">
        <h3><?= $editingId > 0 ? 'Editar Herramienta' : 'Nueva Herramienta' ?></h3>
        <form method="POST" action="">
            <?= csrfField() ?>
            <input type="hidden" name="id" value="<?= $editingId ?>">
            
            <div style="display: grid; grid-template-columns: 1fr 1fr auto; gap: 15px; align-items: end;">
                <div>
                    <label style="display: block; margin-bottom: 5px; font-weight: bold;">Nombre:</label>
                    <input type="text" name="nombre" class="form-control" value="<?= $editingId > 0 ? sanitize($toolData->getNombre()) : '' ?>" required style="width: 100%; padding: 8px; border: 1px solid #ddd; border-radius: 4px;">
                </div>
                <div>
                    <label style="display: block; margin-bottom: 5px; font-weight: bold;">Descripción:</label>
                    <input type="text" name="descripcion" class="form-control" value="<?= $editingId > 0 ? sanitize($toolData->getDescripcion()) : '' ?>" style="width: 100%; padding: 8px; border: 1px solid #ddd; border-radius: 4px;">
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
                    <th style="padding: 10px; text-align: left; border-bottom: 2px solid #ddd;">Descripción</th>
                    <th style="padding: 10px; text-align: center; border-bottom: 2px solid #ddd;">Estado</th>
                    <th style="padding: 10px; text-align: center; border-bottom: 2px solid #ddd;">Acciones</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($tools as $t): ?>
                    <tr>
                        <td style="padding: 10px; border-bottom: 1px solid #eee;"><?= sanitize($t['nombre']) ?></td>
                        <td style="padding: 10px; border-bottom: 1px solid #eee;"><?= sanitize($t['descripcion']) ?></td>
                        <td style="padding: 10px; text-align: center; border-bottom: 1px solid #eee;">
                            <span style="padding: 3px 8px; border-radius: 10px; font-size: 0.8rem; background: <?= $t['activo'] ? '#d4edda' : '#f8d7da' ?>; color: <?= $t['activo'] ? '#155724' : '#721c24' ?>;">
                                <?= $t['activo'] ? 'Activo' : 'Inactivo' ?>
                            </span>
                        </td>
                        <td style="padding: 10px; text-align: center; border-bottom: 1px solid #eee;">
                            <a href="?edit=<?= $t['id'] ?>" class="btn btn-warning btn-sm" style="padding: 5px 10px; font-size: 0.8rem;">Editar</a>
                            <!-- Aquí podrías añadir un botón de eliminar si quisieras -->
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<?php require_once '../includes/footer.php'; ?>