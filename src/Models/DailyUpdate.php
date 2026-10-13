<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\Database;
use PDO;

class DailyUpdate
{
    /**
     * Retrieve daily update for an employee on a given date.
     *
     * @param int $employeeId
     * @param string $date YYYY-MM-DD
     * @return array|null
     */
    public static function getByDate(int $employeeId, string $date): ?array
    {
        $db = Database::getInstance();
        $stmt = $db->prepare("
            SELECT du.*, u.name AS reviewer_name
            FROM daily_updates du
            LEFT JOIN users u ON du.reviewed_by = u.id
            WHERE du.employee_id = :employee_id AND du.update_date = :update_date
            LIMIT 1
        ");
        $stmt->execute([
            'employee_id' => $employeeId,
            'update_date' => $date,
        ]);

        $result = $stmt->fetch();
        return $result ?: null;
    }

    /**
     * Create or update daily work update for today.
     *
     * @param int $employeeId
     * @param string $date
     * @param string $todoTasks
     * @param string $completedWork
     * @param string|null $pendingBlockers
     * @return bool
     */
    public static function save(
        int $employeeId,
        string $date,
        string $todoTasks,
        string $completedWork,
        ?string $pendingBlockers
    ): bool {
        $db = Database::getInstance();
        $stmt = $db->prepare("
            INSERT INTO daily_updates (employee_id, update_date, todo_tasks, completed_work, pending_blockers, status)
            VALUES (:employee_id, :update_date, :todo_tasks, :completed_work, :pending_blockers, 'Submitted')
            ON DUPLICATE KEY UPDATE
                todo_tasks = VALUES(todo_tasks),
                completed_work = VALUES(completed_work),
                pending_blockers = VALUES(pending_blockers),
                status = 'Submitted',
                updated_at = CURRENT_TIMESTAMP
        ");

        return $stmt->execute([
            'employee_id'      => $employeeId,
            'update_date'      => $date,
            'todo_tasks'       => trim($todoTasks),
            'completed_work'   => trim($completedWork),
            'pending_blockers' => $pendingBlockers ? trim($pendingBlockers) : null,
        ]);
    }

    /**
     * Retrieve chronological history of daily submissions for an employee.
     *
     * @param int $employeeId
     * @param int $limit
     * @return array
     */
    public static function getHistory(int $employeeId, int $limit = 30): array
    {
        $db = Database::getInstance();
        $stmt = $db->prepare("
            SELECT du.*, u.name AS reviewer_name, u.email AS reviewer_email
            FROM daily_updates du
            LEFT JOIN users u ON du.reviewed_by = u.id
            WHERE du.employee_id = :employee_id
            ORDER BY du.update_date DESC
            LIMIT :limit
        ");
        $stmt->bindValue(':employee_id', $employeeId, PDO::PARAM_INT);
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->fetchAll();
    }

    /**
     * Review a daily work update: write feedback notes and mark status as Reviewed.
     *
     * @param int $updateId
     * @param int $reviewerId
     * @param string $reviewNotes
     * @param string $status
     * @return bool
     */
    public static function reviewUpdate(int $updateId, int $reviewerId, string $reviewNotes, string $status = 'Reviewed'): bool
    {
        $db = Database::getInstance();
        $stmt = $db->prepare("
            UPDATE daily_updates 
            SET status = :status,
                reviewed_by = :reviewed_by,
                review_notes = :review_notes,
                reviewed_at = CURRENT_TIMESTAMP,
                updated_at = CURRENT_TIMESTAMP
            WHERE id = :id
        ");

        return $stmt->execute([
            'status'       => $status,
            'reviewed_by'  => $reviewerId,
            'review_notes' => trim($reviewNotes),
            'id'           => $updateId,
        ]);
    }

    /**
     * Retrieve daily updates across employees for manager review with optional filters.
     *
     * @param int|null $departmentId
     * @param string|null $dateFilter
     * @param string|null $statusFilter
     * @return array
     */
    public static function getAllWithEmployees(
        ?int $departmentId = null,
        ?string $dateFilter = null,
        ?string $statusFilter = null
    ): array {
        $db = Database::getInstance();
        $sql = "
            SELECT du.*, 
                   u.name AS employee_name, 
                   u.email AS employee_email,
                   d.name AS department_name,
                   r.name AS reviewer_name
            FROM daily_updates du
            JOIN users u ON du.employee_id = u.id
            LEFT JOIN departments d ON u.department_id = d.id
            LEFT JOIN users r ON du.reviewed_by = r.id
            WHERE 1=1
        ";

        $params = [];

        if ($departmentId !== null) {
            $sql .= " AND u.department_id = :dept_id";
            $params['dept_id'] = $departmentId;
        }

        if (!empty($dateFilter)) {
            $sql .= " AND du.update_date = :update_date";
            $params['update_date'] = $dateFilter;
        }

        if (!empty($statusFilter) && in_array($statusFilter, ['Submitted', 'Reviewed'], true)) {
            $sql .= " AND du.status = :status";
            $params['status'] = $statusFilter;
        }

        $sql .= " ORDER BY du.update_date DESC, du.created_at DESC";

        $stmt = $db->prepare($sql);
        $stmt->execute($params);

        return $stmt->fetchAll();
    }

    /**
     * Get team update submission statistics for today.
     *
     * @param int|null $departmentId
     * @return array
     */
    public static function getTodaySubmissionMetrics(?int $departmentId = null): array
    {
        $db = Database::getInstance();
        $today = date('Y-m-d');

        // Total active employees
        $empSql = "SELECT COUNT(*) FROM users WHERE status = 'Active' AND role = 'Employee'";
        $empParams = [];
        if ($departmentId !== null) {
            $empSql .= " AND department_id = :dept_id";
            $empParams['dept_id'] = $departmentId;
        }
        $empStmt = $db->prepare($empSql);
        $empStmt->execute($empParams);
        $totalEmployees = (int) $empStmt->fetchColumn();

        // Total updates submitted today
        $subSql = "
            SELECT 
                COUNT(*) AS total_submitted_today,
                SUM(CASE WHEN du.status = 'Reviewed' THEN 1 ELSE 0 END) AS reviewed_today,
                SUM(CASE WHEN du.status = 'Submitted' THEN 1 ELSE 0 END) AS pending_review_today
            FROM daily_updates du
            JOIN users u ON du.employee_id = u.id
            WHERE du.update_date = :today
        ";
        $subParams = ['today' => $today];
        if ($departmentId !== null) {
            $subSql .= " AND u.department_id = :dept_id";
            $subParams['dept_id'] = $departmentId;
        }
        $subStmt = $db->prepare($subSql);
        $subStmt->execute($subParams);
        $subData = $subStmt->fetch() ?: [];

        $submittedToday = (int) ($subData['total_submitted_today'] ?? 0);
        $reviewedToday = (int) ($subData['reviewed_today'] ?? 0);
        $pendingReview = (int) ($subData['pending_review_today'] ?? 0);
        $notSubmitted = max(0, $totalEmployees - $submittedToday);

        return [
            'total_employees'        => $totalEmployees,
            'submitted_today'        => $submittedToday,
            'not_submitted_today'    => $notSubmitted,
            'reviewed_today'         => $reviewedToday,
            'pending_review_today'   => $pendingReview,
            'compliance_percentage'  => $totalEmployees > 0 ? round(($submittedToday / $totalEmployees) * 100, 1) : 0,
        ];
    }
}
