import type { FormEvent, ReactNode } from 'react';
import { useTranslation } from 'react-i18next';
import { FieldHelpScope } from '@/components/field-help/FieldHelpScope';
import { Button } from '@/components/ui/Button';
import { Field } from '@/components/ui/Field';
import { Input } from '@/components/ui/Input';
import { Select } from '@/components/ui/Select';

export type TechnicianRequestPriorityFormValues = {
    name: string;
    key: string;
    color: string;
};

type TechnicianRequestPriorityFormProps = {
    mode: 'create' | 'edit';
    values: TechnicianRequestPriorityFormValues;
    errors: Partial<Record<keyof TechnicianRequestPriorityFormValues, string>>;
    processing: boolean;
    onChange: (key: keyof TechnicianRequestPriorityFormValues, value: string) => void;
    onSubmit: (event: FormEvent) => void;
    submitLabel: string;
    submitIcon?: ReactNode;
    actions?: ReactNode;
};

const priorityKeys = ['urgent', 'high', 'medium', 'low'] as const;

export function TechnicianRequestPriorityForm({
    values,
    errors,
    processing,
    onChange,
    onSubmit,
    submitLabel,
    submitIcon,
    actions,
}: TechnicianRequestPriorityFormProps) {
    const { t } = useTranslation();

    return (
        <FieldHelpScope table="technician_request_priorities">
            <form onSubmit={onSubmit} className="space-y-5 rounded-2xl border border-line bg-surface p-6 sm:p-8">
                <Field label={t('common.name')} htmlFor="name" error={errors.name} required>
                    <Input
                        id="name"
                        value={values.name}
                        invalid={Boolean(errors.name)}
                        onChange={(event) => onChange('name', event.target.value)}
                    />
                </Field>

                <Field label={t('technicianRequestPriorities.key')} htmlFor="key" error={errors.key} required>
                    <Select
                        id="key"
                        value={values.key}
                        invalid={Boolean(errors.key)}
                        onChange={(event) => onChange('key', event.target.value)}
                    >
                        {priorityKeys.map((key) => (
                            <option key={key} value={key}>
                                {t(`technicianRequestPriorities.keys.${key}`)}
                            </option>
                        ))}
                    </Select>
                    <p className="text-xs text-ink-muted">{t('technicianRequestPriorities.keyHint')}</p>
                </Field>

                <Field label={t('common.color')} htmlFor="color" error={errors.color}>
                    <div className="flex items-center gap-3">
                        <input
                            id="color"
                            type="color"
                            value={values.color || '#FF0000'}
                            onChange={(event) => onChange('color', event.target.value)}
                            className="size-8 cursor-pointer rounded-lg border border-line bg-surface p-0.5"
                        />
                        <Input
                            value={values.color}
                            placeholder={t('technicianRequestPriorities.colorPlaceholder')}
                            invalid={Boolean(errors.color)}
                            onChange={(event) => onChange('color', event.target.value)}
                            className="flex-1"
                        />
                    </div>
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
