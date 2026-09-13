import { FormEvent } from 'react';
import { Head, useForm } from '@inertiajs/react';
import { Plus } from 'lucide-react';
import { useTranslation } from 'react-i18next';
import { WorkOrderStatusForm } from '@/components/config/work-order-statuses/WorkOrderStatusForm';
import { PageHeader } from '@/components/page/PageHeader';
import { AppLayout } from '@/layouts/AppLayout';
import { workOrderStatusesService } from '@/services';

export default function CreateWorkOrderStatus() {
    const { t } = useTranslation();
    const form = useForm({
        name: '',
        kind: 'work_order',
        color: '#a9cef0',
        lifecycle: 1,
        is_open: true,
    });

    function submit(event: FormEvent) {
        event.preventDefault();
        workOrderStatusesService.store(form);
    }

    return (
        <AppLayout title={t('common.newItem', { resource: t('workOrderStatuses.resource') })}>
            <Head title={t('common.newItem', { resource: t('workOrderStatuses.resource') })} />
            <div className="w-full space-y-6">
                <PageHeader
                    eyebrow={t('nav.statuses')}
                    title={t('workOrderStatuses.createTitle')}
                    description={t('workOrderStatuses.createDescription')}
                    backHref={workOrderStatusesService.indexPath}
                    backLabel={t('common.backTo', { resource: t('workOrderStatuses.resourcePlural') })}
                />

                <WorkOrderStatusForm
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
                    submitLabel={t('workOrderStatuses.createTitle')}
                    submitIcon={<Plus className="size-4" aria-hidden />}
                />
            </div>
        </AppLayout>
    );
}
