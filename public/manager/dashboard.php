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
use App\Models\User;

Env::load(dirname(__DIR__, 2) . '/.env');
Session::start();

// Strict RBAC: Managers and Admins only
Auth::requireRole(['Manager', 'Admin']);

$currentUser = Auth::user();
$currentUserId = (int) $currentUser['id'];

// Department scoping: Managers see their department by default (or all if Admin)
$departmentFilter = ($currentUser['role'] === 'Manager' && !empty($currentUser['department_id'])) 
    ? (int) $currentUser['department_id'] 
    : null;

// Update overdue tasks
Task::updateOverdueTasks();

$flashSuccess = Session::getFlash('success');
$flashError = Session::getFlash('error');

// Handle POST actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    $token = $_POST['csrf_token'] ?? '';

    if (!Csrf::validate($token)) {
        Session::setFlash('error', 'Security verification failed (invalid CSRF token). Please try again.');
        header('Location: /manager/dashboard.php');
        exit;
    }

    // 1. Task Assignment Engine: Create & delegate a task
    if ($action === 'create_task') {
        $title = trim($_POST['title'] ?? '');
        $description = trim($_POST['description'] ?? '');
        $assignedTo = (int) ($_POST['assigned_to'] ?? 0);
        $priority = $_POST['priority'] ?? 'Medium';
        $dueDate = trim($_POST['due_date'] ?? '');

        if (empty($title) || empty($dueDate) || $assignedTo <= 0) {
            Session::setFlash('error', 'Task Title, Assignee, and Due Date are mandatory fields.');
        } elseif (!in_array($priority, ['Low', 'Medium', 'High', 'Urgent'], true)) {
            Session::setFlash('error', 'Invalid priority level selected.');
        } else {
            $taskId = Task::create([
                'title'       => $title,
                'description' => $description,
                'assigned_to' => $assignedTo,
                'assigned_by' => $currentUserId,
                'priority'    => $priority,
                'due_date'    => $dueDate,
            ]);

            if ($taskId > 0) {
                Session::setFlash('success', "Task #{$taskId} successfully created and assigned to team member.");
            } else {
                Session::setFlash('error', 'Failed to create task. Please try again.');
            }
        }

        header('Location: /manager/dashboard.php?tab=tasks');
        exit;
    }

    // 2. Daily Updates Review: Write notes and mark as Reviewed
    if ($action === 'review_update') {
        $updateId = (int) ($_POST['update_id'] ?? 0);
        $reviewNotes = trim($_POST['review_notes'] ?? '');

        if ($updateId <= 0) {
            Session::setFlash('error', 'Invalid daily update record specified.');
        } else {
            $reviewed = DailyUpdate::reviewUpdate($updateId, $currentUserId, $reviewNotes, 'Reviewed');
            if ($reviewed) {
                Session::setFlash('success', "Daily update #{$updateId} has been successfully marked as Reviewed.");
            } else {
                Session::setFlash('error', 'Failed to save review notes.');
            }
        }

        header('Location: /manager/dashboard.php?tab=updates');
        exit;
    }

    // 3. Evaluation Form: Submit or update performance evaluation
    if ($action === 'submit_evaluation') {
        $employeeId = (int) ($_POST['employee_id'] ?? 0);
        $evaluationPeriod = trim($_POST['evaluation_period'] ?? '');
        $taskScore = (float) ($_POST['task_completion_score'] ?? -1);
        $punctualityScore = (float) ($_POST['punctuality_score'] ?? -1);
        $qualityScore = (float) ($_POST['quality_score'] ?? -1);
        $remarks = trim($_POST['manager_remarks'] ?? '');

        if ($employeeId <= 0 || empty($evaluationPeriod)) {
            Session::setFlash('error', 'Please select an employee and specify the evaluation cycle (YYYY-MM).');
        } elseif (
            $taskScore < 0 || $taskScore > 100 ||
            $punctualityScore < 0 || $punctualityScore > 100 ||
            $qualityScore < 0 || $qualityScore > 100
        ) {
            Session::setFlash('error', 'All evaluation scores must be between 0.00 and 100.00.');
        } else {
            $saved = Evaluation::createOrUpdate([
                'employee_id'           => $employeeId,
                'evaluator_id'          => $currentUserId,
                'evaluation_period'     => $evaluationPeriod,
                'task_completion_score' => $taskScore,
                'punctuality_score'     => $punctualityScore,
                'quality_score'         => $qualityScore,
                'manager_remarks'       => $remarks,
            ]);

            if ($saved) {
                Session::setFlash('success', "Performance evaluation for period {$evaluationPeriod} saved successfully.");
            } else {
                Session::setFlash('error', 'Failed to record performance evaluation. Please try again.');
            }
        }

        header('Location: /manager/dashboard.php?tab=evaluations');
        exit;
    }
}

// Active view state
$currentTab = $_GET['tab'] ?? 'overview';
$validTabs = ['overview', 'tasks', 'updates', 'analytics', 'evaluations', 'exports'];
if (!in_array($currentTab, $validTabs, true)) {
    $currentTab = 'overview';
}

$taskStatusFilter = $_GET['status_filter'] ?? null;
if ($taskStatusFilter === 'All' || !in_array($taskStatusFilter, ['Pending', 'In Progress', 'Completed', 'Overdue'], true)) {
    $taskStatusFilter = null;
}

$updateStatusFilter = $_GET['update_status'] ?? null;
if ($updateStatusFilter === 'All' || !in_array($updateStatusFilter, ['Submitted', 'Reviewed'], true)) {
    $updateStatusFilter = null;
}

// Fetch Data
$teamMetrics = Task::getTeamMetrics($departmentFilter);
$submissionMetrics = DailyUpdate::getTodaySubmissionMetrics($departmentFilter);
$assignableUsers = User::getAllAssignable($departmentFilter);
$allTasks = Task::getAll($departmentFilter, $taskStatusFilter);
$dailyUpdates = DailyUpdate::getAllWithEmployees($departmentFilter, null, $updateStatusFilter);
$performanceSummary = Evaluation::getTeamPerformanceSummary($departmentFilter);
$allEvaluations = Evaluation::getAllEvaluations($departmentFilter);
?>
<!DOCTYPE html>
<html lang="en" class="h-full bg-slate-950 text-slate-100">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manager Hub &mdash; PharmaDEM Solutions</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script>
        tailwind.config = {
            darkMode: 'class',
            theme: {
                extend: {
                    colors: {
                        brand: {
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
<body class="min-h-full flex flex-col bg-slate-950 text-slate-200 antialiased selection:bg-amber-600 selection:text-white">

    <!-- Top Navigation Bar -->
    <header class="sticky top-0 z-40 w-full border-b border-slate-800 bg-slate-900/90 backdrop-blur">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 flex items-center justify-between h-16">
            <div class="flex items-center space-x-4">
                <a href="/manager/dashboard.php" class="flex items-center space-x-2 text-white font-bold text-lg tracking-tight">
                    <span class="w-8 h-8 rounded-lg bg-amber-600 flex items-center justify-center text-white font-black text-sm shadow-md shadow-amber-500/20">MG</span>
                    <span>PharmaDEM <span class="text-amber-400 font-normal text-xs uppercase tracking-wider ml-1">Manager Hub</span></span>
                </a>
                
                <div class="hidden md:flex items-center pl-4 border-l border-slate-800 space-x-2 text-xs">
                    <?php if ($currentUser['role'] === 'Admin'): ?>
                        <a href="/admin/dashboard.php" class="px-2.5 py-1 rounded bg-slate-800 text-slate-300 hover:text-white hover:bg-slate-700 transition">Admin Center</a>
                    <?php endif; ?>
                    <a href="/employee/dashboard.php" class="px-2.5 py-1 rounded bg-slate-800 text-slate-300 hover:text-white hover:bg-slate-700 transition">Employee View</a>
                </div>
            </div>

            <!-- Profile Info & Logout -->
            <div class="flex items-center space-x-4">
                <div class="hidden sm:flex flex-col text-right">
                    <span class="text-sm font-semibold text-slate-100"><?= htmlspecialchars($currentUser['name']) ?></span>
                    <span class="text-xs text-slate-400"><?= htmlspecialchars($currentUser['department_name']) ?> &bull; <span class="text-amber-400 font-medium"><?= htmlspecialchars($currentUser['role']) ?></span></span>
                </div>
                <div class="w-8 h-8 rounded-full bg-slate-800 border border-slate-700 flex items-center justify-center text-amber-400 font-bold text-xs">
                    <?= htmlspecialchars(strtoupper(substr($currentUser['name'], 0, 2))) ?>
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
            <div class="flex items-center p-4 rounded-lg bg-emerald-950/60 border border-emerald-800 text-emerald-300 text-sm shadow-sm">
                <svg class="w-5 h-5 mr-3 text-emerald-400 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
                <span><?= htmlspecialchars($flashSuccess) ?></span>
            </div>
        <?php endif; ?>

        <?php if ($flashError): ?>
            <div class="flex items-center p-4 rounded-lg bg-red-950/60 border border-red-800 text-red-300 text-sm shadow-sm">
                <svg class="w-5 h-5 mr-3 text-red-400 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
                <span><?= htmlspecialchars($flashError) ?></span>
            </div>
        <?php endif; ?>

        <!-- SECTION 1: Real-time Metric Cards -->
        <section class="grid grid-cols-2 lg:grid-cols-5 gap-4">
            <div class="bg-slate-900 border border-slate-800 rounded-xl p-4 shadow-sm">
                <div class="text-[11px] font-semibold text-slate-400 uppercase tracking-wider">Total Tasks</div>
                <div class="mt-2 flex items-baseline justify-between">
                    <span class="text-2xl font-bold text-white"><?= (int)$teamMetrics['total_tasks'] ?></span>
                    <span class="text-xs text-slate-500">Managed</span>
                </div>
            </div>

            <div class="bg-slate-900 border border-slate-800 rounded-xl p-4 shadow-sm">
                <div class="text-[11px] font-semibold text-slate-400 uppercase tracking-wider">Completed Tasks</div>
                <div class="mt-2 flex items-baseline justify-between">
                    <span class="text-2xl font-bold text-emerald-400"><?= (int)$teamMetrics['completed_tasks'] ?></span>
                    <span class="text-xs text-emerald-500">Delivered</span>
                </div>
            </div>

            <div class="bg-slate-900 border border-slate-800 rounded-xl p-4 shadow-sm">
                <div class="text-[11px] font-semibold text-slate-400 uppercase tracking-wider">Active In Progress</div>
                <div class="mt-2 flex items-baseline justify-between">
                    <span class="text-2xl font-bold text-blue-400"><?= (int)$teamMetrics['in_progress_tasks'] ?></span>
                    <span class="text-xs text-amber-400"><?= (int)$teamMetrics['pending_tasks'] ?> pending</span>
                </div>
            </div>

            <!-- Overdue Tracker KPI Card -->
            <div class="bg-slate-900 border <?= (int)$teamMetrics['overdue_tasks'] > 0 ? 'border-red-500/40 bg-red-950/10' : 'border-slate-800' ?> rounded-xl p-4 shadow-sm">
                <div class="text-[11px] font-semibold text-slate-400 uppercase tracking-wider flex items-center justify-between">
                    <span>Overdue Tasks</span>
                    <?php if ((int)$teamMetrics['overdue_tasks'] > 0): ?>
                        <span class="w-2 h-2 rounded-full bg-red-500 animate-ping"></span>
                    <?php endif; ?>
                </div>
                <div class="mt-2 flex items-baseline justify-between">
                    <span class="text-2xl font-bold <?= (int)$teamMetrics['overdue_tasks'] > 0 ? 'text-red-400' : 'text-slate-400' ?>">
                        <?= (int)$teamMetrics['overdue_tasks'] ?>
                    </span>
                    <a href="?tab=tasks&status_filter=Overdue" class="text-xs text-red-400 hover:underline">Auto-tracked</a>
                </div>
            </div>

            <!-- Today's Daily Update Compliance KPI -->
            <div class="bg-slate-900 border border-slate-800 rounded-xl p-4 shadow-sm col-span-2 lg:col-span-1">
                <div class="text-[11px] font-semibold text-slate-400 uppercase tracking-wider">Today's Updates</div>
                <div class="mt-2 flex items-baseline justify-between">
                    <span class="text-2xl font-bold text-amber-400">
                        <?= $submissionMetrics['submitted_today'] ?> / <?= $submissionMetrics['total_employees'] ?>
                    </span>
                    <span class="text-xs font-semibold text-slate-300"><?= $submissionMetrics['compliance_percentage'] ?>% filed</span>
                </div>
            </div>
        </section>

        <!-- Navigation Tabs Bar -->
        <div class="border-b border-slate-800">
            <nav class="flex space-x-6 overflow-x-auto" aria-label="Tabs">
                <a href="?tab=overview" class="<?= $currentTab === 'overview' ? 'border-amber-500 text-amber-400' : 'border-transparent text-slate-400 hover:text-slate-200 hover:border-slate-700' ?> whitespace-nowrap py-3 px-1 border-b-2 font-medium text-sm flex items-center space-x-2">
                    <span>Overview &amp; Delegations</span>
                </a>

                <a href="?tab=tasks" class="<?= $currentTab === 'tasks' ? 'border-amber-500 text-amber-400' : 'border-transparent text-slate-400 hover:text-slate-200 hover:border-slate-700' ?> whitespace-nowrap py-3 px-1 border-b-2 font-medium text-sm flex items-center space-x-2">
                    <span>Task Management</span>
                    <span class="py-0.5 px-2 text-xs rounded-full bg-slate-800 text-slate-300"><?= count($allTasks) ?></span>
                </a>

                <a href="?tab=updates" class="<?= $currentTab === 'updates' ? 'border-amber-500 text-amber-400' : 'border-transparent text-slate-400 hover:text-slate-200 hover:border-slate-700' ?> whitespace-nowrap py-3 px-1 border-b-2 font-medium text-sm flex items-center space-x-2">
                    <span>Daily Updates Queue</span>
                    <?php if ($submissionMetrics['pending_review_today'] > 0): ?>
                        <span class="py-0.5 px-1.5 text-xs rounded-full bg-amber-500/20 text-amber-300 border border-amber-500/40">
                            <?= $submissionMetrics['pending_review_today'] ?>
                        </span>
                    <?php endif; ?>
                </a>

                <a href="?tab=analytics" class="<?= $currentTab === 'analytics' ? 'border-amber-500 text-amber-400' : 'border-transparent text-slate-400 hover:text-slate-200 hover:border-slate-700' ?> whitespace-nowrap py-3 px-1 border-b-2 font-medium text-sm flex items-center space-x-2">
                    <span>Performance Analytics</span>
                </a>

                <a href="?tab=evaluations" class="<?= $currentTab === 'evaluations' ? 'border-amber-500 text-amber-400' : 'border-transparent text-slate-400 hover:text-slate-200 hover:border-slate-700' ?> whitespace-nowrap py-3 px-1 border-b-2 font-medium text-sm flex items-center space-x-2">
                    <span>Evaluation Form &amp; History</span>
                </a>

                <a href="?tab=exports" class="<?= $currentTab === 'exports' ? 'border-amber-500 text-amber-400' : 'border-transparent text-slate-400 hover:text-slate-200 hover:border-slate-700' ?> whitespace-nowrap py-3 px-1 border-b-2 font-medium text-sm flex items-center space-x-2">
                    <span>Reports &amp; Exports</span>
                </a>
            </nav>
        </div>

        <!-- Overview Tab -->
        <?php if ($currentTab === 'overview'): ?>
            <div class="grid grid-cols-1 lg:grid-cols-12 gap-6">
                <!-- Task Assignment Form (5 Cols) -->
                <div class="lg:col-span-5 bg-slate-900 border border-slate-800 rounded-xl p-6 shadow-sm">
                    <div class="border-b border-slate-800 pb-3 mb-5">
                        <h3 class="text-base font-semibold text-white flex items-center">
                            <svg class="w-4 h-4 mr-2 text-amber-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"></path></svg>
                            Task Assignment Engine
                        </h3>
                        <p class="text-xs text-slate-400 mt-1">Delegate deliverables to team members with due date boundaries.</p>
                    </div>

                    <form method="POST" action="/manager/dashboard.php" class="space-y-4">
                        <?= Csrf::field() ?>
                        <input type="hidden" name="action" value="create_task">

                        <div>
                            <label for="title" class="block text-xs font-semibold text-slate-300 uppercase tracking-wider mb-1">
                                Task Title <span class="text-red-400">*</span>
                            </label>
                            <input 
                                type="text" 
                                id="title" 
                                name="title" 
                                required 
                                placeholder="e.g. Inspect Cleanroom Air Handling System" 
                                class="w-full bg-slate-950 border border-slate-700 rounded-lg text-sm text-slate-100 p-2.5 focus:ring-2 focus:ring-amber-500 focus:outline-none placeholder-slate-600 transition"
                            >
                        </div>

                        <div>
                            <label for="assigned_to" class="block text-xs font-semibold text-slate-300 uppercase tracking-wider mb-1">
                                Delegate To <span class="text-red-400">*</span>
                            </label>
                            <select 
                                id="assigned_to" 
                                name="assigned_to" 
                                required 
                                class="w-full bg-slate-950 border border-slate-700 rounded-lg text-sm text-slate-100 p-2.5 focus:ring-2 focus:ring-amber-500 focus:outline-none transition"
                            >
                                <option value="">-- Select Team Member --</option>
                                <?php foreach ($assignableUsers as $u): ?>
                                    <option value="<?= (int) $u['id'] ?>">
                                        <?= htmlspecialchars($u['name']) ?> (<?= htmlspecialchars($u['department_name'] ?? 'General') ?> &bull; <?= htmlspecialchars($u['role']) ?>)
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="grid grid-cols-2 gap-3">
                            <div>
                                <label for="priority" class="block text-xs font-semibold text-slate-300 uppercase tracking-wider mb-1">
                                    Priority Level
                                </label>
                                <select 
                                    id="priority" 
                                    name="priority" 
                                    class="w-full bg-slate-950 border border-slate-700 rounded-lg text-sm text-slate-100 p-2.5 focus:ring-2 focus:ring-amber-500 focus:outline-none transition"
                                >
                                    <option value="Low">Low</option>
                                    <option value="Medium" selected>Medium</option>
                                    <option value="High">High</option>
                                    <option value="Urgent">Urgent</option>
                                </select>
                            </div>

                            <div>
                                <label for="due_date" class="block text-xs font-semibold text-slate-300 uppercase tracking-wider mb-1">
                                    Due Date <span class="text-red-400">*</span>
                                </label>
                                <input 
                                    type="date" 
                                    id="due_date" 
                                    name="due_date" 
                                    min="<?= date('Y-m-d') ?>" 
                                    value="<?= date('Y-m-d', strtotime('+3 days')) ?>" 
                                    required 
                                    class="w-full bg-slate-950 border border-slate-700 rounded-lg text-sm text-slate-100 p-2.5 focus:ring-2 focus:ring-amber-500 focus:outline-none transition"
                                >
                            </div>
                        </div>

                        <div>
                            <label for="description" class="block text-xs font-semibold text-slate-300 uppercase tracking-wider mb-1">
                                Detailed Instructions &amp; SOP Reference
                            </label>
                            <textarea 
                                id="description" 
                                name="description" 
                                rows="3" 
                                placeholder="State regulatory requirements, expected outputs, and acceptance criteria..." 
                                class="w-full bg-slate-950 border border-slate-700 rounded-lg text-sm text-slate-100 p-2.5 focus:ring-2 focus:ring-amber-500 focus:outline-none placeholder-slate-600 transition"
                            ></textarea>
                        </div>

                        <button type="submit" class="w-full py-2.5 px-4 bg-amber-600 hover:bg-amber-500 text-white font-semibold text-sm rounded-lg transition shadow-md shadow-amber-600/20">
                            Assign &amp; Dispatch Task
                        </button>
                    </form>
                </div>

                <!-- Live Team Updates Snapshot (7 Cols) -->
                <div class="lg:col-span-7 space-y-6">
                    <div class="bg-slate-900 border border-slate-800 rounded-xl p-5 shadow-sm">
                        <div class="flex items-center justify-between mb-4 border-b border-slate-800 pb-3">
                            <h3 class="text-sm font-semibold text-white flex items-center">
                                <span class="w-2.5 h-2.5 rounded-full bg-red-500 mr-2"></span>
                                Urgent &amp; Attention Required Tasks
                            </h3>
                            <a href="?tab=tasks" class="text-xs text-amber-400 hover:underline">View all tasks &rarr;</a>
                        </div>

                        <?php 
                        $urgentTasks = array_filter($allTasks, fn($t) => $t['status'] === 'Overdue' || $t['priority'] === 'Urgent');
                        ?>
                        <?php if (empty($urgentTasks)): ?>
                            <div class="text-center py-6 text-xs text-slate-500">
                                No overdue or critical tasks at this moment. Everything on schedule!
                            </div>
                        <?php else: ?>
                            <div class="space-y-2">
                                <?php foreach (array_slice($urgentTasks, 0, 4) as $uTask): ?>
                                    <div class="flex items-center justify-between p-3 rounded-lg bg-slate-950/70 border border-slate-800 text-xs">
                                        <div class="space-y-0.5">
                                            <div class="font-semibold text-slate-100"><?= htmlspecialchars($uTask['title']) ?></div>
                                            <div class="text-slate-400">Assigned: <strong class="text-slate-300"><?= htmlspecialchars($uTask['assigned_to_name']) ?></strong> &bull; Due: <?= htmlspecialchars($uTask['due_date']) ?></div>
                                        </div>
                                        <div>
                                            <?php if ($uTask['status'] === 'Overdue'): ?>
                                                <span class="px-2 py-0.5 rounded font-semibold text-[11px] bg-red-500/20 text-red-400 border border-red-500/40">OVERDUE</span>
                                            <?php else: ?>
                                                <span class="px-2 py-0.5 rounded font-semibold text-[11px] bg-red-500/10 text-red-300 border border-red-500/30">Urgent</span>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>
                    </div>

                    <!-- Today's Submissions Overview -->
                    <div class="bg-slate-900 border border-slate-800 rounded-xl p-5 shadow-sm">
                        <div class="flex items-center justify-between mb-4 border-b border-slate-800 pb-3">
                            <h3 class="text-sm font-semibold text-white">Today's Daily Updates Activity</h3>
                            <span class="text-xs text-slate-400"><?= date('l, F j, Y') ?></span>
                        </div>

                        <div class="grid grid-cols-3 gap-3 text-center mb-4">
                            <div class="p-3 bg-slate-950 rounded-lg border border-slate-800">
                                <div class="text-[10px] text-slate-400 uppercase font-semibold">Submitted</div>
                                <div class="text-lg font-bold text-emerald-400 mt-1"><?= $submissionMetrics['submitted_today'] ?></div>
                            </div>
                            <div class="p-3 bg-slate-950 rounded-lg border border-slate-800">
                                <div class="text-[10px] text-slate-400 uppercase font-semibold">Awaiting Review</div>
                                <div class="text-lg font-bold text-amber-400 mt-1"><?= $submissionMetrics['pending_review_today'] ?></div>
                            </div>
                            <div class="p-3 bg-slate-950 rounded-lg border border-slate-800">
                                <div class="text-[10px] text-slate-400 uppercase font-semibold">Not Yet Filed</div>
                                <div class="text-lg font-bold text-slate-400 mt-1"><?= $submissionMetrics['not_submitted_today'] ?></div>
                            </div>
                        </div>

                        <div class="text-right">
                            <a href="?tab=updates" class="inline-flex items-center text-xs font-semibold text-amber-400 hover:text-amber-300">
                                Open Daily Updates Review Queue &rarr;
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        <?php endif; ?>

        <!-- Tasks Tab -->
        <?php if ($currentTab === 'tasks'): ?>
            <div class="space-y-4">
                <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3 bg-slate-900 border border-slate-800 p-3 rounded-xl">
                    <div class="flex items-center space-x-1 overflow-x-auto text-xs">
                        <span class="text-slate-400 font-medium mr-2">Filter Status:</span>
                        <?php 
                        $statusTabs = ['All', 'Pending', 'In Progress', 'Completed', 'Overdue'];
                        foreach ($statusTabs as $sKey): 
                            $isCur = ($taskStatusFilter === null && $sKey === 'All') || ($taskStatusFilter === $sKey);
                        ?>
                            <a href="?tab=tasks<?= $sKey !== 'All' ? "&status_filter={$sKey}" : '' ?>" 
                               class="px-3 py-1.5 rounded-lg font-medium transition <?= $isCur ? 'bg-amber-600 text-white shadow-sm' : 'text-slate-400 hover:text-white hover:bg-slate-800' ?>">
                                <?= $sKey ?>
                            </a>
                        <?php endforeach; ?>
                    </div>
                    <div class="text-xs text-slate-400">
                        Displaying <?= count($allTasks) ?> record(s)
                    </div>
                </div>

                <div class="bg-slate-900 border border-slate-800 rounded-xl overflow-hidden shadow-sm">
                    <?php if (empty($allTasks)): ?>
                        <div class="text-center py-16 text-slate-500 text-xs">
                            No tasks found matching this criteria.
                        </div>
                    <?php else: ?>
                        <div class="overflow-x-auto">
                            <table class="w-full text-left text-xs border-collapse">
                                <thead>
                                    <tr class="bg-slate-950/80 border-b border-slate-800 text-slate-400 font-medium">
                                        <th class="py-3 px-4">Task Title</th>
                                        <th class="py-3 px-4">Assignee</th>
                                        <th class="py-3 px-4">Priority</th>
                                        <th class="py-3 px-4">Status</th>
                                        <th class="py-3 px-4">Due Date</th>
                                        <th class="py-3 px-4">Created By</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-slate-800">
                                    <?php foreach ($allTasks as $t): 
                                        $pBadge = match ($t['priority']) {
                                            'Urgent' => 'bg-red-500/10 text-red-400 border-red-500/30',
                                            'High'   => 'bg-amber-500/10 text-amber-400 border-amber-500/30',
                                            'Medium' => 'bg-blue-500/10 text-blue-400 border-blue-500/30',
                                            default  => 'bg-slate-500/10 text-slate-400 border-slate-500/30',
                                        };
                                        $sBadge = match ($t['status']) {
                                            'Completed'   => 'bg-emerald-500/10 text-emerald-400 border-emerald-500/30',
                                            'In Progress' => 'bg-blue-500/10 text-blue-400 border-blue-500/30',
                                            'Overdue'     => 'bg-red-500/20 text-red-400 border-red-500/40 font-bold',
                                            default       => 'bg-amber-500/10 text-amber-400 border-amber-500/30',
                                        };
                                    ?>
                                        <tr class="hover:bg-slate-800/40 transition">
                                            <td class="py-3 px-4 max-w-sm">
                                                <div class="font-semibold text-white"><?= htmlspecialchars($t['title']) ?></div>
                                                <?php if (!empty($t['description'])): ?>
                                                    <div class="text-slate-400 line-clamp-1 mt-0.5"><?= htmlspecialchars($t['description']) ?></div>
                                                <?php endif; ?>
                                            </td>
                                            <td class="py-3 px-4 whitespace-nowrap">
                                                <div class="font-medium text-slate-200"><?= htmlspecialchars($t['assigned_to_name']) ?></div>
                                                <div class="text-[11px] text-slate-500"><?= htmlspecialchars($t['department_name'] ?? 'General') ?></div>
                                            </td>
                                            <td class="py-3 px-4 whitespace-nowrap">
                                                <span class="inline-flex items-center px-2 py-0.5 rounded text-[11px] font-semibold border <?= $pBadge ?>">
                                                    <?= htmlspecialchars($t['priority']) ?>
                                                </span>
                                            </td>
                                            <td class="py-3 px-4 whitespace-nowrap">
                                                <span class="inline-flex items-center px-2 py-0.5 rounded text-[11px] font-semibold border <?= $sBadge ?>">
                                                    <?= htmlspecialchars($t['status']) ?>
                                                </span>
                                            </td>
                                            <td class="py-3 px-4 whitespace-nowrap">
                                                <span class="<?= $t['status'] === 'Overdue' ? 'text-red-400 font-bold' : 'text-slate-300' ?>">
                                                    <?= htmlspecialchars($t['due_date']) ?>
                                                </span>
                                            </td>
                                            <td class="py-3 px-4 text-slate-400 whitespace-nowrap">
                                                <?= htmlspecialchars($t['assigned_by_name']) ?>
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

        <!-- Updates Tab -->
        <?php if ($currentTab === 'updates'): ?>
            <div class="space-y-4">
                <div class="flex items-center justify-between bg-slate-900 border border-slate-800 p-3 rounded-xl text-xs">
                    <div class="flex items-center space-x-2">
                        <span class="text-slate-400 font-medium mr-1">Submission Status:</span>
                        <a href="?tab=updates" class="px-3 py-1.5 rounded-lg <?= $updateStatusFilter === null ? 'bg-amber-600 text-white' : 'text-slate-400 hover:bg-slate-800' ?>">All Updates</a>
                        <a href="?tab=updates&update_status=Submitted" class="px-3 py-1.5 rounded-lg <?= $updateStatusFilter === 'Submitted' ? 'bg-amber-600 text-white' : 'text-slate-400 hover:bg-slate-800' ?>">Awaiting Review</a>
                        <a href="?tab=updates&update_status=Reviewed" class="px-3 py-1.5 rounded-lg <?= $updateStatusFilter === 'Reviewed' ? 'bg-amber-600 text-white' : 'text-slate-400 hover:bg-slate-800' ?>">Reviewed</a>
                    </div>
                    <span class="text-slate-400"><?= count($dailyUpdates) ?> submission(s) found</span>
                </div>

                <?php if (empty($dailyUpdates)): ?>
                    <div class="text-center py-16 bg-slate-900 border border-slate-800 rounded-xl text-slate-500 text-xs">
                        No employee daily work updates found for the selected filter.
                    </div>
                <?php else: ?>
                    <div class="space-y-4">
                        <?php foreach ($dailyUpdates as $item): 
                            $isReviewed = ($item['status'] === 'Reviewed');
                        ?>
                            <div class="bg-slate-900 border border-slate-800 rounded-xl p-5 shadow-sm space-y-4">
                                <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-2 border-b border-slate-800 pb-3">
                                    <div class="flex items-center space-x-3">
                                        <div class="w-8 h-8 rounded-full bg-slate-800 border border-slate-700 flex items-center justify-center text-amber-400 font-bold text-xs">
                                            <?= htmlspecialchars(strtoupper(substr($item['employee_name'], 0, 2))) ?>
                                        </div>
                                        <div>
                                            <h4 class="text-sm font-semibold text-white"><?= htmlspecialchars($item['employee_name']) ?></h4>
                                            <p class="text-xs text-slate-400"><?= htmlspecialchars($item['department_name'] ?? 'General') ?> &bull; Update Date: <strong class="text-slate-200"><?= htmlspecialchars($item['update_date']) ?></strong></p>
                                        </div>
                                    </div>
                                    <div>
                                        <span class="px-2.5 py-1 rounded text-xs font-semibold <?= $isReviewed ? 'bg-emerald-500/10 text-emerald-400 border border-emerald-500/30' : 'bg-blue-500/10 text-blue-400 border border-blue-500/30' ?>">
                                            ● <?= htmlspecialchars($item['status']) ?>
                                        </span>
                                    </div>
                                </div>

                                <div class="grid grid-cols-1 md:grid-cols-3 gap-3 text-xs">
                                    <div class="bg-slate-950/70 border border-slate-800/80 p-3 rounded-lg">
                                        <div class="font-semibold text-slate-400 uppercase tracking-wider mb-1">To-Do Planned</div>
                                        <p class="text-slate-200 whitespace-pre-line"><?= htmlspecialchars($item['todo_tasks']) ?></p>
                                    </div>

                                    <div class="bg-slate-950/70 border border-slate-800/80 p-3 rounded-lg">
                                        <div class="font-semibold text-emerald-400 uppercase tracking-wider mb-1">Completed Deliverables</div>
                                        <p class="text-slate-200 whitespace-pre-line"><?= htmlspecialchars($item['completed_work']) ?></p>
                                    </div>

                                    <div class="bg-slate-950/70 border border-slate-800/80 p-3 rounded-lg">
                                        <div class="font-semibold text-amber-400 uppercase tracking-wider mb-1">Pending Blockers</div>
                                        <p class="<?= !empty($item['pending_blockers']) ? 'text-amber-200 font-medium' : 'text-slate-500' ?> whitespace-pre-line">
                                            <?= htmlspecialchars($item['pending_blockers'] ?: 'None reported.') ?>
                                        </p>
                                    </div>
                                </div>

                                <div class="pt-3 border-t border-slate-800/80">
                                    <form method="POST" action="/manager/dashboard.php" class="flex flex-col sm:flex-row items-end gap-3">
                                        <?= Csrf::field() ?>
                                        <input type="hidden" name="action" value="review_update">
                                        <input type="hidden" name="update_id" value="<?= (int) $item['id'] ?>">

                                        <div class="flex-1 w-full">
                                            <label for="notes_<?= (int)$item['id'] ?>" class="block text-[11px] font-semibold text-slate-400 uppercase tracking-wider mb-1">
                                                Manager Review Feedback / Observations
                                            </label>
                                            <input 
                                                type="text" 
                                                id="notes_<?= (int)$item['id'] ?>" 
                                                name="review_notes" 
                                                value="<?= htmlspecialchars($item['review_notes'] ?? '') ?>"
                                                placeholder="e.g. Good progress on sterile batch review. Verified with QA." 
                                                class="w-full bg-slate-950 border border-slate-700 rounded-lg text-xs text-slate-100 p-2 focus:ring-1 focus:ring-amber-500 focus:outline-none placeholder-slate-600 transition"
                                            >
                                        </div>

                                        <button type="submit" class="w-full sm:w-auto px-4 py-2 bg-slate-800 hover:bg-amber-600 hover:text-white text-slate-200 border border-slate-700 font-semibold text-xs rounded-lg transition whitespace-nowrap shadow-sm">
                                            <?= $isReviewed ? 'Update Notes' : 'Approve &amp; Mark Reviewed' ?>
                                        </button>
                                    </form>

                                    <?php if ($isReviewed && !empty($item['reviewer_name'])): ?>
                                        <div class="mt-2 text-[11px] text-slate-500">
                                            Reviewed by <?= htmlspecialchars($item['reviewer_name']) ?> on <?= htmlspecialchars($item['reviewed_at'] ?? 'N/A') ?>
                                        </div>
                                    <?php endif; ?>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
        <?php endif; ?>

        <!-- Analytics Tab -->
        <?php if ($currentTab === 'analytics'): ?>
            <div class="space-y-6">
                <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3 bg-slate-900 border border-slate-800 p-5 rounded-xl">
                    <div>
                        <h3 class="text-base font-semibold text-white">Automated Task Completion &amp; Rating Analytics</h3>
                        <p class="text-xs text-slate-400 mt-1">Cross-references operational execution rates (on-time delivery vs overdue) alongside manager evaluations.</p>
                    </div>
                    <div class="flex items-center space-x-2">
                        <a href="/export.php?type=performance_summary&format=csv" class="px-3 py-1.5 bg-slate-800 hover:bg-slate-700 text-xs font-semibold text-slate-200 rounded-lg border border-slate-700 transition flex items-center">
                            CSV Export
                        </a>
                        <a href="/export.php?type=performance_summary&format=html" target="_blank" class="px-3 py-1.5 bg-amber-600 hover:bg-amber-500 text-xs font-semibold text-white rounded-lg transition flex items-center">
                            Printable Summary
                        </a>
                    </div>
                </div>

                <div class="bg-slate-900 border border-slate-800 rounded-xl overflow-hidden shadow-sm">
                    <div class="overflow-x-auto">
                        <table class="w-full text-left text-xs border-collapse">
                            <thead>
                                <tr class="bg-slate-950/80 border-b border-slate-800 text-slate-400 font-medium">
                                    <th class="py-3 px-4">Employee</th>
                                    <th class="py-3 px-4">Department</th>
                                    <th class="py-3 px-4">Total Tasks</th>
                                    <th class="py-3 px-4">Task Completion Rate</th>
                                    <th class="py-3 px-4">On-Time Delivery Rate</th>
                                    <th class="py-3 px-4">Overdue Rate</th>
                                    <th class="py-3 px-4">Manager Rating</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-slate-800">
                                <?php foreach ($performanceSummary as $sum): ?>
                                    <tr class="hover:bg-slate-800/40 transition">
                                        <td class="py-3 px-4 whitespace-nowrap">
                                            <div class="font-semibold text-white"><?= htmlspecialchars($sum['employee_name']) ?></div>
                                            <div class="text-[11px] text-slate-500"><?= htmlspecialchars($sum['employee_email']) ?></div>
                                        </td>
                                        <td class="py-3 px-4 text-slate-300 whitespace-nowrap">
                                            <?= htmlspecialchars($sum['department_name'] ?? 'General') ?>
                                        </td>
                                        <td class="py-3 px-4 font-semibold text-slate-200">
                                            <?= (int)$sum['total_tasks'] ?> <span class="text-slate-500 text-[11px] font-normal">(<?= (int)$sum['completed_tasks'] ?> done)</span>
                                        </td>
                                        <td class="py-3 px-4">
                                            <div class="flex items-center space-x-2">
                                                <div class="w-20 bg-slate-800 rounded-full h-1.5 overflow-hidden">
                                                    <div class="bg-blue-500 h-1.5 rounded-full" style="width: <?= min(100, $sum['completion_rate']) ?>%"></div>
                                                </div>
                                                <span class="font-bold text-slate-200"><?= $sum['completion_rate'] ?>%</span>
                                            </div>
                                        </td>
                                        <td class="py-3 px-4">
                                            <div class="flex items-center space-x-2">
                                                <div class="w-20 bg-slate-800 rounded-full h-1.5 overflow-hidden">
                                                    <div class="bg-emerald-500 h-1.5 rounded-full" style="width: <?= min(100, $sum['on_time_rate']) ?>%"></div>
                                                </div>
                                                <span class="font-bold text-emerald-400"><?= $sum['on_time_rate'] ?>%</span>
                                            </div>
                                        </td>
                                        <td class="py-3 px-4">
                                            <span class="<?= $sum['overdue_rate'] > 0 ? 'text-red-400 font-bold' : 'text-slate-400' ?>">
                                                <?= $sum['overdue_rate'] ?>% (<?= (int)$sum['overdue_tasks'] ?>)
                                            </span>
                                        </td>
                                        <td class="py-3 px-4 whitespace-nowrap">
                                            <?php if ($sum['avg_manager_score'] !== null): ?>
                                                <span class="px-2 py-1 rounded text-xs font-black bg-purple-500/10 text-purple-400 border border-purple-500/30">
                                                    <?= htmlspecialchars((string)$sum['avg_manager_score']) ?> / 100
                                                </span>
                                            <?php else: ?>
                                                <span class="text-slate-500 italic">No evaluations yet</span>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        <?php endif; ?>

        <!-- Evaluations Tab -->
        <?php if ($currentTab === 'evaluations'): ?>
            <div class="grid grid-cols-1 lg:grid-cols-12 gap-6">
                <!-- Evaluation Form (5 Cols) -->
                <div class="lg:col-span-5 bg-slate-900 border border-slate-800 rounded-xl p-6 shadow-sm">
                    <div class="border-b border-slate-800 pb-3 mb-5">
                        <h3 class="text-base font-semibold text-white flex items-center">
                            <svg class="w-4 h-4 mr-2 text-purple-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4M7.835 4.697a3.42 3.42 0 001.946-.806 3.42 3.42 0 014.438 0 3.42 3.42 0 001.946.806 3.42 3.42 0 013.138 3.138 3.42 3.42 0 00.806 1.946 3.42 3.42 0 010 4.438 3.42 3.42 0 00-.806 1.946 3.42 3.42 0 01-3.138 3.138 3.42 3.42 0 00-1.946.806 3.42 3.42 0 01-4.438 0 3.42 3.42 0 00-1.946-.806 3.42 3.42 0 01-3.138-3.138 3.42 3.42 0 00-.806-1.946 3.42 3.42 0 010-4.438 3.42 3.42 0 00.806-1.946 3.42 3.42 0 013.138-3.138z"></path></svg>
                            Employee Performance Evaluation
                        </h3>
                        <p class="text-xs text-slate-400 mt-1">Submit formal scorecard and qualitative assessment for a cycle.</p>
                    </div>

                    <form method="POST" action="/manager/dashboard.php" class="space-y-4" id="evalForm">
                        <?= Csrf::field() ?>
                        <input type="hidden" name="action" value="submit_evaluation">

                        <div>
                            <label for="eval_emp_id" class="block text-xs font-semibold text-slate-300 uppercase tracking-wider mb-1">
                                Employee Under Evaluation <span class="text-red-400">*</span>
                            </label>
                            <select 
                                id="eval_emp_id" 
                                name="employee_id" 
                                required 
                                class="w-full bg-slate-950 border border-slate-700 rounded-lg text-sm text-slate-100 p-2.5 focus:ring-2 focus:ring-purple-500 focus:outline-none transition"
                            >
                                <option value="">-- Choose Employee --</option>
                                <?php foreach ($assignableUsers as $u): ?>
                                    <option value="<?= (int) $u['id'] ?>">
                                        <?= htmlspecialchars($u['name']) ?> (<?= htmlspecialchars($u['department_name'] ?? 'General') ?>)
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div>
                            <label for="eval_period" class="block text-xs font-semibold text-slate-300 uppercase tracking-wider mb-1">
                                Evaluation Cycle / Period <span class="text-red-400">*</span>
                            </label>
                            <input 
                                type="text" 
                                id="eval_period" 
                                name="evaluation_period" 
                                value="<?= date('Y-m') ?>" 
                                placeholder="YYYY-MM (e.g. <?= date('Y-m') ?>)" 
                                required 
                                class="w-full bg-slate-950 border border-slate-700 rounded-lg text-sm text-slate-100 p-2.5 focus:ring-2 focus:ring-purple-500 focus:outline-none transition font-mono"
                            >
                        </div>

                        <!-- Score Input Fields -->
                        <div class="space-y-3 bg-slate-950/70 p-3.5 rounded-lg border border-slate-800">
                            <div>
                                <div class="flex justify-between text-xs font-semibold text-slate-300 mb-1">
                                    <span>Task Completion (0 - 100)</span>
                                    <span id="disp_task" class="text-purple-400 font-bold">90.0</span>
                                </div>
                                <input 
                                    type="number" 
                                    step="0.5" 
                                    min="0" 
                                    max="100" 
                                    id="score_task" 
                                    name="task_completion_score" 
                                    value="90.0" 
                                    required 
                                    class="w-full bg-slate-900 border border-slate-700 rounded text-xs text-slate-100 p-2 focus:ring-1 focus:ring-purple-500 focus:outline-none"
                                    oninput="recalcScore()"
                                >
                            </div>

                            <div>
                                <div class="flex justify-between text-xs font-semibold text-slate-300 mb-1">
                                    <span>Punctuality / Attendance (0 - 100)</span>
                                    <span id="disp_punc" class="text-purple-400 font-bold">95.0</span>
                                </div>
                                <input 
                                    type="number" 
                                    step="0.5" 
                                    min="0" 
                                    max="100" 
                                    id="score_punc" 
                                    name="punctuality_score" 
                                    value="95.0" 
                                    required 
                                    class="w-full bg-slate-900 border border-slate-700 rounded text-xs text-slate-100 p-2 focus:ring-1 focus:ring-purple-500 focus:outline-none"
                                    oninput="recalcScore()"
                                >
                            </div>

                            <div>
                                <div class="flex justify-between text-xs font-semibold text-slate-300 mb-1">
                                    <span>Quality &amp; GMP Compliance (0 - 100)</span>
                                    <span id="disp_qual" class="text-purple-400 font-bold">92.0</span>
                                </div>
                                <input 
                                    type="number" 
                                    step="0.5" 
                                    min="0" 
                                    max="100" 
                                    id="score_qual" 
                                    name="quality_score" 
                                    value="92.0" 
                                    required 
                                    class="w-full bg-slate-900 border border-slate-700 rounded text-xs text-slate-100 p-2 focus:ring-1 focus:ring-purple-500 focus:outline-none"
                                    oninput="recalcScore()"
                                >
                            </div>

                            <!-- Live Overall Score Preview -->
                            <div class="pt-2 border-t border-slate-800 flex justify-between items-center text-xs">
                                <span class="text-slate-400 font-semibold uppercase">Computed Overall Rating:</span>
                                <span id="disp_overall" class="text-sm font-black text-purple-400 bg-purple-950/80 px-2 py-0.5 rounded border border-purple-800/60">
                                    92.33 / 100
                                </span>
                            </div>
                        </div>

                        <div>
                            <label for="eval_remarks" class="block text-xs font-semibold text-slate-300 uppercase tracking-wider mb-1">
                                Manager Qualitative Remarks
                            </label>
                            <textarea 
                                id="eval_remarks" 
                                name="manager_remarks" 
                                rows="3" 
                                placeholder="Describe notable achievements, strengths, and targeted improvement milestones..." 
                                class="w-full bg-slate-950 border border-slate-700 rounded-lg text-sm text-slate-100 p-2.5 focus:ring-2 focus:ring-purple-500 focus:outline-none placeholder-slate-600 transition"
                            ></textarea>
                        </div>

                        <button type="submit" class="w-full py-2.5 px-4 bg-purple-600 hover:bg-purple-500 text-white font-semibold text-sm rounded-lg transition shadow-md shadow-purple-600/20">
                            Save Performance Evaluation
                        </button>
                    </form>
                </div>

                <!-- Historical Evaluations Log (7 Cols) -->
                <div class="lg:col-span-7 bg-slate-900 border border-slate-800 rounded-xl p-5 shadow-sm space-y-4">
                    <div class="border-b border-slate-800 pb-3 flex items-center justify-between">
                        <h3 class="text-sm font-semibold text-white">Evaluations Log (<?= count($allEvaluations) ?>)</h3>
                        <a href="/export.php?type=evaluations&format=csv" class="text-xs text-purple-400 hover:underline">
                            Export Evaluations CSV
                        </a>
                    </div>

                    <?php if (empty($allEvaluations)): ?>
                        <div class="text-center py-16 text-slate-500 text-xs">
                            No evaluations submitted yet.
                        </div>
                    <?php else: ?>
                        <div class="space-y-3 max-h-[600px] overflow-y-auto pr-1">
                            <?php foreach ($allEvaluations as $ev): ?>
                                <div class="p-4 rounded-lg bg-slate-950 border border-slate-800 text-xs space-y-2">
                                    <div class="flex items-center justify-between">
                                        <div>
                                            <strong class="text-slate-100 text-sm"><?= htmlspecialchars($ev['employee_name']) ?></strong>
                                            <span class="text-slate-500 ml-1">(<?= htmlspecialchars($ev['department_name'] ?? 'General') ?>)</span>
                                        </div>
                                        <div class="flex items-center space-x-2">
                                            <span class="px-2 py-0.5 rounded font-mono text-[11px] bg-slate-800 text-slate-300">
                                                Cycle: <?= htmlspecialchars($ev['evaluation_period']) ?>
                                            </span>
                                            <span class="font-bold text-purple-400 bg-purple-950/60 px-2 py-0.5 rounded border border-purple-800/40">
                                                <?= htmlspecialchars((string)$ev['overall_score']) ?> / 100
                                            </span>
                                        </div>
                                    </div>

                                    <div class="grid grid-cols-3 gap-2 text-[11px] text-slate-400 pt-1">
                                        <div>Tasks: <strong class="text-slate-200"><?= htmlspecialchars((string)$ev['task_completion_score']) ?>%</strong></div>
                                        <div>Punctuality: <strong class="text-slate-200"><?= htmlspecialchars((string)$ev['punctuality_score']) ?>%</strong></div>
                                        <div>Quality: <strong class="text-slate-200"><?= htmlspecialchars((string)$ev['quality_score']) ?>%</strong></div>
                                    </div>

                                    <?php if (!empty($ev['manager_remarks'])): ?>
                                        <div class="pt-2 border-t border-slate-800/60 text-slate-300">
                                            <em>&ldquo;<?= htmlspecialchars($ev['manager_remarks']) ?>&rdquo;</em>
                                            <div class="text-[10px] text-slate-500 mt-1">&mdash; <?= htmlspecialchars($ev['evaluator_name']) ?> (<?= htmlspecialchars($ev['created_at']) ?>)</div>
                                        </div>
                                    <?php endif; ?>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        <?php endif; ?>

        <!-- Exports Tab -->
        <?php if ($currentTab === 'exports'): ?>
            <div class="space-y-6 max-w-4xl mx-auto">
                <div class="bg-slate-900 border border-slate-800 rounded-xl p-6 shadow-sm">
                    <div class="border-b border-slate-800 pb-3 mb-5">
                        <h3 class="text-base font-semibold text-white">Reports &amp; Data Export Center</h3>
                        <p class="text-xs text-slate-400 mt-1">Generate standardized CSV downloads or clean, printable executive reports for compliance audits.</p>
                    </div>

                    <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                        <!-- Card 1: Daily Updates -->
                        <div class="p-5 rounded-xl bg-slate-950 border border-slate-800 flex flex-col justify-between">
                            <div>
                                <div class="text-xs font-semibold text-blue-400 uppercase tracking-wider mb-1">Work Submission Logs</div>
                                <h4 class="text-sm font-bold text-white mb-2">Daily Work Updates</h4>
                                <p class="text-xs text-slate-400 mb-4">Complete archive of planned tasks, completed deliverables, blockers, and manager sign-offs.</p>
                            </div>
                            <div class="space-y-2">
                                <a href="/export.php?type=daily_updates&format=csv" class="block w-full text-center py-2 px-3 bg-blue-600 hover:bg-blue-500 text-white font-semibold text-xs rounded-lg transition shadow-sm">
                                    Download CSV File
                                </a>
                                <a href="/export.php?type=daily_updates&format=html" target="_blank" class="block w-full text-center py-2 px-3 bg-slate-800 hover:bg-slate-700 text-slate-300 font-semibold text-xs rounded-lg transition border border-slate-700">
                                    Printable HTML View
                                </a>
                            </div>
                        </div>

                        <!-- Card 2: Performance Summary -->
                        <div class="p-5 rounded-xl bg-slate-950 border border-slate-800 flex flex-col justify-between">
                            <div>
                                <div class="text-xs font-semibold text-emerald-400 uppercase tracking-wider mb-1">Operational Analytics</div>
                                <h4 class="text-sm font-bold text-white mb-2">Performance Summary</h4>
                                <p class="text-xs text-slate-400 mb-4">Real-time compilation of on-time delivery percentages, overdue ratios, and evaluation score averages.</p>
                            </div>
                            <div class="space-y-2">
                                <a href="/export.php?type=performance_summary&format=csv" class="block w-full text-center py-2 px-3 bg-emerald-600 hover:bg-emerald-500 text-white font-semibold text-xs rounded-lg transition shadow-sm">
                                    Download CSV File
                                </a>
                                <a href="/export.php?type=performance_summary&format=html" target="_blank" class="block w-full text-center py-2 px-3 bg-slate-800 hover:bg-slate-700 text-slate-300 font-semibold text-xs rounded-lg transition border border-slate-700">
                                    Printable HTML View
                                </a>
                            </div>
                        </div>

                        <!-- Card 3: Evaluations -->
                        <div class="p-5 rounded-xl bg-slate-950 border border-slate-800 flex flex-col justify-between">
                            <div>
                                <div class="text-xs font-semibold text-purple-400 uppercase tracking-wider mb-1">Management Ratings</div>
                                <h4 class="text-sm font-bold text-white mb-2">Formal Evaluations</h4>
                                <p class="text-xs text-slate-400 mb-4">Official cycle ratings, metric score breakdowns, and manager remarks for employee appraisals.</p>
                            </div>
                            <div class="space-y-2">
                                <a href="/export.php?type=evaluations&format=csv" class="block w-full text-center py-2 px-3 bg-purple-600 hover:bg-purple-500 text-white font-semibold text-xs rounded-lg transition shadow-sm">
                                    Download CSV File
                                </a>
                                <a href="/export.php?type=evaluations&format=html" target="_blank" class="block w-full text-center py-2 px-3 bg-slate-800 hover:bg-slate-700 text-slate-300 font-semibold text-xs rounded-lg transition border border-slate-700">
                                    Printable HTML View
                                </a>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        <?php endif; ?>

    </main>

    <!-- Footer -->
    <footer class="border-t border-slate-800 py-4 text-center text-xs text-slate-500">
        &copy; <?= date('Y') ?> PharmaDEM Solutions. Management &amp; Supervisor Control System.
    </footer>

    <script>
    function recalcScore() {
        const task = parseFloat(document.getElementById('score_task')?.value) || 0;
        const punc = parseFloat(document.getElementById('score_punc')?.value) || 0;
        const qual = parseFloat(document.getElementById('score_qual')?.value) || 0;

        document.getElementById('disp_task').innerText = task.toFixed(1);
        document.getElementById('disp_punc').innerText = punc.toFixed(1);
        document.getElementById('disp_qual').innerText = qual.toFixed(1);

        const overall = ((task + punc + qual) / 3).toFixed(2);
        document.getElementById('disp_overall').innerText = overall + " / 100";
    }
    </script>

</body>
</html>
