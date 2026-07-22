<?php
/**
 * CONFIGURACIÓN GLOBAL DE LA APLICACIÓN
 * =====================================
 * Archivo de constantes y configuración base para f00dlist
 * 
 * Este archivo define:
 * - Constantes de la aplicación
 * - Configuración de Base de Datos (vía variables de entorno)
 * - Configuración de Sesión y Seguridad
 * - Rutas y Límites de negocio
 * 
 * Las credenciales sensibles se leen de variables de entorno (getenv).
 * Ver .env.example para las variables requeridas en el servidor.
 * (Decisión 10 / Decisión 43: sin secretos hardcodeados en el repo)
 */

// Evitar acceso directo al archivo
if (!defined('APP_NAME')) {
    define('APP_NAME', 'f00dlist');
    define('APP_VERSION', '1.0.0');
    define('CURRENT_DATE', date('Y-m-d'));
}

// ============================================================================
// CONFIGURACIÓN DE BASE DE DATOS (MARIADB) — desde variables de entorno
// ============================================================================
define('DB_HOST', getenv('DB_HOST') ?: 'localhost');
define('DB_NAME', getenv('DB_NAME') ?: 'inivi_f00dlist');
define('DB_USER', getenv('DB_USER') ?: 'inivi_tXU5o0w');
// La contraseña NUNCA se hardcodea en el repo: debe venir de F00DLIST_DB_PASS
define('DB_PASS', getenv('F00DLIST_DB_PASS') ?: '');
define('DB_CHARSET', 'utf8mb4');

// ============================================================================
// CONFIGURACIÓN DE SESIÓN Y SEGURIDAD
// ============================================================================
// Configuración segura de cookies
ini_set('session.cookie_httponly', 1);
ini_set('session.cookie_secure', isset($_SERVER['HTTPS']));
ini_set('session.use_strict_mode', 1);
ini_set('session.cookie_samesite', 'Strict');
ini_set('session.gc_maxlifetime', 3600); // 1 hora

// ============================================================================
// RUTAS DE LA APLICACIÓN
// ============================================================================
// Detectar protocolo (http/https)
$protocol = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http';
$host = $_SERVER['HTTP_HOST'] ?? 'localhost';
// Base path desde entorno (Decisión 10/43): default raíz '/'
$basePathRaw = getenv('F00DLIST_BASE_PATH') ?: '/';
$basePath = '/' . trim($basePathRaw, '/') . '/';

define('BASE_URL', $protocol . '://' . $host . $basePath);
define('ROOT_PATH', dirname(__DIR__));

// ============================================================================
// LÍMITES DE GENERACIÓN DE MENÚS
// ============================================================================
define('MIN_DIAS_GENERACION', 1);
define('MAX_DIAS_GENERACION', 14); // Máximo 2 semanas

// ============================================================================
// REGLAS DE NEGOCIO (CONSTANTES)
// ============================================================================
// Distancias mínimas entre tipos de platos
define('DISTANCIA_PASTA', 4);        // ≥ 4 días entre pasta
define('DISTANCIA_FAJITAS', 9);      // ≥ 9 días entre fajitas
define('DISTANCIA_TORTILLAS', 5);    // ≥ 5 días entre tortillas
define('DISTANCIA_CREMAS', 5);       // ≥ 5 días entre cremas
define('PESCADO_CADA_X_DIAS', 7);    // Al menos 1 pescado cada 7 días

// ============================================================================
// TIPOS DE CONTENIDO Y ESTADOS
// ============================================================================
// Tipos de menú
define('TIPO_ACTUAL', 'actual');
define('TIPO_FAVORITO', 'favorito');

// Momentos del día
define('MOMENTO_COMIDA', 'comida');
define('MOMENTO_CENA', 'cena');

// Niveles calóricos
define('NIVEL_CALORICO_BAJO', 'bajo');
define('NIVEL_CALORICO_MEDIO', 'medio');
define('NIVEL_CALORICO_ALTO', 'alto');

// ============================================================================
// CONFIGURACIÓN DE ERRORES Y DEBUG
// ============================================================================
// En producción false por defecto (Decisión 10/43): se activa solo con F00DLIST_DEBUG=true
define('DEBUG_MODE', filter_var(getenv('F00DLIST_DEBUG'), FILTER_VALIDATE_BOOLEAN));

if (DEBUG_MODE) {
    error_reporting(E_ALL);
    ini_set('display_errors', 1);
} else {
    error_reporting(0);
    ini_set('display_errors', 0);
    // En producción, registrar errores en log file (opcional)
    // ini_set('log_errors', 1);
    // ini_set('error_log', ROOT_PATH . '/logs/error.log');
}

// ============================================================================
// SEGURIDAD
// ============================================================================
define('CSRF_TOKEN_LENGTH', 32);
define('PASSWORD_MIN_LENGTH', 6);
define('USERNAME_MIN_LENGTH', 3);
define('USERNAME_MAX_LENGTH', 50);
define('EMAIL_MAX_LENGTH', 100);
define('NOMBRE_COMPLETO_MAX_LENGTH', 100); // Decisión 37

// ============================================================================
// PDF CONFIGURATION (Para TCPDF/Dompdf)
// ============================================================================
define('PDF_FONT_SIZE', 12);
define('PDF_MARGIN_TOP', 20);
define('PDF_MARGIN_BOTTOM', 20);
define('PDF_MARGIN_LEFT', 15);
define('PDF_MARGIN_RIGHT', 15);

// ============================================================================
// PAGINACIÓN
// ============================================================================
define('ITEMS_PER_PAGE', 20);

// ============================================================================
// INICIALIZACIÓN
// ============================================================================
// Iniciar sesión si no está iniciada
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Establecer zona horaria (Ajustar según ubicación)
date_default_timezone_set('Europe/Madrid');

// Setear encoding por defecto
mb_internal_encoding('UTF-8');

// ============================================================================
// HELPER: Obtener ruta relativa desde ROOT
// ============================================================================
function asset($path) {
    return BASE_URL . 'assets/' . ltrim($path, '/');
}

function url($path = '') {
    return BASE_URL . ltrim($path, '/');
}

// ============================================================================
// SEGURIDAD: Cabeceras HTTP (Decisión 24 / 38)
// ============================================================================
function applySecurityHeaders() {
    if (headers_sent()) {
        return;
    }
    header("Content-Security-Policy: default-src 'self'; img-src 'self' data:; style-src 'self' 'unsafe-inline'; script-src 'self' 'unsafe-inline'; frame-ancestors 'none'");
    header("X-Frame-Options: DENY");
    header("X-Content-Type-Options: nosniff");
    header("Referrer-Policy: no-referrer");
    if (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on') {
        header("Strict-Transport-Security: max-age=31536000; includeSubDomains");
    }
}
applySecurityHeaders();