import { type FormEvent, type ReactNode, useMemo } from 'react';
import { useTranslation } from 'react-i18next';
import { FieldHelpScope } from '@/components/field-help/FieldHelpScope';
import { Button } from '@/components/ui/Button';
import { Field } from '@/components/ui/Field';
import { Input } from '@/components/ui/Input';
import { SearchableSelect } from '@/components/ui/SearchableSelect';
import { cn } from '@/support/cn';
import type { UserOption } from '@/support/types/domain/common';
import type { EstablishmentOption } from '@/support/types/domain/establishment';
import type {
    IncidentSubtypeOption,
    IncidentTypeOption,
    IncidentTypeWorkflowMap,
} from '@/support/types/domain/incident';

export type IncidentFormValues = {
    subject: string;
    comment: string;
    incident_status_id: string;
    incident_priority_id: string;
    incident_type_id: string;
    incident_subtype_id: string;
    origin_type: string;
    origin_id: string;
    related_type: string;
    related_id: string;
    requester_user_id: string;
    responsible_user_id: string;
    qc_responsible_user_id: string;
    control_at: string;
};

export type IncidentReadonlyFields = {
    duration_seconds: number | null;
    qc_duration_seconds: number | null;
    closed_at: string | null;
};

type IncidentFormProps = {
    values: IncidentFormValues;
    errors: Partial<Record<keyof IncidentFormValues, string>>;
    processing: boolean;
    mode: 'create' | 'edit';
    typeWorkflow: IncidentTypeWorkflowMap;
    incidentStatusOptions: UserOption[];
    incidentPriorityOptions: UserOption[];
    incidentTypeOptions: IncidentTypeOption[];
    incidentSubtypeOptions: IncidentSubtypeOption[];
    userOptions: UserOption[];
    establishmentOptions: EstablishmentOption[];
    clientOptions: UserOption[];
    brandOptions: UserOption[];
    evaluationOptions: UserOption[];
    readonlyFields?: IncidentReadonlyFields | null;
    onChange: (key: keyof IncidentFormValues, value: string) => void;
    onTypeChange: (typeId: string) => void;
    onSubmit: (event: FormEvent) => void;
    submitLabel: string;
    submitIcon?: ReactNode;
    actions?: ReactNode;
};

function toSelectOptions(options: UserOption[]) {
    return options.map((option) => ({
        value: String(option.id),
        label: option.label,
        color: option.color ?? null,
    }));
}

export function defaultIncidentFormValues(overrides: Partial<IncidentFormValues> = {}): IncidentFormValues {
    return {
        subject: '',
        comment: '',
        incident_status_id: '',
        incident_priority_id: '',
        incident_type_id: '',
        incident_subtype_id: '',
        origin_type: '',
        origin_id: '',
        related_type: '',
        related_id: '',
        requester_user_id: '',
        responsible_user_id: '',
        qc_responsible_user_id: '',
        control_at: '',
        ...overrides,
    };
}

export function IncidentForm({
    values,
    errors,
    processing,
    mode,
    typeWorkflow,
    incidentStatusOptions,
    incidentPriorityOptions,
    incidentTypeOptions,
    incidentSubtypeOptions,
    userOptions,
    establishmentOptions,
    clientOptions,
    brandOptions,
    evaluationOptions,
    readonlyFields = null,
    onChange,
    onTypeChange,
    onSubmit,
    submitLabel,
    submitIcon,
    actions,
}: IncidentFormProps) {
    const { t } = useTranslation();
    const hasType = Boolean(values.incident_type_id);
    const workflow = hasType ? typeWorkflow[values.incident_type_id] : null;

    const filteredSubtypeOptions = useMemo(() => {
        if (!values.incident_type_id) {
            return [];
        }

        return incidentSubtypeOptions
            .filter((option) => String(option.incident_type_id) === values.incident_type_id)
            .map((option) => ({
                value: String(option.id),
                label: option.label,
            }));
    }, [incidentSubtypeOptions, values.incident_type_id]);

    const originEntityOptions = useMemo(() => {
        if (values.origin_type === 'establishment') {
            return establishmentOptions.map((option) => ({
                value: String(option.id),
                label: option.label,
            }));
        }

        if (values.origin_type === 'company') {
            return toSelectOptions(clientOptions);
        }

        if (values.origin_type === 'brand') {
            return toSelectOptions(brandOptions);
        }

        return [];
    }, [brandOptions, clientOptions, establishmentOptions, values.origin_type]);

    const originTypeOptions = useMemo(() => {
        const kinds = workflow?.origin_options ?? [];

        return kinds.map((kind) => ({
            value: kind,
            label: t(`incidents.originKinds.${kind}`),
        }));
    }, [t, workflow?.origin_options]);

    const originLabel = values.origin_type
        ? t(`incidents.originKinds.${values.origin_type}`)
        : t('incidents.origin');

    const showOriginTypePicker = Boolean(workflow?.origin_selectable);
    const showOriginEntity = Boolean(workflow && (workflow.origin_options.length > 0 || workflow.origin_required));
    const showRelated = Boolean(workflow?.show_related);
    const originRequired = Boolean(workflow?.origin_required);

    return (
        <FieldHelpScope table="incidents">
            <form onSubmit={onSubmit} className="space-y-5 rounded-2xl border border-line bg-surface p-6 sm:p-8">
                <div className="grid gap-5 sm:grid-cols-2">
                    <Field
                        label={t('incidents.type')}
                        htmlFor="incident_type_id"
                        error={errors.incident_type_id}
                        required
                    >
                        <SearchableSelect
                            id="incident_type_id"
                            value={values.incident_type_id}
                            invalid={Boolean(errors.incident_type_id)}
                            onChange={onTypeChange}
                            emptyLabel={t('common.select')}
                            options={incidentTypeOptions.map((option) => ({
                                value: String(option.id),
                                label: option.label,
                                color: option.color ?? null,
                            }))}
                        />
                    </Field>

                    <Field
                        label={t('incidents.priority')}
                        htmlFor="incident_priority_id"
                        error={errors.incident_priority_id}
                        required
                    >
                        {hasType ? (
                            <SearchableSelect
                                id="incident_priority_id"
                                value={values.incident_priority_id}
                                invalid={Boolean(errors.incident_priority_id)}
                                onChange={(value) => onChange('incident_priority_id', value)}
                                emptyLabel={t('common.select')}
                                options={toSelectOptions(incidentPriorityOptions)}
                            />
                        ) : (
                            <p className="text-sm text-danger">{t('incidents.selectTypeFirst')}</p>
                        )}
                    </Field>

                    {showOriginTypePicker ? (
                        <Field
                            label={t('incidents.originKind')}
                            htmlFor="origin_type"
                            error={errors.origin_type}
                            required={originRequired}
                        >
                            <SearchableSelect
                                id="origin_type"
                                value={values.origin_type}
                                invalid={Boolean(errors.origin_type)}
                                onChange={(value) => {
                                    onChange('origin_type', value);
                                    onChange('origin_id', '');
                                }}
                                emptyLabel={t('common.select')}
                                options={originTypeOptions}
                            />
                        </Field>
                    ) : null}

                    <Field
                        label={t('incidents.subtype')}
                        htmlFor="incident_subtype_id"
                        error={errors.incident_subtype_id}
                        required
                        className={showOriginTypePicker ? undefined : 'sm:col-span-1'}
                    >
                        {hasType ? (
                            <SearchableSelect
                                id="incident_subtype_id"
                                value={values.incident_subtype_id}
                                invalid={Boolean(errors.incident_subtype_id)}
                                onChange={(value) => onChange('incident_subtype_id', value)}
                                emptyLabel={t('common.select')}
                                options={filteredSubtypeOptions}
                            />
                        ) : (
                            <p className="text-sm text-danger">{t('incidents.selectTypeFirst')}</p>
                        )}
                    </Field>

                    {showOriginEntity ? (
                        <Field
                            label={originLabel}
                            htmlFor="origin_id"
                            error={errors.origin_id}
                            required={originRequired}
                            className={showRelated ? undefined : 'sm:col-span-2'}
                        >
                            {hasType ? (
                                values.origin_type ? (
                                    <SearchableSelect
                                        id="origin_id"
                                        value={values.origin_id}
                                        invalid={Boolean(errors.origin_id)}
                                        onChange={(value) => onChange('origin_id', value)}
                                        emptyLabel={t('common.select')}
                                        options={originEntityOptions}
                                    />
                                ) : (
                                    <p className="text-sm text-ink-muted">{t('incidents.selectOriginKindFirst')}</p>
                                )
                            ) : (
                                <p className="text-sm text-danger">{t('incidents.selectTypeFirst')}</p>
                            )}
                        </Field>
                    ) : null}

                    {showRelated ? (
                        <Field
                            label={t('incidents.evaluation')}
                            htmlFor="related_id"
                            error={errors.related_id}
                        >
                            {hasType ? (
                                <SearchableSelect
                                    id="related_id"
                                    value={values.related_id}
                                    invalid={Boolean(errors.related_id)}
                                    onChange={(value) => {
                                        onChange('related_type', value ? 'evaluation' : '');
                                        onChange('related_id', value);
                                    }}
                                    emptyLabel={t('common.select')}
                                    options={toSelectOptions(evaluationOptions)}
                                />
                            ) : (
                                <p className="text-sm text-danger">{t('incidents.selectTypeFirst')}</p>
                            )}
                        </Field>
                    ) : null}

                    {!showOriginEntity && workflow && !workflow.origin_required ? (
                        <p className="sm:col-span-2 text-sm text-ink-muted">{t('incidents.originNotApplicable')}</p>
                    ) : null}

                    <Field
                        label={t('incidents.responsibleUser')}
                        htmlFor="responsible_user_id"
                        error={errors.responsible_user_id}
                        required
                    >
                        {hasType ? (
                            <SearchableSelect
                                id="responsible_user_id"
                                value={values.responsible_user_id}
                                invalid={Boolean(errors.responsible_user_id)}
                                onChange={(value) => onChange('responsible_user_id', value)}
                                emptyLabel={t('common.select')}
                                options={toSelectOptions(userOptions)}
                            />
                        ) : (
                            <p className="text-sm text-danger">{t('incidents.selectTypeFirst')}</p>
                        )}
                    </Field>

                    <Field
                        label={t('incidents.qcResponsibleUser')}
                        htmlFor="qc_responsible_user_id"
                        error={errors.qc_responsible_user_id}
                    >
                        <SearchableSelect
                            id="qc_responsible_user_id"
                            value={values.qc_responsible_user_id}
                            invalid={Boolean(errors.qc_responsible_user_id)}
                            onChange={(value) => onChange('qc_responsible_user_id', value)}
                            emptyLabel={t('common.select')}
                            options={toSelectOptions(userOptions)}
                        />
                    </Field>

                    <Field
                        label={t('incidents.subject')}
                        htmlFor="subject"
                        error={errors.subject}
                        required
                        className="sm:col-span-2"
                    >
                        <Input
                            id="subject"
                            value={values.subject}
                            invalid={Boolean(errors.subject)}
                            onChange={(event) => onChange('subject', event.target.value)}
                        />
                    </Field>

                    <Field
                        label={t('incidents.comment')}
                        htmlFor="comment"
                        error={errors.comment}
                        className="sm:col-span-2"
                    >
                        <textarea
                            id="comment"
                            rows={4}
                            value={values.comment}
                            onChange={(event) => onChange('comment', event.target.value)}
                            className={cn(
                                'w-full rounded-lg border bg-surface px-3 py-2 text-sm text-ink shadow-sm transition',
                                'placeholder:text-ink-muted/70',
                                'focus:border-brand focus:outline-none focus:ring-2 focus:ring-brand/20',
                                errors.comment
                                    ? 'border-danger focus:border-danger focus:ring-danger/20'
                                    : 'border-line',
                            )}
                        />
                    </Field>

                    {mode === 'edit' ? (
                        <>
                            <Field
                                label={t('incidents.status')}
                                htmlFor="incident_status_id"
                                error={errors.incident_status_id}
                            >
                                <SearchableSelect
                                    id="incident_status_id"
                                    value={values.incident_status_id}
                                    invalid={Boolean(errors.incident_status_id)}
                                    onChange={(value) => onChange('incident_status_id', value)}
                                    emptyLabel={t('common.select')}
                                    options={toSelectOptions(incidentStatusOptions)}
                                />
                            </Field>

                            <Field
                                label={t('incidents.requesterUser')}
                                htmlFor="requester_user_id"
                                error={errors.requester_user_id}
                            >
                                <SearchableSelect
                                    id="requester_user_id"
                                    value={values.requester_user_id}
                                    invalid={Boolean(errors.requester_user_id)}
                                    onChange={(value) => onChange('requester_user_id', value)}
                                    emptyLabel={t('common.select')}
                                    options={toSelectOptions(userOptions)}
                                />
                            </Field>

                            <Field label={t('incidents.controlAt')} htmlFor="control_at" error={errors.control_at}>
                                <Input
                                    id="control_at"
                                    type="datetime-local"
                                    value={values.control_at}
                                    invalid={Boolean(errors.control_at)}
                                    onChange={(event) => onChange('control_at', event.target.value)}
                                />
                            </Field>
                        </>
                    ) : null}

                    {readonlyFields ? (
                        <>
                            <Field label={t('incidents.durationSeconds')} htmlFor="duration_seconds">
                                <Input
                                    id="duration_seconds"
                                    value={
                                        readonlyFields.duration_seconds !== null
                                            ? String(readonlyFields.duration_seconds)
                                            : ''
                                    }
                                    readOnly
                                    disabled
                                />
                            </Field>
                            <Field label={t('incidents.qcDurationSeconds')} htmlFor="qc_duration_seconds">
                                <Input
                                    id="qc_duration_seconds"
                                    value={
                                        readonlyFields.qc_duration_seconds !== null
                                            ? String(readonlyFields.qc_duration_seconds)
                                            : ''
                                    }
                                    readOnly
                                    disabled
                                />
                            </Field>
                            <Field label={t('incidents.closedAt')} htmlFor="closed_at">
                                <Input
                                    id="closed_at"
                                    type="datetime-local"
                                    value={readonlyFields.closed_at ?? ''}
                                    readOnly
                                    disabled
                                />
                            </Field>
                        </>
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
