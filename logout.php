<?php
/**
 * PLATAFORMA EDUCATIVA - LOGOUT
 *
 * Cierra la sesión del usuario de forma segura
 */

define('APP_ACCESS', true);
require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/config/db.php';

session_start();

// Verificar que exista una sesión activa
if (isset($_SESSION['user_id']) && isset($_SESSION['session_token'])) {
    try {
        $db = Database::getInstance();
        $userId = $_SESSION['user_id'];
        $sessionToken = $_SESSION['session_token'];

        // Eliminar sesión de la base de datos
        $deleteQuery = "DELETE FROM active_sessions
                       WHERE user_id = :user_id AND session_token = :token";

        $db->query($deleteQuery, [
            'user_id' => $userId,
            'token' => $sessionToken
        ]);

        // Registrar logout en logs
        $logQuery = "INSERT INTO session_logs
                    (user_id, action, device_info, ip_address, details)
                    VALUES
                    (:user_id, 'logout', :device, :ip, :details)";

        $logParams = [
            'user_id' => $userId,
            'device' => $_SERVER['HTTP_USER_AGENT'] ?? 'Unknown',
            'ip' => $_SERVER['REMOTE_ADDR'] ?? 'Unknown',
            'details' => 'Logout manual del usuario'
        ];

        $db->query($logQuery, $logParams);

    } catch (Exception $e) {
        // Registrar error pero continuar con el logout
        error_log("Error al procesar logout: " . $e->getMessage());
    }
}

// Limpiar todas las variables de sesión
$_SESSION = array();

// Destruir la cookie de sesión
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

// Destruir la sesión
session_destroy();

// Redirigir al login
header('Location: login.php?logout=success');
exit;
