<?php

declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/src/autoload.php';

use App\Core\Auth;
use App\Core\Csrf;
use App\Core\Env;
use App\Core\Session;
use App\Models\DailyUpdate;
use App\Models\Evaluation;
use App\Models\Task;

Env::load(dirname(__DIR__, 2) . '/.env');
Session::start();

// Enforce RBAC: Employee, Manager, Admin allowed
Auth::requireRole(['Employee', 'Manager', 'Admin']);

$user = Auth::user();
$userId = (int) $user['id'];
$todayDate = date('Y-m-d');

$flashSuccess = Session::getFlash('success');
$flashError = Session::getFlash('error');

// Handle Form Submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    $token = $_POST['csrf_token'] ?? '';

    if (!Csrf::validate($token)) {
        Session::setFlash('error', 'Security verification failed (invalid CSRF token). Please try again.');
        header('Location: /employee/dashboard.php');
        exit;
    }

    // Action 1: Update Task Status
    if ($action === 'update_task_status') {
        $taskId = (int) ($_POST['task_id'] ?? 0);
        $newStatus = trim($_POST['status'] ?? '');

        if ($taskId > 0 && in_array($newStatus, ['Pending', 'In Progress', 'Completed'], true)) {
            $updated = Task::updateStatusByEmployee($taskId, $userId, $newStatus);
            if ($updated) {
                Session::setFlash('success', "Task #{$taskId} status updated to '{$newStatus}'.");
            } else {
                Session::setFlash('error', "Could not update task. Please ensure you are assigned to this task.");
            }
        } else {
            Session::setFlash('error', 'Invalid task or status selection.');
        }

        $activeTab = $_POST['redirect_tab'] ?? 'tasks';
        header("Location: /employee/dashboard.php?tab={$activeTab}");
        exit;
    }

    // Action 2: Submit / Edit Daily Work Update
    if ($action === 'submit_daily_update') {
        $todoTasks = trim($_POST['todo_tasks'] ?? '');
        $completedWork = trim($_POST['completed_work'] ?? '');
        $pendingBlockers = trim($_POST['pending_blockers'] ?? '');

        if (empty($todoTasks) || empty($completedWork)) {
            Session::setFlash('error', 'Both "To-Do Items" and "Work Completed" fields are required.');
        } else {
            $saved = DailyUpdate::save($userId, $todayDate, $todoTasks, $completedWork, $pendingBlockers);
            if ($saved) {
                Session::setFlash('success', 'Your daily work update for today has been recorded successfully!');
            } else {
                Session::setFlash('error', 'Failed to save daily work update. Please try again.');
            }
        }

        header('Location: /employee/dashboard.php?tab=update');
        exit;
    }
}

// Read current UI state
$currentTab = $_GET['tab'] ?? 'tasks';
if (!in_array($currentTab, ['tasks', 'update', 'history'], true)) {
    $currentTab = 'tasks';
}

$taskStatusFilter = $_GET['filter'] ?? null;
if ($taskStatusFilter === 'All' || !in_array($taskStatusFilter, ['Pending', 'In Progress', 'Completed', 'Overdue'], true)) {
    $taskStatusFilter = null;
}

// Fetch Data for Views
$taskStats = Task::getStatsByEmployee($userId);
$tasks = Task::getByEmployee($userId, $taskStatusFilter);
$todayUpdate = DailyUpdate::getByDate($userId, $todayDate);
$historyUpdates = DailyUpdate::getHistory($userId, 20);
$evaluations = Evaluation::getByEmployee($userId);
$scoreMetrics = Evaluation::getAverageScores($userId);
?>
<!DOCTYPE html>
<html lang="en" class="h-full bg-slate-950 text-slate-100">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Employee Workspace &mdash; PharmaDEM Solutions</title>
    <!-- Tailwind CSS via official CDN -->
    <script src="https://cdn.tailwindcss.com"></script>
    <script>
        tailwind.config = {
            darkMode: 'class',
            theme: {
                extend: {
                    colors: {
                        brand: {
                            50: '#eff6ff',
                            500: '#3b82f6',
                            600: '#2563eb',
                            700: '#1d4ed8',
                        }
                    }
                }
            }
        }
    </script>
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap');
        body { font-family: 'Inter', sans-serif; }
    </style>
</head>
<body class="min-h-full flex flex-col bg-slate-950 text-slate-200 antialiased selection:bg-blue-600 selection:text-white">

    <!-- Top Navigation Bar -->
    <header class="sticky top-0 z-40 w-full border-b border-slate-800 bg-slate-900/90 backdrop-blur">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 flex items-center justify-between h-16">
            <div class="flex items-center space-x-4">
                <a href="/employee/dashboard.php" class="flex items-center space-x-2 text-white font-bold text-lg tracking-tight">
                    <span class="w-8 h-8 rounded-lg bg-blue-600 flex items-center justify-center text-white font-black text-sm shadow-md shadow-blue-500/20">PD</span>
                    <span>PharmaDEM <span class="text-blue-400 font-normal text-xs uppercase tracking-wider ml-1">Workspace</span></span>
                </a>
                
                <?php if ($user['role'] === 'Admin' || $user['role'] === 'Manager'): ?>
                    <div class="hidden md:flex items-center pl-4 border-l border-slate-800 space-x-2 text-xs">
                        <?php if ($user['role'] === 'Admin'): ?>
                            <a href="/admin/dashboard.php" class="px-2 py-1 rounded bg-slate-800 text-slate-300 hover:text-white hover:bg-slate-700 transition">Admin Center</a>
                        <?php endif; ?>
                        <a href="/manager/dashboard.php" class="px-2 py-1 rounded bg-slate-800 text-slate-300 hover:text-white hover:bg-slate-700 transition">Manager Portal</a>
                    </div>
                <?php endif; ?>
            </div>

            <!-- Profile Info & Logout -->
            <div class="flex items-center space-x-4">
                <div class="hidden sm:flex flex-col text-right">
                    <span class="text-sm font-semibold text-slate-100"><?= htmlspecialchars($user['name']) ?></span>
                    <span class="text-xs text-slate-400"><?= htmlspecialchars($user['department_name']) ?> &bull; <span class="text-blue-400 font-medium"><?= htmlspecialchars($user['role']) ?></span></span>
                </div>
                <div class="w-8 h-8 rounded-full bg-slate-800 border border-slate-700 flex items-center justify-center text-blue-400 font-bold text-xs">
                    <?= htmlspecialchars(strtoupper(substr($user['name'], 0, 2))) ?>
                </div>
                <a href="/logout.php" class="inline-flex items-center px-3 py-1.5 border border-red-500/30 text-xs font-medium rounded-md text-red-400 bg-red-500/10 hover:bg-red-500/20 transition">
                    Sign Out
                </a>
            </div>
        </div>
    </header>

    <!-- Main Content Container -->
    <main class="flex-1 max-w-7xl w-full mx-auto px-4 sm:px-6 lg:px-8 py-8 space-y-6">

        <!-- Flash Toast Messages -->
        <?php if ($flashSuccess): ?>
            <div class="flex items-center p-4 rounded-lg bg-emerald-950/60 border border-emerald-800 text-emerald-300 text-sm shadow-sm animate-fade">
                <svg class="w-5 h-5 mr-3 text-emerald-400 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
                <span><?= htmlspecialchars($flashSuccess) ?></span>
            </div>
        <?php endif; ?>

        <?php if ($flashError): ?>
            <div class="flex items-center p-4 rounded-lg bg-red-950/60 border border-red-800 text-red-300 text-sm shadow-sm animate-fade">
                <svg class="w-5 h-5 mr-3 text-red-400 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
                <span><?= htmlspecialchars($flashError) ?></span>
            </div>
        <?php endif; ?>

        <!-- Quick Metrics Overview Cards -->
        <section class="grid grid-cols-2 md:grid-cols-4 gap-4">
            <div class="bg-slate-900 border border-slate-800 rounded-xl p-4 shadow-sm">
                <div class="text-xs font-medium text-slate-400 uppercase tracking-wider">Assigned Tasks</div>
                <div class="mt-2 flex items-baseline justify-between">
                    <span class="text-2xl font-bold text-white"><?= (int)$taskStats['total'] ?></span>
                    <span class="text-xs text-amber-400"><?= (int)$taskStats['pending'] ?> pending</span>
                </div>
            </div>

            <div class="bg-slate-900 border border-slate-800 rounded-xl p-4 shadow-sm">
                <div class="text-xs font-medium text-slate-400 uppercase tracking-wider">In Progress</div>
                <div class="mt-2 flex items-baseline justify-between">
                    <span class="text-2xl font-bold text-blue-400"><?= (int)$taskStats['in_progress'] ?></span>
                    <span class="text-xs text-emerald-400"><?= (int)$taskStats['completed'] ?> done</span>
                </div>
            </div>

            <div class="bg-slate-900 border border-slate-800 rounded-xl p-4 shadow-sm">
                <div class="text-xs font-medium text-slate-400 uppercase tracking-wider">Today's Work Update</div>
                <div class="mt-2 flex items-center justify-between">
                    <?php if ($todayUpdate): ?>
                        <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-semibold bg-emerald-500/20 text-emerald-300 border border-emerald-500/30">
                            <?= htmlspecialchars($todayUpdate['status']) ?>
                        </span>
                        <span class="text-xs text-slate-400">Filed today</span>
                    <?php else: ?>
                        <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-semibold bg-amber-500/20 text-amber-300 border border-amber-500/30">
                            Pending
                        </span>
                        <a href="?tab=update" class="text-xs text-blue-400 hover:underline">Submit now</a>
                    <?php endif; ?>
                </div>
            </div>

            <div class="bg-slate-900 border border-slate-800 rounded-xl p-4 shadow-sm">
                <div class="text-xs font-medium text-slate-400 uppercase tracking-wider">Performance Rating</div>
                <div class="mt-2 flex items-baseline justify-between">
                    <span class="text-2xl font-bold text-purple-400">
                        <?= $scoreMetrics['avg_overall'] > 0 ? htmlspecialchars((string)$scoreMetrics['avg_overall']) : 'N/A' ?>
                    </span>
                    <span class="text-xs text-slate-400"><?= (int)$scoreMetrics['total_evaluations'] ?> evaluations</span>
                </div>
            </div>
        </section>

        <!-- Navigation Tabs -->
        <div class="border-b border-slate-800">
            <nav class="flex space-x-6" aria-label="Tabs">
                <a href="?tab=tasks" class="<?= $currentTab === 'tasks' ? 'border-blue-500 text-blue-400' : 'border-transparent text-slate-400 hover:text-slate-200 hover:border-slate-700' ?> whitespace-nowrap py-3 px-1 border-b-2 font-medium text-sm flex items-center space-x-2">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-6 9l2 2 4-4"></path></svg>
                    <span>Task Board &amp; Status</span>
                    <span class="ml-2 py-0.5 px-2 text-xs rounded-full bg-slate-800 text-slate-300"><?= count($tasks) ?></span>
                </a>

                <a href="?tab=update" class="<?= $currentTab === 'update' ? 'border-blue-500 text-blue-400' : 'border-transparent text-slate-400 hover:text-slate-200 hover:border-slate-700' ?> whitespace-nowrap py-3 px-1 border-b-2 font-medium text-sm flex items-center space-x-2">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"></path></svg>
                    <span>Daily Work Update</span>
                    <?php if (!$todayUpdate): ?>
                        <span class="w-2 h-2 rounded-full bg-amber-400 animate-pulse"></span>
                    <?php endif; ?>
                </a>

                <a href="?tab=history" class="<?= $currentTab === 'history' ? 'border-blue-500 text-blue-400' : 'border-transparent text-slate-400 hover:text-slate-200 hover:border-slate-700' ?> whitespace-nowrap py-3 px-1 border-b-2 font-medium text-sm flex items-center space-x-2">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
                    <span>Submission History &amp; Feedback</span>
                </a>
            </nav>
        </div>

        <!-- Tasks Tab -->
        <?php if ($currentTab === 'tasks'): ?>
            <div class="space-y-4">
                <!-- Status Filter Bar -->
                <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3 bg-slate-900 border border-slate-800 p-3 rounded-xl">
                    <div class="flex items-center space-x-1 overflow-x-auto text-xs">
                        <span class="text-slate-400 font-medium mr-2">Filter:</span>
                        <?php 
                        $filterOptions = ['All' => 'All Tasks', 'Pending' => 'Pending', 'In Progress' => 'In Progress', 'Completed' => 'Completed'];
                        foreach ($filterOptions as $fKey => $fLabel): 
                            $isActive = ($taskStatusFilter === null && $fKey === 'All') || ($taskStatusFilter === $fKey);
                        ?>
                            <a href="?tab=tasks<?= $fKey !== 'All' ? "&filter={$fKey}" : '' ?>" 
                               class="px-3 py-1.5 rounded-lg font-medium transition <?= $isActive ? 'bg-blue-600 text-white shadow-sm' : 'text-slate-400 hover:text-white hover:bg-slate-800' ?>">
                                <?= $fLabel ?>
                            </a>
                        <?php endforeach; ?>
                    </div>
                    <div class="text-xs text-slate-400">
                        Showing <?= count($tasks) ?> task(s)
                    </div>
                </div>

                <!-- Task Cards Grid -->
                <?php if (empty($tasks)): ?>
                    <div class="text-center py-16 bg-slate-900 border border-slate-800 rounded-xl">
                        <svg class="w-12 h-12 text-slate-600 mx-auto mb-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"></path></svg>
                        <h3 class="text-sm font-semibold text-slate-300">No tasks found</h3>
                        <p class="text-xs text-slate-500 mt-1">There are no tasks matching the selected filter criteria.</p>
                    </div>
                <?php else: ?>
                    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
                        <?php foreach ($tasks as $task): 
                            $isOverdue = ($task['status'] !== 'Completed' && strtotime($task['due_date']) < strtotime(date('Y-m-d')));
                            $priorityColor = match ($task['priority']) {
                                'Urgent' => 'bg-red-500/10 text-red-400 border-red-500/30',
                                'High'   => 'bg-amber-500/10 text-amber-400 border-amber-500/30',
                                'Medium' => 'bg-blue-500/10 text-blue-400 border-blue-500/30',
                                default  => 'bg-slate-500/10 text-slate-400 border-slate-500/30',
                            };
                            $statusColor = match ($task['status']) {
                                'Completed'   => 'bg-emerald-500/10 text-emerald-400 border-emerald-500/30',
                                'In Progress' => 'bg-blue-500/10 text-blue-400 border-blue-500/30',
                                default       => 'bg-amber-500/10 text-amber-400 border-amber-500/30',
                            };
                        ?>
                            <div class="bg-slate-900 border border-slate-800 rounded-xl p-5 flex flex-col justify-between hover:border-slate-700 transition shadow-sm">
                                <div>
                                    <div class="flex items-center justify-between gap-2 mb-2">
                                        <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-semibold border <?= $priorityColor ?>">
                                            <?= htmlspecialchars($task['priority']) ?>
                                        </span>
                                        <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-semibold border <?= $statusColor ?>">
                                            <?= htmlspecialchars($task['status']) ?>
                                        </span>
                                    </div>

                                    <h4 class="text-base font-semibold text-white mb-1 leading-snug">
                                        <?= htmlspecialchars($task['title']) ?>
                                    </h4>

                                    <p class="text-xs text-slate-400 line-clamp-3 mb-4">
                                        <?= htmlspecialchars($task['description'] ?: 'No additional description provided.') ?>
                                    </p>
                                </div>

                                <div class="pt-3 border-t border-slate-800/80 space-y-3">
                                    <div class="flex items-center justify-between text-xs text-slate-400">
                                        <span class="flex items-center">
                                            <svg class="w-3.5 h-3.5 mr-1 text-slate-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"></path></svg>
                                            Due: <strong class="ml-1 <?= $isOverdue ? 'text-red-400 font-semibold' : 'text-slate-300' ?>"><?= htmlspecialchars($task['due_date']) ?></strong>
                                        </span>
                                        <span class="truncate max-w-[120px]" title="Assigned by <?= htmlspecialchars($task['assigned_by_name'] ?? 'Manager') ?>">
                                            By <?= htmlspecialchars($task['assigned_by_name'] ?? 'Manager') ?>
                                        </span>
                                    </div>

                                    <!-- Quick Status Update Form -->
                                    <form method="POST" action="/employee/dashboard.php" class="flex items-center space-x-2 pt-1">
                                        <?= Csrf::field() ?>
                                        <input type="hidden" name="action" value="update_task_status">
                                        <input type="hidden" name="task_id" value="<?= (int) $task['id'] ?>">
                                        <input type="hidden" name="redirect_tab" value="tasks">

                                        <label for="status_<?= (int) $task['id'] ?>" class="sr-only">Update Status</label>
                                        <select id="status_<?= (int) $task['id'] ?>" name="status" class="flex-1 bg-slate-950 border border-slate-700 rounded-lg text-xs text-slate-200 py-1.5 px-2 focus:ring-1 focus:ring-blue-500 focus:outline-none">
                                            <option value="Pending" <?= $task['status'] === 'Pending' ? 'selected' : '' ?>>Pending</option>
                                            <option value="In Progress" <?= $task['status'] === 'In Progress' ? 'selected' : '' ?>>In Progress</option>
                                            <option value="Completed" <?= $task['status'] === 'Completed' ? 'selected' : '' ?>>Completed</option>
                                        </select>
                                        
                                        <button type="submit" class="px-2.5 py-1.5 bg-blue-600 hover:bg-blue-500 text-white text-xs font-semibold rounded-lg transition shadow-sm">
                                            Update
                                        </button>
                                    </form>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
        <?php endif; ?>

        <!-- Daily Update Tab -->
        <?php if ($currentTab === 'update'): ?>
            <div class="max-w-3xl mx-auto space-y-6">
                <!-- Status Banner if Already Submitted -->
                <?php if ($todayUpdate): ?>
                    <div class="bg-slate-900 border border-slate-800 rounded-xl p-5 shadow-sm">
                        <div class="flex items-center justify-between">
                            <div class="flex items-center space-x-3">
                                <span class="w-3 h-3 rounded-full bg-emerald-500"></span>
                                <div>
                                    <h4 class="text-sm font-semibold text-white">Update Filed for Today (<?= htmlspecialchars($todayDate) ?>)</h4>
                                    <p class="text-xs text-slate-400">Current Status: <strong class="text-emerald-400"><?= htmlspecialchars($todayUpdate['status']) ?></strong></p>
                                </div>
                            </div>
                            <span class="text-xs text-slate-500">Last updated: <?= htmlspecialchars($todayUpdate['updated_at']) ?></span>
                        </div>

                        <?php if (!empty($todayUpdate['review_notes'])): ?>
                            <div class="mt-4 p-3 rounded-lg bg-blue-950/40 border border-blue-900/50 text-xs text-blue-200">
                                <div class="font-semibold text-blue-400 mb-1">Manager Feedback:</div>
                                <p><?= nl2br(htmlspecialchars($todayUpdate['review_notes'])) ?></p>
                            </div>
                        <?php endif; ?>
                    </div>
                <?php endif; ?>

                <div class="bg-slate-900 border border-slate-800 rounded-xl p-6 shadow-sm">
                    <div class="border-b border-slate-800 pb-4 mb-6">
                        <h3 class="text-lg font-semibold text-white">Daily Work Update</h3>
                        <p class="text-xs text-slate-400 mt-1">
                            Document today's deliverables for <strong class="text-blue-400"><?= htmlspecialchars(date('l, F j, Y')) ?></strong>. 
                            Your manager uses these notes for review and monthly performance evaluation.
                        </p>
                    </div>

                    <form method="POST" action="/employee/dashboard.php" class="space-y-5">
                        <?= Csrf::field() ?>
                        <input type="hidden" name="action" value="submit_daily_update">

                        <div>
                            <label for="todo_tasks" class="block text-xs font-semibold text-slate-300 uppercase tracking-wider mb-2">
                                1. To-Do Items for Today <span class="text-red-400">*</span>
                            </label>
                            <p class="text-xs text-slate-500 mb-2">List the planned activities or batch tasks you intended to address today.</p>
                            <textarea 
                                id="todo_tasks" 
                                name="todo_tasks" 
                                rows="3" 
                                required
                                placeholder="- Audit Line 4 batch packaging logs&#10;- Conduct environmental sterility monitoring"
                                class="w-full bg-slate-950 border border-slate-700 rounded-lg text-sm text-slate-100 p-3 focus:ring-2 focus:ring-blue-500 focus:outline-none placeholder-slate-600 transition"
                            ><?= htmlspecialchars($todayUpdate['todo_tasks'] ?? '') ?></textarea>
                        </div>

                        <div>
                            <label for="completed_work" class="block text-xs font-semibold text-slate-300 uppercase tracking-wider mb-2">
                                2. Work Completed Today <span class="text-red-400">*</span>
                            </label>
                            <p class="text-xs text-slate-500 mb-2">Specify the deliverables, checks, or reports you successfully finished.</p>
                            <textarea 
                                id="completed_work" 
                                name="completed_work" 
                                rows="4" 
                                required
                                placeholder="- Completed sterilization records review for autoclave #3&#10;- Calibrated balance #LAB-04 and logged deviation zero"
                                class="w-full bg-slate-950 border border-slate-700 rounded-lg text-sm text-slate-100 p-3 focus:ring-2 focus:ring-blue-500 focus:outline-none placeholder-slate-600 transition"
                            ><?= htmlspecialchars($todayUpdate['completed_work'] ?? '') ?></textarea>
                        </div>

                        <div>
                            <label for="pending_blockers" class="block text-xs font-semibold text-slate-300 uppercase tracking-wider mb-2">
                                3. Pending Items / Blockers <span class="text-slate-500 font-normal">(Optional)</span>
                            </label>
                            <p class="text-xs text-slate-500 mb-2">Highlight any obstacles, pending supervisor approvals, or material shortages.</p>
                            <textarea 
                                id="pending_blockers" 
                                name="pending_blockers" 
                                rows="3" 
                                placeholder="Awaiting QA supervisor sign-off on change control request #CR-104."
                                class="w-full bg-slate-950 border border-slate-700 rounded-lg text-sm text-slate-100 p-3 focus:ring-2 focus:ring-blue-500 focus:outline-none placeholder-slate-600 transition"
                            ><?= htmlspecialchars($todayUpdate['pending_blockers'] ?? '') ?></textarea>
                        </div>

                        <div class="pt-4 border-t border-slate-800 flex items-center justify-between">
                            <span class="text-xs text-slate-500">
                                Date: <?= htmlspecialchars($todayDate) ?> &bull; Timezone: <?= htmlspecialchars(date_default_timezone_get()) ?>
                            </span>
                            <button type="submit" class="px-5 py-2.5 bg-blue-600 hover:bg-blue-500 text-white font-semibold text-sm rounded-lg transition shadow-md shadow-blue-600/20">
                                <?= $todayUpdate ? 'Update Daily Submission' : 'Submit Daily Work Update' ?>
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        <?php endif; ?>

        <!-- History Tab -->
        <?php if ($currentTab === 'history'): ?>
            <div class="space-y-8">
                <!-- Section 3A: Performance Evaluations & Scores -->
                <div class="bg-slate-900 border border-slate-800 rounded-xl overflow-hidden shadow-sm">
                    <div class="p-5 border-b border-slate-800 flex items-center justify-between">
                        <div>
                            <h3 class="text-base font-semibold text-white">Performance Evaluation History</h3>
                            <p class="text-xs text-slate-400 mt-0.5">Formal evaluations and scores issued by management.</p>
                        </div>
                        <?php if (!empty($evaluations)): ?>
                            <span class="text-xs text-slate-400">Total Evaluations: <?= count($evaluations) ?></span>
                        <?php endif; ?>
                    </div>

                    <?php if (empty($evaluations)): ?>
                        <div class="text-center py-12 text-slate-500 text-xs">
                            No performance evaluations recorded yet. Evaluations are generated by managers at the end of each review cycle.
                        </div>
                    <?php else: ?>
                        <div class="divide-y divide-slate-800">
                            <?php foreach ($evaluations as $eval): ?>
                                <div class="p-5 hover:bg-slate-800/40 transition">
                                    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3 mb-3">
                                        <div class="flex items-center space-x-3">
                                            <span class="px-2.5 py-1 rounded-md text-xs font-bold bg-purple-500/10 text-purple-400 border border-purple-500/30">
                                                Cycle: <?= htmlspecialchars($eval['evaluation_period']) ?>
                                            </span>
                                            <span class="text-xs text-slate-400">
                                                Evaluator: <strong class="text-slate-200"><?= htmlspecialchars($eval['evaluator_name']) ?></strong>
                                            </span>
                                        </div>
                                        <div class="flex items-center space-x-2">
                                            <span class="text-xs text-slate-400">Overall Rating:</span>
                                            <span class="text-base font-black text-purple-400 bg-purple-950/60 px-2 py-0.5 rounded border border-purple-800/50">
                                                <?= htmlspecialchars((string) $eval['overall_score']) ?> / 100
                                            </span>
                                        </div>
                                    </div>

                                    <!-- Score Breakdown Metrics -->
                                    <div class="grid grid-cols-3 gap-3 bg-slate-950/60 p-3 rounded-lg border border-slate-800 text-center mb-3">
                                        <div>
                                            <div class="text-[10px] text-slate-500 uppercase tracking-wider">Task Completion</div>
                                            <div class="text-sm font-semibold text-slate-200 mt-0.5"><?= htmlspecialchars((string)$eval['task_completion_score']) ?>%</div>
                                        </div>
                                        <div>
                                            <div class="text-[10px] text-slate-500 uppercase tracking-wider">Punctuality</div>
                                            <div class="text-sm font-semibold text-slate-200 mt-0.5"><?= htmlspecialchars((string)$eval['punctuality_score']) ?>%</div>
                                        </div>
                                        <div>
                                            <div class="text-[10px] text-slate-500 uppercase tracking-wider">Work Quality</div>
                                            <div class="text-sm font-semibold text-slate-200 mt-0.5"><?= htmlspecialchars((string)$eval['quality_score']) ?>%</div>
                                        </div>
                                    </div>

                                    <?php if (!empty($eval['manager_remarks'])): ?>
                                        <div class="text-xs text-slate-300 bg-slate-900 border border-slate-800 p-3 rounded-lg">
                                            <strong class="text-slate-400 block mb-1">Remarks &amp; Development Goals:</strong>
                                            <?= nl2br(htmlspecialchars($eval['manager_remarks'])) ?>
                                        </div>
                                    <?php endif; ?>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>

                <!-- Section 3B: Chronological Daily Work Updates History -->
                <div class="bg-slate-900 border border-slate-800 rounded-xl overflow-hidden shadow-sm">
                    <div class="p-5 border-b border-slate-800">
                        <h3 class="text-base font-semibold text-white">Daily Updates Log</h3>
                        <p class="text-xs text-slate-400 mt-0.5">Chronological record of your past daily work submissions.</p>
                    </div>

                    <?php if (empty($historyUpdates)): ?>
                        <div class="text-center py-12 text-slate-500 text-xs">
                            No daily submissions found in history.
                        </div>
                    <?php else: ?>
                        <div class="overflow-x-auto">
                            <table class="w-full text-left text-xs border-collapse">
                                <thead>
                                    <tr class="bg-slate-950/80 border-b border-slate-800 text-slate-400 font-medium">
                                        <th class="py-3 px-4">Date</th>
                                        <th class="py-3 px-4">Completed Work Summary</th>
                                        <th class="py-3 px-4">Pending / Blockers</th>
                                        <th class="py-3 px-4">Status</th>
                                        <th class="py-3 px-4">Manager Review</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-slate-800">
                                    <?php foreach ($historyUpdates as $row): 
                                        $statusBadge = match ($row['status']) {
                                            'Reviewed'  => 'bg-emerald-500/10 text-emerald-400 border-emerald-500/30',
                                            default     => 'bg-blue-500/10 text-blue-400 border-blue-500/30',
                                        };
                                    ?>
                                        <tr class="hover:bg-slate-800/40 transition">
                                            <td class="py-3 px-4 font-semibold text-white whitespace-nowrap">
                                                <?= htmlspecialchars($row['update_date']) ?>
                                            </td>
                                            <td class="py-3 px-4 max-w-xs text-slate-300">
                                                <div class="line-clamp-2" title="<?= htmlspecialchars($row['completed_work']) ?>">
                                                    <?= nl2br(htmlspecialchars($row['completed_work'])) ?>
                                                </div>
                                            </td>
                                            <td class="py-3 px-4 text-slate-400">
                                                <?= htmlspecialchars($row['pending_blockers'] ?: 'None') ?>
                                            </td>
                                            <td class="py-3 px-4 whitespace-nowrap">
                                                <span class="inline-flex items-center px-2 py-0.5 rounded text-[11px] font-semibold border <?= $statusBadge ?>">
                                                    <?= htmlspecialchars($row['status']) ?>
                                                </span>
                                            </td>
                                            <td class="py-3 px-4 text-slate-400">
                                                <?php if (!empty($row['review_notes'])): ?>
                                                    <span class="text-blue-300" title="<?= htmlspecialchars($row['review_notes']) ?>">
                                                        <?= htmlspecialchars(mb_strimwidth($row['review_notes'], 0, 35, '...')) ?>
                                                    </span>
                                                <?php elseif ($row['status'] === 'Reviewed'): ?>
                                                    <span class="text-slate-500 italic">Reviewed without notes</span>
                                                <?php else: ?>
                                                    <span class="text-slate-600">Pending review</span>
                                                <?php endif; ?>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        <?php endif; ?>

    </main>

    <!-- Footer -->
    <footer class="border-t border-slate-800 py-4 text-center text-xs text-slate-500">
        &copy; <?= date('Y') ?> PharmaDEM Solutions. Secure Employee Daily Work Update &amp; Performance Evaluation System.
    </footer>

</body>
</html>
