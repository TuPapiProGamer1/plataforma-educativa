-- =============================================
-- Script de Creación de Tabla: ROLES
-- Plataforma Educativa
-- =============================================

-- Crear tabla roles si no existe
CREATE TABLE IF NOT EXISTS roles (
    id_rol INT(11) NOT NULL AUTO_INCREMENT,
    nombre_rol VARCHAR(50) NOT NULL UNIQUE,
    descripcion VARCHAR(255) DEFAULT NULL,
    PRIMARY KEY (id_rol)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Insertar roles de ejemplo
INSERT INTO roles (nombre_rol, descripcion) VALUES
('Admin', 'Administrador del sistema con acceso total'),
('Estudiante', 'Usuario estudiante con acceso limitado a cursos y materiales')
ON DUPLICATE KEY UPDATE descripcion = VALUES(descripcion);

-- Roles adicionales opcionales (comentados por si los necesitas después)
-- INSERT INTO roles (nombre_rol, descripcion) VALUES
-- ('Profesor', 'Docente con permisos para gestionar cursos y estudiantes'),
-- ('Tutor', 'Tutor con acceso a seguimiento de estudiantes')
-- ON DUPLICATE KEY UPDATE descripcion = VALUES(descripcion);

-- Verificar datos insertados
SELECT * FROM roles;
