<?php

declare(strict_types=1);

namespace App\Domain\Config\PaymentDocuments\Services;

use App\Models\PaymentDocument;
use App\Support\ListQuery;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;

final class PaymentDocumentService
{
    /**
     * @param  array{search?: string|null, sort?: string|null, direction?: string|null, per_page?: int|string|null}  $filters
     * @return LengthAwarePaginator<int, PaymentDocument>
     */
    public function paginate(array $filters = [], ?int $perPage = null): LengthAwarePaginator
    {
        $search = trim((string) ($filters['search'] ?? ''));
        $perPage ??= ListQuery::perPage($filters);
        [$sort, $direction] = ListQuery::sort($filters, ['id', 'name', 'is_active'], 'name');

        return PaymentDocument::query()
            ->when($search !== '', function ($query) use ($search): void {
                $query->where('name', 'like', "%{$search}%");
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
            ->through(fn (PaymentDocument $document): array => $this->toListItem($document));
    }

    /**
     * @param  array{name: string, is_active: bool}  $data
     */
    public function create(array $data): PaymentDocument
    {
        return DB::transaction(function () use ($data): PaymentDocument {
            return PaymentDocument::query()->create([
                'name' => $data['name'],
                'is_active' => (bool) ($data['is_active'] ?? true),
            ]);
        });
    }

    /**
     * @param  array{name: string, is_active: bool}  $data
     */
    public function update(PaymentDocument $document, array $data): PaymentDocument
    {
        return DB::transaction(function () use ($document, $data): PaymentDocument {
            $document->update([
                'name' => $data['name'],
                'is_active' => (bool) ($data['is_active'] ?? true),
            ]);

            return $document->fresh() ?? $document;
        });
    }

    public function delete(PaymentDocument $document): void
    {
        if ($document->trashed()) {
            return;
        }

        DB::transaction(function () use ($document): void {
            $document->delete();
        });
    }

    /**
     * @return array{id: int, name: string, is_active: bool}
     */
    public function toFormData(PaymentDocument $document): array
    {
        return [
            'id' => $document->id,
            'name' => $document->name,
            'is_active' => $document->is_active,
        ];
    }

    /**
     * @return array{id: int, name: string, is_active: bool, created_at: string|null}
     */
    public function toListItem(PaymentDocument $document): array
    {
        return [
            'id' => $document->id,
            'name' => $document->name,
            'is_active' => $document->is_active,
            'created_at' => $document->created_at?->toIso8601String(),
        ];
    }
}
