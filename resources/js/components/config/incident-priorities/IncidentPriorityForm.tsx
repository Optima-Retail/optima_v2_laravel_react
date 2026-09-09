import type { FormEvent, ReactNode } from 'react';
import { useTranslation } from 'react-i18next';
import { Button } from '@/components/ui/Button';
import { Field } from '@/components/ui/Field';
import { Input } from '@/components/ui/Input';
import { FieldHelpScope } from '@/components/field-help/FieldHelpScope';

export type IncidentPriorityFormValues = {
    name: string;
    color: string;
    resolution_time_hours: number | string;
};

type IncidentPriorityFormProps = {
    mode: 'create' | 'edit';
    values: IncidentPriorityFormValues;
    errors: Partial<Record<keyof IncidentPriorityFormValues, string>>;
    processing: boolean;
    onChange: (key: keyof IncidentPriorityFormValues, value: string) => void;
    onSubmit: (event: FormEvent) => void;
    submitLabel: string;
    submitIcon?: ReactNode;
    actions?: ReactNode;
};

export function IncidentPriorityForm({
    values,
    errors,
    processing,
    onChange,
    onSubmit,
    submitLabel,
    submitIcon,
    actions,
}: IncidentPriorityFormProps) {
    const { t } = useTranslation();

    return (
        <FieldHelpScope table="incident_priorities">
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
                            value={values.color || '#FF9999'}
                            onChange={(event) => onChange('color', event.target.value)}
                            className="size-8 cursor-pointer rounded-lg border border-line bg-surface p-0.5"
                        />
                        <Input
                            value={values.color}
                            placeholder={t('incidentPriorities.colorPlaceholder')}
                            invalid={Boolean(errors.color)}
                            onChange={(event) => onChange('color', event.target.value)}
                            className="flex-1"
                        />
                    </div>
                </Field>

                <Field
                    label={t('incidentPriorities.resolutionTimeHours')}
                    htmlFor="resolution_time_hours"
                    error={errors.resolution_time_hours}
                    required
                >
                    <Input
                        id="resolution_time_hours"
                        type="number"
                        min={1}
                        max={8760}
                        value={String(values.resolution_time_hours)}
                        invalid={Boolean(errors.resolution_time_hours)}
                        onChange={(event) => onChange('resolution_time_hours', event.target.value)}
                    />
                    <p className="text-xs text-ink-muted">{t('incidentPriorities.resolutionTimeHint')}</p>
                </Field>

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
