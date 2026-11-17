<?php

declare(strict_types=1);

use App\Controllers\CustomerController;
use App\Controllers\ProductController;
use App\Database\Connection;
use App\Http\JsonResponse;
use App\Repositories\CustomerRepository;
use App\Repositories\ProductRepository;

require_once dirname(__DIR__) . '/config/bootstrap.php';

header('Content-Type: application/json; charset=utf-8');

$path = parse_url(
    $_SERVER['REQUEST_URI'] ?? '/',
    PHP_URL_PATH
);

$method = strtoupper(
    $_SERVER['REQUEST_METHOD'] ?? 'GET'
);

if (!is_string($path)) {
    JsonResponse::send([
        'error' => 'Invalid request path',
    ], 400);
}

$path = rtrim($path, '/') ?: '/';

try {
    if ($path === '/api/health') {
        $pdo = Connection::create();
        $pdo->query('SELECT 1');

        JsonResponse::send([
            'status' => 'success',
            'message' => 'ERP Billing API is running',
            'database' => 'connected',
            'endpoints' => [
                'customers' => '/api/customers',
                'products' => '/api/products',
            ],
        ]);
    }

    // Create one PDO connection for this request.
    $pdo = Connection::create();

    // Customer module
    $customerRepository = new CustomerRepository($pdo);
    $customerController = new CustomerController(
        $customerRepository
    );

    // Product module
    $productRepository = new ProductRepository($pdo);
    $productController = new ProductController(
        $productRepository
    );

    // Customer collection
    if ($path === '/api/customers') {
        $customerController->collection($method);
    }

    // Customer item
    if (
        preg_match(
            '#^/api/customers/([0-9]+)$#',
            $path,
            $matches
        ) === 1
    ) {
        $customerController->item(
            $method,
            (int) $matches[1]
        );
    }

    // Product collection
    if ($path === '/api/products') {
        $productController->collection($method);
    }

    // Product item
    if (
        preg_match(
            '#^/api/products/([0-9]+)$#',
            $path,
            $matches
        ) === 1
    ) {
        $productController->item(
            $method,
            (int) $matches[1]
        );
    }

    // No route matched.
    JsonResponse::send([
        'error' => 'Endpoint not found',
    ], 404);

} catch (Throwable $exception) {
    error_log((string) $exception);

    JsonResponse::send([
        'error' => 'Internal server error',
    ], 500);
}
