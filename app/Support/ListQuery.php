<?php

declare(strict_types=1);

namespace App\Support;

final class ListQuery
{
    /** @var list<int> */
    public const PER_PAGE_OPTIONS = [10, 12, 25, 50, 100];

    public static function perPage(array $filters, int $default = 12): int
    {
        $value = (int) ($filters['per_page'] ?? $default);

        return in_array($value, self::PER_PAGE_OPTIONS, true) ? $value : $default;
    }

    /**
     * @param  list<string>  $allowed
     * @return array{0: string, 1: 'asc'|'desc'}
     */
    public static function sort(array $filters, array $allowed, string $default, string $defaultDirection = 'asc'): array
    {
        $sort = (string) ($filters['sort'] ?? $default);

        if (! in_array($sort, $allowed, true)) {
            $sort = $default;
        }

        $direction = strtolower((string) ($filters['direction'] ?? $defaultDirection));

        if (! in_array($direction, ['asc', 'desc'], true)) {
            $direction = $defaultDirection;
        }

        /** @var 'asc'|'desc' $direction */
        return [$sort, $direction];
    }
}
