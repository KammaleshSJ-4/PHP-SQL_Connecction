<?php

declare (strict_types=1);

use App\Database\Connection;

require_once dirname(__DIR__) . '/config/bootstrap.php';

header('Content-Type: application/json');

try {
    $pdo = Connection::create();
    $statement = $pdo->query('SELECT DATABASE() AS database_name');
    $result = $statement->fetch();
    http_response_code(200);
    echo json_encode(['status'=> 'success',
    'message' => 'Database connection successful.',
    'database' => $result['database_name']?? null,
    ],
    JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR
    );  
}
CATCH (PDOException $e) {
    http_response_code(500);
    echo json_encode(['status'=> 'error',
    'message' => 'Database connection failed: ' . $e->getMessage(),
    ],
    JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR
    );
}
catch (Throwable $exception) {
    error_log((string)$exception);

    http_response_code(500);

    echo json_encode(['status'=> 'error',
    'message' => 'An unexpected error occurred: ' . $exception->getMessage(),
    ],
    JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR
    );
}