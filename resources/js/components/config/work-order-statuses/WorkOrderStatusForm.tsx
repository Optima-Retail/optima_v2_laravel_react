import type { FormEvent, ReactNode } from 'react';
import { useTranslation } from 'react-i18next';
import { Button } from '@/components/ui/Button';
import { Field } from '@/components/ui/Field';
import { Input } from '@/components/ui/Input';
import { Select } from '@/components/ui/Select';
import { Toggle } from '@/components/ui/Toggle';
import { FieldHelpScope } from '@/components/field-help/FieldHelpScope';

export type WorkOrderStatusFormValues = {
    name: string;
    kind: string;
    color: string;
    lifecycle: number | string;
    is_open: boolean;
};

type WorkOrderStatusFormProps = {
    mode: 'create' | 'edit';
    values: WorkOrderStatusFormValues;
    errors: Partial<Record<keyof WorkOrderStatusFormValues, string>>;
    processing: boolean;
    onChange: (key: keyof WorkOrderStatusFormValues, value: string | boolean) => void;
    onSubmit: (event: FormEvent) => void;
    submitLabel: string;
    submitIcon?: ReactNode;
    actions?: ReactNode;
};

export function WorkOrderStatusForm({
    values,
    errors,
    processing,
    onChange,
    onSubmit,
    submitLabel,
    submitIcon,
    actions,
}: WorkOrderStatusFormProps) {
    const { t } = useTranslation();

    return (
        <FieldHelpScope table="work_order_statuses">
            <form onSubmit={onSubmit} className="space-y-5 rounded-2xl border border-line bg-surface p-6 sm:p-8">
                <Field label={t('common.name')} htmlFor="name" error={errors.name} required>
                    <Input
                        id="name"
                        value={values.name}
                        invalid={Boolean(errors.name)}
                        onChange={(event) => onChange('name', event.target.value)}
                    />
                </Field>

                <Field label={t('workOrderStatuses.kind')} htmlFor="kind" error={errors.kind} required>
                    <Select
                        id="kind"
                        value={values.kind}
                        invalid={Boolean(errors.kind)}
                        onChange={(event) => onChange('kind', event.target.value)}
                    >
                        <option value="work_order">{t('workOrderStatuses.kinds.work_order')}</option>
                        <option value="estimate">{t('workOrderStatuses.kinds.estimate')}</option>
                    </Select>
                    <p className="text-xs text-ink-muted">{t('workOrderStatuses.kindHint')}</p>
                </Field>

                <Field label={t('common.color')} htmlFor="color" error={errors.color}>
                    <div className="flex items-center gap-3">
                        <input
                            id="color"
                            type="color"
                            value={values.color || '#a9cef0'}
                            onChange={(event) => onChange('color', event.target.value)}
                            className="size-8 cursor-pointer rounded-lg border border-line bg-surface p-0.5"
                        />
                        <Input
                            value={values.color}
                            placeholder={t('workOrderStatuses.colorPlaceholder')}
                            invalid={Boolean(errors.color)}
                            onChange={(event) => onChange('color', event.target.value)}
                            className="flex-1"
                        />
                    </div>
                </Field>

                <Field label={t('workOrderStatuses.lifecycle')} htmlFor="lifecycle" error={errors.lifecycle}>
                    <Input
                        id="lifecycle"
                        type="number"
                        min={0}
                        max={255}
                        value={values.lifecycle === '' || values.lifecycle === null ? '' : String(values.lifecycle)}
                        invalid={Boolean(errors.lifecycle)}
                        onChange={(event) => onChange('lifecycle', event.target.value)}
                    />
                    <p className="text-xs text-ink-muted">{t('workOrderStatuses.lifecycleHint')}</p>
                </Field>

                <div className="space-y-1">
                    <p className="text-sm font-semibold text-ink">{t('workOrderStatuses.isOpen')}</p>
                    <p className="text-xs text-ink-muted">{t('workOrderStatuses.isOpenHint')}</p>
                    <Toggle
                        name="is_open"
                        checked={values.is_open}
                        onCheckedChange={(checked) => onChange('is_open', checked)}
                        checkedLabel={t('workOrderStatuses.isOpen')}
                        uncheckedLabel={t('workOrderStatuses.isOpenOff')}
                    />
                    {errors.is_open ? <p className="text-sm text-danger">{errors.is_open}</p> : null}
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
