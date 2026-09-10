import type { FormEvent, ReactNode } from 'react';
import { useTranslation } from 'react-i18next';
import { Button } from '@/components/ui/Button';
import { Field } from '@/components/ui/Field';
import { Input } from '@/components/ui/Input';
import { SearchableSelect } from '@/components/ui/SearchableSelect';
import { FieldHelpScope } from '@/components/field-help/FieldHelpScope';
import type { UserOption } from '@/support/types/domain/common';

export type VehicleFormValues = {
    brand: string;
    model: string;
    license_plate: string;
    company_relationship_id: string;
};

type VehicleFormProps = {
    mode: 'create' | 'edit';
    values: VehicleFormValues;
    errors: Partial<Record<keyof VehicleFormValues, string>>;
    processing: boolean;
    technicianOptions: UserOption[];
    onChange: (key: keyof VehicleFormValues, value: string) => void;
    onSubmit: (event: FormEvent) => void;
    submitLabel: string;
    submitIcon?: ReactNode;
    actions?: ReactNode;
};

export function VehicleForm({
    values,
    errors,
    processing,
    technicianOptions,
    onChange,
    onSubmit,
    submitLabel,
    submitIcon,
    actions,
}: VehicleFormProps) {
    const { t } = useTranslation();

    return (
        <FieldHelpScope table="vehicles">
            <form onSubmit={onSubmit} className="space-y-5 rounded-2xl border border-line bg-surface p-6 sm:p-8">
                <Field
                    label={t('vehicles.technician')}
                    htmlFor="company_relationship_id"
                    error={errors.company_relationship_id}
                    required
                >
                    <SearchableSelect
                        id="company_relationship_id"
                        value={values.company_relationship_id}
                        invalid={Boolean(errors.company_relationship_id)}
                        placeholder={t('vehicles.technicianPlaceholder')}
                        onChange={(value) => onChange('company_relationship_id', value)}
                        options={technicianOptions.map((option) => ({
                            value: String(option.id),
                            label: option.label,
                        }))}
                    />
                </Field>

                <Field label={t('vehicles.brand')} htmlFor="brand" error={errors.brand}>
                    <Input
                        id="brand"
                        value={values.brand}
                        invalid={Boolean(errors.brand)}
                        onChange={(event) => onChange('brand', event.target.value)}
                    />
                </Field>

                <Field label={t('vehicles.model')} htmlFor="model" error={errors.model}>
                    <Input
                        id="model"
                        value={values.model}
                        invalid={Boolean(errors.model)}
                        onChange={(event) => onChange('model', event.target.value)}
                    />
                </Field>

                <Field label={t('vehicles.licensePlate')} htmlFor="license_plate" error={errors.license_plate}>
                    <Input
                        id="license_plate"
                        value={values.license_plate}
                        invalid={Boolean(errors.license_plate)}
                        onChange={(event) => onChange('license_plate', event.target.value)}
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
