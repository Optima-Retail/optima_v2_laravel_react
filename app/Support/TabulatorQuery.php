<?php

declare(strict_types=1);

namespace App\Support;

use Illuminate\Http\Request;

/**
 * Normalize Tabulator remote ajax query params into the app's list-filter shape.
 *
 * Tabulator sends: page, size, sort[0][field]/sort[0][dir] (or sort as array),
 * and optionally filter[] for header filters. Custom ajaxParams (search, kind, …)
 * are passed through as top-level query keys.
 */
final class TabulatorQuery
{
    /**
     * @param  list<string>  $allowedSorts
     * @param  list<string>  $filterKeys  Extra scalar query keys to keep (e.g. search, kind)
     * @return array{
     *     search: string,
     *     page: int,
     *     per_page: int,
     *     sort: string,
     *     direction: string,
     * }&array<string, mixed>
     */
    public static function fromRequest(
        Request $request,
        array $allowedSorts,
        string $defaultSort = 'name',
        string $defaultDirection = 'asc',
        array $filterKeys = ['search'],
    ): array {
        [$sort, $direction] = self::sort($request, $allowedSorts, $defaultSort, $defaultDirection);

        $filters = [
            'sort' => $sort,
            'direction' => $direction,
            'per_page' => ListQuery::perPage([
                'per_page' => $request->integer('size', $request->integer('per_page', 12)),
            ]),
            'page' => max(1, $request->integer('page', 1)),
        ];

        foreach ($filterKeys as $key) {
            $filters[$key] = $request->string($key)->trim()->toString();
        }

        foreach (self::headerFilters($request) as $field => $value) {
            if (in_array($field, $filterKeys, true) && ($filters[$field] ?? '') === '') {
                $filters[$field] = $value;
            }
        }

        return $filters;
    }

    /**
     * @param  list<string>  $allowedSorts
     * @return array{0: string, 1: 'asc'|'desc'}
     */
    public static function sort(
        Request $request,
        array $allowedSorts,
        string $defaultSort = 'name',
        string $defaultDirection = 'asc',
    ): array {
        $sortField = $defaultSort;
        $sortDirection = $defaultDirection;
        $sortInput = $request->input('sort');

        if (is_string($sortInput) && $sortInput !== '') {
            $decoded = json_decode($sortInput, true);

            if (is_array($decoded)) {
                $sortInput = $decoded;
            }
        }

        if (is_array($sortInput) && $sortInput !== []) {
            $first = $sortInput[0] ?? $sortInput;

            if (is_array($first)) {
                $sortField = (string) ($first['field'] ?? $defaultSort);
                $sortDirection = strtolower((string) ($first['dir'] ?? $defaultDirection));
            }
        } elseif (is_string($sortInput) && $sortInput !== '' && ! str_starts_with($sortInput, '[')) {
            $sortField = $sortInput;
            $sortDirection = $request->string('direction')->trim()->toString() ?: $defaultDirection;
        } else {
            $sortField = $request->string('sort')->trim()->toString() ?: $defaultSort;
            $sortDirection = $request->string('direction')->trim()->toString() ?: $defaultDirection;
        }

        return ListQuery::sort(
            ['sort' => $sortField, 'direction' => $sortDirection],
            $allowedSorts,
            $defaultSort,
            $defaultDirection,
        );
    }

    /**
     * @return array<string, string>
     */
    public static function headerFilters(Request $request): array
    {
        $filterInput = $request->input('filter');
        $mapped = [];

        if (is_string($filterInput) && $filterInput !== '') {
            $decoded = json_decode($filterInput, true);
            $filterInput = is_array($decoded) ? $decoded : [];
        }

        if (! is_array($filterInput)) {
            return [];
        }

        foreach ($filterInput as $filter) {
            if (! is_array($filter)) {
                continue;
            }

            $field = (string) ($filter['field'] ?? '');
            $value = $filter['value'] ?? null;

            if ($field === '' || $value === null || $value === '') {
                continue;
            }

            $mapped[$field] = is_scalar($value) ? trim((string) $value) : '';
        }

        return $mapped;
    }
}
