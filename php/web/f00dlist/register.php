<?php
/**
 * REGISTER.PHP
 * ============
 * Página de registro de nuevos usuarios.
 * Valida datos, verifica duplicados y crea la cuenta.
 */

require_once 'config/config.php';
require_once 'includes/auth.php';
require_once 'includes/functions.php';

// Si ya está logueado, redirigir al dashboard
if (isLoggedIn()) {
    redirect(url('index.php'));
}

$errors = [];
$formData = [
    'username' => '',
    'email' => '',
    'nombre_completo' => ''
];

// Procesar formulario POST
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Validar token CSRF
    if (!validateCSRFToken($_POST['csrf_token'] ?? '')) {
        $errors['general'] = 'Token de seguridad inválido. Por favor, recarga la página.';
    } else {
        // Capturar datos
        $formData['username'] = trim($_POST['username'] ?? '');
        $formData['email'] = trim($_POST['email'] ?? '');
        $formData['nombre_completo'] = trim($_POST['nombre_completo'] ?? '');
        $password = $_POST['password'] ?? '';
        $passwordConfirm = $_POST['password_confirm'] ?? '';

        // Validaciones básicas
        if (strlen($formData['username']) < USERNAME_MIN_LENGTH) {
            $errors['username'] = 'El usuario debe tener al menos ' . USERNAME_MIN_LENGTH . ' caracteres.';
        }

        if (!isValidEmail($formData['email'])) {
            $errors['email'] = 'El email no es válido.';
        }

        if (empty($formData['nombre_completo'])) {
            $errors['nombre_completo'] = 'El nombre completo es obligatorio.';
        }

        if (strlen($password) < PASSWORD_MIN_LENGTH) {
            $errors['password'] = 'La contraseña debe tener al menos ' . PASSWORD_MIN_LENGTH . ' caracteres.';
        }

        if ($password !== $passwordConfirm) {
            $errors['password_confirm'] = 'Las contraseñas no coinciden.';
        }

        // Si no hay errores de validación, intentar registrar
        if (empty($errors)) {
            $result = registerUser(
                $formData['username'],
                $formData['email'],
                $password,
                $formData['nombre_completo']
            );

            if ($result['success']) {
                setSuccessMessage('¡Registro exitoso! Ahora puedes iniciar sesión.');
                redirect(url('login.php'));
            } else {
                $errors['general'] = $result['message'];
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Registro - f00dlist</title>
    <link rel="stylesheet" href="<?= asset('css/style.css') ?>">
    <link rel="stylesheet" href="<?= asset('css/responsive.css') ?>">
    <style>
        /* Estilos específicos para registro (similares a login) */
        body {
            display: flex;
            justify-content: center;
            align-items: center;
            min-height: 100vh;
            background-color: #f4f6f9;
            margin: 0;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }
        .register-container {
            background: white;
            padding: 2rem;
            border-radius: 10px;
            box-shadow: 0 4px 15px rgba(0,0,0,0.1);
            width: 100%;
            max-width: 450px;
        }
        .register-header {
            text-align: center;
            margin-bottom: 2rem;
        }
        .register-header h1 {
            color: #2c3e50;
            margin: 0;
            font-size: 1.8rem;
        }
        .register-header p {
            color: #7f8c8d;
            margin-top: 0.5rem;
        }
        .form-group {
            margin-bottom: 1.2rem;
        }
        .form-group label {
            display: block;
            margin-bottom: 0.4rem;
            color: #34495e;
            font-weight: 600;
            font-size: 0.95rem;
        }
        .form-control {
            width: 100%;
            padding: 0.7rem;
            border: 1px solid #ddd;
            border-radius: 5px;
            font-size: 1rem;
            box-sizing: border-box;
        }
        .form-control:focus {
            border-color: #27ae60;
            outline: none;
            box-shadow: 0 0 0 3px rgba(39, 174, 96, 0.2);
        }
        .btn-success {
            width: 100%;
            padding: 0.75rem;
            background-color: #27ae60;
            color: white;
            border: none;
            border-radius: 5px;
            font-size: 1rem;
            font-weight: 600;
            cursor: pointer;
            transition: background 0.3s;
        }
        .btn-success:hover {
            background-color: #219150;
        }
        .alert {
            padding: 0.75rem;
            margin-bottom: 1.5rem;
            border-radius: 5px;
            font-size: 0.9rem;
        }
        .alert-danger {
            background-color: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
        }
        .error-text {
            color: #e74c3c;
            font-size: 0.85rem;
            margin-top: 0.3rem;
            display: block;
        }
        .register-footer {
            text-align: center;
            margin-top: 1.5rem;
            font-size: 0.9rem;
        }
        .register-footer a {
            color: #27ae60;
            text-decoration: none;
        }
        .register-footer a:hover {
            text-decoration: underline;
        }
    </style>
</head>
<body>

<div class="register-container">
    <div class="register-header">
        <h1>f00dlist</h1>
        <p>Crea tu cuenta y empieza a organizar tus menús</p>
    </div>

    <?php if (!empty($errors['general'])): ?>
        <div class="alert alert-danger">
            <?= sanitize($errors['general']) ?>
        </div>
    <?php endif; ?>

    <form method="POST" action="">
        <?= csrfField() ?>

        <div class="form-group">
            <label for="username">Nombre de Usuario *</label>
            <input type="text" 
                   id="username" 
                   name="username" 
                   class="form-control" 
                   value="<?= sanitize($formData['username']) ?>" 
                   placeholder="Ej: eme"
                   required 
                   autofocus>
            <?php if (!empty($errors['username'])): ?>
                <span class="error-text"><?= sanitize($errors['username']) ?></span>
            <?php endif; ?>
        </div>

        <div class="form-group">
            <label for="email">Email *</label>
            <input type="email" 
                   id="email" 
                   name="email" 
                   class="form-control" 
                   value="<?= sanitize($formData['email']) ?>" 
                   placeholder="tu@email.com"
                   required>
            <?php if (!empty($errors['email'])): ?>
                <span class="error-text"><?= sanitize($errors['email']) ?></span>
            <?php endif; ?>
        </div>

        <div class="form-group">
            <label for="nombre_completo">Nombre Completo *</label>
            <input type="text" 
                   id="nombre_completo" 
                   name="nombre_completo" 
                   class="form-control" 
                   value="<?= sanitize($formData['nombre_completo']) ?>" 
                   placeholder="Ej: Eme García"
                   required>
            <?php if (!empty($errors['nombre_completo'])): ?>
                <span class="error-text"><?= sanitize($errors['nombre_completo']) ?></span>
            <?php endif; ?>
        </div>

        <div class="form-group">
            <label for="password">Contraseña *</label>
            <input type="password" 
                   id="password" 
                   name="password" 
                   class="form-control" 
                   placeholder="Mínimo <?= PASSWORD_MIN_LENGTH ?> caracteres"
                   required>
            <?php if (!empty($errors['password'])): ?>
                <span class="error-text"><?= sanitize($errors['password']) ?></span>
            <?php endif; ?>
        </div>

        <div class="form-group">
            <label for="password_confirm">Confirmar Contraseña *</label>
            <input type="password" 
                   id="password_confirm" 
                   name="password_confirm" 
                   class="form-control" 
                   placeholder="Repite la contraseña"
                   required>
            <?php if (!empty($errors['password_confirm'])): ?>
                <span class="error-text"><?= sanitize($errors['password_confirm']) ?></span>
            <?php endif; ?>
        </div>

        <button type="submit" class="btn-success">Registrarse</button>
    </form>

    <div class="register-footer">
        ¿Ya tienes cuenta? <a href="<?= url('login.php') ?>">Inicia sesión aquí</a>
    </div>
</div>

</body>
</html>