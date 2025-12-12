<?php
/**
 * PLATAFORMA EDUCATIVA - DASHBOARD PRINCIPAL
 *
 * Panel principal del usuario autenticado
 */

define('APP_ACCESS', true);
require_once __DIR__ . '/includes/auth_check.php'; // CRÍTICO: Middleware de autenticación
require_once __DIR__ . '/config/db.php';

// Obtener información completa del usuario
try {
    $db = Database::getInstance();

    $userQuery = "SELECT u.id, u.email, u.role, u.created_at,
                         sp.plan_name, sp.max_concurrent_sessions, sp.description,
                         COUNT(DISTINCT ases.id) as current_sessions
                  FROM users u
                  INNER JOIN subscription_plans sp ON u.subscription_plan_id = sp.id
                  LEFT JOIN active_sessions ases ON u.id = ases.user_id
                  WHERE u.id = :user_id
                  GROUP BY u.id, u.email, u.role, u.created_at, sp.plan_name, sp.max_concurrent_sessions, sp.description";

    $userInfo = $db->fetchOne($userQuery, ['user_id' => $_SESSION['user_id']]);

    // Si no se obtuvo información del usuario, salir
    if (!$userInfo) {
        throw new Exception("No se pudo obtener información del usuario");
    }

    // Obtener sesiones activas
    $sessionsQuery = "SELECT id, device_info, ip_address, last_activity, created_at
                     FROM active_sessions
                     WHERE user_id = :user_id
                     ORDER BY last_activity DESC";

    $activeSessions = $db->fetchAll($sessionsQuery, ['user_id' => $_SESSION['user_id']]);

    // Obtener logs recientes
    $logsQuery = "SELECT action, device_info, ip_address, details, timestamp
                 FROM session_logs
                 WHERE user_id = :user_id
                 ORDER BY timestamp DESC
                 LIMIT 10";

    $recentLogs = $db->fetchAll($logsQuery, ['user_id' => $_SESSION['user_id']]);

} catch (Exception $e) {
    error_log("Error al cargar dashboard: " . $e->getMessage());
    // Redirigir al login si hay un error crítico
    header('Location: login.php?error=dashboard_error');
    exit;
}

// Función helper para formatear fecha
function timeAgo($datetime) {
    $timestamp = strtotime($datetime);
    $difference = time() - $timestamp;

    if ($difference < 60) {
        return 'Hace unos segundos';
    } elseif ($difference < 3600) {
        $minutes = floor($difference / 60);
        return "Hace {$minutes} minuto" . ($minutes > 1 ? 's' : '');
    } elseif ($difference < 86400) {
        $hours = floor($difference / 3600);
        return "Hace {$hours} hora" . ($hours > 1 ? 's' : '');
    } else {
        return date('d/m/Y H:i', $timestamp);
    }
}

// Helper para badge de acción
function getActionBadge($action) {
    $badges = [
        'login' => '<span class="badge bg-success"><i class="bi bi-box-arrow-in-right"></i> Login</span>',
        'logout' => '<span class="badge bg-secondary"><i class="bi bi-box-arrow-right"></i> Logout</span>',
        'session_rotated' => '<span class="badge bg-warning"><i class="bi bi-arrow-repeat"></i> Rotación</span>',
        'session_expired' => '<span class="badge bg-info"><i class="bi bi-clock-history"></i> Expirada</span>',
        'forced_logout' => '<span class="badge bg-danger"><i class="bi bi-x-circle"></i> Forzado</span>'
    ];
    return $badges[$action] ?? '<span class="badge bg-light text-dark">' . $action . '</span>';
}

// Helper para icono de plan
function getPlanIcon($planName) {
    $icons = [
        'Basic' => 'bi-star',
        'Pro' => 'bi-star-fill',
        'Premium' => 'bi-stars'
    ];
    return $icons[$planName] ?? 'bi-star';
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard - <?php echo APP_NAME; ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css" rel="stylesheet">
    <style>
        :root {
            --primary-gradient: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        }
        body {
            background: #f5f7fa;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }
        .navbar-custom {
            background: var(--primary-gradient);
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        .card {
            border: none;
            border-radius: 15px;
            box-shadow: 0 5px 15px rgba(0,0,0,0.08);
            transition: transform 0.3s, box-shadow 0.3s;
        }
        .card:hover {
            transform: translateY(-5px);
            box-shadow: 0 8px 25px rgba(0,0,0,0.15);
        }
        .card-header {
            background: var(--primary-gradient);
            color: white;
            border-radius: 15px 15px 0 0 !important;
            font-weight: 600;
        }
        .stat-card {
            background: white;
            border-radius: 15px;
            padding: 25px;
            box-shadow: 0 5px 15px rgba(0,0,0,0.08);
            margin-bottom: 20px;
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
        .progress-custom {
            height: 10px;
            border-radius: 10px;
        }
        .session-item {
            background: #f8f9fa;
            border-left: 4px solid #667eea;
            padding: 15px;
            border-radius: 8px;
            margin-bottom: 10px;
        }
        .log-item {
            border-bottom: 1px solid #e0e0e0;
            padding: 12px 0;
        }
        .log-item:last-child {
            border-bottom: none;
        }
        .welcome-banner {
            background: var(--primary-gradient);
            color: white;
            padding: 30px;
            border-radius: 15px;
            margin-bottom: 30px;
        }
        .table-responsive {
            border-radius: 10px;
            overflow: hidden;
        }
    </style>
</head>
<body>
    <!-- Navbar -->
    <nav class="navbar navbar-expand-lg navbar-dark navbar-custom">
        <div class="container-fluid">
            <a class="navbar-brand" href="dashboard.php">
                <i class="bi bi-mortarboard-fill"></i>
                <?php echo APP_NAME; ?>
            </a>
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
                <span class="navbar-toggler-icon"></span>
            </button>
            <div class="collapse navbar-collapse" id="navbarNav">
                <ul class="navbar-nav ms-auto">
                    <li class="nav-item">
                        <a class="nav-link active" href="dashboard.php">
                            <i class="bi bi-house-door"></i> Dashboard
                        </a>
                    </li>
                    <?php if (isset($_SESSION['role']) && $_SESSION['role'] === 'admin'): ?>
                        <li class="nav-item">
                            <a class="nav-link" href="admin_dashboard.php">
                                <i class="bi bi-shield-lock-fill"></i> Panel Admin
                            </a>
                        </li>
                    <?php endif; ?>
                    <li class="nav-item dropdown">
                        <a class="nav-link dropdown-toggle" href="#" id="userDropdown" role="button"
                           data-bs-toggle="dropdown">
                            <i class="bi bi-person-circle"></i>
                            <?php echo htmlspecialchars($_SESSION['email']); ?>
                        </a>
                        <ul class="dropdown-menu dropdown-menu-end">
                            <?php if (isset($_SESSION['role']) && $_SESSION['role'] === 'admin'): ?>
                                <li>
                                    <a class="dropdown-item" href="admin_dashboard.php">
                                        <i class="bi bi-shield-lock-fill"></i> Panel de Administrador
                                    </a>
                                </li>
                                <li><hr class="dropdown-divider"></li>
                            <?php endif; ?>
                            <li>
                                <a class="dropdown-item" href="#">
                                    <i class="bi bi-gear"></i> Configuración
                                </a>
                            </li>
                            <li><hr class="dropdown-divider"></li>
                            <li>
                                <a class="dropdown-item text-danger" href="logout.php">
                                    <i class="bi bi-box-arrow-right"></i> Cerrar Sesión
                                </a>
                            </li>
                        </ul>
                    </li>
                </ul>
            </div>
        </div>
    </nav>

    <!-- Contenido Principal -->
    <div class="container my-4">
        <!-- Banner de Bienvenida -->
        <div class="welcome-banner">
            <div class="row align-items-center">
                <div class="col-md-8">
                    <h2><i class="bi bi-emoji-smile"></i> ¡Bienvenido, <?php echo htmlspecialchars($userInfo['email']); ?>!</h2>
                    <p class="mb-0">
                        <i class="<?php echo getPlanIcon($userInfo['plan_name']); ?>"></i>
                        Plan: <strong><?php echo htmlspecialchars($userInfo['plan_name']); ?></strong> |
                        <i class="bi bi-shield-check"></i>
                        Rol: <strong><?php echo ucfirst($userInfo['role']); ?></strong>
                    </p>
                </div>
                <div class="col-md-4 text-md-end">
                    <h5><i class="bi bi-calendar3"></i> <?php echo date('d/m/Y'); ?></h5>
                    <p class="mb-0" id="currentTime"></p>
                </div>
            </div>
        </div>

        <!-- Tarjetas de Estadísticas -->
        <div class="row mb-4">
            <!-- Sesiones Activas -->
            <div class="col-md-4">
                <div class="stat-card">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <h6 class="text-muted mb-1">Sesiones Activas</h6>
                            <h2 class="mb-0">
                                <?php echo $userInfo['current_sessions']; ?> / <?php echo $userInfo['max_concurrent_sessions']; ?>
                            </h2>
                        </div>
                        <div class="stat-icon bg-primary bg-opacity-10 text-primary">
                            <i class="bi bi-display"></i>
                        </div>
                    </div>
                    <div class="progress progress-custom mt-3">
                        <?php
                        $percentage = ($userInfo['current_sessions'] / $userInfo['max_concurrent_sessions']) * 100;
                        $progressColor = $percentage < 50 ? 'success' : ($percentage < 80 ? 'warning' : 'danger');
                        ?>
                        <div class="progress-bar bg-<?php echo $progressColor; ?>"
                             style="width: <?php echo $percentage; ?>%"></div>
                    </div>
                </div>
            </div>

            <!-- Plan -->
            <div class="col-md-4">
                <div class="stat-card">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <h6 class="text-muted mb-1">Tu Plan</h6>
                            <h2 class="mb-0"><?php echo htmlspecialchars($userInfo['plan_name']); ?></h2>
                        </div>
                        <div class="stat-icon bg-success bg-opacity-10 text-success">
                            <i class="<?php echo getPlanIcon($userInfo['plan_name']); ?>"></i>
                        </div>
                    </div>
                    <p class="text-muted mb-0 mt-2 small">
                        <?php echo htmlspecialchars($userInfo['description']); ?>
                    </p>
                </div>
            </div>

            <!-- Miembro desde -->
            <div class="col-md-4">
                <div class="stat-card">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <h6 class="text-muted mb-1">Miembro Desde</h6>
                            <h2 class="mb-0"><?php echo date('d/m/Y', strtotime($userInfo['created_at'])); ?></h2>
                        </div>
                        <div class="stat-icon bg-info bg-opacity-10 text-info">
                            <i class="bi bi-calendar-check"></i>
                        </div>
                    </div>
                    <p class="text-muted mb-0 mt-2 small">
                        Hace <?php echo floor((time() - strtotime($userInfo['created_at'])) / 86400); ?> días
                    </p>
                </div>
            </div>
        </div>

        <!-- Sesiones Activas -->
        <div class="row">
            <div class="col-md-7">
                <div class="card mb-4">
                    <div class="card-header">
                        <i class="bi bi-laptop"></i> Dispositivos Conectados
                    </div>
                    <div class="card-body">
                        <?php if (empty($activeSessions)): ?>
                            <p class="text-muted text-center py-4">No hay sesiones activas</p>
                        <?php else: ?>
                            <?php foreach ($activeSessions as $session): ?>
                                <div class="session-item">
                                    <div class="row align-items-center">
                                        <div class="col-md-8">
                                            <h6 class="mb-1">
                                                <i class="bi bi-phone"></i>
                                                <?php
                                                $ua = $session['device_info'];
                                                if (stripos($ua, 'mobile') !== false) echo 'Móvil';
                                                elseif (stripos($ua, 'tablet') !== false) echo 'Tablet';
                                                else echo 'Escritorio';
                                                ?>
                                                <?php if ($session['id'] == $_SESSION['session_token']): ?>
                                                    <span class="badge bg-success">Este dispositivo</span>
                                                <?php endif; ?>
                                            </h6>
                                            <small class="text-muted d-block">
                                                <i class="bi bi-geo-alt"></i> IP: <?php echo htmlspecialchars($session['ip_address']); ?>
                                            </small>
                                            <small class="text-muted">
                                                <i class="bi bi-info-circle"></i>
                                                <?php echo htmlspecialchars(substr($session['device_info'], 0, 60)); ?>...
                                            </small>
                                        </div>
                                        <div class="col-md-4 text-md-end">
                                            <small class="text-muted d-block">Última actividad:</small>
                                            <strong><?php echo timeAgo($session['last_activity']); ?></strong>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <!-- Historial de Actividad -->
            <div class="col-md-5">
                <div class="card mb-4">
                    <div class="card-header">
                        <i class="bi bi-clock-history"></i> Actividad Reciente
                    </div>
                    <div class="card-body" style="max-height: 500px; overflow-y: auto;">
                        <?php if (empty($recentLogs)): ?>
                            <p class="text-muted text-center py-4">No hay actividad registrada</p>
                        <?php else: ?>
                            <?php foreach ($recentLogs as $log): ?>
                                <div class="log-item">
                                    <div class="d-flex justify-content-between align-items-start">
                                        <div>
                                            <?php echo getActionBadge($log['action']); ?>
                                            <small class="text-muted d-block mt-1">
                                                <i class="bi bi-geo-alt"></i> <?php echo htmlspecialchars($log['ip_address']); ?>
                                            </small>
                                            <?php if ($log['details']): ?>
                                                <small class="text-muted d-block">
                                                    <?php echo htmlspecialchars($log['details']); ?>
                                                </small>
                                            <?php endif; ?>
                                        </div>
                                        <small class="text-nowrap text-muted">
                                            <?php echo timeAgo($log['timestamp']); ?>
                                        </small>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>

        <!-- Información del Sistema -->
        <div class="row">
            <div class="col-12">
                <div class="card">
                    <div class="card-header">
                        <i class="bi bi-info-circle"></i> Información del Sistema
                    </div>
                    <div class="card-body">
                        <div class="row">
                            <div class="col-md-6">
                                <h6>Límites de tu Plan <?php echo htmlspecialchars($userInfo['plan_name']); ?>:</h6>
                                <ul class="list-unstyled">
                                    <li>
                                        <i class="bi bi-check-circle-fill text-success"></i>
                                        Máximo <strong><?php echo $userInfo['max_concurrent_sessions']; ?></strong> sesiones simultáneas
                                    </li>
                                    <li>
                                        <i class="bi bi-check-circle-fill text-success"></i>
                                        Rotación automática de sesiones
                                    </li>
                                    <li>
                                        <i class="bi bi-check-circle-fill text-success"></i>
                                        Auditoría completa de actividad
                                    </li>
                                </ul>
                            </div>
                            <div class="col-md-6">
                                <h6>¿Qué es la Rotación Automática?</h6>
                                <p class="text-muted small">
                                    Cuando alcanzas el límite de sesiones permitidas, el sistema cierra
                                    automáticamente la sesión más antigua para permitir el nuevo acceso.
                                    Esto garantiza que siempre puedas acceder desde tus dispositivos más recientes.
                                </p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Reloj en tiempo real
        function updateTime() {
            const now = new Date();
            const timeString = now.toLocaleTimeString('es-ES');
            document.getElementById('currentTime').textContent = timeString;
        }
        setInterval(updateTime, 1000);
        updateTime();
    </script>
</body>
</html>
