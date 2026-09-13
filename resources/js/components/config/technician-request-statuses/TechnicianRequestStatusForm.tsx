import type { FormEvent, ReactNode } from 'react';
import { useTranslation } from 'react-i18next';
import { FieldHelpScope } from '@/components/field-help/FieldHelpScope';
import { Button } from '@/components/ui/Button';
import { Field } from '@/components/ui/Field';
import { Input } from '@/components/ui/Input';
import { Select } from '@/components/ui/Select';
import { Toggle } from '@/components/ui/Toggle';

export type TechnicianRequestStatusFormValues = {
    kind: string;
    name: string;
    color: string;
    lifecycle: number | string;
    is_open: boolean;
};

type TechnicianRequestStatusFormProps = {
    mode: 'create' | 'edit';
    values: TechnicianRequestStatusFormValues;
    errors: Partial<Record<keyof TechnicianRequestStatusFormValues, string>>;
    processing: boolean;
    onChange: (key: keyof TechnicianRequestStatusFormValues, value: string | boolean) => void;
    onSubmit: (event: FormEvent) => void;
    submitLabel: string;
    submitIcon?: ReactNode;
    actions?: ReactNode;
};

export function TechnicianRequestStatusForm({
    values,
    errors,
    processing,
    onChange,
    onSubmit,
    submitLabel,
    submitIcon,
    actions,
}: TechnicianRequestStatusFormProps) {
    const { t } = useTranslation();

    return (
        <FieldHelpScope table="technician_request_statuses">
            <form onSubmit={onSubmit} className="space-y-5 rounded-2xl border border-line bg-surface p-6 sm:p-8">
                <Field label={t('technicianRequestStatuses.kind')} htmlFor="kind" error={errors.kind} required>
                    <Select
                        id="kind"
                        value={values.kind}
                        invalid={Boolean(errors.kind)}
                        onChange={(event) => onChange('kind', event.target.value)}
                    >
                        <option value="request">{t('technicianRequests.request')}</option>
                        <option value="screening">{t('technicianRequests.screening')}</option>
                    </Select>
                </Field>

                <Field label={t('common.name')} htmlFor="name" error={errors.name} required>
                    <Input
                        id="name"
                        value={values.name}
                        invalid={Boolean(errors.name)}
                        onChange={(event) => onChange('name', event.target.value)}
                    />
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
                            placeholder={t('technicianRequestStatuses.colorPlaceholder')}
                            invalid={Boolean(errors.color)}
                            onChange={(event) => onChange('color', event.target.value)}
                            className="flex-1"
                        />
                    </div>
                </Field>

                <Field
                    label={t('technicianRequestStatuses.lifecycle')}
                    htmlFor="lifecycle"
                    error={errors.lifecycle}
                >
                    <Input
                        id="lifecycle"
                        type="number"
                        min={0}
                        max={255}
                        value={values.lifecycle === '' || values.lifecycle === null ? '' : String(values.lifecycle)}
                        invalid={Boolean(errors.lifecycle)}
                        onChange={(event) => onChange('lifecycle', event.target.value)}
                    />
                    <p className="text-xs text-ink-muted">{t('technicianRequestStatuses.lifecycleHint')}</p>
                </Field>

                <div className="space-y-1">
                    <p className="text-sm font-semibold text-ink">{t('technicianRequestStatuses.isOpen')}</p>
                    <p className="text-xs text-ink-muted">{t('technicianRequestStatuses.isOpenHint')}</p>
                    <Toggle
                        name="is_open"
                        checked={values.is_open}
                        onCheckedChange={(checked) => onChange('is_open', checked)}
                        checkedLabel={t('technicianRequestStatuses.isOpen')}
                        uncheckedLabel={t('technicianRequestStatuses.isOpenOff')}
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
