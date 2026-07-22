<?php
/**
 * FAVORITOS.PHP
 * =============
 * Lista de menús guardados como favoritos.
 * Permite: Ver detalles, Cargar en menú actual, Eliminar, Renombrar.
 */

require_once 'config/config.php';
require_once 'config/db.php';
require_once 'includes/auth.php';
require_once 'includes/functions.php';
require_once 'classes/Menu.php';

// 1. Verificar autenticación
requireLogin();

$userId = getCurrentUserId();
$mensaje = '';
$tipoMensaje = '';

// 2. Procesar acciones POST (Eliminar, Cargar, Renombrar)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!validateCSRFToken($_POST['csrf_token'] ?? '')) {
        $tipoMensaje = 'danger';
        $mensaje = 'Token de seguridad inválido.';
    } else {
        $action = $_POST['action'] ?? '';
        $favoriteId = (int)($_POST['favorite_id'] ?? 0);

        if ($favoriteId <= 0) {
            $tipoMensaje = 'danger';
            $mensaje = 'ID de favorito inválido.';
        } else {
            $menu = new Menu($favoriteId);
            
            // Verificar que el menú existe y es del usuario
            if (!$menu->getId() || $menu->getUsuarioCreadorId() != $userId || $menu->getTipo() !== TIPO_FAVORITO) {
                $tipoMensaje = 'danger';
                $mensaje = 'Favorito no encontrado o no autorizado.';
            } else {
                if ($action === 'eliminar') {
                    if ($menu->delete()) {
                        $tipoMensaje = 'success';
                        $mensaje = 'Menú favorito eliminado correctamente.';
                    } else {
                        $tipoMensaje = 'danger';
                        $mensaje = 'Error al eliminar el menú.';
                    }
                } elseif ($action === 'cargar') {
                    // Lógica para cargar el favorito en el menú actual
                    // 1. Obtener o crear menú actual
                    $menuActual = Menu::getActualForUser($userId);
                    if (!$menuActual) {
                        $menuActual = new Menu(null, $userId, 'Menú Actual', TIPO_ACTUAL);
                        $menuActual->save();
                    }

                    // 2. Borrar contenido actual
                    // (La clase Menu no tiene un método "clearAll", lo hacemos manual o recreamos)
                    // Para simplificar: Borramos menu_dias y menu_comensales del actual
                    $db = getDB();
                    $db->beginTransaction();
                    try {
                        $stmt = $db->prepare("DELETE FROM menu_dias WHERE id_menu = ?");
                        $stmt->execute([$menuActual->getId()]);
                        $stmt = $db->prepare("DELETE FROM menu_comensales WHERE id_menu = ?");
                        $stmt->execute([$menuActual->getId()]);
                        
                        // 3. Copiar datos del favorito al actual
                        $dias = $menu->getDiasData();
                        foreach ($dias as $dia => $momentos) {
                            foreach ($momentos as $momento => $datos) {
                                if ($datos['id_plato']) {
                                    $menuActual->setPlato($dia, $momento, $datos['id_plato']);
                                }
                            }
                        }
                        
                        $comensales = $menu->getComensalesData();
                        foreach ($comensales as $comensal) {
                            $menuActual->addComensal($comensal['id']);
                        }
                        
                        $db->commit();
                        $tipoMensaje = 'success';
                        $mensaje = 'Menú cargado en el panel principal. ¡Edita y guarda!';
                        // Redirigir al index
                        redirect(url('index.php'));
                    } catch (Exception $e) {
                        $db->rollBack();
                        $tipoMensaje = 'danger';
                        $mensaje = 'Error al cargar el menú: ' . $e->getMessage();
                    }
                } elseif ($action === 'renombrar') {
                    $nuevoNombre = trim($_POST['nuevo_nombre'] ?? '');
                    if (empty($nuevoNombre)) {
                        $tipoMensaje = 'danger';
                        $mensaje = 'El nombre no puede estar vacío.';
                    } else {
                        $menu->setNombre($nuevoNombre);
                        if ($menu->save()) {
                            $tipoMensaje = 'success';
                            $mensaje = 'Nombre actualizado correctamente.';
                        } else {
                            $tipoMensaje = 'danger';
                            $mensaje = 'Error al actualizar el nombre.';
                        }
                    }
                }
            }
        }
    }
}

// 3. Obtener lista de favoritos
$favoritos = Menu::getFavoritesForUser($userId);
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Mis Favoritos - f00dlist</title>
    <link rel="stylesheet" href="<?= asset('css/style.css') ?>">
    <link rel="stylesheet" href="<?= asset('css/responsive.css') ?>">
    <style>
        .favorites-container { max-width: 1000px; margin: 0 auto; padding: 20px; }
        .header-panel { background: #fff; padding: 20px; border-radius: 8px; box-shadow: 0 2px 5px rgba(0,0,0,0.05); margin-bottom: 20px; display: flex; justify-content: space-between; align-items: center; }
        .alert { padding: 15px; margin-bottom: 20px; border-radius: 5px; }
        .alert-success { background: #d4edda; color: #155724; border: 1px solid #c3e6cb; }
        .alert-danger { background: #f8d7da; color: #721c24; border: 1px solid #f5c6cb; }
        .card-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(300px, 1fr)); gap: 20px; }
        .favorite-card { background: #fff; border-radius: 8px; box-shadow: 0 2px 5px rgba(0,0,0,0.1); overflow: hidden; transition: transform 0.2s; }
        .favorite-card:hover { transform: translateY(-5px); }
        .card-header { background: #3498db; color: white; padding: 15px; font-weight: bold; font-size: 1.1rem; }
        .card-body { padding: 15px; }
        .card-info { font-size: 0.9rem; color: #666; margin-bottom: 15px; }
        .card-actions { display: flex; gap: 10px; flex-wrap: wrap; }
        .btn { padding: 8px 12px; border: none; border-radius: 4px; cursor: pointer; text-decoration: none; font-size: 0.9rem; display: inline-block; text-align: center; }
        .btn-primary { background: #3498db; color: white; }
        .btn-success { background: #27ae60; color: white; }
        .btn-danger { background: #e74c3c; color: white; }
        .btn-warning { background: #f39c12; color: white; }
        .modal { display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.5); z-index: 1000; justify-content: center; align-items: center; }
        .modal-content { background: white; padding: 20px; border-radius: 8px; width: 90%; max-width: 400px; }
        .form-control { width: 100%; padding: 8px; margin: 10px 0; border: 1px solid #ddd; border-radius: 4px; box-sizing: border-box; }
    </style>
</head>
<body>

<div class="favorites-container">
    <div class="header-panel">
        <h1>Mis Menús Favoritos</h1>
        <a href="<?= url('index.php') ?>" class="btn btn-primary">← Volver al Dashboard</a>
    </div>

    <?php if ($mensaje): ?>
        <div class="alert alert-<?= $tipoMensaje ?>"><?= sanitize($mensaje) ?></div>
    <?php endif; ?>

    <?php if (empty($favoritos)): ?>
        <div style="text-align: center; padding: 40px; background: #fff; border-radius: 8px;">
            <h3>No tienes menús guardados aún.</h3>
            <p>Genera un menú en el dashboard y guárdalo como favorito.</p>
            <a href="<?= url('index.php') ?>" class="btn btn-success">Crear Menú</a>
        </div>
    <?php else: ?>
        <div class="card-grid">
            <?php foreach ($favoritos as $fav): ?>
                <div class="favorite-card">
                    <div class="card-header">
                        <?= sanitize($fav->getNombre()) ?>
                    </div>
                    <div class="card-body">
                        <div class="card-info">
                            <strong>Creado:</strong> <?= formatDateES($fav->getFechaGeneracion()) ?><br>
                            <strong>Días:</strong> <?= count($fav->getDiasData()) ?><br>
                            <strong>Comensales:</strong> <?= count($fav->getComensalesData()) ?>
                        </div>
                        <div class="card-actions">
                            <form method="POST" action="" style="display:inline;">
                                <?= csrfField() ?>
                                <input type="hidden" name="action" value="cargar">
                                <input type="hidden" name="favorite_id" value="<?= $fav->getId() ?>">
                                <button type="submit" class="btn btn-success">Cargar</button>
                            </form>
                            
                            <button class="btn btn-warning" onclick="openRenameModal(<?= $fav->getId() ?>, '<?= sanitize($fav->getNombre()) ?>')">Renombrar</button>
                            
                            <form method="POST" action="" style="display:inline;" onsubmit="return confirm('¿Estás seguro de eliminar este favorito?');">
                                <?= csrfField() ?>
                                <input type="hidden" name="action" value="eliminar">
                                <input type="hidden" name="favorite_id" value="<?= $fav->getId() ?>">
                                <button type="submit" class="btn btn-danger">Eliminar</button>
                            </form>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</div>

<!-- Modal para Renombrar -->
<div id="renameModal" class="modal">
    <div class="modal-content">
        <h3>Renombrar Menú</h3>
        <form method="POST" action="">
            <?= csrfField() ?>
            <input type="hidden" name="action" value="renombrar">
            <input type="hidden" name="favorite_id" id="renameId" value="">
            <label>Nuevo Nombre:</label>
            <input type="text" name="nuevo_nombre" id="renameInput" class="form-control" required>
            <div style="text-align: right; margin-top: 15px;">
                <button type="button" class="btn btn-danger" onclick="closeRenameModal()">Cancelar</button>
                <button type="submit" class="btn btn-primary">Guardar</button>
            </div>
        </form>
    </div>
</div>

<script>
    function openRenameModal(id, currentName) {
        document.getElementById('renameId').value = id;
        document.getElementById('renameInput').value = currentName;
        document.getElementById('renameModal').style.display = 'flex';
    }
    function closeRenameModal() {
        document.getElementById('renameModal').style.display = 'none';
    }
    // Cerrar modal al hacer clic fuera
    window.onclick = function(event) {
        const modal = document.getElementById('renameModal');
        if (event.target == modal) {
            closeRenameModal();
        }
    }
</script>

</body>
</html>