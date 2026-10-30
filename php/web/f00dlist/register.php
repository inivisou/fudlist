<?php
/**
 * REGISTER.PHP
 * ============
 * Página de registro de nuevos usuarios.
 */

require_once 'config/config.php';
require_once 'includes/auth.php';
require_once 'includes/functions.php';

if (isLoggedIn()) {
    redirect(url('index.php'));
}

$errors = [];
$formData = ['username' => '', 'email' => '', 'nombre_completo' => ''];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!validateCSRFToken($_POST['csrf_token'] ?? '')) {
        $errors['general'] = 'Token de seguridad inválido.';
    } else {
        $clientIp = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
        if (!checkRateLimit($clientIp, null, 5, 300)) {
            $errors['general'] = 'Demasiados intentos. Intenta más tarde.';
        } else {
            $formData['username'] = trim($_POST['username'] ?? '');
            $formData['email'] = trim($_POST['email'] ?? '');
            $formData['nombre_completo'] = trim($_POST['nombre_completo'] ?? '');
            $password = $_POST['password'] ?? '';
            $passwordConfirm = $_POST['password_confirm'] ?? '';

            if (strlen($formData['username']) < USERNAME_MIN_LENGTH || strlen($formData['username']) > USERNAME_MAX_LENGTH) {
                $errors['username'] = 'El usuario debe tener entre ' . USERNAME_MIN_LENGTH . ' y ' . USERNAME_MAX_LENGTH . ' caracteres.';
            }
            if (!isValidEmail($formData['email'])) {
                $errors['email'] = 'Email no válido.';
            }
            if (!validateNombreCompleto($formData['nombre_completo'])) {
                $errors['nombre_completo'] = 'Nombre entre 2 y 100 caracteres.';
            }
            if (strlen($password) < PASSWORD_MIN_LENGTH) {
                $errors['password'] = 'Mínimo ' . PASSWORD_MIN_LENGTH . ' caracteres.';
            }
            if ($password !== $passwordConfirm) {
                $errors['password_confirm'] = 'Las contraseñas no coinciden.';
            }

            if (empty($errors)) {
                $result = registerUser($formData['username'], $formData['email'], $password, $formData['nombre_completo']);
                if ($result['success']) {
                    setSuccessMessage('Registro exitoso. Ahora inicia sesión.');
                    redirect(url('login.php'));
                } else {
                    $errors['general'] = $result['message'];
                }
            }
        }
    }
}

$pageTitle = 'Registro';
require_once 'includes/header.php';
?>

<div class="row justify-content-center mt-4">
    <div class="col-md-6 col-lg-5">
        <div class="card shadow-sm">
            <div class="card-body p-4">
                <h1 class="text-center mb-1">f00dlist</h1>
                <p class="text-center text-muted mb-4">Crea tu cuenta</p>

                <?php if (!empty($errors['general'])): ?>
                    <div class="alert alert-danger"><?= sanitize($errors['general']) ?></div>
                <?php endif; ?>

                <form method="POST" action="">
                    <?= csrfField() ?>

                    <div class="mb-3">
                        <label for="username" class="form-label">Usuario *</label>
                        <input type="text" id="username" name="username" class="form-control"
                               value="<?= sanitize($formData['username']) ?>" placeholder="Ej: eme" required autofocus>
                        <?php if (!empty($errors['username'])): ?>
                            <div class="text-danger small mt-1"><?= sanitize($errors['username']) ?></div>
                        <?php endif; ?>
                    </div>

                    <div class="mb-3">
                        <label for="email" class="form-label">Email *</label>
                        <input type="email" id="email" name="email" class="form-control"
                               value="<?= sanitize($formData['email']) ?>" placeholder="tu@email.com" required>
                        <?php if (!empty($errors['email'])): ?>
                            <div class="text-danger small mt-1"><?= sanitize($errors['email']) ?></div>
                        <?php endif; ?>
                    </div>

                    <div class="mb-3">
                        <label for="nombre_completo" class="form-label">Nombre Completo *</label>
                        <input type="text" id="nombre_completo" name="nombre_completo" class="form-control"
                               value="<?= sanitize($formData['nombre_completo']) ?>" placeholder="Ej: Eme García" required>
                        <?php if (!empty($errors['nombre_completo'])): ?>
                            <div class="text-danger small mt-1"><?= sanitize($errors['nombre_completo']) ?></div>
                        <?php endif; ?>
                    </div>

                    <div class="mb-3">
                        <label for="password" class="form-label">Contraseña *</label>
                        <input type="password" id="password" name="password" class="form-control"
                               placeholder="Mínimo <?= PASSWORD_MIN_LENGTH ?> caracteres" required>
                        <?php if (!empty($errors['password'])): ?>
                            <div class="text-danger small mt-1"><?= sanitize($errors['password']) ?></div>
                        <?php endif; ?>
                    </div>

                    <div class="mb-3">
                        <label for="password_confirm" class="form-label">Confirmar Contraseña *</label>
                        <input type="password" id="password_confirm" name="password_confirm" class="form-control"
                               placeholder="Repite la contraseña" required>
                        <?php if (!empty($errors['password_confirm'])): ?>
                            <div class="text-danger small mt-1"><?= sanitize($errors['password_confirm']) ?></div>
                        <?php endif; ?>
                    </div>

                    <button type="submit" class="btn btn-success w-100">Registrarse</button>
                </form>

                <p class="text-center mt-3 mb-0">
                    ¿Ya tienes cuenta? <a href="<?= url('login.php') ?>">Inicia sesión</a>
                </p>
            </div>
        </div>
    </div>
</div>

<?php require_once 'includes/footer.php'; ?>