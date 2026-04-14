<?php
/**
 * API: SAVE AS FAVORITE
 * =====================
 * Guarda el menú actual como un favorito con un nombre personalizado.
 * Realiza una copia profunda de menu_dias y menu_comensales.
 */

require_once '../config/config.php';
require_once '../config/db.php';
require_once '../includes/auth.php';
require_once '../classes/Menu.php';

// 1. Verificar que es una petición POST y el usuario está logueado
if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !isLoggedIn()) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Acceso denegado.']);
    exit;
}

// 2. Validar token CSRF
if (!validateCSRFToken($_POST['csrf_token'] ?? '')) {
    echo json_encode(['success' => false, 'message' => 'Token de seguridad inválido.']);
    exit;
}

// 3. Obtener y validar parámetros
$menuId = (int)($_POST['menu_id'] ?? 0);
$nombre = trim($_POST['nombre'] ?? '');

if ($menuId <= 0) {
    echo json_encode(['success' => false, 'message' => 'ID de menú inválido.']);
    exit;
}

if (empty($nombre)) {
    echo json_encode(['success' => false, 'message' => 'El nombre del favorito es obligatorio.']);
    exit;
}

if (strlen($nombre) > 100) {
    echo json_encode(['success' => false, 'message' => 'El nombre no puede superar los 100 caracteres.']);
    exit;
}

// 4. Verificar que el menú pertenece al usuario
$menu = new Menu($menuId);
if (!$menu->getId() || $menu->getUsuarioCreadorId() != getCurrentUserId()) {
    echo json_encode(['success' => false, 'message' => 'Menú no encontrado o no autorizado.']);
    exit;
}

// 5. Guardar como favorito
try {
    $newFavorite = $menu->saveAsFavorite($nombre);

    if ($newFavorite && $newFavorite->getId()) {
        echo json_encode([
            'success' => true, 
            'message' => 'Menú guardado como favorito: ' . sanitize($nombre),
            'favorite_id' => $newFavorite->getId()
        ]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Error al guardar como favorito.']);
    }

} catch (Exception $e) {
    if (DEBUG_MODE) {
        error_log("Error en save_as_favorite: " . $e->getMessage());
    }
    echo json_encode(['success' => false, 'message' => 'Error interno del servidor.']);
}
?>