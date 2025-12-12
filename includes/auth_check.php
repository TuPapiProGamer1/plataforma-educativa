<?php
/**
 * PLATAFORMA EDUCATIVA - MIDDLEWARE DE AUTENTICACIÓN
 *
 * CRÍTICO: Este archivo debe incluirse al inicio de TODAS las páginas protegidas
 *
 * Funcionalidad:
 * 1. Verifica que el usuario esté autenticado
 * 2. Valida que el token de sesión siga activo en la BD
 * 3. Si la sesión fue rotada, expulsa al usuario
 * 4. Actualiza last_activity para mantener la sesión viva
 *
 * USO: require_once __DIR__ . '/includes/auth_check.php';
 */

defined('APP_ACCESS') or define('APP_ACCESS', true);

// Iniciar sesión si no está iniciada
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/db.php';

/**
 * Cerrar sesión y redirigir
 *
 * @param string $message Mensaje de error
 * @param string $redirectUrl URL de redirección
 */
function forceLogout($message = '', $redirectUrl = 'login.php') {
    // Limpiar variables de sesión
    $_SESSION = array();

    // Destruir cookie de sesión
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

    // Destruir sesión
    session_destroy();

    // Redirigir con mensaje
    if ($message) {
        header("Location: {$redirectUrl}?" . http_build_query(['session_expired' => 1, 'msg' => $message]));
    } else {
        header("Location: {$redirectUrl}?session_expired=1");
    }
    exit;
}

// ===================================================
// VERIFICACIÓN 1: Sesión PHP activa
// ===================================================
if (!isset($_SESSION['user_id']) || !isset($_SESSION['session_token'])) {
    forceLogout('', 'login.php');
}

try {
    $db = Database::getInstance();
    $userId = $_SESSION['user_id'];
    $sessionToken = $_SESSION['session_token'];

    // ===================================================
    // VERIFICACIÓN 2: Token existe en base de datos
    // ===================================================
    $query = "SELECT id, last_activity
              FROM active_sessions
              WHERE user_id = :user_id AND session_token = :token
              LIMIT 1";

    $params = [
        'user_id' => $userId,
        'token' => $sessionToken
    ];

    $activeSession = $db->fetchOne($query, $params);

    // Si el token NO existe en la BD = fue rotado/eliminado
    if (!$activeSession) {

        // Registrar evento de cierre forzado
        try {
            $logQuery = "INSERT INTO session_logs
                        (user_id, action, device_info, ip_address, details)
                        VALUES
                        (:user_id, 'forced_logout', :device, :ip, :details)";

            $logParams = [
                'user_id' => $userId,
                'device' => $_SERVER['HTTP_USER_AGENT'] ?? 'Unknown',
                'ip' => $_SERVER['REMOTE_ADDR'] ?? 'Unknown',
                'details' => 'Sesión rotada por límite de dispositivos concurrentes'
            ];

            $db->query($logQuery, $logParams);
        } catch (Exception $logError) {
            // No detener el proceso si falla el log
            error_log("Error al registrar logout forzado: " . $logError->getMessage());
        }

        // EXPULSAR al usuario
        forceLogout('Tu sesión fue cerrada porque se alcanzó el límite de dispositivos simultáneos.');
    }

    // ===================================================
    // VERIFICACIÓN 3: Sesión no expirada por inactividad
    // ===================================================
    $lastActivity = strtotime($activeSession['last_activity']);
    $currentTime = time();

    // Si la sesión tiene más de SESSION_LIFETIME segundos de inactividad
    if (($currentTime - $lastActivity) > SESSION_LIFETIME) {

        // Eliminar sesión de BD
        $deleteQuery = "DELETE FROM active_sessions WHERE id = :id";
        $db->query($deleteQuery, ['id' => $activeSession['id']]);

        // Registrar expiración
        try {
            $logQuery = "INSERT INTO session_logs
                        (user_id, action, device_info, ip_address, details)
                        VALUES
                        (:user_id, 'session_expired', :device, :ip, :details)";

            $logParams = [
                'user_id' => $userId,
                'device' => $_SERVER['HTTP_USER_AGENT'] ?? 'Unknown',
                'ip' => $_SERVER['REMOTE_ADDR'] ?? 'Unknown',
                'details' => 'Sesión expirada por inactividad'
            ];

            $db->query($logQuery, $logParams);
        } catch (Exception $logError) {
            error_log("Error al registrar expiración: " . $logError->getMessage());
        }

        forceLogout('Tu sesión expiró por inactividad.');
    }

    // ===================================================
    // TODO OK: Actualizar last_activity
    // ===================================================
    $updateQuery = "UPDATE active_sessions
                   SET last_activity = CURRENT_TIMESTAMP
                   WHERE id = :id";

    $db->query($updateQuery, ['id' => $activeSession['id']]);

    // Actualizar timestamp en sesión PHP
    $_SESSION['last_activity'] = $currentTime;

} catch (Exception $e) {
    // Error crítico de base de datos
    error_log("Error crítico en auth_check: " . $e->getMessage());

    // En producción, podrías redirigir a página de error
    if (APP_ENV === 'production') {
        forceLogout('Error de sistema. Por favor intenta nuevamente.');
    } else {
        // En desarrollo, mostrar error
        die("Error en auth_check: " . $e->getMessage());
    }
}

// ===================================================
// SEGURIDAD ADICIONAL: Regenerar ID de sesión periódicamente
// ===================================================
if (!isset($_SESSION['last_regeneration'])) {
    $_SESSION['last_regeneration'] = time();
} elseif (time() - $_SESSION['last_regeneration'] > 1800) { // Cada 30 minutos
    session_regenerate_id(true);
    $_SESSION['last_regeneration'] = time();
}
