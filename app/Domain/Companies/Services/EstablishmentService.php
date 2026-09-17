<?php

declare(strict_types=1);

namespace App\Domain\Companies\Services;

use App\Domain\Companies\Enums\CompanyRelationshipKind;
use App\Domain\Companies\Support\CompanyMemberUsers;
use App\Domain\Companies\Support\Coordinates;
use App\Models\Company;
use App\Models\CompanyRelationship;
use App\Models\Compliment;
use App\Models\Delegation;
use App\Models\Establishment;
use App\Models\EstablishmentFormTemplate;
use App\Models\EstablishmentType;
use App\Models\Evaluation;
use App\Models\FormTemplate;
use App\Models\Incident;
use App\Models\Language;
use App\Models\Series;
use App\Models\Timezone;
use App\Models\WorkOrder;
use App\Models\WorkOrderType;
use App\Support\ListQuery;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;

final class EstablishmentService
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
     * @return list<array{id: int, label: string, logo_url: string|null}>
     */
    /**
     * Client companies for selects (active only). Keep `$includeId` for edit forms.
     *
     * @return list<array{id: int, label: string, logo_url: string|null}>
     */
    public function clientCompanyOptions(Company $owner, ?int $includeId = null): array
    {
        $ids = $this->accessibleCompanyIds($owner);

        if ($ids === []) {
            return [];
        }

        return Company::query()
            ->whereIn('id', $ids)
            ->where(function ($query) use ($includeId): void {
                $query->where('is_active', true);

                if ($includeId !== null) {
                    $query->orWhereKey($includeId);
                }
            })
            ->orderBy('name')
            ->get(['id', 'name', 'tax_id', 'logo'])
            ->map(fn (Company $company): array => $company->toSelectOption())
            ->values()
            ->all();
    }

    /**
     * @param  array{search?: string|null, sort?: string|null, direction?: string|null, per_page?: int|string|null, is_active?: string|null, created_from?: string|null, created_to?: string|null}  $filters
     * @return LengthAwarePaginator<int, Establishment>
     */
    public function paginateForOwner(Company $owner, array $filters = [], ?int $perPage = null): LengthAwarePaginator
    {
        $search = trim((string) ($filters['search'] ?? ''));
        $isActive = trim((string) ($filters['is_active'] ?? ''));
        $createdFrom = trim((string) ($filters['created_from'] ?? ''));
        $createdTo = trim((string) ($filters['created_to'] ?? ''));
        $perPage ??= ListQuery::perPage($filters);
        [$sort, $direction] = ListQuery::sort($filters, ['id', 'name', 'code', 'city', 'is_active', 'created_at'], 'name');

        return Establishment::query()
            ->with(['company', 'country', 'timezone', 'billingCompany', 'delegation'])
            ->whereIn('company_id', $this->accessibleCompanyIds($owner))
            ->when($search !== '', function ($query) use ($search): void {
                $query->where(function ($inner) use ($search): void {
                    $inner
                        ->where('name', 'like', "%{$search}%")
                        ->orWhere('code', 'like', "%{$search}%")
                        ->orWhere('city', 'like', "%{$search}%");
                });
            })
            ->when($isActive === '1' || $isActive === '0', fn ($query) => $query->where('is_active', $isActive === '1'))
            ->when($createdFrom !== '', fn ($query) => $query->whereDate('created_at', '>=', $createdFrom))
            ->when($createdTo !== '', fn ($query) => $query->whereDate('created_at', '<=', $createdTo))
            ->orderBy($sort, $direction)
            ->paginate($perPage)
            ->withQueryString();
    }

    /**
     * @param  array{search?: string|null, sort?: string|null, direction?: string|null, per_page?: int|string|null, is_active?: string|null, created_from?: string|null, created_to?: string|null}  $filters
     * @return LengthAwarePaginator<int, array<string, mixed>>
     */
    public function paginateForWeb(Company $owner, array $filters = [], ?int $perPage = null): LengthAwarePaginator
    {
        return $this->paginateForOwner($owner, $filters, $perPage)
            ->through(fn (Establishment $establishment): array => $this->toListItem($establishment));
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function create(array $data): Establishment
    {
        return DB::transaction(function () use ($data): Establishment {
            $establishment = Establishment::query()->create($this->attributes($data));
            $establishment->collaborators()->sync($data['collaborator_ids'] ?? []);
            $establishment->blacklistedTechnicians()->sync($data['blocked_technician_ids'] ?? []);
            $establishment->favoriteTechnicians()->sync($data['favorite_technician_ids'] ?? []);

            return $establishment->load([
                'company',
                'country',
                'timezone',
                'delegation',
                'collaborators',
                'blacklistedTechnicians',
                'favoriteTechnicians',
            ]);
        });
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function update(Establishment $establishment, array $data): Establishment
    {
        return DB::transaction(function () use ($establishment, $data): Establishment {
            $establishment->update($this->attributes($data));
            $establishment->collaborators()->sync($data['collaborator_ids'] ?? []);
            $establishment->blacklistedTechnicians()->sync($data['blocked_technician_ids'] ?? []);
            $establishment->favoriteTechnicians()->sync($data['favorite_technician_ids'] ?? []);
            $this->syncFormTemplateLinks($establishment, $data['form_template_links'] ?? null);

            return $establishment->fresh([
                'company',
                'country',
                'timezone',
                'billingCompany',
                'delegation',
                'collaborators',
                'blacklistedTechnicians',
                'favoriteTechnicians',
                'formTemplateLinks',
            ]) ?? $establishment;
        });
    }

    public function delete(Establishment $establishment): void
    {
        if ($establishment->trashed()) {
            return;
        }

        if (! $this->canBeDeleted($establishment)) {
            throw new \InvalidArgumentException('establishment_cannot_be_deleted');
        }

        DB::transaction(function () use ($establishment): void {
            $establishment->collaborators()->detach();
            $establishment->blacklistedTechnicians()->detach();
            $establishment->favoriteTechnicians()->detach();
            $establishment->formTemplateLinks()->delete();
            $establishment->softDeleteSafely();
        });
    }

    /**
     * Operational usages that still reference this establishment and block deletion.
     *
     * Owned configuration (collaborators, attachments, blacklist/favorites, template links)
     * does not block.
     *
     * @return list<string>
     */
    public function deletionBlockers(Establishment $establishment): array
    {
        $id = $establishment->id;
        $blockers = [];

        if (WorkOrder::query()->where('establishment_id', $id)->exists()) {
            $blockers[] = 'work_orders';
        }

        if (Evaluation::query()->where('establishment_id', $id)->exists()) {
            $blockers[] = 'evaluations';
        }

        if (DB::table('contract_establishment')->where('establishment_id', $id)->exists()) {
            $blockers[] = 'contracts';
        }

        if (Compliment::query()->where('establishment_id', $id)->exists()) {
            $blockers[] = 'compliments';
        }

        if (Incident::query()->where('establishment_id', $id)->exists()) {
            $blockers[] = 'incidents';
        }

        if (FormTemplate::query()->where('establishment_id', $id)->exists()) {
            $blockers[] = 'form_templates';
        }

        if (DB::table('work_order_type_form_templates')->where('establishment_id', $id)->exists()) {
            $blockers[] = 'work_order_type_form_templates';
        }

        return $blockers;
    }

    public function canBeDeleted(Establishment $establishment): bool
    {
        return $this->deletionBlockers($establishment) === [];
    }

    /**
     * @return list<array{id: int, label: string}>
     */
    public function timezoneOptions(): array
    {
        return Timezone::query()
            ->orderBy('name')
            ->get(['id', 'name', 'timezone'])
            ->map(fn (Timezone $timezone): array => [
                'id' => $timezone->id,
                'label' => "{$timezone->name} ({$timezone->timezone})",
            ])
            ->values()
            ->all();
    }

    /**
     * @return list<array{id: int, label: string}>
     */
    public function delegationOptions(): array
    {
        return Delegation::query()
            ->orderBy('name')
            ->get(['id', 'name'])
            ->map(fn (Delegation $delegation): array => [
                'id' => $delegation->id,
                'label' => $delegation->name,
            ])
            ->values()
            ->all();
    }

    /**
     * @return list<array{id: int, label: string}>
     */
    public function languageOptions(): array
    {
        return Language::query()
            ->orderBy('name')
            ->get(['id', 'name', 'code'])
            ->map(fn (Language $language): array => [
                'id' => $language->id,
                'label' => "{$language->name} ({$language->code})",
            ])
            ->values()
            ->all();
    }

    /**
     * @return list<array{id: int, label: string}>
     */
    public function establishmentTypeOptions(): array
    {
        return EstablishmentType::query()
            ->orderBy('name')
            ->get(['id', 'name', 'code'])
            ->map(fn (EstablishmentType $type): array => [
                'id' => $type->id,
                'label' => "{$type->name} ({$type->code})",
            ])
            ->values()
            ->all();
    }

    /**
     * @return list<array{id: int, label: string}>
     */
    public function seriesOptions(?int $includeId = null): array
    {
        return Series::query()
            ->where(function ($query) use ($includeId): void {
                $query->where('is_selectable', true);

                if ($includeId !== null) {
                    $query->orWhereKey($includeId);
                }
            })
            ->orderBy('key')
            ->get(['id', 'key'])
            ->map(fn (Series $series): array => [
                'id' => $series->id,
                'label' => $series->key,
            ])
            ->values()
            ->all();
    }

    /**
     * @param  list<int>  $includeUserIds
     * @return list<array{id: int, label: string}>
     */
    public function userOptions(Company $owner, array $includeUserIds = []): array
    {
        return CompanyMemberUsers::options($owner, $includeUserIds);
    }

    /**
     * Technician company relationships owned by the active company.
     *
     * @return list<array{id: int, label: string, logo_url: string|null}>
     */
    public function technicianOptions(Company $owner): array
    {
        return CompanyRelationship::query()
            ->with('relatedCompany:id,name,tradename,logo,is_active')
            ->where('owner_company_id', $owner->id)
            ->where('kind', CompanyRelationshipKind::Technician->value)
            ->whereHas('relatedCompany', fn ($query) => $query->where('is_active', true))
            ->orderBy('id')
            ->get()
            ->map(fn (CompanyRelationship $relationship): array => $relationship->toSelectOption())
            ->values()
            ->all();
    }

    /**
     * @return list<array{id: int, label: string}>
     */
    public function workOrderTypeOptions(): array
    {
        return WorkOrderType::query()
            ->orderBy('name')
            ->get(['id', 'name', 'code'])
            ->map(fn (WorkOrderType $type): array => [
                'id' => $type->id,
                'label' => $type->code ? "{$type->name} ({$type->code})" : $type->name,
            ])
            ->values()
            ->all();
    }

    /**
     * @return list<array{id: int, label: string}>
     */
    public function formTemplateOptions(Company $owner): array
    {
        return FormTemplate::query()
            ->where('company_id', $owner->id)
            ->orderBy('name')
            ->get(['id', 'name'])
            ->map(fn (FormTemplate $template): array => [
                'id' => $template->id,
                'label' => $template->name,
            ])
            ->values()
            ->all();
    }

    /**
     * @return list<array{id: int, form_template_id: int, work_order_type_id: int}>
     */
    public function formTemplateLinksFor(Establishment $establishment): array
    {
        return $establishment->formTemplateLinks()
            ->orderBy('id')
            ->get(['id', 'form_template_id', 'work_order_type_id'])
            ->map(fn (EstablishmentFormTemplate $link): array => [
                'id' => $link->id,
                'form_template_id' => (int) $link->form_template_id,
                'work_order_type_id' => (int) $link->work_order_type_id,
            ])
            ->values()
            ->all();
    }

    /**
     * @return array<string, mixed>
     */
    public function toFormData(Establishment $establishment): array
    {
        $establishment->loadMissing(['collaborators', 'blacklistedTechnicians', 'favoriteTechnicians']);

        return [
            'id' => $establishment->id,
            'company_id' => $establishment->company_id,
            'name' => $establishment->name,
            'collaborator_ids' => $establishment->collaborators->pluck('id')->values()->all(),
            'blocked_technician_ids' => $establishment->blacklistedTechnicians->pluck('id')->map(fn ($id) => (int) $id)->values()->all(),
            'favorite_technician_ids' => $establishment->favoriteTechnicians->pluck('id')->map(fn ($id) => (int) $id)->values()->all(),
            'form_template_links' => $this->formTemplateLinksFor($establishment),
            'code' => $establishment->code,
            'store_code' => $establishment->store_code,
            'alternate_store_code' => $establishment->alternate_store_code,
            'phone' => $establishment->phone,
            'email' => $establishment->email,
            'emails' => $establishment->emails,
            'recipient_emails' => $establishment->recipient_emails,
            'address_line_1' => $establishment->address_line_1,
            'address_line_2' => $establishment->address_line_2,
            'city' => $establishment->city,
            'province_id' => $establishment->province_id,
            'postal_code' => $establishment->postal_code,
            'country_id' => $establishment->country_id,
            'timezone_id' => $establishment->timezone_id,
            'language_id' => $establishment->language_id,
            'establishment_type_id' => $establishment->establishment_type_id,
            'delegation_id' => $establishment->delegation_id,
            'series_id' => $establishment->series_id,
            'billing_company_id' => $establishment->billing_company_id,
            'responsible_user_id' => $establishment->responsible_user_id,
            'is_active' => $establishment->is_active,
            'is_client_priority' => $establishment->is_client_priority,
            'is_reviewed' => $establishment->is_reviewed,
            'is_email_reviewed' => $establishment->is_email_reviewed,
            'has_site_health_and_safety' => $establishment->has_site_health_and_safety,
            'has_customer_health_and_safety' => $establishment->has_customer_health_and_safety,
            'is_quality_control_contactable' => $establishment->is_quality_control_contactable,
            'has_parking' => $establishment->has_parking,
            'is_ulez_zone' => $establishment->is_ulez_zone,
            'latitude' => Coordinates::format($establishment->latitude),
            'longitude' => Coordinates::format($establishment->longitude),
            'tax_rate' => $establishment->tax_rate,
            'tax_included' => $establishment->tax_included,
            'legacy_erp_id' => $establishment->legacy_erp_id,
            'integration_external_id' => $establishment->integration_external_id,
            'notes' => $establishment->notes,
            'notes_alert' => $establishment->notes_alert,
            'internal_notes' => $establishment->internal_notes,
            'internal_notes_alert' => $establishment->internal_notes_alert,
            'voicebot_time_slots' => $establishment->voicebot_time_slots,
        ];
    }

    /**
     * @return list<array{id: int, name: string, code: string|null, city: string|null, is_active: bool}>
     */
    public function forClientCompany(int $companyId): array
    {
        return Establishment::query()
            ->where('company_id', $companyId)
            ->where('is_active', true)
            ->orderBy('name')
            ->get(['id', 'name', 'code', 'city', 'is_active'])
            ->map(fn (Establishment $establishment): array => [
                'id' => $establishment->id,
                'name' => $establishment->name,
                'code' => $establishment->code,
                'city' => $establishment->city,
                'is_active' => (bool) $establishment->is_active,
            ])
            ->values()
            ->all();
    }

    /**
     * @return array<string, mixed>
     */
    public function toListItem(Establishment $establishment): array
    {
        return [
            'id' => $establishment->id,
            'name' => $establishment->name,
            'code' => $establishment->code,
            'city' => $establishment->city,
            'company_name' => $establishment->company?->name,
            'delegation_name' => $establishment->delegation?->name,
            'is_active' => $establishment->is_active,
            'created_at' => $establishment->created_at?->toIso8601String(),
        ];
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function attributes(array $data): array
    {
        $data = Arr::except($data, [
            'collaborator_ids',
            'blocked_technician_ids',
            'favorite_technician_ids',
            'form_template_links',
        ]);

        foreach ([
            'code', 'store_code', 'alternate_store_code', 'phone', 'email', 'emails', 'recipient_emails',
            'address_line_1', 'address_line_2', 'city', 'province_id', 'postal_code',
            'country_id', 'timezone_id', 'language_id', 'establishment_type_id', 'delegation_id', 'series_id',
            'billing_company_id', 'responsible_user_id',
            'latitude', 'longitude', 'tax_rate', 'legacy_erp_id', 'integration_external_id',
            'notes', 'internal_notes',
        ] as $key) {
            if (array_key_exists($key, $data) && $data[$key] === '') {
                $data[$key] = null;
            }
        }

        return $data;
    }

    /**
     * @param  list<array{id?: int|null, form_template_id: int|string, work_order_type_id: int|string}>|null  $links
     */
    private function syncFormTemplateLinks(Establishment $establishment, ?array $links): void
    {
        if ($links === null) {
            return;
        }

        $rows = [];

        foreach ($links as $link) {
            $formTemplateId = (int) ($link['form_template_id'] ?? 0);
            $workOrderTypeId = (int) ($link['work_order_type_id'] ?? 0);

            if ($formTemplateId < 1 || $workOrderTypeId < 1) {
                continue;
            }

            $key = $formTemplateId.':'.$workOrderTypeId;

            if (isset($rows[$key])) {
                continue;
            }

            $rows[$key] = [
                'form_template_id' => $formTemplateId,
                'work_order_type_id' => $workOrderTypeId,
            ];
        }

        $establishment->formTemplateLinks()->delete();

        foreach ($rows as $attrs) {
            $establishment->formTemplateLinks()->create($attrs);
        }
    }
}
