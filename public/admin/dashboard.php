<?php

declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/src/autoload.php';

use App\Core\Auth;
use App\Core\Csrf;
use App\Core\Database;
use App\Core\Env;
use App\Core\Session;
use App\Models\DailyUpdate;
use App\Models\Task;
use App\Models\User;

Env::load(dirname(__DIR__, 2) . '/.env');
Session::start();

// Strict RBAC: Admin ONLY
Auth::requireRole('Admin');

$currentUser = Auth::user();

// Run automated overdue tracker
Task::updateOverdueTasks();

$flashSuccess = Session::getFlash('success');
$flashError = Session::getFlash('error');

$db = Database::getInstance();
$pdo = $db->getConnection();

// System-wide metrics
$totalUsers = (int) $pdo->query("SELECT COUNT(*) FROM users")->fetchColumn();
$totalDepts = (int) $pdo->query("SELECT COUNT(*) FROM departments")->fetchColumn();
$teamMetrics = Task::getTeamMetrics(null);
$subMetrics = DailyUpdate::getTodaySubmissionMetrics(null);

// Fetch departments for overview
$departments = $pdo->query("
    SELECT d.*, 
           COUNT(u.id) AS user_count 
    FROM departments d
    LEFT JOIN users u ON u.department_id = d.id
    GROUP BY d.id
    ORDER BY d.id ASC
")->fetchAll();

// Fetch users
$users = $pdo->query("
    SELECT u.*, d.name AS dept_name
    FROM users u
    LEFT JOIN departments d ON u.department_id = d.id
    ORDER BY u.id ASC
")->fetchAll();
?>
<!DOCTYPE html>
<html lang="en" class="h-full bg-slate-950 text-slate-100">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Center &mdash; PharmaDEM Solutions</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap');
        body { font-family: 'Inter', sans-serif; }
    </style>
</head>
<body class="min-h-full flex flex-col bg-slate-950 text-slate-200 antialiased selection:bg-red-600 selection:text-white">

    <!-- Top Navigation Bar -->
    <header class="sticky top-0 z-40 w-full border-b border-slate-800 bg-slate-900/90 backdrop-blur">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 flex items-center justify-between h-16">
            <div class="flex items-center space-x-4">
                <a href="/admin/dashboard.php" class="flex items-center space-x-2 text-white font-bold text-lg tracking-tight">
                    <span class="w-8 h-8 rounded-lg bg-red-600 flex items-center justify-center text-white font-black text-sm shadow-md shadow-red-500/20">AD</span>
                    <span>PharmaDEM <span class="text-red-400 font-normal text-xs uppercase tracking-wider ml-1">Admin Center</span></span>
                </a>
                
                <div class="hidden md:flex items-center pl-4 border-l border-slate-800 space-x-2 text-xs">
                    <a href="/manager/dashboard.php" class="px-2.5 py-1 rounded bg-slate-800 text-slate-300 hover:text-white hover:bg-slate-700 transition">Manager Hub</a>
                    <a href="/manager/dashboard.php?tab=analytics" class="px-2.5 py-1 rounded bg-slate-800 text-slate-300 hover:text-white hover:bg-slate-700 transition">Performance Analytics</a>
                    <a href="/manager/dashboard.php?tab=exports" class="px-2.5 py-1 rounded bg-amber-600/20 text-amber-300 border border-amber-600/40 hover:bg-amber-600/30 transition">Reports &amp; Exports</a>
                    <a href="/employee/dashboard.php" class="px-2.5 py-1 rounded bg-slate-800 text-slate-300 hover:text-white hover:bg-slate-700 transition">Employee Portal</a>
                </div>
            </div>

            <!-- Profile Info & Logout -->
            <div class="flex items-center space-x-4">
                <div class="hidden sm:flex flex-col text-right">
                    <span class="text-sm font-semibold text-slate-100"><?= htmlspecialchars($currentUser['name']) ?></span>
                    <span class="text-xs text-slate-400"><?= htmlspecialchars($currentUser['department_name']) ?> &bull; <span class="text-red-400 font-medium"><?= htmlspecialchars($currentUser['role']) ?></span></span>
                </div>
                <div class="w-8 h-8 rounded-full bg-slate-800 border border-slate-700 flex items-center justify-center text-red-400 font-bold text-xs">
                    AD
                </div>
                <a href="/logout.php" class="inline-flex items-center px-3 py-1.5 border border-red-500/30 text-xs font-medium rounded-md text-red-400 bg-red-500/10 hover:bg-red-500/20 transition">
                    Sign Out
                </a>
            </div>
        </div>
    </header>

    <!-- Main Content Container -->
    <main class="flex-1 max-w-7xl w-full mx-auto px-4 sm:px-6 lg:px-8 py-8 space-y-6">

        <?php if ($flashSuccess): ?>
            <div class="flex items-center p-4 rounded-lg bg-emerald-950/60 border border-emerald-800 text-emerald-300 text-sm shadow-sm">
                <svg class="w-5 h-5 mr-3 text-emerald-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
                <span><?= htmlspecialchars($flashSuccess) ?></span>
            </div>
        <?php endif; ?>

        <?php if ($flashError): ?>
            <div class="flex items-center p-4 rounded-lg bg-red-950/60 border border-red-800 text-red-300 text-sm shadow-sm">
                <svg class="w-5 h-5 mr-3 text-red-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
                <span><?= htmlspecialchars($flashError) ?></span>
            </div>
        <?php endif; ?>

        <!-- Top Global Metric Cards -->
        <section class="grid grid-cols-2 lg:grid-cols-4 gap-4">
            <div class="bg-slate-900 border border-slate-800 rounded-xl p-4 shadow-sm">
                <div class="text-[11px] font-semibold text-slate-400 uppercase tracking-wider">System Users</div>
                <div class="mt-2 flex items-baseline justify-between">
                    <span class="text-2xl font-bold text-white"><?= $totalUsers ?></span>
                    <span class="text-xs text-slate-500"><?= $totalDepts ?> Departments</span>
                </div>
            </div>

            <div class="bg-slate-900 border border-slate-800 rounded-xl p-4 shadow-sm">
                <div class="text-[11px] font-semibold text-slate-400 uppercase tracking-wider">Total Tasks</div>
                <div class="mt-2 flex items-baseline justify-between">
                    <span class="text-2xl font-bold text-blue-400"><?= (int)$teamMetrics['total_tasks'] ?></span>
                    <span class="text-xs text-emerald-400"><?= (int)$teamMetrics['completed_tasks'] ?> completed</span>
                </div>
            </div>

            <div class="bg-slate-900 border <?= (int)$teamMetrics['overdue_tasks'] > 0 ? 'border-red-500/40 bg-red-950/10' : 'border-slate-800' ?> rounded-xl p-4 shadow-sm">
                <div class="text-[11px] font-semibold text-slate-400 uppercase tracking-wider">Overdue Alerts</div>
                <div class="mt-2 flex items-baseline justify-between">
                    <span class="text-2xl font-bold text-red-400"><?= (int)$teamMetrics['overdue_tasks'] ?></span>
                    <span class="text-xs text-red-400">Needs attention</span>
                </div>
            </div>

            <div class="bg-slate-900 border border-slate-800 rounded-xl p-4 shadow-sm">
                <div class="text-[11px] font-semibold text-slate-400 uppercase tracking-wider">Today's Updates</div>
                <div class="mt-2 flex items-baseline justify-between">
                    <span class="text-2xl font-bold text-emerald-400"><?= $subMetrics['submitted_today'] ?> / <?= $subMetrics['total_employees'] ?></span>
                    <span class="text-xs text-slate-400"><?= $subMetrics['compliance_percentage'] ?>% filing rate</span>
                </div>
            </div>
        </section>

        <!-- Department Breakdown Cards -->
        <section class="bg-slate-900 border border-slate-800 rounded-xl p-6 shadow-sm">
            <div class="border-b border-slate-800 pb-3 mb-4 flex items-center justify-between">
                <div>
                    <h3 class="text-base font-semibold text-white">Department Structures</h3>
                    <p class="text-xs text-slate-400 mt-0.5">Normalized organization departments and staff distributions.</p>
                </div>
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
                <?php foreach ($departments as $dept): ?>
                    <div class="bg-slate-950 border border-slate-800 rounded-lg p-4">
                        <div class="flex items-center justify-between mb-2">
                            <h4 class="text-sm font-semibold text-white"><?= htmlspecialchars($dept['name']) ?></h4>
                            <span class="text-xs text-blue-400 font-bold"><?= (int)$dept['user_count'] ?> member(s)</span>
                        </div>
                        <p class="text-xs text-slate-400"><?= htmlspecialchars($dept['description'] ?: 'No description provided.') ?></p>
                    </div>
                <?php endforeach; ?>
            </div>
        </section>

        <!-- User Accounts Management Table -->
        <section class="bg-slate-900 border border-slate-800 rounded-xl overflow-hidden shadow-sm">
            <div class="p-5 border-b border-slate-800 flex items-center justify-between">
                <div>
                    <h3 class="text-base font-semibold text-white">System User Directory &amp; RBAC Control</h3>
                    <p class="text-xs text-slate-400 mt-0.5">All registered users with active security roles.</p>
                </div>
                <a href="/manager/dashboard.php?tab=overview" class="text-xs font-semibold text-amber-400 hover:underline">
                    Go to Task Assignment &rarr;
                </a>
            </div>

            <div class="overflow-x-auto">
                <table class="w-full text-left text-xs border-collapse">
                    <thead>
                        <tr class="bg-slate-950/80 border-b border-slate-800 text-slate-400 font-medium">
                            <th class="py-3 px-4">User ID</th>
                            <th class="py-3 px-4">Name</th>
                            <th class="py-3 px-4">Email</th>
                            <th class="py-3 px-4">Role</th>
                            <th class="py-3 px-4">Department</th>
                            <th class="py-3 px-4">Status</th>
                            <th class="py-3 px-4">Registered Date</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-800">
                        <?php foreach ($users as $u): 
                            $rBadge = match ($u['role']) {
                                'Admin'    => 'bg-red-500/20 text-red-400 border-red-500/40',
                                'Manager'  => 'bg-amber-500/20 text-amber-400 border-amber-500/40',
                                default    => 'bg-blue-500/20 text-blue-400 border-blue-500/40',
                            };
                        ?>
                            <tr class="hover:bg-slate-800/40 transition">
                                <td class="py-3 px-4 font-mono text-slate-400">#<?= htmlspecialchars((string)$u['id']) ?></td>
                                <td class="py-3 px-4 font-semibold text-white"><?= htmlspecialchars($u['name']) ?></td>
                                <td class="py-3 px-4 text-slate-300"><?= htmlspecialchars($u['email']) ?></td>
                                <td class="py-3 px-4">
                                    <span class="px-2 py-0.5 rounded text-[11px] font-semibold border <?= $rBadge ?>">
                                        <?= htmlspecialchars($u['role']) ?>
                                    </span>
                                </td>
                                <td class="py-3 px-4 text-slate-300"><?= htmlspecialchars($u['dept_name'] ?? 'N/A') ?></td>
                                <td class="py-3 px-4">
                                    <span class="text-emerald-400 font-medium flex items-center">
                                        <span class="w-1.5 h-1.5 rounded-full bg-emerald-400 mr-1.5"></span>
                                        <?= htmlspecialchars($u['status']) ?>
                                    </span>
                                </td>
                                <td class="py-3 px-4 text-slate-400"><?= htmlspecialchars($u['created_at']) ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </section>

    </main>

    <!-- Footer -->
    <footer class="border-t border-slate-800 py-4 text-center text-xs text-slate-500">
        &copy; <?= date('Y') ?> PharmaDEM Solutions. Master Administration Dashboard.
    </footer>

</body>
</html>
