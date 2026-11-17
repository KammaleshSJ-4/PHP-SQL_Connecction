<?php

declare(strict_types=1);

$path = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH);

if (!is_string($path)) {
    http_response_code(400);
    exit;
}

if ($path === '/') {
    readfile(__DIR__ . '/index.html');
    exit;
}

$file = __DIR__ . $path;

if (is_file($file)) {
    return false;
}

require __DIR__ . '/index.php';