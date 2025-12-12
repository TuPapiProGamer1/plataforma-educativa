<?php
/**
 * PLATAFORMA EDUCATIVA - LOGIN CON GESTIÓN DE SESIONES CONCURRENTES
 *
 * CRÍTICO: Implementa rotación automática de sesiones según el plan del usuario
 */

define('APP_ACCESS', true);
require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/classes/EmailService.php';

session_start();

// Si ya está autenticado, redirigir al dashboard
if (isset($_SESSION['user_id']) && isset($_SESSION['session_token'])) {
    header('Location: dashboard.php');
    exit;
}

$errors = [];

// Procesar formulario de login
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $email = filter_input(INPUT_POST, 'email', FILTER_SANITIZE_EMAIL);
        $password = $_POST['password'] ?? '';

        if (empty($email) || empty($password)) {
            $errors[] = "Por favor completa todos los campos.";
        } else {
            $db = Database::getInstance();

            // Obtener usuario con información del plan
            $query = "SELECT u.id, u.email, u.password_hash, u.role, u.is_verified,
                             sp.id as plan_id, sp.plan_name, sp.max_concurrent_sessions
                      FROM users u
                      INNER JOIN subscription_plans sp ON u.subscription_plan_id = sp.id
                      WHERE u.email = :email
                      LIMIT 1";

            $user = $db->fetchOne($query, ['email' => $email]);

            if (!$user) {
                $errors[] = "Credenciales incorrectas.";

            } elseif ($user['is_verified'] == 0) {
                $errors[] = "Debes verificar tu cuenta antes de iniciar sesión. Revisa tu email.";

            } elseif (!password_verify($password, $user['password_hash'])) {
                $errors[] = "Credenciales incorrectas.";

            } else {
                // ===================================================
                // LÓGICA CRÍTICA: GESTIÓN DE SESIONES CONCURRENTES
                // ===================================================

                $userId = $user['id'];
                $maxSessions = $user['max_concurrent_sessions'];

                // Contar sesiones activas actuales
                $countQuery = "SELECT COUNT(*) as total FROM active_sessions WHERE user_id = :user_id";
                $sessionCount = $db->fetchOne($countQuery, ['user_id' => $userId]);
                $currentSessions = $sessionCount['total'];

                // Información del dispositivo actual
                $userAgent = $_SERVER['HTTP_USER_AGENT'] ?? 'Unknown';
                $ipAddress = $_SERVER['REMOTE_ADDR'] ?? 'Unknown';
                $deviceInfo = substr($userAgent, 0, 500); // Limitar tamaño

                // Generar nuevo token de sesión
                $sessionToken = bin2hex(random_bytes(64));

                // Iniciar transacción para garantizar consistencia
                $db->beginTransaction();

                try {
                    // SI SE EXCEDE EL LÍMITE: ROTACIÓN AUTOMÁTICA
                    if ($currentSessions >= $maxSessions) {

                        // 1. Obtener la sesión más antigua
                        $oldestQuery = "SELECT id, device_info, ip_address
                                       FROM active_sessions
                                       WHERE user_id = :user_id
                                       ORDER BY last_activity ASC
                                       LIMIT 1";

                        $oldestSession = $db->fetchOne($oldestQuery, ['user_id' => $userId]);

                        if ($oldestSession) {
                            // 2. Registrar evento de rotación en logs
                            $logQuery = "INSERT INTO session_logs
                                        (user_id, action, device_info, ip_address, details)
                                        VALUES
                                        (:user_id, 'session_rotated', :device, :ip, :details)";

                            $logParams = [
                                'user_id' => $userId,
                                'device' => $oldestSession['device_info'],
                                'ip' => $oldestSession['ip_address'],
                                'details' => "Sesión rotada automáticamente. Límite: {$maxSessions} sesiones. Nueva desde IP: {$ipAddress}"
                            ];

                            $db->query($logQuery, $logParams);

                            // 3. Eliminar la sesión más antigua
                            $deleteQuery = "DELETE FROM active_sessions WHERE id = :id";
                            $db->query($deleteQuery, ['id' => $oldestSession['id']]);

                            // 4. (Opcional) Enviar email de alerta al usuario
                            // Comentar si no se desea notificar por cada rotación
                            /*
                            $emailService = new EmailService();
                            $emailService->sendSessionRotationAlert($user['email'], $oldestSession['device_info']);
                            */
                        }
                    }

                    // 5. Crear nueva sesión activa
                    $insertSessionQuery = "INSERT INTO active_sessions
                                          (user_id, session_token, device_info, ip_address, user_agent)
                                          VALUES
                                          (:user_id, :token, :device, :ip, :user_agent)";

                    $sessionParams = [
                        'user_id' => $userId,
                        'token' => $sessionToken,
                        'device' => $deviceInfo,
                        'ip' => $ipAddress,
                        'user_agent' => $userAgent
                    ];

                    $db->query($insertSessionQuery, $sessionParams);

                    // 6. Registrar login exitoso
                    $loginLogQuery = "INSERT INTO session_logs
                                     (user_id, action, device_info, ip_address, details)
                                     VALUES
                                     (:user_id, 'login', :device, :ip, :details)";

                    $loginLogParams = [
                        'user_id' => $userId,
                        'device' => $deviceInfo,
                        'ip' => $ipAddress,
                        'details' => "Login exitoso. Plan: {$user['plan_name']}"
                    ];

                    $db->query($loginLogQuery, $loginLogParams);

                    // Confirmar transacción
                    $db->commit();

                    // ===================================================
                    // CREAR SESIÓN PHP
                    // ===================================================

                    session_regenerate_id(true); // Prevenir session fixation

                    $_SESSION['user_id'] = $userId;
                    $_SESSION['email'] = $user['email'];
                    $_SESSION['role'] = $user['role'];
                    $_SESSION['plan_name'] = $user['plan_name'];
                    $_SESSION['max_sessions'] = $maxSessions;
                    $_SESSION['session_token'] = $sessionToken;
                    $_SESSION['login_time'] = time();

                    // Redirigir al dashboard
                    header('Location: dashboard.php');
                    exit;

                } catch (Exception $e) {
                    // Revertir transacción en caso de error
                    $db->rollback();
                    throw $e;
                }
            }
        }

    } catch (Exception $e) {
        $errors[] = "Error al procesar el login. Por favor intenta nuevamente.";
        error_log("Error en login: " . $e->getMessage());
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Iniciar Sesión - <?php echo APP_NAME; ?></title>
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
        .login-container {
            background: white;
            border-radius: 15px;
            box-shadow: 0 10px 40px rgba(0,0,0,0.2);
            overflow: hidden;
            max-width: 450px;
            width: 100%;
            margin: 20px;
        }
        .login-header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 40px 30px;
            text-align: center;
        }
        .login-header i {
            font-size: 60px;
            margin-bottom: 15px;
        }
        .login-body {
            padding: 40px;
        }
        .form-control:focus {
            border-color: #667eea;
            box-shadow: 0 0 0 0.2rem rgba(102, 126, 234, 0.25);
        }
        .btn-primary {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            border: none;
        }
        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(102, 126, 234, 0.4);
        }
        .feature-list {
            background: #f8f9fa;
            border-radius: 10px;
            padding: 20px;
            margin-top: 20px;
        }
        .feature-list li {
            margin-bottom: 10px;
        }
    </style>
</head>
<body>
    <div class="login-container">
        <div class="login-header">
            <i class="bi bi-mortarboard-fill"></i>
            <h1 class="h3 mb-0"><?php echo APP_NAME; ?></h1>
            <p class="mb-0 mt-2">Inicia sesión para continuar</p>
        </div>

        <div class="login-body">
            <?php if (isset($_GET['session_expired'])): ?>
                <div class="alert alert-warning alert-dismissible fade show" role="alert">
                    <i class="bi bi-exclamation-triangle-fill"></i>
                    <strong>Sesión Expirada</strong><br>
                    Has excedido el límite de sesiones concurrentes. Tu sesión en este dispositivo fue cerrada.
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            <?php endif; ?>

            <?php if (!empty($errors)): ?>
                <div class="alert alert-danger alert-dismissible fade show">
                    <strong><i class="bi bi-exclamation-circle"></i> Error:</strong>
                    <ul class="mb-0 mt-2">
                        <?php foreach ($errors as $error): ?>
                            <li><?php echo htmlspecialchars($error); ?></li>
                        <?php endforeach; ?>
                    </ul>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            <?php endif; ?>

            <form method="POST" action="">
                <div class="mb-3">
                    <label for="email" class="form-label">
                        <i class="bi bi-envelope"></i> Email
                    </label>
                    <input type="email" class="form-control form-control-lg" id="email" name="email"
                           value="<?php echo htmlspecialchars($_POST['email'] ?? ''); ?>"
                           placeholder="tu@email.com" required autofocus>
                </div>

                <div class="mb-3">
                    <label for="password" class="form-label">
                        <i class="bi bi-lock"></i> Contraseña
                    </label>
                    <input type="password" class="form-control form-control-lg" id="password" name="password"
                           placeholder="••••••••" required>
                </div>

                <div class="d-grid mb-3">
                    <button type="submit" class="btn btn-primary btn-lg">
                        <i class="bi bi-box-arrow-in-right"></i> Iniciar Sesión
                    </button>
                </div>
            </form>

            <div class="text-center">
                <p class="mb-0">
                    ¿No tienes cuenta?
                    <a href="register.php" class="text-decoration-none fw-bold">Regístrate aquí</a>
                </p>
            </div>

            <div class="feature-list">
                <h6 class="fw-bold mb-3"><i class="bi bi-shield-check"></i> Características del Sistema</h6>
                <ul class="list-unstyled small">
                    <li><i class="bi bi-check-circle-fill text-success"></i> Sesiones concurrentes controladas</li>
                    <li><i class="bi bi-check-circle-fill text-success"></i> Rotación automática de dispositivos</li>
                    <li><i class="bi bi-check-circle-fill text-success"></i> Protección contra accesos no autorizados</li>
                    <li><i class="bi bi-check-circle-fill text-success"></i> Auditoría completa de sesiones</li>
                </ul>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>

    <?php if (isset($_GET['registered'])): ?>
    <script>
        // Mostrar mensaje de registro exitoso
        setTimeout(() => {
            alert('✓ Registro exitoso. Por favor verifica tu email antes de iniciar sesión.');
        }, 300);
    </script>
    <?php endif; ?>
</body>
</html>
