<?php
/**
 * LOGIN.PHP
 * =========
 * Página de inicio de sesión.
 * Valida credenciales y gestiona la sesión.
 */

require_once 'config/config.php';
require_once 'includes/auth.php';
require_once 'includes/functions.php';

// Si ya está logueado, redirigir al dashboard
if (isLoggedIn()) {
    redirect(url('index.php'));
}

$error = '';
$username = '';

// Procesar formulario POST
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Validar token CSRF
    if (!validateCSRFToken($_POST['csrf_token'] ?? '')) {
        $error = 'Token de seguridad inválido. Por favor, recarga la página.';
    } else {
        $usernameInput = trim($_POST['username'] ?? '');
        $passwordInput = $_POST['password'] ?? '';

        // Rate limiting: verificar intentos fallidos por IP y username
        $clientIp = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
        if (!checkRateLimit($clientIp, $usernameInput, 5, 300)) {
            $error = 'Demasiados intentos fallidos. Por favor, intenta más tarde.';
        } else {
            if (empty($usernameInput) || empty($passwordInput)) {
                $error = 'Por favor, introduce usuario y contraseña.';
            } else {
                // Intentar login
                $result = loginUser($usernameInput, $passwordInput);

                if ($result['success']) {
                    // Login exitoso: redirigir al dashboard
                    setSuccessMessage('¡Bienvenido de nuevo, ' . sanitize($result['user']['username']) . '!');
                    redirect(url('index.php'));
                } else {
                    // Registrar intento fallido para rate limiting (ya se registra en checkRateLimit, pero llamamos nuevamente para contar este intento)
                    checkRateLimit($clientIp, $usernameInput, 5, 300);
                    $error = $result['message'];
                    $username = $usernameInput; // Mantener el usuario introducido
                }
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
    <title>Login - f00dlist</title>
    <link rel="stylesheet" href="<?= asset('css/style.css') ?>">
    <link rel="stylesheet" href="<?= asset('css/responsive.css') ?>">
    <style>
        /* Estilos específicos para login (puedes moverlos a style.css después) */
        body {
            display: flex;
            justify-content: center;
            align-items: center;
            min-height: 100vh;
            background-color: #f4f6f9;
            margin: 0;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }
        .login-container {
            background: white;
            padding: 2rem;
            border-radius: 10px;
            box-shadow: 0 4px 15px rgba(0,0,0,0.1);
            width: 100%;
            max-width: 400px;
        }
        .login-header {
            text-align: center;
            margin-bottom: 2rem;
        }
        .login-header h1 {
            color: #2c3e50;
            margin: 0;
            font-size: 1.8rem;
        }
        .login-header p {
            color: #7f8c8d;
            margin-top: 0.5rem;
        }
        .form-group {
            margin-bottom: 1.5rem;
        }
        .form-group label {
            display: block;
            margin-bottom: 0.5rem;
            color: #34495e;
            font-weight: 600;
        }
        .form-control {
            width: 100%;
            padding: 0.75rem;
            border: 1px solid #ddd;
            border-radius: 5px;
            font-size: 1rem;
            box-sizing: border-box;
        }
        .form-control:focus {
            border-color: #3498db;
            outline: none;
            box-shadow: 0 0 0 3px rgba(52, 152, 219, 0.2);
        }
        .btn-primary {
            width: 100%;
            padding: 0.75rem;
            background-color: #3498db;
            color: white;
            border: none;
            border-radius: 5px;
            font-size: 1rem;
            font-weight: 600;
            cursor: pointer;
            transition: background 0.3s;
        }
        .btn-primary:hover {
            background-color: #2980b9;
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
        .login-footer {
            text-align: center;
            margin-top: 1.5rem;
            font-size: 0.9rem;
        }
        .login-footer a {
            color: #3498db;
            text-decoration: none;
        }
        .login-footer a:hover {
            text-decoration: underline;
        }
    </style>
</head>
<body>

<div class="login-container">
    <div class="login-header">
        <h1>f00dlist</h1>
        <p>Accede a tu gestor de menús</p>
    </div>

    <?php if ($error): ?>
        <div class="alert alert-danger">
            <?= sanitize($error) ?>
        </div>
    <?php endif; ?>

    <form method="POST" action="">
        <?= csrfField() ?>
        
        <div class="form-group">
            <label for="username">Usuario o Email</label>
            <input type="text" 
                   id="username" 
                   name="username" 
                   class="form-control" 
                   value="<?= sanitize($username) ?>" 
                   placeholder="Ej: eme o eme@ejemplo.com"
                   required 
                   autofocus>
        </div>

        <div class="form-group">
            <label for="password">Contraseña</label>
            <input type="password" 
                   id="password" 
                   name="password" 
                   class="form-control" 
                   placeholder="••••••••"
                   required>
        </div>

        <button type="submit" class="btn-primary">Iniciar Sesión</button>
    </form>

    <div class="login-footer">
        ¿No tienes cuenta? <a href="<?= url('register.php') ?>">Regístrate aquí</a>
    </div>
</div>

</body>
</html>