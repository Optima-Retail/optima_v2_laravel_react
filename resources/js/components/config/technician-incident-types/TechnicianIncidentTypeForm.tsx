import type { FormEvent, ReactNode } from 'react';
import { useTranslation } from 'react-i18next';
import { Button } from '@/components/ui/Button';
import { Field } from '@/components/ui/Field';
import { Input } from '@/components/ui/Input';
import { Toggle } from '@/components/ui/Toggle';
import { FieldHelpScope } from '@/components/field-help/FieldHelpScope';

export type TechnicianIncidentTypeFormValues = {
    name: string;
    due_days: number | string;
    send_mail_to_technician: boolean;
};

type TechnicianIncidentTypeFormProps = {
    mode: 'create' | 'edit';
    values: TechnicianIncidentTypeFormValues;
    errors: Partial<Record<keyof TechnicianIncidentTypeFormValues, string>>;
    processing: boolean;
    onChange: (key: keyof TechnicianIncidentTypeFormValues, value: string | boolean) => void;
    onSubmit: (event: FormEvent) => void;
    submitLabel: string;
    submitIcon?: ReactNode;
    actions?: ReactNode;
};

export function TechnicianIncidentTypeForm({
    values,
    errors,
    processing,
    onChange,
    onSubmit,
    submitLabel,
    submitIcon,
    actions,
}: TechnicianIncidentTypeFormProps) {
    const { t } = useTranslation();

    return (
        <FieldHelpScope table="technician_incident_types">
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
                    label={t('technicianIncidentTypes.dueDays')}
                    htmlFor="due_days"
                    error={errors.due_days}
                    required
                >
                    <Input
                        id="due_days"
                        type="number"
                        min={0}
                        value={values.due_days === '' || values.due_days === null ? '' : String(values.due_days)}
                        invalid={Boolean(errors.due_days)}
                        onChange={(event) => onChange('due_days', event.target.value)}
                    />
                    <p className="text-xs text-ink-muted">{t('technicianIncidentTypes.dueDaysHint')}</p>
                </Field>

                <div className="space-y-1">
                    <p className="text-sm font-semibold text-ink">
                        {t('technicianIncidentTypes.sendMailToTechnician')}
                    </p>
                    <p className="text-xs text-ink-muted">
                        {t('technicianIncidentTypes.sendMailToTechnicianHint')}
                    </p>
                    <Toggle
                        name="send_mail_to_technician"
                        checked={values.send_mail_to_technician}
                        onCheckedChange={(checked) => onChange('send_mail_to_technician', checked)}
                        checkedLabel={t('technicianIncidentTypes.sendMailToTechnician')}
                        uncheckedLabel={t('technicianIncidentTypes.sendMailToTechnicianOff')}
                    />
                    {errors.send_mail_to_technician ? (
                        <p className="text-sm text-danger">{errors.send_mail_to_technician}</p>
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
