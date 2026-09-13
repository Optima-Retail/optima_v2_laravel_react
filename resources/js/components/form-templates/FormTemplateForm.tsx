import type { FormEvent, ReactNode } from 'react';
import { Plus, Trash2 } from 'lucide-react';
import { useTranslation } from 'react-i18next';
import { Button } from '@/components/ui/Button';
import { Field } from '@/components/ui/Field';
import { Input } from '@/components/ui/Input';
import { Select } from '@/components/ui/Select';

export type FormTemplateFieldValues = {
    id?: number;
    sort_order: number;
    type: string;
    label: string;
    default_value: string;
    is_required: boolean;
    is_repeatable: boolean;
    is_visible: boolean;
    is_locked: boolean;
};

export type FormTemplateSectionValues = {
    id?: number;
    sort_order: number;
    label: string;
    is_repeatable: boolean;
    is_modal: boolean;
    is_visible: boolean;
    fields: FormTemplateFieldValues[];
};

export type FormTemplateFormValues = {
    name: string;
    form_type_id: string;
    language_id: string;
    is_default: boolean;
    work_order_type_id: string;
    owner_type: string;
    brand_id: string;
    company_relationship_id: string;
    establishment_id: string;
    form_bible_id: string;
    sections: FormTemplateSectionValues[];
};

type Option = { id: number; label: string };

type FormTemplateFormProps = {
    values: FormTemplateFormValues;
    errors: Partial<Record<string, string>>;
    processing: boolean;
    typeOptions: Option[];
    languageOptions: Option[];
    workOrderTypeOptions: Option[];
    brandOptions: Option[];
    customerOptions: Option[];
    establishmentOptions: Option[];
    bibleOptions: Option[];
    onChange: (key: keyof FormTemplateFormValues, value: FormTemplateFormValues[keyof FormTemplateFormValues]) => void;
    onSubmit: (event: FormEvent) => void;
    submitLabel: string;
    submitIcon?: ReactNode;
    actions?: ReactNode;
};

const FIELD_TYPES = [
    'texto',
    'nota',
    'fecha',
    'fecha-hora',
    'numero',
    'si-no',
    'imagen',
    'firma',
    'materiales',
    'tecnicos',
    'trabajo',
    'condicional',
    'subformulario',
    'cabecera',
    'nueva-incidencia',
];

export function defaultFormTemplateFormValues(
    overrides: Partial<FormTemplateFormValues> = {},
): FormTemplateFormValues {
    return {
        name: '',
        form_type_id: '',
        language_id: '',
        is_default: false,
        work_order_type_id: '',
        owner_type: 'global',
        brand_id: '',
        company_relationship_id: '',
        establishment_id: '',
        form_bible_id: '',
        sections: [],
        ...overrides,
    };
}

export function emptySection(sortOrder = 0): FormTemplateSectionValues {
    return {
        sort_order: sortOrder,
        label: '',
        is_repeatable: false,
        is_modal: false,
        is_visible: true,
        fields: [],
    };
}

export function emptyField(sortOrder = 0): FormTemplateFieldValues {
    return {
        sort_order: sortOrder,
        type: 'texto',
        label: '',
        default_value: '',
        is_required: false,
        is_repeatable: false,
        is_visible: true,
        is_locked: false,
    };
}

export function FormTemplateForm({
    values,
    errors,
    processing,
    typeOptions,
    languageOptions,
    workOrderTypeOptions,
    brandOptions,
    customerOptions,
    establishmentOptions,
    bibleOptions,
    onChange,
    onSubmit,
    submitLabel,
    submitIcon,
    actions,
}: FormTemplateFormProps) {
    const { t } = useTranslation();

    function updateSections(sections: FormTemplateSectionValues[]) {
        onChange('sections', sections);
    }

    return (
        <form onSubmit={onSubmit} className="space-y-5">
            <div className="space-y-5 rounded-2xl border border-line bg-surface p-6 sm:p-8">
                <div className="grid gap-5 sm:grid-cols-2">
                    <Field label={t('common.name')} htmlFor="name" error={errors.name} required className="sm:col-span-2">
                        <Input
                            id="name"
                            value={values.name}
                            invalid={Boolean(errors.name)}
                            onChange={(event) => onChange('name', event.target.value)}
                        />
                    </Field>

                    <Field label={t('formTemplates.type')} htmlFor="form_type_id" error={errors.form_type_id} required>
                        <Select
                            id="form_type_id"
                            value={values.form_type_id}
                            invalid={Boolean(errors.form_type_id)}
                            onChange={(event) => onChange('form_type_id', event.target.value)}
                        >
                            <option value="">{t('common.select')}</option>
                            {typeOptions.map((option) => (
                                <option key={option.id} value={option.id}>
                                    {option.label}
                                </option>
                            ))}
                        </Select>
                    </Field>

                    <Field label={t('formTemplates.language')} htmlFor="language_id" error={errors.language_id}>
                        <Select
                            id="language_id"
                            value={values.language_id}
                            onChange={(event) => onChange('language_id', event.target.value)}
                        >
                            <option value="">{t('common.select')}</option>
                            {languageOptions.map((option) => (
                                <option key={option.id} value={option.id}>
                                    {option.label}
                                </option>
                            ))}
                        </Select>
                    </Field>

                    <Field label={t('formTemplates.workOrderType')} htmlFor="work_order_type_id" error={errors.work_order_type_id}>
                        <Select
                            id="work_order_type_id"
                            value={values.work_order_type_id}
                            onChange={(event) => onChange('work_order_type_id', event.target.value)}
                        >
                            <option value="">{t('common.select')}</option>
                            {workOrderTypeOptions.map((option) => (
                                <option key={option.id} value={option.id}>
                                    {option.label}
                                </option>
                            ))}
                        </Select>
                    </Field>

                    <Field label={t('formTemplates.ownerType')} htmlFor="owner_type" error={errors.owner_type} required>
                        <Select
                            id="owner_type"
                            value={values.owner_type}
                            onChange={(event) => onChange('owner_type', event.target.value)}
                        >
                            <option value="global">{t('formTemplates.ownerTypes.global')}</option>
                            <option value="brand">{t('formTemplates.ownerTypes.brand')}</option>
                            <option value="customer">{t('formTemplates.ownerTypes.customer')}</option>
                            <option value="establishment">{t('formTemplates.ownerTypes.establishment')}</option>
                            <option value="bible">{t('formTemplates.ownerTypes.bible')}</option>
                        </Select>
                    </Field>

                    {values.owner_type === 'brand' ? (
                        <Field label={t('formTemplates.brand')} htmlFor="brand_id" error={errors.brand_id} required>
                            <Select
                                id="brand_id"
                                value={values.brand_id}
                                onChange={(event) => onChange('brand_id', event.target.value)}
                            >
                                <option value="">{t('common.select')}</option>
                                {brandOptions.map((option) => (
                                    <option key={option.id} value={option.id}>
                                        {option.label}
                                    </option>
                                ))}
                            </Select>
                        </Field>
                    ) : null}

                    {values.owner_type === 'customer' ? (
                        <Field
                            label={t('formTemplates.customer')}
                            htmlFor="company_relationship_id"
                            error={errors.company_relationship_id}
                            required
                        >
                            <Select
                                id="company_relationship_id"
                                value={values.company_relationship_id}
                                onChange={(event) => onChange('company_relationship_id', event.target.value)}
                            >
                                <option value="">{t('common.select')}</option>
                                {customerOptions.map((option) => (
                                    <option key={option.id} value={option.id}>
                                        {option.label}
                                    </option>
                                ))}
                            </Select>
                        </Field>
                    ) : null}

                    {values.owner_type === 'establishment' ? (
                        <Field
                            label={t('formTemplates.establishment')}
                            htmlFor="establishment_id"
                            error={errors.establishment_id}
                            required
                        >
                            <Select
                                id="establishment_id"
                                value={values.establishment_id}
                                onChange={(event) => onChange('establishment_id', event.target.value)}
                            >
                                <option value="">{t('common.select')}</option>
                                {establishmentOptions.map((option) => (
                                    <option key={option.id} value={option.id}>
                                        {option.label}
                                    </option>
                                ))}
                            </Select>
                        </Field>
                    ) : null}

                    {values.owner_type === 'bible' ? (
                        <Field label={t('formTemplates.bible')} htmlFor="form_bible_id" error={errors.form_bible_id} required>
                            <Select
                                id="form_bible_id"
                                value={values.form_bible_id}
                                onChange={(event) => onChange('form_bible_id', event.target.value)}
                            >
                                <option value="">{t('common.select')}</option>
                                {bibleOptions.map((option) => (
                                    <option key={option.id} value={option.id}>
                                        {option.label}
                                    </option>
                                ))}
                            </Select>
                        </Field>
                    ) : null}

                    <label className="flex items-center gap-2 text-sm text-ink sm:col-span-2">
                        <input
                            type="checkbox"
                            checked={values.is_default}
                            onChange={(event) => onChange('is_default', event.target.checked)}
                            className="size-4 rounded border-line"
                        />
                        {t('formTemplates.isDefault')}
                    </label>
                </div>
            </div>

            <div className="space-y-4 rounded-2xl border border-line bg-surface p-6 sm:p-8">
                <div className="flex flex-wrap items-center justify-between gap-3">
                    <div>
                        <h2 className="text-base font-semibold text-ink">{t('formTemplates.sectionsTitle')}</h2>
                        <p className="mt-1 text-sm text-ink-muted">{t('formTemplates.sectionsDescription')}</p>
                    </div>
                    <Button
                        type="button"
                        variant="secondary"
                        onClick={() => updateSections([...values.sections, emptySection(values.sections.length)])}
                    >
                        <Plus className="size-4" aria-hidden />
                        {t('formTemplates.addSection')}
                    </Button>
                </div>

                {values.sections.length === 0 ? (
                    <p className="rounded-xl border border-dashed border-line px-4 py-8 text-center text-sm text-ink-muted">
                        {t('formTemplates.sectionsEmpty')}
                    </p>
                ) : (
                    values.sections.map((section, sectionIndex) => (
                        <div key={section.id ?? `section-${sectionIndex}`} className="space-y-4 rounded-xl border border-line p-4">
                            <div className="flex flex-wrap items-start justify-between gap-3">
                                <Field
                                    label={t('formTemplates.sectionLabel')}
                                    htmlFor={`section-label-${sectionIndex}`}
                                    className="min-w-[16rem] flex-1"
                                >
                                    <Input
                                        id={`section-label-${sectionIndex}`}
                                        value={section.label}
                                        onChange={(event) => {
                                            const next = [...values.sections];
                                            next[sectionIndex] = { ...section, label: event.target.value };
                                            updateSections(next);
                                        }}
                                    />
                                </Field>
                                <Button
                                    type="button"
                                    variant="danger"
                                    onClick={() => updateSections(values.sections.filter((_, i) => i !== sectionIndex))}
                                >
                                    <Trash2 className="size-3.5" aria-hidden />
                                    {t('common.delete')}
                                </Button>
                            </div>

                            <div className="flex flex-wrap gap-4 text-sm text-ink">
                                {(
                                    [
                                        ['is_repeatable', t('formTemplates.repeatable')],
                                        ['is_modal', t('formTemplates.modal')],
                                        ['is_visible', t('formTemplates.visible')],
                                    ] as const
                                ).map(([key, label]) => (
                                    <label key={key} className="inline-flex items-center gap-2">
                                        <input
                                            type="checkbox"
                                            checked={section[key]}
                                            onChange={(event) => {
                                                const next = [...values.sections];
                                                next[sectionIndex] = { ...section, [key]: event.target.checked };
                                                updateSections(next);
                                            }}
                                            className="size-4 rounded border-line"
                                        />
                                        {label}
                                    </label>
                                ))}
                            </div>

                            <div className="space-y-3">
                                <div className="flex items-center justify-between gap-2">
                                    <h3 className="text-sm font-semibold text-ink">{t('formTemplates.fieldsTitle')}</h3>
                                    <Button
                                        type="button"
                                        variant="secondary"
                                        onClick={() => {
                                            const next = [...values.sections];
                                            next[sectionIndex] = {
                                                ...section,
                                                fields: [...section.fields, emptyField(section.fields.length)],
                                            };
                                            updateSections(next);
                                        }}
                                    >
                                        <Plus className="size-4" aria-hidden />
                                        {t('formTemplates.addField')}
                                    </Button>
                                </div>

                                {section.fields.map((field, fieldIndex) => (
                                    <div
                                        key={field.id ?? `field-${sectionIndex}-${fieldIndex}`}
                                        className="grid gap-3 rounded-lg border border-line bg-canvas/40 p-3 sm:grid-cols-2"
                                    >
                                        <Field label={t('formTemplates.fieldType')} htmlFor={`field-type-${sectionIndex}-${fieldIndex}`}>
                                            <Select
                                                id={`field-type-${sectionIndex}-${fieldIndex}`}
                                                value={field.type}
                                                onChange={(event) => {
                                                    const next = [...values.sections];
                                                    const fields = [...section.fields];
                                                    fields[fieldIndex] = { ...field, type: event.target.value };
                                                    next[sectionIndex] = { ...section, fields };
                                                    updateSections(next);
                                                }}
                                            >
                                                {FIELD_TYPES.map((type) => (
                                                    <option key={type} value={type}>
                                                        {type}
                                                    </option>
                                                ))}
                                            </Select>
                                        </Field>
                                        <Field label={t('formTemplates.fieldLabel')} htmlFor={`field-label-${sectionIndex}-${fieldIndex}`}>
                                            <Input
                                                id={`field-label-${sectionIndex}-${fieldIndex}`}
                                                value={field.label}
                                                onChange={(event) => {
                                                    const next = [...values.sections];
                                                    const fields = [...section.fields];
                                                    fields[fieldIndex] = { ...field, label: event.target.value };
                                                    next[sectionIndex] = { ...section, fields };
                                                    updateSections(next);
                                                }}
                                            />
                                        </Field>
                                        <Field
                                            label={t('formTemplates.defaultValue')}
                                            htmlFor={`field-default-${sectionIndex}-${fieldIndex}`}
                                            className="sm:col-span-2"
                                        >
                                            <Input
                                                id={`field-default-${sectionIndex}-${fieldIndex}`}
                                                value={field.default_value}
                                                onChange={(event) => {
                                                    const next = [...values.sections];
                                                    const fields = [...section.fields];
                                                    fields[fieldIndex] = { ...field, default_value: event.target.value };
                                                    next[sectionIndex] = { ...section, fields };
                                                    updateSections(next);
                                                }}
                                            />
                                        </Field>
                                        <div className="flex flex-wrap items-center justify-between gap-3 sm:col-span-2">
                                            <div className="flex flex-wrap gap-4 text-sm">
                                                {(
                                                    [
                                                        ['is_required', t('formTemplates.required')],
                                                        ['is_repeatable', t('formTemplates.repeatable')],
                                                        ['is_visible', t('formTemplates.visible')],
                                                        ['is_locked', t('formTemplates.locked')],
                                                    ] as const
                                                ).map(([key, label]) => (
                                                    <label key={key} className="inline-flex items-center gap-2">
                                                        <input
                                                            type="checkbox"
                                                            checked={field[key]}
                                                            onChange={(event) => {
                                                                const next = [...values.sections];
                                                                const fields = [...section.fields];
                                                                fields[fieldIndex] = {
                                                                    ...field,
                                                                    [key]: event.target.checked,
                                                                };
                                                                next[sectionIndex] = { ...section, fields };
                                                                updateSections(next);
                                                            }}
                                                            className="size-4 rounded border-line"
                                                        />
                                                        {label}
                                                    </label>
                                                ))}
                                            </div>
                                            <Button
                                                type="button"
                                                variant="danger"
                                                onClick={() => {
                                                    const next = [...values.sections];
                                                    next[sectionIndex] = {
                                                        ...section,
                                                        fields: section.fields.filter((_, i) => i !== fieldIndex),
                                                    };
                                                    updateSections(next);
                                                }}
                                            >
                                                <Trash2 className="size-3.5" aria-hidden />
                                                {t('common.delete')}
                                            </Button>
                                        </div>
                                    </div>
                                ))}
                            </div>
                        </div>
                    ))
                )}
            </div>

            <div className="flex flex-wrap items-center justify-end gap-2">
                {actions}
                <Button type="submit" loading={processing}>
                    {submitIcon}
                    {submitLabel}
                </Button>
            </div>
        </form>
    );
}
