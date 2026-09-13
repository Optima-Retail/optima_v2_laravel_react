import { FormEvent } from 'react';
import { Head, useForm } from '@inertiajs/react';
import { Save, Trash2 } from 'lucide-react';
import { useTranslation } from 'react-i18next';
import { WorkOrderStatusForm } from '@/components/config/work-order-statuses/WorkOrderStatusForm';
import { PageHeader } from '@/components/page/PageHeader';
import { Button } from '@/components/ui/Button';
import { confirmAction } from '@/helpers/confirm';
import { AppLayout } from '@/layouts/AppLayout';
import { workOrderStatusesService } from '@/services';
import type { WorkOrderStatusFormData } from '@/support/types/domain/work-order-status';

type EditWorkOrderStatusProps = {
    workOrderStatus: WorkOrderStatusFormData;
    can: {
        delete: boolean;
    };
};

export default function EditWorkOrderStatus({ workOrderStatus, can }: EditWorkOrderStatusProps) {
    const { t } = useTranslation();
    const form = useForm({
        name: workOrderStatus.name,
        kind: workOrderStatus.kind,
        color: workOrderStatus.color ?? '#a9cef0',
        lifecycle: workOrderStatus.lifecycle ?? '',
        is_open: workOrderStatus.is_open,
    });

    function submit(event: FormEvent) {
        event.preventDefault();
        workOrderStatusesService.update(workOrderStatus.id, form);
    }

    async function destroyStatus() {
        const confirmed = await confirmAction({
            title: t('common.deleteTitle', { resource: t('workOrderStatuses.resource') }),
            message: t('common.deleteMessage', { name: workOrderStatus.name }),
            confirmLabel: t('common.delete'),
            tone: 'danger',
        });

        if (!confirmed) {
            return;
        }

        workOrderStatusesService.destroy(workOrderStatus.id);
    }

    return (
        <AppLayout title={t('common.editResource', { resource: t('workOrderStatuses.resource') })}>
            <Head title={t('common.editItem', { name: workOrderStatus.name })} />
            <div className="w-full space-y-6">
                <PageHeader
                    eyebrow={t('nav.statuses')}
                    title={t('workOrderStatuses.editTitle')}
                    description={t('common.updateDetails', { name: workOrderStatus.name })}
                    backHref={workOrderStatusesService.indexPath}
                    backLabel={t('common.backTo', { resource: t('workOrderStatuses.resourcePlural') })}
                />

                <WorkOrderStatusForm
                    mode="edit"
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
                    submitLabel={t('common.save')}
                    submitIcon={<Save className="size-4" aria-hidden />}
                    actions={
                        can.delete ? (
                            <Button type="button" variant="danger" onClick={destroyStatus}>
                                <Trash2 className="size-4" aria-hidden />
                                {t('common.delete')}
                            </Button>
                        ) : null
                    }
                />
            </div>
        </AppLayout>
    );
}
