<?php

declare(strict_types=1);

namespace Snaply\Api;

use Snaply\Api\Handlers\ProjectHandler;
use Snaply\Api\Handlers\PageHandler;
use Snaply\Api\Handlers\SnapshotHandler;
use Snaply\Api\Handlers\CommentHandler;
use Throwable;

/**
 * Simple API router for Snaply endpoints.
 *
 * Routes requests to appropriate handlers based on URI pattern matching.
 * Supports nested resource patterns like /projects/{id}/pages.
 */
class Router
{
    private ProjectHandler $projectHandler;
    private PageHandler $pageHandler;
    private SnapshotHandler $snapshotHandler;
    private CommentHandler $commentHandler;

    public function __construct(
        ?ProjectHandler $projectHandler = null,
        ?PageHandler $pageHandler = null,
        ?SnapshotHandler $snapshotHandler = null,
        ?CommentHandler $commentHandler = null
    ) {
        $this->projectHandler = $projectHandler ?? new ProjectHandler();
        $this->pageHandler = $pageHandler ?? new PageHandler();
        $this->snapshotHandler = $snapshotHandler ?? new SnapshotHandler();
        $this->commentHandler = $commentHandler ?? new CommentHandler();
    }

    /**
     * Dispatch the current request.
     *
     * Parses the request URI and routes to the appropriate handler.
     */
    public function dispatch(): void
    {
        try {
            $method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
            $uri = $this->parseUri();

            $this->route($method, $uri);

        } catch (ApiException $e) {
            ApiResponse::fromException($e);
        } catch (Throwable $e) {
            // Log the error in production
            error_log('API Error: ' . $e->getMessage() . "\n" . $e->getTraceAsString());

            ApiResponse::error(
                'An internal error occurred',
                500,
                ApiException::CODE_INTERNAL_ERROR
            );
        }
    }

    /**
     * Parse the request URI.
     *
     * Removes /api prefix and query string.
     *
     * @return string Clean URI path
     */
    private function parseUri(): string
    {
        $uri = $_SERVER['REQUEST_URI'] ?? '/';

        // Remove query string
        $queryPos = strpos($uri, '?');
        if ($queryPos !== false) {
            $uri = substr($uri, 0, $queryPos);
        }

        // Remove /api prefix if present
        if (str_starts_with($uri, '/api')) {
            $uri = substr($uri, 4);
        }

        // Ensure leading slash
        if (!str_starts_with($uri, '/')) {
            $uri = '/' . $uri;
        }

        // Remove trailing slash (except for root)
        if ($uri !== '/' && str_ends_with($uri, '/')) {
            $uri = rtrim($uri, '/');
        }

        return $uri;
    }

    /**
     * Route the request to the appropriate handler.
     *
     * @param string $method HTTP method
     * @param string $uri Parsed URI path
     * @throws ApiException If route not found
     */
    private function route(string $method, string $uri): void
    {
        // Split URI into segments
        $segments = array_values(array_filter(explode('/', $uri)));
        $count = count($segments);

        // Route based on patterns
        // /projects
        if ($count === 1 && $segments[0] === 'projects') {
            $this->projectHandler->handle($method, null);
            return;
        }

        // /projects/{id}
        if ($count === 2 && $segments[0] === 'projects' && is_numeric($segments[1])) {
            $this->projectHandler->handle($method, (int) $segments[1]);
            return;
        }

        // /projects/{id}/restore
        if ($count === 3 && $segments[0] === 'projects' && is_numeric($segments[1]) && $segments[2] === 'restore') {
            $this->projectHandler->handle($method, (int) $segments[1], 'restore');
            return;
        }

        // /projects/{id}/pages
        if ($count === 3 && $segments[0] === 'projects' && is_numeric($segments[1]) && $segments[2] === 'pages') {
            $this->pageHandler->handleProjectScoped($method, (int) $segments[1]);
            return;
        }

        // /pages/{id}
        if ($count === 2 && $segments[0] === 'pages' && is_numeric($segments[1])) {
            $this->pageHandler->handle($method, (int) $segments[1]);
            return;
        }

        // /pages/{id}/restore
        if ($count === 3 && $segments[0] === 'pages' && is_numeric($segments[1]) && $segments[2] === 'restore') {
            $this->pageHandler->handle($method, (int) $segments[1], 'restore');
            return;
        }

        // /pages/{id}/snapshots
        if ($count === 3 && $segments[0] === 'pages' && is_numeric($segments[1]) && $segments[2] === 'snapshots') {
            $this->snapshotHandler->handlePageScoped($method, (int) $segments[1]);
            return;
        }

        // /snapshots/{id}
        if ($count === 2 && $segments[0] === 'snapshots' && is_numeric($segments[1])) {
            $this->snapshotHandler->handle($method, (int) $segments[1]);
            return;
        }

        // /snapshots/{id}/comments
        if ($count === 3 && $segments[0] === 'snapshots' && is_numeric($segments[1]) && $segments[2] === 'comments') {
            $this->commentHandler->handleSnapshotScoped($method, (int) $segments[1]);
            return;
        }

        // /comments/{id}
        if ($count === 2 && $segments[0] === 'comments' && is_numeric($segments[1])) {
            $this->commentHandler->handle($method, (int) $segments[1]);
            return;
        }

        // No matching route
        throw ApiException::notFound('Endpoint', "{$method} {$uri}");
    }

    /**
     * Create router and dispatch request (convenience method).
     */
    public static function run(): void
    {
        $router = new self();
        $router->dispatch();
    }
}
