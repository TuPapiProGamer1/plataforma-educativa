<?php
/**
 * PLATAFORMA EDUCATIVA - SERVICIO DE EMAIL
 *
 * Gestiona el envío de correos electrónicos usando PHPMailer
 * OPTIMIZADO PARA XAMPP CON BYPASS SSL Y DEBUGGING
 */

defined('APP_ACCESS') or die('Acceso denegado');

// Importar PHPMailer
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\SMTP;
use PHPMailer\PHPMailer\Exception;

require_once __DIR__ . '/../vendor/autoload.php';

class EmailService {

    private $mailer;

    /**
     * Constructor - Configura PHPMailer con SMTP
     * OPTIMIZADO PARA XAMPP LOCALHOST
     */
    public function __construct() {
        $this->mailer = new PHPMailer(true);

        try {
            // ===================================================
            // CONFIGURACIÓN SMTP BÁSICA
            // ===================================================
            $this->mailer->isSMTP();
            $this->mailer->Host       = SMTP_HOST;              // smtp.gmail.com
            $this->mailer->SMTPAuth   = true;
            $this->mailer->Username   = SMTP_USERNAME;          // Tu email
            $this->mailer->Password   = SMTP_PASSWORD;          // App Password
            $this->mailer->SMTPSecure = SMTP_SECURE;            // tls
            $this->mailer->Port       = SMTP_PORT;              // 587

            // ===================================================
            // 1. BYPASS DE CERTIFICADOS SSL (CRÍTICO PARA XAMPP)
            // Solución al error: "SMTP connect() failed"
            // ===================================================
            $this->mailer->SMTPOptions = array(
                'ssl' => array(
                    'verify_peer'       => false,  // No verificar certificado del peer
                    'verify_peer_name'  => false,  // No verificar nombre del peer
                    'allow_self_signed' => true    // Permitir certificados autofirmados
                )
            );

            // ===================================================
            // 2. ACTIVACIÓN DE DEBUGGING (MODO DESARROLLO)
            // Niveles: 0=OFF, 1=Client, 2=Client+Server, 3=Client+Server+Connection, 4=Low-level
            // ===================================================
            // DEBUG FORZADO NIVEL 2 - SALIDA EN PANTALLA
            $this->mailer->SMTPDebug = 2;
            $this->mailer->Debugoutput = 'html'; // Salida HTML formateada en pantalla

            // ===================================================
            // 3. CONFIGURACIÓN OPTIMIZADA PARA LOCALHOST
            // ===================================================
            $this->mailer->Timeout        = 60;         // Timeout extendido (60 segundos)
            $this->mailer->SMTPKeepAlive  = false;      // No mantener conexión abierta
            $this->mailer->SMTPAutoTLS    = true;       // Auto-habilitar TLS si disponible

            // Configuración del remitente
            $this->mailer->setFrom(SMTP_FROM_EMAIL, SMTP_FROM_NAME);
            $this->mailer->CharSet = 'UTF-8';
            $this->mailer->Encoding = 'base64';

            // Configuración adicional de headers para evitar spam
            $this->mailer->XMailer = ' '; // Ocultar PHPMailer version
            $this->mailer->Priority = 3;  // Normal priority

        } catch (Exception $e) {
            $this->logError("Error al configurar PHPMailer: " . $e->getMessage());
            throw new Exception("Error de configuración del servicio de email");
        }
    }

    /**
     * Enviar email de verificación de cuenta
     *
     * @param string $toEmail Email del destinatario
     * @param string $verificationToken Token de verificación
     * @return bool
     */
    public function sendVerificationEmail($toEmail, $verificationToken) {
        try {
            $verificationLink = APP_URL . "/verify.php?token=" . urlencode($verificationToken);

            $this->mailer->clearAddresses();
            $this->mailer->addAddress($toEmail);

            $this->mailer->isHTML(true);
            $this->mailer->Subject = 'Verifica tu cuenta - ' . APP_NAME;

            $this->mailer->Body = $this->getVerificationEmailTemplate($verificationLink);
            $this->mailer->AltBody = "Bienvenido a " . APP_NAME . ".\n\n"
                                   . "Por favor verifica tu cuenta accediendo al siguiente enlace:\n"
                                   . $verificationLink . "\n\n"
                                   . "Este enlace expirará en 24 horas.";

            // INTENTAR ENVIAR
            $sent = $this->mailer->send();

            if ($sent) {
                $this->logInfo("Email de verificación enviado exitosamente a: {$toEmail}");
            }

            return $sent;

        } catch (Exception $e) {
            $errorMsg = "Error al enviar email de verificación a {$toEmail}: " . $e->getMessage();
            $this->logError($errorMsg);

            // Log adicional del stack trace en desarrollo
            if (APP_ENV === 'development') {
                $this->logError("Stack Trace: " . $e->getTraceAsString());
            }

            return false;
        }
    }

    /**
     * Enviar email de alerta de sesión rotada
     *
     * @param string $toEmail
     * @param string $deviceInfo
     * @return bool
     */
    public function sendSessionRotationAlert($toEmail, $deviceInfo) {
        try {
            $this->mailer->clearAddresses();
            $this->mailer->addAddress($toEmail);

            $this->mailer->isHTML(true);
            $this->mailer->Subject = 'Alerta de Seguridad - Sesión Rotada';

            $this->mailer->Body = $this->getSessionRotationTemplate($deviceInfo);
            $this->mailer->AltBody = "Tu sesión en el dispositivo: {$deviceInfo} ha sido cerrada "
                                   . "debido a que se alcanzó el límite de sesiones concurrentes.\n\n"
                                   . "Si no reconoces esta actividad, por favor cambia tu contraseña.";

            $sent = $this->mailer->send();

            if ($sent) {
                $this->logInfo("Email de alerta de rotación enviado a: {$toEmail}");
            }

            return $sent;

        } catch (Exception $e) {
            $this->logError("Error al enviar alerta de rotación: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Método de prueba para verificar configuración SMTP
     *
     * @param string $testEmail Email de prueba
     * @return array Array con resultado y mensaje
     */
    public function testConnection($testEmail = null) {
        $result = [
            'success' => false,
            'message' => '',
            'details' => []
        ];

        try {
            // Verificar credenciales
            if (empty(SMTP_USERNAME) || empty(SMTP_PASSWORD)) {
                throw new Exception("Credenciales SMTP no configuradas en config.php");
            }

            $result['details']['smtp_host'] = SMTP_HOST;
            $result['details']['smtp_port'] = SMTP_PORT;
            $result['details']['smtp_secure'] = SMTP_SECURE;
            $result['details']['smtp_username'] = SMTP_USERNAME;

            // Si se proporciona email, intentar enviar email de prueba
            if ($testEmail) {
                $this->mailer->clearAddresses();
                $this->mailer->addAddress($testEmail);
                $this->mailer->isHTML(false);
                $this->mailer->Subject = 'Test - Plataforma Educativa';
                $this->mailer->Body = 'Este es un email de prueba. La configuración SMTP funciona correctamente.';

                $sent = $this->mailer->send();

                if ($sent) {
                    $result['success'] = true;
                    $result['message'] = "Email de prueba enviado correctamente a {$testEmail}";
                }
            } else {
                // Solo verificar que la configuración esté presente
                $result['success'] = true;
                $result['message'] = "Configuración SMTP verificada (sin envío de prueba)";
            }

        } catch (Exception $e) {
            $result['success'] = false;
            $result['message'] = "Error: " . $e->getMessage();
            $result['details']['error_trace'] = $e->getTraceAsString();
        }

        return $result;
    }

    /**
     * Template HTML para email de verificación
     *
     * @param string $verificationLink
     * @return string
     */
    private function getVerificationEmailTemplate($verificationLink) {
        return "
        <!DOCTYPE html>
        <html>
        <head>
            <meta charset='UTF-8'>
            <style>
                body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }
                .container { max-width: 600px; margin: 0 auto; padding: 20px; }
                .header { background: #4CAF50; color: white; padding: 20px; text-align: center; }
                .content { background: #f9f9f9; padding: 30px; border: 1px solid #ddd; }
                .button { display: inline-block; padding: 12px 30px; background: #4CAF50; color: white; text-decoration: none; border-radius: 5px; margin: 20px 0; }
                .footer { text-align: center; margin-top: 20px; color: #777; font-size: 12px; }
            </style>
        </head>
        <body>
            <div class='container'>
                <div class='header'>
                    <h1>" . APP_NAME . "</h1>
                </div>
                <div class='content'>
                    <h2>¡Bienvenido!</h2>
                    <p>Gracias por registrarte en nuestra plataforma educativa.</p>
                    <p>Para activar tu cuenta, por favor haz clic en el siguiente botón:</p>
                    <div style='text-align: center;'>
                        <a href='{$verificationLink}' class='button'>Verificar mi cuenta</a>
                    </div>
                    <p>O copia y pega este enlace en tu navegador:</p>
                    <p style='word-break: break-all; color: #4CAF50;'>{$verificationLink}</p>
                    <p><strong>Este enlace expirará en 24 horas.</strong></p>
                </div>
                <div class='footer'>
                    <p>Si no solicitaste esta cuenta, puedes ignorar este correo.</p>
                    <p>&copy; " . date('Y') . " " . APP_NAME . ". Todos los derechos reservados.</p>
                </div>
            </div>
        </body>
        </html>
        ";
    }

    /**
     * Template HTML para alerta de rotación
     *
     * @param string $deviceInfo
     * @return string
     */
    private function getSessionRotationTemplate($deviceInfo) {
        $timestamp = date('d/m/Y H:i:s');
        return "
        <!DOCTYPE html>
        <html>
        <head>
            <meta charset='UTF-8'>
            <style>
                body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }
                .container { max-width: 600px; margin: 0 auto; padding: 20px; }
                .header { background: #ff9800; color: white; padding: 20px; text-align: center; }
                .content { background: #f9f9f9; padding: 30px; border: 1px solid #ddd; }
                .alert { background: #fff3cd; border-left: 4px solid #ff9800; padding: 15px; margin: 15px 0; }
                .footer { text-align: center; margin-top: 20px; color: #777; font-size: 12px; }
            </style>
        </head>
        <body>
            <div class='container'>
                <div class='header'>
                    <h1>⚠️ Alerta de Seguridad</h1>
                </div>
                <div class='content'>
                    <h2>Sesión Cerrada por Límite de Dispositivos</h2>
                    <div class='alert'>
                        <strong>Tu sesión ha sido cerrada automáticamente.</strong>
                    </div>
                    <p><strong>Dispositivo afectado:</strong> {$deviceInfo}</p>
                    <p><strong>Fecha y hora:</strong> {$timestamp}</p>
                    <p>Se alcanzó el límite máximo de sesiones concurrentes permitidas por tu plan.</p>
                    <p>La sesión más antigua fue cerrada para permitir el nuevo inicio de sesión.</p>
                    <hr>
                    <p>Si no reconoces esta actividad, por favor:</p>
                    <ul>
                        <li>Cambia tu contraseña inmediatamente</li>
                        <li>Revisa tus sesiones activas</li>
                        <li>Contacta a soporte si es necesario</li>
                    </ul>
                </div>
                <div class='footer'>
                    <p>&copy; " . date('Y') . " " . APP_NAME . ". Todos los derechos reservados.</p>
                </div>
            </div>
        </body>
        </html>
        ";
    }

    /**
     * Registrar mensajes informativos
     *
     * @param string $message
     */
    private function logInfo($message) {
        $this->writeLog('INFO', $message);
    }

    /**
     * Registrar errores en archivo de log
     *
     * @param string $message
     */
    private function logError($message) {
        $this->writeLog('ERROR', $message);
    }

    /**
     * Registrar mensajes de debug
     *
     * @param string $message
     */
    private function logDebug($message) {
        if (APP_ENV === 'development') {
            $this->writeLog('DEBUG', $message);
        }
    }

    /**
     * Escribir en archivo de log
     *
     * @param string $level
     * @param string $message
     */
    private function writeLog($level, $message) {
        $logDir = __DIR__ . '/../logs';
        if (!is_dir($logDir)) {
            mkdir($logDir, 0755, true);
        }

        $logFile = $logDir . '/email.log';
        $timestamp = date('Y-m-d H:i:s');
        $logMessage = "[{$timestamp}] [{$level}] {$message}" . PHP_EOL;

        error_log($logMessage, 3, $logFile);
    }
}
