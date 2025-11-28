<?php

declare(strict_types=1);

namespace Snaply\Repositories;

use PDO;
use PDOStatement;
use Snaply\Config\Database;

/**
 * Abstract base repository providing common database operations.
 *
 * All entity repositories should extend this class to inherit
 * common functionality for database access and soft delete handling.
 */
abstract class BaseRepository
{
    protected PDO $db;

    public function __construct(?PDO $connection = null)
    {
        $this->db = $connection ?? Database::getConnection();
    }

    /**
     * Get the table name for this repository.
     *
     * @return string
     */
    abstract protected function getTableName(): string;

    /**
     * Check if this entity supports soft delete.
     *
     * @return bool
     */
    protected function supportsSoftDelete(): bool
    {
        return true;
    }

    /**
     * Build the WHERE clause condition for soft delete filtering.
     *
     * @param bool   $includeDeleted Whether to include soft-deleted records
     * @param string $alias          Optional table alias
     * @return string SQL condition (empty string if includeDeleted is true)
     */
    protected function softDeleteCondition(bool $includeDeleted, string $alias = ''): string
    {
        if ($includeDeleted || !$this->supportsSoftDelete()) {
            return '';
        }

        $prefix = $alias !== '' ? "{$alias}." : '';
        return "{$prefix}deleted_at IS NULL";
    }

    /**
     * Build WHERE clause with soft delete condition.
     *
     * @param array<string, mixed> $conditions    Existing conditions
     * @param bool                 $includeDeleted Whether to include soft-deleted records
     * @param string               $alias          Optional table alias
     * @return string Complete WHERE clause
     */
    protected function buildWhereClause(
        array $conditions,
        bool $includeDeleted = false,
        string $alias = ''
    ): string {
        $parts = [];

        foreach ($conditions as $column => $value) {
            $prefix = $alias !== '' ? "{$alias}." : '';
            if ($value === null) {
                $parts[] = "{$prefix}{$column} IS NULL";
            } else {
                $parts[] = "{$prefix}{$column} = :{$column}";
            }
        }

        $softDeleteCond = $this->softDeleteCondition($includeDeleted, $alias);
        if ($softDeleteCond !== '') {
            $parts[] = $softDeleteCond;
        }

        if (empty($parts)) {
            return '';
        }

        return 'WHERE ' . implode(' AND ', $parts);
    }

    /**
     * Execute a query and return the statement.
     *
     * @param string               $sql    SQL query
     * @param array<string, mixed> $params Query parameters
     * @return PDOStatement
     */
    protected function executeQuery(string $sql, array $params = []): PDOStatement
    {
        $stmt = $this->db->prepare($sql);

        foreach ($params as $key => $value) {
            $type = match (true) {
                is_int($value) => PDO::PARAM_INT,
                is_bool($value) => PDO::PARAM_BOOL,
                is_null($value) => PDO::PARAM_NULL,
                default => PDO::PARAM_STR,
            };
            $stmt->bindValue(":{$key}", $value, $type);
        }

        $stmt->execute();
        return $stmt;
    }

    /**
     * Get the last inserted ID.
     *
     * @return int
     */
    protected function lastInsertId(): int
    {
        return (int)$this->db->lastInsertId();
    }

    /**
     * Begin a database transaction.
     *
     * @return bool
     */
    public function beginTransaction(): bool
    {
        return $this->db->beginTransaction();
    }

    /**
     * Commit the current transaction.
     *
     * @return bool
     */
    public function commit(): bool
    {
        return $this->db->commit();
    }

    /**
     * Roll back the current transaction.
     *
     * @return bool
     */
    public function rollBack(): bool
    {
        return $this->db->rollBack();
    }

    /**
     * Check if a transaction is currently active.
     *
     * @return bool
     */
    public function inTransaction(): bool
    {
        return $this->db->inTransaction();
    }

    /**
     * Count all records in the table.
     *
     * @param bool $includeDeleted Whether to include soft-deleted records
     * @return int
     */
    public function count(bool $includeDeleted = false): int
    {
        $table = $this->getTableName();
        $where = $this->buildWhereClause([], $includeDeleted);

        $sql = "SELECT COUNT(*) FROM {$table} {$where}";
        $stmt = $this->db->query($sql);

        return (int)$stmt->fetchColumn();
    }

    /**
     * Check if a record exists by ID.
     *
     * @param int  $id             Record ID
     * @param bool $includeDeleted Whether to include soft-deleted records
     * @return bool
     */
    public function exists(int $id, bool $includeDeleted = false): bool
    {
        $table = $this->getTableName();
        $where = $this->buildWhereClause(['id' => $id], $includeDeleted);

        $sql = "SELECT 1 FROM {$table} {$where} LIMIT 1";
        $stmt = $this->executeQuery($sql, ['id' => $id]);

        return $stmt->fetchColumn() !== false;
    }

    /**
     * Soft delete a record by ID.
     *
     * @param int $id Record ID
     * @return bool True if record was deleted
     */
    public function delete(int $id): bool
    {
        if (!$this->supportsSoftDelete()) {
            return $this->hardDelete($id);
        }

        $table = $this->getTableName();
        $sql = "UPDATE {$table} SET deleted_at = NOW() WHERE id = :id AND deleted_at IS NULL";

        $stmt = $this->executeQuery($sql, ['id' => $id]);
        return $stmt->rowCount() > 0;
    }

    /**
     * Permanently delete a record by ID.
     *
     * @param int $id Record ID
     * @return bool True if record was deleted
     */
    public function hardDelete(int $id): bool
    {
        $table = $this->getTableName();
        $sql = "DELETE FROM {$table} WHERE id = :id";

        $stmt = $this->executeQuery($sql, ['id' => $id]);
        return $stmt->rowCount() > 0;
    }

    /**
     * Restore a soft-deleted record by ID.
     *
     * @param int $id Record ID
     * @return bool True if record was restored
     */
    public function restore(int $id): bool
    {
        if (!$this->supportsSoftDelete()) {
            return false;
        }

        $table = $this->getTableName();
        $sql = "UPDATE {$table} SET deleted_at = NULL WHERE id = :id AND deleted_at IS NOT NULL";

        $stmt = $this->executeQuery($sql, ['id' => $id]);
        return $stmt->rowCount() > 0;
    }
}
