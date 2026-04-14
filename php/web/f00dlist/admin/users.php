<?php
/**
 * ADMIN/USERS.PHP
 * ===============
 * Gestión de Usuarios.
 * Permite: Ver lista, Cambiar roles, Activar/Desactivar cuentas.
 */

require_once '../config/config.php';
require_once '../config/db.php';
require_once '../includes/auth.php';
require_once '../includes/functions.php';

requireAdmin();

$mensaje = '';
$tipoMensaje = '';

// Procesar acciones (Cambiar rol o Activar/Desactivar)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!validateCSRFToken($_POST['csrf_token'] ?? '')) {
        $tipoMensaje = 'danger';
        $mensaje = 'Token inválido.';
    } else {
        $action = $_POST['action'] ?? '';
        $userId = (int)($_POST['user_id'] ?? 0);

        if ($userId <= 0) {
            $tipoMensaje = 'danger';
            $mensaje = 'ID de usuario inválido.';
        } else {
            // Evitar que el admin se quite el rol a sí mismo
            if ($userId == getCurrentUserId() && $action == 'remove_admin') {
                $tipoMensaje = 'danger';
                $mensaje = 'No puedes quitarte el rol de administrador a ti mismo.';
            } else {
                try {
                    if ($action === 'set_admin') {
                        // Asignar rol Admin (ID 1)
                        // Primero quitar cualquier otro rol
                        executeQuery("DELETE FROM usuarios_roles WHERE id_usuario = ?", [$userId]);
                        executeQuery("INSERT INTO usuarios_roles (id_usuario, id_rol) VALUES (?, 1)", [$userId]);
                        $tipoMensaje = 'success';
                        $mensaje = 'Usuario promovido a Administrador.';
                    } elseif ($action === 'remove_admin') {
                        // Quitar rol Admin y asignar Usuario (ID 2)
                        executeQuery("DELETE FROM usuarios_roles WHERE id_usuario = ?", [$userId]);
                        executeQuery("INSERT INTO usuarios_roles (id_usuario, id_rol) VALUES (?, 2)", [$userId]);
                        $tipoMensaje = 'success';
                        $mensaje = 'Rol de Administrador removido. Ahora es Usuario estándar.';
                    } elseif ($action === 'toggle_active') {
                        $activo = isset($_POST['activo']) ? 0 : 1;
                        executeQuery("UPDATE users SET activo = ? WHERE id = ?", [$activo, $userId]);
                        $tipoMensaje = 'success';
                        $mensaje = $activo ? 'Usuario activado.' : 'Usuario desactivado.';
                    }
                } catch (Exception $e) {
                    $tipoMensaje = 'danger';
                    $mensaje = 'Error al actualizar: ' . $e->getMessage();
                }
            }
        }
    }
}

// Listar usuarios con sus roles
$sql = "SELECT u.*, 
        GROUP_CONCAT(r.nombre SEPARATOR ', ') as roles 
        FROM users u
        LEFT JOIN usuarios_roles ur ON u.id = ur.id_usuario
        LEFT JOIN roles r ON ur.id_rol = r.id
        GROUP BY u.id
        ORDER BY u.activo DESC, u.username ASC";
$users = fetchAll($sql);

$pageTitle = 'Gestionar Usuarios';
require_once '../includes/header.php';
?>

<div class="admin-page">
    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px;">
        <h1>Gestión de Usuarios</h1>
        <a href="index.php" class="btn btn-secondary">← Volver</a>
    </div>

    <?php if ($mensaje): ?>
        <div class="alert alert-<?= $tipoMensaje ?>"><?= sanitize($mensaje) ?></div>
    <?php endif; ?>

    <div style="background: white; padding: 20px; border-radius: 8px; box-shadow: 0 2px 5px rgba(0,0,0,0.05);">
        <table style="width: 100%; border-collapse: collapse;">
            <thead>
                <tr style="background: #f8f9fa;">
                    <th style="padding: 10px; text-align: left;">Usuario</th>
                    <th style="padding: 10px; text-align: left;">Email</th>
                    <th style="padding: 10px; text-align: left;">Nombre</th>
                    <th style="padding: 10px; text-align: center;">Roles</th>
                    <th style="padding: 10px; text-align: center;">Estado</th>
                    <th style="padding: 10px; text-align: center;">Acciones</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($users as $u): ?>
                    <tr>
                        <td style="padding: 10px; border-bottom: 1px solid #eee; font-weight: bold;"><?= sanitize($u['username']) ?></td>
                        <td style="padding: 10px; border-bottom: 1px solid #eee;"><?= sanitize($u['email']) ?></td>
                        <td style="padding: 10px; border-bottom: 1px solid #eee;"><?= sanitize($u['nombre_completo']) ?></td>
                        <td style="padding: 10px; text-align: center; border-bottom: 1px solid #eee;">
                            <span style="padding: 3px 8px; border-radius: 10px; font-size: 0.8rem; background: <?= strpos($u['roles'], 'admin') !== false ? '#e3f2fd' : '#f3e5f5' ?>; color: <?= strpos($u['roles'], 'admin') !== false ? '#1565c0' : '#7b1fa2' ?>;">
                                <?= sanitize($u['roles'] ?: 'Usuario') ?>
                            </span>
                        </td>
                        <td style="padding: 10px; text-align: center; border-bottom: 1px solid #eee;">
                            <span style="padding: 3px 8px; border-radius: 10px; font-size: 0.8rem; background: <?= $u['activo'] ? '#d4edda' : '#f8d7da' ?>; color: <?= $u['activo'] ? '#155724' : '#721c24' ?>;">
                                <?= $u['activo'] ? 'Activo' : 'Inactivo' ?>
                            </span>
                        </td>
                        <td style="padding: 10px; text-align: center; border-bottom: 1px solid #eee;">
                            <form method="POST" action="" style="display: inline;">
                                <?= csrfField() ?>
                                <input type="hidden" name="user_id" value="<?= $u['id'] ?>">
                                
                                <?php if (strpos($u['roles'], 'admin') !== false): ?>
                                    <!-- Si es admin, botón para quitar admin -->
                                    <?php if ($u['id'] != getCurrentUserId()): ?>
                                        <button type="submit" name="action" value="remove_admin" class="btn btn-warning btn-sm" style="padding: 5px 8px; font-size: 0.8rem;" onclick="return confirm('¿Quitar rol de admin?')">Quitar Admin</button>
                                    <?php endif; ?>
                                <?php else: ?>
                                    <!-- Si no es admin, botón para dar admin -->
                                    <button type="submit" name="action" value="set_admin" class="btn btn-primary btn-sm" style="padding: 5px 8px; font-size: 0.8rem;">Dar Admin</button>
                                <?php endif; ?>

                                <button type="submit" name="action" value="toggle_active" name="activo" value="<?= $u['activo'] ? 0 : 1 ?>" class="btn btn-secondary btn-sm" style="padding: 5px 8px; font-size: 0.8rem; margin-left: 5px;">
                                    <?= $u['activo'] ? 'Desactivar' : 'Activar' ?>
                                </button>
                            </form>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<?php require_once '../includes/footer.php'; ?>