import type { FormEvent, ReactNode } from 'react';
import { useTranslation } from 'react-i18next';
import { FieldHelpScope } from '@/components/field-help/FieldHelpScope';
import { Button } from '@/components/ui/Button';
import { Field } from '@/components/ui/Field';
import { Input } from '@/components/ui/Input';
import { SearchableSelect } from '@/components/ui/SearchableSelect';
import { Toggle } from '@/components/ui/Toggle';
import type { UserOption } from '@/support/types/domain/common';

const ORIGIN_KINDS = ['company', 'brand', 'establishment'] as const;

export type IncidentTypeFormValues = {
    name: string;
    color: string;
    default_priority_id: string;
    origin_selectable: boolean;
    origin_options: string[];
    default_origin_type: string;
    origin_required: boolean;
    related_type: string;
    show_related: boolean;
};

type IncidentTypeFormProps = {
    mode: 'create' | 'edit';
    values: IncidentTypeFormValues;
    errors: Partial<Record<keyof IncidentTypeFormValues, string>>;
    processing: boolean;
    incidentPriorityOptions: UserOption[];
    onChange: (key: keyof IncidentTypeFormValues, value: string | boolean | string[]) => void;
    onSubmit: (event: FormEvent) => void;
    submitLabel: string;
    submitIcon?: ReactNode;
    actions?: ReactNode;
};

export function defaultIncidentTypeFormValues(
    overrides: Partial<IncidentTypeFormValues> = {},
): IncidentTypeFormValues {
    return {
        name: '',
        color: '#FFFFFF',
        default_priority_id: '2',
        origin_selectable: false,
        origin_options: ['establishment'],
        default_origin_type: 'establishment',
        origin_required: true,
        related_type: '',
        show_related: false,
        ...overrides,
    };
}

export function IncidentTypeForm({
    values,
    errors,
    processing,
    incidentPriorityOptions,
    onChange,
    onSubmit,
    submitLabel,
    submitIcon,
    actions,
}: IncidentTypeFormProps) {
    const { t } = useTranslation();

    function toggleOriginOption(kind: string) {
        const current = values.origin_options;
        const next = current.includes(kind)
            ? current.filter((item) => item !== kind)
            : [...current, kind];

        onChange('origin_options', next);

        if (values.default_origin_type && !next.includes(values.default_origin_type)) {
            onChange('default_origin_type', '');
        }
    }

    return (
        <FieldHelpScope table="incident_types">
            <form onSubmit={onSubmit} className="space-y-5 rounded-2xl border border-line bg-surface p-6 sm:p-8">
                <Field label={t('common.name')} htmlFor="name" error={errors.name} required>
                    <Input
                        id="name"
                        value={values.name}
                        invalid={Boolean(errors.name)}
                        onChange={(event) => onChange('name', event.target.value)}
                    />
                </Field>

                <Field label={t('common.color')} htmlFor="color" error={errors.color}>
                    <div className="flex items-center gap-3">
                        <input
                            id="color"
                            type="color"
                            value={values.color || '#FFFFFF'}
                            onChange={(event) => onChange('color', event.target.value)}
                            className="size-8 cursor-pointer rounded-lg border border-line bg-surface p-0.5"
                        />
                        <Input
                            value={values.color}
                            placeholder={t('incidentTypes.colorPlaceholder')}
                            invalid={Boolean(errors.color)}
                            onChange={(event) => onChange('color', event.target.value)}
                            className="flex-1"
                        />
                    </div>
                </Field>

                <Field
                    label={t('incidentTypes.defaultPriority')}
                    htmlFor="default_priority_id"
                    error={errors.default_priority_id}
                >
                    <SearchableSelect
                        id="default_priority_id"
                        value={values.default_priority_id}
                        invalid={Boolean(errors.default_priority_id)}
                        onChange={(value) => onChange('default_priority_id', value)}
                        emptyLabel={t('common.select')}
                        options={incidentPriorityOptions.map((option) => ({
                            value: String(option.id),
                            label: option.label,
                            color: option.color ?? null,
                        }))}
                    />
                </Field>

                <div className="space-y-4 rounded-xl border border-line p-4">
                    <p className="text-sm font-semibold text-ink">{t('incidentTypes.formBehaviour')}</p>
                    <p className="text-xs text-ink-muted">{t('incidentTypes.formBehaviourHint')}</p>

                    <div className="space-y-1">
                        <Toggle
                            name="origin_required"
                            checked={values.origin_required}
                            onCheckedChange={(checked) => onChange('origin_required', checked)}
                            checkedLabel={t('incidentTypes.originRequired')}
                            uncheckedLabel={t('incidentTypes.originOptional')}
                        />
                        {errors.origin_required ? (
                            <p className="text-sm text-danger">{errors.origin_required}</p>
                        ) : null}
                    </div>

                    <div className="space-y-1">
                        <Toggle
                            name="origin_selectable"
                            checked={values.origin_selectable}
                            onCheckedChange={(checked) => onChange('origin_selectable', checked)}
                            checkedLabel={t('incidentTypes.originSelectable')}
                            uncheckedLabel={t('incidentTypes.originFixed')}
                        />
                        {errors.origin_selectable ? (
                            <p className="text-sm text-danger">{errors.origin_selectable}</p>
                        ) : null}
                    </div>

                    <Field label={t('incidentTypes.originOptions')} error={errors.origin_options}>
                        <div className="flex flex-wrap gap-3">
                            {ORIGIN_KINDS.map((kind) => (
                                <label key={kind} className="inline-flex items-center gap-2 text-sm text-ink">
                                    <input
                                        type="checkbox"
                                        checked={values.origin_options.includes(kind)}
                                        onChange={() => toggleOriginOption(kind)}
                                        className="size-4 rounded border-line"
                                    />
                                    {t(`incidents.originKinds.${kind}`)}
                                </label>
                            ))}
                        </div>
                    </Field>

                    <Field
                        label={t('incidentTypes.defaultOriginType')}
                        htmlFor="default_origin_type"
                        error={errors.default_origin_type}
                    >
                        <SearchableSelect
                            id="default_origin_type"
                            value={values.default_origin_type}
                            invalid={Boolean(errors.default_origin_type)}
                            onChange={(value) => onChange('default_origin_type', value)}
                            emptyLabel={t('common.select')}
                            options={values.origin_options.map((kind) => ({
                                value: kind,
                                label: t(`incidents.originKinds.${kind}`),
                            }))}
                        />
                    </Field>

                    <div className="space-y-1">
                        <Toggle
                            name="show_related"
                            checked={values.show_related}
                            onCheckedChange={(checked) => {
                                onChange('show_related', checked);
                                if (!checked) {
                                    onChange('related_type', '');
                                } else if (!values.related_type) {
                                    onChange('related_type', 'evaluation');
                                }
                            }}
                            checkedLabel={t('incidentTypes.showRelated')}
                            uncheckedLabel={t('incidentTypes.hideRelated')}
                        />
                        {errors.show_related ? (
                            <p className="text-sm text-danger">{errors.show_related}</p>
                        ) : null}
                    </div>

                    {values.show_related ? (
                        <Field
                            label={t('incidentTypes.relatedType')}
                            htmlFor="related_type"
                            error={errors.related_type}
                        >
                            <SearchableSelect
                                id="related_type"
                                value={values.related_type}
                                invalid={Boolean(errors.related_type)}
                                onChange={(value) => onChange('related_type', value)}
                                emptyLabel={t('common.select')}
                                options={[
                                    {
                                        value: 'evaluation',
                                        label: t('incidents.evaluation'),
                                    },
                                ]}
                            />
                        </Field>
                    ) : null}
                </div>

                <div className="flex flex-wrap items-center justify-end gap-2 border-t border-line pt-4">
                    {actions}
                    <Button type="submit" loading={processing}>
                        {submitIcon}
                        {submitLabel}
                    </Button>
                </div>
            </form>
        </FieldHelpScope>
    );
}
