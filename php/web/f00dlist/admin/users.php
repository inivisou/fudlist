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

// Listar usuarios con sus roles
$users = getAllUsersWithRoles();

$pageTitle = 'Gestionar Usuarios';
require_once '../includes/header.php';
?>

<div class="admin-page">
    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px;">
        <h1>Gestión de Usuarios</h1>
        <a href="index.php" class="btn btn-secondary">← Volver</a>
    </div>

    <div id="api-message"></div>

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
                    <tr id="user-row-<?= $u['id'] ?>">
                        <td style="padding: 10px; border-bottom: 1px solid #eee; font-weight: bold;"><?= sanitize($u['username']) ?></td>
                        <td style="padding: 10px; border-bottom: 1px solid #eee;"><?= sanitize($u['email']) ?></td>
                        <td style="padding: 10px; border-bottom: 1px solid #eee;"><?= sanitize($u['nombre_completo']) ?></td>
                        <td style="padding: 10px; text-align: center; border-bottom: 1px solid #eee;">
                            <?php 
                            $rolesList = $u['roles'] ?? '';
                            $isAdmin = strpos($rolesList, 'admin') !== false;
                            $isColab = strpos($rolesList, 'colaborador') !== false;
                            ?>
                            <span class="role-badge" style="padding: 3px 8px; border-radius: 10px; font-size: 0.8rem; background: <?= $isAdmin ? '#e3f2fd' : ($isColab ? '#e8f5e9' : '#f3e5f5') ?>; color: <?= $isAdmin ? '#1565c0' : ($isColab ? '#2e7d32' : '#7b1fa2') ?>;">
                                <?= sanitize($rolesList ?: 'user') ?>
                            </span>
                        </td>
                        <td style="padding: 10px; text-align: center; border-bottom: 1px solid #eee;">
                            <span class="status-badge" style="padding: 3px 8px; border-radius: 10px; font-size: 0.8rem; background: <?= $u['activo'] ? '#d4edda' : '#f8d7da' ?>; color: <?= $u['activo'] ? '#155724' : '#721c24' ?>;">
                                <?= $u['activo'] ? 'Activo' : 'Inactivo' ?>
                            </span>
                        </td>
                        <td style="padding: 10px; text-align: center; border-bottom: 1px solid #eee;">
                            <div class="actions-container" style="display: inline-flex; gap: 5px;">
                                <?php if ($isAdmin): ?>
                                    <?php if ($u['id'] != getCurrentUserId()): ?>
                                        <button onclick="userAction(<?= $u['id'] ?>, 'remove_role')" class="btn btn-warning btn-sm">Quitar Admin</button>
                                    <?php endif; ?>
                                <?php else: ?>
                                    <button onclick="userAction(<?= $u['id'] ?>, 'set_admin')" class="btn btn-primary btn-sm">Hacer Admin</button>
                                    <?php if ($isColab): ?>
                                        <button onclick="userAction(<?= $u['id'] ?>, 'remove_role')" class="btn btn-warning btn-sm">Quitar Colab.</button>
                                    <?php else: ?>
                                        <button onclick="userAction(<?= $u['id'] ?>, 'set_colaborador')" class="btn btn-success btn-sm">Hacer Colab.</button>
                                    <?php endif; ?>
                                <?php endif; ?>
                                <button onclick="userAction(<?= $u['id'] ?>, 'toggle_active', <?= $u['activo'] ?>)" class="btn btn-secondary btn-sm">
                                    <?= $u['activo'] ? 'Baja' : 'Alta' ?>
                                </button>
                            </div>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<script>
const CSRF_TOKEN = '<?= generateCSRFToken() ?>';
function userAction(userId, action, currentStatus = 0) {
    const formData = new URLSearchParams();
    formData.append('csrf_token', CSRF_TOKEN);
    formData.append('user_id', userId);
    formData.append('action', action);
    if(action === 'toggle_active') formData.append('current_status', currentStatus);

    fetch('../api/admin_users.php', {
        method: 'POST',
        body: formData
    })
    .then(r => r.json())
    .then(res => {
        if(res.success) location.reload(); // Simplificado para actualizar botones complejos, o manipular DOM aquí.
        else alert(res.message);
    });
}
</script>

<?php require_once '../includes/footer.php'; ?>