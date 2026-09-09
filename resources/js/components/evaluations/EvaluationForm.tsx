import { type FormEvent, type ReactNode } from 'react';
import { useTranslation } from 'react-i18next';
import { FieldHelpScope } from '@/components/field-help/FieldHelpScope';
import { Button } from '@/components/ui/Button';
import { Field } from '@/components/ui/Field';
import { Input } from '@/components/ui/Input';
import { SearchableSelect } from '@/components/ui/SearchableSelect';
import type { UserOption } from '@/support/types/domain/common';
import type { EstablishmentOption } from '@/support/types/domain/establishment';

export type EvaluationFormValues = {
    subject: string;
    establishment_id: string;
    evaluation_status_id: string;
    responsible_user_id: string;
    next_action_at: string;
    facility_question: string;
    technician_question: string;
};

export type EvaluationReadonlyFields = {
    public_id: string;
    visit_count: number;
    call_count: number;
    qc_duration_minutes: number | null;
    first_contact_attempt_at: string | null;
    closed_at: string | null;
};

type EvaluationFormProps = {
    values: EvaluationFormValues;
    errors: Partial<Record<keyof EvaluationFormValues, string>>;
    processing: boolean;
    evaluationStatusOptions: UserOption[];
    userOptions: UserOption[];
    establishmentOptions: EstablishmentOption[];
    readonlyFields?: EvaluationReadonlyFields | null;
    onChange: (key: keyof EvaluationFormValues, value: string) => void;
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

export function defaultEvaluationFormValues(
    overrides: Partial<EvaluationFormValues> = {},
): EvaluationFormValues {
    return {
        subject: '',
        establishment_id: '',
        evaluation_status_id: '',
        responsible_user_id: '',
        next_action_at: '',
        facility_question: '',
        technician_question: '',
        ...overrides,
    };
}

export function EvaluationForm({
    values,
    errors,
    processing,
    evaluationStatusOptions,
    userOptions,
    establishmentOptions,
    readonlyFields = null,
    onChange,
    onSubmit,
    submitLabel,
    submitIcon,
    actions,
}: EvaluationFormProps) {
    const { t } = useTranslation();

    return (
        <FieldHelpScope table="evaluations">
            <form onSubmit={onSubmit} className="space-y-5 rounded-2xl border border-line bg-surface p-6 sm:p-8">
                <div className="grid gap-5 sm:grid-cols-2">
                    <Field label={t('evaluations.subject')} htmlFor="subject" error={errors.subject} className="sm:col-span-2">
                        <Input
                            id="subject"
                            value={values.subject}
                            invalid={Boolean(errors.subject)}
                            onChange={(event) => onChange('subject', event.target.value)}
                        />
                    </Field>

                    <Field
                        label={t('evaluations.establishment')}
                        htmlFor="establishment_id"
                        error={errors.establishment_id}
                        required
                    >
                        <SearchableSelect
                            id="establishment_id"
                            value={values.establishment_id}
                            invalid={Boolean(errors.establishment_id)}
                            onChange={(value) => onChange('establishment_id', value)}
                            emptyLabel={t('common.select')}
                            options={establishmentOptions.map((option) => ({
                                value: String(option.id),
                                label: option.label,
                            }))}
                        />
                    </Field>

                    <Field
                        label={t('evaluations.status')}
                        htmlFor="evaluation_status_id"
                        error={errors.evaluation_status_id}
                    >
                        <SearchableSelect
                            id="evaluation_status_id"
                            value={values.evaluation_status_id}
                            invalid={Boolean(errors.evaluation_status_id)}
                            onChange={(value) => onChange('evaluation_status_id', value)}
                            emptyLabel={t('common.select')}
                            options={toSelectOptions(evaluationStatusOptions)}
                        />
                    </Field>

                    <Field
                        label={t('evaluations.responsibleUser')}
                        htmlFor="responsible_user_id"
                        error={errors.responsible_user_id}
                    >
                        <SearchableSelect
                            id="responsible_user_id"
                            value={values.responsible_user_id}
                            invalid={Boolean(errors.responsible_user_id)}
                            onChange={(value) => onChange('responsible_user_id', value)}
                            emptyLabel={t('common.select')}
                            options={toSelectOptions(userOptions)}
                        />
                    </Field>

                    <Field label={t('evaluations.nextActionAt')} htmlFor="next_action_at" error={errors.next_action_at}>
                        <Input
                            id="next_action_at"
                            type="datetime-local"
                            value={values.next_action_at}
                            invalid={Boolean(errors.next_action_at)}
                            onChange={(event) => onChange('next_action_at', event.target.value)}
                        />
                    </Field>

                    <Field
                        label={t('evaluations.facilityQuestion')}
                        htmlFor="facility_question"
                        error={errors.facility_question}
                        className="sm:col-span-2"
                    >
                        <Input
                            id="facility_question"
                            value={values.facility_question}
                            invalid={Boolean(errors.facility_question)}
                            onChange={(event) => onChange('facility_question', event.target.value)}
                        />
                    </Field>

                    <Field
                        label={t('evaluations.technicianQuestion')}
                        htmlFor="technician_question"
                        error={errors.technician_question}
                        className="sm:col-span-2"
                    >
                        <Input
                            id="technician_question"
                            value={values.technician_question}
                            invalid={Boolean(errors.technician_question)}
                            onChange={(event) => onChange('technician_question', event.target.value)}
                        />
                    </Field>

                    {readonlyFields ? (
                        <>
                            <Field label={t('evaluations.publicId')} htmlFor="public_id">
                                <Input id="public_id" value={readonlyFields.public_id} readOnly disabled />
                            </Field>
                            <Field label={t('evaluations.visitCount')} htmlFor="visit_count">
                                <Input id="visit_count" value={String(readonlyFields.visit_count)} readOnly disabled />
                            </Field>
                            <Field label={t('evaluations.callCount')} htmlFor="call_count">
                                <Input id="call_count" value={String(readonlyFields.call_count)} readOnly disabled />
                            </Field>
                            <Field label={t('evaluations.qcDurationMinutes')} htmlFor="qc_duration_minutes">
                                <Input
                                    id="qc_duration_minutes"
                                    value={
                                        readonlyFields.qc_duration_minutes !== null
                                            ? String(readonlyFields.qc_duration_minutes)
                                            : ''
                                    }
                                    readOnly
                                    disabled
                                />
                            </Field>
                            <Field label={t('evaluations.firstContactAttemptAt')} htmlFor="first_contact_attempt_at">
                                <Input
                                    id="first_contact_attempt_at"
                                    type="datetime-local"
                                    value={readonlyFields.first_contact_attempt_at ?? ''}
                                    readOnly
                                    disabled
                                />
                            </Field>
                            <Field label={t('evaluations.closedAt')} htmlFor="closed_at">
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
