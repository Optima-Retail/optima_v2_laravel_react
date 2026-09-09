import type { FormEvent, ReactNode } from 'react';
import { useTranslation } from 'react-i18next';
import { Button } from '@/components/ui/Button';
import { Field } from '@/components/ui/Field';
import { Input } from '@/components/ui/Input';
import { Toggle } from '@/components/ui/Toggle';
import { FieldHelpScope } from '@/components/field-help/FieldHelpScope';

export type IncidentStatusFormValues = {
    name: string;
    color: string;
    lifecycle: number | string;
    is_open: boolean;
};

type IncidentStatusFormProps = {
    mode: 'create' | 'edit';
    values: IncidentStatusFormValues;
    errors: Partial<Record<keyof IncidentStatusFormValues, string>>;
    processing: boolean;
    onChange: (key: keyof IncidentStatusFormValues, value: string | boolean) => void;
    onSubmit: (event: FormEvent) => void;
    submitLabel: string;
    submitIcon?: ReactNode;
    actions?: ReactNode;
};

export function IncidentStatusForm({
    values,
    errors,
    processing,
    onChange,
    onSubmit,
    submitLabel,
    submitIcon,
    actions,
}: IncidentStatusFormProps) {
    const { t } = useTranslation();

    return (
        <FieldHelpScope table="incident_statuses">
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
                            value={values.color || '#a9cef0'}
                            onChange={(event) => onChange('color', event.target.value)}
                            className="size-8 cursor-pointer rounded-lg border border-line bg-surface p-0.5"
                        />
                        <Input
                            value={values.color}
                            placeholder={t('incidentStatuses.colorPlaceholder')}
                            invalid={Boolean(errors.color)}
                            onChange={(event) => onChange('color', event.target.value)}
                            className="flex-1"
                        />
                    </div>
                </Field>

                <Field label={t('incidentStatuses.lifecycle')} htmlFor="lifecycle" error={errors.lifecycle}>
                    <Input
                        id="lifecycle"
                        type="number"
                        min={0}
                        max={255}
                        value={values.lifecycle === '' || values.lifecycle === null ? '' : String(values.lifecycle)}
                        invalid={Boolean(errors.lifecycle)}
                        onChange={(event) => onChange('lifecycle', event.target.value)}
                    />
                    <p className="text-xs text-ink-muted">{t('incidentStatuses.lifecycleHint')}</p>
                </Field>

                <div className="space-y-1">
                    <p className="text-sm font-semibold text-ink">{t('incidentStatuses.isOpen')}</p>
                    <p className="text-xs text-ink-muted">{t('incidentStatuses.isOpenHint')}</p>
                    <Toggle
                        name="is_open"
                        checked={values.is_open}
                        onCheckedChange={(checked) => onChange('is_open', checked)}
                        checkedLabel={t('incidentStatuses.isOpen')}
                        uncheckedLabel={t('incidentStatuses.isOpenOff')}
                    />
                    {errors.is_open ? <p className="text-sm text-danger">{errors.is_open}</p> : null}
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
