import type { FormEvent, ReactNode } from 'react';
import { useTranslation } from 'react-i18next';
import { Button } from '@/components/ui/Button';
import { Field } from '@/components/ui/Field';
import { Input } from '@/components/ui/Input';
import { Toggle } from '@/components/ui/Toggle';
import { FieldHelpScope } from '@/components/field-help/FieldHelpScope';

export type PaymentMethodFormValues = {
    name: string;
    due_count: string;
    days: string;
    code: string;
    is_active: boolean;
};

type PaymentMethodFormProps = {
    mode: 'create' | 'edit';
    values: PaymentMethodFormValues;
    errors: Partial<Record<keyof PaymentMethodFormValues, string>>;
    processing: boolean;
    onChange: (key: keyof PaymentMethodFormValues, value: string | boolean) => void;
    onSubmit: (event: FormEvent) => void;
    submitLabel: string;
    submitIcon?: ReactNode;
    actions?: ReactNode;
};

export function PaymentMethodForm({
    values,
    errors,
    processing,
    onChange,
    onSubmit,
    submitLabel,
    submitIcon,
    actions,
}: PaymentMethodFormProps) {
    const { t } = useTranslation();

    return (
        <FieldHelpScope table="payment_methods">
            <form onSubmit={onSubmit} className="space-y-5 rounded-2xl border border-line bg-surface p-6 sm:p-8">
                <Field label={t('common.name')} htmlFor="name" error={errors.name} required>
                    <Input
                        id="name"
                        value={values.name}
                        invalid={Boolean(errors.name)}
                        onChange={(event) => onChange('name', event.target.value)}
                    />
                </Field>

                <div className="grid gap-5 sm:grid-cols-3">
                    <Field label={t('paymentMethods.dueCount')} htmlFor="due_count" error={errors.due_count}>
                        <Input
                            id="due_count"
                            type="number"
                            min={0}
                            value={values.due_count}
                            invalid={Boolean(errors.due_count)}
                            onChange={(event) => onChange('due_count', event.target.value)}
                        />
                    </Field>
                    <Field label={t('paymentMethods.days')} htmlFor="days" error={errors.days}>
                        <Input
                            id="days"
                            type="number"
                            min={0}
                            value={values.days}
                            invalid={Boolean(errors.days)}
                            onChange={(event) => onChange('days', event.target.value)}
                        />
                    </Field>
                    <Field label={t('paymentMethods.code')} htmlFor="code" error={errors.code}>
                        <Input
                            id="code"
                            value={values.code}
                            invalid={Boolean(errors.code)}
                            onChange={(event) => onChange('code', event.target.value)}
                        />
                    </Field>
                </div>

                <Field label={t('common.status')} htmlFor="is_active" error={errors.is_active}>
                    <Toggle
                        id="is_active"
                        checked={values.is_active}
                        onCheckedChange={(checked) => onChange('is_active', checked)}
                        checkedLabel={t('common.active')}
                        uncheckedLabel={t('common.inactive')}
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
