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

$pageTitle = 'Mis Favoritos';
require_once 'includes/header.php';
?>

<div class="favorites-container">
    <div class="row mb-4">
        <div class="col-12">
            <h1 class="mb-3">Mis Menús Favoritos</h1>
            <a href="<?= url('index.php') ?>" class="btn btn-primary">← Volver al Dashboard</a>
        </div>
    </div>

    <?php if ($mensaje): ?>
        <div class="alert alert-<?= $tipoMensaje ?>"><?= sanitize($mensaje) ?></div>
    <?php endif; ?>

    <?php if (empty($favoritos)): ?>
        <div class="text-center py-5">
            <h3>No tienes menús guardados aún.</h3>
            <p class="text-muted">Genera un menú en el dashboard y guárdalo como favorito.</p>
            <a href="<?= url('index.php') ?>" class="btn btn-success">Crear Menú</a>
        </div>
    <?php else: ?>
        <div class="row">
            <?php foreach ($favoritos as $fav): ?>
                <div class="col-md-6 col-lg-4 mb-4">
                    <div class="card h-100">
                        <div class="card-body">
                            <h5 class="card-title"><?= sanitize($fav->getNombre()) ?></h5>
                            <p class="card-text text-muted small">
                                <strong>Creado:</strong> <?= formatDateES($fav->getFechaGeneracion()) ?><br>
                                <strong>Días:</strong> <?= count($fav->getDiasData()) ?><br>
                                <strong>Comensales:</strong> <?= count($fav->getComensalesData()) ?>
                            </p>
                        </div>
                        <div class="card-footer bg-white">
                            <form method="POST" action="" class="d-inline">
                                <?= csrfField() ?>
                                <input type="hidden" name="action" value="cargar">
                                <input type="hidden" name="favorite_id" value="<?= $fav->getId() ?>">
                                <button type="submit" class="btn btn-success btn-sm">Cargar</button>
                            </form>
                            
                            <button class="btn btn-warning btn-sm" onclick="openRenameModal(<?= $fav->getId() ?>, '<?= sanitize($fav->getNombre()) ?>')">Renombrar</button>
                            
                            <form method="POST" action="" class="d-inline" onsubmit="return confirm('¿Estás seguro de eliminar este favorito?');">
                                <?= csrfField() ?>
                                <input type="hidden" name="action" value="eliminar">
                                <input type="hidden" name="favorite_id" value="<?= $fav->getId() ?>">
                                <button type="submit" class="btn btn-danger btn-sm">Eliminar</button>
                            </form>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</div>

<!-- Modal para Renombrar -->
<div class="modal fade" id="renameModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Renombrar Menú</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST" action="">
                <?= csrfField() ?>
                <input type="hidden" name="action" value="renombrar">
                <input type="hidden" name="favorite_id" id="renameId" value="">
                <div class="modal-body">
                    <div class="mb-3">
                        <label for="renameInput" class="form-label">Nuevo Nombre:</label>
                        <input type="text" id="renameInput" name="nuevo_nombre" class="form-control" required>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn btn-primary">Guardar</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
    function openRenameModal(id, currentName) {
        document.getElementById('renameId').value = id;
        document.getElementById('renameInput').value = currentName;
        new bootstrap.Modal(document.getElementById('renameModal')).show();
    }
</script>

<?php require_once 'includes/footer.php'; ?>
