import { FormEvent } from 'react';
import { Head, useForm } from '@inertiajs/react';
import { Plus } from 'lucide-react';
import { useTranslation } from 'react-i18next';
import { TechnicianIncidentStatusForm } from '@/components/config/technician-incident-statuses/TechnicianIncidentStatusForm';
import { PageHeader } from '@/components/page/PageHeader';
import { AppLayout } from '@/layouts/AppLayout';
import { technicianIncidentStatusesService } from '@/services';

export default function CreateTechnicianIncidentStatus() {
    const { t } = useTranslation();
    const form = useForm({
        name: '',
        color: '#a9cef0',
        lifecycle: 1,
        is_open: true,
    });

    function submit(event: FormEvent) {
        event.preventDefault();
        technicianIncidentStatusesService.store(form);
    }

    return (
        <AppLayout title={t('common.newItem', { resource: t('technicianIncidentStatuses.resource') })}>
            <Head title={t('common.newItem', { resource: t('technicianIncidentStatuses.resource') })} />
            <div className="w-full space-y-6">
                <PageHeader
                    eyebrow={t('nav.statuses')}
                    title={t('technicianIncidentStatuses.createTitle')}
                    description={t('technicianIncidentStatuses.createDescription')}
                    backHref={technicianIncidentStatusesService.indexPath}
                    backLabel={t('common.backTo', {
                        resource: t('technicianIncidentStatuses.resourcePlural'),
                    })}
                />

                <TechnicianIncidentStatusForm
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
                    submitLabel={t('technicianIncidentStatuses.createTitle')}
                    submitIcon={<Plus className="size-4" aria-hidden />}
                />
            </div>
        </AppLayout>
    );
}
