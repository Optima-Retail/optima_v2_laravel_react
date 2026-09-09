import { FormEvent } from 'react';
import { Head, useForm } from '@inertiajs/react';
import { Plus } from 'lucide-react';
import { useTranslation } from 'react-i18next';
import { IncidentStatusForm } from '@/components/config/incident-statuses/IncidentStatusForm';
import { PageHeader } from '@/components/page/PageHeader';
import { AppLayout } from '@/layouts/AppLayout';
import { incidentStatusesService } from '@/services';

export default function CreateIncidentStatus() {
    const { t } = useTranslation();
    const form = useForm({
        name: '',
        color: '#a9cef0',
        lifecycle: 1,
        is_open: true,
    });

    function submit(event: FormEvent) {
        event.preventDefault();
        incidentStatusesService.store(form);
    }

    return (
        <AppLayout title={t('common.newItem', { resource: t('incidentStatuses.resource') })}>
            <Head title={t('common.newItem', { resource: t('incidentStatuses.resource') })} />
            <div className="w-full space-y-6">
                <PageHeader
                    eyebrow={t('nav.statuses')}
                    title={t('incidentStatuses.createTitle')}
                    description={t('incidentStatuses.createDescription')}
                    backHref={incidentStatusesService.indexPath}
                    backLabel={t('common.backTo', { resource: t('incidentStatuses.resourcePlural') })}
                />

                <IncidentStatusForm
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
                    submitLabel={t('incidentStatuses.createTitle')}
                    submitIcon={<Plus className="size-4" aria-hidden />}
                />
            </div>
        </AppLayout>
    );
}
