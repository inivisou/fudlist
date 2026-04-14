<?php
/**
 * CONEXIÓN A BASE DE DATOS (PDO)
 * ==============================
 * Patrón Singleton para gestión segura de conexiones a MariaDB.
 * Proporciona funciones helper para consultas preparadas.
 */

require_once __DIR__ . '/config.php';

class Database {
    private static $instance = null;
    private $connection;
    
    /**
     * Constructor privado (Singleton)
     */
    private function __construct() {
        try {
            $dsn = "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=" . DB_CHARSET;
            
            $options = [
                PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION, // Lanzar excepciones en error
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,       // Fetch asociativo por defecto
                PDO::ATTR_EMULATE_PREPARES   => false,                  // Preparados reales (seguridad)
                PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci"
            ];
            
            $this->connection = new PDO($dsn, DB_USER, DB_PASS, $options);
            
            // Opcional: Verificar conexión en modo debug
            if (DEBUG_MODE) {
                // echo "Conexión a BD establecida correctamente.";
            }
        } catch (PDOException $e) {
            // Log de error (en producción no mostrar el error al usuario)
            $errorMsg = "Error de conexión a la base de datos: " . $e->getMessage();
            
            if (DEBUG_MODE) {
                die($errorMsg);
            } else {
                // En producción, registrar en log y mostrar mensaje genérico
                error_log($errorMsg);
                die("Error interno del servidor. Por favor, inténtalo más tarde.");
            }
        }
    }
    
    /**
     * Obtener instancia única
     */
    public static function getInstance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    /**
     * Obtener la conexión PDO
     */
    public function getConnection() {
        return $this->connection;
    }
    
    // Prevenir clonación
    private function __clone() {}
    
    // Prevenir deserialización
    public function __wakeup() {
        throw new Exception("Cannot unserialize singleton");
    }
}

// ============================================================================
// FUNCIONES HELPERS (Globales para facilitar uso)
// ============================================================================

/**
 * Obtener conexión PDO
 */
function getDB() {
    return Database::getInstance()->getConnection();
}

/**
 * Preparar y ejecutar una consulta SQL con parámetros
 * @param string $sql
 * @param array $params
 * @return PDOStatement
 */
function prepareAndExecute($sql, $params = []) {
    $db = getDB();
    $stmt = $db->prepare($sql);
    $stmt->execute($params);
    return $stmt;
}

/**
 * Obtener un solo registro (primera fila)
 * @param string $sql
 * @param array $params
 * @return array|null
 */
function fetchOne($sql, $params = []) {
    $stmt = prepareAndExecute($sql, $params);
    return $stmt->fetch();
}

/**
 * Obtener múltiples registros (todas las filas)
 * @param string $sql
 * @param array $params
 * @return array
 */
function fetchAll($sql, $params = []) {
    $stmt = prepareAndExecute($sql, $params);
    return $stmt->fetchAll();
}

/**
 * Obtener el ID del último registro insertado
 * @return string
 */
function getLastInsertId() {
    return getDB()->lastInsertId();
}

/**
 * Contar filas afectadas por una operación (UPDATE, DELETE, INSERT)
 * @param string $sql
 * @param array $params
 * @return int
 */
function rowCount($sql, $params = []) {
    $stmt = prepareAndExecute($sql, $params);
    return $stmt->rowCount();
}

/**
 * Ejecutar una consulta que no devuelve resultados (ej: INSERT, UPDATE, DELETE)
 * Devuelve true si fue exitoso, false si falló.
 * @param string $sql
 * @param array $params
 * @return bool
 */
function executeQuery($sql, $params = []) {
    try {
        prepareAndExecute($sql, $params);
        return true;
    } catch (PDOException $e) {
        if (DEBUG_MODE) {
            error_log("Error en executeQuery: " . $e->getMessage());
        }
        return false;
    }
}

/**
 * Iniciar transacción
 */
function beginTransaction() {
    getDB()->beginTransaction();
}

/**
 * Confirmar transacción
 */
function commit() {
    getDB()->commit();
}

/**
 * Revertir transacción
 */
function rollback() {
    getDB()->rollBack();
}
?>