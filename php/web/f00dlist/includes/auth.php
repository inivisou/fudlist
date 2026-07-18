<?php
/**
 * AUTENTICACIÓN Y GESTIÓN DE ROLES Y PERMISOS
 * ===========================================
 * Funciones para:
 * - Registro y Login seguro
 * - Gestión de sesiones
 * - Verificación de roles y permisos
 * - Tokens CSRF
 */

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/db.php';

// ============================================================================
// FUNCIONES BÁSICAS DE SESIÓN
// ============================================================================

/**
 * Verificar si el usuario está logueado
 * @return bool
 */
function isLoggedIn() {
    return isset($_SESSION['user_id']) && !empty($_SESSION['user_id']);
}

/**
 * Obtener el ID del usuario logueado
 * @return int|null
 */
function getCurrentUserId() {
    return $_SESSION['user_id'] ?? null;
}

/**
 * Obtener el username del usuario logueado
 * @return string|null
 */
function getCurrentUsername() {
    return $_SESSION['username'] ?? null;
}

/**
 * Obtener el email del usuario logueado
 * @return string|null
 */
function getCurrentUserEmail() {
    return $_SESSION['email'] ?? null;
}

/**
 * Redirigir si no está logueado
 * @param string $redirectUrl URL de destino (por defecto login)
 */
function requireLogin($redirectUrl = null) {
    if (!isLoggedIn()) {
        $url = $redirectUrl ?? url('login.php');
        header('Location: ' . $url);
        exit;
    }
}

/**
 * Redirigir si NO es admin (para páginas solo admin)
 */
function requireAdmin() {
    requireLogin();
    if (!isAdmin()) {
        header('Location: ' . url('index.php'));
        exit;
    }
}

// ============================================================================
// REGISTRO Y LOGIN
// ============================================================================

/**
 * Registrar un nuevo usuario
 * @param string $username
 * @param string $email
 * @param string $password
 * @param string $nombreCompleto
 * @return array ['success' => bool, 'message' => string, 'user_id' => int|null]
 */
function registerUser($username, $email, $password, $nombreCompleto) {
    // Validaciones básicas
    if (strlen($username) < USERNAME_MIN_LENGTH || strlen($username) > USERNAME_MAX_LENGTH) {
        return ['success' => false, 'message' => 'El nombre de usuario debe tener entre ' . USERNAME_MIN_LENGTH . ' y ' . USERNAME_MAX_LENGTH . ' caracteres'];
    }
    
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        return ['success' => false, 'message' => 'Email inválido'];
    }
    
    if (strlen($password) < PASSWORD_MIN_LENGTH) {
        return ['success' => false, 'message' => 'La contraseña debe tener al menos ' . PASSWORD_MIN_LENGTH . ' caracteres'];
    }
    
    // Verificar si el usuario o email ya existen
    $checkSql = "SELECT id FROM users WHERE username = ? OR email = ?";
    $existing = fetchOne($checkSql, [$username, $email]);
    
    if ($existing) {
        return ['success' => false, 'message' => 'El nombre de usuario o email ya están registrados'];
    }
    
    // Hash de contraseña (Bcrypt)
    $passwordHash = password_hash($password, PASSWORD_BCRYPT);
    
    try {
        // Insertar usuario
        $insertSql = "INSERT INTO users (username, email, password_hash, nombre_completo) VALUES (?, ?, ?, ?)";
        executeQuery($insertSql, [$username, $email, $passwordHash, $nombreCompleto]);
        
        $userId = getLastInsertId();
        
        // Asignar rol de usuario por defecto buscando por nombre
        $assignRoleSql = "INSERT INTO usuarios_roles (id_usuario, id_rol) SELECT ?, id FROM roles WHERE nombre = 'user'";
        executeQuery($assignRoleSql, [$userId]);
        
        return ['success' => true, 'user_id' => $userId, 'message' => 'Registro exitoso'];
    } catch (Exception $e) {
        if (DEBUG_MODE) {
            return ['success' => false, 'message' => 'Error en el registro: ' . $e->getMessage()];
        }
        return ['success' => false, 'message' => 'Error interno del servidor'];
    }
}

/**
 * Iniciar sesión
 * @param string $username (puede ser username o email)
 * @param string $password
 * @return array ['success' => bool, 'message' => string, 'user' => array|null]
 */
function loginUser($username, $password) {
    $sql = "SELECT id, username, email, password_hash, nombre_completo, activo 
            FROM users 
            WHERE (username = ? OR email = ?) AND activo = 1";
    $user = fetchOne($sql, [$username, $username]);
    
    if (!$user) {
        return ['success' => false, 'message' => 'Usuario o contraseña incorrectos'];
    }
    
    if (!password_verify($password, $user['password_hash'])) {
        return ['success' => false, 'message' => 'Usuario o contraseña incorrectos'];
    }
    
    // Verificar si el usuario está activo
    if (!$user['activo']) {
        return ['success' => false, 'message' => 'Tu cuenta está desactivada. Contacta con el administrador'];
    }
    
    // Iniciar sesión
    $_SESSION['user_id'] = $user['id'];
    $_SESSION['username'] = $user['username'];
    $_SESSION['email'] = $user['email'];
    $_SESSION['nombre_completo'] = $user['nombre_completo'];
    $_SESSION['logged_in'] = time();
    
    // Regenerar ID de sesión para prevenir session fixation
    session_regenerate_id(true);
    
    return ['success' => true, 'user' => $user, 'message' => 'Login exitoso'];
}

/**
 * Cerrar sesión
 */
function logoutUser() {
    // Limpiar variables de sesión
    $_SESSION = [];
    
    // Borrar cookie de sesión
    if (ini_get("session.use_cookies")) {
        $params = session_get_cookie_params();
        setcookie(
            session_name(), 
            '', 
            time() - 42000, 
            $params["path"], 
            $params["domain"], 
            $params["secure"], 
            $params["httponly"]
        );
    }
    
    session_destroy();
}

// ============================================================================
// GESTIÓN DE ROLES Y PERMISOS
// ============================================================================

/**
 * Obtener roles del usuario actual
 * @param int|null $userId (si es null, usa el usuario logueado)
 * @return array
 */
function getUserRoles($userId = null) {
    if ($userId === null) {
        $userId = getCurrentUserId();
    }
    
    if (!$userId) {
        return [];
    }
    
    $sql = "SELECT r.id, r.nombre, r.descripcion 
            FROM roles r 
            INNER JOIN usuarios_roles ur ON r.id = ur.id_rol 
            WHERE ur.id_usuario = ?";
    
    return fetchAll($sql, [$userId]);
}

/**
 * Obtener todos los usuarios con sus roles concatenados
 * @return array
 */
function getAllUsersWithRoles() {
    $sql = "SELECT u.*, 
            GROUP_CONCAT(r.nombre SEPARATOR ', ') as roles 
            FROM users u
            LEFT JOIN usuarios_roles ur ON u.id = ur.id_usuario
            LEFT JOIN roles r ON ur.id_rol = r.id
            GROUP BY u.id
            ORDER BY u.activo DESC, u.username ASC";
    return fetchAll($sql);
}

/**
 * Asignar un único rol a un usuario (reemplaza los anteriores)
 * @param int $userId
 * @param string $roleName
 * @return bool
 */
function syncUserRole($userId, $roleName) {
    try {
        beginTransaction();
        // 1. Eliminar roles actuales
        executeQuery("DELETE FROM usuarios_roles WHERE id_usuario = ?", [$userId]);
        // 2. Insertar nuevo rol buscando por nombre
        $sql = "INSERT INTO usuarios_roles (id_usuario, id_rol) 
                SELECT ?, id FROM roles WHERE nombre = ?";
        $success = executeQuery($sql, [$userId, $roleName]);
        commit();
        return $success;
    } catch (Exception $e) {
        rollback();
        return false;
    }
}

/**
 * Cambiar el estado activo de un usuario
 * @param int $userId
 * @param int $active (1 o 0)
 * @return bool
 */
function setUserActiveState($userId, $active) {
    $sql = "UPDATE users SET activo = ? WHERE id = ?";
    return executeQuery($sql, [$active, $userId]);
}

function getEligibleComensals() {
    $sql = "SELECT u.id, u.username, u.nombre_completo 
            FROM users u 
            WHERE u.activo = 1 AND u.id NOT IN (SELECT ur.id_usuario FROM usuarios_roles ur JOIN roles r ON ur.id_rol = r.id WHERE r.nombre = 'admin') 
            ORDER BY u.nombre_completo ASC";
    return fetchAll($sql);
}

/**
 * Obtener permisos del usuario actual (agrupados por roles)
 * @param int|null $userId
 * @return array Array de strings con los nombres de los permisos
 */
function getUserPermissions($userId = null) {
    if ($userId === null) {
        $userId = getCurrentUserId();
    }
    
    if (!$userId) {
        return [];
    }
    
    $sql = "SELECT DISTINCT p.nombre 
            FROM permisos p 
            INNER JOIN roles_permisos rp ON p.id = rp.id_permiso 
            INNER JOIN usuarios_roles ur ON rp.id_rol = ur.id_rol 
            WHERE ur.id_usuario = ?";
    
    $permissions = fetchAll($sql, [$userId]);
    
    // Extraer solo los nombres de permisos
    return array_column($permissions, 'nombre');
}

/**
 * Verificar si el usuario tiene un permiso específico
 * @param string $permissionName
 * @param int|null $userId
 * @return bool
 */
function hasPermission($permissionName, $userId = null) {
    $permissions = getUserPermissions($userId);
    return in_array($permissionName, $permissions);
}

/**
 * Verificar si el usuario tiene un rol específico por nombre
 * @param string $roleName
 * @param int|null $userId
 * @return bool
 */
function hasRole($roleName, $userId = null) {
    $roles = getUserRoles($userId);
    foreach ($roles as $role) {
        if ($role['nombre'] === $roleName) {
            return true;
        }
    }
    return false;
}

/**
 * Verificar si el usuario es admin (tiene el rol 'admin')
 * @param int|null $userId
 * @return bool
 */
function isAdmin($userId = null) {
    return hasRole('admin', $userId);
}

/**
 * Verificar si el usuario tiene uno de varios permisos
 * @param array $permissionNames
 * @param int|null $userId
 * @return bool
 */
function hasAnyPermission($permissionNames, $userId = null) {
    $permissions = getUserPermissions($userId);
    foreach ($permissionNames as $perm) {
        if (in_array($perm, $permissions)) {
            return true;
        }
    }
    return false;
}

// ============================================================================
// SEGURIDAD: TOKENS CSRF
// ============================================================================

/**
 * Generar token CSRF si no existe
 * @return string
 */
function generateCSRFToken() {
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(CSRF_TOKEN_LENGTH));
    }
    return $_SESSION['csrf_token'];
}

/**
 * Validar token CSRF
 * @param string $token
 * @return bool
 */
function validateCSRFToken($token) {
    return isset($_SESSION['csrf_token']) && hash_equals($_SESSION['csrf_token'], $token);
}

/**
 * Generar un campo oculto HTML para el token CSRF
 * @return string
 */
function csrfField() {
    return '<input type="hidden" name="csrf_token" value="' . generateCSRFToken() . '">';
}

// ============================================================================
// DATOS DEL USUARIO
// ============================================================================

/**
 * Obtener datos completos del usuario actual
 * @return array|null
 */
function getCurrentUserData() {
    $userId = getCurrentUserId();
    if (!$userId) {
        return null;
    }
    
    $sql = "SELECT * FROM users WHERE id = ?";
    return fetchOne($sql, [$userId]);
}

/**
 * Actualizar datos del usuario actual
 * @param array $data ['nombre_completo', 'avatar_url', etc.]
 * @return bool
 */
function updateCurrentUser($data) {
    $userId = getCurrentUserId();
    if (!$userId) {
        return false;
    }
    
    // Construir SET dinámico
    $setParts = [];
    $params = [];
    
    foreach ($data as $key => $value) {
        if (in_array($key, ['nombre_completo', 'avatar_url'])) {
            $setParts[] = "$key = ?";
            $params[] = $value;
        }
    }
    
    if (empty($setParts)) {
        return false;
    }
    
    $params[] = $userId;
    $sql = "UPDATE users SET " . implode(', ', $setParts) . " WHERE id = ?";
    
    return executeQuery($sql, $params);
}
?>