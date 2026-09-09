<?php

declare(strict_types=1);

namespace App\Domain\Evaluations\Services;

use App\Domain\Companies\Enums\CompanyRelationshipKind;
use App\Models\Company;
use App\Models\Establishment;
use App\Models\Evaluation;
use App\Models\EvaluationStatus;
use App\Models\User;
use App\Support\ListQuery;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

final class EvaluationService
{
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
     * Establishments for accessible client companies.
     *
     * @return list<array{id: int, label: string, company_id: int}>
     */
    public function establishmentOptions(Company $owner): array
    {
        $ids = $this->accessibleCompanyIds($owner);

        if ($ids === []) {
            return [];
        }

        return Establishment::query()
            ->whereIn('company_id', $ids)
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
     * @return list<array{id: int, label: string}>
     */
    public function userOptions(): array
    {
        return User::query()
            ->orderBy('name')
            ->get(['id', 'name', 'email'])
            ->map(fn (User $user): array => [
                'id' => $user->id,
                'label' => $user->email
                    ? "{$user->name} ({$user->email})"
                    : $user->name,
            ])
            ->values()
            ->all();
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
     * @param  array{search?: string|null, sort?: string|null, direction?: string|null, per_page?: int|string|null}  $filters
     * @return LengthAwarePaginator<int, Evaluation>
     */
    public function paginateForOwner(Company $owner, array $filters = [], ?int $perPage = null): LengthAwarePaginator
    {
        $search = trim((string) ($filters['search'] ?? ''));
        $perPage ??= ListQuery::perPage($filters);
        [$sort, $direction] = ListQuery::sort(
            $filters,
            ['id', 'subject', 'next_action_at', 'visit_count', 'call_count', 'created_at'],
            'id',
        );

        $companyIds = $this->accessibleCompanyIds($owner);

        return Evaluation::query()
            ->with(['establishment', 'status', 'responsibleUser'])
            ->whereHas('establishment', function ($query) use ($companyIds): void {
                $query->whereIn('company_id', $companyIds);
            })
            ->when($search !== '', function ($query) use ($search): void {
                $query->where(function ($inner) use ($search): void {
                    $inner
                        ->where('subject', 'like', "%{$search}%")
                        ->orWhere('public_id', 'like', "%{$search}%")
                        ->orWhereHas('establishment', function ($establishmentQuery) use ($search): void {
                            $establishmentQuery
                                ->where('name', 'like', "%{$search}%")
                                ->orWhere('code', 'like', "%{$search}%");
                        });
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

            return $evaluation->load(['establishment', 'status', 'responsibleUser']);
        });
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function update(Evaluation $evaluation, array $data): Evaluation
    {
        return DB::transaction(function () use ($evaluation, $data): Evaluation {
            $evaluation->update($this->attributes($data));

            return $evaluation->fresh(['establishment', 'status', 'responsibleUser']) ?? $evaluation;
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
