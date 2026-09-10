import type { FormEvent, ReactNode } from 'react';
import { useTranslation } from 'react-i18next';
import { FieldHelpScope } from '@/components/field-help/FieldHelpScope';
import { Button } from '@/components/ui/Button';
import { Field } from '@/components/ui/Field';
import { Input } from '@/components/ui/Input';

export type TechnicianAttendanceConfirmationTypeFormValues = {
    name: string;
};

type TechnicianAttendanceConfirmationTypeFormProps = {
    mode: 'create' | 'edit';
    values: TechnicianAttendanceConfirmationTypeFormValues;
    errors: Partial<Record<keyof TechnicianAttendanceConfirmationTypeFormValues, string>>;
    processing: boolean;
    onChange: (key: keyof TechnicianAttendanceConfirmationTypeFormValues, value: string) => void;
    onSubmit: (event: FormEvent) => void;
    submitLabel: string;
    submitIcon?: ReactNode;
    actions?: ReactNode;
};

export function TechnicianAttendanceConfirmationTypeForm({
    values,
    errors,
    processing,
    onChange,
    onSubmit,
    submitLabel,
    submitIcon,
    actions,
}: TechnicianAttendanceConfirmationTypeFormProps) {
    const { t } = useTranslation();

    return (
        <FieldHelpScope table="technician_attendance_confirmation_types">
            <form onSubmit={onSubmit} className="space-y-5 rounded-2xl border border-line bg-surface p-6 sm:p-8">
                <Field label={t('common.name')} htmlFor="name" error={errors.name} required>
                    <Input
                        id="name"
                        value={values.name}
                        invalid={Boolean(errors.name)}
                        onChange={(event) => onChange('name', event.target.value)}
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
