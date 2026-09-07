import { FormEvent } from 'react';
import { Head, useForm } from '@inertiajs/react';
import { Plus } from 'lucide-react';
import { useTranslation } from 'react-i18next';
import { WorkOrderTypeForm } from '@/components/config/work-order-types/WorkOrderTypeForm';
import { PageHeader } from '@/components/page/PageHeader';
import { AppLayout } from '@/layouts/AppLayout';
import { workOrderTypesService } from '@/services';

export default function CreateWorkOrderType() {
    const { t } = useTranslation();
    const form = useForm({
        name: '',
        code: '',
        color: '#2563eb',
    });

    function submit(event: FormEvent) {
        event.preventDefault();
        workOrderTypesService.store(form);
    }

    return (
        <AppLayout title={t('common.newItem', { resource: t('workOrderTypes.resource') })}>
            <Head title={t('common.newItem', { resource: t('workOrderTypes.resource') })} />
            <div className="w-full space-y-6">
                <PageHeader
                    eyebrow={t('workOrderTypes.title')}
                    title={t('workOrderTypes.createTitle')}
                    description={t('workOrderTypes.createDescription')}
                    backHref={workOrderTypesService.indexPath}
                    backLabel={t('common.backTo', { resource: t('workOrderTypes.resourcePlural') })}
                />

                <WorkOrderTypeForm
                    mode="create"
                    values={form.data}
                    errors={form.errors}
                    processing={form.processing}
                    onChange={(key, value) => form.setData((data) => ({ ...data, [key]: value }))}
                    onSubmit={submit}
                    submitLabel={t('workOrderTypes.createTitle')}
                    submitIcon={<Plus className="size-4" aria-hidden />}
                />
            </div>
        </AppLayout>
    );
}
