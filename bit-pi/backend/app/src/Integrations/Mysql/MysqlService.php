<?php

namespace BitApps\Pi\src\Integrations\Mysql;

// Prevent direct script access
if (!defined('ABSPATH')) {
    exit;
}

use BitApps\Pi\Helpers\Utility;
use BitApps\Pi\src\Authorization\AuthorizationFactory;
use BitApps\Pi\src\Authorization\AuthorizationType;
use InvalidArgumentException;
use PDO;
use RuntimeException;

/**
 * Runs SQL against a user-configured MySQL server over a direct PDO connection.
 *
 * All values are bound with prepared statements; table/column identifiers are
 * escaped via backtick quoting since they cannot be parameter-bound.
 */
final class MysqlService
{
    private const DEFAULT_CONNECT_TIMEOUT = 10;

    private PDO $pdo;

    public function __construct(array $config)
    {
        $host = $config['host'] ?? '';
        $port = (int) ($config['port'] ?? 3306);
        $database = $config['database'] ?? '';

        if (empty($host) || empty($database)) {
            throw new InvalidArgumentException('MySQL host and database are required.');
        }

        $charset = preg_replace('/[^A-Za-z0-9_]/', '', (string) ($config['charset'] ?? 'utf8mb4')) ?: 'utf8mb4';

        $dsn = \sprintf('mysql:host=%s;port=%d;dbname=%s;charset=%s', $host, $port, $database, $charset);

        $this->pdo = new PDO(
            $dsn,
            $config['username'] ?? '',
            $config['password'] ?? '',
            $this->buildPdoOptions($config)
        );
    }

    /**
     * Build a service from a stored connection id (resolves + decrypts credentials).
     *
     * @param mixed $connectionId
     */
    public static function fromConnection($connectionId): self
    {
        $config = AuthorizationFactory::getAuthorizationHandler(
            AuthorizationType::CUSTOM,
            $connectionId,
            'mysql'
        )->getAccessToken();

        if (\is_array($config) && isset($config['error'])) {
            throw new RuntimeException(esc_html($config['message'] ?? 'Failed to resolve MySQL connection.'));
        }

        return new self((array) $config);
    }

    public function executeQuery(string $query, array $params): array
    {
        if (trim($query) === '') {
            throw new InvalidArgumentException('Query cannot be empty.');
        }

        $statement = $this->pdo->prepare($query);
        $statement->execute(array_values($params));

        $isRead = preg_match('/^\s*(SELECT|SHOW|DESCRIBE|EXPLAIN|WITH)\b/i', $query) === 1;
        $response = $isRead ? $statement->fetchAll() : ['affected_rows' => $statement->rowCount()];

        return Utility::formatResponseData(200, ['query' => $query], $response);
    }

    public function insertRow(string $table, array $columns): array
    {
        if ($table === '' || $columns === []) {
            throw new InvalidArgumentException('Table and at least one column are required.');
        }

        $names = array_keys($columns);
        $columnSql = implode(', ', array_map([$this, 'quoteIdentifier'], $names));
        $placeholders = implode(', ', array_fill(0, \count($names), '?'));

        $sql = 'INSERT INTO ' . $this->quoteIdentifier($table) . " ({$columnSql}) VALUES ({$placeholders})";
        $statement = $this->pdo->prepare($sql);
        $statement->execute(array_values($columns));

        return Utility::formatResponseData(
            200,
            ['table'     => $table],
            ['insert_id' => $this->pdo->lastInsertId(), 'affected_rows' => $statement->rowCount()]
        );
    }

    /**
     * Update rows in a table matching the given column value.
     *
     * @param mixed $matchValue
     */
    public function updateRow(string $table, array $columns, string $matchColumn, $matchValue): array
    {
        if ($table === '' || $columns === [] || $matchColumn === '') {
            throw new InvalidArgumentException('Table, columns and a match column are required.');
        }

        $setSql = implode(', ', array_map(fn ($name) => $this->quoteIdentifier($name) . ' = ?', array_keys($columns)));
        $sql = 'UPDATE ' . $this->quoteIdentifier($table) . " SET {$setSql} WHERE " . $this->quoteIdentifier($matchColumn) . ' = ?';

        $statement = $this->pdo->prepare($sql);
        $statement->execute([...array_values($columns), $matchValue]);

        return Utility::formatResponseData(200, ['table' => $table], ['affected_rows' => $statement->rowCount()]);
    }

    public function selectRows(string $table, string $where, array $whereParams, $limit): array
    {
        if ($table === '') {
            throw new InvalidArgumentException('Table is required.');
        }

        $sql = 'SELECT * FROM ' . $this->quoteIdentifier($table);

        if (trim($where) !== '') {
            $sql .= ' WHERE ' . $where;
        }

        if (is_numeric($limit)) {
            $sql .= ' LIMIT ' . (int) $limit;
        }

        $statement = $this->pdo->prepare($sql);
        $statement->execute(array_values($whereParams));

        return Utility::formatResponseData(200, ['table' => $table], $statement->fetchAll());
    }

    /**
     * Delete rows from a table matching the given column value.
     *
     * @param mixed $matchValue
     */
    public function deleteRows(string $table, string $matchColumn, $matchValue): array
    {
        if ($table === '' || $matchColumn === '') {
            throw new InvalidArgumentException('Table and a match column are required.');
        }

        $sql = 'DELETE FROM ' . $this->quoteIdentifier($table) . ' WHERE ' . $this->quoteIdentifier($matchColumn) . ' = ?';
        $statement = $this->pdo->prepare($sql);
        $statement->execute([$matchValue]);

        return Utility::formatResponseData(200, ['table' => $table], ['affected_rows' => $statement->rowCount()]);
    }

    /**
     * List tables as label/value options for the table dropdown.
     */
    public function listTables(): array
    {
        $tables = $this->pdo->query('SHOW TABLES')->fetchAll(PDO::FETCH_COLUMN);

        return array_map(fn ($table) => ['label' => $table, 'value' => $table], $tables);
    }

    /**
     * List a table's columns as label/value options for column dropdowns.
     */
    public function listColumns(string $table): array
    {
        $statement = $this->pdo->prepare(
            'SELECT COLUMN_NAME FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? ORDER BY ORDINAL_POSITION'
        );
        $statement->execute([$table]);
        $columns = $statement->fetchAll(PDO::FETCH_COLUMN);

        return array_map(fn ($column) => ['label' => $column, 'value' => $column], $columns);
    }

    /**
     * Base PDO options plus the optional advanced settings: connect timeout and
     * SSL file paths (CA / client certificate / client key). SSL is enabled
     * whenever any of those paths is provided.
     */
    private function buildPdoOptions(array $config): array
    {
        $options = [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
            PDO::ATTR_TIMEOUT            => is_numeric($config['connectTimeout'] ?? null)
                ? (int) $config['connectTimeout']
                : self::DEFAULT_CONNECT_TIMEOUT,
        ];

        foreach (
            [
                PDO::MYSQL_ATTR_SSL_CA   => 'sslCa',
                PDO::MYSQL_ATTR_SSL_KEY  => 'sslKey',
                PDO::MYSQL_ATTR_SSL_CERT => 'sslCert',
            ] as $attribute => $key
        ) {
            $path = trim((string) ($config[$key] ?? ''));
            if ($path !== '') {
                $options[$attribute] = $path;
            }
        }

        return $options;
    }

    private function quoteIdentifier(string $identifier): string
    {
        return '`' . str_replace('`', '``', $identifier) . '`';
    }
}
