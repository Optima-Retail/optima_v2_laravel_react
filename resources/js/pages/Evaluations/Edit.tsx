import { FormEvent } from 'react';
import { Head, useForm } from '@inertiajs/react';
import { Save, Trash2 } from 'lucide-react';
import { useTranslation } from 'react-i18next';
import { defaultEvaluationFormValues, EvaluationForm } from '@/components/evaluations/EvaluationForm';
import { PageHeader } from '@/components/page/PageHeader';
import { Button } from '@/components/ui/Button';
import { confirmAction } from '@/helpers/confirm';
import { AppLayout } from '@/layouts/AppLayout';
import { evaluationsService } from '@/services';
import type { UserOption } from '@/support/types/domain/common';
import type { EstablishmentOption } from '@/support/types/domain/establishment';
import type { EvaluationFormData } from '@/support/types/domain/evaluation';

type EditEvaluationProps = {
    evaluation: EvaluationFormData;
    evaluationStatusOptions: UserOption[];
    userOptions: UserOption[];
    establishmentOptions: EstablishmentOption[];
    can: {
        delete: boolean;
    };
};

export default function EditEvaluation({
    evaluation,
    evaluationStatusOptions,
    userOptions,
    establishmentOptions,
    can,
}: EditEvaluationProps) {
    const { t } = useTranslation();
    const form = useForm(
        defaultEvaluationFormValues({
            subject: evaluation.subject ?? '',
            establishment_id: evaluation.establishment_id ? String(evaluation.establishment_id) : '',
            evaluation_status_id: evaluation.evaluation_status_id ? String(evaluation.evaluation_status_id) : '',
            responsible_user_id: evaluation.responsible_user_id ? String(evaluation.responsible_user_id) : '',
            next_action_at: evaluation.next_action_at ?? '',
            facility_question: evaluation.facility_question ?? '',
            technician_question: evaluation.technician_question ?? '',
        }),
    );

    function submit(event: FormEvent) {
        event.preventDefault();
        evaluationsService.update(evaluation.id, form);
    }

    async function destroyEvaluation() {
        const confirmed = await confirmAction({
            title: t('common.deleteTitle', { resource: t('evaluations.resource') }),
            message: t('common.deleteMessage', {
                name: evaluation.subject || evaluation.public_id || evaluation.id,
            }),
            confirmLabel: t('common.delete'),
            tone: 'danger',
        });

        if (!confirmed) {
            return;
        }

        evaluationsService.destroy(evaluation.id);
    }

    return (
        <AppLayout title={t('common.editResource', { resource: t('evaluations.resource') })}>
            <Head
                title={t('common.editItem', {
                    name: evaluation.subject || evaluation.public_id || evaluation.id,
                })}
            />
            <div className="w-full space-y-6">
                <PageHeader
                    eyebrow={t('evaluations.title')}
                    title={t('common.editResource', { resource: t('evaluations.resource') })}
                    description={t('common.updateDetails', {
                        name: evaluation.subject || evaluation.public_id || evaluation.id,
                    })}
                    backHref={evaluationsService.indexPath}
                    backLabel={t('common.backTo', { resource: t('evaluations.resourcePlural') })}
                />

                <EvaluationForm
                    values={form.data}
                    errors={form.errors}
                    processing={form.processing}
                    evaluationStatusOptions={evaluationStatusOptions}
                    userOptions={userOptions}
                    establishmentOptions={establishmentOptions}
                    readonlyFields={{
                        public_id: evaluation.public_id,
                        visit_count: evaluation.visit_count,
                        call_count: evaluation.call_count,
                        qc_duration_minutes: evaluation.qc_duration_minutes,
                        first_contact_attempt_at: evaluation.first_contact_attempt_at,
                        closed_at: evaluation.closed_at,
                    }}
                    onChange={(key, value) => form.setData(key, value)}
                    onSubmit={submit}
                    submitLabel={t('common.save')}
                    submitIcon={<Save className="size-4" aria-hidden />}
                    actions={
                        can.delete ? (
                            <Button type="button" variant="danger" onClick={destroyEvaluation}>
                                <Trash2 className="size-4" aria-hidden />
                                {t('common.delete')}
                            </Button>
                        ) : null
                    }
                />
            </div>
        </AppLayout>
    );
}
