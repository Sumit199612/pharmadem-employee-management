<?php

declare(strict_types=1);

namespace App\Core;

use App\Core\Exceptions\DatabaseException;
use PDO;
use PDOException;
use PDOStatement;
use Throwable;

class Database
{
    private static ?Database $instance = null;
    private ?PDO $pdo = null;

    private function __construct()
    {
        $this->connect();
    }

    private function __clone() {}

    public function __wakeup(): void
    {
        throw new DatabaseException("Cannot unserialize singleton instance.");
    }

    public static function getInstance(): self
    {
        if (self::$instance === null) {
            self::$instance = new self();
        }

        return self::$instance;
    }

    private function connect(): void
    {
        $configPath = dirname(__DIR__, 2) . '/config/database.php';

        if (!file_exists($configPath)) {
            throw new DatabaseException("Config file missing: {$configPath}");
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

        if (!isset($options[PDO::MYSQL_ATTR_INIT_COMMAND])) {
            $options[PDO::MYSQL_ATTR_INIT_COMMAND] = "SET NAMES '{$config['charset']}' COLLATE '{$config['collation']}'";
        }

        try {
            $this->pdo = new PDO($dsn, $config['username'], $config['password'], $options);
        } catch (PDOException $e) {
            error_log(sprintf(
                "Database connection error: %s (%s:%d)",
                $e->getMessage(),
                $e->getFile(),
                $e->getLine()
            ));

            $debug = Env::get('APP_DEBUG', false);
            $message = $debug ? $e->getMessage() : "Database connection failed.";

            throw new DatabaseException($message, (int) $e->getCode(), $e);
        }
    }

    public function getConnection(): PDO
    {
        return $this->pdo;
    }

    public function prepare(string $sql): PDOStatement
    {
        return $this->pdo->prepare($sql);
    }

    public function lastInsertId(?string $name = null): string
    {
        return (string) $this->pdo->lastInsertId($name);
    }

    public function beginTransaction(): bool
    {
        return $this->pdo->beginTransaction();
    }

    public function commit(): bool
    {
        return $this->pdo->commit();
    }

    public function rollBack(): bool
    {
        return $this->pdo->rollBack();
    }

    public function inTransaction(): bool
    {
        return $this->pdo->inTransaction();
    }

    /**
     * Run callback inside a transaction
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
