import type { FormEvent, ReactNode } from 'react';
import { useTranslation } from 'react-i18next';
import { Button } from '@/components/ui/Button';
import { Field } from '@/components/ui/Field';
import { Input } from '@/components/ui/Input';
import { Select } from '@/components/ui/Select';
import { Toggle } from '@/components/ui/Toggle';
import { FieldHelpScope } from '@/components/field-help/FieldHelpScope';
import { cn } from '@/support/cn';
import type {
    ChecklistDocumentTypeOption,
    ChecklistStatusOption,
} from '@/support/types/domain/checklist';

export type ChecklistFormValues = {
    label: string;
    requires_validation: boolean;
    document_type: string;
    work_order_status_id: string;
    sort_order: number | string;
};

type ChecklistFormProps = {
    mode: 'create' | 'edit';
    values: ChecklistFormValues;
    errors: Partial<Record<keyof ChecklistFormValues, string>>;
    processing: boolean;
    documentTypeOptions: ChecklistDocumentTypeOption[];
    workOrderStatusOptions: ChecklistStatusOption[];
    estimateStatusOptions: ChecklistStatusOption[];
    onChange: (key: keyof ChecklistFormValues, value: string | boolean) => void;
    onSubmit: (event: FormEvent) => void;
    submitLabel: string;
    submitIcon?: ReactNode;
    actions?: ReactNode;
};

export function ChecklistForm({
    values,
    errors,
    processing,
    documentTypeOptions,
    workOrderStatusOptions,
    estimateStatusOptions,
    onChange,
    onSubmit,
    submitLabel,
    submitIcon,
    actions,
}: ChecklistFormProps) {
    const { t } = useTranslation();
    const isWorkOrder = values.document_type === 'work_order';
    const isEstimate = values.document_type === 'estimate';
    const statusOptions = isEstimate ? estimateStatusOptions : workOrderStatusOptions;

    return (
        <FieldHelpScope table="checklists">
            <form onSubmit={onSubmit} className="space-y-5 rounded-2xl border border-line bg-surface p-6 sm:p-8">
                <Field label={t('checklists.label')} htmlFor="label" error={errors.label} required>
                    <textarea
                        id="label"
                        rows={3}
                        value={values.label}
                        onChange={(event) => onChange('label', event.target.value)}
                        className={cn(
                            'w-full rounded-lg border bg-surface px-3 py-2 text-sm text-ink shadow-sm transition',
                            'placeholder:text-ink-muted/70',
                            'focus:border-brand focus:outline-none focus:ring-2 focus:ring-brand/20',
                            errors.label ? 'border-danger focus:border-danger focus:ring-danger/20' : 'border-line',
                        )}
                    />
                </Field>

                <div className="grid gap-5 sm:grid-cols-2">
                    <Field
                        label={t('checklists.documentType')}
                        htmlFor="document_type"
                        error={errors.document_type}
                        required
                    >
                        <Select
                            id="document_type"
                            value={values.document_type}
                            invalid={Boolean(errors.document_type)}
                            onChange={(event) => onChange('document_type', event.target.value)}
                        >
                            <option value="">{t('common.select')}</option>
                            {documentTypeOptions.map((option) => (
                                <option key={option.value} value={option.value}>
                                    {t(`checklists.documentTypes.${option.value}`, {
                                        defaultValue: option.label,
                                    })}
                                </option>
                            ))}
                        </Select>
                    </Field>

                    <Field label={t('checklists.sortOrder')} htmlFor="sort_order" error={errors.sort_order}>
                        <Input
                            id="sort_order"
                            type="number"
                            min={0}
                            value={values.sort_order === '' || values.sort_order === null ? '' : String(values.sort_order)}
                            invalid={Boolean(errors.sort_order)}
                            onChange={(event) => onChange('sort_order', event.target.value)}
                        />
                    </Field>
                </div>

                {(isWorkOrder || isEstimate) ? (
                    <Field
                        label={t('checklists.status')}
                        htmlFor="work_order_status_id"
                        error={errors.work_order_status_id}
                        required
                    >
                        <Select
                            id="work_order_status_id"
                            value={values.work_order_status_id}
                            invalid={Boolean(errors.work_order_status_id)}
                            onChange={(event) => onChange('work_order_status_id', event.target.value)}
                        >
                            <option value="">{t('common.select')}</option>
                            {statusOptions.map((option) => (
                                <option key={option.id} value={String(option.id)}>
                                    {option.label}
                                </option>
                            ))}
                        </Select>
                    </Field>
                ) : null}

                <div className="space-y-1">
                    <p className="text-sm font-semibold text-ink">{t('checklists.requiresValidation')}</p>
                    <p className="text-xs text-ink-muted">{t('checklists.requiresValidationHint')}</p>
                    <Toggle
                        name="requires_validation"
                        checked={values.requires_validation}
                        onCheckedChange={(checked) => onChange('requires_validation', checked)}
                        checkedLabel={t('checklists.requiresValidation')}
                        uncheckedLabel={t('checklists.requiresValidationOff')}
                    />
                    {errors.requires_validation ? (
                        <p className="text-sm text-danger">{errors.requires_validation}</p>
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
