<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/src/autoload.php';

use App\Core\Auth;
use App\Core\Env;
use App\Core\Session;
use App\Models\DailyUpdate;
use App\Models\Evaluation;

Env::load(dirname(__DIR__) . '/.env');
Session::start();

// Strict RBAC: Only Managers and Admins can export reporting data
Auth::requireRole(['Manager', 'Admin']);

$user = Auth::user();
$departmentFilter = ($user['role'] === 'Manager' && !empty($user['department_id'])) 
    ? (int) $user['department_id'] 
    : null;

$type = $_GET['type'] ?? 'daily_updates';
$format = strtolower($_GET['format'] ?? 'csv');
$period = $_GET['period'] ?? null;
$timestamp = date('Ymd_His');

// CSV Export
if ($format === 'csv') {
    if ($type === 'daily_updates') {
        $filename = "daily_work_logs_{$timestamp}.csv";
        $data = DailyUpdate::getAllWithEmployees($departmentFilter);

        header('Content-Type: text/csv; charset=utf-8');
        header("Content-Disposition: attachment; filename=\"{$filename}\"");
        header('Pragma: no-cache');
        header('Expires: 0');

        $output = fopen('php://output', 'w');
        // Add UTF-8 BOM for Excel compatibility
        fprintf($output, chr(0xEF).chr(0xBB).chr(0xBF));

        fputcsv($output, [
            'Update ID',
            'Date',
            'Employee Name',
            'Employee Email',
            'Department',
            'To-Do Tasks',
            'Completed Work',
            'Pending Blockers',
            'Status',
            'Reviewer Name',
            'Manager Remarks',
            'Submitted At'
        ]);

        foreach ($data as $row) {
            fputcsv($output, [
                $row['id'],
                $row['update_date'],
                $row['employee_name'],
                $row['employee_email'],
                $row['department_name'] ?? 'General',
                $row['todo_tasks'],
                $row['completed_work'],
                $row['pending_blockers'] ?: 'None',
                $row['status'],
                $row['reviewer_name'] ?: 'N/A',
                $row['review_notes'] ?: 'None',
                $row['created_at'],
            ]);
        }

        fclose($output);
        exit;
    }

    if ($type === 'evaluations') {
        $filename = "performance_evaluations_{$timestamp}.csv";
        $data = Evaluation::getAllEvaluations($departmentFilter, $period);

        header('Content-Type: text/csv; charset=utf-8');
        header("Content-Disposition: attachment; filename=\"{$filename}\"");
        header('Pragma: no-cache');
        header('Expires: 0');

        $output = fopen('php://output', 'w');
        fprintf($output, chr(0xEF).chr(0xBB).chr(0xBF));

        fputcsv($output, [
            'Evaluation ID',
            'Period',
            'Employee Name',
            'Employee Email',
            'Department',
            'Task Completion Score (%)',
            'Punctuality Score (%)',
            'Quality Score (%)',
            'Overall Score (/100)',
            'Evaluator Name',
            'Manager Remarks',
            'Evaluated Date'
        ]);

        foreach ($data as $row) {
            fputcsv($output, [
                $row['id'],
                $row['evaluation_period'],
                $row['employee_name'],
                $row['employee_email'],
                $row['department_name'] ?? 'General',
                $row['task_completion_score'],
                $row['punctuality_score'],
                $row['quality_score'],
                $row['overall_score'],
                $row['evaluator_name'],
                $row['manager_remarks'] ?: 'None',
                $row['created_at'],
            ]);
        }

        fclose($output);
        exit;
    }

    if ($type === 'performance_summary') {
        $filename = "performance_summary_analytics_{$timestamp}.csv";
        $data = Evaluation::getTeamPerformanceSummary($departmentFilter);

        header('Content-Type: text/csv; charset=utf-8');
        header("Content-Disposition: attachment; filename=\"{$filename}\"");
        header('Pragma: no-cache');
        header('Expires: 0');

        $output = fopen('php://output', 'w');
        fprintf($output, chr(0xEF).chr(0xBB).chr(0xBF));

        fputcsv($output, [
            'Employee ID',
            'Employee Name',
            'Employee Email',
            'Department',
            'Total Tasks Assigned',
            'Completed Tasks',
            'On-Time Completed Tasks',
            'Overdue Tasks',
            'Task Completion Rate (%)',
            'On-Time Rate (%)',
            'Overdue Rate (%)',
            'Average Manager Rating (/100)'
        ]);

        foreach ($data as $row) {
            fputcsv($output, [
                $row['employee_id'],
                $row['employee_name'],
                $row['employee_email'],
                $row['department_name'] ?? 'General',
                $row['total_tasks'],
                $row['completed_tasks'],
                $row['on_time_completed'],
                $row['overdue_tasks'],
                $row['completion_rate'] . '%',
                $row['on_time_rate'] . '%',
                $row['overdue_rate'] . '%',
                $row['avg_manager_score'] !== null ? $row['avg_manager_score'] : 'N/A',
            ]);
        }

        fclose($output);
        exit;
    }
}

// Printable HTML Report
$reportTitle = match ($type) {
    'evaluations'         => 'Performance Evaluations Executive Report',
    'performance_summary' => 'Automated Performance & Task Completion Analytics Report',
    default               => 'Employee Daily Work Submissions Log',
};

$data = match ($type) {
    'evaluations'         => Evaluation::getAllEvaluations($departmentFilter, $period),
    'performance_summary' => Evaluation::getTeamPerformanceSummary($departmentFilter),
    default               => DailyUpdate::getAllWithEmployees($departmentFilter),
};
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($reportTitle) ?> &mdash; PharmaDEM Solutions</title>
    <style>
        * { box-sizing: border-box; margin: 0; padding: 0; }
        body {
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Arial, sans-serif;
            background: #ffffff;
            color: #1e293b;
            padding: 2rem;
            font-size: 13px;
            line-height: 1.5;
        }
        .header {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            border-bottom: 2px solid #0f172a;
            padding-bottom: 1rem;
            margin-bottom: 1.5rem;
        }
        .brand h1 { font-size: 20px; color: #0f172a; font-weight: 700; }
        .brand p { font-size: 12px; color: #64748b; }
        .meta { text-align: right; font-size: 12px; color: #64748b; }
        .no-print-bar {
            background: #f1f5f9;
            border: 1px solid #cbd5e1;
            border-radius: 6px;
            padding: 0.75rem 1rem;
            margin-bottom: 1.5rem;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        .btn {
            background: #2563eb;
            color: #ffffff;
            border: none;
            padding: 0.4rem 1rem;
            border-radius: 4px;
            cursor: pointer;
            font-weight: 600;
            text-decoration: none;
            font-size: 12px;
        }
        .btn-outline {
            background: #ffffff;
            color: #334155;
            border: 1px solid #cbd5e1;
            margin-left: 0.5rem;
        }
        table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 1rem;
        }
        th, td {
            border: 1px solid #cbd5e1;
            padding: 8px 10px;
            text-align: left;
            vertical-align: top;
        }
        th {
            background: #f8fafc;
            font-weight: 600;
            color: #334155;
        }
        tr:nth-child(even) { background: #fbfcfd; }
        .badge {
            display: inline-block;
            padding: 2px 6px;
            border-radius: 3px;
            font-size: 11px;
            font-weight: 600;
        }
        .badge-Reviewed { background: #dcfce7; color: #166534; }
        .badge-Submitted { background: #dbeafe; color: #1e40af; }
        @media print {
            .no-print-bar { display: none; }
            body { padding: 0; }
        }
    </style>
</head>
<body>

    <div class="no-print-bar">
        <div>
            <strong>Report Preview Mode</strong> &bull; Generated by <?= htmlspecialchars($user['name']) ?> (<?= htmlspecialchars($user['role']) ?>)
        </div>
        <div>
            <button onclick="window.print()" class="btn">Print / Save to PDF</button>
            <a href="/export.php?type=<?= urlencode($type) ?>&format=csv" class="btn btn-outline">Download CSV</a>
            <a href="/manager/dashboard.php" class="btn btn-outline">Back to Dashboard</a>
        </div>
    </div>

    <div class="header">
        <div class="brand">
            <h1>PharmaDEM Solutions</h1>
            <p><?= htmlspecialchars($reportTitle) ?></p>
        </div>
        <div class="meta">
            <div><strong>Generated Date:</strong> <?= date('F j, Y - H:i:s') ?></div>
            <div><strong>Department:</strong> <?= htmlspecialchars($user['department_name'] ?? 'All Organization') ?></div>
            <div><strong>Total Records:</strong> <?= count($data) ?></div>
        </div>
    </div>

    <?php if ($type === 'evaluations'): ?>
        <table>
            <thead>
                <tr>
                    <th>Cycle</th>
                    <th>Employee</th>
                    <th>Department</th>
                    <th>Task Score</th>
                    <th>Punctuality</th>
                    <th>Quality</th>
                    <th>Overall Rating</th>
                    <th>Evaluator</th>
                    <th>Manager Remarks</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($data as $r): ?>
                    <tr>
                        <td><strong><?= htmlspecialchars($r['evaluation_period']) ?></strong></td>
                        <td><?= htmlspecialchars($r['employee_name']) ?></td>
                        <td><?= htmlspecialchars($r['department_name'] ?? 'General') ?></td>
                        <td><?= htmlspecialchars((string)$r['task_completion_score']) ?>%</td>
                        <td><?= htmlspecialchars((string)$r['punctuality_score']) ?>%</td>
                        <td><?= htmlspecialchars((string)$r['quality_score']) ?>%</td>
                        <td><strong><?= htmlspecialchars((string)$r['overall_score']) ?> / 100</strong></td>
                        <td><?= htmlspecialchars($r['evaluator_name']) ?></td>
                        <td><?= nl2br(htmlspecialchars($r['manager_remarks'] ?: 'None')) ?></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>

    <?php elseif ($type === 'performance_summary'): ?>
        <table>
            <thead>
                <tr>
                    <th>Employee Name</th>
                    <th>Department</th>
                    <th>Total Tasks</th>
                    <th>Completed Tasks</th>
                    <th>Completion Rate</th>
                    <th>On-Time Rate</th>
                    <th>Overdue Rate</th>
                    <th>Avg Manager Rating</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($data as $r): ?>
                    <tr>
                        <td><strong><?= htmlspecialchars($r['employee_name']) ?></strong></td>
                        <td><?= htmlspecialchars($r['department_name'] ?? 'General') ?></td>
                        <td><?= (int)$r['total_tasks'] ?></td>
                        <td><?= (int)$r['completed_tasks'] ?></td>
                        <td><strong><?= $r['completion_rate'] ?>%</strong></td>
                        <td><?= $r['on_time_rate'] ?>%</td>
                        <td style="<?= $r['overdue_rate'] > 0 ? 'color: #dc2626; font-weight: 600;' : '' ?>">
                            <?= $r['overdue_rate'] ?>% (<?= (int)$r['overdue_tasks'] ?>)
                        </td>
                        <td>
                            <strong><?= $r['avg_manager_score'] !== null ? $r['avg_manager_score'] . ' / 100' : 'N/A' ?></strong>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>

    <?php else: ?>
        <table>
            <thead>
                <tr>
                    <th>Date</th>
                    <th>Employee</th>
                    <th>Department</th>
                    <th>Planned To-Do</th>
                    <th>Completed Work</th>
                    <th>Pending Blockers</th>
                    <th>Status</th>
                    <th>Manager Review</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($data as $r): ?>
                    <tr>
                        <td><strong><?= htmlspecialchars($r['update_date']) ?></strong></td>
                        <td><?= htmlspecialchars($r['employee_name']) ?></td>
                        <td><?= htmlspecialchars($r['department_name'] ?? 'General') ?></td>
                        <td><?= nl2br(htmlspecialchars($r['todo_tasks'])) ?></td>
                        <td><?= nl2br(htmlspecialchars($r['completed_work'])) ?></td>
                        <td><?= htmlspecialchars($r['pending_blockers'] ?: 'None') ?></td>
                        <td><span class="badge badge-<?= htmlspecialchars($r['status']) ?>"><?= htmlspecialchars($r['status']) ?></span></td>
                        <td>
                            <?= nl2br(htmlspecialchars($r['review_notes'] ?: 'Pending')) ?>
                            <?php if (!empty($r['reviewer_name'])): ?>
                                <div style="font-size: 11px; color: #64748b; margin-top: 4px;">By: <?= htmlspecialchars($r['reviewer_name']) ?></div>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    <?php endif; ?>

</body>
</html>
