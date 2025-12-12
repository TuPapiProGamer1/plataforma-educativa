-- ============================================
-- PLATAFORMA EDUCATIVA - SCHEMA SQL
-- Base de datos con gestión de sesiones concurrentes
-- ============================================

-- Crear base de datos
CREATE DATABASE IF NOT EXISTS plataforma_educativa
CHARACTER SET utf8mb4
COLLATE utf8mb4_unicode_ci;

USE plataforma_educativa;

-- ============================================
-- TABLA: subscription_plans
-- Define los planes disponibles y sus límites
-- ============================================
CREATE TABLE subscription_plans (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    plan_name VARCHAR(50) NOT NULL UNIQUE,
    max_concurrent_sessions INT UNSIGNED NOT NULL,
    price DECIMAL(10,2) DEFAULT 0.00,
    description TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_plan_name (plan_name)
) ENGINE=InnoDB;

-- ============================================
-- TABLA: users
-- Almacena información de usuarios
-- ============================================
CREATE TABLE users (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    email VARCHAR(255) NOT NULL UNIQUE,
    password_hash VARCHAR(255) NOT NULL,
    role ENUM('admin', 'student') NOT NULL DEFAULT 'student',
    subscription_plan_id INT UNSIGNED NOT NULL,
    is_verified TINYINT(1) DEFAULT 0,
    verification_token VARCHAR(64) NULL,
    token_expires_at DATETIME NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_email (email),
    INDEX idx_verification_token (verification_token),
    FOREIGN KEY (subscription_plan_id) REFERENCES subscription_plans(id)
) ENGINE=InnoDB;

-- ============================================
-- TABLA: active_sessions
-- CRÍTICA: Controla las sesiones activas por usuario
-- ============================================
CREATE TABLE active_sessions (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id INT UNSIGNED NOT NULL,
    session_token VARCHAR(128) NOT NULL UNIQUE,
    device_info VARCHAR(500) NOT NULL,
    ip_address VARCHAR(45) NOT NULL,
    user_agent TEXT,
    last_activity TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_user_id (user_id),
    INDEX idx_session_token (session_token),
    INDEX idx_last_activity (last_activity),
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- ============================================
-- TABLA: session_logs
-- Auditoría de eventos de sesión
-- ============================================
CREATE TABLE session_logs (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id INT UNSIGNED NOT NULL,
    action ENUM('login', 'logout', 'session_rotated', 'session_expired', 'forced_logout') NOT NULL,
    device_info VARCHAR(500),
    ip_address VARCHAR(45),
    details TEXT,
    timestamp TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_user_id (user_id),
    INDEX idx_timestamp (timestamp),
    INDEX idx_action (action),
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- ============================================
-- INSERTAR PLANES DE SUSCRIPCIÓN
-- ============================================
INSERT INTO subscription_plans (plan_name, max_concurrent_sessions, price, description) VALUES
('Basic', 1, 0.00, 'Plan básico con 1 sesión simultánea'),
('Pro', 3, 9.99, 'Plan profesional con 3 sesiones simultáneas'),
('Premium', 5, 19.99, 'Plan premium con 5 sesiones simultáneas');

-- ============================================
-- CREAR USUARIOS DE PRUEBA CON HASHES CORRECTOS
-- ============================================

-- Usuario ADMIN
-- Email: admin@plataforma.com
-- Password: Admin@123
INSERT INTO users (email, password_hash, role, subscription_plan_id, is_verified) VALUES
('admin@plataforma.com', '$2y$12$SqldukPwZVQMdT1gWW/VseB1mmaylZEP.aW366SMBLhGCj3pSiSM6', 'admin', 3, 1);

-- Usuario ESTUDIANTE
-- Email: student@test.com
-- Password: Student@123
INSERT INTO users (email, password_hash, role, subscription_plan_id, is_verified) VALUES
('student@test.com', '$2y$12$xYCyvMksQV2gd7kGlJEVDeW9xltUbPzWVP5i7hyccOWjPpaS8HYsG', 'student', 1, 1);

-- ============================================
-- VISTA: Información completa de usuarios
-- ============================================
CREATE VIEW user_session_info AS
SELECT
    u.id,
    u.email,
    u.role,
    sp.plan_name,
    sp.max_concurrent_sessions,
    COUNT(DISTINCT ases.id) as current_active_sessions,
    u.is_verified,
    u.created_at
FROM users u
LEFT JOIN subscription_plans sp ON u.subscription_plan_id = sp.id
LEFT JOIN active_sessions ases ON u.id = ases.user_id
GROUP BY u.id, u.email, u.role, sp.plan_name, sp.max_concurrent_sessions, u.is_verified, u.created_at;

-- ============================================
-- PROCEDIMIENTO: Limpiar sesiones expiradas
-- Elimina sesiones inactivas por más de 24 horas
-- ============================================
DELIMITER //

CREATE PROCEDURE cleanup_expired_sessions()
BEGIN
    -- Registrar sesiones que serán eliminadas
    INSERT INTO session_logs (user_id, action, device_info, ip_address, details)
    SELECT
        user_id,
        'session_expired',
        device_info,
        ip_address,
        CONCAT('Sesión expirada por inactividad. Última actividad: ', last_activity)
    FROM active_sessions
    WHERE last_activity < DATE_SUB(NOW(), INTERVAL 24 HOUR);

    -- Eliminar sesiones expiradas
    DELETE FROM active_sessions
    WHERE last_activity < DATE_SUB(NOW(), INTERVAL 24 HOUR);

    SELECT ROW_COUNT() as sessions_cleaned;
END //

DELIMITER ;

-- ============================================
-- EVENTO: Ejecutar limpieza automática cada hora
-- ============================================
SET GLOBAL event_scheduler = ON;

CREATE EVENT IF NOT EXISTS auto_cleanup_sessions
ON SCHEDULE EVERY 1 HOUR
DO
    CALL cleanup_expired_sessions();

-- ============================================
-- ÍNDICES ADICIONALES PARA OPTIMIZACIÓN
-- ============================================
ALTER TABLE active_sessions
    ADD INDEX idx_user_last_activity (user_id, last_activity);

ALTER TABLE session_logs
    ADD INDEX idx_user_timestamp (user_id, timestamp);

-- ============================================
-- FIN DEL SCHEMA
-- ============================================
