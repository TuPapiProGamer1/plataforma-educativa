<?php
/**
 * SCRIPT DE PRUEBA DE EMAIL SMTP
 *
 * Verifica la configuración de PHPMailer en XAMPP
 * URL: http://localhost/plataformaeducativa/test_email_smtp.php
 *
 * ELIMINAR ESTE ARCHIVO EN PRODUCCIÓN
 */

error_reporting(E_ALL);
ini_set('display_errors', 1);

define('APP_ACCESS', true);
require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/classes/EmailService.php';

$testResults = [];
$emailToTest = null;

// Procesar formulario
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['test_email'])) {
    $emailToTest = filter_input(INPUT_POST, 'email', FILTER_SANITIZE_EMAIL);

    try {
        $emailService = new EmailService();

        // Test 1: Verificar configuración
        $testResults['config'] = $emailService->testConnection();

        // Test 2: Enviar email de prueba
        if ($emailToTest && filter_var($emailToTest, FILTER_VALIDATE_EMAIL)) {
            $testResults['send'] = $emailService->testConnection($emailToTest);
        }

    } catch (Exception $e) {
        $testResults['error'] = $e->getMessage();
    }
}

// Leer logs recientes
function getRecentLogs($file, $lines = 20) {
    if (!file_exists($file)) {
        return "No hay logs disponibles.";
    }

    $content = file_get_contents($file);
    $logLines = explode("\n", $content);
    $logLines = array_filter($logLines);
    $recentLines = array_slice($logLines, -$lines);

    return implode("\n", $recentLines);
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Test Email SMTP - XAMPP</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css" rel="stylesheet">
    <style>
        body {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            padding: 20px;
        }
        .test-container {
            max-width: 900px;
            margin: 0 auto;
            background: white;
            border-radius: 15px;
            box-shadow: 0 10px 40px rgba(0,0,0,0.2);
            overflow: hidden;
        }
        .test-header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 30px;
            text-align: center;
        }
        .test-body {
            padding: 30px;
        }
        .config-item {
            background: #f8f9fa;
            padding: 15px;
            border-radius: 8px;
            margin-bottom: 10px;
            border-left: 4px solid #667eea;
        }
        .log-box {
            background: #1e1e1e;
            color: #00ff00;
            padding: 20px;
            border-radius: 8px;
            font-family: 'Courier New', monospace;
            font-size: 12px;
            max-height: 400px;
            overflow-y: auto;
            white-space: pre-wrap;
            word-wrap: break-word;
        }
        .status-ok { color: #28a745; font-weight: bold; }
        .status-error { color: #dc3545; font-weight: bold; }
        .status-warning { color: #ffc107; font-weight: bold; }
    </style>
</head>
<body>
    <div class="test-container">
        <div class="test-header">
            <h1><i class="bi bi-envelope-check"></i> Test de Email SMTP</h1>
            <p class="mb-0">Verificación de PHPMailer en XAMPP</p>
        </div>

        <div class="test-body">
            <!-- ALERTA DE SEGURIDAD -->
            <div class="alert alert-danger">
                <strong><i class="bi bi-exclamation-triangle-fill"></i> IMPORTANTE:</strong><br>
                Este archivo es solo para pruebas. <strong>ELIMÍNALO antes de subir a producción.</strong>
            </div>

            <!-- VERIFICACIÓN DE CONFIGURACIÓN -->
            <div class="card mb-4">
                <div class="card-header bg-primary text-white">
                    <h5 class="mb-0"><i class="bi bi-gear"></i> 1. Verificación de Configuración</h5>
                </div>
                <div class="card-body">
                    <div class="config-item">
                        <strong>SMTP Host:</strong> <?php echo SMTP_HOST; ?>
                        <?php if (SMTP_HOST === 'smtp.gmail.com'): ?>
                            <span class="status-ok">✓</span>
                        <?php else: ?>
                            <span class="status-warning">⚠️ No es Gmail</span>
                        <?php endif; ?>
                    </div>

                    <div class="config-item">
                        <strong>SMTP Port:</strong> <?php echo SMTP_PORT; ?>
                        <?php if (SMTP_PORT == 587 || SMTP_PORT == 465): ?>
                            <span class="status-ok">✓</span>
                        <?php else: ?>
                            <span class="status-error">✗ Puerto incorrecto</span>
                        <?php endif; ?>
                    </div>

                    <div class="config-item">
                        <strong>SMTP Secure:</strong> <?php echo SMTP_SECURE; ?>
                        <?php if (SMTP_SECURE === 'tls' || SMTP_SECURE === 'ssl'): ?>
                            <span class="status-ok">✓</span>
                        <?php else: ?>
                            <span class="status-error">✗ Debe ser 'tls' o 'ssl'</span>
                        <?php endif; ?>
                    </div>

                    <div class="config-item">
                        <strong>SMTP Username:</strong>
                        <?php if (!empty(SMTP_USERNAME)): ?>
                            <?php echo htmlspecialchars(SMTP_USERNAME); ?>
                            <span class="status-ok">✓ Configurado</span>
                        <?php else: ?>
                            <span class="status-error">✗ NO CONFIGURADO</span>
                        <?php endif; ?>
                    </div>

                    <div class="config-item">
                        <strong>SMTP Password:</strong>
                        <?php if (!empty(SMTP_PASSWORD) && strlen(SMTP_PASSWORD) >= 16): ?>
                            <?php echo str_repeat('*', 16); ?>... (<?php echo strlen(SMTP_PASSWORD); ?> caracteres)
                            <span class="status-ok">✓ Configurado</span>
                        <?php elseif (!empty(SMTP_PASSWORD)): ?>
                            <span class="status-warning">⚠️ Configurado pero parece corto</span>
                        <?php else: ?>
                            <span class="status-error">✗ NO CONFIGURADO</span>
                        <?php endif; ?>
                    </div>

                    <div class="config-item">
                        <strong>APP_ENV:</strong> <?php echo APP_ENV; ?>
                        <?php if (APP_ENV === 'development'): ?>
                            <span class="status-ok">✓ Modo desarrollo (debug activo)</span>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <!-- EXTENSIONES PHP -->
            <div class="card mb-4">
                <div class="card-header bg-info text-white">
                    <h5 class="mb-0"><i class="bi bi-box"></i> 2. Extensiones PHP Requeridas</h5>
                </div>
                <div class="card-body">
                    <div class="config-item">
                        <strong>OpenSSL:</strong>
                        <?php if (extension_loaded('openssl')): ?>
                            <span class="status-ok">✓ Habilitada</span>
                        <?php else: ?>
                            <span class="status-error">✗ NO HABILITADA - Editar php.ini</span>
                        <?php endif; ?>
                    </div>

                    <div class="config-item">
                        <strong>MBString:</strong>
                        <?php if (extension_loaded('mbstring')): ?>
                            <span class="status-ok">✓ Habilitada</span>
                        <?php else: ?>
                            <span class="status-error">✗ NO HABILITADA</span>
                        <?php endif; ?>
                    </div>

                    <div class="config-item">
                        <strong>CURL:</strong>
                        <?php if (extension_loaded('curl')): ?>
                            <span class="status-ok">✓ Habilitada</span>
                        <?php else: ?>
                            <span class="status-warning">⚠️ No habilitada (opcional)</span>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <!-- FORMULARIO DE PRUEBA -->
            <div class="card mb-4">
                <div class="card-header bg-success text-white">
                    <h5 class="mb-0"><i class="bi bi-send"></i> 3. Enviar Email de Prueba</h5>
                </div>
                <div class="card-body">
                    <form method="POST">
                        <div class="mb-3">
                            <label class="form-label">Email de destino:</label>
                            <input type="email" name="email" class="form-control"
                                   value="<?php echo htmlspecialchars($emailToTest ?? ''); ?>"
                                   placeholder="tu-email@gmail.com" required>
                            <small class="text-muted">Ingresa tu email real para recibir el mensaje de prueba</small>
                        </div>

                        <button type="submit" name="test_email" class="btn btn-success w-100">
                            <i class="bi bi-envelope-check"></i> Enviar Email de Prueba
                        </button>
                    </form>

                    <?php if (isset($testResults['send'])): ?>
                        <div class="mt-3 alert <?php echo $testResults['send']['success'] ? 'alert-success' : 'alert-danger'; ?>">
                            <strong><?php echo $testResults['send']['success'] ? '✓ ÉXITO:' : '✗ ERROR:'; ?></strong><br>
                            <?php echo htmlspecialchars($testResults['send']['message']); ?>

                            <?php if (!empty($testResults['send']['details'])): ?>
                                <hr>
                                <small>
                                    <strong>Detalles:</strong><br>
                                    <pre class="mb-0"><?php echo htmlspecialchars(print_r($testResults['send']['details'], true)); ?></pre>
                                </small>
                            <?php endif; ?>
                        </div>
                    <?php endif; ?>

                    <?php if (isset($testResults['error'])): ?>
                        <div class="mt-3 alert alert-danger">
                            <strong>✗ ERROR CRÍTICO:</strong><br>
                            <?php echo htmlspecialchars($testResults['error']); ?>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- LOGS DE EMAIL -->
            <div class="card mb-4">
                <div class="card-header bg-dark text-white">
                    <h5 class="mb-0"><i class="bi bi-file-earmark-text"></i> 4. Logs de Email (Últimas 20 líneas)</h5>
                </div>
                <div class="card-body p-0">
                    <div class="log-box">
                        <?php
                        $logFile = __DIR__ . '/logs/email.log';
                        echo htmlspecialchars(getRecentLogs($logFile, 20));
                        ?>
                    </div>
                </div>
            </div>

            <!-- GUÍA DE SOLUCIÓN DE PROBLEMAS -->
            <div class="card mb-4">
                <div class="card-header bg-warning">
                    <h5 class="mb-0"><i class="bi bi-tools"></i> Solución de Problemas</h5>
                </div>
                <div class="card-body">
                    <h6>❌ Error: "SMTP connect() failed"</h6>
                    <ul>
                        <li>Verificar que <code>extension=openssl</code> esté habilitado en <code>php.ini</code></li>
                        <li>Verificar credenciales en <code>config/config.php</code></li>
                        <li>Usar App Password de Gmail (no contraseña normal)</li>
                        <li>Reiniciar Apache después de cambios en <code>php.ini</code></li>
                    </ul>

                    <h6>❌ Error: "Could not authenticate"</h6>
                    <ul>
                        <li>App Password incorrecto</li>
                        <li>Generar nuevo App Password en: <a href="https://myaccount.google.com/apppasswords" target="_blank">Google Account</a></li>
                        <li>Verificar que SMTP_USERNAME sea tu email completo</li>
                    </ul>

                    <h6>⚠️ Email no llega</h6>
                    <ul>
                        <li>Revisar carpeta de SPAM</li>
                        <li>Verificar logs arriba para ver errores</li>
                        <li>Intentar con otro email de destino</li>
                        <li>Cambiar <code>SMTP_FROM_EMAIL</code> para que coincida con <code>SMTP_USERNAME</code></li>
                    </ul>
                </div>
            </div>

            <!-- CONFIGURACIÓN APLICADA (EmailService.php) -->
            <div class="card">
                <div class="card-header bg-secondary text-white">
                    <h5 class="mb-0"><i class="bi bi-code-square"></i> Configuración Aplicada en EmailService.php</h5>
                </div>
                <div class="card-body">
                    <h6>✅ 1. Bypass de Certificados SSL (Líneas 45-51):</h6>
                    <pre class="bg-light p-3 rounded"><code>$this->mailer->SMTPOptions = array(
    'ssl' => array(
        'verify_peer'       => false,
        'verify_peer_name'  => false,
        'allow_self_signed' => true
    )
);</code></pre>

                    <h6>✅ 2. Debugging Activado (Líneas 57-65):</h6>
                    <pre class="bg-light p-3 rounded"><code>$this->mailer->SMTPDebug = SMTP::DEBUG_SERVER;  // Nivel 2
$this->mailer->Debugoutput = function($str, $level) {
    $this->logDebug("DEBUG Level $level: $str");
};</code></pre>

                    <h6>✅ 3. Configuraciones Optimizadas (Líneas 70-72):</h6>
                    <pre class="bg-light p-3 rounded"><code>$this->mailer->Timeout        = 60;
$this->mailer->SMTPKeepAlive  = false;
$this->mailer->SMTPAutoTLS    = true;</code></pre>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
