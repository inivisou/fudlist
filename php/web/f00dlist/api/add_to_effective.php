<?php
/**
 * API: ADD TO EFFECTIVE
 * =====================
 * Recibe un plato del menú tentativo y lo añade al menú efectivo.
 * Busca el primer hueco libre si no se especifica día/momento, o lo pone en la posición indicada.
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
$platoId = (int)($_POST['plato_id'] ?? 0);

if ($menuId <= 0 || $platoId <= 0 || !in_array($momento, ['comida', 'cena'])) {
    echo json_encode(['success' => false, 'message' => 'Parámetros inválidos.']);
    exit;
}

// 4. Verificar que el menú pertenece al usuario
$menu = new Menu($menuId);
if (!$menu->getId() || $menu->getUsuarioCreadorId() != getCurrentUserId()) {
    echo json_encode(['success' => false, 'message' => 'Menú no encontrado o no autorizado.']);
    exit;
}

// 5. Lógica de inserción
try {
    // Si se especificó día y momento, intentamos ponerlo ahí
    if ($dia > 0) {
        // Verificar si ya hay algo ahí (si es así, podríamos devolver error o sobrescribir)
        // En este caso, sobrescribimos si el usuario hizo clic en el tentativo sobre un hueco libre
        // Pero si ya hay algo, quizás queremos devolver error.
        // Para simplificar: si hay algo, no hacemos nada o devolvemos mensaje.
        
        // Comprobamos si ya existe un plato en esa posición
        $checkSql = "SELECT id_plato FROM menu_dias WHERE id_menu = ? AND dia_numero = ? AND tipo_momento = ?";
        $existing = fetchOne($checkSql, [$menuId, $dia, $momento]);
        
        if ($existing && $existing['id_plato'] !== null) {
            // Ya hay un plato, no hacemos nada (o podríamos devolver un error)
            echo json_encode(['success' => true, 'message' => 'Ya hay un plato en esa posición.']);
            exit;
        }
        
        // Asignar
        $success = $menu->setPlato($dia, $momento, $platoId);
    } else {
        // Si no se especificó día, buscar el primer hueco libre
        $freeSlot = $menu->getFirstFreeSlot(MAX_DIAS_GENERACION);
        
        if (!$freeSlot) {
            echo json_encode(['success' => false, 'message' => 'No hay huecos libres en el menú.']);
            exit;
        }
        
        $success = $menu->setPlato($freeSlot['dia'], $freeSlot['momento'], $platoId);
    }

    if ($success) {
        echo json_encode(['success' => true, 'message' => 'Plato añadido correctamente.']);
    } else {
        echo json_encode(['success' => false, 'message' => 'Error al guardar el plato.']);
    }

} catch (Exception $e) {
    if (DEBUG_MODE) {
        error_log("Error en add_to_effective: " . $e->getMessage());
    }
    echo json_encode(['success' => false, 'message' => 'Error interno del servidor.']);
}
?>