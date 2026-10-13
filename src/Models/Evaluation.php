<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\Database;
use PDO;

class Evaluation
{
    /**
     * Retrieve all evaluations filed for an employee.
     *
     * @param int $employeeId
     * @return array
     */
    public static function getByEmployee(int $employeeId): array
    {
        $db = Database::getInstance();
        $stmt = $db->prepare("
            SELECT pe.*, u.name AS evaluator_name, u.email AS evaluator_email, u.role AS evaluator_role
            FROM performance_evaluations pe
            JOIN users u ON pe.evaluator_id = u.id
            WHERE pe.employee_id = :employee_id
            ORDER BY pe.evaluation_period DESC, pe.created_at DESC
        ");
        $stmt->execute(['employee_id' => $employeeId]);

        return $stmt->fetchAll();
    }

    /**
     * Retrieve aggregate score metrics for an employee.
     *
     * @param int $employeeId
     * @return array
     */
    public static function getAverageScores(int $employeeId): array
    {
        $db = Database::getInstance();
        $stmt = $db->prepare("
            SELECT 
                COUNT(*) as total_evaluations,
                ROUND(AVG(overall_score), 2) as avg_overall,
                ROUND(AVG(task_completion_score), 2) as avg_completion,
                ROUND(AVG(punctuality_score), 2) as avg_punctuality,
                ROUND(AVG(quality_score), 2) as avg_quality
            FROM performance_evaluations
            WHERE employee_id = :employee_id
        ");
        $stmt->execute(['employee_id' => $employeeId]);

        return $stmt->fetch() ?: [
            'total_evaluations' => 0,
            'avg_overall' => 0.0,
            'avg_completion' => 0.0,
            'avg_punctuality' => 0.0,
            'avg_quality' => 0.0,
        ];
    }

    /**
     * Create or update a performance evaluation for an employee and period.
     *
     * @param array $data
     * @return bool
     */
    public static function createOrUpdate(array $data): bool
    {
        $db = Database::getInstance();
        $stmt = $db->prepare("
            INSERT INTO performance_evaluations 
                (employee_id, evaluator_id, evaluation_period, task_completion_score, punctuality_score, quality_score, manager_remarks)
            VALUES 
                (:employee_id, :evaluator_id, :evaluation_period, :task_completion_score, :punctuality_score, :quality_score, :manager_remarks)
            ON DUPLICATE KEY UPDATE
                evaluator_id = VALUES(evaluator_id),
                task_completion_score = VALUES(task_completion_score),
                punctuality_score = VALUES(punctuality_score),
                quality_score = VALUES(quality_score),
                manager_remarks = VALUES(manager_remarks),
                updated_at = CURRENT_TIMESTAMP
        ");

        return $stmt->execute([
            'employee_id'           => (int) $data['employee_id'],
            'evaluator_id'          => (int) $data['evaluator_id'],
            'evaluation_period'     => trim($data['evaluation_period']),
            'task_completion_score' => (float) $data['task_completion_score'],
            'punctuality_score'     => (float) $data['punctuality_score'],
            'quality_score'         => (float) $data['quality_score'],
            'manager_remarks'       => !empty($data['manager_remarks']) ? trim($data['manager_remarks']) : null,
        ]);
    }

    /**
     * Retrieve all evaluations across employees for management reporting.
     *
     * @param int|null $departmentId
     * @param string|null $period
     * @return array
     */
    public static function getAllEvaluations(?int $departmentId = null, ?string $period = null): array
    {
        $db = Database::getInstance();
        $sql = "
            SELECT pe.*,
                   u_emp.name AS employee_name,
                   u_emp.email AS employee_email,
                   d.name AS department_name,
                   u_eval.name AS evaluator_name
            FROM performance_evaluations pe
            JOIN users u_emp ON pe.employee_id = u_emp.id
            LEFT JOIN departments d ON u_emp.department_id = d.id
            JOIN users u_eval ON pe.evaluator_id = u_eval.id
            WHERE 1=1
        ";

        $params = [];

        if ($departmentId !== null) {
            $sql .= " AND u_emp.department_id = :dept_id";
            $params['dept_id'] = $departmentId;
        }

        if (!empty($period)) {
            $sql .= " AND pe.evaluation_period = :period";
            $params['period'] = $period;
        }

        $sql .= " ORDER BY pe.evaluation_period DESC, pe.created_at DESC";

        $stmt = $db->prepare($sql);
        $stmt->execute($params);

        return $stmt->fetchAll();
    }

    /**
     * Performance Summary View: Analytics comparing automated task completion rates
     * (on-time vs overdue) alongside manager ratings.
     *
     * @param int|null $departmentId
     * @return array
     */
    public static function getTeamPerformanceSummary(?int $departmentId = null): array
    {
        // First ensure overdue tasks are synchronized
        Task::updateOverdueTasks();

        $db = Database::getInstance();
        $sql = "
            SELECT 
                u.id AS employee_id,
                u.name AS employee_name,
                u.email AS employee_email,
                d.name AS department_name,
                COUNT(t.id) AS total_tasks,
                SUM(CASE WHEN t.status = 'Completed' THEN 1 ELSE 0 END) AS completed_tasks,
                SUM(CASE WHEN t.status = 'In Progress' THEN 1 ELSE 0 END) AS in_progress_tasks,
                SUM(CASE WHEN t.status = 'Pending' THEN 1 ELSE 0 END) AS pending_tasks,
                SUM(CASE WHEN t.status = 'Overdue' THEN 1 ELSE 0 END) AS overdue_tasks,
                SUM(CASE 
                    WHEN t.status = 'Completed' AND (t.completed_at IS NULL OR DATE(t.completed_at) <= t.due_date) THEN 1 
                    ELSE 0 
                END) AS on_time_completed,
                ROUND(AVG(pe.overall_score), 2) AS avg_manager_score,
                ROUND(AVG(pe.task_completion_score), 2) AS avg_task_score,
                ROUND(AVG(pe.punctuality_score), 2) AS avg_punctuality_score,
                ROUND(AVG(pe.quality_score), 2) AS avg_quality_score,
                COUNT(DISTINCT pe.id) AS total_evaluations_count
            FROM users u
            LEFT JOIN departments d ON u.department_id = d.id
            LEFT JOIN tasks t ON t.assigned_to = u.id
            LEFT JOIN performance_evaluations pe ON pe.employee_id = u.id
            WHERE u.status = 'Active' AND u.role = 'Employee'
        ";

        $params = [];
        if ($departmentId !== null) {
            $sql .= " AND u.department_id = :dept_id";
            $params['dept_id'] = $departmentId;
        }

        $sql .= " GROUP BY u.id ORDER BY avg_manager_score DESC, completed_tasks DESC";

        $stmt = $db->prepare($sql);
        $stmt->execute($params);
        $results = $stmt->fetchAll();

        // Calculate automated completion rates
        foreach ($results as &$row) {
            $total = (int) $row['total_tasks'];
            $completed = (int) $row['completed_tasks'];
            $onTime = (int) $row['on_time_completed'];
            $overdue = (int) $row['overdue_tasks'];

            $row['completion_rate'] = $total > 0 ? round(($completed / $total) * 100, 1) : 0.0;
            $row['on_time_rate']    = $completed > 0 ? round(($onTime / $completed) * 100, 1) : 0.0;
            $row['overdue_rate']    = $total > 0 ? round(($overdue / $total) * 100, 1) : 0.0;
        }

        return $results;
    }
}
