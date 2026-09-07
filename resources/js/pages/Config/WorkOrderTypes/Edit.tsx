import { FormEvent } from 'react';
import { Head, useForm } from '@inertiajs/react';
import { Save, Trash2 } from 'lucide-react';
import { useTranslation } from 'react-i18next';
import { WorkOrderTypeForm } from '@/components/config/work-order-types/WorkOrderTypeForm';
import { PageHeader } from '@/components/page/PageHeader';
import { Button } from '@/components/ui/Button';
import { confirmAction } from '@/helpers/confirm';
import { AppLayout } from '@/layouts/AppLayout';
import { workOrderTypesService } from '@/services';
import type { WorkOrderTypeFormData } from '@/support/types/domain';

type EditWorkOrderTypeProps = {
    workOrderType: WorkOrderTypeFormData;
    can: {
        delete: boolean;
    };
};

export default function EditWorkOrderType({ workOrderType, can }: EditWorkOrderTypeProps) {
    const { t } = useTranslation();
    const form = useForm({
        name: workOrderType.name,
        code: workOrderType.code ?? '',
        color: workOrderType.color ?? '#2563eb',
    });

    function submit(event: FormEvent) {
        event.preventDefault();
        workOrderTypesService.update(workOrderType.id, form);
    }

    async function destroyType() {
        const confirmed = await confirmAction({
            title: t('common.deleteTitle', { resource: t('workOrderTypes.resource') }),
            message: t('common.deleteMessage', { name: workOrderType.name }),
            confirmLabel: t('common.delete'),
            tone: 'danger',
        });

        if (!confirmed) {
            return;
        }

        workOrderTypesService.destroy(workOrderType.id);
    }

    return (
        <AppLayout title={t('common.editResource', { resource: t('workOrderTypes.resource') })}>
            <Head title={t('common.editItem', { name: workOrderType.name })} />
            <div className="w-full space-y-6">
                <PageHeader
                    eyebrow={t('workOrderTypes.title')}
                    title={t('workOrderTypes.editTitle')}
                    description={t('common.updateDetails', { name: workOrderType.name })}
                    backHref={workOrderTypesService.indexPath}
                    backLabel={t('common.backTo', { resource: t('workOrderTypes.resourcePlural') })}
                />

                <WorkOrderTypeForm
                    mode="edit"
                    values={form.data}
                    errors={form.errors}
                    processing={form.processing}
                    onChange={(key, value) => form.setData((data) => ({ ...data, [key]: value }))}
                    onSubmit={submit}
                    submitLabel={t('common.save')}
                    submitIcon={<Save className="size-4" aria-hidden />}
                    actions={
                        can.delete ? (
                            <Button type="button" variant="danger" onClick={destroyType}>
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
