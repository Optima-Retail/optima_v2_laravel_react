import type { FormEvent, ReactNode } from 'react';
import { useTranslation } from 'react-i18next';
import { Button } from '@/components/ui/Button';
import { Field } from '@/components/ui/Field';
import { Input } from '@/components/ui/Input';

export type CurrencyFormValues = {
    name: string;
    code: string;
};

type CurrencyFormProps = {
    mode: 'create' | 'edit';
    values: CurrencyFormValues;
    errors: Partial<Record<keyof CurrencyFormValues, string>>;
    processing: boolean;
    onChange: (key: keyof CurrencyFormValues, value: string) => void;
    onSubmit: (event: FormEvent) => void;
    submitLabel: string;
    submitIcon?: ReactNode;
    actions?: ReactNode;
};

export function CurrencyForm({
    values,
    errors,
    processing,
    onChange,
    onSubmit,
    submitLabel,
    submitIcon,
    actions,
}: CurrencyFormProps) {
    const { t } = useTranslation();

    return (
        <form onSubmit={onSubmit} className="space-y-5 rounded-2xl border border-line bg-surface p-6 sm:p-8">
            <div className="grid gap-5 sm:grid-cols-2">
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
                        placeholder={t('currencies.codePlaceholder')}
                        invalid={Boolean(errors.code)}
                        onChange={(event) => onChange('code', event.target.value.toUpperCase())}
                    />
                </Field>
            </div>

            <div className="flex flex-wrap items-center justify-end gap-2 border-t border-line pt-4">
                {actions}
                <Button type="submit" loading={processing}>
                    {submitIcon}
                    {submitLabel}
                </Button>
            </div>
        </form>
    );
}
