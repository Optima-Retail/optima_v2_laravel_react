import type { FormEvent, ReactNode } from 'react';
import { useTranslation } from 'react-i18next';
import { Button } from '@/components/ui/Button';
import { Field } from '@/components/ui/Field';
import { Input } from '@/components/ui/Input';
import { FieldHelpScope } from '@/components/field-help/FieldHelpScope';

export type EstablishmentTypeFormValues = {
    name: string;
    code: string;
    health_and_safety_delay_days: string;
};

type EstablishmentTypeFormProps = {
    mode: 'create' | 'edit';
    values: EstablishmentTypeFormValues;
    errors: Partial<Record<keyof EstablishmentTypeFormValues, string>>;
    processing: boolean;
    onChange: (key: keyof EstablishmentTypeFormValues, value: string) => void;
    onSubmit: (event: FormEvent) => void;
    submitLabel: string;
    submitIcon?: ReactNode;
    actions?: ReactNode;
};

export function EstablishmentTypeForm({
    values,
    errors,
    processing,
    onChange,
    onSubmit,
    submitLabel,
    submitIcon,
    actions,
}: EstablishmentTypeFormProps) {
    const { t } = useTranslation();

    return (
        <FieldHelpScope table="establishment_types">
        <form onSubmit={onSubmit} className="space-y-5 rounded-2xl border border-line bg-surface p-6 sm:p-8">
            <Field label={t('common.name')} htmlFor="name" error={errors.name} required>
                <Input
                    id="name"
                    value={values.name}
                    invalid={Boolean(errors.name)}
                    onChange={(event) => onChange('name', event.target.value)}
                />
            </Field>

            <Field label={t('common.code')} htmlFor="code" error={errors.code} required>
                <Input
                    id="code"
                    value={values.code}
                    placeholder={t('establishmentTypes.codePlaceholder')}
                    invalid={Boolean(errors.code)}
                    onChange={(event) => onChange('code', event.target.value.toLowerCase())}
                />
            </Field>

            <Field
                label={t('establishmentTypes.healthAndSafetyDelayDays')}
                htmlFor="health_and_safety_delay_days"
                error={errors.health_and_safety_delay_days}
                required
            >
                <Input
                    id="health_and_safety_delay_days"
                    type="number"
                    min={0}
                    value={values.health_and_safety_delay_days}
                    placeholder={t('establishmentTypes.healthAndSafetyDelayDaysPlaceholder')}
                    invalid={Boolean(errors.health_and_safety_delay_days)}
                    onChange={(event) => onChange('health_and_safety_delay_days', event.target.value)}
                />
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
