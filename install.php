<?php
/**
 * PLATAFORMA EDUCATIVA - INSTALADOR AUTOMÁTICO
 *
 * Script de instalación interactivo para facilitar la configuración inicial
 * ADVERTENCIA: Eliminar este archivo después de la instalación por seguridad
 */

error_reporting(E_ALL);
ini_set('display_errors', 1);

$step = isset($_GET['step']) ? (int)$_GET['step'] : 1;
$errors = [];
$success = [];

// Verificar si ya está instalado
if ($step === 1 && file_exists(__DIR__ . '/config/.installed')) {
    die('La aplicación ya está instalada. Si deseas reinstalar, elimina el archivo config/.installed');
}

// Función para escribir configuración
function writeConfig($dbHost, $dbName, $dbUser, $dbPass, $smtpUser, $smtpPass, $appUrl) {
    $configContent = "<?php
/**
 * PLATAFORMA EDUCATIVA - CONFIGURACIÓN GENERAL
 *
 * Define constantes y configuraciones globales del sistema
 */

defined('APP_ACCESS') or define('APP_ACCESS', true);

// ============================================
// CONFIGURACIÓN DE BASE DE DATOS
// ============================================
define('DB_HOST', '{$dbHost}');
define('DB_NAME', '{$dbName}');
define('DB_USER', '{$dbUser}');
define('DB_PASS', '{$dbPass}');
define('DB_CHARSET', 'utf8mb4');

// ============================================
// CONFIGURACIÓN DE EMAIL (SMTP)
// ============================================
define('SMTP_HOST', 'smtp.gmail.com');
define('SMTP_PORT', 587);
define('SMTP_SECURE', 'tls');
define('SMTP_USERNAME', '{$smtpUser}');
define('SMTP_PASSWORD', '{$smtpPass}');
define('SMTP_FROM_EMAIL', 'noreply@plataforma.com');
define('SMTP_FROM_NAME', 'Plataforma Educativa');

// ============================================
// CONFIGURACIÓN DE APLICACIÓN
// ============================================
define('APP_NAME', 'Plataforma Educativa');
define('APP_URL', '{$appUrl}');
define('APP_ENV', 'production');

// ============================================
// CONFIGURACIÓN DE SESIONES
// ============================================
define('SESSION_LIFETIME', 86400);
define('SESSION_COOKIE_NAME', 'plataforma_session');
define('SESSION_SECURE', false);
define('SESSION_HTTPONLY', true);
define('SESSION_SAMESITE', 'Strict');

// ============================================
// CONFIGURACIÓN DE SEGURIDAD
// ============================================
define('PASSWORD_MIN_LENGTH', 8);
define('TOKEN_LENGTH', 64);
define('MAX_LOGIN_ATTEMPTS', 5);
define('LOGIN_LOCKOUT_TIME', 900);

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
spl_autoload_register(function (\$class) {
    \$paths = [
        __DIR__ . '/../classes/',
        __DIR__ . '/../includes/'
    ];

    foreach (\$paths as \$path) {
        \$file = \$path . \$class . '.php';
        if (file_exists(\$file)) {
            require_once \$file;
            return;
        }
    }
});
";

    return file_put_contents(__DIR__ . '/config/config.php', $configContent);
}

// Procesar pasos
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if ($step === 2) {
        // Validar conexión a base de datos
        $dbHost = $_POST['db_host'] ?? 'localhost';
        $dbName = $_POST['db_name'] ?? '';
        $dbUser = $_POST['db_user'] ?? '';
        $dbPass = $_POST['db_pass'] ?? '';

        try {
            $pdo = new PDO("mysql:host={$dbHost};charset=utf8mb4", $dbUser, $dbPass);
            $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

            // Crear base de datos si no existe
            $pdo->exec("CREATE DATABASE IF NOT EXISTS `{$dbName}` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
            $pdo->exec("USE `{$dbName}`");

            // Ejecutar schema SQL
            $schema = file_get_contents(__DIR__ . '/database/schema.sql');
            $statements = explode(';', $schema);

            foreach ($statements as $statement) {
                $statement = trim($statement);
                if (!empty($statement)) {
                    $pdo->exec($statement);
                }
            }

            $success[] = "Base de datos configurada correctamente.";
            $_SESSION['install_data'] = [
                'db_host' => $dbHost,
                'db_name' => $dbName,
                'db_user' => $dbUser,
                'db_pass' => $dbPass
            ];
            $step = 3;

        } catch (PDOException $e) {
            $errors[] = "Error de base de datos: " . $e->getMessage();
        }

    } elseif ($step === 3) {
        // Configurar email y URL
        session_start();
        $installData = $_SESSION['install_data'] ?? [];

        $smtpUser = $_POST['smtp_user'] ?? '';
        $smtpPass = $_POST['smtp_pass'] ?? '';
        $appUrl = $_POST['app_url'] ?? '';

        if (writeConfig(
            $installData['db_host'],
            $installData['db_name'],
            $installData['db_user'],
            $installData['db_pass'],
            $smtpUser,
            $smtpPass,
            $appUrl
        )) {
            // Marcar como instalado
            file_put_contents(__DIR__ . '/config/.installed', date('Y-m-d H:i:s'));

            $success[] = "Instalación completada exitosamente.";
            $step = 4;
        } else {
            $errors[] = "No se pudo escribir el archivo de configuración.";
        }
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Instalador - Plataforma Educativa</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css" rel="stylesheet">
    <style>
        body {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            padding: 40px 0;
        }
        .install-container {
            max-width: 700px;
            margin: 0 auto;
            background: white;
            border-radius: 15px;
            box-shadow: 0 10px 40px rgba(0,0,0,0.2);
            overflow: hidden;
        }
        .install-header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 30px;
            text-align: center;
        }
        .install-body {
            padding: 40px;
        }
        .step-indicator {
            display: flex;
            justify-content: space-between;
            margin-bottom: 30px;
        }
        .step {
            flex: 1;
            text-align: center;
            padding: 10px;
            background: #f0f0f0;
            margin: 0 5px;
            border-radius: 5px;
        }
        .step.active {
            background: #667eea;
            color: white;
        }
        .step.completed {
            background: #4caf50;
            color: white;
        }
    </style>
</head>
<body>
    <div class="install-container">
        <div class="install-header">
            <h1><i class="bi bi-gear-fill"></i> Instalador de Plataforma Educativa</h1>
            <p class="mb-0">Configuración Inicial del Sistema</p>
        </div>

        <div class="install-body">
            <!-- Indicador de pasos -->
            <div class="step-indicator">
                <div class="step <?php echo $step >= 1 ? 'completed' : ''; ?>">1. Bienvenida</div>
                <div class="step <?php echo $step === 2 ? 'active' : ($step > 2 ? 'completed' : ''); ?>">2. Base de Datos</div>
                <div class="step <?php echo $step === 3 ? 'active' : ($step > 3 ? 'completed' : ''); ?>">3. Configuración</div>
                <div class="step <?php echo $step === 4 ? 'active' : ''; ?>">4. Finalizar</div>
            </div>

            <?php if (!empty($errors)): ?>
                <div class="alert alert-danger">
                    <strong><i class="bi bi-exclamation-triangle"></i> Errores:</strong>
                    <ul class="mb-0 mt-2">
                        <?php foreach ($errors as $error): ?>
                            <li><?php echo htmlspecialchars($error); ?></li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            <?php endif; ?>

            <?php if (!empty($success)): ?>
                <div class="alert alert-success">
                    <strong><i class="bi bi-check-circle"></i> Éxito:</strong>
                    <ul class="mb-0 mt-2">
                        <?php foreach ($success as $msg): ?>
                            <li><?php echo htmlspecialchars($msg); ?></li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            <?php endif; ?>

            <!-- Paso 1: Bienvenida -->
            <?php if ($step === 1): ?>
                <h3>Bienvenido al Instalador</h3>
                <p>Este asistente te guiará en la configuración inicial de la Plataforma Educativa.</p>

                <div class="alert alert-info">
                    <strong><i class="bi bi-info-circle"></i> Requisitos previos:</strong>
                    <ul class="mb-0">
                        <li>PHP 8.0 o superior</li>
                        <li>MySQL 5.7+ o MariaDB 10.3+</li>
                        <li>Composer instalado (ejecutar: <code>composer install</code>)</li>
                        <li>Cuenta de Gmail con App Password configurado</li>
                    </ul>
                </div>

                <div class="alert alert-warning">
                    <strong><i class="bi bi-exclamation-triangle"></i> Importante:</strong>
                    Elimina el archivo <code>install.php</code> después de completar la instalación por seguridad.
                </div>

                <a href="?step=2" class="btn btn-primary btn-lg w-100">
                    Comenzar Instalación <i class="bi bi-arrow-right"></i>
                </a>
            <?php endif; ?>

            <!-- Paso 2: Base de Datos -->
            <?php if ($step === 2): ?>
                <h3>Configuración de Base de Datos</h3>
                <p>Ingresa las credenciales de tu servidor MySQL:</p>

                <form method="POST" action="?step=2">
                    <div class="mb-3">
                        <label class="form-label">Host de Base de Datos</label>
                        <input type="text" name="db_host" class="form-control" value="localhost" required>
                    </div>

                    <div class="mb-3">
                        <label class="form-label">Nombre de Base de Datos</label>
                        <input type="text" name="db_name" class="form-control" value="plataforma_educativa" required>
                        <small class="text-muted">Se creará automáticamente si no existe.</small>
                    </div>

                    <div class="mb-3">
                        <label class="form-label">Usuario de MySQL</label>
                        <input type="text" name="db_user" class="form-control" value="root" required>
                    </div>

                    <div class="mb-3">
                        <label class="form-label">Contraseña de MySQL</label>
                        <input type="password" name="db_pass" class="form-control">
                        <small class="text-muted">Dejar en blanco si no hay contraseña (XAMPP por defecto).</small>
                    </div>

                    <button type="submit" class="btn btn-primary w-100">
                        Configurar Base de Datos <i class="bi bi-arrow-right"></i>
                    </button>
                </form>
            <?php endif; ?>

            <!-- Paso 3: Configuración General -->
            <?php if ($step === 3): ?>
                <h3>Configuración General</h3>
                <p>Configura el email y la URL de la aplicación:</p>

                <form method="POST" action="?step=3">
                    <div class="mb-3">
                        <label class="form-label">Email SMTP (Gmail)</label>
                        <input type="email" name="smtp_user" class="form-control" placeholder="tu-email@gmail.com" required>
                    </div>

                    <div class="mb-3">
                        <label class="form-label">App Password de Gmail</label>
                        <input type="text" name="smtp_pass" class="form-control" placeholder="xxxx xxxx xxxx xxxx">
                        <small class="text-muted">
                            Genera en: <a href="https://myaccount.google.com/apppasswords" target="_blank">https://myaccount.google.com/apppasswords</a>
                        </small>
                    </div>

                    <div class="mb-3">
                        <label class="form-label">URL de la Aplicación</label>
                        <input type="url" name="app_url" class="form-control"
                               value="<?php echo 'http://' . $_SERVER['HTTP_HOST'] . dirname($_SERVER['PHP_SELF']); ?>" required>
                        <small class="text-muted">URL completa donde está instalada la aplicación.</small>
                    </div>

                    <button type="submit" class="btn btn-success w-100">
                        Finalizar Instalación <i class="bi bi-check-circle"></i>
                    </button>
                </form>
            <?php endif; ?>

            <!-- Paso 4: Completado -->
            <?php if ($step === 4): ?>
                <div class="text-center">
                    <i class="bi bi-check-circle-fill text-success" style="font-size: 80px;"></i>
                    <h3 class="mt-3">¡Instalación Completada!</h3>
                    <p>La plataforma educativa ha sido configurada correctamente.</p>

                    <div class="alert alert-success text-start mt-4">
                        <strong>Usuarios de prueba creados:</strong>
                        <ul class="mb-0">
                            <li>
                                <strong>Admin:</strong> admin@plataforma.com / Admin@123
                            </li>
                            <li>
                                <strong>Estudiante:</strong> student@test.com / Student@123
                            </li>
                        </ul>
                    </div>

                    <div class="alert alert-danger text-start">
                        <strong><i class="bi bi-exclamation-triangle"></i> Acción Requerida:</strong>
                        Por seguridad, elimina el archivo <code>install.php</code> ahora mismo.
                    </div>

                    <div class="d-grid gap-2">
                        <a href="login.php" class="btn btn-primary btn-lg">
                            <i class="bi bi-box-arrow-in-right"></i> Ir al Login
                        </a>
                        <a href="dashboard.php" class="btn btn-outline-secondary">
                            <i class="bi bi-speedometer2"></i> Ir al Dashboard
                        </a>
                    </div>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
