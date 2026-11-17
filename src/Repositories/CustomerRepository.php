<?php

declare(strict_types=1);

namespace App\Repositories;

use PDO;

final class CustomerRepository
{
    public function __construct(
        private PDO $pdo
    ){}

    public function findAll(): array {
        $sql = "
                SELECT
                    id,
                    customer_code,
                    name,
                    phone,
                    email,
                    address,
                    credit_limit,
                    opening_balance,
                    status,
                    created_at,
                    updated_at
                FROM customers
                ORDER BY id DESC
        ";

        return $this->pdo->query($sql)->fetchAll();
    }

    public function findById(int $id): ?array {
        $sql = "
                SELECT
                    id,
                    customer_code,
                    name,
                    phone,
                    email,
                    address,
                    credit_limit,
                    opening_balance,
                    status,
                    created_at,
                    updated_at
                FROM customers
                WHERE id = :id
        ";

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute(['id' => $id, ]);
        $customer = $stmt->fetch();

        return $customer === false ? null : $customer;
    }

    public function create(array $data): int {
        $sql = "
                INSERT INTO customers (
                    customer_code,
                    name,
                    phone,
                    email,
                    address,
                    credit_limit,
                    opening_balance,
                    status
                ) VALUES (
                    :customer_code,
                    :name,
                    :phone,
                    :email,
                    :address,
                    :credit_limit,
                    :opening_balance,
                    :status
                )
        ";

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($data);

        return (int)$this->pdo->lastInsertId();
    }

    public function update(int $id, array $data): void{
        $sql = "
                UPDATE customers
                SET
                    customer_code = :customer_code,
                    name = :name,
                    phone = :phone,
                    email = :email,
                    address = :address,
                    credit_limit = :credit_limit,
                    opening_balance = :opening_balance,
                    status = :status
                WHERE id = :id
        ";

        $data['id'] = $id;
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($data);

    }

    public function hasOrders(int $customerId): bool {
        $sql = "
                SELECT 1
                FROM orders
                WHERE customer_id = :customer_id
                LIMIT 1
        ";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute(['customer_id' => $customerId]);
        return $stmt->fetchColumn() !== false;
    }

    public function delete(int $id): void {
        $sql = "
                DELETE FROM customers
                WHERE id = :id
            ";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute(['id' => $id]);

    }
}