<?php

declare(strict_types=1);

namespace App\Domain\Config\JobTitles\Services;

use App\Models\JobTitle;
use App\Support\ListQuery;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;

final class JobTitleService
{
    /**
     * @param  array{search?: string|null, sort?: string|null, direction?: string|null, per_page?: int|string|null}  $filters
     * @return LengthAwarePaginator<int, JobTitle>
     */
    public function paginate(array $filters = [], ?int $perPage = null): LengthAwarePaginator
    {
        $search = trim((string) ($filters['search'] ?? ''));
        $perPage ??= ListQuery::perPage($filters);
        [$sort, $direction] = ListQuery::sort($filters, ['id', 'name', 'code'], 'name');

        return JobTitle::query()
            ->when($search !== '', function ($query) use ($search): void {
                $query->where(function ($inner) use ($search): void {
                    $inner
                        ->where('name', 'like', "%{$search}%")
                        ->orWhere('code', 'like', "%{$search}%");
                });
            })
            ->orderBy($sort, $direction)
            ->paginate($perPage)
            ->withQueryString();
    }

    /**
     * @param  array{search?: string|null, sort?: string|null, direction?: string|null, per_page?: int|string|null}  $filters
     * @return LengthAwarePaginator<int, array<string, mixed>>
     */
    public function paginateForWeb(array $filters = [], ?int $perPage = null): LengthAwarePaginator
    {
        return $this->paginate($filters, $perPage)
            ->through(fn (JobTitle $jobTitle): array => $this->toListItem($jobTitle));
    }

    /**
     * @param  array{name: string, code: string}  $data
     */
    public function create(array $data): JobTitle
    {
        return DB::transaction(function () use ($data): JobTitle {
            return JobTitle::query()->create([
                'name' => $data['name'],
                'code' => strtoupper($data['code']),
            ]);
        });
    }

    /**
     * @param  array{name: string, code: string}  $data
     */
    public function update(JobTitle $jobTitle, array $data): JobTitle
    {
        return DB::transaction(function () use ($jobTitle, $data): JobTitle {
            $jobTitle->update([
                'name' => $data['name'],
                'code' => strtoupper($data['code']),
            ]);

            return $jobTitle->fresh();
        });
    }

    public function delete(JobTitle $jobTitle): void
    {
        if ($jobTitle->trashed()) {
            return;
        }

        DB::transaction(function () use ($jobTitle): void {
            $jobTitle->softDeleteSafely();
        });
    }

    /**
     * @return array{id: int, name: string, code: string}
     */
    public function toFormData(JobTitle $jobTitle): array
    {
        return [
            'id' => $jobTitle->id,
            'name' => $jobTitle->name,
            'code' => $jobTitle->code,
        ];
    }

    /**
     * @return array{id: int, name: string, code: string, created_at: string|null}
     */
    public function toListItem(JobTitle $jobTitle): array
    {
        return [
            'id' => $jobTitle->id,
            'name' => $jobTitle->name,
            'code' => $jobTitle->code,
            'created_at' => $jobTitle->created_at?->toIso8601String(),
        ];
    }
}
