import type { FormEvent, ReactNode } from 'react';
import { useTranslation } from 'react-i18next';
import { Button } from '@/components/ui/Button';
import { Field } from '@/components/ui/Field';
import { Input } from '@/components/ui/Input';
import { MultiSelect } from '@/components/ui/MultiSelect';
import { Select } from '@/components/ui/Select';
import { Toggle } from '@/components/ui/Toggle';
import { FieldHelpScope } from '@/components/field-help/FieldHelpScope';
import type { UserOption } from '@/support/types/domain/common';

export type WorkOrderStatusTransitionForm = {
    to_status_id: string;
    requires_confirmation: boolean;
    requires_justification: boolean;
};

export type WorkOrderStatusFormValues = {
    name: string;
    kind: string;
    color: string;
    lifecycle: number | string;
    is_open: boolean;
    is_default: boolean;
    confirms_estimate: boolean;
    rejects_to_estimate: boolean;
    is_post_confirm_default: boolean;
    sets_sent_at: boolean;
    transitions: WorkOrderStatusTransitionForm[];
};

type WorkOrderStatusFormProps = {
    mode: 'create' | 'edit';
    values: WorkOrderStatusFormValues;
    errors: Partial<Record<string, string>>;
    processing: boolean;
    targetOptions?: UserOption[];
    onChange: (key: keyof WorkOrderStatusFormValues, value: WorkOrderStatusFormValues[keyof WorkOrderStatusFormValues]) => void;
    onSubmit: (event: FormEvent) => void;
    submitLabel: string;
    submitIcon?: ReactNode;
    actions?: ReactNode;
};

export function WorkOrderStatusForm({
    values,
    errors,
    processing,
    targetOptions = [],
    onChange,
    onSubmit,
    submitLabel,
    submitIcon,
    actions,
}: WorkOrderStatusFormProps) {
    const { t } = useTranslation();
    const isEstimate = values.kind === 'estimate';

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

                <div className="space-y-1">
                    <p className="text-sm font-semibold text-ink">{t('workOrderStatuses.isDefault')}</p>
                    <p className="text-xs text-ink-muted">{t('workOrderStatuses.isDefaultHint')}</p>
                    <Toggle
                        name="is_default"
                        checked={values.is_default}
                        onCheckedChange={(checked) => onChange('is_default', checked)}
                        checkedLabel={t('common.yes')}
                        uncheckedLabel={t('common.no')}
                    />
                </div>

                {isEstimate ? (
                    <>
                        <div className="space-y-1">
                            <p className="text-sm font-semibold text-ink">{t('workOrderStatuses.confirmsEstimate')}</p>
                            <p className="text-xs text-ink-muted">{t('workOrderStatuses.confirmsEstimateHint')}</p>
                            <Toggle
                                name="confirms_estimate"
                                checked={values.confirms_estimate}
                                onCheckedChange={(checked) => onChange('confirms_estimate', checked)}
                                checkedLabel={t('common.yes')}
                                uncheckedLabel={t('common.no')}
                            />
                        </div>
                        <div className="space-y-1">
                            <p className="text-sm font-semibold text-ink">{t('workOrderStatuses.setsSentAt')}</p>
                            <p className="text-xs text-ink-muted">{t('workOrderStatuses.setsSentAtHint')}</p>
                            <Toggle
                                name="sets_sent_at"
                                checked={values.sets_sent_at}
                                onCheckedChange={(checked) => onChange('sets_sent_at', checked)}
                                checkedLabel={t('common.yes')}
                                uncheckedLabel={t('common.no')}
                            />
                        </div>
                    </>
                ) : (
                    <>
                        <div className="space-y-1">
                            <p className="text-sm font-semibold text-ink">{t('workOrderStatuses.rejectsToEstimate')}</p>
                            <p className="text-xs text-ink-muted">{t('workOrderStatuses.rejectsToEstimateHint')}</p>
                            <Toggle
                                name="rejects_to_estimate"
                                checked={values.rejects_to_estimate}
                                onCheckedChange={(checked) => onChange('rejects_to_estimate', checked)}
                                checkedLabel={t('common.yes')}
                                uncheckedLabel={t('common.no')}
                            />
                        </div>
                        <div className="space-y-1">
                            <p className="text-sm font-semibold text-ink">{t('workOrderStatuses.isPostConfirmDefault')}</p>
                            <p className="text-xs text-ink-muted">{t('workOrderStatuses.isPostConfirmDefaultHint')}</p>
                            <Toggle
                                name="is_post_confirm_default"
                                checked={values.is_post_confirm_default}
                                onCheckedChange={(checked) => onChange('is_post_confirm_default', checked)}
                                checkedLabel={t('common.yes')}
                                uncheckedLabel={t('common.no')}
                            />
                        </div>
                    </>
                )}

                {targetOptions.length > 0 ? (
                    <div className="space-y-3 border-t border-line pt-5">
                        <div>
                            <p className="text-sm font-semibold text-ink">{t('workOrderStatuses.nextStatuses')}</p>
                            <p className="text-xs text-ink-muted">{t('workOrderStatuses.nextStatusesHint')}</p>
                        </div>
                        <MultiSelect
                            id="next_status_ids"
                            value={values.transitions.map((row) => row.to_status_id)}
                            onChange={(next) => {
                                const current = new Map(
                                    values.transitions.map((row) => [row.to_status_id, row]),
                                );
                                onChange(
                                    'transitions',
                                    next.map((id) => current.get(id) ?? {
                                        to_status_id: id,
                                        requires_confirmation: false,
                                        requires_justification: false,
                                    }),
                                );
                            }}
                            options={targetOptions.map((option) => ({
                                value: String(option.id),
                                label: option.label,
                            }))}
                            placeholder={t('workOrderStatuses.nextStatusesPlaceholder')}
                        />
                        {values.transitions.map((row) => {
                            const label = targetOptions.find((option) => String(option.id) === row.to_status_id)?.label
                                ?? row.to_status_id;

                            return (
                                <div key={row.to_status_id} className="grid gap-3 rounded-xl border border-line p-3 sm:grid-cols-2">
                                    <p className="sm:col-span-2 text-sm font-medium text-ink">{label}</p>
                                    <Toggle
                                        name={`transition-${row.to_status_id}-confirm`}
                                        checked={row.requires_confirmation}
                                        onCheckedChange={(checked) =>
                                            onChange(
                                                'transitions',
                                                values.transitions.map((item) =>
                                                    item.to_status_id === row.to_status_id
                                                        ? { ...item, requires_confirmation: checked }
                                                        : item,
                                                ),
                                            )
                                        }
                                        checkedLabel={t('workOrderStatuses.requiresConfirmation')}
                                        uncheckedLabel={t('workOrderStatuses.requiresConfirmationOff')}
                                    />
                                    <Toggle
                                        name={`transition-${row.to_status_id}-justify`}
                                        checked={row.requires_justification}
                                        onCheckedChange={(checked) =>
                                            onChange(
                                                'transitions',
                                                values.transitions.map((item) =>
                                                    item.to_status_id === row.to_status_id
                                                        ? { ...item, requires_justification: checked }
                                                        : item,
                                                ),
                                            )
                                        }
                                        checkedLabel={t('workOrderStatuses.requiresJustification')}
                                        uncheckedLabel={t('workOrderStatuses.requiresJustificationOff')}
                                    />
                                </div>
                            );
                        })}
                    </div>
                ) : null}

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
