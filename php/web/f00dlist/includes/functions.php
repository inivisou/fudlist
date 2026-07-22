<?php
/**
 * FUNCIONES UTILITARIAS GENERALES
 * ================================
 * Funciones auxiliares usadas en toda la aplicación.
 * - Formateo de datos
 * - Validaciones
 * - Helpers de UI
 * - Seguridad básica
 */

require_once __DIR__ . '/../config/config.php';

// ============================================================================
// FORMATEO Y LIMPIEZA
// ============================================================================

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

// ============================================================================
// VALIDACIONES
// ============================================================================

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

// ============================================================================
// REDIRECCIONES Y SALIDAS
// ============================================================================

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

// ============================================================================
// AYUDA PARA FORMULARIOS
// ============================================================================

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

// ============================================================================
// UTILIDADES DE ARRAY
// ============================================================================

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

// ============================================================================
// DEBUGGING (Solo en DEBUG_MODE)
// ============================================================================

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

// ============================================================================
// RATE LIMITING
// ============================================================================

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
