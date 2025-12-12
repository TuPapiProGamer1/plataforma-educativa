# 🛡️ GUÍA DEL PANEL DE ADMINISTRADOR

## 📋 CARACTERÍSTICAS IMPLEMENTADAS

### ✅ **1. Panel de Administrador Exclusivo**
- **URL:** `http://localhost/plataformaeducativa/admin_dashboard.php`
- **Acceso:** Solo usuarios con `role = 'admin'`
- **Redirección automática:** Si un estudiante intenta acceder, es redirigido al dashboard normal

---

## 🔐 CONTROL DE ACCESO

### **Verificación de Rol (admin_dashboard.php:12-15):**

```php
// VERIFICAR QUE EL USUARIO SEA ADMIN
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    header('Location: dashboard.php?error=access_denied');
    exit;
}
```

**Resultado:**
- ✅ Admin → Accede al panel
- ❌ Student → Redirigido a `dashboard.php`
- ❌ No autenticado → Redirigido a `login.php` (por `auth_check.php`)

---

## 🎨 FUNCIONALIDADES DEL CRUD

### **1. VER USUARIOS (Read)**

**Tabla con información completa:**
- ID del usuario
- Email
- Rol (Admin/Student)
- Plan de suscripción (Basic/Pro/Premium)
- Sesiones activas vs límite
- Estado de verificación (Sí/No)
- Fecha de registro
- Acciones disponibles

**Estadísticas en tiempo real:**
- Total de usuarios
- Total de administradores
- Total de estudiantes
- Usuarios verificados
- Sesiones activas en el sistema

---

### **2. CREAR USUARIOS (Create)**

**Modal "Nuevo Usuario":**
- Email
- Contraseña (mínimo 8 caracteres)
- Rol (Admin/Student)
- Plan de suscripción
- **Verificado automáticamente** (se crea con `is_verified = 1`)

**Código (admin_dashboard.php:67-97):**
```php
$insertQuery = "INSERT INTO users (email, password_hash, role, subscription_plan_id, is_verified)
               VALUES (:email, :password_hash, :role, :plan_id, 1)";
```

---

### **3. EDITAR USUARIOS (Update)**

**Modal "Editar Usuario":**
- Cambiar plan de suscripción (Basic/Pro/Premium)
- Activar/Desactivar verificación
- Email (solo lectura, no editable)

**Funciones:**
- ✅ Cambiar de Basic a Premium
- ✅ Verificar usuario manualmente
- ✅ Bloquear acceso (quitar verificación)

**Código (admin_dashboard.php:38-56):**
```php
$updateQuery = "UPDATE users
               SET subscription_plan_id = :plan_id,
                   is_verified = :verified
               WHERE id = :id";
```

---

### **4. ELIMINAR USUARIOS (Delete)**

**Confirmación de seguridad:**
- Alerta de confirmación antes de eliminar
- **No se puede eliminar a sí mismo** (protección)

**Eliminación en cascada:**
- ✅ Usuario eliminado
- ✅ Sesiones activas eliminadas (FK CASCADE)
- ✅ Logs de sesión eliminados (FK CASCADE)

**Código (admin_dashboard.php:20-36):**
```php
// No permitir eliminar al admin actual
if ($userId == $_SESSION['user_id']) {
    throw new Exception("No puedes eliminarte a ti mismo.");
}

$db->query("DELETE FROM users WHERE id = :id", ['id' => $userId]);
```

---

### **5. CERRAR SESIONES (Extra)**

**Botón "Cerrar sesiones":**
- Aparece solo si el usuario tiene sesiones activas
- Cierra TODAS las sesiones del usuario
- Registra evento en `session_logs`

**Uso:**
- ✅ Usuario bloqueado → Cerrar sesiones
- ✅ Comportamiento sospechoso → Forzar logout
- ✅ Cambio de plan → Reiniciar sesiones

**Código (admin_dashboard.php:99-118):**
```php
// Eliminar todas las sesiones activas
$db->query("DELETE FROM active_sessions WHERE user_id = :id", ['id' => $userId]);

// Registrar evento
$db->query(
    "INSERT INTO session_logs (user_id, action, details)
     VALUES (:id, 'forced_logout', 'Sesiones cerradas por administrador')"
);
```

---

## 🚀 CÓMO ACCEDER AL PANEL DE ADMIN

### **Opción 1: Desde el Dashboard Normal**

Si eres admin, verás un botón en el navbar:

```
Dashboard → Panel Admin (botón en navbar)
```

### **Opción 2: URL Directa**

```
http://localhost/plataformaeducativa/admin_dashboard.php
```

### **Opción 3: Dropdown del Usuario**

Menú desplegable del usuario → "Panel de Administrador"

---

## 👥 USUARIOS DE PRUEBA

### **Usuario Admin (Acceso completo):**
```
Email:    admin@plataforma.com
Password: Admin@123
Acceso:   ✅ Dashboard normal
          ✅ Panel de administrador
```

### **Usuario Estudiante (Sin acceso):**
```
Email:    student@test.com
Password: Student@123
Acceso:   ✅ Dashboard normal
          ❌ Panel de administrador (redirigido)
```

---

## 📊 CASOS DE USO

### **Caso 1: Cambiar Plan de un Usuario**

1. Ir a Panel Admin
2. Buscar usuario en la tabla
3. Click en botón **"Editar"** (lápiz)
4. Seleccionar nuevo plan (Basic/Pro/Premium)
5. Click en **"Guardar Cambios"**

**Resultado:** El usuario puede abrir más/menos sesiones según el nuevo plan.

---

### **Caso 2: Bloquear Acceso de un Usuario**

**Opción A: Quitar Verificación**
1. Click en **"Editar"**
2. Desmarcar "Usuario Verificado"
3. Guardar

**Opción B: Cerrar Sesiones**
1. Click en botón **"Cerrar sesiones"** (puerta)
2. Confirmar

**Resultado:** El usuario no puede hacer login (no verificado) o es expulsado (sesiones cerradas).

---

### **Caso 3: Crear Admin Adicional**

1. Click en **"Nuevo Usuario"**
2. Ingresar email y contraseña
3. Seleccionar Rol: **Administrador**
4. Seleccionar Plan: **Premium** (recomendado)
5. Click en **"Crear Usuario"**

**Resultado:** Nuevo administrador con acceso completo.

---

### **Caso 4: Eliminar Usuario Inactivo**

1. Buscar usuario en la tabla
2. Click en botón **"Eliminar"** (basura roja)
3. Confirmar la acción

**Resultado:** Usuario y sus datos eliminados permanentemente.

---

## 🛡️ SEGURIDAD IMPLEMENTADA

### **1. Protección contra Auto-Eliminación:**
```php
if ($userId == $_SESSION['user_id']) {
    throw new Exception("No puedes eliminarte a ti mismo.");
}
```

### **2. Validación de Rol en Cada Request:**
```php
if ($_SESSION['role'] !== 'admin') {
    header('Location: dashboard.php?error=access_denied');
}
```

### **3. Prepared Statements (Anti SQL Injection):**
```php
$db->query("DELETE FROM users WHERE id = :id", ['id' => $userId]);
```

### **4. Confirmación de Acciones Críticas:**
```javascript
onsubmit="return confirm('¿Estás seguro de eliminar este usuario?')"
```

---

## 📁 ARCHIVOS CREADOS/MODIFICADOS

| Archivo | Estado | Descripción |
|---------|--------|-------------|
| `admin_dashboard.php` | ⭐ **NUEVO** | Panel completo de administrador con CRUD |
| `dashboard.php` | ✅ **MODIFICADO** | Agregado botón "Panel Admin" (solo para admins) |
| `EmailService.php` | ✅ **MODIFICADO** | Configuración SSL para XAMPP |
| `CONFIGURACION_EMAIL_XAMPP.md` | ⭐ **NUEVO** | Guía completa de configuración SMTP |
| `GUIA_PANEL_ADMIN.md` | ⭐ **NUEVO** | Esta guía |

---

## ✅ CHECKLIST DE FUNCIONALIDADES

- [x] Panel exclusivo para administradores
- [x] Redirección automática de estudiantes
- [x] Ver lista de usuarios con detalles completos
- [x] Crear nuevos usuarios (Admin/Student)
- [x] Editar plan de suscripción
- [x] Editar estado de verificación
- [x] Eliminar usuarios (con protección anti auto-eliminación)
- [x] Cerrar sesiones activas de usuarios
- [x] Estadísticas en tiempo real
- [x] Diseño responsive con Bootstrap 5
- [x] Confirmaciones de seguridad
- [x] Registro de eventos en session_logs

---

## 🎯 MEJORAS FUTURAS (OPCIONALES)

- [ ] Búsqueda de usuarios por email
- [ ] Filtros por rol/plan/verificación
- [ ] Paginación de tabla (si hay muchos usuarios)
- [ ] Exportar lista de usuarios a CSV/Excel
- [ ] Enviar email masivo a todos los usuarios
- [ ] Gráficas de actividad (Chart.js)
- [ ] Logs de acciones del admin (auditoría)
- [ ] Cambio de contraseña de usuarios
- [ ] Suspender cuenta (sin eliminar)

---

## 🧪 PRUEBAS RECOMENDADAS

### **Test 1: Acceso como Admin**
```
Login: admin@plataforma.com / Admin@123
Verificar: Puede acceder a admin_dashboard.php ✓
```

### **Test 2: Acceso como Student**
```
Login: student@test.com / Student@123
Acceder a: http://localhost/plataformaeducativa/admin_dashboard.php
Resultado esperado: Redirigido a dashboard.php ✓
```

### **Test 3: CRUD Completo**
```
1. Crear nuevo usuario
2. Editar su plan (Basic → Pro)
3. Cerrar sus sesiones
4. Eliminar el usuario
```

### **Test 4: Protección Anti Auto-Eliminación**
```
1. Login como admin@plataforma.com
2. Intentar eliminar a admin@plataforma.com
3. Resultado: Error "No puedes eliminarte a ti mismo" ✓
```

---

## 📞 SOPORTE

**Archivos de referencia:**
- `admin_dashboard.php` - Código completo del panel
- `CONFIGURACION_EMAIL_XAMPP.md` - Configuración de emails
- `README.md` - Documentación general del proyecto

---

**¡Panel de Administrador completamente funcional! 🚀**
