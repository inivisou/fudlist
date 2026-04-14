<?php
/**
 * API: TOGGLE INGREDIENT (YA EN CASA)
 * ====================================
 * Marca o desmarca un ingrediente como "comprado" en el menú actual.
 * Actualiza la tabla ingredientes_comprados.
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
$ingredienteId = (int)($_POST['ingrediente_id'] ?? 0);
$comprado = isset($_POST['comprado']) ? (int)$_POST['comprado'] : 0; // 1 = true, 0 = false

if ($menuId <= 0 || $ingredienteId <= 0 || !in_array($comprado, [0, 1])) {
    echo json_encode(['success' => false, 'message' => 'Parámetros inválidos.']);
    exit;
}

// 4. Verificar que el menú pertenece al usuario
$menu = new Menu($menuId);
if (!$menu->getId() || $menu->getUsuarioCreadorId() != getCurrentUserId()) {
    echo json_encode(['success' => false, 'message' => 'Menú no encontrado o no autorizado.']);
    exit;
}

// 5. Actualizar o Insertar en ingredientes_comprados
try {
    // Verificar si ya existe el registro
    $checkSql = "SELECT id FROM ingredientes_comprados WHERE id_menu = ? AND id_ingrediente = ?";
    $existing = fetchOne($checkSql, [$menuId, $ingredienteId]);

    if ($existing) {
        // Actualizar estado
        $sql = "UPDATE ingredientes_comprados SET comprado = ? WHERE id_menu = ? AND id_ingrediente = ?";
        $success = executeQuery($sql, [$comprado, $menuId, $ingredienteId]);
    } else {
        // Insertar nuevo registro
        $sql = "INSERT INTO ingredientes_comprados (id_menu, id_ingrediente, comprado) VALUES (?, ?, ?)";
        $success = executeQuery($sql, [$menuId, $ingredienteId, $comprado]);
    }

    if ($success) {
        echo json_encode([
            'success' => true, 
            'message' => 'Estado actualizado.',
            'comprado' => (bool)$comprado
        ]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Error al actualizar el estado.']);
    }

} catch (Exception $e) {
    if (DEBUG_MODE) {
        error_log("Error en toggle_ingredient: " . $e->getMessage());
    }
    echo json_encode(['success' => false, 'message' => 'Error interno del servidor.']);
}
?>