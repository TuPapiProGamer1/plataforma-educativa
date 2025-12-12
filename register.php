<?php
/**
 * PLATAFORMA EDUCATIVA - REGISTRO DE USUARIOS
 *
 * Procesa el registro de nuevos usuarios y envía email de verificación
 */

define('APP_ACCESS', true);
require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/classes/EmailService.php';

session_start();

// Si ya está autenticado, redirigir al dashboard
if (isset($_SESSION['user_id'])) {
    header('Location: dashboard.php');
    exit;
}

$errors = [];
$success = false;

// Procesar formulario de registro
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $email = filter_input(INPUT_POST, 'email', FILTER_SANITIZE_EMAIL);
        $password = $_POST['password'] ?? '';
        $confirmPassword = $_POST['confirm_password'] ?? '';
        $plan = filter_input(INPUT_POST, 'plan', FILTER_VALIDATE_INT);

        // Validaciones
        if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $errors[] = "Por favor ingresa un email válido.";
        }

        if (strlen($password) < PASSWORD_MIN_LENGTH) {
            $errors[] = "La contraseña debe tener al menos " . PASSWORD_MIN_LENGTH . " caracteres.";
        }

        if ($password !== $confirmPassword) {
            $errors[] = "Las contraseñas no coinciden.";
        }

        // Validar complejidad de contraseña
        if (!preg_match('/[A-Z]/', $password) ||
            !preg_match('/[a-z]/', $password) ||
            !preg_match('/[0-9]/', $password) ||
            !preg_match('/[@$!%*?&#]/', $password)) {
            $errors[] = "La contraseña debe contener al menos: 1 mayúscula, 1 minúscula, 1 número y 1 carácter especial (@$!%*?&#).";
        }

        if (!in_array($plan, [1, 2, 3])) {
            $errors[] = "Por favor selecciona un plan válido.";
        }

        if (empty($errors)) {
            $db = Database::getInstance();

            // Verificar si el email ya existe
            $checkQuery = "SELECT id FROM users WHERE email = :email";
            $existing = $db->fetchOne($checkQuery, ['email' => $email]);

            if ($existing) {
                $errors[] = "Este email ya está registrado.";
            } else {
                // Generar token de verificación
                $verificationToken = bin2hex(random_bytes(TOKEN_LENGTH / 2));
                $tokenExpires = date('Y-m-d H:i:s', strtotime('+24 hours'));

                // Hash de contraseña
                $passwordHash = password_hash($password, PASSWORD_ARGON2ID);

                // Insertar usuario
                $insertQuery = "INSERT INTO users
                    (email, password_hash, role, subscription_plan_id, is_verified, verification_token, token_expires_at)
                    VALUES
                    (:email, :password_hash, 'student', :plan, 0, :token, :expires)";

                $params = [
                    'email' => $email,
                    'password_hash' => $passwordHash,
                    'plan' => $plan,
                    'token' => $verificationToken,
                    'expires' => $tokenExpires
                ];

                $db->query($insertQuery, $params);

                // Enviar email de verificación
                $emailService = new EmailService();
                $emailSent = $emailService->sendVerificationEmail($email, $verificationToken);

                if ($emailSent) {
                    $success = true;
                    $_SESSION['registration_email'] = $email;
                } else {
                    $errors[] = "Usuario registrado, pero hubo un error al enviar el email de verificación. Por favor contacta a soporte.";
                }
            }
        }

    } catch (Exception $e) {
        $errors[] = "Error al procesar el registro. Por favor intenta nuevamente.";
        error_log("Error en registro: " . $e->getMessage());
    }
}

// Obtener planes disponibles
try {
    $db = Database::getInstance();
    $plansQuery = "SELECT id, plan_name, max_concurrent_sessions, price, description FROM subscription_plans ORDER BY id";
    $plans = $db->fetchAll($plansQuery);
} catch (Exception $e) {
    $plans = [];
    error_log("Error al cargar planes: " . $e->getMessage());
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Registro - <?php echo APP_NAME; ?></title>
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
        .register-container {
            background: white;
            border-radius: 15px;
            box-shadow: 0 10px 40px rgba(0,0,0,0.2);
            overflow: hidden;
            max-width: 900px;
            width: 100%;
            margin: 20px;
        }
        .register-header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 30px;
            text-align: center;
        }
        .register-body {
            padding: 40px;
        }
        .plan-card {
            border: 2px solid #e0e0e0;
            border-radius: 10px;
            padding: 20px;
            cursor: pointer;
            transition: all 0.3s;
            margin-bottom: 15px;
        }
        .plan-card:hover {
            border-color: #667eea;
            box-shadow: 0 5px 15px rgba(102, 126, 234, 0.3);
        }
        .plan-card.selected {
            border-color: #667eea;
            background: #f8f9ff;
        }
        .plan-card input[type="radio"] {
            width: 20px;
            height: 20px;
        }
        .password-strength {
            height: 5px;
            border-radius: 3px;
            margin-top: 5px;
            background: #e0e0e0;
        }
        .password-strength.weak { background: #f44336; }
        .password-strength.medium { background: #ff9800; }
        .password-strength.strong { background: #4caf50; }
    </style>
</head>
<body>
    <div class="register-container">
        <div class="register-header">
            <h1><i class="bi bi-mortarboard-fill"></i> <?php echo APP_NAME; ?></h1>
            <p class="mb-0">Crea tu cuenta y comienza a aprender</p>
        </div>

        <div class="register-body">
            <?php if ($success): ?>
                <div class="alert alert-success">
                    <h4 class="alert-heading"><i class="bi bi-check-circle-fill"></i> ¡Registro Exitoso!</h4>
                    <p>Se ha enviado un email de verificación a <strong><?php echo htmlspecialchars($_SESSION['registration_email']); ?></strong></p>
                    <hr>
                    <p class="mb-0">Por favor revisa tu bandeja de entrada y haz clic en el enlace de verificación para activar tu cuenta.</p>
                    <div class="mt-3">
                        <a href="login.php" class="btn btn-primary">Ir al Login</a>
                    </div>
                </div>
            <?php else: ?>
                <?php if (!empty($errors)): ?>
                    <div class="alert alert-danger">
                        <strong><i class="bi bi-exclamation-triangle-fill"></i> Errores:</strong>
                        <ul class="mb-0 mt-2">
                            <?php foreach ($errors as $error): ?>
                                <li><?php echo htmlspecialchars($error); ?></li>
                            <?php endforeach; ?>
                        </ul>
                    </div>
                <?php endif; ?>

                <form method="POST" action="" id="registerForm">
                    <!-- Email -->
                    <div class="mb-3">
                        <label for="email" class="form-label">
                            <i class="bi bi-envelope"></i> Email
                        </label>
                        <input type="email" class="form-control" id="email" name="email"
                               value="<?php echo htmlspecialchars($_POST['email'] ?? ''); ?>"
                               required>
                    </div>

                    <!-- Contraseña -->
                    <div class="mb-3">
                        <label for="password" class="form-label">
                            <i class="bi bi-lock"></i> Contraseña
                        </label>
                        <input type="password" class="form-control" id="password" name="password" required>
                        <div class="password-strength" id="passwordStrength"></div>
                        <small class="text-muted">Mínimo 8 caracteres, incluye mayúsculas, minúsculas, números y símbolos</small>
                    </div>

                    <!-- Confirmar Contraseña -->
                    <div class="mb-3">
                        <label for="confirm_password" class="form-label">
                            <i class="bi bi-lock-fill"></i> Confirmar Contraseña
                        </label>
                        <input type="password" class="form-control" id="confirm_password" name="confirm_password" required>
                    </div>

                    <!-- Planes de Suscripción -->
                    <div class="mb-4">
                        <label class="form-label fw-bold">
                            <i class="bi bi-star"></i> Selecciona tu Plan
                        </label>
                        <?php foreach ($plans as $planItem): ?>
                            <div class="plan-card" onclick="selectPlan(<?php echo $planItem['id']; ?>)">
                                <div class="row align-items-center">
                                    <div class="col-auto">
                                        <input type="radio" name="plan" value="<?php echo $planItem['id']; ?>"
                                               id="plan<?php echo $planItem['id']; ?>"
                                               <?php echo (isset($_POST['plan']) && $_POST['plan'] == $planItem['id']) ? 'checked' : ''; ?>
                                               required>
                                    </div>
                                    <div class="col">
                                        <h5 class="mb-1"><?php echo htmlspecialchars($planItem['plan_name']); ?></h5>
                                        <p class="mb-1 text-muted"><?php echo htmlspecialchars($planItem['description']); ?></p>
                                        <small class="text-primary">
                                            <i class="bi bi-display"></i>
                                            <?php echo $planItem['max_concurrent_sessions']; ?>
                                            sesión(es) simultánea(s)
                                        </small>
                                    </div>
                                    <div class="col-auto">
                                        <h4 class="mb-0 text-success">
                                            <?php echo $planItem['price'] == 0 ? 'Gratis' : '$' . number_format($planItem['price'], 2); ?>
                                        </h4>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>

                    <button type="submit" class="btn btn-primary w-100 py-2">
                        <i class="bi bi-person-plus"></i> Crear Cuenta
                    </button>
                </form>

                <div class="text-center mt-4">
                    <p class="mb-0">¿Ya tienes cuenta? <a href="login.php">Inicia Sesión</a></p>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Seleccionar plan
        function selectPlan(planId) {
            document.querySelectorAll('.plan-card').forEach(card => {
                card.classList.remove('selected');
            });
            event.currentTarget.classList.add('selected');
            document.getElementById('plan' + planId).checked = true;
        }

        // Validación de fortaleza de contraseña
        document.getElementById('password').addEventListener('input', function() {
            const password = this.value;
            const strengthBar = document.getElementById('passwordStrength');

            let strength = 0;
            if (password.length >= 8) strength++;
            if (/[a-z]/.test(password)) strength++;
            if (/[A-Z]/.test(password)) strength++;
            if (/[0-9]/.test(password)) strength++;
            if (/[@$!%*?&#]/.test(password)) strength++;

            strengthBar.className = 'password-strength';
            if (strength <= 2) strengthBar.classList.add('weak');
            else if (strength <= 4) strengthBar.classList.add('medium');
            else strengthBar.classList.add('strong');
        });

        // Inicializar plan seleccionado
        document.addEventListener('DOMContentLoaded', function() {
            const selectedRadio = document.querySelector('input[name="plan"]:checked');
            if (selectedRadio) {
                selectedRadio.closest('.plan-card').classList.add('selected');
            }
        });
    </script>
</body>
</html>
