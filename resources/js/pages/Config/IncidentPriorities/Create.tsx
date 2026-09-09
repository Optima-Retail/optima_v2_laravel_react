import { FormEvent } from 'react';
import { Head, useForm } from '@inertiajs/react';
import { Plus } from 'lucide-react';
import { useTranslation } from 'react-i18next';
import { IncidentPriorityForm } from '@/components/config/incident-priorities/IncidentPriorityForm';
import { PageHeader } from '@/components/page/PageHeader';
import { AppLayout } from '@/layouts/AppLayout';
import { incidentPrioritiesService } from '@/services';

export default function CreateIncidentPriority() {
    const { t } = useTranslation();
    const form = useForm({
        name: '',
        color: '#FF9999',
        resolution_time_hours: 4,
    });

    function submit(event: FormEvent) {
        event.preventDefault();
        incidentPrioritiesService.store(form);
    }

    return (
        <AppLayout title={t('common.newItem', { resource: t('incidentPriorities.resource') })}>
            <Head title={t('common.newItem', { resource: t('incidentPriorities.resource') })} />
            <div className="w-full space-y-6">
                <PageHeader
                    eyebrow={t('incidentPriorities.title')}
                    title={t('incidentPriorities.createTitle')}
                    description={t('incidentPriorities.createDescription')}
                    backHref={incidentPrioritiesService.indexPath}
                    backLabel={t('common.backTo', { resource: t('incidentPriorities.resourcePlural') })}
                />

                <IncidentPriorityForm
                    mode="create"
                    values={form.data}
                    errors={form.errors}
                    processing={form.processing}
                    onChange={(key, value) =>
                        form.setData((data) => ({
                            ...data,
                            [key]: key === 'resolution_time_hours' ? Number(value) || value : value,
                        }))
                    }
                    onSubmit={submit}
                    submitLabel={t('incidentPriorities.createTitle')}
                    submitIcon={<Plus className="size-4" aria-hidden />}
                />
            </div>
        </AppLayout>
    );
}
