<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/src/autoload.php';

use App\Core\Auth;
use App\Core\Csrf;
use App\Core\Env;
use App\Core\Session;

Env::load(dirname(__DIR__) . '/.env');
Session::start();

// Redirect to respective dashboard if already logged in
Auth::redirectIfAuthenticated();

$error = Session::getFlash('error');
$success = Session::getFlash('success');
$emailInput = '';

// Handle POST login request
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $submittedToken = $_POST['csrf_token'] ?? '';
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    $emailInput = $email;

    if (!Csrf::validate($submittedToken)) {
        $error = 'Invalid security token (CSRF). Please refresh and try again.';
    } elseif (empty($email) || empty($password)) {
        $error = 'Please enter both your email address and password.';
    } else {
        if (Auth::attempt($email, $password)) {
            $user = Auth::user();
            Session::setFlash('success', "Welcome back, {$user['name']}!");

            // Check if there was an intended URL requested earlier
            $intended = Session::get('_intended_url');
            Session::remove('_intended_url');

            $redirectUrl = $intended ?: Auth::dashboardUrl();
            header("Location: {$redirectUrl}");
            exit;
        } else {
            // Generic error message to prevent account enumeration
            $error = 'Invalid credentials or inactive account.';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sign In &mdash; <?= htmlspecialchars(Env::get('APP_NAME', 'PharmaDEM')) ?></title>
    <style>
        :root {
            --bg-base: #090d16;
            --card-bg: #111827;
            --input-bg: #1f2937;
            --border-color: #374151;
            --text-primary: #f9fafb;
            --text-secondary: #9ca3af;
            --brand-primary: #2563eb;
            --brand-hover: #1d4ed8;
            --danger: #ef4444;
            --success: #10b981;
            --radius: 10px;
        }

        * {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
        }

        body {
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Oxygen, Ubuntu, Cantarell, sans-serif;
            background-color: var(--bg-base);
            color: var(--text-primary);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 1.5rem;
            background-image: 
                radial-gradient(at 0% 0%, rgba(37, 99, 235, 0.15) 0px, transparent 50%),
                radial-gradient(at 100% 100%, rgba(16, 185, 129, 0.1) 0px, transparent 50%);
        }

        .auth-container {
            width: 100%;
            max-width: 440px;
            background: var(--card-bg);
            border: 1px solid var(--border-color);
            border-radius: var(--radius);
            box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.5);
            padding: 2.25rem;
        }

        .auth-header {
            text-align: center;
            margin-bottom: 2rem;
        }

        .auth-header h1 {
            font-size: 1.5rem;
            font-weight: 700;
            letter-spacing: -0.025em;
            color: var(--text-primary);
            margin-bottom: 0.5rem;
        }

        .auth-header p {
            font-size: 0.875rem;
            color: var(--text-secondary);
        }

        .alert {
            padding: 0.75rem 1rem;
            border-radius: 8px;
            font-size: 0.875rem;
            margin-bottom: 1.5rem;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .alert-danger {
            background-color: rgba(239, 68, 68, 0.15);
            color: var(--danger);
            border: 1px solid rgba(239, 68, 68, 0.3);
        }

        .alert-success {
            background-color: rgba(16, 185, 129, 0.15);
            color: var(--success);
            border: 1px solid rgba(16, 185, 129, 0.3);
        }

        .form-group {
            margin-bottom: 1.25rem;
        }

        .form-label {
            display: block;
            font-size: 0.875rem;
            font-weight: 500;
            margin-bottom: 0.5rem;
            color: #d1d5db;
        }

        .form-input {
            width: 100%;
            padding: 0.75rem 1rem;
            background-color: var(--input-bg);
            border: 1px solid var(--border-color);
            border-radius: 6px;
            color: var(--text-primary);
            font-size: 0.95rem;
            transition: border-color 0.15s ease, box-shadow 0.15s ease;
        }

        .form-input:focus {
            outline: none;
            border-color: var(--brand-primary);
            box-shadow: 0 0 0 3px rgba(37, 99, 235, 0.3);
        }

        .btn-submit {
            width: 100%;
            padding: 0.8rem;
            background-color: var(--brand-primary);
            color: #ffffff;
            font-weight: 600;
            font-size: 0.95rem;
            border: none;
            border-radius: 6px;
            cursor: pointer;
            transition: background-color 0.15s ease;
            margin-top: 0.5rem;
        }

        .btn-submit:hover {
            background-color: var(--brand-hover);
        }

        .login-footer {
            margin-top: 2rem;
            text-align: center;
            font-size: 0.75rem;
            color: var(--text-secondary);
        }
    </style>
</head>
<body>

<div class="auth-container">
    <div class="auth-header">
        <h1>PharmaDEM Portal</h1>
        <p>Daily Work Update &amp; Performance Evaluation</p>
    </div>

    <?php if ($error): ?>
        <div class="alert alert-danger">
            <?= htmlspecialchars($error) ?>
        </div>
    <?php endif; ?>

    <?php if ($success): ?>
        <div class="alert alert-success">
            <?= htmlspecialchars($success) ?>
        </div>
    <?php endif; ?>

    <form method="POST" action="/login.php" autocomplete="on">
        <?= Csrf::field() ?>

        <div class="form-group">
            <label class="form-label" for="email">Work Email</label>
            <input 
                type="email" 
                id="email" 
                name="email" 
                class="form-input" 
                placeholder="name@pharmadem.local"
                value="<?= htmlspecialchars($emailInput) ?>" 
                required 
                autofocus
            >
        </div>

        <div class="form-group">
            <label class="form-label" for="password">Password</label>
            <input 
                type="password" 
                id="password" 
                name="password" 
                class="form-input" 
                placeholder="••••••••••••"
                required
            >
        </div>

        <button type="submit" class="btn-submit">
            Sign In
        </button>
    </form>

    <div class="login-footer">
        &copy; <?= date('Y') ?> PharmaDEM Solutions. All rights reserved.
    </div>
</div>

</body>
</html>
