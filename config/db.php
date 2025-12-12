<?php
/**
 * PLATAFORMA EDUCATIVA - CONEXIÓN A BASE DE DATOS
 *
 * Clase Database con patrón Singleton para gestión de conexión PDO
 */

defined('APP_ACCESS') or define('APP_ACCESS', true);
require_once __DIR__ . '/config.php';

class Database {
    private static $instance = null;
    private $connection;

    /**
     * Constructor privado para patrón Singleton
     */
    private function __construct() {
        try {
            $dsn = "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=" . DB_CHARSET;

            $options = [
                PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES   => false,
                PDO::ATTR_PERSISTENT         => false,
                PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES " . DB_CHARSET
            ];

            $this->connection = new PDO($dsn, DB_USER, DB_PASS, $options);

        } catch (PDOException $e) {
            $this->logError($e->getMessage());
            die("Error de conexión a la base de datos. Por favor, contacte al administrador.");
        }
    }

    /**
     * Obtener instancia única de la clase (Singleton)
     *
     * @return Database
     */
    public static function getInstance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * Obtener conexión PDO
     *
     * @return PDO
     */
    public function getConnection() {
        return $this->connection;
    }

    /**
     * Ejecutar consulta preparada
     *
     * @param string $query Consulta SQL
     * @param array $params Parámetros para bind
     * @return PDOStatement
     * @throws Exception
     */
    public function query($query, $params = []) {
        try {
            $stmt = $this->connection->prepare($query);
            $stmt->execute($params);
            return $stmt;
        } catch (PDOException $e) {
            $this->logError($e->getMessage());
            throw new Exception("Error en la consulta a la base de datos.");
        }
    }

    /**
     * Obtener un solo registro
     *
     * @param string $query
     * @param array $params
     * @return array|false
     */
    public function fetchOne($query, $params = []) {
        return $this->query($query, $params)->fetch();
    }

    /**
     * Obtener todos los registros
     *
     * @param string $query
     * @param array $params
     * @return array
     */
    public function fetchAll($query, $params = []) {
        return $this->query($query, $params)->fetchAll();
    }

    /**
     * Obtener último ID insertado
     *
     * @return string
     */
    public function lastInsertId() {
        return $this->connection->lastInsertId();
    }

    /**
     * Iniciar transacción
     */
    public function beginTransaction() {
        return $this->connection->beginTransaction();
    }

    /**
     * Confirmar transacción
     */
    public function commit() {
        return $this->connection->commit();
    }

    /**
     * Revertir transacción
     */
    public function rollback() {
        return $this->connection->rollBack();
    }

    /**
     * Verificar si está en transacción
     *
     * @return bool
     */
    public function inTransaction() {
        return $this->connection->inTransaction();
    }

    /**
     * Registrar errores en archivo de log
     *
     * @param string $message
     */
    private function logError($message) {
        $logDir = __DIR__ . '/../logs';
        if (!is_dir($logDir)) {
            mkdir($logDir, 0755, true);
        }

        $logFile = $logDir . '/db_errors.log';
        $timestamp = date('Y-m-d H:i:s');
        $logMessage = "[{$timestamp}] {$message}" . PHP_EOL;

        error_log($logMessage, 3, $logFile);
    }

    /**
     * Prevenir clonación
     */
    private function __clone() {}

    /**
     * Prevenir deserialización
     */
    public function __wakeup() {
        throw new Exception("Cannot unserialize singleton");
    }
}

/**
 * Función helper para obtener la conexión de base de datos
 *
 * @return PDO
 */
function getDB() {
    return Database::getInstance()->getConnection();
}
