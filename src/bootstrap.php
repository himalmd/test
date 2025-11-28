<?php

/**
 * Snaply Application Bootstrap
 *
 * This file initializes the application by setting up autoloading
 * and configuring the database connection.
 */

declare(strict_types=1);

// Load Composer autoloader if available
$autoloadPaths = [
    __DIR__ . '/../vendor/autoload.php',
    __DIR__ . '/../../vendor/autoload.php',
];

$autoloaded = false;
foreach ($autoloadPaths as $autoloadPath) {
    if (file_exists($autoloadPath)) {
        require_once $autoloadPath;
        $autoloaded = true;
        break;
    }
}

// Fallback: Simple PSR-4 autoloader if Composer is not available
if (!$autoloaded) {
    spl_autoload_register(function (string $class): void {
        $prefix = 'Snaply\\';
        $baseDir = __DIR__ . '/';

        $len = strlen($prefix);
        if (strncmp($prefix, $class, $len) !== 0) {
            return;
        }

        $relativeClass = substr($class, $len);
        $file = $baseDir . str_replace('\\', '/', $relativeClass) . '.php';

        if (file_exists($file)) {
            require $file;
        }
    });
}

// Load environment configuration if available
$envFile = __DIR__ . '/../.env';
if (file_exists($envFile)) {
    $lines = file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        if (str_starts_with(trim($line), '#')) {
            continue;
        }
        if (str_contains($line, '=')) {
            putenv(trim($line));
        }
    }
}

// Configure database connection from environment variables
$dbHost = getenv('DB_HOST') ?: 'localhost';
$dbName = getenv('DB_NAME') ?: 'snaply';
$dbUser = getenv('DB_USER') ?: 'root';
$dbPass = getenv('DB_PASS') ?: '';

\Snaply\Config\Database::configure($dbHost, $dbName, $dbUser, $dbPass);
