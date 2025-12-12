# ⚡ INICIO RÁPIDO - Plataforma Educativa

## 🚨 SOLUCIÓN AL ERROR "vendor/autoload.php not found"

Si ves este error, sigue estos pasos:

---

## 📋 PASOS PARA ARRANCAR EL PROYECTO

### ✅ **PASO 1: Instalar Dependencias (Composer)**

Abre **CMD** o **PowerShell** y ejecuta:

```bash
cd C:\xampp\htdocs\plataformaeducativa
composer install
```

**Esperarás ver:**
```
Installing dependencies from lock file
  - Installing phpmailer/phpmailer (v6.x.x)
Generating optimized autoload files
```

Si no tienes Composer instalado:
1. Descargar: https://getcomposer.org/download/
2. Instalar con las opciones por defecto
3. Reiniciar terminal
4. Ejecutar `composer install`

---

### ✅ **PASO 2: Verificar que XAMPP esté corriendo**

1. Abrir **XAMPP Control Panel**
2. Iniciar **Apache** (debe estar en verde)
3. Iniciar **MySQL** (debe estar en verde)

---

### ✅ **PASO 3: Crear la Base de Datos**

**Opción A: Instalador Automático (Recomendado)**

1. Abrir navegador
2. Ir a: `http://localhost/plataformaeducativa/setup_database.php`
3. Click en **"Instalar Base de Datos"**
4. Esperar mensaje de éxito
5. **Eliminar el archivo `setup_database.php` por seguridad**

**Opción B: Manual (phpMyAdmin)**

1. Ir a: `http://localhost/phpmyadmin`
2. Click en **"Importar"**
3. Seleccionar archivo: `C:\xampp\htdocs\plataformaeducativa\database\schema.sql`
4. Click en **"Continuar"**

---

### ✅ **PASO 4: Verificar el Sistema**

Ir a: `http://localhost/plataformaeducativa/check_system.php`

**Debes ver todos los checks en verde ✓**

Si alguno está en rojo ✗, sigue las instrucciones en pantalla.

---

### ✅ **PASO 5: Acceder al Sistema**

**Login:**
```
http://localhost/plataformaeducativa/login.php
```

**Usuarios de Prueba:**

| Email | Password | Plan |
|-------|----------|------|
| admin@plataforma.com | Admin@123 | Premium (5 sesiones) |
| student@test.com | Student@123 | Basic (1 sesión) |

---

## 🔧 SOLUCIÓN DE PROBLEMAS

### ❌ Error: "Access denied for user 'root'"

**Solución:** Tu MySQL tiene contraseña. Edita `config/config.php`:

```php
define('DB_PASS', 'TU_CONTRASEÑA_MYSQL'); // Línea 17
```

---

### ❌ Error: "Table doesn't exist"

**Solución:** La base de datos no se importó correctamente.

1. Ir a: `http://localhost/plataformaeducativa/setup_database.php`
2. Reinstalar

O manualmente:
```sql
DROP DATABASE IF EXISTS plataforma_educativa;
CREATE DATABASE plataforma_educativa;
USE plataforma_educativa;
SOURCE C:/xampp/htdocs/plataformaeducativa/database/schema.sql;
```

---

### ❌ Error: "composer: command not found"

**Solución:** Composer no está instalado.

**Windows:**
1. Descargar: https://getcomposer.org/Composer-Setup.exe
2. Ejecutar instalador
3. Reiniciar terminal
4. Ejecutar `composer install`

**Verificar instalación:**
```bash
composer --version
```

---

### ❌ Error: "SMTP connect() failed"

**Solución 1 (Temporal):** Deshabilitar envío de emails

Editar `register.php` (línea 87-93), comentar:

```php
// $emailService = new EmailService();
// $emailSent = $emailService->sendVerificationEmail($email, $verificationToken);

$emailSent = true; // Forzar como enviado
```

**Solución 2 (Permanente):** Configurar Gmail App Password

1. Ir a: https://myaccount.google.com/apppasswords
2. Generar contraseña para "Correo"
3. Editar `config/config.php`:

```php
define('SMTP_USERNAME', 'tu-email@gmail.com');
define('SMTP_PASSWORD', 'xxxx xxxx xxxx xxxx'); // App Password
```

---

### ❌ Error: "Session expired" constantemente

**Solución:** Aumentar tiempo de sesión.

Editar `config/config.php` (línea 45):

```php
define('SESSION_LIFETIME', 86400); // 24 horas
```

---

## 📂 ESTRUCTURA DE ARCHIVOS (Verificar que existan)

```
plataformaeducativa/
├── ✓ vendor/                    (Creado por composer install)
├── ✓ logs/                      (Se crea automáticamente)
├── ✓ config/
│   ├── ✓ config.php
│   └── ✓ db.php
├── ✓ classes/
│   └── ✓ EmailService.php
├── ✓ includes/
│   └── ✓ auth_check.php
├── ✓ database/
│   └── ✓ schema.sql
├── ✓ index.php
├── ✓ login.php
├── ✓ register.php
├── ✓ verify.php
├── ✓ dashboard.php
├── ✓ logout.php
├── ✓ composer.json
└── ✓ check_system.php          (Nuevo - Verificador)
```

---

## 🎯 FLUJO DE INSTALACIÓN VISUAL

```
1. Descargar/Clonar proyecto
   ↓
2. Abrir terminal en carpeta del proyecto
   ↓
3. Ejecutar: composer install
   ↓
4. Iniciar XAMPP (Apache + MySQL)
   ↓
5. Abrir: http://localhost/plataformaeducativa/setup_database.php
   ↓
6. Click "Instalar Base de Datos"
   ↓
7. Abrir: http://localhost/plataformaeducativa/check_system.php
   ↓
8. Verificar que todo esté en verde ✓
   ↓
9. Acceder: http://localhost/plataformaeducativa/login.php
   ↓
10. Login con: admin@plataforma.com / Admin@123
   ↓
11. ¡Listo! 🎉
```

---

## 🧪 PROBAR LA ROTACIÓN DE SESIONES

1. Login con `student@test.com` en **Chrome**
2. Login con el mismo usuario en **Firefox**
3. Volver a **Chrome** y refrescar
4. **Resultado:** Chrome muestra "Sesión cerrada por límite"

**Verificar en BD:**

Ir a: `http://localhost/phpmyadmin`

```sql
-- Ver sesiones activas
SELECT * FROM active_sessions;

-- Ver logs de rotación
SELECT * FROM session_logs WHERE action = 'session_rotated' ORDER BY timestamp DESC;
```

---

## 📞 AYUDA ADICIONAL

**Archivos Útiles:**
- `README.md` - Documentación completa
- `INSTALL.md` - Guía de instalación detallada
- `DEFENSA_PROYECTO.md` - Guía para defensa del proyecto

**URLs de Verificación:**
- `http://localhost/plataformaeducativa/check_system.php` - Verificar sistema
- `http://localhost/phpmyadmin` - Administrar base de datos
- `http://localhost/dashboard` - Panel de XAMPP

---

## ✅ CHECKLIST FINAL

- [ ] Composer instalado (`composer --version`)
- [ ] Dependencias instaladas (`vendor/` existe)
- [ ] XAMPP corriendo (Apache + MySQL en verde)
- [ ] Base de datos creada (4+ tablas)
- [ ] `check_system.php` todo en verde ✓
- [ ] Login funciona
- [ ] Dashboard carga correctamente

---

**Si todo está en verde, ¡estás listo para usar la plataforma! 🚀**
