<?php

declare(strict_types=1);

namespace App\Repositories;

use PDO;

final class ProductRepository
{
    public function __construct(
        private PDO $pdo
    ){}

    public function findAll(): array {
        $sql = "
                SELECT
                    id,
                    sku,
                    name,
                    description,
                    unit,
                    cost_price,
                    selling_price,
                    stock_quantity,
                    reorder_level,
                    status,
                    created_at,
                    updated_at
                FROM products
                ORDER BY id DESC
                ";
        $stmt = $this->pdo->query($sql);
        return $stmt->fetchAll();
    }

    public function findById(int $id): array {
        $sql = "
                SELECT
                    id,
                    sku,
                    name,
                    description,
                    unit,
                    cost_price,
                    selling_price,
                    stock_quantity,
                    reorder_level,
                    status,
                    created_at,
                    updated_at
                FROM products
                WHERE id = :id
        ";

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute(['id' => $id, ]);
        $product = $stmt->fetch();

        return $product === false ? [] : $product;
    }

    public function createProduct(array $data): int {
        $sql = "
                INSERT INTO products (
                    sku,
                    name,
                    description,
                    unit,
                    cost_price,
                    selling_price,
                    stock_quantity,
                    reorder_level,
                    status
                ) VALUES (
                    :sku,
                    :name,
                    :description,
                    :unit,
                    :cost_price,
                    :selling_price,
                    :stock_quantity,
                    :reorder_level,
                    :status
                )
        ";

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($data);

        return (int)$this->pdo->lastInsertId();
    }

    public function updateProduct (int $id, array $data): void {
        $sql = "
                UPDATE products
                SET
                    sku = :sku,
                    name = :name,
                    description = :description,
                    unit = :unit,
                    cost_price = :cost_price,
                    selling_price = :selling_price,
                    stock_quantity = :stock_quantity,
                    reorder_level = :reorder_level,
                    status = :status
                WHERE id = :id
        ";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute(['id' => $id] + $data);

    }

    public function statusChange(int $id, string $status): void {
        $sql = "
                UPDATE products
                SET status = :status
                WHERE id = :id";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute(['id' => $id, 'status' => $status]);
    }

    public function checkStatus(int $id): string {
        $sql = "
                SELECT status
                FROM products
                WHERE id = :id";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute(['id' => $id]);
        $status = $stmt->fetchColumn();
        return $status === false ? '' : $status;
    }
}