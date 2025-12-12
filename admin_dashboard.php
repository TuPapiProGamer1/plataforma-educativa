<?php
/**
 * PLATAFORMA EDUCATIVA - PANEL DE ADMINISTRADOR
 *
 * Dashboard exclusivo para administradores con CRUD de usuarios
 */

define('APP_ACCESS', true);
require_once __DIR__ . '/includes/auth_check.php';
require_once __DIR__ . '/config/db.php';

// VERIFICAR QUE EL USUARIO SEA ADMIN
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    header('Location: dashboard.php?error=access_denied');
    exit;
}

$db = Database::getInstance();
$success = [];
$errors = [];

// ============================================
// PROCESAR ACCIONES DEL CRUD
// ============================================

// ELIMINAR USUARIO
if (isset($_POST['action']) && $_POST['action'] === 'delete' && isset($_POST['user_id'])) {
    try {
        $userId = filter_input(INPUT_POST, 'user_id', FILTER_VALIDATE_INT);

        // No permitir eliminar al admin actual
        if ($userId == $_SESSION['user_id']) {
            throw new Exception("No puedes eliminarte a ti mismo.");
        }

        $db->query("DELETE FROM users WHERE id = :id", ['id' => $userId]);
        $success[] = "Usuario eliminado correctamente.";

    } catch (Exception $e) {
        $errors[] = "Error al eliminar usuario: " . $e->getMessage();
    }
}

// ACTUALIZAR USUARIO (Plan, Estado, Verificación)
if (isset($_POST['action']) && $_POST['action'] === 'update' && isset($_POST['user_id'])) {
    try {
        $userId = filter_input(INPUT_POST, 'user_id', FILTER_VALIDATE_INT);
        $planId = filter_input(INPUT_POST, 'plan_id', FILTER_VALIDATE_INT);
        $isVerified = isset($_POST['is_verified']) ? 1 : 0;

        $updateQuery = "UPDATE users
                       SET subscription_plan_id = :plan_id,
                           is_verified = :verified
                       WHERE id = :id";

        $db->query($updateQuery, [
            'plan_id' => $planId,
            'verified' => $isVerified,
            'id' => $userId
        ]);

        $success[] = "Usuario actualizado correctamente.";

    } catch (Exception $e) {
        $errors[] = "Error al actualizar usuario: " . $e->getMessage();
    }
}

// CREAR NUEVO USUARIO (Opcional)
if (isset($_POST['action']) && $_POST['action'] === 'create') {
    try {
        $email = filter_input(INPUT_POST, 'email', FILTER_SANITIZE_EMAIL);
        $password = $_POST['password'] ?? '';
        $role = $_POST['role'] ?? 'student';
        $planId = filter_input(INPUT_POST, 'plan_id', FILTER_VALIDATE_INT);

        if (empty($email) || empty($password)) {
            throw new Exception("Email y contraseña son requeridos.");
        }

        // Verificar que el email no exista
        $existing = $db->fetchOne("SELECT id FROM users WHERE email = :email", ['email' => $email]);
        if ($existing) {
            throw new Exception("Este email ya está registrado.");
        }

        $passwordHash = password_hash($password, PASSWORD_BCRYPT);

        $insertQuery = "INSERT INTO users (email, password_hash, role, subscription_plan_id, is_verified)
                       VALUES (:email, :password_hash, :role, :plan_id, 1)";

        $db->query($insertQuery, [
            'email' => $email,
            'password_hash' => $passwordHash,
            'role' => $role,
            'plan_id' => $planId
        ]);

        $success[] = "Usuario creado correctamente.";

    } catch (Exception $e) {
        $errors[] = "Error al crear usuario: " . $e->getMessage();
    }
}

// CERRAR TODAS LAS SESIONES DE UN USUARIO
if (isset($_POST['action']) && $_POST['action'] === 'logout_user' && isset($_POST['user_id'])) {
    try {
        $userId = filter_input(INPUT_POST, 'user_id', FILTER_VALIDATE_INT);

        // Eliminar todas las sesiones activas
        $db->query("DELETE FROM active_sessions WHERE user_id = :id", ['id' => $userId]);

        // Registrar evento
        $db->query(
            "INSERT INTO session_logs (user_id, action, device_info, details)
             VALUES (:id, 'forced_logout', 'Admin action', 'Sesiones cerradas por administrador')",
            ['id' => $userId]
        );

        $success[] = "Todas las sesiones del usuario han sido cerradas.";

    } catch (Exception $e) {
        $errors[] = "Error: " . $e->getMessage();
    }
}

// ============================================
// OBTENER DATOS
// ============================================

// Obtener lista de usuarios con información del plan
$usersQuery = "SELECT u.id, u.email, u.role, u.is_verified, u.created_at,
                      sp.plan_name, sp.max_concurrent_sessions,
                      COUNT(DISTINCT ases.id) as active_sessions
               FROM users u
               INNER JOIN subscription_plans sp ON u.subscription_plan_id = sp.id
               LEFT JOIN active_sessions ases ON u.id = ases.user_id
               GROUP BY u.id, u.email, u.role, u.is_verified, u.created_at,
                        sp.plan_name, sp.max_concurrent_sessions
               ORDER BY u.created_at DESC";

$users = $db->fetchAll($usersQuery);

// Obtener planes disponibles
$plans = $db->fetchAll("SELECT id, plan_name, max_concurrent_sessions FROM subscription_plans ORDER BY id");

// Estadísticas generales
$stats = [
    'total_users' => $db->fetchOne("SELECT COUNT(*) as total FROM users")['total'],
    'total_admins' => $db->fetchOne("SELECT COUNT(*) as total FROM users WHERE role = 'admin'")['total'],
    'total_students' => $db->fetchOne("SELECT COUNT(*) as total FROM users WHERE role = 'student'")['total'],
    'verified_users' => $db->fetchOne("SELECT COUNT(*) as total FROM users WHERE is_verified = 1")['total'],
    'active_sessions' => $db->fetchOne("SELECT COUNT(*) as total FROM active_sessions")['total']
];
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Panel de Administrador - <?php echo APP_NAME; ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css" rel="stylesheet">
    <style>
        :root {
            --admin-primary: #dc3545;
            --admin-gradient: linear-gradient(135deg, #dc3545 0%, #c92333 100%);
        }
        body {
            background: #f5f7fa;
        }
        .navbar-admin {
            background: var(--admin-gradient);
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        .stat-card {
            background: white;
            border-radius: 15px;
            padding: 25px;
            box-shadow: 0 5px 15px rgba(0,0,0,0.08);
            margin-bottom: 20px;
            border-left: 4px solid var(--admin-primary);
        }
        .stat-icon {
            width: 60px;
            height: 60px;
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 24px;
        }
        .table-actions button {
            padding: 5px 10px;
            font-size: 12px;
        }
        .badge-admin {
            background: #dc3545;
        }
        .badge-student {
            background: #0d6efd;
        }
        .modal-header.admin-modal {
            background: var(--admin-gradient);
            color: white;
        }
    </style>
</head>
<body>
    <!-- Navbar -->
    <nav class="navbar navbar-expand-lg navbar-dark navbar-admin">
        <div class="container-fluid">
            <a class="navbar-brand" href="admin_dashboard.php">
                <i class="bi bi-shield-lock-fill"></i>
                Panel de Administrador
            </a>
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
                <span class="navbar-toggler-icon"></span>
            </button>
            <div class="collapse navbar-collapse" id="navbarNav">
                <ul class="navbar-nav ms-auto">
                    <li class="nav-item">
                        <a class="nav-link" href="dashboard.php">
                            <i class="bi bi-house-door"></i> Dashboard Normal
                        </a>
                    </li>
                    <li class="nav-item dropdown">
                        <a class="nav-link dropdown-toggle" href="#" id="userDropdown" role="button"
                           data-bs-toggle="dropdown">
                            <i class="bi bi-person-circle"></i>
                            <?php echo htmlspecialchars($_SESSION['email']); ?>
                        </a>
                        <ul class="dropdown-menu dropdown-menu-end">
                            <li><a class="dropdown-item text-danger" href="logout.php">
                                <i class="bi bi-box-arrow-right"></i> Cerrar Sesión
                            </a></li>
                        </ul>
                    </li>
                </ul>
            </div>
        </div>
    </nav>

    <div class="container my-4">
        <!-- Alertas -->
        <?php if (!empty($success)): ?>
            <div class="alert alert-success alert-dismissible fade show">
                <strong><i class="bi bi-check-circle"></i> Éxito:</strong>
                <ul class="mb-0">
                    <?php foreach ($success as $msg): ?>
                        <li><?php echo htmlspecialchars($msg); ?></li>
                    <?php endforeach; ?>
                </ul>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>

        <?php if (!empty($errors)): ?>
            <div class="alert alert-danger alert-dismissible fade show">
                <strong><i class="bi bi-exclamation-triangle"></i> Error:</strong>
                <ul class="mb-0">
                    <?php foreach ($errors as $msg): ?>
                        <li><?php echo htmlspecialchars($msg); ?></li>
                    <?php endforeach; ?>
                </ul>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>

        <!-- Estadísticas -->
        <div class="row mb-4">
            <div class="col-md-3">
                <div class="stat-card">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <h6 class="text-muted mb-1">Total Usuarios</h6>
                            <h2 class="mb-0"><?php echo $stats['total_users']; ?></h2>
                        </div>
                        <div class="stat-icon bg-primary bg-opacity-10 text-primary">
                            <i class="bi bi-people"></i>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="stat-card">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <h6 class="text-muted mb-1">Administradores</h6>
                            <h2 class="mb-0"><?php echo $stats['total_admins']; ?></h2>
                        </div>
                        <div class="stat-icon bg-danger bg-opacity-10 text-danger">
                            <i class="bi bi-shield-fill-check"></i>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="stat-card">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <h6 class="text-muted mb-1">Verificados</h6>
                            <h2 class="mb-0"><?php echo $stats['verified_users']; ?></h2>
                        </div>
                        <div class="stat-icon bg-success bg-opacity-10 text-success">
                            <i class="bi bi-check-circle-fill"></i>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="stat-card">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <h6 class="text-muted mb-1">Sesiones Activas</h6>
                            <h2 class="mb-0"><?php echo $stats['active_sessions']; ?></h2>
                        </div>
                        <div class="stat-icon bg-info bg-opacity-10 text-info">
                            <i class="bi bi-display"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Tabla de Usuarios -->
        <div class="card">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h5 class="mb-0"><i class="bi bi-table"></i> Gestión de Usuarios</h5>
                <button class="btn btn-success btn-sm" data-bs-toggle="modal" data-bs-target="#createUserModal">
                    <i class="bi bi-plus-circle"></i> Nuevo Usuario
                </button>
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-hover">
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>Email</th>
                                <th>Rol</th>
                                <th>Plan</th>
                                <th>Sesiones</th>
                                <th>Verificado</th>
                                <th>Registro</th>
                                <th>Acciones</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($users as $user): ?>
                                <tr>
                                    <td><?php echo $user['id']; ?></td>
                                    <td><?php echo htmlspecialchars($user['email']); ?></td>
                                    <td>
                                        <span class="badge <?php echo $user['role'] === 'admin' ? 'badge-admin' : 'badge-student'; ?>">
                                            <?php echo ucfirst($user['role']); ?>
                                        </span>
                                    </td>
                                    <td><?php echo htmlspecialchars($user['plan_name']); ?></td>
                                    <td>
                                        <span class="badge bg-info">
                                            <?php echo $user['active_sessions']; ?>/<?php echo $user['max_concurrent_sessions']; ?>
                                        </span>
                                    </td>
                                    <td>
                                        <?php if ($user['is_verified']): ?>
                                            <span class="badge bg-success">Sí</span>
                                        <?php else: ?>
                                            <span class="badge bg-warning">No</span>
                                        <?php endif; ?>
                                    </td>
                                    <td><?php echo date('d/m/Y', strtotime($user['created_at'])); ?></td>
                                    <td class="table-actions">
                                        <button class="btn btn-primary btn-sm"
                                                onclick="editUser(<?php echo htmlspecialchars(json_encode($user)); ?>)">
                                            <i class="bi bi-pencil"></i>
                                        </button>

                                        <?php if ($user['active_sessions'] > 0): ?>
                                            <form method="POST" class="d-inline"
                                                  onsubmit="return confirm('¿Cerrar todas las sesiones de este usuario?')">
                                                <input type="hidden" name="action" value="logout_user">
                                                <input type="hidden" name="user_id" value="<?php echo $user['id']; ?>">
                                                <button type="submit" class="btn btn-warning btn-sm" title="Cerrar sesiones">
                                                    <i class="bi bi-door-closed"></i>
                                                </button>
                                            </form>
                                        <?php endif; ?>

                                        <?php if ($user['id'] != $_SESSION['user_id']): ?>
                                            <form method="POST" class="d-inline"
                                                  onsubmit="return confirm('¿Estás seguro de eliminar este usuario?')">
                                                <input type="hidden" name="action" value="delete">
                                                <input type="hidden" name="user_id" value="<?php echo $user['id']; ?>">
                                                <button type="submit" class="btn btn-danger btn-sm">
                                                    <i class="bi bi-trash"></i>
                                                </button>
                                            </form>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <!-- Modal: Editar Usuario -->
    <div class="modal fade" id="editUserModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header admin-modal">
                    <h5 class="modal-title"><i class="bi bi-pencil"></i> Editar Usuario</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <form method="POST">
                    <div class="modal-body">
                        <input type="hidden" name="action" value="update">
                        <input type="hidden" name="user_id" id="edit_user_id">

                        <div class="mb-3">
                            <label class="form-label">Email</label>
                            <input type="text" class="form-control" id="edit_email" readonly>
                        </div>

                        <div class="mb-3">
                            <label class="form-label">Plan de Suscripción</label>
                            <select name="plan_id" id="edit_plan_id" class="form-select" required>
                                <?php foreach ($plans as $plan): ?>
                                    <option value="<?php echo $plan['id']; ?>">
                                        <?php echo htmlspecialchars($plan['plan_name']); ?>
                                        (<?php echo $plan['max_concurrent_sessions']; ?> sesiones)
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="mb-3 form-check">
                            <input type="checkbox" class="form-check-input" name="is_verified" id="edit_verified">
                            <label class="form-check-label" for="edit_verified">
                                Usuario Verificado
                            </label>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                        <button type="submit" class="btn btn-primary">Guardar Cambios</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Modal: Crear Usuario -->
    <div class="modal fade" id="createUserModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header admin-modal">
                    <h5 class="modal-title"><i class="bi bi-plus-circle"></i> Crear Nuevo Usuario</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <form method="POST">
                    <div class="modal-body">
                        <input type="hidden" name="action" value="create">

                        <div class="mb-3">
                            <label class="form-label">Email</label>
                            <input type="email" class="form-control" name="email" required>
                        </div>

                        <div class="mb-3">
                            <label class="form-label">Contraseña</label>
                            <input type="password" class="form-control" name="password" required minlength="8">
                            <small class="text-muted">Mínimo 8 caracteres</small>
                        </div>

                        <div class="mb-3">
                            <label class="form-label">Rol</label>
                            <select name="role" class="form-select" required>
                                <option value="student">Estudiante</option>
                                <option value="admin">Administrador</option>
                            </select>
                        </div>

                        <div class="mb-3">
                            <label class="form-label">Plan de Suscripción</label>
                            <select name="plan_id" class="form-select" required>
                                <?php foreach ($plans as $plan): ?>
                                    <option value="<?php echo $plan['id']; ?>">
                                        <?php echo htmlspecialchars($plan['plan_name']); ?>
                                        (<?php echo $plan['max_concurrent_sessions']; ?> sesiones)
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="alert alert-info">
                            <small><i class="bi bi-info-circle"></i> El usuario se creará como verificado automáticamente.</small>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                        <button type="submit" class="btn btn-success">Crear Usuario</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        function editUser(user) {
            document.getElementById('edit_user_id').value = user.id;
            document.getElementById('edit_email').value = user.email;
            document.getElementById('edit_plan_id').value = user.plan_name === 'Basic' ? 1 : (user.plan_name === 'Pro' ? 2 : 3);
            document.getElementById('edit_verified').checked = user.is_verified == 1;

            const modal = new bootstrap.Modal(document.getElementById('editUserModal'));
            modal.show();
        }
    </script>
</body>
</html>
