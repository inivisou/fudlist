<?php
/**
 * API: REMOVE FROM EFFECTIVE
 * ==========================
 * Elimina un plato de una posición específica del menú efectivo.
 * Deja el hueco libre para que pueda ser rellenado posteriormente.
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
$dia = (int)($_POST['dia'] ?? 0);
$momento = $_POST['momento'] ?? ''; // 'comida' o 'cena'

if ($menuId <= 0 || $dia <= 0 || !in_array($momento, ['comida', 'cena'])) {
    echo json_encode(['success' => false, 'message' => 'Parámetros inválidos.']);
    exit;
}

// 4. Verificar que el menú pertenece al usuario
$menu = new Menu($menuId);
if (!$menu->getId() || $menu->getUsuarioCreadorId() != getCurrentUserId()) {
    echo json_encode(['success' => false, 'message' => 'Menú no encontrado o no autorizado.']);
    exit;
}

// 5. Eliminar el plato
try {
    $result = $menu->removePlato($dia, $momento); // Devuelve 'eliminado' | 'ya_vacio' | 'error' (Decisión 42)

    if ($result === 'eliminado') {
        echo json_encode(['success' => true, 'message' => 'Plato eliminado. Hueco libre.']);
    } elseif ($result === 'ya_vacio') {
        echo json_encode(['success' => true, 'message' => 'El hueco ya estaba vacío.']);
    } else {
        echo json_encode(['success' => false, 'message' => 'No se pudo eliminar el plato.']);
    }

} catch (Exception $e) {
    if (DEBUG_MODE) {
        error_log("Error en remove_from_effective: " . $e->getMessage());
    }
    echo json_encode(['success' => false, 'message' => 'Error interno del servidor.']);
}
?>