<?php
/**
 * PLATAFORMA EDUCATIVA - CONFIGURACIÓN GENERAL
 *
 * Define constantes y configuraciones globales del sistema
 */

// Prevenir acceso directo
defined('APP_ACCESS') or define('APP_ACCESS', true);

// ============================================
// CONFIGURACIÓN DE BASE DE DATOS
// ============================================
define('DB_HOST', 'localhost');
define('DB_NAME', 'plataforma_educativa');
define('DB_USER', 'root');  // Cambiar en producción (000webhost)
define('DB_PASS', '');      // Cambiar en producción (000webhost)
define('DB_CHARSET', 'utf8mb4');

// ============================================
// CONFIGURACIÓN DE EMAIL (SMTP)
// ============================================
define('SMTP_HOST', 'smtp.gmail.com');
define('SMTP_PORT', 587);
define('SMTP_SECURE', 'tls'); // 'tls' o 'ssl'
define('SMTP_USERNAME', 'fabrizzio.andrews@gmail.com');
define('SMTP_PASSWORD', 'ehmv hian qjhg olhx');
define('SMTP_FROM_EMAIL', 'fabrizzio.andrews@gmail.com');
define('SMTP_FROM_NAME', 'Plataforma Educativa');

// ============================================
// CONFIGURACIÓN DE APLICACIÓN
// ============================================
define('APP_NAME', 'Plataforma Educativa');
define('APP_URL', 'http://localhost/plataformaeducativa'); // Cambiar en producción
define('APP_ENV', 'development'); // 'development' o 'production'

// ============================================
// CONFIGURACIÓN DE SESIONES
// ============================================
define('SESSION_LIFETIME', 86400);        // 24 horas en segundos
define('SESSION_COOKIE_NAME', 'plataforma_session');
define('SESSION_SECURE', false);          // true en producción (HTTPS)
define('SESSION_HTTPONLY', true);
define('SESSION_SAMESITE', 'Strict');

// ============================================
// CONFIGURACIÓN DE SEGURIDAD
// ============================================
define('PASSWORD_MIN_LENGTH', 8);
define('TOKEN_LENGTH', 64);
define('MAX_LOGIN_ATTEMPTS', 5);
define('LOGIN_LOCKOUT_TIME', 900); // 15 minutos en segundos

// ============================================
// TIMEZONE
// ============================================
date_default_timezone_set('America/Mexico_City');

// ============================================
// MANEJO DE ERRORES
// ============================================
if (APP_ENV === 'development') {
    error_reporting(E_ALL);
    ini_set('display_errors', 1);
} else {
    error_reporting(0);
    ini_set('display_errors', 0);
    ini_set('log_errors', 1);
    ini_set('error_log', __DIR__ . '/../logs/error.log');
}

// ============================================
// AUTOLOAD DE CLASES
// ============================================
spl_autoload_register(function ($class) {
    $paths = [
        __DIR__ . '/../classes/',
        __DIR__ . '/../includes/'
    ];

    foreach ($paths as $path) {
        $file = $path . $class . '.php';
        if (file_exists($file)) {
            require_once $file;
            return;
        }
    }
});
