<?php

declare(strict_types=1);

namespace App\Core;

/**
 * Class Session
 * Hardened session management with strict cookie flags, regeneration, and flash messages.
 */
class Session
{
    private static bool $started = false;

    /**
     * Start secure session if not already active.
     */
    public static function start(): void
    {
        if (self::$started || session_status() === PHP_SESSION_ACTIVE) {
            self::$started = true;
            return;
        }

        $isSecure = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ||
                    ($_SERVER['SERVER_PORT'] ?? 80) == 443;

        // Strict security cookie attributes
        session_set_cookie_params([
            'lifetime' => 0,              // Session cookie (until browser closes)
            'path'     => '/',
            'domain'   => '',
            'secure'   => $isSecure,      // Transmit only over HTTPS if available
            'httponly' => true,           // Prevent JavaScript access (XSS mitigation)
            'samesite' => 'Lax',          // Mitigate CSRF
        ]);

        ini_set('session.use_only_cookies', '1');
        ini_set('session.use_strict_mode', '1');

        session_start();
        self::$started = true;
    }

    public static function set(string $key, mixed $value): void
    {
        self::start();
        $_SESSION[$key] = $value;
    }

    public static function get(string $key, mixed $default = null): mixed
    {
        self::start();
        return $_SESSION[$key] ?? $default;
    }

    public static function has(string $key): bool
    {
        self::start();
        return isset($_SESSION[$key]);
    }

    public static function remove(string $key): void
    {
        self::start();
        unset($_SESSION[$key]);
    }

    /**
     * Regenerate session ID to prevent session fixation attacks.
     */
    public static function regenerate(): void
    {
        self::start();
        session_regenerate_id(true);
    }

    /**
     * Set a flash message that lasts for only one request.
     */
    public static function setFlash(string $type, string $message): void
    {
        self::start();
        $_SESSION['_flash'][$type] = $message;
    }

    /**
     * Retrieve and clear flash message.
     */
    public static function getFlash(string $type): ?string
    {
        self::start();
        if (isset($_SESSION['_flash'][$type])) {
            $msg = $_SESSION['_flash'][$type];
            unset($_SESSION['_flash'][$type]);
            return $msg;
        }
        return null;
    }

    /**
     * Destroy current session completely.
     */
    public static function destroy(): void
    {
        self::start();
        $_SESSION = [];

        if (ini_get('session.use_cookies')) {
            $params = session_get_cookie_params();
            setcookie(
                session_name(),
                '',
                time() - 42000,
                $params['path'],
                $params['domain'],
                $params['secure'],
                $params['httponly']
            );
        }

        session_destroy();
        self::$started = false;
    }
}
