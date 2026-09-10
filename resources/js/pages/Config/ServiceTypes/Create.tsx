import { FormEvent } from 'react';
import { Head, useForm } from '@inertiajs/react';
import { Plus } from 'lucide-react';
import { useTranslation } from 'react-i18next';
import { ServiceTypeForm } from '@/components/config/service-types/ServiceTypeForm';
import { PageHeader } from '@/components/page/PageHeader';
import { AppLayout } from '@/layouts/AppLayout';
import { serviceTypesService } from '@/services';

export default function CreateServiceType() {
    const { t } = useTranslation();
    const form = useForm({
        name: '',
        code: '',
        color: '#2563eb',
    });

    function submit(event: FormEvent) {
        event.preventDefault();
        serviceTypesService.store(form);
    }

    return (
        <AppLayout title={t('common.newItem', { resource: t('serviceTypes.resource') })}>
            <Head title={t('common.newItem', { resource: t('serviceTypes.resource') })} />
            <div className="w-full space-y-6">
                <PageHeader
                    eyebrow={t('serviceTypes.title')}
                    title={t('serviceTypes.createTitle')}
                    description={t('serviceTypes.createDescription')}
                    backHref={serviceTypesService.indexPath}
                    backLabel={t('common.backTo', { resource: t('serviceTypes.resourcePlural') })}
                />

                <ServiceTypeForm
                    mode="create"
                    values={form.data}
                    errors={form.errors}
                    processing={form.processing}
                    onChange={(key, value) => form.setData((data) => ({ ...data, [key]: value }))}
                    onSubmit={submit}
                    submitLabel={t('serviceTypes.createTitle')}
                    submitIcon={<Plus className="size-4" aria-hidden />}
                />
            </div>
        </AppLayout>
    );
}
