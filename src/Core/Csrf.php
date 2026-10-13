<?php

declare(strict_types=1);

namespace App\Core;

/**
 * Class Csrf
 * Cryptographically secure CSRF token generator and validator.
 */
class Csrf
{
    private const SESSION_KEY = '_csrf_token';

    /**
     * Generate or return existing CSRF token.
     */
    public static function getToken(): string
    {
        Session::start();
        $token = Session::get(self::SESSION_KEY);
        if (!$token) {
            $token = bin2hex(random_bytes(32));
            Session::set(self::SESSION_KEY, $token);
        }
        return $token;
    }

    /**
     * Validate submitted CSRF token using timing-attack-safe comparison.
     */
    public static function validate(?string $token): bool
    {
        Session::start();
        $stored = Session::get(self::SESSION_KEY);
        if (!$stored || !$token) {
            return false;
        }
        return hash_equals($stored, $token);
    }

    /**
     * Output a hidden HTML input field containing the CSRF token.
     */
    public static function field(): string
    {
        $token = htmlspecialchars(self::getToken(), ENT_QUOTES, 'UTF-8');
        return '<input type="hidden" name="csrf_token" value="' . $token . '">';
    }
}
