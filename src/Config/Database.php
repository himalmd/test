<?php

declare(strict_types=1);

namespace Snaply\Config;

use PDO;
use PDOException;

/**
 * Database connection singleton for Snaply application.
 *
 * Provides a single PDO connection instance with proper configuration
 * for MySQL/MariaDB with utf8mb4 character set.
 */
class Database
{
    private static ?PDO $instance = null;

    private static string $host = 'localhost';
    private static string $dbname = 'snaply';
    private static string $username = 'root';
    private static string $password = '';
    private static string $charset = 'utf8mb4';

    /**
     * Prevent direct instantiation.
     */
    private function __construct()
    {
    }

    /**
     * Prevent cloning.
     */
    private function __clone()
    {
    }

    /**
     * Configure database connection parameters.
     *
     * Call this method before getConnection() to set custom connection details.
     *
     * @param string $host     Database host
     * @param string $dbname   Database name
     * @param string $username Database username
     * @param string $password Database password
     * @param string $charset  Character set (default: utf8mb4)
     */
    public static function configure(
        string $host,
        string $dbname,
        string $username,
        string $password,
        string $charset = 'utf8mb4'
    ): void {
        self::$host = $host;
        self::$dbname = $dbname;
        self::$username = $username;
        self::$password = $password;
        self::$charset = $charset;

        // Reset instance to apply new configuration
        self::$instance = null;
    }

    /**
     * Get the PDO database connection instance.
     *
     * Creates a new connection if one doesn't exist, otherwise returns
     * the existing connection.
     *
     * @return PDO The database connection
     * @throws PDOException If connection fails
     */
    public static function getConnection(): PDO
    {
        if (self::$instance === null) {
            $dsn = sprintf(
                'mysql:host=%s;dbname=%s;charset=%s',
                self::$host,
                self::$dbname,
                self::$charset
            );

            self::$instance = new PDO($dsn, self::$username, self::$password, [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false,
                PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci"
            ]);
        }

        return self::$instance;
    }

    /**
     * Close the database connection.
     *
     * Useful for long-running scripts or testing.
     */
    public static function close(): void
    {
        self::$instance = null;
    }
}
