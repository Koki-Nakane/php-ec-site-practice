<?php

declare(strict_types=1);

namespace App\Controller;

use App\Mapper\ProductMapper;
use App\Model\Product;
use DateTimeImmutable;
use Throwable;

final class ProductController
{
    public function __construct(
        private ProductMapper $products,
    ) {
    }

    public function adminList(?bool $onlyActive = null, int $limit = 50, int $offset = 0): array
    {
        try {
            return $this->products->listForAdmin($onlyActive, $limit, $offset);
        } catch (Throwable $e) {
            return [];
        }
    }

    public function editForm(int $id): ?Product
    {
        try {
            return $this->products->findIncludingDeleted($id);
        } catch (Throwable $e) {
            return null;
        }
    }

    public function update(int $id, array $data): bool
    {
        try {
            $product = $this->products->findIncludingDeleted($id);
            if ($product === null) {
                return false;
            }

            if (array_key_exists('name', $data)) {
                $product->rename((string) $data['name']);
            }

            if (array_key_exists('price', $data)) {
                $product->changePrice((float) $data['price']);
            }

            if (array_key_exists('stock', $data)) {
                $product->changeStock((int) $data['stock']);
            }

            if (array_key_exists('description', $data)) {
                $product->changeDescription((string) $data['description']);
            }

            if (array_key_exists('is_active', $data)) {
                $isActive = filter_var($data['is_active'], FILTER_VALIDATE_BOOL, FILTER_NULL_ON_FAILURE);
                if ($isActive === true) {
                    $product->activate();
                } elseif ($isActive === false) {
                    $product->deactivate();
                }
            }

            $this->products->save($product);

            return true;
        } catch (Throwable $e) {
            return false;
        }
    }

    public function delete(int $id, ?DateTimeImmutable $when = null): bool
    {
        try {
            $this->products->markDeleted($id, $when ?? new DateTimeImmutable());
            return true;
        } catch (Throwable $e) {
            return false;
        }
    }

    public function restore(int $id, ?DateTimeImmutable $when = null): bool
    {
        try {
            $this->products->restore($id, $when ?? new DateTimeImmutable());
            return true;
        } catch (Throwable $e) {
            return false;
        }
    }

    public function enable(int $id, ?DateTimeImmutable $when = null): bool
    {
        try {
            $this->products->enable($id, $when ?? new DateTimeImmutable());
            return true;
        } catch (Throwable $e) {
            return false;
        }
    }

    public function disable(int $id, ?DateTimeImmutable $when = null): bool
    {
        try {
            $this->products->disable($id, $when ?? new DateTimeImmutable());
            return true;
        } catch (Throwable $e) {
            return false;
        }
    }
}
