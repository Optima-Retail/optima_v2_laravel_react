<?php

declare(strict_types=1);

namespace App\Domain\Companies\Services;

use App\Domain\Companies\Enums\CompanyKind;
use App\Domain\Companies\Support\CompanyValidation;
use App\Models\Brand;
use App\Models\Company;
use App\Models\CompanyRelationship;
use App\Models\Delegation;
use App\Models\Integration;
use App\Models\Language;
use App\Models\RatingType;
use App\Models\Series;
use App\Models\User;
use App\Support\ListQuery;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class CompanyRelationshipService
{
    /**
     * @param  array{search?: string|null, sort?: string|null, direction?: string|null, per_page?: int|string|null, page?: int|string|null, kind?: string|null, kinds?: list<string>|null}  $filters
     * @return LengthAwarePaginator<int, CompanyRelationship>
     */
    public function paginateForOwner(Company $owner, array $filters = [], ?int $perPage = null): LengthAwarePaginator
    {
        $search = trim((string) ($filters['search'] ?? ''));
        $kind = trim((string) ($filters['kind'] ?? ''));
        /** @var list<string> $kinds */
        $kinds = array_values(array_filter(
            array_map('strval', $filters['kinds'] ?? []),
            static fn (string $value): bool => $value !== '',
        ));
        $perPage ??= ListQuery::perPage($filters);
        [$sort, $direction] = ListQuery::sort($filters, ['id', 'kind', 'status', 'created_at'], 'id', 'desc');
        $page = max(1, (int) ($filters['page'] ?? request()->integer('page', 1)));

        return CompanyRelationship::query()
            ->with(['relatedCompany', 'brand'])
            ->where('owner_company_id', $owner->id)
            ->when($kinds !== [], fn ($query) => $query->whereIn('kind', $kinds))
            ->when($kind !== '', fn ($query) => $query->where('kind', $kind))
            ->when($search !== '', function ($query) use ($search): void {
                $query->where(function ($inner) use ($search): void {
                    $inner
                        ->where('owner_reference', 'like', "%{$search}%")
                        ->orWhere('related_reference', 'like', "%{$search}%")
                        ->orWhere('external_code', 'like', "%{$search}%")
                        ->orWhereHas('relatedCompany', fn ($companies) => $companies
                            ->where('name', 'like', "%{$search}%")
                            ->orWhere('tax_id', 'like', "%{$search}%"));
                });
            })
            ->orderBy($sort, $direction)
            ->paginate($perPage, ['*'], 'page', $page)
            ->withQueryString();
    }

    /**
     * @param  array{search?: string|null, sort?: string|null, direction?: string|null, per_page?: int|string|null, page?: int|string|null, kind?: string|null, kinds?: list<string>|null}  $filters
     * @return LengthAwarePaginator<int, array<string, mixed>>
     */
    public function paginateForWeb(Company $owner, array $filters = [], ?int $perPage = null): LengthAwarePaginator
    {
        return $this->paginateForOwner($owner, $filters, $perPage)
            ->through(fn (CompanyRelationship $relationship): array => $this->toListItem($relationship));
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function create(Company $owner, array $data, User $actor): CompanyRelationship
    {
        return DB::transaction(function () use ($owner, $data, $actor): CompanyRelationship {
            $relatedId = $this->resolveRelatedCompanyId($data);

            if ($relatedId === $owner->id) {
                throw ValidationException::withMessages([
                    'related_company_id' => 'A company cannot have a relationship with itself.',
                ]);
            }

            $kind = (string) ($data['kind'] ?? '');

            $duplicate = CompanyRelationship::query()
                ->where('owner_company_id', $owner->id)
                ->where('related_company_id', $relatedId)
                ->where('kind', $kind)
                ->where('deleted_token', '')
                ->exists();

            if ($duplicate) {
                $field = (($data['related_mode'] ?? 'existing') === 'new')
                    ? 'related_company.name'
                    : 'related_company_id';

                throw ValidationException::withMessages([
                    $field => 'This relationship already exists for the selected company and type.',
                ]);
            }

            $payload = $this->attributes($data);
            $payload['related_company_id'] = $relatedId;

            return CompanyRelationship::query()->create([
                ...$payload,
                'owner_company_id' => $owner->id,
                'created_by' => $actor->id,
                'updated_by' => $actor->id,
            ])->load(['relatedCompany', 'brand']);
        });
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function resolveRelatedCompanyId(array $data): int
    {
        $mode = (string) ($data['related_mode'] ?? 'existing');

        if ($mode === 'new') {
            /** @var array<string, mixed> $related */
            $related = is_array($data['related_company'] ?? null) ? $data['related_company'] : [];

            $company = app(CompanyService::class)->create([
                'name' => (string) ($related['name'] ?? ''),
                'tradename' => $related['tradename'] ?? null,
                'tax_id' => $related['tax_id'] ?? null,
                'kind' => CompanyKind::Party->value,
                'email' => $related['email'] ?? null,
                'phone' => $related['phone'] ?? null,
                'is_active' => true,
            ]);

            return $company->id;
        }

        return (int) ($data['related_company_id'] ?? 0);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function update(CompanyRelationship $relationship, array $data, User $actor): CompanyRelationship
    {
        $relatedId = (int) ($data['related_company_id'] ?? $relationship->related_company_id);

        if ($relatedId === $relationship->owner_company_id) {
            throw ValidationException::withMessages([
                'related_company_id' => 'A company cannot have a relationship with itself.',
            ]);
        }

        return DB::transaction(function () use ($relationship, $data, $actor): CompanyRelationship {
            $relationship->update([
                ...$this->attributes($data),
                'updated_by' => $actor->id,
            ]);

            return $relationship->fresh(['relatedCompany', 'brand']) ?? $relationship;
        });
    }

    public function delete(CompanyRelationship $relationship): void
    {
        if ($relationship->trashed()) {
            return;
        }

        DB::transaction(function () use ($relationship): void {
            $relationship->softDeleteSafely();
        });
    }

    /**
     * @return array{
     *     brandOptions: list<array{id: int, label: string}>,
     *     delegationOptions: list<array{id: int, label: string}>,
     *     languageOptions: list<array{id: int, label: string}>,
     *     seriesOptions: list<array{id: int, label: string}>,
     *     ratingTypeOptions: list<array{id: int, label: string}>,
     *     integrationOptions: list<array{id: int, label: string}>,
     *     userOptions: list<array{id: int, label: string}>
     * }
     */
    public function formOptions(): array
    {
        return [
            'brandOptions' => Brand::query()
                ->orderBy('name')
                ->get(['id', 'name'])
                ->map(fn (Brand $brand): array => [
                    'id' => $brand->id,
                    'label' => $brand->name,
                ])
                ->values()
                ->all(),
            'delegationOptions' => Delegation::query()
                ->orderBy('name')
                ->get(['id', 'name'])
                ->map(fn (Delegation $delegation): array => [
                    'id' => $delegation->id,
                    'label' => $delegation->name,
                ])
                ->values()
                ->all(),
            'languageOptions' => Language::query()
                ->orderBy('name')
                ->get(['id', 'name', 'code'])
                ->map(fn (Language $language): array => [
                    'id' => $language->id,
                    'label' => "{$language->name} ({$language->code})",
                ])
                ->values()
                ->all(),
            'seriesOptions' => Series::query()
                ->orderBy('key')
                ->get(['id', 'key'])
                ->map(fn (Series $series): array => [
                    'id' => $series->id,
                    'label' => $series->key,
                ])
                ->values()
                ->all(),
            'ratingTypeOptions' => RatingType::query()
                ->orderBy('name')
                ->get(['id', 'name', 'code'])
                ->map(fn (RatingType $ratingType): array => [
                    'id' => $ratingType->id,
                    'label' => "{$ratingType->name} ({$ratingType->code})",
                ])
                ->values()
                ->all(),
            'integrationOptions' => Integration::query()
                ->orderBy('name')
                ->get(['id', 'name', 'code'])
                ->map(fn (Integration $integration): array => [
                    'id' => $integration->id,
                    'label' => "{$integration->name} ({$integration->code})",
                ])
                ->values()
                ->all(),
            'userOptions' => User::query()
                ->orderBy('name')
                ->get(['id', 'name', 'email'])
                ->map(fn (User $user): array => [
                    'id' => $user->id,
                    'label' => "{$user->name} ({$user->email})",
                ])
                ->values()
                ->all(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function toFormData(CompanyRelationship $relationship): array
    {
        $relationship->loadMissing(['relatedCompany', 'brand']);

        $time = static function (mixed $value): ?string {
            if ($value === null || $value === '') {
                return null;
            }

            $string = (string) $value;

            return strlen($string) >= 5 ? substr($string, 0, 5) : $string;
        };

        return [
            'id' => $relationship->id,
            'owner_company_id' => $relationship->owner_company_id,
            'related_company_id' => $relationship->related_company_id,
            'related_company_name' => $relationship->relatedCompany?->name,
            'kind' => $relationship->kind->value,
            'status' => $relationship->status->value,
            'classification' => $relationship->classification->value,
            'owner_reference' => $relationship->owner_reference,
            'related_reference' => $relationship->related_reference,
            'brand_id' => $relationship->brand_id,
            'external_code' => $relationship->external_code,
            'notes' => $relationship->notes,
            'starts_at' => $relationship->starts_at?->toDateString(),
            'ends_at' => $relationship->ends_at?->toDateString(),
            'delegation_id' => $relationship->delegation_id,
            'billing_language_id' => $relationship->billing_language_id,
            'series_id' => $relationship->series_id,
            'rating_type_id' => $relationship->rating_type_id,
            'integration_id' => $relationship->integration_id,
            'integration_external_id' => $relationship->integration_external_id,
            'reported_customer_relationship_id' => $relationship->reported_customer_relationship_id,
            'corrective_work_order_owner_id' => $relationship->corrective_work_order_owner_id,
            'preventive_work_order_owner_id' => $relationship->preventive_work_order_owner_id,
            'quality_owner_id' => $relationship->quality_owner_id,
            'account_owner_id' => $relationship->account_owner_id,
            'commercial_owner_id' => $relationship->commercial_owner_id,
            'sourced_by_user_id' => $relationship->sourced_by_user_id,
            'internal_notes' => $relationship->internal_notes,
            'notes_alert' => $relationship->notes_alert,
            'internal_notes_alert' => $relationship->internal_notes_alert,
            'onboarding_notes' => $relationship->onboarding_notes,
            'billing_comments' => $relationship->billing_comments,
            'rates_notes' => $relationship->rates_notes,
            'archetype' => $relationship->archetype,
            'tax_rate' => $relationship->tax_rate,
            'is_reviewed' => $relationship->is_reviewed,
            'is_email_reviewed' => $relationship->is_email_reviewed,
            'is_invoicing_reviewed' => $relationship->is_invoicing_reviewed,
            'invoicing_reviewed_at' => $relationship->invoicing_reviewed_at?->toDateString(),
            'quote_close_days' => $relationship->quote_close_days,
            'recurring_meeting_frequency' => $relationship->recurring_meeting_frequency,
            'sales_feedback_meeting_frequency' => $relationship->sales_feedback_meeting_frequency,
            'group_zero_cost_work_orders' => $relationship->group_zero_cost_work_orders,
            'load_materials_on_corrective' => $relationship->load_materials_on_corrective,
            'group_preventive_and_corrective' => $relationship->group_preventive_and_corrective,
            'group_preventives_by' => $relationship->group_preventives_by?->value,
            'group_correctives_by' => $relationship->group_correctives_by?->value,
            'invoice_at_month_end' => $relationship->invoice_at_month_end,
            'requires_purchase_order' => $relationship->requires_purchase_order,
            'requires_requester' => $relationship->requires_requester,
            'is_franchise' => $relationship->is_franchise,
            'requires_justification' => $relationship->requires_justification,
            'auto_send_invoices' => $relationship->auto_send_invoices,
            'send_invoices_individually' => $relationship->send_invoices_individually,
            'send_debt_reminders' => $relationship->send_debt_reminders,
            'is_quality_control_contactable' => $relationship->is_quality_control_contactable,
            'requires_client_informed_check' => $relationship->requires_client_informed_check,
            'requires_intervention_scheduled_check' => $relationship->requires_intervention_scheduled_check,
            'requires_budget_approval_limit' => $relationship->requires_budget_approval_limit,
            'is_intercompany' => $relationship->is_intercompany,
            'optima_score' => $relationship->optima_score,
            'customer_score' => $relationship->customer_score,
            'average_score' => $relationship->average_score,
            'optima_score_count' => $relationship->optima_score_count,
            'customer_score_count' => $relationship->customer_score_count,
            'has_health_and_safety' => $relationship->has_health_and_safety,
            'is_field_technician' => $relationship->is_field_technician,
            'is_creditor' => $relationship->is_creditor,
            'is_vip' => $relationship->is_vip,
            'is_available_24h' => $relationship->is_available_24h,
            'day_start_at' => $time($relationship->day_start_at),
            'day_end_at' => $time($relationship->day_end_at),
            'has_garnishment' => $relationship->has_garnishment,
            'whatsapp_messaging_authorized' => $relationship->whatsapp_messaging_authorized,
            'registered_at' => $relationship->registered_at?->toDateString(),
            'legacy_status_id' => $relationship->legacy_status_id,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function toListItem(CompanyRelationship $relationship): array
    {
        return [
            'id' => $relationship->id,
            'related_company_id' => $relationship->related_company_id,
            'related_company_name' => $relationship->relatedCompany?->name,
            'kind' => $relationship->kind->value,
            'status' => $relationship->status->value,
            'classification' => $relationship->classification->value,
            'brand_name' => $relationship->brand?->name,
            'owner_reference' => $relationship->owner_reference,
            'created_at' => $relationship->created_at?->toIso8601String(),
        ];
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function attributes(array $data): array
    {
        unset($data['related_mode'], $data['related_company'], $data['owner_company_id']);

        foreach (CompanyValidation::relationshipNullableKeys() as $key) {
            if (array_key_exists($key, $data) && $data[$key] === '') {
                $data[$key] = null;
            }
        }

        return $data;
    }
}
