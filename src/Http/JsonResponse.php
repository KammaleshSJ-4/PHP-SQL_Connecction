<?php

declare(strict_types=1);

namespace App\Http;

final class JsonResponse
{
    public static function send(array $data, int $statusCode = 200): void {
        http_response_code($statusCode);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
        exit;
    }

    public static function noContent(): void {
        http_response_code(204);
        exit;
    }
}