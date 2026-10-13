<?php

declare(strict_types=1);

/**
 * Database Seed Script for Default RBAC User Accounts
 * Can be executed via CLI: `php database/seed.php` or accessed in a dev browser.
 */

require_once dirname(__DIR__) . '/src/autoload.php';

use App\Core\Database;
use App\Core\Env;

// Load environment variables
Env::load(dirname(__DIR__) . '/.env');

$isCli = (php_sapi_name() === 'cli');

function output(string $message, bool $isCli, string $type = 'info'): void {
    if ($isCli) {
        $prefix = match ($type) {
            'success' => "[SUCCESS] ",
            'error'   => "[ERROR] ",
            default   => "[INFO] ",
        };
        echo $prefix . $message . PHP_EOL;
    } else {
        $color = match ($type) {
            'success' => '#22c55e',
            'error'   => '#ef4444',
            default   => '#38bdf8',
        };
        echo "<div style='font-family:monospace; padding:8px 12px; margin:6px 0; border-radius:6px; background:#1e293b; color:{$color}; border:1px solid #334155;'>{$message}</div>";
    }
}

try {
    $db = Database::getInstance();
    $pdo = $db->getConnection();

    output("Connected to database successfully.", $isCli, 'info');

    // 1. Seed Departments if missing
    $departments = [
        ['name' => 'Engineering', 'description' => 'Software engineering, QA and infrastructure'],
        ['name' => 'Operations', 'description' => 'Pharmaceutical QA and regulatory operations'],
        ['name' => 'Human Resources', 'description' => 'Talent acquisition, management, and compliance']
    ];

    $deptStmt = $pdo->prepare("
        INSERT INTO departments (name, description) 
        VALUES (:name, :description)
        ON DUPLICATE KEY UPDATE description = VALUES(description)
    ");

    foreach ($departments as $dept) {
        $deptStmt->execute($dept);
    }
    output("Departments seeded.", $isCli, 'success');

    // Fetch department IDs
    $engDept = $pdo->query("SELECT id FROM departments WHERE name = 'Engineering' LIMIT 1")->fetchColumn();
    $opsDept = $pdo->query("SELECT id FROM departments WHERE name = 'Operations' LIMIT 1")->fetchColumn();
    $hrDept  = $pdo->query("SELECT id FROM departments WHERE name = 'Human Resources' LIMIT 1")->fetchColumn();

    // 2. Default accounts configuration
    $defaultPassword = 'Password@123';
    $passwordHash = password_hash($defaultPassword, PASSWORD_BCRYPT, ['cost' => 12]);

    $seedUsers = [
        [
            'name'          => 'System Administrator',
            'email'         => 'admin@pharmadem.local',
            'password'      => $passwordHash,
            'role'          => 'Admin',
            'department_id' => $engDept ?: 1,
            'status'        => 'Active',
        ],
        [
            'name'          => 'Sarah Jenkins',
            'email'         => 'manager@pharmadem.local',
            'password'      => $passwordHash,
            'role'          => 'Manager',
            'department_id' => $opsDept ?: 2,
            'status'        => 'Active',
        ],
        [
            'name'          => 'Alex Mercer',
            'email'         => 'employee@pharmadem.local',
            'password'      => $passwordHash,
            'role'          => 'Employee',
            'department_id' => $engDept ?: 1,
            'status'        => 'Active',
        ],
        [
            'name'          => 'Elena Rostova',
            'email'         => 'elena.employee@pharmadem.local',
            'password'      => $passwordHash,
            'role'          => 'Employee',
            'department_id' => $hrDept ?: 3,
            'status'        => 'Active',
        ],
    ];

    $userStmt = $pdo->prepare("
        INSERT INTO users (name, email, password, role, department_id, status)
        VALUES (:name, :email, :password, :role, :department_id, :status)
        ON DUPLICATE KEY UPDATE
            name = VALUES(name),
            password = VALUES(password),
            role = VALUES(role),
            department_id = VALUES(department_id),
            status = VALUES(status)
    ");

    foreach ($seedUsers as $user) {
        $userStmt->execute($user);
        output("Seeded user [{$user['role']}]: {$user['email']} (Password: {$defaultPassword})", $isCli, 'success');
    }

    // 3. Seed Sample Tasks for Employee (Alex Mercer)
    $alexId = $pdo->query("SELECT id FROM users WHERE email = 'employee@pharmadem.local' LIMIT 1")->fetchColumn();
    $managerId = $pdo->query("SELECT id FROM users WHERE email = 'manager@pharmadem.local' LIMIT 1")->fetchColumn();

    if ($alexId && $managerId) {
        $tasks = [
            [
                'title'       => 'Audit Batch Production Records for Line 4',
                'description' => 'Verify compliance with standard operating procedures and check temperature logs.',
                'assigned_to' => $alexId,
                'assigned_by' => $managerId,
                'priority'    => 'Urgent',
                'status'      => 'In Progress',
                'due_date'    => date('Y-m-d', strtotime('+1 day')),
            ],
            [
                'title'       => 'Submit Monthly Sterility Test Summary',
                'description' => 'Compile incubation test results and flag any colony forming unit deviations.',
                'assigned_to' => $alexId,
                'assigned_by' => $managerId,
                'priority'    => 'High',
                'status'      => 'Pending',
                'due_date'    => date('Y-m-d', strtotime('+3 days')),
            ],
            [
                'title'       => 'Calibrate Analytical Balance in Quality Lab 2',
                'description' => 'Routine monthly recalibration using standard F1 reference weights.',
                'assigned_to' => $alexId,
                'assigned_by' => $managerId,
                'priority'    => 'Medium',
                'status'      => 'Completed',
                'due_date'    => date('Y-m-d', strtotime('-2 days')),
            ],
            [
                'title'       => 'Update Safety Data Sheets (SDS) Archive',
                'description' => 'Ensure all new raw chemical material data sheets are synchronized in the system.',
                'assigned_to' => $alexId,
                'assigned_by' => $managerId,
                'priority'    => 'Low',
                'status'      => 'Pending',
                'due_date'    => date('Y-m-d', strtotime('+5 days')),
            ],
        ];

        $taskStmt = $pdo->prepare("
            INSERT INTO tasks (title, description, assigned_to, assigned_by, priority, status, due_date)
            VALUES (:title, :description, :assigned_to, :assigned_by, :priority, :status, :due_date)
            ON DUPLICATE KEY UPDATE status = VALUES(status)
        ");

        foreach ($tasks as $t) {
            $taskStmt->execute($t);
        }
        output("Sample tasks seeded for employee.", $isCli, 'success');

        // 4. Seed a Sample Performance Evaluation
        $evalStmt = $pdo->prepare("
            INSERT INTO performance_evaluations 
                (employee_id, evaluator_id, evaluation_period, task_completion_score, punctuality_score, quality_score, manager_remarks)
            VALUES 
                (:emp, :mgr, :period, 92.50, 95.00, 90.00, :remarks)
            ON DUPLICATE KEY UPDATE 
                manager_remarks = VALUES(manager_remarks)
        ");
        $evalStmt->execute([
            'emp'     => $alexId,
            'mgr'     => $managerId,
            'period'  => date('Y-m', strtotime('-1 month')),
            'remarks' => 'Alex demonstrated exceptional attention to GMP compliance this cycle. Keep up the high standard on sterility documentation!'
        ]);
        output("Sample performance evaluation seeded for employee.", $isCli, 'success');
    }

    output("All default test accounts, tasks, and evaluations ready!", $isCli, 'success');

} catch (Throwable $e) {
    output("Seeding failed: " . $e->getMessage(), $isCli, 'error');
    exit(1);
}
