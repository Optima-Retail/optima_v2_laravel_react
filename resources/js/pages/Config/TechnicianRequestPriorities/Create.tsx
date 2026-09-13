import { FormEvent } from 'react';
import { Head, useForm } from '@inertiajs/react';
import { Plus } from 'lucide-react';
import { useTranslation } from 'react-i18next';
import { TechnicianRequestPriorityForm } from '@/components/config/technician-request-priorities/TechnicianRequestPriorityForm';
import { PageHeader } from '@/components/page/PageHeader';
import { AppLayout } from '@/layouts/AppLayout';
import { technicianRequestPrioritiesService } from '@/services';

export default function CreateTechnicianRequestPriority() {
    const { t } = useTranslation();
    const form = useForm({
        name: '',
        key: 'medium',
        color: '#FFFF00',
    });

    function submit(event: FormEvent) {
        event.preventDefault();
        technicianRequestPrioritiesService.store(form);
    }

    return (
        <AppLayout title={t('common.newItem', { resource: t('technicianRequestPriorities.resource') })}>
            <Head title={t('common.newItem', { resource: t('technicianRequestPriorities.resource') })} />
            <div className="w-full space-y-6">
                <PageHeader
                    eyebrow={t('technicianRequestPriorities.title')}
                    title={t('technicianRequestPriorities.createTitle')}
                    description={t('technicianRequestPriorities.createDescription')}
                    backHref={technicianRequestPrioritiesService.indexPath}
                    backLabel={t('common.backTo', {
                        resource: t('technicianRequestPriorities.resourcePlural'),
                    })}
                />

                <TechnicianRequestPriorityForm
                    mode="create"
                    values={form.data}
                    errors={form.errors}
                    processing={form.processing}
                    onChange={(key, value) => form.setData(key, value)}
                    onSubmit={submit}
                    submitLabel={t('technicianRequestPriorities.createTitle')}
                    submitIcon={<Plus className="size-4" aria-hidden />}
                />
            </div>
        </AppLayout>
    );
}
