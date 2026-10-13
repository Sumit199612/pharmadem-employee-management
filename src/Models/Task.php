<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\Database;
use PDO;

class Task
{
    /**
     * Retrieve tasks assigned to a specific employee, optionally filtered by status.
     *
     * @param int $employeeId
     * @param string|null $statusFilter
     * @return array
     */
    public static function getByEmployee(int $employeeId, ?string $statusFilter = null): array
    {
        $db = Database::getInstance();
        $sql = "
            SELECT t.*, u.name AS assigned_by_name, u.email AS assigned_by_email
            FROM tasks t
            LEFT JOIN users u ON t.assigned_by = u.id
            WHERE t.assigned_to = :employee_id
        ";

        $params = ['employee_id' => $employeeId];

        if ($statusFilter && in_array($statusFilter, ['Pending', 'In Progress', 'Completed', 'Overdue'], true)) {
            $sql .= " AND t.status = :status";
            $params['status'] = $statusFilter;
        }

        $sql .= " ORDER BY CASE t.priority 
                    WHEN 'Urgent' THEN 1 
                    WHEN 'High' THEN 2 
                    WHEN 'Medium' THEN 3 
                    WHEN 'Low' THEN 4 
                    ELSE 5 END ASC, t.due_date ASC";

        $stmt = $db->prepare($sql);
        $stmt->execute($params);

        return $stmt->fetchAll();
    }

    /**
     * Update task status for an assigned employee.
     *
     * @param int $taskId
     * @param int $employeeId
     * @param string $newStatus
     * @return bool
     */
    public static function updateStatusByEmployee(int $taskId, int $employeeId, string $newStatus): bool
    {
        $validStatuses = ['Pending', 'In Progress', 'Completed'];
        if (!in_array($newStatus, $validStatuses, true)) {
            return false;
        }

        $db = Database::getInstance();
        $completedAt = ($newStatus === 'Completed') ? date('Y-m-d H:i:s') : null;

        $stmt = $db->prepare("
            UPDATE tasks 
            SET status = :status, 
                completed_at = :completed_at,
                updated_at = CURRENT_TIMESTAMP
            WHERE id = :id AND assigned_to = :employee_id
        ");

        return $stmt->execute([
            'status'       => $newStatus,
            'completed_at' => $completedAt,
            'id'           => $taskId,
            'employee_id'  => $employeeId,
        ]);
    }

    /**
     * Automated Overdue Tracker: Flags all tasks past due date as 'Overdue'.
     *
     * @return int Number of affected rows
     */
    public static function updateOverdueTasks(): int
    {
        $db = Database::getInstance();
        $stmt = $db->prepare("
            UPDATE tasks 
            SET status = 'Overdue',
                updated_at = CURRENT_TIMESTAMP
            WHERE due_date < CURDATE() 
              AND status NOT IN ('Completed', 'Overdue')
        ");
        $stmt->execute();
        return $stmt->rowCount();
    }

    /**
     * Create and delegate a new task to an employee.
     *
     * @param array $data
     * @return int Inserted Task ID
     */
    public static function create(array $data): int
    {
        $db = Database::getInstance();
        $stmt = $db->prepare("
            INSERT INTO tasks (title, description, assigned_to, assigned_by, priority, status, due_date)
            VALUES (:title, :description, :assigned_to, :assigned_by, :priority, 'Pending', :due_date)
        ");

        $stmt->execute([
            'title'       => trim($data['title']),
            'description' => !empty($data['description']) ? trim($data['description']) : null,
            'assigned_to' => (int) $data['assigned_to'],
            'assigned_by' => (int) $data['assigned_by'],
            'priority'    => $data['priority'] ?? 'Medium',
            'due_date'    => $data['due_date'],
        ]);

        return (int) $db->lastInsertId();
    }

    /**
     * Retrieve all tasks with employee and assigner details.
     *
     * @param int|null $departmentId Optional filter by department
     * @param string|null $statusFilter Optional status filter
     * @return array
     */
    public static function getAll(?int $departmentId = null, ?string $statusFilter = null): array
    {
        self::updateOverdueTasks();

        $db = Database::getInstance();
        $sql = "
            SELECT t.*, 
                   u_to.name AS assigned_to_name, 
                   u_to.email AS assigned_to_email,
                   d.name AS department_name,
                   u_by.name AS assigned_by_name
            FROM tasks t
            JOIN users u_to ON t.assigned_to = u_to.id
            LEFT JOIN departments d ON u_to.department_id = d.id
            JOIN users u_by ON t.assigned_by = u_by.id
            WHERE 1=1
        ";

        $params = [];

        if ($departmentId !== null) {
            $sql .= " AND u_to.department_id = :dept_id";
            $params['dept_id'] = $departmentId;
        }

        if ($statusFilter && in_array($statusFilter, ['Pending', 'In Progress', 'Completed', 'Overdue'], true)) {
            $sql .= " AND t.status = :status";
            $params['status'] = $statusFilter;
        }

        $sql .= " ORDER BY 
                    CASE t.status 
                        WHEN 'Overdue' THEN 1 
                        WHEN 'In Progress' THEN 2 
                        WHEN 'Pending' THEN 3 
                        ELSE 4 END ASC,
                    CASE t.priority 
                        WHEN 'Urgent' THEN 1 
                        WHEN 'High' THEN 2 
                        WHEN 'Medium' THEN 3 
                        ELSE 4 END ASC,
                    t.due_date ASC";

        $stmt = $db->prepare($sql);
        $stmt->execute($params);

        return $stmt->fetchAll();
    }

    /**
     * Get real-time overview metrics for management dashboards.
     *
     * @param int|null $departmentId
     * @return array
     */
    public static function getTeamMetrics(?int $departmentId = null): array
    {
        self::updateOverdueTasks();

        $db = Database::getInstance();
        $sql = "
            SELECT 
                COUNT(*) AS total_tasks,
                SUM(CASE WHEN t.status = 'Completed' THEN 1 ELSE 0 END) AS completed_tasks,
                SUM(CASE WHEN t.status = 'In Progress' THEN 1 ELSE 0 END) AS in_progress_tasks,
                SUM(CASE WHEN t.status = 'Pending' THEN 1 ELSE 0 END) AS pending_tasks,
                SUM(CASE WHEN t.status = 'Overdue' THEN 1 ELSE 0 END) AS overdue_tasks
            FROM tasks t
            JOIN users u ON t.assigned_to = u.id
            WHERE 1=1
        ";

        $params = [];
        if ($departmentId !== null) {
            $sql .= " AND u.department_id = :dept_id";
            $params['dept_id'] = $departmentId;
        }

        $stmt = $db->prepare($sql);
        $stmt->execute($params);

        return $stmt->fetch() ?: [
            'total_tasks'       => 0,
            'completed_tasks'   => 0,
            'in_progress_tasks' => 0,
            'pending_tasks'     => 0,
            'overdue_tasks'     => 0,
        ];
    }

    /**
     * Get task statistics for employee.
     *
     * @param int $employeeId
     * @return array
     */
    public static function getStatsByEmployee(int $employeeId): array
    {
        self::updateOverdueTasks();

        $db = Database::getInstance();
        $stmt = $db->prepare("
            SELECT 
                COUNT(*) AS total,
                SUM(CASE WHEN status = 'Pending' THEN 1 ELSE 0 END) AS pending,
                SUM(CASE WHEN status = 'In Progress' THEN 1 ELSE 0 END) AS in_progress,
                SUM(CASE WHEN status = 'Completed' THEN 1 ELSE 0 END) AS completed,
                SUM(CASE WHEN status = 'Overdue' THEN 1 ELSE 0 END) AS overdue
            FROM tasks
            WHERE assigned_to = :employee_id
        ");
        $stmt->execute(['employee_id' => $employeeId]);
        return $stmt->fetch() ?: ['total' => 0, 'pending' => 0, 'in_progress' => 0, 'completed' => 0, 'overdue' => 0];
    }
}
