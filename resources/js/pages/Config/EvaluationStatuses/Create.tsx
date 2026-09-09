import { FormEvent } from 'react';
import { Head, useForm } from '@inertiajs/react';
import { Plus } from 'lucide-react';
import { useTranslation } from 'react-i18next';
import { EvaluationStatusForm } from '@/components/config/evaluation-statuses/EvaluationStatusForm';
import { PageHeader } from '@/components/page/PageHeader';
import { AppLayout } from '@/layouts/AppLayout';
import { evaluationStatusesService } from '@/services';

export default function CreateEvaluationStatus() {
    const { t } = useTranslation();
    const form = useForm({
        name: '',
        color: '#a9cef0',
        lifecycle: 1,
        is_open: true,
    });

    function submit(event: FormEvent) {
        event.preventDefault();
        evaluationStatusesService.store(form);
    }

    return (
        <AppLayout title={t('common.newItem', { resource: t('evaluationStatuses.resource') })}>
            <Head title={t('common.newItem', { resource: t('evaluationStatuses.resource') })} />
            <div className="w-full space-y-6">
                <PageHeader
                    eyebrow={t('nav.statuses')}
                    title={t('evaluationStatuses.createTitle')}
                    description={t('evaluationStatuses.createDescription')}
                    backHref={evaluationStatusesService.indexPath}
                    backLabel={t('common.backTo', { resource: t('evaluationStatuses.resourcePlural') })}
                />

                <EvaluationStatusForm
                    mode="create"
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
                    submitLabel={t('evaluationStatuses.createTitle')}
                    submitIcon={<Plus className="size-4" aria-hidden />}
                />
            </div>
        </AppLayout>
    );
}
