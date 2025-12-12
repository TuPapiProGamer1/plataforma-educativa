# 🎓 GUÍA PARA LA DEFENSA DEL PROYECTO

## 📋 Resumen Ejecutivo del Proyecto

**Nombre:** Plataforma Educativa con Gestión de Sesiones Concurrentes
**Stack:** PHP 8.0+ | MySQL | Bootstrap 5 | PHPMailer
**Característica Principal:** Rotación automática de sesiones según plan de suscripción

---

## 🎯 Puntos Clave de la Rúbrica Cumplidos

### ✅ 1. Base de Datos Robusta (20 puntos)

**Tablas Normalizadas:**
- `subscription_plans` - Definición de planes (Basic, Pro, Premium)
- `users` - Información de usuarios con verificación por email
- `active_sessions` - **TABLA CRÍTICA** para control de sesiones
- `session_logs` - Auditoría completa de actividad

**Características Avanzadas:**
- Índices optimizados para consultas rápidas
- Relaciones con Foreign Keys (integridad referencial)
- Procedimiento almacenado `cleanup_expired_sessions()`
- Vista SQL `user_session_info` para consultas complejas
- Evento programado para limpieza automática

**Mostrar en defensa:**
```sql
-- Ver estructura de tabla crítica
DESCRIBE active_sessions;

-- Consultar vista optimizada
SELECT * FROM user_session_info;

-- Ejecutar procedimiento
CALL cleanup_expired_sessions();
```

---

### ✅ 2. Autenticación y Seguridad (25 puntos)

**Registro Seguro:**
- Validación de email con expresiones regulares
- Hash de contraseñas con **Argon2ID** (más seguro que bcrypt)
- Generación de token aleatorio de 64 caracteres
- Envío de email de verificación con PHPMailer

**Código clave (register.php:67-71):**
```php
$passwordHash = password_hash($password, PASSWORD_ARGON2ID);
$verificationToken = bin2hex(random_bytes(TOKEN_LENGTH / 2));
```

**Login Seguro:**
- Verificación de cuenta activada (`is_verified = 1`)
- Prepared Statements con PDO (previene SQL Injection)
- Protección contra Session Fixation (`session_regenerate_id()`)

**Protecciones Implementadas:**
- ✅ SQL Injection → Prepared Statements
- ✅ XSS → `htmlspecialchars()` en todas las salidas
- ✅ CSRF → Tokens de sesión únicos
- ✅ Password Strength → Validación de complejidad
- ✅ Session Hijacking → Regeneración periódica de ID

---

### ✅ 3. Lógica de Control de Sesiones (35 puntos) - **EL NÚCLEO**

#### **Rotación Automática al Login**

**Flujo completo (login.php:123-198):**

1. **Detectar plan del usuario:**
```php
$maxSessions = $user['max_concurrent_sessions']; // 1, 3 o 5
```

2. **Contar sesiones activas:**
```php
$currentSessions = $db->fetchOne(
    "SELECT COUNT(*) as total FROM active_sessions WHERE user_id = :user_id"
);
```

3. **Si excede límite → ROTACIÓN:**
```php
if ($currentSessions >= $maxSessions) {
    // Obtener sesión más antigua
    $oldestSession = $db->fetchOne(
        "SELECT id FROM active_sessions
         WHERE user_id = :user_id
         ORDER BY last_activity ASC LIMIT 1"
    );

    // Registrar en logs
    $db->query("INSERT INTO session_logs (action) VALUES ('session_rotated')");

    // Eliminar sesión antigua
    $db->query("DELETE FROM active_sessions WHERE id = :id");
}
```

4. **Crear nueva sesión:**
```php
$sessionToken = bin2hex(random_bytes(64));
$db->query("INSERT INTO active_sessions (session_token, ...) VALUES (...)");
```

#### **Middleware de Expulsión (auth_check.php)**

**Incluido en TODAS las páginas protegidas:**
```php
require_once __DIR__ . '/includes/auth_check.php';
```

**Funciones:**
1. Verificar que el token de sesión PHP existe en la BD
2. Si NO existe → La sesión fue rotada → **EXPULSAR**
3. Actualizar `last_activity` para mantener sesión viva

**Código crítico (auth_check.php:55-68):**
```php
$activeSession = $db->fetchOne(
    "SELECT id FROM active_sessions
     WHERE user_id = :user_id AND session_token = :token"
);

if (!$activeSession) {
    // Token no existe = sesión rotada
    forceLogout('Has excedido el límite de sesiones concurrentes');
}
```

---

### ✅ 4. Frontend Responsive (10 puntos)

**Bootstrap 5 Implementado:**
- Grid System responsive (col-md-*, col-lg-*)
- Cards con hover effects
- Navbar con dropdown
- Alerts contextuales
- Progress bars dinámicos
- Mobile-first approach

**Características visuales:**
- Gradientes personalizados (`linear-gradient(135deg, #667eea 0%, #764ba2 100%)`)
- Iconos de Bootstrap Icons
- Transiciones CSS suaves
- Dashboard con información en tiempo real (reloj JS)

**Responsive breakpoints:**
- Mobile (< 768px): Columnas apiladas
- Tablet (768px - 992px): 2 columnas
- Desktop (> 992px): 3 columnas

---

### ✅ 5. Email con PHPMailer (10 puntos)

**Configuración SMTP (EmailService.php:31-42):**
```php
$this->mailer->isSMTP();
$this->mailer->Host       = 'smtp.gmail.com';
$this->mailer->SMTPAuth   = true;
$this->mailer->Username   = SMTP_USERNAME;
$this->mailer->Password   = SMTP_PASSWORD; // App Password
$this->mailer->SMTPSecure = 'tls';
$this->mailer->Port       = 587;
```

**Emails enviados:**
1. **Verificación de cuenta** (HTML + texto plano)
2. **Alerta de rotación** (opcional, notifica dispositivo expulsado)

**Templates HTML profesionales:**
- Diseño responsive
- Botones call-to-action
- Branding consistente
- Fallback a texto plano

---

## 🔍 Demostración en Vivo - Paso a Paso

### **Demo 1: Registro y Verificación**

1. Ir a `register.php`
2. Completar formulario con email real
3. Seleccionar Plan Basic (1 sesión)
4. Mostrar email recibido en bandeja
5. Click en link de verificación
6. Verificar en BD: `SELECT is_verified FROM users WHERE email = 'tu@email.com';`

### **Demo 2: Rotación Automática (CRÍTICO)**

**Escenario:** Usuario con Plan Basic (1 sesión)

1. **Navegador Chrome:**
   - Login con `student@test.com` / `Student@123`
   - Ir al dashboard
   - Mostrar sesión activa: `SELECT * FROM active_sessions;`

2. **Navegador Firefox:**
   - Login con el mismo usuario
   - Mostrar que se creó nueva sesión

3. **Volver a Chrome:**
   - Refrescar página
   - **RESULTADO:** Mensaje "Has excedido el límite..."
   - Redirigido a login

4. **Verificar en BD:**
```sql
-- Ver logs de rotación
SELECT * FROM session_logs
WHERE user_id = 2 AND action = 'session_rotated'
ORDER BY timestamp DESC LIMIT 5;

-- Ver sesiones activas (debe haber solo 1)
SELECT COUNT(*) FROM active_sessions WHERE user_id = 2;
```

### **Demo 3: Múltiples Sesiones con Plan Premium**

1. Cambiar plan del usuario:
```sql
UPDATE users SET subscription_plan_id = 3 WHERE id = 2; -- Premium (5 sesiones)
```

2. Abrir sesiones desde 5 navegadores/dispositivos distintos
3. Intentar abrir la 6ta sesión
4. Mostrar que la sesión más antigua fue eliminada
5. Verificar logs de rotación en el dashboard

---

## 📊 Consultas SQL para Demostración

```sql
-- 1. Ver todos los usuarios y sus planes
SELECT u.email, sp.plan_name, sp.max_concurrent_sessions, u.is_verified
FROM users u
JOIN subscription_plans sp ON u.subscription_plan_id = sp.id;

-- 2. Ver sesiones activas por usuario
SELECT u.email, COUNT(s.id) as sesiones_activas, sp.max_concurrent_sessions
FROM users u
LEFT JOIN active_sessions s ON u.id = s.user_id
JOIN subscription_plans sp ON u.subscription_plan_id = sp.id
GROUP BY u.id;

-- 3. Ver actividad reciente de un usuario
SELECT action, device_info, ip_address, timestamp
FROM session_logs
WHERE user_id = 2
ORDER BY timestamp DESC
LIMIT 10;

-- 4. Ver sesiones más antiguas (candidatas a rotación)
SELECT user_id, device_info, last_activity
FROM active_sessions
ORDER BY last_activity ASC;

-- 5. Simular limpieza de sesiones expiradas
CALL cleanup_expired_sessions();
```

---

## 🛡️ Explicación de Seguridad

### **¿Por qué Argon2ID en vez de bcrypt?**
- Argon2ID es el ganador del Password Hashing Competition (2015)
- Resistente a ataques GPU/ASIC
- Combina resistencia a side-channel y GPU cracking
- Recomendado por OWASP

### **¿Por qué Prepared Statements?**
```php
// ❌ VULNERABLE a SQL Injection
$query = "SELECT * FROM users WHERE email = '$email'";

// ✅ SEGURO con Prepared Statements
$query = "SELECT * FROM users WHERE email = :email";
$db->query($query, ['email' => $email]);
```

### **¿Cómo previene Session Hijacking?**
1. Token único de 128 caracteres aleatorios
2. Regeneración de ID cada 30 minutos
3. Validación en cada request contra la BD
4. HttpOnly cookies (JavaScript no puede acceder)

---

## 💡 Preguntas Frecuentes y Respuestas

### **P: ¿Qué pasa si el usuario cierra el navegador?**
R: La sesión permanece activa en la BD. Se elimina solo por:
- Logout manual
- Rotación (exceso de límite)
- Expiración por inactividad (24 horas)
- Limpieza automática del procedimiento almacenado

### **P: ¿Se puede evitar la rotación?**
R: No. Es el comportamiento deseado según la rúbrica. Si quieres más sesiones, debes cambiar de plan.

### **P: ¿Qué pasa si elimino manualmente una sesión de la BD?**
R: El middleware `auth_check.php` detecta que el token no existe y expulsa al usuario inmediatamente en el siguiente request.

### **P: ¿Por qué usas transacciones en el login?**
R: Para garantizar consistencia. Si falla la creación de la nueva sesión después de eliminar la antigua, se revierte todo con `$db->rollback()`.

### **P: ¿Funciona en 000webhost?**
R: Sí, completamente. Solo necesitas:
1. Subir archivos vía FTP/File Manager
2. Importar `schema.sql` en phpMyAdmin
3. Ejecutar `composer install` desde terminal SSH (si está disponible)
4. Configurar credenciales en `config.php`

---

## 📈 Estructura del Código - Árbol de Archivos

```
plataformaeducativa/
│
├── 📄 index.php                  # Redirección inicial
├── 📄 register.php               # Registro de usuarios
├── 📄 verify.php                 # Verificación de email
├── 📄 login.php                  # ⭐ Login con rotación
├── 📄 dashboard.php              # Panel principal
├── 📄 logout.php                 # Cierre de sesión
│
├── 📁 config/
│   ├── config.php                # Configuración general
│   └── db.php                    # ⭐ Conexión PDO singleton
│
├── 📁 includes/
│   └── auth_check.php            # ⭐ Middleware crítico
│
├── 📁 classes/
│   └── EmailService.php          # Servicio de emails
│
├── 📁 database/
│   └── schema.sql                # ⭐ Schema completo
│
├── 📁 logs/                      # Logs de errores (auto)
├── 📁 vendor/                    # Dependencias Composer
│
├── 📄 composer.json              # Dependencias
├── 📄 .htaccess                  # Seguridad Apache
├── 📄 .gitignore                 # Archivos ignorados
│
├── 📄 README.md                  # Documentación completa
├── 📄 INSTALL.md                 # Guía de instalación
└── 📄 DEFENSA_PROYECTO.md        # Este archivo
```

---

## 🎤 Script de Presentación (5 minutos)

### **Introducción (30 seg)**
"Buenos días. Hoy presento una Plataforma Educativa con gestión avanzada de sesiones concurrentes. La característica principal es la **rotación automática** de dispositivos según el plan de suscripción del usuario."

### **Arquitectura (1 min)**
"El stack tecnológico es: PHP 8 nativo con POO, MySQL con InnoDB, Bootstrap 5 responsive, y PHPMailer para emails vía SMTP de Gmail. La base de datos tiene 4 tablas normalizadas con índices optimizados."

### **Demostración (2 min)**
"Voy a mostrar la rotación en vivo:
1. Me registro con Plan Basic (1 sesión)
2. Hago login en Chrome
3. Hago login en Firefox con el mismo usuario
4. Chrome es expulsado automáticamente
5. El sistema registra el evento en `session_logs`"

[Ejecutar demo en vivo]

### **Seguridad (1 min)**
"Las medidas de seguridad implementadas son:
- Prepared Statements contra SQL Injection
- Argon2ID para hashing de passwords
- Middleware en cada página que valida el token contra la BD
- Si el token fue eliminado por rotación, el usuario es expulsado inmediatamente"

### **Cierre (30 seg)**
"El proyecto cumple todos los puntos de la rúbrica: BD robusta, autenticación segura, rotación automática, frontend responsive con Bootstrap 5, y emails con PHPMailer. El código está listo para producción y puede desplegarse en 000webhost. ¿Preguntas?"

---

## 📌 Checklist Pre-Defensa

- [ ] Base de datos importada y funcionando
- [ ] Usuarios de prueba creados
- [ ] Emails configurados (o deshabilitados si no funciona SMTP)
- [ ] Navegadores listos para demo (Chrome + Firefox)
- [ ] phpMyAdmin abierto para mostrar consultas
- [ ] Dashboard accesible
- [ ] Código fuente listo para mostrar partes críticas
- [ ] README impreso o en pantalla secundaria

---

## 🏆 Diferenciadores de Este Proyecto

1. **Transacciones de BD** para consistencia en rotación
2. **Procedimientos almacenados** para limpieza automática
3. **Vista SQL** para consultas optimizadas
4. **Middleware robusto** que expulsa sesiones rotadas
5. **Auditoría completa** con `session_logs`
6. **Templates HTML** profesionales en emails
7. **Código documentado** con comentarios explicativos
8. **Instalador automático** opcional

---

**¡Éxito en tu defensa! 🚀**
