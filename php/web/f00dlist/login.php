<?php
/**
 * LOGIN.PHP
 * =========
 * Página de inicio de sesión.
 */

require_once 'config/config.php';
require_once 'includes/auth.php';
require_once 'includes/functions.php';

if (isLoggedIn()) {
    redirect(url('index.php'));
}

$error = '';
$username = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!validateCSRFToken($_POST['csrf_token'] ?? '')) {
        $error = 'Token de seguridad inválido.';
    } else {
        $usernameInput = trim($_POST['username'] ?? '');
        $passwordInput = $_POST['password'] ?? '';
        $clientIp = $_SERVER['REMOTE_ADDR'] ?? 'unknown';

        if (!checkRateLimit($clientIp, $usernameInput, 5, 300)) {
            $error = 'Demasiados intentos fallidos. Intenta más tarde.';
        } elseif (empty($usernameInput) || empty($passwordInput)) {
            $error = 'Introduce usuario y contraseña.';
        } else {
            $result = loginUser($usernameInput, $passwordInput);
            if ($result['success']) {
                setSuccessMessage('¡Bienvenido de nuevo, ' . sanitize($result['user']['username']) . '!');
                redirect(url('index.php'));
            } else {
                checkRateLimit($clientIp, $usernameInput, 5, 300);
                $error = $result['message'];
                $username = $usernameInput;
            }
        }
    }
}

$pageTitle = 'Login';
require_once 'includes/header.php';
?>

<div class="row justify-content-center mt-5">
    <div class="col-md-5 col-lg-4">
        <div class="card shadow-sm">
            <div class="card-body p-4">
                <h1 class="text-center mb-1">f00dlist</h1>
                <p class="text-center text-muted mb-4">Accede a tu gestor de menús</p>

                <?php if ($error): ?>
                    <div class="alert alert-danger"><?= sanitize($error) ?></div>
                <?php endif; ?>

                <form method="POST" action="">
                    <?= csrfField() ?>
                    <div class="mb-3">
                        <label for="username" class="form-label">Usuario o Email</label>
                        <input type="text" id="username" name="username" class="form-control"
                               value="<?= sanitize($username) ?>" placeholder="Ej: usuario@email.com" required autofocus>
                    </div>
                    <div class="mb-3">
                        <label for="password" class="form-label">Contraseña</label>
                        <input type="password" id="password" name="password" class="form-control"
                               placeholder="••••••••" required>
                    </div>
                    <button type="submit" class="btn btn-primary w-100">Iniciar Sesión</button>
                </form>

                <p class="text-center mt-3 mb-0">
                    ¿No tienes cuenta? <a href="<?= url('register.php') ?>">Regístrate aquí</a>
                </p>
            </div>
        </div>
    </div>
</div>

<?php require_once 'includes/footer.php'; ?>