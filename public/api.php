<?php

/**
 * Snaply API Entry Point
 *
 * All API requests are routed through this file.
 *
 * Example URLs:
 *   /api/projects
 *   /api/projects/1
 *   /api/projects/1/pages
 *   /api/pages/1/snapshots
 *   /api/snapshots/1/comments
 */

declare(strict_types=1);

// Error reporting (disable in production)
error_reporting(E_ALL);
ini_set('display_errors', '0');
ini_set('log_errors', '1');

// CORS headers (adjust for production)
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, PATCH, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With');
header('Access-Control-Max-Age: 86400');

// Handle preflight requests
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(204);
    exit;
}

// Load bootstrap (autoloader, configuration)
require_once dirname(__DIR__) . '/src/bootstrap.php';

// Dispatch the request
use Snaply\Api\Router;

Router::run();
