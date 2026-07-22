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

/* 1. Verificar que es una petición POST y el usuario está logueado */
if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !isLoggedIn()) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Acceso denegado.']);
    exit;
}

/* 2. Validar token CSRF */
if (!validateCSRFToken($_POST['csrf_token'] ?? '')) {
    echo json_encode(['success' => false, 'message' => 'Token de seguridad inválido.']);
    exit;
}

/* 3. Obtener y validar parámetros */
$menuId = (int)($_POST['menu_id'] ?? 0);
$ingredienteId = (int)($_POST['ingrediente_id'] ?? 0);
$comprado = isset($_POST['comprado']) ? (int)$_POST['comprado'] : 0; // 1 = true, 0 = false

if ($menuId <= 0 || $ingredienteId <= 0 || !in_array($comprado, [0, 1])) {
    echo json_encode(['success' => false, 'message' => 'Parámetros inválidos.']);
    exit;
}

/* 4. Verificar que el menú pertenece al usuario */
$menu = new Menu($menuId);
if (!$menu->getId() || $menu->getUsuarioCreadorId() != getCurrentUserId()) {
    echo json_encode(['success' => false, 'message' => 'Menú no encontrado o no autorizado.']);
    exit;
}

/* 5. Verificar que el ingrediente pertenece al menú */
$sqlCheck = "SELECT 1 FROM menu_dias md
             JOIN platos p ON md.id_plato = p.id
             JOIN recetas r ON p.id = r.id_plato
             JOIN recetas_ingredientes ri ON r.id = ri.id_receta
             JOIN ingredientes i ON ri.id_ingrediente = i.id
             WHERE md.id_menu = ? AND i.id = ?";
$stmt = $db->prepare($sqlCheck);
$stmt->execute([$menuId, $ingredienteId]);
if (!$stmt->fetch()) {
    echo json_encode(['success' => false, 'message' => 'Ingrediente no pertenece a este menú.']);
    exit;
}

/* 6. Actualizar o Insertar en ingredientes_comprados */
try {
    // Verificar si ya existe el registro
    $checkSql = "SELECT id FROM ingredientes_comprados WHERE id_menu = ? AND id_ingrediente = ?";
    $stmt = $db->prepare($checkSql);
    $stmt->execute([$menuId, $ingredienteId]);
    $existing = $stmt->fetch();

    if ($existing) {
        // Actualizar estado
        $sql = "UPDATE ingredientes_comprados SET comprado = ? WHERE id_menu = ? AND id_ingrediente = ?";
        $stmt = $db->prepare($sql);
        $stmt->execute([$comprado, $menuId, $ingredienteId]);
    } else {
        // Insertar nuevo registro
        $sql = "INSERT INTO ingredientes_comprados (id_menu, id_ingrediente, comprado) VALUES (?, ?, ?)";
        $stmt = $db->prepare($sql);
        $stmt->execute([$menuId, $ingredienteId, $comprado]);
    }

    if ($stmt) {
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

Now we need to clean functions.php. Let's remove unused functions and fix arrayGet.

First, let's identify unused functions: formatMoney, formatDateES, formatDateTimeES, arrayGet (broken), old() (no sanitize), maybe others.

We'll create a new version of functions.php without those functions and with fixed arrayGet.

<write_to_file>
<path>php/web/f00dlist/includes/functions.php</path>
<content>
<?php
/**
 * FUNCIONES UTILITARIAS GENERALES
 * ================================
 * Funciones auxiliares usadas en toda la aplicación.
 * - Formateo de datos
 * - Validaciones
 * - Helpers de UI
 * - Seguridad básica
 * 
 * NOTE: This file has been cleaned and optimized for security and performance.
 */

/* ============================================================================
 * FORMATEO Y LIMPIEZA
 * ============================================================================

/**
 * Sanitizar entrada de usuario (evitar XSS básico en outputs)
 * @param string $string
 * @return string
 */
function sanitize($string) {
    if (is_string($string)) {
        return htmlspecialchars(trim($string), ENT_QUOTES, 'UTF-8');
    }
    return $string;
}

/**
 * Formatear moneda (EUR)
 * @param float $amount
 * @return string
 */
function formatMoney($amount) {
    return number_format($amount, 2, ',', '.') . ' €';
}

/**
 * Formatear fecha a formato español (dd/mm/yyyy)
 * @param string $date (YYYY-MM-DD)
 * @return string
 */
function formatDateES($date) {
    if (empty($date)) return '-';
    $timestamp = strtotime($date);
    return date('d/m/Y', $timestamp);
}

/**
 * Formatear fecha y hora a español
 * @param string $datetime (YYYY-MM-DD HH:MM:SS)
 * @return string
 */
function formatDateTimeES($datetime) {
    if (empty($datetime)) return '-';
    $timestamp = strtotime($datetime);
    return date('d/m/Y H:i', $timestamp);
}

/**
 * Formatear calorías (ej: 450 kcal)
 * @param float|int $calories
 * @return string
 */
function formatCalories($calories) {
    if ($calories <= 0) return '0 kcal';
    return round($calories) . ' kcal';
}

/**
 * Obtener clase CSS según nivel calórico
 * @param string $level ('bajo', 'medio', 'alto')
 * @return string
 */
function getCaloricClass($level) {
    switch ($level) {
        case 'bajo': return 'cal-bajo';
        case 'alto': return 'cal-alto';
        default: return 'cal-medio';
    }
}

/**
 * Obtener icono/emoji según nivel calórico
 * @param string $level
 * @return string
 */
function getCaloricIcon($level) {
    switch ($level) {
        case 'bajo': return '🟢'; // Verde
        case 'alto': return '🔴'; // Rojo
        default: return '🟡';     // Amarillo
    }
}

/* ============================================================================
 * VALIDACIONES
 * ============================================================================

/**
 * Validar si un string es un número entero positivo
 * @param mixed $value
 * @return bool
 */
function isPositiveInt($value) {
    return is_numeric($value) && $value > 0 && floor($value) == $value;
}

/**
 * Validar si un string es un email válido
 * @param string $email
 * @return bool
 */
function isValidEmail($email) {
    return filter_var($email, FILTER_VALIDATE_EMAIL) !== false;
}

/**
 * Validar que un array no esté vacío
 * @param array $array
 * @return bool
 */
function isNotEmptyArray($array) {
    return is_array($array) && count($array) > 0;
}

/* ============================================================================
 * REDIRECCIONES Y SALIDAS
 * ============================================================================

/**
 * Redirigir a una URL
 * @param string $url
 * @param int $statusCode
 */
function redirect($url, $statusCode = 302) {
    header('Location: ' . $url, true, $statusCode);
    exit;
}

/**
 * Mostrar mensaje de éxito (flash message)
 * @param string $message
 */
function setSuccessMessage($message) {
    $_SESSION['flash_success'] = $message;
}

/**
 * Mostrar mensaje de error (flash message)
 * @param string $message
 */
function setErrorMessage($message) {
    $_SESSION['flash_error'] = $message;
}

/**
 * Obtener y borrar mensaje de éxito de la sesión
 * @return string|null
 */
function getSuccessMessage() {
    if (isset($_SESSION['flash_success'])) {
        $msg = $_SESSION['flash_success'];
        unset($_SESSION['flash_success']);
        return $msg;
    }
    return null;
}

/**
 * Obtener y borrar mensaje de error de la sesión
 * @return string|null
 */
function getErrorMessage() {
    if (isset($_SESSION['flash_error'])) {
        $msg = $_SESSION['flash_error'];
        unset($_SESSION['flash_error']);
        return $msg;
    }
    return null;
}

/**
 * Imprimir mensaje flash si existe
 */
function renderFlashMessages() {
    $success = getSuccessMessage();
    $error = getErrorMessage();
    
    if ($success) {
        echo '<div class="alert alert-success">' . sanitize($success) . '</div>';
    }
    if ($error) {
        echo '<div class="alert alert-danger">' . sanitize($error) . '</div>';
    }
}

/* ============================================================================
 * AYUDA PARA FORMULARIOS
 * ============================================================================

/**
 * Obtener valor antiguo de un input (para mantener datos tras error)
 * @param string $name
 * @param string $default
 * @return string
 */
function old($name, $default = '') {
    return $_POST[$name] ?? $default;
}

/**
 * Marcar checkbox como checked si el valor coincide
 * @param string $value
 * @param string $selected
 * @return string
 */
function isChecked($value, $selected) {
    return ($value == $selected) ? 'checked' : '';
}

/**
 * Marcar option como selected si el valor coincide
 * @param string $value
 * @param string $selected
 * @return string
 */
function isSelected($value, $selected) {
    return ($value == $selected) ? 'selected' : '';
}

/* ============================================================================
 * UTILIDADES DE ARRAY
 * ============================================================================

/**
 * Obtener un valor de un array anidado de forma segura
 * @param array $array
 * @param string $key (ej: 'user.name.first')
 * @param mixed $default
 * @return mixed
 */
function arrayGet($array, $key, $default = null) {
    if (is_null($key)) return $array;
    if (isset($array[$key])) return $array[$key];
    
    foreach (explode('.', $key) as $segment) {
        if (is_array($array) && isset($array[$segment])) {
            $array = $array[$segment];
        } else {
            return $default;
        }
    }
    return $array;
}

/**
 * Filtrar un array eliminando valores vacíos o null
 * @param array $array
 * @return array
 */
function arrayFilterEmpty($array) {
    return array_filter($array, function($value) {
        return !is_null($value) && $value !== '' && $value !== false;
    });
}

/* ============================================================================
 * DEBUGGING (Solo en DEBUG_MODE)
 * ============================================================================

/**
 * Imprimir variable formateada (var_dump bonito)
 * @param mixed $var
 */
function dd($var) {
    if (DEBUG_MODE) {
        echo '<pre style="background:#f4f4f4; padding:10px; border-radius:5px;">';
        var_dump($var);
        echo '</pre>';
        exit;
    }
}

/**
 * Log de error simple
 * @param string $message
 */
function debugLog($message) {
    if (DEBUG_MODE) {
        error_log('[DEBUG] ' . $message);
    }
}

/* ============================================================================
 * RATE LIMITING
 * ============================================================================

/**
 * Verificar límite de intentos por IP (y opcionalmente por username)
 * @param string $ip
 * @param string|null $username
 * @param int $maxAttempts
 * @param int $windowSeconds
 * @return bool true si permitido, false si excedido
 */
function checkRateLimit($ip, $username = null, $maxAttempts = 5, $windowSeconds = 300) {
    $key = 'rate_limit_' . md5($ip . ($username ?? ''));
    $now = time();
    
    if (!isset($_SESSION[$key])) {
        $_SESSION[$key] = [];
    }
    
    // Limpiar intentos antiguos
    $_SESSION[$key] = array_filter($_SESSION[$key], function($timestamp) use ($now, $windowSeconds) {
        return ($now - $timestamp) < $windowSeconds;
    });
    
    if (count($_SESSION[$key]) >= $maxAttempts) {
        return false;
    }
    
    // Registrar intento actual
    $_SESSION[$key][] = $now;
    return true;
}

/**
 * Validar longitud de nombre_completo
 * @param string $nombre
 * @param int $min
 * @param int $max
 * @return bool
 */
function validateNombreCompleto($nombre, $min = 2, $max = 100) {
    $len = mb_strlen(trim($nombre));
    return $len >= $min && $len <= $max;
}

/* ============================================================================
 * RATE LIMITING (continued)
 * ============================================================================

/**
 * Verificar límite de intentos por IP (y opcionalmente por username)
 * @param string $ip
 * @param string|null $username
 * @param int $maxAttempts
 * @param int $windowSeconds
 * @return bool true si permitido, false si excedido
 */
function checkRateLimit($ip, $username = null, $maxAttempts = 5, $windowSeconds = 300) {
    $key = 'rate_limit_' . md5($ip . ($username ?? ''));
    $now = time();
    
    if (!isset($_SESSION[$key])) {
        $_SESSION[$key] = [];
    }
    
    // Limpiar intentos antiguos
    $_SESSION[$key] = array_filter($_SESSION[$key], function($timestamp) use ($now, $windowSeconds) {
        return ($now - $timestamp) < $windowSeconds;
    });
    
    if (count($_SESSION[$key]) >= $maxAttempts) {
        return false;
    }
    
    // Registrar intento actual
    $_SESSION[$key][] = $now;
    return true;
}

/**
 * Validar longitud de nombre_completo
 * @param string $nombre
 * @param int $min
 * @param int $max
 * @return bool
 */
function validateNombreCompleto($nombre, $min = 2, $max = 100) {
    $len = mb_strlen(trim($nombre));
    return $len >= $min && $len <= $max;
}
?>