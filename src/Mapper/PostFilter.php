<?php

declare(strict_types=1);

namespace App\Mapper;

final class PostFilter
{
    private const DEFAULT_PER_PAGE = 20;
    private const MAX_PER_PAGE = 50;

    private int $page;
    private int $perPage;
    private ?string $query;
    /** @var int[] */
    private array $categoryIds;
    /** @var string[] */
    private array $categorySlugs;
    private ?string $status;
    private string $sort;
    private string $order;

    /**
     * @param int[] $categoryIds
     * @param string[] $categorySlugs
     */
    public function __construct(
        int $page = 1,
        int $perPage = self::DEFAULT_PER_PAGE,
        ?string $query = null,
        array $categoryIds = [],
        array $categorySlugs = [],
        ?string $status = null,
        string $sort = 'createdAt',
        string $order = 'desc'
    ) {
        $this->page = max(1, $page);
        $this->perPage = $this->normalizePerPage($perPage);
        $this->query = $this->normalizeQuery($query);
        $this->categoryIds = $this->normalizeIds($categoryIds);
        $this->categorySlugs = $this->normalizeSlugs($categorySlugs);
        $this->status = $this->normalizeStatus($status);
        $this->sort = $this->normalizeSort($sort);
        $this->order = $this->normalizeOrder($order);
    }

    public function getPage(): int
    {
        return $this->page;
    }

    public function getPerPage(): int
    {
        return $this->perPage;
    }

    public function getOffset(): int
    {
        return ($this->page - 1) * $this->perPage;
    }

    public function getQuery(): ?string
    {
        return $this->query;
    }

    /**
     * @return int[]
     */
    public function getCategoryIds(): array
    {
        return $this->categoryIds;
    }

    /**
     * @return string[]
     */
    public function getCategorySlugs(): array
    {
        return $this->categorySlugs;
    }

    public function getStatus(): ?string
    {
        return $this->status;
    }

    public function getSort(): string
    {
        return $this->sort;
    }

    public function getOrder(): string
    {
        return $this->order;
    }

    private function normalizePerPage(int $perPage): int
    {
        if ($perPage < 1) {
            return self::DEFAULT_PER_PAGE;
        }

        if ($perPage > self::MAX_PER_PAGE) {
            return self::MAX_PER_PAGE;
        }

        return $perPage;
    }

    private function normalizeQuery(?string $query): ?string
    {
        if ($query === null) {
            return null;
        }

        $trimmed = trim($query);

        return $trimmed === '' ? null : $trimmed;
    }

    /**
     * @param int[] $ids
     * @return int[]
     */
    private function normalizeIds(array $ids): array
    {
        $normalized = [];

        foreach ($ids as $value) {
            $id = (int) $value;
            if ($id > 0) {
                $normalized[$id] = $id;
            }
        }

        return array_values($normalized);
    }

    /**
     * @param string[] $slugs
     * @return string[]
     */
    private function normalizeSlugs(array $slugs): array
    {
        $normalized = [];

        foreach ($slugs as $slug) {
            $slug = trim((string) $slug);
            if ($slug !== '') {
                $normalized[$slug] = $slug;
            }
        }

        return array_values($normalized);
    }

    private function normalizeStatus(?string $status): ?string
    {
        if ($status === null) {
            return null;
        }

        $normalized = strtolower(trim($status));

        return $normalized === '' ? null : $normalized;
    }

    private function normalizeSort(string $sort): string
    {
        return match ($sort) {
            'commentCount' => 'commentCount',
            'updatedAt' => 'updatedAt',
            default => 'createdAt',
        };
    }

    private function normalizeOrder(string $order): string
    {
        $normalized = strtolower($order);

        return $normalized === 'asc' ? 'asc' : 'desc';
    }
}
