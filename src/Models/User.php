<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\Database;
use PDO;

class User
{
    /**
     * Find active user by email address.
     *
     * @param string $email
     * @return array|null
     */
    public static function findByEmail(string $email): ?array
    {
        $db = Database::getInstance();
        $stmt = $db->prepare("
            SELECT u.*, d.name AS department_name 
            FROM users u
            LEFT JOIN departments d ON u.department_id = d.id
            WHERE u.email = :email AND u.status = 'Active'
            LIMIT 1
        ");
        $stmt->execute(['email' => strtolower(trim($email))]);
        $user = $stmt->fetch();

        return $user ?: null;
    }

    /**
     * Find user by ID.
     *
     * @param int $id
     * @return array|null
     */
    public static function findById(int $id): ?array
    {
        $db = Database::getInstance();
        $stmt = $db->prepare("
            SELECT u.id, u.name, u.email, u.role, u.department_id, u.status, u.created_at, d.name AS department_name
            FROM users u
            LEFT JOIN departments d ON u.department_id = d.id
            WHERE u.id = :id
            LIMIT 1
        ");
        $stmt->execute(['id' => $id]);
        $user = $stmt->fetch();

        return $user ?: null;
    }

    /**
     * Create or update a user with hashed password.
     *
     * @param array $data
     * @return int Inserted ID
     */
    public static function create(array $data): int
    {
        $db = Database::getInstance();
        $stmt = $db->prepare("
            INSERT INTO users (name, email, password, role, department_id, status)
            VALUES (:name, :email, :password, :role, :department_id, :status)
        ");

        $hashedPassword = password_hash($data['password'], PASSWORD_BCRYPT, ['cost' => 12]);

        $stmt->execute([
            'name'          => trim($data['name']),
            'email'         => strtolower(trim($data['email'])),
            'password'      => $hashedPassword,
            'role'          => $data['role'],
            'department_id' => $data['department_id'] ?? null,
            'status'        => $data['status'] ?? 'Active',
        ]);

        return (int) $db->lastInsertId();
    }

    /**
     * Retrieve all active employees (and optional department filter) for assignment dropdowns.
     *
     * @param int|null $departmentId
     * @return array
     */
    public static function getAllAssignable(?int $departmentId = null): array
    {
        $db = Database::getInstance();
        $sql = "
            SELECT u.id, u.name, u.email, u.role, d.name AS department_name
            FROM users u
            LEFT JOIN departments d ON u.department_id = d.id
            WHERE u.status = 'Active' AND u.role IN ('Employee', 'Manager')
        ";

        $params = [];
        if ($departmentId !== null) {
            $sql .= " AND u.department_id = :dept_id";
            $params['dept_id'] = $departmentId;
        }

        $sql .= " ORDER BY d.name ASC, u.name ASC";

        $stmt = $db->prepare($sql);
        $stmt->execute($params);

        return $stmt->fetchAll();
    }
}
