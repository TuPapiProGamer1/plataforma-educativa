<?php
/**
 * PLATAFORMA EDUCATIVA - VERIFICACIÓN DE EMAIL
 *
 * Procesa el token de verificación enviado por email
 */

define('APP_ACCESS', true);
require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/config/db.php';

$message = '';
$messageType = '';
$verified = false;

// Procesar token de verificación
if (isset($_GET['token']) && !empty($_GET['token'])) {
    try {
        $token = $_GET['token'];
        $db = Database::getInstance();

        // Buscar usuario con el token
        $query = "SELECT id, email, is_verified, token_expires_at
                  FROM users
                  WHERE verification_token = :token
                  LIMIT 1";

        $user = $db->fetchOne($query, ['token' => $token]);

        if (!$user) {
            $messageType = 'danger';
            $message = 'Token de verificación inválido o ya utilizado.';

        } elseif ($user['is_verified'] == 1) {
            $messageType = 'info';
            $message = 'Esta cuenta ya ha sido verificada. Puedes iniciar sesión.';
            $verified = true;

        } elseif (strtotime($user['token_expires_at']) < time()) {
            $messageType = 'warning';
            $message = 'El token de verificación ha expirado. Por favor, solicita uno nuevo.';

        } else {
            // Verificar cuenta
            $updateQuery = "UPDATE users
                           SET is_verified = 1,
                               verification_token = NULL,
                               token_expires_at = NULL
                           WHERE id = :id";

            $db->query($updateQuery, ['id' => $user['id']]);

            $messageType = 'success';
            $message = '¡Cuenta verificada exitosamente! Ya puedes iniciar sesión.';
            $verified = true;
        }

    } catch (Exception $e) {
        $messageType = 'danger';
        $message = 'Error al verificar la cuenta. Por favor intenta nuevamente.';
        error_log("Error en verificación: " . $e->getMessage());
    }

} else {
    $messageType = 'danger';
    $message = 'No se proporcionó un token de verificación.';
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Verificación de Cuenta - <?php echo APP_NAME; ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css" rel="stylesheet">
    <style>
        body {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }
        .verify-container {
            background: white;
            border-radius: 15px;
            box-shadow: 0 10px 40px rgba(0,0,0,0.2);
            padding: 40px;
            max-width: 500px;
            width: 100%;
            margin: 20px;
            text-align: center;
        }
        .icon-container {
            font-size: 80px;
            margin-bottom: 20px;
        }
        .icon-success { color: #4caf50; }
        .icon-danger { color: #f44336; }
        .icon-warning { color: #ff9800; }
        .icon-info { color: #2196f3; }
    </style>
</head>
<body>
    <div class="verify-container">
        <div class="icon-container">
            <?php if ($messageType === 'success'): ?>
                <i class="bi bi-check-circle-fill icon-success"></i>
            <?php elseif ($messageType === 'danger'): ?>
                <i class="bi bi-x-circle-fill icon-danger"></i>
            <?php elseif ($messageType === 'warning'): ?>
                <i class="bi bi-exclamation-triangle-fill icon-warning"></i>
            <?php else: ?>
                <i class="bi bi-info-circle-fill icon-info"></i>
            <?php endif; ?>
        </div>

        <h2 class="mb-4">
            <?php
            if ($messageType === 'success') echo 'Verificación Exitosa';
            elseif ($messageType === 'danger') echo 'Error de Verificación';
            elseif ($messageType === 'warning') echo 'Token Expirado';
            else echo 'Cuenta Ya Verificada';
            ?>
        </h2>

        <div class="alert alert-<?php echo $messageType; ?> text-start">
            <?php echo $message; ?>
        </div>

        <?php if ($verified): ?>
            <div class="d-grid gap-2 mt-4">
                <a href="login.php" class="btn btn-primary btn-lg">
                    <i class="bi bi-box-arrow-in-right"></i> Iniciar Sesión
                </a>
            </div>
        <?php else: ?>
            <div class="d-grid gap-2 mt-4">
                <a href="register.php" class="btn btn-secondary">
                    <i class="bi bi-arrow-left"></i> Volver al Registro
                </a>
                <a href="login.php" class="btn btn-outline-primary">
                    <i class="bi bi-box-arrow-in-right"></i> Ir al Login
                </a>
            </div>
        <?php endif; ?>

        <div class="mt-4">
            <small class="text-muted">
                <i class="bi bi-shield-check"></i>
                <?php echo APP_NAME; ?> - Sistema Seguro
            </small>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
