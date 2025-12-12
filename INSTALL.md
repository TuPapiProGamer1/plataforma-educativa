# 🚀 Guía de Instalación Rápida - Plataforma Educativa

## ⚡ Instalación en 5 Minutos

### 1️⃣ Instalar Dependencias

```bash
cd C:\xampp\htdocs\plataformaeducativa
composer install
```

### 2️⃣ Crear Base de Datos

**Opción A: Desde MySQL CLI**
```bash
mysql -u root -p < database/schema.sql
```

**Opción B: Desde phpMyAdmin**
1. Abrir phpMyAdmin (http://localhost/phpmyadmin)
2. Click en "Importar"
3. Seleccionar `database/schema.sql`
4. Click en "Continuar"

### 3️⃣ Configurar Credenciales

Editar `config/config.php` (líneas 14-24):

```php
// Base de Datos
define('DB_HOST', 'localhost');
define('DB_NAME', 'plataforma_educativa');
define('DB_USER', 'root');
define('DB_PASS', '');  // Tu contraseña MySQL

// Email SMTP (Gmail)
define('SMTP_USERNAME', 'tu-email@gmail.com');
define('SMTP_PASSWORD', 'xxxx xxxx xxxx xxxx'); // App Password de Gmail

// URL de la aplicación
define('APP_URL', 'http://localhost/plataformaeducativa');
```

### 4️⃣ Configurar App Password de Gmail

1. Ir a: https://myaccount.google.com/apppasswords
2. Generar contraseña para "Correo"
3. Copiar la contraseña de 16 caracteres
4. Pegar en `SMTP_PASSWORD` (con o sin espacios)

### 5️⃣ Acceder a la Aplicación

```
http://localhost/plataformaeducativa/login.php
```

---

## 👤 Usuarios de Prueba

### Admin
- Email: `admin@plataforma.com`
- Password: `Admin@123`

### Estudiante
- Email: `student@test.com`
- Password: `Student@123`

---

## ✅ Verificar Instalación

### Test 1: Login Básico
1. Acceder a login.php
2. Ingresar credenciales de prueba
3. Debe redirigir al dashboard

### Test 2: Rotación de Sesiones
1. Login con `student@test.com` en Chrome (Plan Basic = 1 sesión)
2. Login con el mismo usuario en Firefox
3. **Resultado esperado**: Chrome debe ser expulsado automáticamente

### Test 3: Registro y Email
1. Ir a register.php
2. Registrar nuevo usuario
3. Revisar bandeja de entrada
4. Click en link de verificación

---

## 🐛 Solución de Problemas Comunes

### Error: "Access denied for user"
```php
// Verificar credenciales en config/config.php
define('DB_USER', 'root');
define('DB_PASS', ''); // ← Cambiar si tienes contraseña
```

### Error: "Table doesn't exist"
```bash
# Reimportar base de datos
mysql -u root -p plataforma_educativa < database/schema.sql
```

### Error: "SMTP connect() failed"
```php
// Opción 1: Usar App Password de Gmail (recomendado)
define('SMTP_PASSWORD', 'xxxx xxxx xxxx xxxx');

// Opción 2: Deshabilitar emails temporalmente
// Comentar líneas de envío en register.php (línea 87-93)
```

### Error: "Session expired" constantemente
```php
// En config/config.php aumentar tiempo:
define('SESSION_LIFETIME', 86400); // 24 horas
```

---

## 📂 Estructura de Archivos Requerida

```
plataformaeducativa/
├── config/
│   ├── config.php ✓
│   └── db.php ✓
├── classes/
│   └── EmailService.php ✓
├── includes/
│   └── auth_check.php ✓
├── database/
│   └── schema.sql ✓
├── vendor/ (generado por composer)
├── logs/ (auto-creado)
├── register.php ✓
├── login.php ✓
├── verify.php ✓
├── dashboard.php ✓
├── logout.php ✓
└── composer.json ✓
```

---

## 🌐 Despliegue en 000webhost

### Paso 1: Preparar Proyecto

```bash
# Excluir vendor y logs del ZIP
zip -r plataforma.zip . -x "vendor/*" "logs/*"
```

### Paso 2: Subir Archivos

1. Acceder a File Manager de 000webhost
2. Subir `plataforma.zip` a `/public_html/`
3. Extraer archivos

### Paso 3: Instalar Dependencias

Desde terminal SSH de 000webhost:

```bash
cd public_html
composer install --no-dev
```

### Paso 4: Configurar Base de Datos

1. Crear BD desde panel de 000webhost
2. Anotar credenciales:
   - Host: `localhost`
   - Database: `id12345_plataforma`
   - User: `id12345_user`
   - Password: `xxxxxxxxxx`

3. Importar `database/schema.sql` desde phpMyAdmin

4. Actualizar `config/config.php`:

```php
define('DB_HOST', 'localhost');
define('DB_NAME', 'id12345_plataforma');
define('DB_USER', 'id12345_user');
define('DB_PASS', 'xxxxxxxxxx');
define('APP_URL', 'https://tu-sitio.000webhostapp.com');
define('APP_ENV', 'production');
```

### Paso 5: Probar

```
https://tu-sitio.000webhostapp.com/login.php
```

---

## 🔧 Configuración Avanzada

### Cambiar Tiempo de Expiración de Sesiones

```php
// config/config.php (línea 45)
define('SESSION_LIFETIME', 86400); // 24 horas en segundos
```

### Cambiar Límites de Planes

```sql
-- Ejecutar en MySQL
UPDATE subscription_plans SET max_concurrent_sessions = 10 WHERE plan_name = 'Premium';
```

### Agregar Nuevo Plan

```sql
INSERT INTO subscription_plans (plan_name, max_concurrent_sessions, price, description)
VALUES ('Enterprise', 20, 49.99, 'Plan empresarial con 20 sesiones simultáneas');
```

---

## 📊 Comandos Útiles de MySQL

```sql
-- Ver usuarios registrados
SELECT email, is_verified, created_at FROM users;

-- Ver sesiones activas
SELECT u.email, COUNT(s.id) as sesiones
FROM users u
LEFT JOIN active_sessions s ON u.id = s.user_id
GROUP BY u.id;

-- Ver logs de actividad
SELECT u.email, sl.action, sl.timestamp
FROM session_logs sl
JOIN users u ON sl.user_id = u.id
ORDER BY sl.timestamp DESC
LIMIT 20;

-- Limpiar sesiones expiradas manualmente
CALL cleanup_expired_sessions();

-- Eliminar todas las sesiones de un usuario
DELETE FROM active_sessions WHERE user_id = 1;
```

---

## 🎯 Checklist de Instalación

- [ ] Composer instalado
- [ ] MySQL/MariaDB corriendo
- [ ] Base de datos creada (`plataforma_educativa`)
- [ ] Schema SQL importado
- [ ] Dependencias instaladas (`composer install`)
- [ ] Credenciales de BD configuradas en `config.php`
- [ ] SMTP configurado (o deshabilitado temporalmente)
- [ ] Acceso a `login.php` exitoso
- [ ] Login con usuario de prueba funcionando
- [ ] Dashboard cargando correctamente

---

## 🆘 Contacto y Soporte

Si encuentras problemas:

1. **Revisar logs**: `logs/db_errors.log` y `logs/email_errors.log`
2. **Verificar configuración**: `config/config.php`
3. **Comprobar base de datos**: phpMyAdmin

---

**¡Listo para usar! 🎉**
