<?php
/**
 * VERIFICADOR DEL SISTEMA
 *
 * Comprueba que todos los componentes estén correctamente instalados
 * URL: http://localhost/plataformaeducativa/check_system.php
 */

error_reporting(E_ALL);
ini_set('display_errors', 1);

$checks = [];

// 1. Verificar versión de PHP
$checks['PHP Version'] = [
    'status' => version_compare(PHP_VERSION, '8.0.0', '>='),
    'message' => PHP_VERSION . (version_compare(PHP_VERSION, '8.0.0', '>=') ? ' ✓' : ' ✗ (Se requiere 8.0+)'),
    'required' => true
];

// 2. Verificar extensiones de PHP
$requiredExtensions = ['pdo', 'pdo_mysql', 'mbstring', 'openssl'];
foreach ($requiredExtensions as $ext) {
    $checks["Extension: {$ext}"] = [
        'status' => extension_loaded($ext),
        'message' => extension_loaded($ext) ? 'Instalada ✓' : 'No instalada ✗',
        'required' => true
    ];
}

// 3. Verificar Composer autoload
$checks['Composer Autoload'] = [
    'status' => file_exists(__DIR__ . '/vendor/autoload.php'),
    'message' => file_exists(__DIR__ . '/vendor/autoload.php') ? 'Instalado ✓' : 'No instalado ✗ (ejecuta: composer install)',
    'required' => true
];

// 4. Verificar PHPMailer
$checks['PHPMailer'] = [
    'status' => file_exists(__DIR__ . '/vendor/phpmailer/phpmailer/src/PHPMailer.php'),
    'message' => file_exists(__DIR__ . '/vendor/phpmailer/phpmailer/src/PHPMailer.php') ? 'Instalado ✓' : 'No instalado ✗',
    'required' => true
];

// 5. Verificar archivos de configuración
$checks['config.php'] = [
    'status' => file_exists(__DIR__ . '/config/config.php'),
    'message' => file_exists(__DIR__ . '/config/config.php') ? 'Existe ✓' : 'No existe ✗',
    'required' => true
];

$checks['db.php'] = [
    'status' => file_exists(__DIR__ . '/config/db.php'),
    'message' => file_exists(__DIR__ . '/config/db.php') ? 'Existe ✓' : 'No existe ✗',
    'required' => true
];

// 6. Verificar schema SQL
$checks['schema.sql'] = [
    'status' => file_exists(__DIR__ . '/database/schema.sql'),
    'message' => file_exists(__DIR__ . '/database/schema.sql') ? 'Existe ✓' : 'No existe ✗',
    'required' => true
];

// 7. Verificar carpeta de logs
$checks['Carpeta logs/'] = [
    'status' => is_dir(__DIR__ . '/logs') && is_writable(__DIR__ . '/logs'),
    'message' => is_dir(__DIR__ . '/logs') && is_writable(__DIR__ . '/logs') ? 'Existe y escribible ✓' : 'No existe o no escribible ✗',
    'required' => false
];

// 8. Verificar conexión a base de datos
$dbStatus = false;
$dbMessage = '';
try {
    require_once __DIR__ . '/config/config.php';
    $pdo = new PDO("mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=" . DB_CHARSET, DB_USER, DB_PASS);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    $dbStatus = true;
    $dbMessage = "Conectado a '" . DB_NAME . "' ✓";

    // Verificar tablas
    $stmt = $pdo->query("SHOW TABLES");
    $tables = $stmt->fetchAll(PDO::FETCH_COLUMN);

    if (count($tables) >= 4) {
        $dbMessage .= " | Tablas: " . count($tables) . " ✓";
    } else {
        $dbMessage .= " | ⚠️ Solo " . count($tables) . " tablas (se esperan 4+)";
    }

} catch (Exception $e) {
    $dbMessage = "Error: " . $e->getMessage() . " ✗";
}

$checks['Conexión a BD'] = [
    'status' => $dbStatus,
    'message' => $dbMessage,
    'required' => true
];

// Calcular status general
$allRequired = true;
foreach ($checks as $check) {
    if ($check['required'] && !$check['status']) {
        $allRequired = false;
        break;
    }
}

?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Verificación del Sistema</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css" rel="stylesheet">
    <style>
        body {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            padding: 40px 20px;
        }
        .check-container {
            max-width: 800px;
            margin: 0 auto;
            background: white;
            border-radius: 15px;
            padding: 40px;
            box-shadow: 0 10px 40px rgba(0,0,0,0.2);
        }
        .check-item {
            padding: 15px;
            border-bottom: 1px solid #e0e0e0;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        .check-item:last-child {
            border-bottom: none;
        }
        .status-ok {
            color: #4caf50;
            font-weight: bold;
        }
        .status-error {
            color: #f44336;
            font-weight: bold;
        }
    </style>
</head>
<body>
    <div class="check-container">
        <h2 class="text-center mb-4">
            <i class="bi bi-clipboard-check"></i>
            Verificación del Sistema
        </h2>

        <?php if ($allRequired): ?>
            <div class="alert alert-success">
                <h5 class="alert-heading">
                    <i class="bi bi-check-circle-fill"></i>
                    ¡Sistema Listo!
                </h5>
                <p class="mb-0">Todos los componentes requeridos están instalados correctamente.</p>
            </div>
        <?php else: ?>
            <div class="alert alert-danger">
                <h5 class="alert-heading">
                    <i class="bi bi-exclamation-triangle-fill"></i>
                    Hay Problemas
                </h5>
                <p class="mb-0">Algunos componentes requeridos no están disponibles. Revisa la lista abajo.</p>
            </div>
        <?php endif; ?>

        <div class="mt-4">
            <?php foreach ($checks as $name => $check): ?>
                <div class="check-item">
                    <div>
                        <strong><?php echo $name; ?></strong>
                        <?php if ($check['required']): ?>
                            <span class="badge bg-danger ms-2">Requerido</span>
                        <?php endif; ?>
                    </div>
                    <div class="<?php echo $check['status'] ? 'status-ok' : 'status-error'; ?>">
                        <?php echo $check['message']; ?>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>

        <hr class="my-4">

        <div class="row g-3">
            <?php if (!$dbStatus): ?>
                <div class="col-12">
                    <div class="alert alert-warning">
                        <strong>⚠️ Base de datos no disponible</strong><br>
                        Ejecuta el instalador de base de datos:
                        <a href="setup_database.php" class="btn btn-sm btn-warning mt-2">
                            Ir al Instalador
                        </a>
                    </div>
                </div>
            <?php endif; ?>

            <?php if (!file_exists(__DIR__ . '/vendor/autoload.php')): ?>
                <div class="col-12">
                    <div class="alert alert-danger">
                        <strong>❌ Dependencias no instaladas</strong><br>
                        Ejecuta desde terminal:
                        <pre class="mt-2 mb-0 bg-dark text-white p-2 rounded">composer install</pre>
                    </div>
                </div>
            <?php endif; ?>

            <?php if ($allRequired): ?>
                <div class="col-md-6">
                    <a href="login.php" class="btn btn-primary w-100 py-3">
                        <i class="bi bi-box-arrow-in-right"></i>
                        Ir al Login
                    </a>
                </div>
                <div class="col-md-6">
                    <a href="register.php" class="btn btn-success w-100 py-3">
                        <i class="bi bi-person-plus"></i>
                        Registrarse
                    </a>
                </div>
            <?php endif; ?>
        </div>

        <div class="text-center mt-4">
            <small class="text-muted">
                Plataforma Educativa - Verificación del Sistema v1.0
            </small>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
