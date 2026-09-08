import type { FormEvent, ReactNode } from 'react';
import { useTranslation } from 'react-i18next';
import { Button } from '@/components/ui/Button';
import { Field } from '@/components/ui/Field';
import { Input } from '@/components/ui/Input';
import { FieldHelpScope } from '@/components/field-help/FieldHelpScope';

export type IntegrationFormValues = {
    name: string;
    code: string;
};

type IntegrationFormProps = {
    mode: 'create' | 'edit';
    values: IntegrationFormValues;
    errors: Partial<Record<keyof IntegrationFormValues, string>>;
    processing: boolean;
    onChange: (key: keyof IntegrationFormValues, value: string) => void;
    onSubmit: (event: FormEvent) => void;
    submitLabel: string;
    submitIcon?: ReactNode;
    actions?: ReactNode;
};

export function IntegrationForm({
    values,
    errors,
    processing,
    onChange,
    onSubmit,
    submitLabel,
    submitIcon,
    actions,
}: IntegrationFormProps) {
    const { t } = useTranslation();

    return (
        <FieldHelpScope table="integrations">
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
                    placeholder={t('integrations.codePlaceholder')}
                    invalid={Boolean(errors.code)}
                    onChange={(event) => onChange('code', event.target.value.toLowerCase())}
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
