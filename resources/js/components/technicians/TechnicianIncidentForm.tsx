import type { FormEvent, ReactNode } from 'react';
import { useTranslation } from 'react-i18next';
import { Button } from '@/components/ui/Button';
import { Field } from '@/components/ui/Field';
import { Select } from '@/components/ui/Select';
import type { UserOption } from '@/support/types/domain/common';

export type TechnicianIncidentFormValues = {
    technician_id: string;
    technician_incident_type_id: string;
    responded_by_id: string;
    incident_text: string;
};

type TechnicianIncidentFormProps = {
    values: TechnicianIncidentFormValues;
    errors: Partial<Record<keyof TechnicianIncidentFormValues, string>>;
    processing: boolean;
    typeOptions: UserOption[];
    userOptions: UserOption[];
    technicianOptions: UserOption[];
    technicianLocked?: boolean;
    onChange: (key: keyof TechnicianIncidentFormValues, value: string) => void;
    onSubmit: (event: FormEvent) => void;
    submitLabel: string;
    submitIcon?: ReactNode;
    actions?: ReactNode;
};

export function TechnicianIncidentForm({
    values,
    errors,
    processing,
    typeOptions,
    userOptions,
    technicianOptions,
    technicianLocked = false,
    onChange,
    onSubmit,
    submitLabel,
    submitIcon,
    actions,
}: TechnicianIncidentFormProps) {
    const { t } = useTranslation();

    return (
        <form onSubmit={onSubmit} className="space-y-5 rounded-2xl border border-line bg-surface p-6 sm:p-8">
            <Field
                label={t('technicianIncidents.technician')}
                htmlFor="technician_id"
                error={errors.technician_id}
                required
            >
                <Select
                    id="technician_id"
                    value={values.technician_id}
                    invalid={Boolean(errors.technician_id)}
                    disabled={technicianLocked}
                    onChange={(event) => onChange('technician_id', event.target.value)}
                >
                    <option value="">{t('common.select')}</option>
                    {technicianOptions.map((option) => (
                        <option key={option.id} value={option.id}>
                            {option.label}
                        </option>
                    ))}
                </Select>
            </Field>

            <Field
                label={t('technicianIncidents.type')}
                htmlFor="technician_incident_type_id"
                error={errors.technician_incident_type_id}
                required
            >
                <Select
                    id="technician_incident_type_id"
                    value={values.technician_incident_type_id}
                    invalid={Boolean(errors.technician_incident_type_id)}
                    onChange={(event) => onChange('technician_incident_type_id', event.target.value)}
                >
                    <option value="">{t('common.select')}</option>
                    {typeOptions.map((option) => (
                        <option key={option.id} value={option.id}>
                            {option.label}
                        </option>
                    ))}
                </Select>
            </Field>

            <Field
                label={t('technicianIncidents.assignedTo')}
                htmlFor="responded_by_id"
                error={errors.responded_by_id}
                required
            >
                <Select
                    id="responded_by_id"
                    value={values.responded_by_id}
                    invalid={Boolean(errors.responded_by_id)}
                    onChange={(event) => onChange('responded_by_id', event.target.value)}
                >
                    <option value="">{t('common.select')}</option>
                    {userOptions.map((option) => (
                        <option key={option.id} value={option.id}>
                            {option.label}
                        </option>
                    ))}
                </Select>
            </Field>

            <Field
                label={t('technicianIncidents.incidentText')}
                htmlFor="incident_text"
                error={errors.incident_text}
                required
            >
                <textarea
                    id="incident_text"
                    value={values.incident_text}
                    rows={6}
                    onChange={(event) => onChange('incident_text', event.target.value)}
                    className="w-full rounded-lg border border-line bg-surface px-3 py-2 text-sm text-ink shadow-sm transition focus:border-brand focus:outline-none focus:ring-2 focus:ring-brand/20"
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
    );
}
