import { FormEvent } from 'react';
import { Head, useForm } from '@inertiajs/react';
import { Plus } from 'lucide-react';
import { useTranslation } from 'react-i18next';
import { TechnicianRequestStatusForm } from '@/components/config/technician-request-statuses/TechnicianRequestStatusForm';
import { PageHeader } from '@/components/page/PageHeader';
import { AppLayout } from '@/layouts/AppLayout';
import { technicianRequestStatusesService } from '@/services';

export default function CreateTechnicianRequestStatus() {
    const { t } = useTranslation();
    const form = useForm({
        kind: 'request',
        name: '',
        color: '#a9cef0',
        lifecycle: 1,
        is_open: true,
    });

    function submit(event: FormEvent) {
        event.preventDefault();
        technicianRequestStatusesService.store(form);
    }

    return (
        <AppLayout title={t('common.newItem', { resource: t('technicianRequestStatuses.resource') })}>
            <Head title={t('common.newItem', { resource: t('technicianRequestStatuses.resource') })} />
            <div className="w-full space-y-6">
                <PageHeader
                    eyebrow={t('nav.statuses')}
                    title={t('technicianRequestStatuses.createTitle')}
                    description={t('technicianRequestStatuses.createDescription')}
                    backHref={technicianRequestStatusesService.indexPath}
                    backLabel={t('common.backTo', {
                        resource: t('technicianRequestStatuses.resourcePlural'),
                    })}
                />

                <TechnicianRequestStatusForm
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
                    submitLabel={t('technicianRequestStatuses.createTitle')}
                    submitIcon={<Plus className="size-4" aria-hidden />}
                />
            </div>
        </AppLayout>
    );
}
