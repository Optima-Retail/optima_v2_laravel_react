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
     *     form_bible_id?: int|null,
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

            return $template->fresh(['sections.fields', 'type', 'language', 'workOrderType', 'brand', 'companyRelationship.relatedCompany', 'establishment', 'bible', 'establishments'])
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
     *     form_bible_id?: int|null,
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

            return $template->fresh(['sections.fields', 'type', 'language', 'workOrderType', 'brand', 'companyRelationship.relatedCompany', 'establishment', 'bible', 'establishments'])
                ?? $template;
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
            'bible',
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
            'form_bible_id' => $template->form_bible_id,
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
                ])->values()->all(),
            ])->values()->all(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function toListItem(FormTemplate $template): array
    {
        $template->loadMissing(['type', 'brand', 'companyRelationship.relatedCompany', 'establishment', 'bible']);

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
    public function customerOptions(?Company $owner = null): array
    {
        $query = CompanyRelationship::query()
            ->with('relatedCompany:id,name,tradename')
            ->where('kind', CompanyRelationshipKind::Customer->value);

        if ($owner !== null) {
            $query->where('owner_company_id', $owner->id);
        }

        return $query->limit(500)->get()
            ->map(fn (CompanyRelationship $rel): array => [
                'id' => $rel->id,
                'label' => (string) ($rel->relatedCompany?->name ?? $rel->relatedCompany?->tradename ?? '#'.$rel->id),
            ])
            ->values()->all();
    }

    /**
     * @return list<array{id: int, label: string}>
     */
    public function establishmentOptions(?Company $owner = null): array
    {
        $query = Establishment::query()->orderBy('name');

        if ($owner !== null) {
            $companyIds = $owner->ownedRelationships()
                ->where('kind', CompanyRelationshipKind::Customer->value)
                ->pluck('related_company_id');
            $query->whereIn('company_id', $companyIds);
        }

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
     * @return Builder<FormTemplate>
     */
    private function scopedQuery(Company $owner): Builder
    {
        return FormTemplate::query()
            ->where('company_id', $owner->id)
            ->with([
                'type:id,name',
                'brand:id,name',
                'companyRelationship.relatedCompany:id,name,tradename',
                'establishment:id,name',
                'bible:id,name',
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
            'form_bible_id' => $ownerType === FormTemplateOwnerType::Bible ? ($data['form_bible_id'] ?? null) : null,
        ];
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
