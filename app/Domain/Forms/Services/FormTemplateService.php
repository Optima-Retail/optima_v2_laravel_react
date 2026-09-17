<?php

declare(strict_types=1);

namespace App\Domain\Forms\Services;

use App\Domain\Companies\Enums\CompanyRelationshipKind;
use App\Domain\Forms\Enums\FormTemplateOwnerType;
use App\Models\Brand;
use App\Models\Company;
use App\Models\CompanyRelationship;
use App\Models\Establishment;
use App\Models\FormTemplate;
use App\Models\FormTemplateField;
use App\Models\FormTemplateSection;
use App\Models\FormType;
use App\Models\Language;
use App\Models\WorkOrder;
use App\Models\WorkOrderType;
use App\Support\ListQuery;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;

final class FormTemplateService
{
    /**
     * @param  array{
     *     search?: string|null,
     *     form_type_id?: string|null,
     *     owner_type?: string|null,
     *     sort?: string|null,
     *     direction?: string|null,
     *     per_page?: int|string|null
     * }  $filters
     * @return LengthAwarePaginator<int, array<string, mixed>>
     */
    public function paginateForWeb(Company $owner, array $filters = [], ?int $perPage = null): LengthAwarePaginator
    {
        $search = trim((string) ($filters['search'] ?? ''));
        $formTypeId = trim((string) ($filters['form_type_id'] ?? ''));
        $ownerType = trim((string) ($filters['owner_type'] ?? ''));
        $perPage ??= ListQuery::perPage($filters);
        [$sort, $direction] = ListQuery::sort($filters, ['id', 'name', 'created_at'], 'id');
        $direction = $direction === 'asc' ? 'asc' : 'desc';

        return $this->scopedQuery($owner)
            ->when($search !== '', function (Builder $query) use ($search): void {
                $query->where('name', 'like', "%{$search}%");
            })
            ->when($formTypeId !== '', fn (Builder $q) => $q->where('form_type_id', (int) $formTypeId))
            ->when($ownerType !== '', fn (Builder $q) => $q->where('owner_type', $ownerType))
            ->orderBy($sort, $direction)
            ->paginate($perPage)
            ->withQueryString()
            ->through(fn (FormTemplate $template): array => $this->toListItem($template));
    }

    /**
     * @param  array{
     *     name: string,
     *     form_type_id: int,
     *     language_id?: int|null,
     *     is_default?: bool,
     *     work_order_type_id?: int|null,
     *     owner_type: string,
     *     brand_id?: int|null,
     *     company_relationship_id?: int|null,
     *     establishment_id?: int|null,
     *     establishment_ids?: list<int>,
     *     sections?: list<array<string, mixed>>
     * }  $data
     */
    public function create(Company $owner, array $data): FormTemplate
    {
        return DB::transaction(function () use ($owner, $data): FormTemplate {
            $template = FormTemplate::query()->create([
                'company_id' => $owner->id,
                ...$this->headerAttributes($data),
            ]);
            $this->syncEstablishments($template, $data['establishment_ids'] ?? []);
            $this->syncSections($template, $data['sections'] ?? []);
            $this->unsetSiblingDefaults($template);

            return $template->fresh(['sections.fields', 'type', 'language', 'workOrderType', 'brand', 'companyRelationship.relatedCompany', 'establishment', 'establishments'])
                ?? $template;
        });
    }

    /**
     * @param  array{
     *     name: string,
     *     form_type_id: int,
     *     language_id?: int|null,
     *     is_default?: bool,
     *     work_order_type_id?: int|null,
     *     owner_type: string,
     *     brand_id?: int|null,
     *     company_relationship_id?: int|null,
     *     establishment_id?: int|null,
     *     establishment_ids?: list<int>,
     *     sections?: list<array<string, mixed>>
     * }  $data
     */
    public function update(FormTemplate $template, array $data): FormTemplate
    {
        return DB::transaction(function () use ($template, $data): FormTemplate {
            $template->update($this->headerAttributes($data));
            $this->syncEstablishments($template, $data['establishment_ids'] ?? []);
            $this->syncSections($template, $data['sections'] ?? []);
            $this->unsetSiblingDefaults($template);

            return $template->fresh(['sections.fields', 'type', 'language', 'workOrderType', 'brand', 'companyRelationship.relatedCompany', 'establishment', 'establishments'])
                ?? $template;
        });
    }

    /**
     * Duplicate a template together with its sections and fields. The copy always
     * starts out as a non-default draft so it can never silently steal the
     * "default template" slot from the one it was cloned from.
     */
    public function duplicate(FormTemplate $template): FormTemplate
    {
        return DB::transaction(function () use ($template): FormTemplate {
            $template->loadMissing(['sections.fields', 'establishments:id']);

            $copy = FormTemplate::query()->create([
                'company_id' => $template->company_id,
                'name' => $this->duplicateName($template),
                'form_type_id' => $template->form_type_id,
                'language_id' => $template->language_id,
                'is_default' => false,
                'work_order_type_id' => $template->work_order_type_id,
                'owner_type' => $template->owner_type,
                'brand_id' => $template->brand_id,
                'company_relationship_id' => $template->company_relationship_id,
                'establishment_id' => $template->establishment_id,
            ]);

            $this->syncEstablishments($copy, $template->establishments->pluck('id')->map(fn ($id) => (int) $id)->all());

            foreach ($template->sections as $section) {
                $newSection = FormTemplateSection::query()->create([
                    'form_template_id' => $copy->id,
                    'sort_order' => $section->sort_order,
                    'label' => $section->label,
                    'is_repeatable' => $section->is_repeatable,
                    'is_modal' => $section->is_modal,
                    'is_visible' => $section->is_visible,
                ]);

                // Conditional fields can only ever point at a field that already
                // existed before this save (see StoreFormTemplateRequest), so a
                // fresh clone intentionally drops the link rather than reattaching
                // it to the wrong template's copy.
                foreach ($section->fields as $field) {
                    FormTemplateField::query()->create([
                        'form_template_section_id' => $newSection->id,
                        'sort_order' => $field->sort_order,
                        'type' => $field->type,
                        'label' => $field->label,
                        'default_value' => $field->default_value,
                        'is_required' => $field->is_required,
                        'is_repeatable' => $field->is_repeatable,
                        'is_visible' => $field->is_visible,
                        'is_locked' => $field->is_locked,
                        'payload' => $field->payload,
                    ]);
                }
            }

            return $copy->fresh(['sections.fields', 'type', 'language', 'workOrderType', 'brand', 'companyRelationship.relatedCompany', 'establishment', 'establishments'])
                ?? $copy;
        });
    }

    public function delete(FormTemplate $template): void
    {
        if ($template->trashed()) {
            return;
        }

        DB::transaction(fn () => $template->delete());
    }

    /**
     * @return array<string, mixed>
     */
    public function toFormData(FormTemplate $template): array
    {
        $template->loadMissing([
            'sections.fields',
            'establishments:id',
            'type',
            'language',
            'workOrderType',
            'brand',
            'companyRelationship.relatedCompany',
            'establishment',
        ]);

        return [
            'id' => $template->id,
            'company_id' => $template->company_id,
            'name' => $template->name,
            'form_type_id' => $template->form_type_id,
            'language_id' => $template->language_id,
            'is_default' => $template->is_default,
            'work_order_type_id' => $template->work_order_type_id,
            'owner_type' => $template->owner_type->value,
            'brand_id' => $template->brand_id,
            'company_relationship_id' => $template->company_relationship_id,
            'establishment_id' => $template->establishment_id,
            'owner_label' => $template->ownerLabel(),
            'type_name' => $template->type?->name,
            'establishment_ids' => $template->establishments->pluck('id')->map(fn ($id) => (int) $id)->values()->all(),
            'sections' => $template->sections->map(fn (FormTemplateSection $section): array => [
                'id' => $section->id,
                'sort_order' => $section->sort_order,
                'label' => $section->label,
                'is_repeatable' => $section->is_repeatable,
                'is_modal' => $section->is_modal,
                'is_visible' => $section->is_visible,
                'fields' => $section->fields->map(fn (FormTemplateField $field): array => [
                    'id' => $field->id,
                    'sort_order' => $field->sort_order,
                    'type' => $field->type,
                    'label' => $field->label,
                    'default_value' => $field->default_value,
                    'is_required' => $field->is_required,
                    'is_repeatable' => $field->is_repeatable,
                    'is_visible' => $field->is_visible,
                    'is_locked' => $field->is_locked,
                    'conditional_field_id' => $field->conditional_field_id,
                    'payload' => $field->payload,
                ])->values()->all(),
            ])->values()->all(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function toListItem(FormTemplate $template): array
    {
        $template->loadMissing(['type', 'brand', 'companyRelationship.relatedCompany', 'establishment']);

        return [
            'id' => $template->id,
            'name' => $template->name,
            'type_name' => $template->type?->name,
            'owner_type' => $template->owner_type->value,
            'owner_label' => $template->ownerLabel(),
            'is_default' => $template->is_default,
            'created_at' => $template->created_at?->timezone(config('app.timezone'))->format('d/m/Y H:i'),
        ];
    }

    /**
     * @return list<array{id: int, label: string}>
     */
    public function typeOptions(): array
    {
        return FormType::query()->orderBy('name')->get(['id', 'name'])
            ->map(fn (FormType $type): array => ['id' => $type->id, 'label' => $type->name])
            ->values()->all();
    }

    /**
     * @return list<array{id: int, label: string}>
     */
    public function languageOptions(): array
    {
        return Language::query()->orderBy('name')->get(['id', 'name'])
            ->map(fn (Language $language): array => ['id' => $language->id, 'label' => $language->name])
            ->values()->all();
    }

    /**
     * @return list<array{id: int, label: string}>
     */
    public function workOrderTypeOptions(): array
    {
        return WorkOrderType::query()->orderBy('name')->get(['id', 'name'])
            ->map(fn (WorkOrderType $type): array => ['id' => $type->id, 'label' => $type->name])
            ->values()->all();
    }

    /**
     * @return list<array{id: int, label: string}>
     */
    public function brandOptions(): array
    {
        return Brand::query()->orderBy('name')->limit(500)->get(['id', 'name'])
            ->map(fn (Brand $brand): array => ['id' => $brand->id, 'label' => $brand->name])
            ->values()->all();
    }

    /**
     * @return list<array{id: int, label: string}>
     */
    /**
     * @return list<array{id: int, label: string, logo_url: string|null}>
     */
    public function customerOptions(?Company $owner = null, ?int $includeId = null): array
    {
        $query = CompanyRelationship::query()
            ->with('relatedCompany:id,name,tradename,logo,is_active')
            ->where('kind', CompanyRelationshipKind::Customer->value)
            ->where(function ($inner) use ($includeId): void {
                $inner->whereHas('relatedCompany', fn ($company) => $company->where('is_active', true));

                if ($includeId !== null) {
                    $inner->orWhereKey($includeId);
                }
            });

        if ($owner !== null) {
            $query->where('owner_company_id', $owner->id);
        }

        return $query->limit(500)->get()
            ->map(fn (CompanyRelationship $rel): array => $rel->toSelectOption(
                (string) ($rel->relatedCompany?->name ?? $rel->relatedCompany?->tradename ?? '#'.$rel->id),
            ))
            ->values()->all();
    }

    /**
     * @param  list<int>  $includeIds
     * @return list<array{id: int, label: string}>
     */
    public function establishmentOptions(?Company $owner = null, array $includeIds = []): array
    {
        $query = Establishment::query()->orderBy('name');

        if ($owner !== null) {
            $companyIds = $owner->ownedRelationships()
                ->where('kind', CompanyRelationshipKind::Customer->value)
                ->pluck('related_company_id');
            $query->whereIn('company_id', $companyIds);
        }

        $includeIds = array_values(array_unique(array_filter(
            array_map('intval', $includeIds),
            fn (int $id): bool => $id > 0,
        )));

        $query->where(function ($inner) use ($includeIds): void {
            $inner->where('is_active', true);

            if ($includeIds !== []) {
                $inner->orWhereIn('id', $includeIds);
            }
        });

        return $query->limit(500)->get(['id', 'name'])
            ->map(fn (Establishment $establishment): array => [
                'id' => $establishment->id,
                'label' => $establishment->name,
            ])
            ->values()->all();
    }

    /**
     * @return list<array{id: int, label: string}>
     */
    public function options(Company $owner): array
    {
        return $this->scopedQuery($owner)
            ->orderBy('name')
            ->get(['id', 'name'])
            ->map(fn (FormTemplate $template): array => ['id' => $template->id, 'label' => $template->name])
            ->values()->all();
    }

    public function canAccess(Company $owner, FormTemplate $template): bool
    {
        return (int) $template->company_id === (int) $owner->id;
    }

    /**
     * Rank the templates eligible for a work order instead of handing back every
     * template the company owns. A template explicitly linked (via
     * establishment_form_template) to other establishments but not this one is
     * dropped entirely; everything else is ordered best match first:
     *
     *   0. linked to this establishment for this exact work order type
     *   1. linked to this establishment for any work order type
     *   2. not linked to any establishment (i.e. usable everywhere)
     *   — tie-broken by the template's own `is_default` flag, then name.
     *
     * @return list<array{id: int, label: string, is_default: bool}>
     */
    public function resolveForWorkOrder(Company $owner, WorkOrder $workOrder, ?int $formTypeId = null): array
    {
        $workOrder->loadMissing('establishment');
        $establishmentId = $workOrder->establishment_id;
        $languageId = $workOrder->establishment?->language_id;
        $workOrderTypeId = $workOrder->work_order_type_id;

        return $this->scopedQuery($owner)
            ->when($formTypeId !== null, fn (Builder $q) => $q->where('form_type_id', $formTypeId))
            ->where(function (Builder $q) use ($languageId): void {
                $q->whereNull('language_id');

                if ($languageId !== null) {
                    $q->orWhere('language_id', $languageId);
                }
            })
            ->with('establishments')
            ->get()
            ->map(function (FormTemplate $template) use ($establishmentId, $workOrderTypeId): ?array {
                $rank = $this->establishmentMatchRank($template, $establishmentId, $workOrderTypeId);

                return $rank === null ? null : ['template' => $template, 'rank' => $rank];
            })
            ->filter()
            ->sort(fn (array $a, array $b): int => $a['rank'] <=> $b['rank']
                ?: ($b['template']->is_default <=> $a['template']->is_default)
                ?: strcasecmp($a['template']->name, $b['template']->name))
            ->values()
            ->map(fn (array $row): array => [
                'id' => $row['template']->id,
                'label' => $row['template']->name,
                'is_default' => $row['template']->is_default,
            ])
            ->all();
    }

    /**
     * Null means "not eligible for this work order at all" (the template is
     * scoped to a set of establishments that doesn't include this one).
     */
    private function establishmentMatchRank(FormTemplate $template, ?int $establishmentId, ?int $workOrderTypeId): ?int
    {
        $links = $template->establishments;

        if ($links->isEmpty()) {
            return 2;
        }

        $match = $establishmentId !== null ? $links->firstWhere('id', $establishmentId) : null;

        if ($match === null) {
            return null;
        }

        $pivotWorkOrderTypeId = $match->pivot->work_order_type_id ?? null;

        if ($pivotWorkOrderTypeId !== null && $workOrderTypeId !== null && (int) $pivotWorkOrderTypeId === (int) $workOrderTypeId) {
            return 0;
        }

        return 1;
    }

    /**
     * @return Builder<FormTemplate>
     */
    private function scopedQuery(Company $owner): Builder
    {
        return FormTemplate::query()
            ->where('company_id', $owner->id)
            ->with([
                'type:id,name',
                'brand:id,name',
                'companyRelationship.relatedCompany:id,name,tradename,logo',
                'establishment:id,name',
            ]);
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function headerAttributes(array $data): array
    {
        $ownerType = FormTemplateOwnerType::from((string) $data['owner_type']);

        return [
            'name' => $data['name'],
            'form_type_id' => $data['form_type_id'],
            'language_id' => $data['language_id'] ?? null,
            'is_default' => (bool) ($data['is_default'] ?? false),
            'work_order_type_id' => $data['work_order_type_id'] ?? null,
            'owner_type' => $ownerType,
            'brand_id' => $ownerType === FormTemplateOwnerType::Brand ? ($data['brand_id'] ?? null) : null,
            'company_relationship_id' => $ownerType === FormTemplateOwnerType::Customer
                ? ($data['company_relationship_id'] ?? null)
                : null,
            'establishment_id' => $ownerType === FormTemplateOwnerType::Establishment
                ? ($data['establishment_id'] ?? null)
                : null,
        ];
    }

    /**
     * When a template is flagged as the default for its form type + language,
     * every other template sharing that (form_type_id, language_id) pair within
     * the same company stops being the default.
     */
    private function unsetSiblingDefaults(FormTemplate $template): void
    {
        if (! $template->is_default) {
            return;
        }

        FormTemplate::query()
            ->where('company_id', $template->company_id)
            ->where('form_type_id', $template->form_type_id)
            ->where(function (Builder $query) use ($template): void {
                if ($template->language_id === null) {
                    $query->whereNull('language_id');

                    return;
                }

                $query->where('language_id', $template->language_id);
            })
            ->whereKeyNot($template->id)
            ->update(['is_default' => false]);
    }

    private function duplicateName(FormTemplate $template): string
    {
        $base = $template->name.' (copy)';
        $name = $base;
        $suffix = 2;

        while (FormTemplate::query()->where('company_id', $template->company_id)->where('name', $name)->exists()) {
            $name = $base.' '.$suffix;
            $suffix++;
        }

        return $name;
    }

    /**
     * @param  list<int>  $establishmentIds
     */
    private function syncEstablishments(FormTemplate $template, array $establishmentIds): void
    {
        $template->establishments()->sync(array_values(array_unique(array_map('intval', $establishmentIds))));
    }

    /**
     * @param  list<array<string, mixed>>  $sections
     */
    private function syncSections(FormTemplate $template, array $sections): void
    {
        $keptSectionIds = [];

        foreach (array_values($sections) as $index => $sectionData) {
            $sectionId = isset($sectionData['id']) && is_numeric($sectionData['id'])
                ? (int) $sectionData['id']
                : null;

            $attributes = [
                'form_template_id' => $template->id,
                'sort_order' => (int) ($sectionData['sort_order'] ?? $index),
                'label' => $sectionData['label'] ?? null,
                'is_repeatable' => (bool) ($sectionData['is_repeatable'] ?? false),
                'is_modal' => (bool) ($sectionData['is_modal'] ?? false),
                'is_visible' => array_key_exists('is_visible', $sectionData)
                    ? (bool) $sectionData['is_visible']
                    : true,
            ];

            if ($sectionId !== null) {
                $section = FormTemplateSection::query()
                    ->where('form_template_id', $template->id)
                    ->whereKey($sectionId)
                    ->first();

                if ($section !== null) {
                    $section->update($attributes);
                } else {
                    $section = FormTemplateSection::query()->create($attributes);
                }
            } else {
                $section = FormTemplateSection::query()->create($attributes);
            }

            $keptSectionIds[] = $section->id;
            $this->syncFields($section, $sectionData['fields'] ?? []);
        }

        FormTemplateSection::query()
            ->where('form_template_id', $template->id)
            ->when($keptSectionIds !== [], fn (Builder $q) => $q->whereNotIn('id', $keptSectionIds))
            ->when($keptSectionIds === [], fn (Builder $q) => $q)
            ->get()
            ->each(function (FormTemplateSection $section): void {
                $section->fields()->delete();
                $section->delete();
            });
    }

    /**
     * @param  list<array<string, mixed>>  $fields
     */
    private function syncFields(FormTemplateSection $section, array $fields): void
    {
        $keptFieldIds = [];

        foreach (array_values($fields) as $index => $fieldData) {
            $fieldId = isset($fieldData['id']) && is_numeric($fieldData['id'])
                ? (int) $fieldData['id']
                : null;

            $attributes = [
                'form_template_section_id' => $section->id,
                'sort_order' => (int) ($fieldData['sort_order'] ?? $index),
                'type' => (string) ($fieldData['type'] ?? 'texto'),
                'label' => $fieldData['label'] ?? null,
                'default_value' => $fieldData['default_value'] ?? null,
                'is_required' => (bool) ($fieldData['is_required'] ?? false),
                'is_repeatable' => (bool) ($fieldData['is_repeatable'] ?? false),
                'is_visible' => array_key_exists('is_visible', $fieldData)
                    ? (bool) $fieldData['is_visible']
                    : true,
                'is_locked' => (bool) ($fieldData['is_locked'] ?? false),
                'conditional_field_id' => isset($fieldData['conditional_field_id']) && is_numeric($fieldData['conditional_field_id'])
                    ? (int) $fieldData['conditional_field_id']
                    : null,
                'payload' => $fieldData['payload'] ?? null,
            ];

            if ($fieldId !== null) {
                $field = FormTemplateField::query()
                    ->where('form_template_section_id', $section->id)
                    ->whereKey($fieldId)
                    ->first();

                if ($field !== null) {
                    $field->update($attributes);
                } else {
                    $field = FormTemplateField::query()->create($attributes);
                }
            } else {
                $field = FormTemplateField::query()->create($attributes);
            }

            $keptFieldIds[] = $field->id;
        }

        FormTemplateField::query()
            ->where('form_template_section_id', $section->id)
            ->when($keptFieldIds !== [], fn (Builder $q) => $q->whereNotIn('id', $keptFieldIds))
            ->get()
            ->each(fn (FormTemplateField $field) => $field->delete());
    }
}
