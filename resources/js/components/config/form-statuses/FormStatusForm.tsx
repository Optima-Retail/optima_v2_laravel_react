import type { FormEvent, ReactNode } from 'react';
import { useTranslation } from 'react-i18next';
import { Button } from '@/components/ui/Button';
import { Field } from '@/components/ui/Field';
import { Input } from '@/components/ui/Input';
import { Select } from '@/components/ui/Select';
import { Toggle } from '@/components/ui/Toggle';
import { FieldHelpScope } from '@/components/field-help/FieldHelpScope';
import type { UserOption } from '@/support/types/domain/common';

export type FormStatusFormValues = {
    name: string;
    next_status_id: number | string | null;
    is_active: boolean;
};

type FormStatusFormProps = {
    mode: 'create' | 'edit';
    values: FormStatusFormValues;
    errors: Partial<Record<keyof FormStatusFormValues, string>>;
    processing: boolean;
    statusOptions: UserOption[];
    onChange: (key: keyof FormStatusFormValues, value: string | boolean) => void;
    onSubmit: (event: FormEvent) => void;
    submitLabel: string;
    submitIcon?: ReactNode;
    actions?: ReactNode;
};

export function FormStatusForm({
    values,
    errors,
    processing,
    statusOptions,
    onChange,
    onSubmit,
    submitLabel,
    submitIcon,
    actions,
}: FormStatusFormProps) {
    const { t } = useTranslation();

    return (
        <FieldHelpScope table="form_statuses">
            <form onSubmit={onSubmit} className="space-y-5 rounded-2xl border border-line bg-surface p-6 sm:p-8">
                <Field label={t('common.name')} htmlFor="name" error={errors.name} required>
                    <Input
                        id="name"
                        value={values.name}
                        invalid={Boolean(errors.name)}
                        onChange={(event) => onChange('name', event.target.value)}
                    />
                </Field>

                <Field
                    label={t('formStatuses.nextStatus')}
                    htmlFor="next_status_id"
                    error={errors.next_status_id}
                >
                    <Select
                        id="next_status_id"
                        value={values.next_status_id === null || values.next_status_id === undefined ? '' : String(values.next_status_id)}
                        invalid={Boolean(errors.next_status_id)}
                        onChange={(event) => onChange('next_status_id', event.target.value)}
                    >
                        <option value="">{t('formStatuses.nextStatusNone')}</option>
                        {statusOptions.map((option) => (
                            <option key={option.id} value={option.id}>
                                {option.label}
                            </option>
                        ))}
                    </Select>
                    <p className="text-xs text-ink-muted">{t('formStatuses.nextStatusHint')}</p>
                </Field>

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
