<?php

declare(strict_types=1);

namespace App\Core;

use App\Core\Exceptions\DatabaseException;
use PDO;
use PDOException;
use PDOStatement;
use Throwable;

/**
 * Class Database
 * Enterprise-grade PDO Database Connection Manager implementing Singleton pattern.
 */
class Database
{
    private static ?Database $instance = null;
    private ?PDO $pdo = null;

    /**
     * Private constructor to enforce Singleton pattern.
     */
    private function __construct()
    {
        $this->connect();
    }

    /**
     * Prevent cloning of the instance.
     */
    private function __clone() {}

    /**
     * Prevent unserializing of the instance.
     *
     * @throws DatabaseException
     */
    public function __wakeup(): void
    {
        throw new DatabaseException("Cannot unserialize singleton instance.");
    }

    /**
     * Get the single instance of Database.
     *
     * @return Database
     */
    public static function getInstance(): self
    {
        if (self::$instance === null) {
            self::$instance = new self();
        }

        return self::$instance;
    }

    /**
     * Initialize PDO connection using configuration.
     *
     * @throws DatabaseException
     */
    private function connect(): void
    {
        $configPath = dirname(__DIR__, 2) . '/config/database.php';

        if (!file_exists($configPath)) {
            throw new DatabaseException("Database configuration file not found at: {$configPath}");
        }

        $config = require $configPath;

        $dsn = sprintf(
            '%s:host=%s;port=%d;dbname=%s;charset=%s',
            $config['driver'] ?? 'mysql',
            $config['host'] ?? '127.0.0.1',
            $config['port'] ?? 3306,
            $config['database'] ?? '',
            $config['charset'] ?? 'utf8mb4'
        );

        $options = $config['options'] ?? [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
        ];

        // Ensure UTF-8 collation init command
        if (!isset($options[PDO::MYSQL_ATTR_INIT_COMMAND])) {
            $options[PDO::MYSQL_ATTR_INIT_COMMAND] = "SET NAMES '{$config['charset']}' COLLATE '{$config['collation']}'";
        }

        try {
            $this->pdo = new PDO($dsn, $config['username'], $config['password'], $options);
        } catch (PDOException $e) {
            // Log real error internally with details, but do NOT expose DB credentials or host in public exception message
            error_log(sprintf(
                "[Database Connection Failure] %s in %s on line %d",
                $e->getMessage(),
                $e->getFile(),
                $e->getLine()
            ));

            $debugMode = Env::get('APP_DEBUG', false);
            $message = $debugMode 
                ? "Database connection error: " . $e->getMessage() 
                : "Unable to connect to the database. Please contact system administrator.";

            throw new DatabaseException($message, (int) $e->getCode(), $e);
        }
    }

    /**
     * Retrieve the underlying PDO connection.
     *
     * @return PDO
     */
    public function getConnection(): PDO
    {
        return $this->pdo;
    }

    /**
     * Shortcut to prepare a statement directly on PDO.
     *
     * @param string $sql
     * @return PDOStatement
     */
    public function prepare(string $sql): PDOStatement
    {
        return $this->pdo->prepare($sql);
    }

    /**
     * Retrieve the last inserted ID.
     *
     * @param string|null $name Name of sequence or null
     * @return string
     */
    public function lastInsertId(?string $name = null): string
    {
        return (string) $this->pdo->lastInsertId($name);
    }

    /**
     * Begin a transaction.
     *
     * @return bool
     */
    public function beginTransaction(): bool
    {
        return $this->pdo->beginTransaction();
    }

    /**
     * Commit a transaction.
     *
     * @return bool
     */
    public function commit(): bool
    {
        return $this->pdo->commit();
    }

    /**
     * Rollback a transaction.
     *
     * @return bool
     */
    public function rollBack(): bool
    {
        return $this->pdo->rollBack();
    }

    /**
     * Check if currently within a transaction.
     *
     * @return bool
     */
    public function inTransaction(): bool
    {
        return $this->pdo->inTransaction();
    }

    /**
     * Execute a callback inside an atomic transaction.
     *
     * @template T
     * @param callable(PDO): T $callback
     * @return T
     * @throws Throwable
     */
    public function transaction(callable $callback): mixed
    {
        $this->beginTransaction();

        try {
            $result = $callback($this->pdo);
            $this->commit();
            return $result;
        } catch (Throwable $e) {
            if ($this->inTransaction()) {
                $this->rollBack();
            }
            throw $e;
        }
    }
}
