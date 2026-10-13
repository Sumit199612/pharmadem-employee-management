<?php

declare(strict_types=1);

namespace App\Core;

use App\Models\User;

class Auth
{
    private const SESSION_USER_KEY = 'auth_user';

    public static function attempt(string $email, string $password): bool
    {
        $user = User::findByEmail($email);
        if (!$user) {
            return false;
        }

        if (!password_verify($password, $user['password'])) {
            return false;
        }

        if (password_needs_rehash($user['password'], PASSWORD_BCRYPT, ['cost' => 12])) {
            $newHash = password_hash($password, PASSWORD_BCRYPT, ['cost' => 12]);
            $db = Database::getInstance();
            $stmt = $db->prepare("UPDATE users SET password = :password WHERE id = :id");
            $stmt->execute(['password' => $newHash, 'id' => $user['id']]);
        }

        Session::regenerate();

        Session::set(self::SESSION_USER_KEY, [
            'id'              => (int) $user['id'],
            'name'            => $user['name'],
            'email'           => $user['email'],
            'role'            => $user['role'],
            'department_id'   => $user['department_id'] ? (int) $user['department_id'] : null,
            'department_name' => $user['department_name'] ?? 'General',
        ]);

        return true;
    }

    public static function check(): bool
    {
        return Session::has(self::SESSION_USER_KEY);
    }

    public static function user(): ?array
    {
        return Session::get(self::SESSION_USER_KEY);
    }

    public static function id(): ?int
    {
        $user = self::user();
        return $user['id'] ?? null;
    }

    public static function role(): ?string
    {
        $user = self::user();
        return $user['role'] ?? null;
    }

    public static function isAdmin(): bool
    {
        return self::role() === 'Admin';
    }

    public static function isManager(): bool
    {
        return self::role() === 'Manager';
    }

    public static function isEmployee(): bool
    {
        return self::role() === 'Employee';
    }

    public static function hasRole(string|array $roles): bool
    {
        if (!self::check()) {
            return false;
        }

        $roles = (array) $roles;
        return in_array(self::role(), $roles, true);
    }

    public static function logout(): void
    {
        Session::destroy();
    }

    public static function requireLogin(string $loginUrl = '/login.php'): void
    {
        if (!self::check()) {
            Session::set('_intended_url', $_SERVER['REQUEST_URI'] ?? '/');
            Session::setFlash('error', 'Please log in to continue.');
            header("Location: {$loginUrl}");
            exit;
        }
    }

    public static function requireRole(string|array $roles): void
    {
        self::requireLogin();

        $allowedRoles = (array) $roles;
        if (!in_array(self::role(), $allowedRoles, true)) {
            Session::setFlash('error', 'You do not have permission to view that page.');
            $redirectUrl = self::dashboardUrl();
            header("Location: {$redirectUrl}");
            exit;
        }
    }

    public static function redirectIfAuthenticated(): void
    {
        if (self::check()) {
            $redirectUrl = self::dashboardUrl();
            header("Location: {$redirectUrl}");
            exit;
        }
    }

    public static function dashboardUrl(): string
    {
        return match (self::role()) {
            'Admin'    => '/admin/dashboard.php',
            'Manager'  => '/manager/dashboard.php',
            'Employee' => '/employee/dashboard.php',
            default    => '/login.php',
        };
    }
}
