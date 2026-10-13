<?php

declare(strict_types=1);

namespace App\Core;

use App\Models\User;

/**
 * Class Auth
 * Authentication and Role-Based Access Control (RBAC) service.
 */
class Auth
{
    private const SESSION_USER_KEY = '_authenticated_user';

    /**
     * Attempt login with email and plaintext password.
     *
     * @param string $email
     * @param string $password
     * @return bool
     */
    public static function attempt(string $email, string $password): bool
    {
        $user = User::findByEmail($email);

        if (!$user) {
            return false;
        }

        // Verify password using cryptographically secure password_verify
        if (!password_verify($password, $user['password'])) {
            return false;
        }

        // Check if password hash needs upgrade
        if (password_needs_rehash($user['password'], PASSWORD_BCRYPT, ['cost' => 12])) {
            $newHash = password_hash($password, PASSWORD_BCRYPT, ['cost' => 12]);
            $db = Database::getInstance();
            $stmt = $db->prepare("UPDATE users SET password = :password WHERE id = :id");
            $stmt->execute(['password' => $newHash, 'id' => $user['id']]);
        }

        // Prevent session fixation by regenerating session ID upon login
        Session::regenerate();

        // Store sanitized session payload (never store password in session)
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

    /**
     * Check if a user is currently authenticated.
     */
    public static function check(): bool
    {
        return Session::has(self::SESSION_USER_KEY);
    }

    /**
     * Retrieve the currently authenticated user's session data.
     */
    public static function user(): ?array
    {
        return Session::get(self::SESSION_USER_KEY);
    }

    /**
     * Retrieve authenticated user ID.
     */
    public static function id(): ?int
    {
        $user = self::user();
        return $user['id'] ?? null;
    }

    /**
     * Retrieve authenticated user role.
     */
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

    /**
     * Check if current user has any of the specified roles.
     *
     * @param string|array<string> $roles
     * @return bool
     */
    public static function hasRole(string|array $roles): bool
    {
        if (!self::check()) {
            return false;
        }

        $roles = (array) $roles;
        return in_array(self::role(), $roles, true);
    }

    /**
     * Log the current user out and destroy the session.
     */
    public static function logout(): void
    {
        Session::destroy();
    }

    /**
     * Route access control: Require active authentication.
     *
     * @param string $loginUrl URL to redirect unauthenticated users
     */
    public static function requireLogin(string $loginUrl = '/login.php'): void
    {
        if (!self::check()) {
            Session::set('_intended_url', $_SERVER['REQUEST_URI'] ?? '/');
            Session::setFlash('error', 'You must log in to access this page.');
            header("Location: {$loginUrl}");
            exit;
        }
    }

    /**
     * Route access control: Restrict route to specific role(s).
     *
     * @param string|array<string> $roles Single role or array of allowed roles
     */
    public static function requireRole(string|array $roles): void
    {
        self::requireLogin();

        $allowedRoles = (array) $roles;
        if (!in_array(self::role(), $allowedRoles, true)) {
            Session::setFlash('error', 'Access Denied: You do not have permission to view that resource.');
            $redirectUrl = self::dashboardUrl();
            header("Location: {$redirectUrl}");
            exit;
        }
    }

    /**
     * Redirect authenticated users away from guest pages (e.g., login).
     */
    public static function redirectIfAuthenticated(): void
    {
        if (self::check()) {
            $redirectUrl = self::dashboardUrl();
            header("Location: {$redirectUrl}");
            exit;
        }
    }

    /**
     * Resolve default dashboard URL based on current role.
     */
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
