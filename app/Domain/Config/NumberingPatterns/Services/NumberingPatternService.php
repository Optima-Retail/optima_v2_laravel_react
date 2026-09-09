<?php

declare(strict_types=1);

namespace App\Domain\Config\NumberingPatterns\Services;

use App\Domain\Config\NumberingPatterns\Enums\NumberingResource;
use App\Domain\Config\NumberingPatterns\Enums\NumberingSegmentType;
use App\Models\Company;
use App\Models\NumberingPattern;
use App\Support\ListQuery;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class NumberingPatternService
{
    /**
     * @param  array{search?: string|null, sort?: string|null, direction?: string|null, per_page?: int|string|null}  $filters
     * @return LengthAwarePaginator<int, array<string, mixed>>
     */
    public function paginateForWeb(Company $company, array $filters = [], ?int $perPage = null): LengthAwarePaginator
    {
        $search = trim((string) ($filters['search'] ?? ''));
        $perPage ??= ListQuery::perPage($filters);
        [$sort, $direction] = ListQuery::sort($filters, ['id', 'resource', 'is_active'], 'resource');

        return NumberingPattern::query()
            ->where('company_id', $company->id)
            ->when($search !== '', function ($query) use ($search): void {
                $query->where('resource', 'like', "%{$search}%");
            })
            ->orderBy($sort, $direction)
            ->paginate($perPage)
            ->withQueryString()
            ->through(fn (NumberingPattern $pattern): array => $this->toListItem($pattern));
    }

    public function findActiveForResource(Company $company, string $resource): ?NumberingPattern
    {
        return NumberingPattern::query()
            ->where('company_id', $company->id)
            ->where('resource', $resource)
            ->where('is_active', true)
            ->first();
    }

    public function findForResource(Company $company, string $resource): ?NumberingPattern
    {
        return NumberingPattern::query()
            ->where('company_id', $company->id)
            ->where('resource', $resource)
            ->first();
    }

    /**
     * Create the default pattern for a company/resource when none exists yet.
     */
    public function ensureDefault(Company $company, string $resource): NumberingPattern
    {
        $existing = $this->findForResource($company, $resource);

        if ($existing !== null) {
            return $existing;
        }

        return $this->create([
            'company_id' => $company->id,
            'resource' => $resource,
            'segments' => $this->defaultSegmentsFor($resource),
            'reset_yearly' => false,
            'is_active' => true,
        ]);
    }

    public function peekNext(Company $company, string $resource, ?int $year = null): ?string
    {
        $pattern = $this->findActiveForResource($company, $resource);

        if ($pattern === null) {
            if ($this->findForResource($company, $resource) !== null) {
                return null;
            }

            $pattern = $this->makeDefaultDraft($company, $resource);
        }

        $year ??= (int) now()->format('Y');
        [$sequence] = $this->nextSequenceValues($pattern, $year);

        return $this->format($pattern, $sequence, $year);
    }

    public function allocateNext(Company $company, string $resource, ?int $year = null): string
    {
        $year ??= (int) now()->format('Y');

        return DB::transaction(function () use ($company, $resource, $year): string {
            $pattern = NumberingPattern::query()
                ->where('company_id', $company->id)
                ->where('resource', $resource)
                ->lockForUpdate()
                ->first();

            if ($pattern === null) {
                $created = $this->create([
                    'company_id' => $company->id,
                    'resource' => $resource,
                    'segments' => $this->defaultSegmentsFor($resource),
                    'reset_yearly' => false,
                    'is_active' => true,
                ]);

                $pattern = NumberingPattern::query()
                    ->whereKey($created->id)
                    ->lockForUpdate()
                    ->firstOrFail();
            }

            if (! $pattern->is_active) {
                throw ValidationException::withMessages([
                    'code' => 'No active numbering pattern is configured for this resource.',
                ]);
            }

            [$sequence, $sequenceYear] = $this->nextSequenceValues($pattern, $year);

            $pattern->update([
                'last_sequence' => $sequence,
                'last_year' => $sequenceYear,
            ]);

            return $this->format($pattern, $sequence, $year);
        });
    }

    /**
     * @param  array{company_id: int, resource: string, segments: list<array<string, mixed>>, reset_yearly: bool, is_active: bool}  $data
     */
    public function create(array $data): NumberingPattern
    {
        return DB::transaction(function () use ($data): NumberingPattern {
            return NumberingPattern::query()->create([
                'company_id' => $data['company_id'],
                'resource' => $data['resource'],
                'segments' => $this->normalizeSegments($data['segments'] ?? []),
                'reset_yearly' => (bool) $data['reset_yearly'],
                'is_active' => (bool) $data['is_active'],
                'last_sequence' => 0,
                'last_year' => null,
            ]);
        });
    }

    /**
     * @param  array{segments: list<array<string, mixed>>, reset_yearly: bool, is_active: bool}  $data
     */
    public function update(NumberingPattern $pattern, array $data): NumberingPattern
    {
        return DB::transaction(function () use ($pattern, $data): NumberingPattern {
            $pattern->update([
                'segments' => $this->normalizeSegments($data['segments'] ?? []),
                'reset_yearly' => (bool) $data['reset_yearly'],
                'is_active' => (bool) $data['is_active'],
            ]);

            return $pattern->fresh() ?? $pattern;
        });
    }

    /**
     * @param  array{segments: list<array<string, mixed>>, reset_yearly: bool, is_active: bool}  $data
     */
    public function upsertForResource(Company $company, string $resource, array $data): NumberingPattern
    {
        $existing = $this->findForResource($company, $resource);

        if ($existing === null) {
            return $this->create([
                ...$data,
                'company_id' => $company->id,
                'resource' => $resource,
            ]);
        }

        return $this->update($existing, $data);
    }

    public function delete(NumberingPattern $pattern): void
    {
        if ($pattern->trashed()) {
            return;
        }

        DB::transaction(function () use ($pattern): void {
            $pattern->softDeleteSafely();
        });
    }

    /**
     * @return list<array{id: string, label: string}>
     */
    public function resourceOptions(): array
    {
        return collect(NumberingResource::cases())
            ->map(fn (NumberingResource $resource): array => [
                'id' => $resource->value,
                'label' => $resource->value,
            ])
            ->values()
            ->all();
    }

    /**
     * @return list<array{id: string, label: string}>
     */
    public function segmentTypeOptions(): array
    {
        return collect(NumberingSegmentType::cases())
            ->map(fn (NumberingSegmentType $type): array => [
                'id' => $type->value,
                'label' => $type->value,
            ])
            ->values()
            ->all();
    }

    /**
     * @return array{id: int, company_id: int, resource: string, segments: list<array<string, mixed>>, reset_yearly: bool, is_active: bool, last_sequence: int, last_year: int|null, preview: string}
     */
    public function toFormData(NumberingPattern $pattern): array
    {
        $segments = $this->normalizeSegments($pattern->segments ?? []);

        return [
            'id' => $pattern->id,
            'company_id' => (int) $pattern->company_id,
            'resource' => $pattern->resource,
            'segments' => $segments,
            'reset_yearly' => $pattern->reset_yearly,
            'is_active' => $pattern->is_active,
            'last_sequence' => $pattern->last_sequence,
            'last_year' => $pattern->last_year,
            'preview' => $this->format(
                $pattern,
                max(1, $pattern->last_sequence + 1),
                (int) now()->format('Y'),
            ),
        ];
    }

    /**
     * @return array{id: null, company_id: int, resource: string, segments: list<array<string, mixed>>, reset_yearly: bool, is_active: bool, last_sequence: int, last_year: int|null, preview: string}
     */
    public function defaultFormData(Company $company, string $resource): array
    {
        $segments = $this->defaultSegmentsFor($resource);
        $draft = $this->makeDefaultDraft($company, $resource);

        return [
            'id' => null,
            'company_id' => $company->id,
            'resource' => $resource,
            'segments' => $segments,
            'reset_yearly' => false,
            'is_active' => true,
            'last_sequence' => 0,
            'last_year' => null,
            'preview' => $this->format($draft, 1, (int) now()->format('Y')),
        ];
    }

    /**
     * @return array{id: int, company_id: int, resource: string, segments: list<array<string, mixed>>, reset_yearly: bool, is_active: bool, preview: string, created_at: string|null}
     */
    public function toListItem(NumberingPattern $pattern): array
    {
        return [
            'id' => $pattern->id,
            'company_id' => (int) $pattern->company_id,
            'resource' => $pattern->resource,
            'segments' => $this->normalizeSegments($pattern->segments ?? []),
            'reset_yearly' => $pattern->reset_yearly,
            'is_active' => $pattern->is_active,
            'preview' => $this->format(
                $pattern,
                max(1, (int) $pattern->last_sequence + 1),
                (int) now()->format('Y'),
            ),
            'created_at' => $pattern->created_at?->toIso8601String(),
        ];
    }

    public function format(NumberingPattern $pattern, int $sequence, int $year): string
    {
        $parts = [];

        foreach ($this->normalizeSegments($pattern->segments ?? []) as $segment) {
            $type = NumberingSegmentType::tryFrom((string) ($segment['type'] ?? ''));

            if ($type === null) {
                continue;
            }

            $parts[] = match ($type) {
                NumberingSegmentType::Letters,
                NumberingSegmentType::Symbols => (string) ($segment['value'] ?? ''),
                NumberingSegmentType::Year => (string) $year,
                NumberingSegmentType::Sequence => str_pad(
                    (string) $sequence,
                    max(1, min(10, (int) ($segment['digit_length'] ?? 5))),
                    '0',
                    STR_PAD_LEFT,
                ),
            };
        }

        return implode('', $parts);
    }

    /**
     * @param  list<array<string, mixed>>  $segments
     * @return list<array{type: string, value?: string, digit_length?: int}>
     */
    public function normalizeSegments(array $segments): array
    {
        $normalized = [];

        foreach (array_values($segments) as $segment) {
            if (! is_array($segment)) {
                continue;
            }

            $type = NumberingSegmentType::tryFrom((string) ($segment['type'] ?? ''));

            if ($type === null) {
                continue;
            }

            $row = ['type' => $type->value];

            if ($type->requiresValue()) {
                $row['value'] = (string) ($segment['value'] ?? '');
            }

            if ($type->requiresDigitLength()) {
                $row['digit_length'] = max(1, min(10, (int) ($segment['digit_length'] ?? 5)));
            }

            $normalized[] = $row;
        }

        return $normalized;
    }

    /**
     * Default: first letter of the resource + 5-digit sequence (e.g. contracts → C00001).
     *
     * @return list<array{type: string, value?: string, digit_length?: int}>
     */
    public function defaultSegmentsFor(string $resource): array
    {
        $prefix = mb_strtoupper(mb_substr(trim($resource), 0, 1));

        if ($prefix === '') {
            $prefix = 'X';
        }

        return [
            ['type' => NumberingSegmentType::Letters->value, 'value' => $prefix],
            ['type' => NumberingSegmentType::Sequence->value, 'digit_length' => 5],
        ];
    }

    private function makeDefaultDraft(Company $company, string $resource): NumberingPattern
    {
        return new NumberingPattern([
            'company_id' => $company->id,
            'resource' => $resource,
            'segments' => $this->defaultSegmentsFor($resource),
            'reset_yearly' => false,
            'is_active' => true,
            'last_sequence' => 0,
            'last_year' => null,
        ]);
    }

    /**
     * @return array{0: int, 1: int}
     */
    private function nextSequenceValues(NumberingPattern $pattern, int $year): array
    {
        $sequence = (int) $pattern->last_sequence;

        if ($pattern->reset_yearly) {
            if ($pattern->last_year === null || (int) $pattern->last_year !== $year) {
                $sequence = 0;
            }
        }

        return [$sequence + 1, $year];
    }
}
