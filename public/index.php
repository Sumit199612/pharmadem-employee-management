<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/src/autoload.php';

use App\Core\Auth;
use App\Core\Database;
use App\Core\Env;
use App\Core\Exceptions\DatabaseException;
use App\Core\Session;

Env::load(dirname(__DIR__) . '/.env');
Session::start();

// If authenticated, seamlessly forward to role-specific dashboard
if (Auth::check()) {
    header('Location: ' . Auth::dashboardUrl());
    exit;
}

$pageTitle = Env::get('APP_NAME', 'Employee Management System');

$dbStatus = 'Not Connected';
$dbMessage = '';
$dbConnected = false;

try {
    $db = Database::getInstance();
    $pdo = $db->getConnection();
    $dbConnected = true;
    $dbStatus = 'Connected Successfully';
    
    $stmt = $pdo->query("SELECT VERSION() as version");
    $dbVersion = $stmt->fetch()['version'] ?? 'Unknown';
    $dbMessage = "MySQL Engine Version: " . htmlspecialchars((string) $dbVersion);
} catch (DatabaseException $e) {
    $dbStatus = 'Connection Failed';
    $dbMessage = $e->getMessage();
} catch (Throwable $e) {
    $dbStatus = 'Error';
    $dbMessage = $e->getMessage();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($pageTitle) ?></title>
    <style>
        :root {
            --bg: #090d16;
            --card-bg: #111827;
            --border: #374151;
            --text-main: #f9fafb;
            --text-muted: #9ca3af;
            --primary: #38bdf8;
            --brand: #2563eb;
            --success: #22c55e;
            --error: #ef4444;
        }
        * { box-sizing: border-box; margin: 0; padding: 0; }
        body {
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif;
            background: var(--bg);
            color: var(--text-main);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 2rem;
            background-image: 
                radial-gradient(at 0% 0%, rgba(37, 99, 235, 0.15) 0px, transparent 50%),
                radial-gradient(at 100% 100%, rgba(56, 189, 248, 0.1) 0px, transparent 50%);
        }
        .container {
            max-width: 680px;
            width: 100%;
            background: var(--card-bg);
            border: 1px solid var(--border);
            border-radius: 12px;
            padding: 2.5rem;
            box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.5);
            text-align: center;
        }
        h1 {
            font-size: 1.75rem;
            margin-bottom: 0.5rem;
            color: var(--primary);
        }
        p.subtitle {
            color: var(--text-muted);
            margin-bottom: 2rem;
            font-size: 0.95rem;
        }
        .status-badge {
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            padding: 0.4rem 1rem;
            border-radius: 9999px;
            font-size: 0.85rem;
            font-weight: 600;
            margin-bottom: 1.5rem;
        }
        .status-badge.success {
            background: rgba(34, 197, 94, 0.15);
            color: var(--success);
            border: 1px solid rgba(34, 197, 94, 0.3);
        }
        .status-badge.error {
            background: rgba(239, 68, 68, 0.15);
            color: var(--error);
            border: 1px solid rgba(239, 68, 68, 0.3);
        }
        .cta-box {
            margin: 2rem 0;
            display: flex;
            justify-content: center;
            gap: 1rem;
        }
        .btn-primary {
            display: inline-block;
            background: var(--brand);
            color: #fff;
            padding: 0.8rem 1.8rem;
            border-radius: 6px;
            text-decoration: none;
            font-weight: 600;
            transition: background 0.15s ease;
        }
        .btn-primary:hover { background: #1d4ed8; }
        .details-box {
            background: rgba(15, 23, 42, 0.6);
            border: 1px solid var(--border);
            border-radius: 8px;
            padding: 1rem;
            font-family: ui-monospace, monospace;
            font-size: 0.8rem;
            color: var(--text-muted);
            text-align: left;
        }
    </style>
</head>
<body>
    <div class="container">
        <h1><?= htmlspecialchars($pageTitle) ?></h1>
        <p class="subtitle">Vanilla PHP (OOP + PDO) &amp; MySQL Enterprise Platform</p>

        <div class="status-badge <?= $dbConnected ? 'success' : 'error' ?>">
            <span>●</span> Database Status: <?= htmlspecialchars($dbStatus) ?>
        </div>

        <div class="cta-box">
            <a href="/login.php" class="btn-primary">Go to Login Portal &rarr;</a>
        </div>

        <div class="details-box">
            <div><strong>Host:</strong> <?= htmlspecialchars(Env::get('DB_HOST', '127.0.0.1')) ?>:<?= htmlspecialchars((string)Env::get('DB_PORT', 3306)) ?></div>
            <div><strong>Database:</strong> <?= htmlspecialchars(Env::get('DB_NAME', 'employee_management_db')) ?></div>
            <div><strong>Diagnostic:</strong> <?= htmlspecialchars($dbMessage) ?></div>
        </div>
    </div>
</body>
</html>
