<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Http\JsonResponse;
use App\Repositories\ProductRepository;
use JsonException;
use PDOException;

final class ProductController
{
    public function __construct(
        private ProductRepository $products
    ){

    }
    public function collection(string $method): void
    {
        if ($method === 'GET') {
            JsonResponse::send([
                'data' => $this->products->findAll(),
            ]);
        }

        if ($method === 'POST') {
            $input = $this->readJsonBody();
            $data = $this->normaliseProduct($input);

            try {
                $id = $this->products->createProduct($data);
            } catch (PDOException $exception) {
                $this->handleWriteException($exception);
            }

            $product = $this->products->findById($id);

            JsonResponse::send([
                    'id' => $id,
                ], 201);


        }
        header('Allow: GET, POST');

        JsonResponse::send([
            'error' => 'Method not allowed',
        ], 405);
    }

    public function item(string $method, int $id): void
    {
        if ($id < 1) {
            JsonResponse::send([
                'error' => 'Product Id must be a positive integer',
            ], 400);
        }

        $product = $this->products->findById($id);

        if ($product === null || $product === []) {
            JsonResponse::send([
                'error' => 'Product not found',
            ],404);
        }
        if ($method === 'GET') {
            JsonResponse::send($product);
        }

        if ($method === 'PUT' || $method === 'PATCH'){
            $input = $this->readJsonBody();
            $data = $this->normaliseProduct($input , $method === 'PATCH' ? $product: null);

            try {
                $this->products->updateProduct($id,$data);
            } catch(PDOException $exception){
                $this->handleWriteException($exception);
            }

            JsonResponse::send($this->products->findById($id) ?? ['error' => 'Product not found']
            );
        }

        header('Allow: GET, PUT, PATCH');

        JsonResponse::send(['error'=>'Method not found'], 405);
    }

    public function readJsonBody(): array
    {
        $input = file_get_contents('php://input');

        if ($input === false || trim($input) === '') {
            JsonResponse::send([
                'error' => 'Request body is empty',
            ], 400);
        }

        try {
            $data = json_decode($input, true, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException $exception) {
            JsonResponse::send ([
                'error' => 'Invalid JSON in body request',
            ],400);
        }
        if (!is_array($data)) {
            JsonResponse::send([
                'error' => 'Response body must be a JSON object',
            ], 400);
        }

        return $data;
    }

    private function normaliseProduct(
        array $input,
        ?array $existing = null
    ): array {
        $allowedFields = [
            'sku',
            'name',
            'description',
            'unit',
            'cost_price',
            'selling_price',
            'stock_quantity',
            'reorder_level',
            'status',
        ];

        $unknownFields = array_diff(
            array_keys($input),
            $allowedFields
        );

        if ($unknownFields !== []) {
            JsonResponse::send([
                'error' => 'Unknown product fields',
                'fields' => array_values($unknownFields),
            ], 422);
        }

        // PATCH retains existing values for fields not supplied.
        if ($existing !== null) {
            $existingData = array_intersect_key(
                $existing,
                array_flip($allowedFields)
            );

            $input = array_replace($existingData, $input);
        }

        $errors = [];

        $sku = $input['sku'] ?? null;
        $name = $input['name'] ?? null;
        $unit = $input['unit'] ?? 'pcs';
        $description = $input['description'] ?? null;
        $status = $input['status'] ?? 'active';

        if (
            !is_string($sku) ||
            trim($sku) === '' ||
            strlen(trim($sku)) > 50
        ) {
            $errors['sku'] =
                'A SKU of 1-50 characters is required';
        }

        if (
            !is_string($name) ||
            trim($name) === '' ||
            strlen(trim($name)) > 200
        ) {
            $errors['name'] =
                'A product name of 1-200 characters is required';
        }

        if (
            !is_string($unit) ||
            trim($unit) === '' ||
            strlen(trim($unit)) > 20
        ) {
            $errors['unit'] =
                'A unit of 1-20 characters is required';
        }

        if (
            $description !== null &&
            !is_string($description)
        ) {
            $errors['description'] =
                'Description must be a string or null';
        }

        if (
            !is_string($status) ||
            !in_array($status, ['active', 'inactive'], true)
        ) {
            $errors['status'] =
                'Status must be active or inactive';
        }

        $data = [
            'sku' => is_string($sku) ? trim($sku) : '',
            'name' => is_string($name) ? trim($name) : '',
            'unit' => is_string($unit) ? trim($unit) : 'pcs',
            'description' => is_string($description)
                ? $description
                : null,
            'status' => is_string($status) ? $status : 'active',
        ];

        // DECIMAL(12,2): maximum 10 integer digits and 2 decimals.
        foreach ([
            'cost_price' => [
                'default' => '0.00',
                'max' => 9999999999.99,
                'scale' => 2,
            ],
            'selling_price' => [
                'default' => '0.00',
                'max' => 9999999999.99,
                'scale' => 2,
            ],
            // DECIMAL(12,3): maximum 9 integer digits and 3 decimals.
            'stock_quantity' => [
                'default' => '0.000',
                'max' => 999999999.999,
                'scale' => 3,
            ],
            'reorder_level' => [
                'default' => '0.000',
                'max' => 999999999.999,
                'scale' => 3,
            ],
        ] as $field => $rules) {
            $value = $input[$field] ?? $rules['default'];

            if (
                (!is_string($value) && !is_int($value))
                || !is_numeric($value)
                || !is_finite((float) $value)
                || (float) $value < 0
                || (float) $value > $rules['max']
                || preg_match(
                    '/^\d+(\.\d{1,' . $rules['scale'] . '})?$/',
                    (string) $value
                ) !== 1
            ) {
                $errors[$field] =
                    'Must be a nonnegative number with at most '
                    . $rules['scale'] . ' decimal places';
                $data[$field] = $rules['default'];
                continue;
            }

            // Keep decimal values as strings for MySQL DECIMAL.
            $data[$field] = (string) $value;
        }

        if ($errors !== []) {
            JsonResponse::send([
                'error' => 'Validation failed',
                'fields' => $errors,
            ], 422);
        }

        return $data;
    }

    private function handleWriteException(
        PDOException $exception
    ): void {
        if ($exception->getCode() === '23000') {
            JsonResponse::send([
                'error' =>
                    'A database constraint was violated. '
                    . 'Check whether the SKU already exists.',
            ], 409);
        }

        // Unexpected errors should be logged and handled centrally.
        throw $exception;
    }


}