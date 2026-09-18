import type { FormEvent, ReactNode } from 'react';
import { useTranslation } from 'react-i18next';
import { FieldHelpScope } from '@/components/field-help/FieldHelpScope';
import { Button } from '@/components/ui/Button';
import { Field } from '@/components/ui/Field';
import { Input } from '@/components/ui/Input';
import { AsyncSearchableSelect } from '@/components/ui/AsyncSearchableSelect';
import { MultiSelect } from '@/components/ui/MultiSelect';
import { SearchableSelect } from '@/components/ui/SearchableSelect';
import { Toggle } from '@/components/ui/Toggle';
import { cn } from '@/support/cn';

export type TechnicianRequestFormValues = {
    is_screening: boolean;
    description: string;
    notes: string;
    internal_notes: string;
    city: string;
    postal_code: string;
    address_line: string;
    province_name: string;
    country_id: string;
    language_id: string;
    responsible_user_id: string;
    work_order_id: string;
    due_at: string;
    next_action_at: string;
    technician_request_priority_id: string;
    technician_request_status_id: string;
    service_type_ids: string[];
};

type Option = { id: number; label: string; color?: string | null; kind?: string };

type TechnicianRequestFormProps = {
    mode: 'create' | 'edit';
    values: TechnicianRequestFormValues;
    errors: Partial<Record<string, string>>;
    processing: boolean;
    lockScreening?: boolean;
    statusOptions: Option[];
    priorityOptions: Option[];
    userOptions: Option[];
    languageOptions: Option[];
    countryOptions: Option[];
    serviceTypeOptions: Option[];
    workOrderOptions: Option[];
    onChange: (key: keyof TechnicianRequestFormValues, value: string | boolean | string[]) => void;
    onSubmit: (event: FormEvent) => void;
    submitLabel: string;
    submitIcon?: ReactNode;
    actions?: ReactNode;
};

export function defaultTechnicianRequestFormValues(
    overrides: Partial<TechnicianRequestFormValues> = {},
): TechnicianRequestFormValues {
    return {
        is_screening: false,
        description: '',
        notes: '',
        internal_notes: '',
        city: '',
        postal_code: '',
        address_line: '',
        province_name: '',
        country_id: '',
        language_id: '',
        responsible_user_id: '',
        work_order_id: '',
        due_at: '',
        next_action_at: '',
        technician_request_priority_id: '',
        technician_request_status_id: '',
        service_type_ids: [],
        ...overrides,
    };
}

function toSelectOptions(options: Option[]) {
    return options.map((option) => ({
        value: String(option.id),
        label: option.label,
        color: option.color ?? undefined,
    }));
}

export function TechnicianRequestForm({
    mode,
    values,
    errors,
    processing,
    lockScreening = false,
    statusOptions,
    priorityOptions,
    userOptions,
    languageOptions,
    countryOptions,
    serviceTypeOptions,
    workOrderOptions,
    onChange,
    onSubmit,
    submitLabel,
    submitIcon,
    actions,
}: TechnicianRequestFormProps) {
    const { t } = useTranslation();

    return (
        <FieldHelpScope table="technician_requests">
            <form onSubmit={onSubmit} className="space-y-5 rounded-2xl border border-line bg-surface p-6 sm:p-8">
                <div className="grid gap-5 sm:grid-cols-2">
                    {mode === 'create' ? (
                        <div className="space-y-1 sm:col-span-2">
                            <p className="text-sm font-semibold text-ink">{t('technicianRequests.isScreening')}</p>
                            <p className="text-xs text-ink-muted">{t('technicianRequests.isScreeningHint')}</p>
                            <Toggle
                                name="is_screening"
                                checked={values.is_screening}
                                disabled={lockScreening}
                                onCheckedChange={(checked) => onChange('is_screening', checked)}
                                checkedLabel={t('technicianRequests.screening')}
                                uncheckedLabel={t('technicianRequests.request')}
                            />
                            {errors.is_screening ? <p className="text-sm text-danger">{errors.is_screening}</p> : null}
                        </div>
                    ) : null}

                    <Field
                        label={t('technicianRequests.priority')}
                        htmlFor="technician_request_priority_id"
                        error={errors.technician_request_priority_id}
                    >
                        <SearchableSelect
                            id="technician_request_priority_id"
                            value={values.technician_request_priority_id}
                            onChange={(value) => onChange('technician_request_priority_id', value)}
                            options={toSelectOptions(priorityOptions)}
                            invalid={Boolean(errors.technician_request_priority_id)}
                        />
                    </Field>

                    <Field
                        label={t('technicianRequests.status')}
                        htmlFor="technician_request_status_id"
                        error={errors.technician_request_status_id}
                    >
                        <SearchableSelect
                            id="technician_request_status_id"
                            value={values.technician_request_status_id}
                            onChange={(value) => onChange('technician_request_status_id', value)}
                            options={toSelectOptions(statusOptions)}
                            invalid={Boolean(errors.technician_request_status_id)}
                        />
                    </Field>

                    <Field
                        label={t('technicianRequests.responsible')}
                        htmlFor="responsible_user_id"
                        error={errors.responsible_user_id}
                    >
                        <AsyncSearchableSelect
                            id="responsible_user_id"
                            resource="users"
                            value={values.responsible_user_id}
                            onChange={(value) => onChange('responsible_user_id', value)}
                            seedOptions={userOptions}
                            invalid={Boolean(errors.responsible_user_id)}
                        />
                    </Field>

                    <Field
                        label={t('technicianRequests.workOrder')}
                        htmlFor="work_order_id"
                        error={errors.work_order_id}
                    >
                        <AsyncSearchableSelect
                            id="work_order_id"
                            resource="work-orders"
                            value={values.work_order_id}
                            onChange={(value) => onChange('work_order_id', value)}
                            seedOptions={workOrderOptions}
                            invalid={Boolean(errors.work_order_id)}
                        />
                    </Field>

                    <Field
                        label={t('technicianRequests.language')}
                        htmlFor="language_id"
                        error={errors.language_id}
                    >
                        <SearchableSelect
                            id="language_id"
                            value={values.language_id}
                            onChange={(value) => onChange('language_id', value)}
                            options={toSelectOptions(languageOptions)}
                            invalid={Boolean(errors.language_id)}
                        />
                    </Field>

                    <Field label={t('technicianRequests.country')} htmlFor="country_id" error={errors.country_id}>
                        <SearchableSelect
                            id="country_id"
                            value={values.country_id}
                            onChange={(value) => onChange('country_id', value)}
                            options={toSelectOptions(countryOptions)}
                            invalid={Boolean(errors.country_id)}
                        />
                    </Field>

                    <Field
                        label={t('technicianRequests.serviceTypes')}
                        htmlFor="service_type_ids"
                        error={errors.service_type_ids}
                        className="sm:col-span-2"
                    >
                        <MultiSelect
                            id="service_type_ids"
                            value={values.service_type_ids}
                            onChange={(ids) => onChange('service_type_ids', ids)}
                            options={toSelectOptions(serviceTypeOptions)}
                            placeholder={t('technicianRequests.serviceTypesPlaceholder')}
                            invalid={Boolean(errors.service_type_ids)}
                        />
                    </Field>

                    <Field
                        label={t('technicianRequests.description')}
                        htmlFor="description"
                        error={errors.description}
                        className="sm:col-span-2"
                    >
                        <textarea
                            id="description"
                            value={values.description}
                            onChange={(event) => onChange('description', event.target.value)}
                            rows={4}
                            className={cn(
                                'w-full rounded-lg border border-line bg-surface px-3 py-2 text-sm text-ink outline-none focus:border-brand',
                                errors.description && 'border-danger',
                            )}
                        />
                    </Field>

                    <Field label={t('technicianRequests.notes')} htmlFor="notes" error={errors.notes} className="sm:col-span-2">
                        <textarea
                            id="notes"
                            value={values.notes}
                            onChange={(event) => onChange('notes', event.target.value)}
                            rows={3}
                            className={cn(
                                'w-full rounded-lg border border-line bg-surface px-3 py-2 text-sm text-ink outline-none focus:border-brand',
                                errors.notes && 'border-danger',
                            )}
                        />
                    </Field>

                    <Field
                        label={t('technicianRequests.internalNotes')}
                        htmlFor="internal_notes"
                        error={errors.internal_notes}
                        className="sm:col-span-2"
                    >
                        <textarea
                            id="internal_notes"
                            value={values.internal_notes}
                            onChange={(event) => onChange('internal_notes', event.target.value)}
                            rows={3}
                            className={cn(
                                'w-full rounded-lg border border-line bg-surface px-3 py-2 text-sm text-ink outline-none focus:border-brand',
                                errors.internal_notes && 'border-danger',
                            )}
                        />
                    </Field>

                    <Field label={t('technicianRequests.addressLine')} htmlFor="address_line" error={errors.address_line}>
                        <Input
                            id="address_line"
                            value={values.address_line}
                            invalid={Boolean(errors.address_line)}
                            onChange={(event) => onChange('address_line', event.target.value)}
                        />
                    </Field>

                    <Field label={t('technicianRequests.city')} htmlFor="city" error={errors.city}>
                        <Input
                            id="city"
                            value={values.city}
                            invalid={Boolean(errors.city)}
                            onChange={(event) => onChange('city', event.target.value)}
                        />
                    </Field>

                    <Field label={t('technicianRequests.postalCode')} htmlFor="postal_code" error={errors.postal_code}>
                        <Input
                            id="postal_code"
                            value={values.postal_code}
                            invalid={Boolean(errors.postal_code)}
                            onChange={(event) => onChange('postal_code', event.target.value)}
                        />
                    </Field>

                    <Field
                        label={t('technicianRequests.provinceName')}
                        htmlFor="province_name"
                        error={errors.province_name}
                    >
                        <Input
                            id="province_name"
                            value={values.province_name}
                            invalid={Boolean(errors.province_name)}
                            onChange={(event) => onChange('province_name', event.target.value)}
                        />
                    </Field>

                    <Field label={t('technicianRequests.dueAt')} htmlFor="due_at" error={errors.due_at}>
                        <Input
                            id="due_at"
                            type="datetime-local"
                            value={values.due_at}
                            invalid={Boolean(errors.due_at)}
                            onChange={(event) => onChange('due_at', event.target.value)}
                        />
                    </Field>

                    <Field
                        label={t('technicianRequests.nextActionAt')}
                        htmlFor="next_action_at"
                        error={errors.next_action_at}
                    >
                        <Input
                            id="next_action_at"
                            type="datetime-local"
                            value={values.next_action_at}
                            invalid={Boolean(errors.next_action_at)}
                            onChange={(event) => onChange('next_action_at', event.target.value)}
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
        </FieldHelpScope>
    );
}
