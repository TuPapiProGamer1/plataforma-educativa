<?php
/**
 * =============================================
 * MÓDULO DE GESTIÓN DE ROLES
 * Plataforma Educativa - Panel Administrativo
 * =============================================
 */

// Incluir archivo de conexión a la base de datos
require_once 'db.php';

// Iniciar sesión si no está iniciada
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// ====== SEGURIDAD CRÍTICA ======
// Verificar que la sesión esté activa
if (!isset($_SESSION['usuario_id']) || !isset($_SESSION['rol'])) {
    header("Location: login.php");
    exit();
}

// Verificar estrictamente que el rol sea 'Admin'
if ($_SESSION['rol'] !== 'Admin') {
    // Si no es Admin, redirigir al inicio
    header("Location: index.php");
    exit();
}

// Variables para mensajes de retroalimentación
$mensaje = '';
$tipo_alerta = '';

// ====== PROCESAMIENTO DE ACCIONES ======

// ACCIÓN: CREAR NUEVO ROL
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['accion']) && $_POST['accion'] === 'crear') {
    $nombre_rol = trim($_POST['nombre_rol']);
    $descripcion = trim($_POST['descripcion']);

    // Validar que el nombre no esté vacío
    if (empty($nombre_rol)) {
        $mensaje = "El nombre del rol es obligatorio.";
        $tipo_alerta = "danger";
    } else {
        // Verificar que no exista un rol con el mismo nombre
        $stmt = $conn->prepare("SELECT id_rol FROM roles WHERE nombre_rol = ?");
        $stmt->bind_param("s", $nombre_rol);
        $stmt->execute();
        $resultado = $stmt->get_result();

        if ($resultado->num_rows > 0) {
            $mensaje = "Ya existe un rol con el nombre '{$nombre_rol}'.";
            $tipo_alerta = "warning";
        } else {
            // Insertar el nuevo rol
            $stmt = $conn->prepare("INSERT INTO roles (nombre_rol, descripcion) VALUES (?, ?)");
            $stmt->bind_param("ss", $nombre_rol, $descripcion);

            if ($stmt->execute()) {
                $mensaje = "Rol '{$nombre_rol}' creado exitosamente.";
                $tipo_alerta = "success";
            } else {
                $mensaje = "Error al crear el rol: " . $conn->error;
                $tipo_alerta = "danger";
            }
        }
        $stmt->close();
    }
}

// ACCIÓN: EDITAR ROL EXISTENTE
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['accion']) && $_POST['accion'] === 'editar') {
    $id_rol = intval($_POST['id_rol']);
    $nombre_rol = trim($_POST['nombre_rol']);
    $descripcion = trim($_POST['descripcion']);

    // Validar que el nombre no esté vacío
    if (empty($nombre_rol)) {
        $mensaje = "El nombre del rol es obligatorio.";
        $tipo_alerta = "danger";
    } else {
        // Verificar que no exista otro rol con el mismo nombre
        $stmt = $conn->prepare("SELECT id_rol FROM roles WHERE nombre_rol = ? AND id_rol != ?");
        $stmt->bind_param("si", $nombre_rol, $id_rol);
        $stmt->execute();
        $resultado = $stmt->get_result();

        if ($resultado->num_rows > 0) {
            $mensaje = "Ya existe otro rol con el nombre '{$nombre_rol}'.";
            $tipo_alerta = "warning";
        } else {
            // Actualizar el rol
            $stmt = $conn->prepare("UPDATE roles SET nombre_rol = ?, descripcion = ? WHERE id_rol = ?");
            $stmt->bind_param("ssi", $nombre_rol, $descripcion, $id_rol);

            if ($stmt->execute()) {
                $mensaje = "Rol actualizado exitosamente.";
                $tipo_alerta = "success";
            } else {
                $mensaje = "Error al actualizar el rol: " . $conn->error;
                $tipo_alerta = "danger";
            }
        }
        $stmt->close();
    }
}

// ACCIÓN: ELIMINAR ROL
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['accion']) && $_POST['accion'] === 'eliminar') {
    $id_rol = intval($_POST['id_rol']);

    // VALIDACIÓN CRÍTICA: Verificar si hay usuarios asignados a este rol
    $stmt = $conn->prepare("SELECT COUNT(*) as total_usuarios FROM usuarios WHERE id_rol = ?");
    $stmt->bind_param("i", $id_rol);
    $stmt->execute();
    $resultado = $stmt->get_result();
    $datos = $resultado->fetch_assoc();
    $total_usuarios = $datos['total_usuarios'];

    if ($total_usuarios > 0) {
        // No permitir eliminación si hay usuarios asignados
        $mensaje = "No se puede eliminar este rol porque tiene {$total_usuarios} usuario(s) asignado(s). Primero reasigne o elimine los usuarios.";
        $tipo_alerta = "danger";
    } else {
        // Proceder con la eliminación
        $stmt = $conn->prepare("DELETE FROM roles WHERE id_rol = ?");
        $stmt->bind_param("i", $id_rol);

        if ($stmt->execute()) {
            $mensaje = "Rol eliminado exitosamente.";
            $tipo_alerta = "success";
        } else {
            $mensaje = "Error al eliminar el rol: " . $conn->error;
            $tipo_alerta = "danger";
        }
    }
    $stmt->close();
}

// ====== CONSULTA PRINCIPAL: OBTENER ROLES CON USUARIOS ACTIVOS ======
$query = "
    SELECT
        r.id_rol,
        r.nombre_rol,
        r.descripcion,
        COUNT(u.id_usuario) AS usuarios_activos
    FROM roles r
    LEFT JOIN usuarios u ON r.id_rol = u.id_rol
    GROUP BY r.id_rol, r.nombre_rol, r.descripcion
    ORDER BY r.id_rol ASC
";
$resultado_roles = $conn->query($query);
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gestión de Roles | Panel Administrativo</title>

    <!-- Bootstrap 5 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Bootstrap Icons -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">

    <style>
        body {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            padding: 20px 0;
        }
        .main-card {
            border-radius: 15px;
            box-shadow: 0 10px 30px rgba(0,0,0,0.2);
        }
        .table-container {
            background: white;
            border-radius: 10px;
            overflow: hidden;
        }
        .btn-action {
            margin: 2px;
        }
        .badge-usuarios {
            font-size: 0.9rem;
            padding: 6px 12px;
        }
    </style>
</head>
<body>
    <div class="container">
        <!-- Encabezado -->
        <div class="row mb-4">
            <div class="col-12">
                <div class="card main-card">
                    <div class="card-header bg-primary text-white py-3">
                        <div class="d-flex justify-content-between align-items-center">
                            <h3 class="mb-0">
                                <i class="bi bi-shield-lock-fill"></i> Gestión de Roles
                            </h3>
                            <div>
                                <button type="button" class="btn btn-light" data-bs-toggle="modal" data-bs-target="#modalNuevoRol">
                                    <i class="bi bi-plus-circle-fill"></i> Nuevo Rol
                                </button>
                                <a href="admin_dashboard.php" class="btn btn-outline-light ms-2">
                                    <i class="bi bi-house-door"></i> Inicio
                                </a>
                            </div>
                        </div>
                    </div>

                    <div class="card-body">
                        <!-- Mensaje de retroalimentación -->
                        <?php if (!empty($mensaje)): ?>
                            <div class="alert alert-<?php echo $tipo_alerta; ?> alert-dismissible fade show" role="alert">
                                <i class="bi bi-info-circle-fill"></i>
                                <strong><?php echo htmlspecialchars($mensaje); ?></strong>
                                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                            </div>
                        <?php endif; ?>

                        <!-- Información del usuario actual -->
                        <div class="mb-3 text-muted small">
                            <i class="bi bi-person-circle"></i> Sesión activa:
                            <strong><?php echo htmlspecialchars($_SESSION['nombre'] ?? 'Administrador'); ?></strong>
                            (<?php echo htmlspecialchars($_SESSION['rol']); ?>)
                        </div>

                        <!-- Tabla de Roles -->
                        <div class="table-responsive table-container">
                            <table class="table table-hover table-striped mb-0">
                                <thead class="table-dark">
                                    <tr>
                                        <th scope="col" class="text-center">ID</th>
                                        <th scope="col">Nombre del Rol</th>
                                        <th scope="col">Descripción</th>
                                        <th scope="col" class="text-center">Usuarios Activos</th>
                                        <th scope="col" class="text-center">Acciones</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if ($resultado_roles && $resultado_roles->num_rows > 0): ?>
                                        <?php while ($rol = $resultado_roles->fetch_assoc()): ?>
                                            <tr>
                                                <td class="text-center">
                                                    <span class="badge bg-secondary"><?php echo $rol['id_rol']; ?></span>
                                                </td>
                                                <td>
                                                    <strong><?php echo htmlspecialchars($rol['nombre_rol']); ?></strong>
                                                </td>
                                                <td><?php echo htmlspecialchars($rol['descripcion'] ?? 'Sin descripción'); ?></td>
                                                <td class="text-center">
                                                    <span class="badge badge-usuarios <?php echo $rol['usuarios_activos'] > 0 ? 'bg-success' : 'bg-secondary'; ?>">
                                                        <i class="bi bi-people-fill"></i> <?php echo $rol['usuarios_activos']; ?>
                                                    </span>
                                                </td>
                                                <td class="text-center">
                                                    <button type="button"
                                                            class="btn btn-sm btn-warning btn-action"
                                                            onclick="cargarDatosEdicion(<?php echo $rol['id_rol']; ?>, '<?php echo addslashes($rol['nombre_rol']); ?>', '<?php echo addslashes($rol['descripcion']); ?>')">
                                                        <i class="bi bi-pencil-square"></i> Editar
                                                    </button>
                                                    <button type="button"
                                                            class="btn btn-sm btn-danger btn-action"
                                                            onclick="confirmarEliminacion(<?php echo $rol['id_rol']; ?>, '<?php echo addslashes($rol['nombre_rol']); ?>', <?php echo $rol['usuarios_activos']; ?>)">
                                                        <i class="bi bi-trash-fill"></i> Eliminar
                                                    </button>
                                                </td>
                                            </tr>
                                        <?php endwhile; ?>
                                    <?php else: ?>
                                        <tr>
                                            <td colspan="5" class="text-center text-muted py-4">
                                                <i class="bi bi-inbox" style="font-size: 2rem;"></i>
                                                <p class="mb-0 mt-2">No hay roles registrados en el sistema</p>
                                            </td>
                                        </tr>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- ====== MODAL: NUEVO ROL ====== -->
    <div class="modal fade" id="modalNuevoRol" tabindex="-1" aria-labelledby="modalNuevoRolLabel" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <form method="POST" action="" id="formNuevoRol">
                    <div class="modal-header bg-primary text-white">
                        <h5 class="modal-title" id="modalNuevoRolLabel">
                            <i class="bi bi-plus-circle-fill"></i> Crear Nuevo Rol
                        </h5>
                        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <div class="modal-body">
                        <input type="hidden" name="accion" value="crear">

                        <div class="mb-3">
                            <label for="nombre_rol_nuevo" class="form-label">
                                Nombre del Rol <span class="text-danger">*</span>
                            </label>
                            <input type="text"
                                   class="form-control"
                                   id="nombre_rol_nuevo"
                                   name="nombre_rol"
                                   placeholder="Ej: Profesor, Tutor, etc."
                                   required
                                   maxlength="50">
                        </div>

                        <div class="mb-3">
                            <label for="descripcion_nuevo" class="form-label">Descripción</label>
                            <textarea class="form-control"
                                      id="descripcion_nuevo"
                                      name="descripcion"
                                      rows="3"
                                      placeholder="Descripción breve del rol y sus permisos"
                                      maxlength="255"></textarea>
                            <div class="form-text">Opcional. Máximo 255 caracteres.</div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                            <i class="bi bi-x-circle"></i> Cancelar
                        </button>
                        <button type="submit" class="btn btn-primary">
                            <i class="bi bi-save"></i> Guardar Rol
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- ====== MODAL: EDITAR ROL ====== -->
    <div class="modal fade" id="modalEditarRol" tabindex="-1" aria-labelledby="modalEditarRolLabel" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <form method="POST" action="" id="formEditarRol">
                    <div class="modal-header bg-warning">
                        <h5 class="modal-title text-dark" id="modalEditarRolLabel">
                            <i class="bi bi-pencil-square"></i> Editar Rol
                        </h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <div class="modal-body">
                        <input type="hidden" name="accion" value="editar">
                        <input type="hidden" name="id_rol" id="id_rol_editar">

                        <div class="mb-3">
                            <label for="nombre_rol_editar" class="form-label">
                                Nombre del Rol <span class="text-danger">*</span>
                            </label>
                            <input type="text"
                                   class="form-control"
                                   id="nombre_rol_editar"
                                   name="nombre_rol"
                                   required
                                   maxlength="50">
                        </div>

                        <div class="mb-3">
                            <label for="descripcion_editar" class="form-label">Descripción</label>
                            <textarea class="form-control"
                                      id="descripcion_editar"
                                      name="descripcion"
                                      rows="3"
                                      maxlength="255"></textarea>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                            <i class="bi bi-x-circle"></i> Cancelar
                        </button>
                        <button type="submit" class="btn btn-warning text-dark">
                            <i class="bi bi-check-circle"></i> Actualizar Rol
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- ====== MODAL: CONFIRMAR ELIMINACIÓN ====== -->
    <div class="modal fade" id="modalEliminarRol" tabindex="-1" aria-labelledby="modalEliminarRolLabel" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <form method="POST" action="" id="formEliminarRol">
                    <div class="modal-header bg-danger text-white">
                        <h5 class="modal-title" id="modalEliminarRolLabel">
                            <i class="bi bi-exclamation-triangle-fill"></i> Confirmar Eliminación
                        </h5>
                        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <div class="modal-body">
                        <input type="hidden" name="accion" value="eliminar">
                        <input type="hidden" name="id_rol" id="id_rol_eliminar">

                        <div class="alert alert-warning">
                            <i class="bi bi-info-circle-fill"></i>
                            ¿Está seguro que desea eliminar el rol <strong id="nombre_rol_eliminar"></strong>?
                        </div>

                        <div id="alerta_usuarios_activos" class="alert alert-danger" style="display: none;">
                            <i class="bi bi-exclamation-octagon-fill"></i>
                            <strong>¡ATENCIÓN!</strong> Este rol tiene <span id="cantidad_usuarios"></span> usuario(s) asignado(s).
                            <br>No se puede eliminar. Primero debe reasignar o eliminar los usuarios.
                        </div>

                        <p class="text-muted small mb-0">Esta acción no se puede deshacer.</p>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                            <i class="bi bi-x-circle"></i> Cancelar
                        </button>
                        <button type="submit" class="btn btn-danger" id="btnConfirmarEliminar">
                            <i class="bi bi-trash-fill"></i> Eliminar Definitivamente
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Bootstrap 5 JS Bundle -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>

    <script>
        /**
         * Cargar datos del rol en el modal de edición
         */
        function cargarDatosEdicion(id, nombre, descripcion) {
            document.getElementById('id_rol_editar').value = id;
            document.getElementById('nombre_rol_editar').value = nombre;
            document.getElementById('descripcion_editar').value = descripcion || '';

            // Mostrar el modal
            const modal = new bootstrap.Modal(document.getElementById('modalEditarRol'));
            modal.show();
        }

        /**
         * Confirmar eliminación de rol
         */
        function confirmarEliminacion(id, nombre, usuariosActivos) {
            document.getElementById('id_rol_eliminar').value = id;
            document.getElementById('nombre_rol_eliminar').textContent = nombre;

            const alertaUsuarios = document.getElementById('alerta_usuarios_activos');
            const btnEliminar = document.getElementById('btnConfirmarEliminar');

            if (usuariosActivos > 0) {
                // Mostrar advertencia y deshabilitar botón
                document.getElementById('cantidad_usuarios').textContent = usuariosActivos;
                alertaUsuarios.style.display = 'block';
                btnEliminar.disabled = true;
                btnEliminar.classList.add('disabled');
            } else {
                // Ocultar advertencia y habilitar botón
                alertaUsuarios.style.display = 'none';
                btnEliminar.disabled = false;
                btnEliminar.classList.remove('disabled');
            }

            // Mostrar el modal
            const modal = new bootstrap.Modal(document.getElementById('modalEliminarRol'));
            modal.show();
        }

        /**
         * Limpiar formularios al cerrar modales
         */
        document.getElementById('modalNuevoRol').addEventListener('hidden.bs.modal', function () {
            document.getElementById('formNuevoRol').reset();
        });

        document.getElementById('modalEditarRol').addEventListener('hidden.bs.modal', function () {
            document.getElementById('formEditarRol').reset();
        });
    </script>
</body>
</html>

<?php
// Cerrar la conexión a la base de datos
$conn->close();
?>
