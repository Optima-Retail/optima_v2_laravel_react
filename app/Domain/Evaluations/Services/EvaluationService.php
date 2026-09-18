<?php

declare(strict_types=1);

namespace App\Domain\Evaluations\Services;

use App\Domain\Chats\Enums\ChatDocumentType;
use App\Domain\Companies\Enums\CompanyRelationshipKind;
use App\Domain\Companies\Support\CompanyMemberUsers;
use App\Domain\StatusChanges\Services\StatusChangeHistoryService;
use App\Models\Company;
use App\Models\Establishment;
use App\Models\Evaluation;
use App\Models\EvaluationStatus;
use App\Support\ListQuery;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Pagination\LengthAwarePaginator as LengthAwarePaginatorConcrete;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

final class EvaluationService
{
    public function __construct(
        private readonly StatusChangeHistoryService $statusChanges,
    ) {}

    /**
     * Client company IDs linked to the owner (kind = customer).
     *
     * @return list<int>
     */
    public function accessibleCompanyIds(Company $owner): array
    {
        return $owner->ownedRelationships()
            ->where('kind', CompanyRelationshipKind::Customer->value)
            ->pluck('related_company_id')
            ->map(fn ($id) => (int) $id)
            ->unique()
            ->values()
            ->all();
    }

    /**
     * Seed establishment options. Full lists load via /select-options/establishments.
     *
     * @param  list<int>  $includeIds
     * @return list<array{id: int, label: string, company_id: int}>
     */
    public function establishmentOptions(Company $owner, array $includeIds = []): array
    {
        unset($owner);

        $includeIds = array_values(array_unique(array_filter(
            array_map('intval', $includeIds),
            fn (int $id): bool => $id > 0,
        )));

        if ($includeIds === []) {
            return [];
        }

        return Establishment::query()
            ->whereIn('id', $includeIds)
            ->orderBy('name')
            ->get(['id', 'name', 'code', 'company_id'])
            ->map(fn (Establishment $establishment): array => [
                'id' => $establishment->id,
                'label' => $establishment->code
                    ? "{$establishment->name} ({$establishment->code})"
                    : $establishment->name,
                'company_id' => (int) $establishment->company_id,
            ])
            ->values()
            ->all();
    }

    /**
     * Active (`is_open`) statuses for evaluation forms.
     * Optionally keep a current inactive status so edit still shows the saved value.
     *
     * @return list<array{id: int, label: string, color: string|null}>
     */
    public function evaluationStatusOptions(?int $includeId = null): array
    {
        return EvaluationStatus::query()
            ->where(function ($query) use ($includeId): void {
                $query->where('is_open', true);

                if ($includeId !== null) {
                    $query->orWhere('id', $includeId);
                }
            })
            ->orderBy('lifecycle')
            ->orderBy('name')
            ->get(['id', 'name', 'color'])
            ->map(fn (EvaluationStatus $status): array => [
                'id' => $status->id,
                'label' => $status->name,
                'color' => $status->color,
            ])
            ->values()
            ->all();
    }

    /**
     * Seed user options. Full lists load via /select-options/users.
     *
     * @param  list<int>  $includeUserIds
     * @return list<array{id: int, label: string}>
     */
    public function userOptions(Company $owner, array $includeUserIds = []): array
    {
        unset($owner);

        return CompanyMemberUsers::optionsByIds($includeUserIds);
    }

    /**
     * Default status: legacy "Abierta" (id 73) when present, otherwise first open status.
     */
    public function defaultEvaluationStatusId(): ?int
    {
        $abierta = EvaluationStatus::query()
            ->whereKey(73)
            ->where('is_open', true)
            ->value('id');

        if ($abierta !== null) {
            return (int) $abierta;
        }

        $open = EvaluationStatus::query()
            ->where('is_open', true)
            ->orderBy('lifecycle')
            ->orderBy('id')
            ->value('id');

        return $open !== null ? (int) $open : null;
    }

    /**
     * @param  array{search?: string|null, sort?: string|null, direction?: string|null, per_page?: int|string|null, evaluation_status_id?: string|null, created_from?: string|null, created_to?: string|null}  $filters
     * @return LengthAwarePaginator<int, Evaluation>
     */
    public function paginateForOwner(Company $owner, array $filters = [], ?int $perPage = null): LengthAwarePaginator
    {
        $search = trim((string) ($filters['search'] ?? ''));
        $evaluationStatusId = trim((string) ($filters['evaluation_status_id'] ?? ''));
        $createdFrom = trim((string) ($filters['created_from'] ?? ''));
        $createdTo = trim((string) ($filters['created_to'] ?? ''));
        $perPage ??= ListQuery::perPage($filters);
        [$sort, $direction] = ListQuery::sort(
            $filters,
            ['id', 'subject', 'next_action_at', 'visit_count', 'call_count', 'created_at'],
            'id',
            'desc',
        );

        $companyIds = $this->accessibleCompanyIds($owner);

        if ($companyIds === []) {
            return new LengthAwarePaginatorConcrete([], 0, $perPage);
        }

        $query = Evaluation::query()
            ->select('evaluations.*')
            ->join('establishments as eval_est', function ($join) use ($companyIds): void {
                $join->on('eval_est.id', '=', 'evaluations.establishment_id')
                    ->whereIn('eval_est.company_id', $companyIds);
            })
            ->with(['establishment:id,name,code,company_id', 'status:id,name,color', 'responsibleUser:id,name'])
            ->when($search !== '', function ($builder) use ($search): void {
                $builder->where(function ($inner) use ($search): void {
                    $inner
                        ->where('evaluations.subject', 'like', "%{$search}%")
                        ->orWhere('evaluations.public_id', 'like', "%{$search}%")
                        ->orWhere('eval_est.name', 'like', "%{$search}%")
                        ->orWhere('eval_est.code', 'like', "%{$search}%");
                });
            })
            ->when($evaluationStatusId !== '', fn ($builder) => $builder->where('evaluations.evaluation_status_id', (int) $evaluationStatusId))
            ->when($createdFrom !== '', fn ($builder) => $builder->whereDate('evaluations.created_at', '>=', $createdFrom))
            ->when($createdTo !== '', fn ($builder) => $builder->whereDate('evaluations.created_at', '<=', $createdTo))
            ->orderBy("evaluations.{$sort}", $direction);

        // ~200k rows: exact COUNT(*) with establishment scope is expensive. Detect has-more only.
        $page = max(1, (int) ($filters['page'] ?? LengthAwarePaginatorConcrete::resolveCurrentPage()));
        $rows = (clone $query)
            ->forPage($page, $perPage + 1)
            ->get();
        $hasMore = $rows->count() > $perPage;
        $items = $rows->take($perPage)->values();
        $total = (($page - 1) * $perPage) + $items->count() + ($hasMore ? 1 : 0);

        return (new LengthAwarePaginatorConcrete(
            $items,
            $total,
            $perPage,
            $page,
            [
                'path' => LengthAwarePaginatorConcrete::resolveCurrentPath(),
                'pageName' => 'page',
            ],
        ))->withQueryString();
    }

    /**
     * @param  array{search?: string|null, sort?: string|null, direction?: string|null, per_page?: int|string|null, evaluation_status_id?: string|null, created_from?: string|null, created_to?: string|null}  $filters
     * @return LengthAwarePaginator<int, array<string, mixed>>
     */
    public function paginateForWeb(Company $owner, array $filters = [], ?int $perPage = null): LengthAwarePaginator
    {
        return $this->paginateForOwner($owner, $filters, $perPage)
            ->through(fn (Evaluation $evaluation): array => $this->toListItem($evaluation));
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function create(array $data): Evaluation
    {
        return DB::transaction(function () use ($data): Evaluation {
            $attributes = $this->attributes($data);
            $attributes['public_id'] = (string) Str::uuid();
            $attributes['visit_count'] = 0;
            $attributes['call_count'] = 0;

            if (($attributes['evaluation_status_id'] ?? null) === null) {
                $attributes['evaluation_status_id'] = $this->defaultEvaluationStatusId();
            }

            $evaluation = Evaluation::query()->create($attributes);

            $this->statusChanges->record(
                ChatDocumentType::Evaluation,
                (int) $evaluation->id,
                null,
                $evaluation->evaluation_status_id !== null ? (int) $evaluation->evaluation_status_id : null,
            );

            return $evaluation->load(['establishment', 'status', 'responsibleUser']);
        });
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function update(Evaluation $evaluation, array $data): Evaluation
    {
        return DB::transaction(function () use ($evaluation, $data): Evaluation {
            $oldStatusId = $evaluation->evaluation_status_id !== null
                ? (int) $evaluation->evaluation_status_id
                : null;

            $evaluation->update($this->attributes($data));

            $fresh = $evaluation->fresh(['establishment', 'status', 'responsibleUser']) ?? $evaluation;

            $this->statusChanges->record(
                ChatDocumentType::Evaluation,
                (int) $fresh->id,
                $oldStatusId,
                $fresh->evaluation_status_id !== null ? (int) $fresh->evaluation_status_id : null,
            );

            return $fresh;
        });
    }

    public function delete(Evaluation $evaluation): void
    {
        if ($evaluation->trashed()) {
            return;
        }

        $evaluation->delete();
    }

    /**
     * @return array<string, mixed>
     */
    public function toFormData(Evaluation $evaluation): array
    {
        return [
            'id' => $evaluation->id,
            'subject' => $evaluation->subject,
            'public_id' => $evaluation->public_id,
            'establishment_id' => $evaluation->establishment_id,
            'evaluation_status_id' => $evaluation->evaluation_status_id,
            'responsible_user_id' => $evaluation->responsible_user_id,
            'next_action_at' => $evaluation->next_action_at?->format('Y-m-d\TH:i'),
            'facility_question' => $evaluation->facility_question,
            'technician_question' => $evaluation->technician_question,
            'visit_count' => $evaluation->visit_count,
            'call_count' => $evaluation->call_count,
            'qc_duration_minutes' => $evaluation->qc_duration_minutes,
            'first_contact_attempt_at' => $evaluation->first_contact_attempt_at?->format('Y-m-d\TH:i'),
            'closed_at' => $evaluation->closed_at?->format('Y-m-d\TH:i'),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function toListItem(Evaluation $evaluation): array
    {
        return [
            'id' => $evaluation->id,
            'subject' => $evaluation->subject,
            'establishment_name' => $evaluation->establishment?->name,
            'status_name' => $evaluation->status?->name,
            'status_color' => $evaluation->status?->color,
            'responsible_user_name' => $evaluation->responsibleUser?->name,
            'next_action_at' => $evaluation->next_action_at?->toIso8601String(),
            'visit_count' => $evaluation->visit_count,
            'call_count' => $evaluation->call_count,
            'created_at' => $evaluation->created_at?->toIso8601String(),
        ];
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function attributes(array $data): array
    {
        return [
            'subject' => $data['subject'] ?? null,
            'establishment_id' => $data['establishment_id'] ?? null,
            'evaluation_status_id' => $data['evaluation_status_id'] ?? null,
            'responsible_user_id' => $data['responsible_user_id'] ?? null,
            'next_action_at' => $data['next_action_at'] ?? null,
            'facility_question' => $data['facility_question'] ?? null,
            'technician_question' => $data['technician_question'] ?? null,
        ];
    }
}
