import { FormEvent } from 'react';
import { Head, useForm } from '@inertiajs/react';
import { Plus } from 'lucide-react';
import { useTranslation } from 'react-i18next';
import { GlobalServiceTypeForm } from '@/components/config/global-service-types/GlobalServiceTypeForm';
import { PageHeader } from '@/components/page/PageHeader';
import { AppLayout } from '@/layouts/AppLayout';
import { globalServiceTypesService } from '@/services';

export default function CreateGlobalServiceType() {
    const { t } = useTranslation();
    const form = useForm({
        name: '',
        code: '',
        color: '#fcba03',
    });

    function submit(event: FormEvent) {
        event.preventDefault();
        globalServiceTypesService.store(form);
    }

    return (
        <AppLayout title={t('common.newItem', { resource: t('globalServiceTypes.resource') })}>
            <Head title={t('common.newItem', { resource: t('globalServiceTypes.resource') })} />
            <div className="w-full space-y-6">
                <PageHeader
                    eyebrow={t('globalServiceTypes.title')}
                    title={t('globalServiceTypes.createTitle')}
                    description={t('globalServiceTypes.createDescription')}
                    backHref={globalServiceTypesService.indexPath}
                    backLabel={t('common.backTo', { resource: t('globalServiceTypes.resourcePlural') })}
                />

                <GlobalServiceTypeForm
                    mode="create"
                    values={form.data}
                    errors={form.errors}
                    processing={form.processing}
                    onChange={(key, value) => form.setData((data) => ({ ...data, [key]: value }))}
                    onSubmit={submit}
                    submitLabel={t('globalServiceTypes.createTitle')}
                    submitIcon={<Plus className="size-4" aria-hidden />}
                />
            </div>
        </AppLayout>
    );
}
