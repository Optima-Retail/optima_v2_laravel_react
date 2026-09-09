import { FormEvent } from 'react';
import { Head, useForm } from '@inertiajs/react';
import { Save, Trash2 } from 'lucide-react';
import { useTranslation } from 'react-i18next';
import { EvaluationStatusForm } from '@/components/config/evaluation-statuses/EvaluationStatusForm';
import { PageHeader } from '@/components/page/PageHeader';
import { Button } from '@/components/ui/Button';
import { confirmAction } from '@/helpers/confirm';
import { AppLayout } from '@/layouts/AppLayout';
import { evaluationStatusesService } from '@/services';
import type { EvaluationStatusFormData } from '@/support/types/domain/evaluation-status';

type EditEvaluationStatusProps = {
    evaluationStatus: EvaluationStatusFormData;
    can: {
        delete: boolean;
    };
};

export default function EditEvaluationStatus({ evaluationStatus, can }: EditEvaluationStatusProps) {
    const { t } = useTranslation();
    const form = useForm({
        name: evaluationStatus.name,
        color: evaluationStatus.color ?? '#a9cef0',
        lifecycle: evaluationStatus.lifecycle ?? '',
        is_open: evaluationStatus.is_open,
    });

    function submit(event: FormEvent) {
        event.preventDefault();
        evaluationStatusesService.update(evaluationStatus.id, form);
    }

    async function destroyStatus() {
        const confirmed = await confirmAction({
            title: t('common.deleteTitle', { resource: t('evaluationStatuses.resource') }),
            message: t('common.deleteMessage', { name: evaluationStatus.name }),
            confirmLabel: t('common.delete'),
            tone: 'danger',
        });

        if (!confirmed) {
            return;
        }

        evaluationStatusesService.destroy(evaluationStatus.id);
    }

    return (
        <AppLayout title={t('common.editResource', { resource: t('evaluationStatuses.resource') })}>
            <Head title={t('common.editItem', { name: evaluationStatus.name })} />
            <div className="w-full space-y-6">
                <PageHeader
                    eyebrow={t('nav.statuses')}
                    title={t('evaluationStatuses.editTitle')}
                    description={t('common.updateDetails', { name: evaluationStatus.name })}
                    backHref={evaluationStatusesService.indexPath}
                    backLabel={t('common.backTo', { resource: t('evaluationStatuses.resourcePlural') })}
                />

                <EvaluationStatusForm
                    mode="edit"
                    values={form.data}
                    errors={form.errors}
                    processing={form.processing}
                    onChange={(key, value) =>
                        form.setData((data) => ({
                            ...data,
                            [key]:
                                key === 'lifecycle'
                                    ? value === ''
                                        ? ''
                                        : Number(value) || value
                                    : value,
                        }))
                    }
                    onSubmit={submit}
                    submitLabel={t('common.save')}
                    submitIcon={<Save className="size-4" aria-hidden />}
                    actions={
                        can.delete ? (
                            <Button type="button" variant="danger" onClick={destroyStatus}>
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
