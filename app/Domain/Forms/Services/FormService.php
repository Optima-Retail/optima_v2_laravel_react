<?php

declare(strict_types=1);

namespace App\Domain\Forms\Services;

use App\Domain\Companies\Enums\CompanyRelationshipKind;
use App\Domain\Companies\Support\ActiveCompany;
use App\Domain\Forms\Enums\FormSubjectType;
use App\Models\Company;
use App\Models\CompanyRelationship;
use App\Models\Form;
use App\Models\FormField;
use App\Models\FormSection;
use App\Models\FormStatus;
use App\Models\FormTemplate;
use App\Models\FormTemplateField;
use App\Models\FormType;
use App\Models\User;
use App\Models\WorkOrder;
use App\Support\ListQuery;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

final class FormService
{
    /**
     * @param  array{
     *     search?: string|null,
     *     form_type_id?: string|null,
     *     form_status_id?: string|null,
     *     subject_type?: string|null,
     *     sort?: string|null,
     *     direction?: string|null,
     *     per_page?: int|string|null
     * }  $filters
     * @return LengthAwarePaginator<int, array<string, mixed>>
     */
    public function paginateForWeb(?Company $owner, array $filters = [], ?int $perPage = null): LengthAwarePaginator
    {
        $search = trim((string) ($filters['search'] ?? ''));
        $formTypeId = trim((string) ($filters['form_type_id'] ?? ''));
        $formStatusId = trim((string) ($filters['form_status_id'] ?? ''));
        $subjectType = trim((string) ($filters['subject_type'] ?? ''));
        $perPage ??= ListQuery::perPage($filters);
        [$sort, $direction] = ListQuery::sort($filters, ['id', 'name', 'created_at', 'occurred_on'], 'id');
        $direction = $direction === 'asc' ? 'asc' : 'desc';

        return $this->scopedQuery($owner)
            ->when($search !== '', function (Builder $query) use ($search): void {
                $query->where(function (Builder $inner) use ($search): void {
                    $inner->where('name', 'like', "%{$search}%")
                        ->orWhere('public_id', 'like', "%{$search}%")
                        ->orWhere('technician_code', 'like', "%{$search}%");
                });
            })
            ->when($formTypeId !== '', fn (Builder $q) => $q->where('form_type_id', (int) $formTypeId))
            ->when($formStatusId !== '', fn (Builder $q) => $q->where('form_status_id', (int) $formStatusId))
            ->when($subjectType !== '', fn (Builder $q) => $q->where('subject_type', $subjectType))
            ->orderBy($sort, $direction)
            ->paginate($perPage)
            ->withQueryString()
            ->through(fn (Form $form): array => $this->toListItem($form));
    }

    /**
     * @param  array{
     *     name?: string|null,
     *     form_template_id?: int|null,
     *     form_type_id?: int|null,
     *     form_status_id?: int|null,
     *     language_id?: int|null,
     *     subject_type: string,
     *     work_order_id?: int|null,
     *     company_relationship_id?: int|null,
     *     occurred_on?: string|null,
     *     app_platform_id?: int|null,
     *     technician_code?: string|null,
     *     sections?: list<array<string, mixed>>
     * }  $data
     */
    public function create(User $user, array $data): Form
    {
        return DB::transaction(function () use ($user, $data): Form {
            $templateId = isset($data['form_template_id']) ? (int) $data['form_template_id'] : null;
            $template = $templateId
                ? FormTemplate::query()->with('sections.fields')->findOrFail($templateId)
                : null;

            if ($template !== null) {
                $owner = app(ActiveCompany::class)->forUser($user);
                abort_if(
                    $owner === null || (int) $template->company_id !== (int) $owner->id,
                    403,
                );
            }

            $form = Form::query()->create([
                'public_id' => Str::lower(Str::random(16)),
                'name' => $data['name'] ?? $template?->name,
                'form_type_id' => $data['form_type_id'] ?? $template?->form_type_id,
                'form_status_id' => $data['form_status_id'] ?? FormStatus::query()->orderBy('id')->value('id'),
                'language_id' => $data['language_id'] ?? $template?->language_id,
                'user_id' => $user->id,
                'form_template_id' => $template?->id,
                ...$this->subjectAttributes($data),
                'occurred_on' => $data['occurred_on'] ?? now()->toDateString(),
                'app_platform_id' => (int) ($data['app_platform_id'] ?? 1),
                'technician_code' => $data['technician_code'] ?? null,
                'was_edited' => false,
            ]);

            if ($template !== null) {
                $this->copyFromTemplate($form, $template);
            } elseif (! empty($data['sections'])) {
                $this->syncSections($form, $data['sections']);
            }

            return $form->fresh(['sections.fields', 'type', 'status', 'template', 'workOrder', 'companyRelationship.relatedCompany', 'user'])
                ?? $form;
        });
    }

    /**
     * @param  array{
     *     name?: string|null,
     *     form_type_id?: int|null,
     *     form_status_id?: int|null,
     *     language_id?: int|null,
     *     subject_type: string,
     *     work_order_id?: int|null,
     *     company_relationship_id?: int|null,
     *     occurred_on?: string|null,
     *     app_platform_id?: int|null,
     *     technician_code?: string|null,
     *     was_edited?: bool,
     *     sections?: list<array<string, mixed>>
     * }  $data
     */
    public function update(Form $form, array $data): Form
    {
        return DB::transaction(function () use ($form, $data): Form {
            $form->update([
                'name' => $data['name'] ?? $form->name,
                'form_type_id' => $data['form_type_id'] ?? $form->form_type_id,
                'form_status_id' => $data['form_status_id'] ?? $form->form_status_id,
                'language_id' => $data['language_id'] ?? $form->language_id,
                ...$this->subjectAttributes($data),
                'occurred_on' => $data['occurred_on'] ?? $form->occurred_on?->toDateString(),
                'app_platform_id' => (int) ($data['app_platform_id'] ?? $form->app_platform_id),
                'technician_code' => $data['technician_code'] ?? $form->technician_code,
                'was_edited' => (bool) ($data['was_edited'] ?? true),
            ]);

            if (array_key_exists('sections', $data)) {
                $this->syncSections($form, $data['sections'] ?? []);
            }

            return $form->fresh(['sections.fields', 'type', 'status', 'template', 'workOrder', 'companyRelationship.relatedCompany', 'user'])
                ?? $form;
        });
    }

    public function advanceStatus(Form $form): Form
    {
        $form->loadMissing('status');
        $nextId = $form->status?->next_status_id;

        if ($nextId === null) {
            return $form;
        }

        return DB::transaction(function () use ($form, $nextId): Form {
            $form->update([
                'form_status_id' => $nextId,
                'was_edited' => true,
            ]);

            return $form->fresh(['sections.fields', 'type', 'status', 'template', 'workOrder', 'companyRelationship.relatedCompany', 'user'])
                ?? $form;
        });
    }

    public function delete(Form $form): void
    {
        if ($form->trashed()) {
            return;
        }

        DB::transaction(fn () => $form->delete());
    }

    public function canAccess(?Company $owner, Form $form): bool
    {
        if ($owner === null) {
            return false;
        }

        return match ($form->subject_type) {
            FormSubjectType::WorkOrder => $form->work_order_id !== null
                && WorkOrder::query()
                    ->whereKey($form->work_order_id)
                    ->whereHas('establishment', function (Builder $query) use ($owner): void {
                        $companyIds = $owner->ownedRelationships()
                            ->where('kind', CompanyRelationshipKind::Customer->value)
                            ->pluck('related_company_id');
                        $query->whereIn('company_id', $companyIds);
                    })
                    ->exists(),
            FormSubjectType::Technician => $form->company_relationship_id !== null
                && CompanyRelationship::query()
                    ->whereKey($form->company_relationship_id)
                    ->where('owner_company_id', $owner->id)
                    ->where('kind', CompanyRelationshipKind::Technician->value)
                    ->exists(),
            default => false,
        };
    }

/**
     * @return array<string, mixed>
     */
    public function toPublicData(Form $form): array
    {
        $form->loadMissing(['sections.fields', 'type', 'status']);

        return [
            'public_id' => $form->public_id,
            'name' => $form->name,
            'type_name' => $form->type?->name,
            'status_name' => $form->status?->name,
            'occurred_on' => $form->occurred_on?->format('Y-m-d'),
            'sections' => $form->sections
                ->filter(fn (FormSection $section): bool => $section->is_visible)
                ->values()
                ->map(fn (FormSection $section): array => [
                    'id' => $section->id,
                    'label' => $section->label,
                    'fields' => $section->fields
                        ->filter(fn (FormField $field): bool => $field->is_visible)
                        ->values()
                        ->map(fn (FormField $field): array => [
                            'id' => $field->id,
                            'type' => $field->type,
                            'label' => $field->label,
                            'value' => $field->value,
                            'is_required' => $field->is_required,
                        ])->all(),
                ])->all(),
        ];
    }

    public function findPublicByPublicId(string $publicId): Form
    {
        $form = Form::query()
            ->where('public_id', $publicId)
            ->firstOrFail();

        // Legacy: forms still in "Creando" (seeded id 1) are not publicly reachable.
        abort_if((int) $form->form_status_id === 1, 404);

        return $form;
    }

    /**
     * @return array<string, mixed>
     */
    public function toFormData(Form $form): array
    {
        $form->loadMissing([
            'sections.fields',
            'type',
            'status',
            'template',
            'workOrder',
            'companyRelationship.relatedCompany',
            'user',
        ]);

        return [
            'id' => $form->id,
            'public_id' => $form->public_id,
            'name' => $form->name,
            'form_type_id' => $form->form_type_id,
            'form_status_id' => $form->form_status_id,
            'language_id' => $form->language_id,
            'form_template_id' => $form->form_template_id,
            'subject_type' => $form->subject_type->value,
            'work_order_id' => $form->work_order_id,
            'company_relationship_id' => $form->company_relationship_id,
            'occurred_on' => $form->occurred_on?->format('Y-m-d'),
            'app_platform_id' => $form->app_platform_id,
            'technician_code' => $form->technician_code,
            'was_edited' => $form->was_edited,
            'subject_label' => $form->subjectLabel(),
            'type_name' => $form->type?->name,
            'status_name' => $form->status?->name,
            'next_status_id' => $form->status?->next_status_id,
            'public_url' => url('/forms/public/'.$form->public_id),
            'sections' => $form->sections->map(fn (FormSection $section): array => [
                'id' => $section->id,
                'sort_order' => $section->sort_order,
                'label' => $section->label,
                'is_repeatable' => $section->is_repeatable,
                'is_modal' => $section->is_modal,
                'is_visible' => $section->is_visible,
                'fields' => $section->fields->map(fn (FormField $field): array => [
                    'id' => $field->id,
                    'sort_order' => $field->sort_order,
                    'type' => $field->type,
                    'label' => $field->label,
                    'value' => $field->value,
                    'placeholder' => $field->placeholder,
                    'is_required' => $field->is_required,
                    'is_visible' => $field->is_visible,
                    'is_locked' => $field->is_locked,
                    'payload' => $field->payload,
                ])->values()->all(),
            ])->values()->all(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function toListItem(Form $form): array
    {
        $form->loadMissing(['type', 'status', 'workOrder', 'companyRelationship.relatedCompany']);

        return [
            'id' => $form->id,
            'public_id' => $form->public_id,
            'name' => $form->name,
            'type_name' => $form->type?->name,
            'status_name' => $form->status?->name,
            'subject_type' => $form->subject_type->value,
            'subject_label' => $form->subjectLabel(),
            'occurred_on' => $form->occurred_on?->format('d/m/Y'),
            'created_at' => $form->created_at?->timezone(config('app.timezone'))->format('d/m/Y H:i'),
            'public_url' => url('/forms/public/'.$form->public_id),
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
    public function statusOptions(): array
    {
        return FormStatus::query()->orderBy('id')->get(['id', 'name'])
            ->map(fn (FormStatus $status): array => ['id' => $status->id, 'label' => $status->name])
            ->values()->all();
    }

    /**
     * @return list<array{id: int, label: string}>
     */
    public function workOrderOptions(Company $owner): array
    {
        $companyIds = $owner->ownedRelationships()
            ->where('kind', CompanyRelationshipKind::Customer->value)
            ->pluck('related_company_id');

        return WorkOrder::query()
            ->whereHas('establishment', fn (Builder $q) => $q->whereIn('company_id', $companyIds))
            ->orderByDesc('id')
            ->limit(300)
            ->get(['id', 'code', 'subject'])
            ->map(fn (WorkOrder $wo): array => [
                'id' => $wo->id,
                'label' => trim(($wo->code ?? '').' — '.($wo->subject ?? '')),
            ])
            ->values()->all();
    }

    /**
     * @return list<array{id: int, label: string}>
     */
    public function technicianOptions(Company $owner): array
    {
        return CompanyRelationship::query()
            ->with('relatedCompany:id,name,tradename')
            ->where('owner_company_id', $owner->id)
            ->where('kind', CompanyRelationshipKind::Technician->value)
            ->limit(300)
            ->get()
            ->map(fn (CompanyRelationship $rel): array => [
                'id' => $rel->id,
                'label' => (string) ($rel->relatedCompany?->name ?? $rel->relatedCompany?->tradename ?? '#'.$rel->id),
            ])
            ->values()->all();
    }

    /**
     * @return Builder<Form>
     */
    private function scopedQuery(?Company $owner): Builder
    {
        $query = Form::query()->with([
            'type:id,name',
            'status:id,name',
            'workOrder:id,code,subject',
            'companyRelationship.relatedCompany:id,name,tradename',
        ]);

        if ($owner === null) {
            return $query->whereRaw('1 = 0');
        }

        $customerCompanyIds = $owner->ownedRelationships()
            ->where('kind', CompanyRelationshipKind::Customer->value)
            ->pluck('related_company_id');
        $technicianRelationshipIds = $owner->ownedRelationships()
            ->where('kind', CompanyRelationshipKind::Technician->value)
            ->pluck('id');

        return $query->where(function (Builder $inner) use ($customerCompanyIds, $technicianRelationshipIds): void {
            $inner->where(function (Builder $wo) use ($customerCompanyIds): void {
                $wo->where('subject_type', FormSubjectType::WorkOrder->value)
                    ->whereHas('workOrder.establishment', fn (Builder $q) => $q->whereIn('company_id', $customerCompanyIds));
            })->orWhere(function (Builder $tech) use ($technicianRelationshipIds): void {
                $tech->where('subject_type', FormSubjectType::Technician->value)
                    ->whereIn('company_relationship_id', $technicianRelationshipIds);
            });
        });
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function subjectAttributes(array $data): array
    {
        $subjectType = FormSubjectType::from((string) $data['subject_type']);

        return [
            'subject_type' => $subjectType,
            'work_order_id' => $subjectType === FormSubjectType::WorkOrder
                ? ($data['work_order_id'] ?? null)
                : null,
            'company_relationship_id' => $subjectType === FormSubjectType::Technician
                ? ($data['company_relationship_id'] ?? null)
                : null,
        ];
    }

    private function copyFromTemplate(Form $form, FormTemplate $template): void
    {
        foreach ($template->sections as $templateSection) {
            $section = FormSection::query()->create([
                'form_id' => $form->id,
                'sort_order' => $templateSection->sort_order,
                'label' => $templateSection->label,
                'is_repeatable' => $templateSection->is_repeatable,
                'is_modal' => $templateSection->is_modal,
                'is_visible' => $templateSection->is_visible,
                'is_cloned' => false,
                'form_template_section_id' => $templateSection->id,
            ]);

            foreach ($templateSection->fields as $templateField) {
                /** @var FormTemplateField $templateField */
                FormField::query()->create([
                    'form_section_id' => $section->id,
                    'sort_order' => $templateField->sort_order,
                    'type' => $templateField->type,
                    'label' => $templateField->label,
                    'value' => $templateField->default_value,
                    'placeholder' => strlen((string) $templateField->default_value) < 255
                        ? $templateField->default_value
                        : null,
                    'is_required' => $templateField->is_required,
                    'is_repeatable' => $templateField->is_repeatable,
                    'is_visible' => $templateField->is_visible,
                    'is_locked' => $templateField->is_locked,
                    'is_cloned' => $templateField->is_cloned,
                    'payload' => $templateField->payload,
                ]);
            }
        }
    }

    /**
     * @param  list<array<string, mixed>>  $sections
     */
    private function syncSections(Form $form, array $sections): void
    {
        $keptSectionIds = [];

        foreach (array_values($sections) as $index => $sectionData) {
            $sectionId = isset($sectionData['id']) && is_numeric($sectionData['id'])
                ? (int) $sectionData['id']
                : null;

            $attributes = [
                'form_id' => $form->id,
                'sort_order' => (int) ($sectionData['sort_order'] ?? $index),
                'label' => $sectionData['label'] ?? null,
                'is_repeatable' => (bool) ($sectionData['is_repeatable'] ?? false),
                'is_modal' => (bool) ($sectionData['is_modal'] ?? false),
                'is_visible' => array_key_exists('is_visible', $sectionData)
                    ? (bool) $sectionData['is_visible']
                    : true,
            ];

            if ($sectionId !== null) {
                $section = FormSection::query()->where('form_id', $form->id)->whereKey($sectionId)->first();
                if ($section !== null) {
                    $section->update($attributes);
                } else {
                    $section = FormSection::query()->create($attributes);
                }
            } else {
                $section = FormSection::query()->create($attributes);
            }

            $keptSectionIds[] = $section->id;
            $this->syncFields($section, $sectionData['fields'] ?? []);
        }

        FormSection::query()
            ->where('form_id', $form->id)
            ->when($keptSectionIds !== [], fn (Builder $q) => $q->whereNotIn('id', $keptSectionIds))
            ->get()
            ->each(function (FormSection $section): void {
                $section->fields()->delete();
                $section->delete();
            });
    }

    /**
     * @param  list<array<string, mixed>>  $fields
     */
    private function syncFields(FormSection $section, array $fields): void
    {
        $keptFieldIds = [];

        foreach (array_values($fields) as $index => $fieldData) {
            $fieldId = isset($fieldData['id']) && is_numeric($fieldData['id'])
                ? (int) $fieldData['id']
                : null;

            $attributes = [
                'form_section_id' => $section->id,
                'sort_order' => (int) ($fieldData['sort_order'] ?? $index),
                'type' => (string) ($fieldData['type'] ?? 'texto'),
                'label' => $fieldData['label'] ?? null,
                'value' => $fieldData['value'] ?? null,
                'placeholder' => $fieldData['placeholder'] ?? null,
                'is_required' => (bool) ($fieldData['is_required'] ?? false),
                'is_visible' => array_key_exists('is_visible', $fieldData)
                    ? (bool) $fieldData['is_visible']
                    : true,
                'is_locked' => (bool) ($fieldData['is_locked'] ?? false),
                'payload' => $fieldData['payload'] ?? null,
            ];

            if ($fieldId !== null) {
                $field = FormField::query()->where('form_section_id', $section->id)->whereKey($fieldId)->first();
                if ($field !== null) {
                    $field->update($attributes);
                } else {
                    $field = FormField::query()->create($attributes);
                }
            } else {
                $field = FormField::query()->create($attributes);
            }

            $keptFieldIds[] = $field->id;
        }

        FormField::query()
            ->where('form_section_id', $section->id)
            ->when($keptFieldIds !== [], fn (Builder $q) => $q->whereNotIn('id', $keptFieldIds))
            ->get()
            ->each(fn (FormField $field) => $field->delete());
    }
}
