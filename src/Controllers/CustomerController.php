<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Http\JsonResponse;
use App\Repositories\CustomerRepository;
use JsonException;
use PDOException;

final class CustomerController
{
    public function __construct(
        private CustomerRepository $customers
    ) {
    }

    public function collection(string $method): void
    {
        if ($method === 'GET') {
            JsonResponse::send([
                'data' => $this->customers->findAll(),
            ]);
        }

        if ($method === 'POST') {
            $input = $this->readJsonBody();
            $data = $this->normaliseCustomer($input);

            try {
                $id = $this->customers->create($data);
            } catch (PDOException $exception) {
                $this->handleWriteException($exception);
            }

            $customer = $this->customers->findById($id);

            JsonResponse::send(
                $customer ?? ['id' => $id],
                201
            );
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
                'error' => 'Customer ID must be a positive integer',
            ], 400);
        }

        $customer = $this->customers->findById($id);

        if ($customer === null) {
            JsonResponse::send([
                'error' => 'Customer not found',
            ], 404);
        }

        if ($method === 'GET') {
            JsonResponse::send($customer);
        }

        if ($method === 'PUT' || $method === 'PATCH') {
            $input = $this->readJsonBody();

            $data = $this->normaliseCustomer(
                $input,
                $method === 'PATCH' ? $customer : null
            );

            try {
                $this->customers->update($id, $data);
            } catch (PDOException $exception) {
                $this->handleWriteException($exception);
            }

            JsonResponse::send(
                $this->customers->findById($id) ?? [
                    'error' => 'Customer not found',
                ]
            );
        }

        if ($method === 'DELETE') {
            if ($this->customers->hasOrders($id)) {
                JsonResponse::send([
                    'error' => 'Customer has existing orders and cannot be deleted',
                    'suggestion' => 'Deactivate the customer instead',
                ], 409);
            }

            $this->customers->delete($id);

            JsonResponse::noContent();
        }

        header('Allow: GET, PUT, PATCH, DELETE');

        JsonResponse::send([
            'error' => 'Method not allowed',
        ], 405);
    }

    private function readJsonBody(): array
    {
        $body = file_get_contents('php://input');

        if ($body === false || trim($body) === '') {
            JsonResponse::send([
                'error' => 'Request body is required',
            ], 400);
        }

        try {
            $data = json_decode(
                $body,
                true,
                512,
                JSON_THROW_ON_ERROR
            );
        } catch (JsonException $exception) {
            JsonResponse::send([
                'error' => 'Invalid JSON body',
            ], 400);
        }

        if (!is_array($data)) {
            JsonResponse::send([
                'error' => 'JSON body must be an object',
            ], 400);
        }

        return $data;
    }

    private function normaliseCustomer(
        array $input,
        ?array $existing = null
    ): array {
        $allowedFields = [
            'customer_code',
            'name',
            'phone',
            'email',
            'address',
            'credit_limit',
            'opening_balance',
            'status',
        ];

        $unknownFields = array_diff(
            array_keys($input),
            $allowedFields
        );

        if ($unknownFields !== []) {
            JsonResponse::send([
                'error' => 'Unknown customer fields',
                'fields' => array_values($unknownFields),
            ], 422);
        }

        /*
         * When updating only selected fields,
         * retain all existing fields not provided by the client.
         */
        if ($existing !== null) {
            $existingData = array_intersect_key(
                $existing,
                array_flip($allowedFields)
            );

            $input = array_replace($existingData, $input);
        }

        $errors = [];

        $code = $input['customer_code'] ?? null;
        $name = $input['name'] ?? null;

        if (
            !is_string($code) ||
            trim($code) === '' ||
            strlen(trim($code)) > 30
        ) {
            $errors['customer_code'] =
                'A customer code of 1–30 characters is required';
        }

        if (
            !is_string($name) ||
            trim($name) === '' ||
            strlen(trim($name)) > 150
        ) {
            $errors['name'] =
                'A customer name of 1–150 characters is required';
        }

        $data = [
            'customer_code' => is_string($code)
                ? trim($code)
                : '',
            'name' => is_string($name)
                ? trim($name)
                : '',
        ];

        foreach ([
            'phone' => 30,
            'email' => 150,
            'address' => null,
        ] as $field => $maxLength) {
            $value = $input[$field] ?? null;

            if ($value !== null && !is_string($value)) {
                $errors[$field] = 'Must be a string or null';
                $data[$field] = null;
                continue;
            }

            $value = is_string($value) ? trim($value) : null;

            if ($value === '') {
                $value = null;
            }

            if (
                $value !== null &&
                $maxLength !== null &&
                strlen($value) > $maxLength
            ) {
                $errors[$field] =
                    "Must not exceed {$maxLength} characters";
            }

            if (
                $field === 'email' &&
                $value !== null &&
                filter_var($value, FILTER_VALIDATE_EMAIL) === false
            ) {
                $errors['email'] = 'Enter a valid email address';
            }

            $data[$field] = $value;
        }

        foreach ([
            'credit_limit' => $input['credit_limit'] ?? 0,
            'opening_balance' => $input['opening_balance'] ?? 0,
        ] as $field => $value) {
            if (
                !is_numeric($value) ||
                !is_finite((float) $value) ||
                (float) $value < 0 ||
                (float) $value > 9999999999.99
            ) {
                $errors[$field] =
                    'Must be a valid non-negative amount';
                $data[$field] = '0.00';
                continue;
            }

            // Keep the amount as a decimal string for MySQL DECIMAL.
            $data[$field] = (string) $value;
        }

        $status = $input['status'] ?? 'active';

        if (!in_array($status, ['active', 'inactive'], true)) {
            $errors['status'] = 'Must be active or inactive';
        }

        $data['status'] = $status;

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
                'error' => 'Customer code already exists or a database constraint was violated',
            ], 409);
        }

        throw $exception;
    }
}