<?php

declare(strict_types=1);

/**
 * PDO Singleton Database Connection
 * Adsbooster.net
 */

class Database
{
    private static ?Database $instance = null;
    private ?PDO $connection = null;

    private string $host = '127.0.0.1';
    private string $port = '3306';
    private string $dbname = 'adsbooster';
    private string $charset = 'latin1';
    private string $username = 'root';
    private string $password = '';

    private function __construct()
    {
        // Deployment-specific credentials: config/db.local.php returns an array
        // like ['host'=>..,'port'=>..,'dbname'=>..,'username'=>..,'password'=>..]
        // Environment variables (DB_HOST, DB_PORT, DB_NAME, DB_USER, DB_PASS)
        // are honoured too. Defaults above are the local Laragon setup.
        $localFile = __DIR__ . '/db.local.php';
        if (is_readable($localFile)) {
            $local = (static function () use ($localFile): array {
                $v = (static function () use ($localFile) {
                    return require $localFile;
                })();
                return is_array($v) ? $v : [];
            })();
            foreach (['host', 'port', 'dbname', 'username', 'password'] as $k) {
                if (isset($local[$k]) && is_string($local[$k]) && $local[$k] !== '') {
                    $this->{$k} = $local[$k];
                }
            }
        }
        $envMap = ['host' => 'DB_HOST', 'port' => 'DB_PORT', 'dbname' => 'DB_NAME', 'username' => 'DB_USER', 'password' => 'DB_PASS'];
        foreach ($envMap as $prop => $env) {
            $val = getenv($env);
            if (is_string($val) && $val !== '') {
                $this->{$prop} = $val;
            }
        }

        $dsn = "mysql:host={$this->host};port={$this->port};dbname={$this->dbname};charset={$this->charset}";

        $options = [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
            PDO::ATTR_TIMEOUT            => 8,
        ];

        try {
            $this->connection = new PDO($dsn, $this->username, $this->password, $options);
        } catch (PDOException $e) {
            error_log("Database connection failed: " . $e->getMessage());
            throw new \PDOException(
                'Database connection failed (db=' . $this->dbname . ', user=' . $this->username
                    . '): ' . $e->getMessage(),
                (int)$e->getCode(),
                $e
            );
        }
    }

    /**
     * Read-only view of the active credentials — used by the schema installer
     * to open a dedicated multi-statement connection to the same database.
     */
    public function credentials(): array
    {
        return [
            'host'     => $this->host,
            'port'     => $this->port,
            'dbname'   => $this->dbname,
            'charset'  => $this->charset,
            'username' => $this->username,
            'password' => $this->password,
        ];
    }

    public static function getInstance(): self
    {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    public function getConnection(): PDO
    {
        return $this->connection;
    }

    public function query(string $sql, array $params = []): PDOStatement
    {
        $stmt = $this->connection->prepare($sql);
        $stmt->execute($params);
        return $stmt;
    }

    public function prepare(string $sql): PDOStatement
    {
        return $this->connection->prepare($sql);
    }

    public function lastInsertId(): string
    {
        return $this->connection->lastInsertId();
    }

    public function fetchOne(string $sql, array $params = []): ?array
    {
        $stmt = $this->query($sql, $params);
        $result = $stmt->fetch();
        return $result ?: null;
    }

    public function fetch(string $sql, array $params = []): ?array
    {
        return $this->fetchOne($sql, $params);
    }

    public function fetchAll(string $sql, array $params = []): array
    {
        $stmt = $this->query($sql, $params);
        return $stmt->fetchAll();
    }

    public function insert(string $table, array $data): int
    {
        $columns = implode(', ', array_keys($data));
        $placeholders = implode(', ', array_fill(0, count($data), '?'));
        $sql = "INSERT INTO {$table} ({$columns}) VALUES ({$placeholders})";
        $this->query($sql, array_values($data));
        return (int) $this->connection->lastInsertId();
    }

    public function update(string $table, array $data, string $where, array $whereParams = []): int
    {
        $set = implode(', ', array_map(fn($col) => "{$col} = ?", array_keys($data)));
        $sql = "UPDATE {$table} SET {$set} WHERE {$where}";
        $stmt = $this->query($sql, array_merge(array_values($data), $whereParams));
        return $stmt->rowCount();
    }

    public function delete(string $table, string $where, array $params = []): int
    {
        $sql = "DELETE FROM {$table} WHERE {$where}";
        $stmt = $this->query($sql, $params);
        return $stmt->rowCount();
    }

    public function count(string $table, string $where = '1=1', array $params = []): int
    {
        $sql = "SELECT COUNT(*) as cnt FROM {$table} WHERE {$where}";
        $result = $this->fetchOne($sql, $params);
        return (int)($result['cnt'] ?? 0);
    }
}

function db(): Database
{
    return Database::getInstance();
}

function pdo(): PDO
{
    return Database::getInstance()->getConnection();
}

if (!class_exists('DB')) {
    class DB
    {
        public static function getConnection(): PDO
        {
            return Database::getInstance()->getConnection();
        }

        public static function getInstance(): Database
        {
            return Database::getInstance();
        }
    }
}
