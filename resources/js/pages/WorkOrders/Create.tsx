import { FormEvent } from 'react';
import { Head, useForm } from '@inertiajs/react';
import { Plus } from 'lucide-react';
import { useTranslation } from 'react-i18next';
import { defaultWorkOrderFormValues, WorkOrderForm } from '@/components/work-orders/WorkOrderForm';
import { PageHeader } from '@/components/page/PageHeader';
import { AppLayout } from '@/layouts/AppLayout';
import { workOrdersService } from '@/services';
import type { UserOption } from '@/support/types/domain/common';
import type { EstablishmentOption } from '@/support/types/domain/establishment';

type CreateWorkOrderProps = {
    suggestedCode: string | null;
    codeIsAutomatic: boolean;
    defaultStatusId: number | null;
    defaultEstablishmentId: number | null;
    defaultContractId: number | null;
    defaultSubject: string | null;
    statusOptions: UserOption[];
    typeOptions: UserOption[];
    priorityOptions: UserOption[];
    userOptions: UserOption[];
    establishmentOptions: EstablishmentOption[];
    contractOptions: UserOption[];
    requesterOptions: UserOption[];
    technicianOptions: UserOption[];
    articleOptions: UserOption[];
};

export default function CreateWorkOrder({
    suggestedCode,
    codeIsAutomatic,
    defaultStatusId,
    defaultEstablishmentId,
    defaultContractId,
    defaultSubject,
    statusOptions,
    typeOptions,
    priorityOptions,
    userOptions,
    establishmentOptions,
    contractOptions,
    requesterOptions,
    technicianOptions,
    articleOptions,
}: CreateWorkOrderProps) {
    const { t } = useTranslation();
    const form = useForm(
        defaultWorkOrderFormValues({
            code: suggestedCode ?? '',
            subject: defaultSubject ?? '',
            stage: 'work_order',
            status_id: defaultStatusId ? String(defaultStatusId) : '',
            establishment_id: defaultEstablishmentId ? String(defaultEstablishmentId) : '',
            contract_id: defaultContractId ? String(defaultContractId) : '',
        }),
    );

    function submit(event: FormEvent) {
        event.preventDefault();
        workOrdersService.store(form);
    }

    return (
        <AppLayout title={t('common.newItem', { resource: t('workOrders.resource') })}>
            <Head title={t('common.newItem', { resource: t('workOrders.resource') })} />
            <div className="w-full space-y-6">
                <PageHeader
                    eyebrow={t('workOrders.title')}
                    title={t('common.createItem', { resource: t('workOrders.resource') })}
                    description={t('workOrders.createDescription')}
                    backHref={workOrdersService.indexPath}
                    backLabel={t('common.backTo', { resource: t('workOrders.resourcePlural') })}
                />

                <WorkOrderForm
                    values={form.data}
                    errors={form.errors}
                    processing={form.processing}
                    codeDisabled={codeIsAutomatic}
                    stageLocked
                    statusOptions={statusOptions}
                    typeOptions={typeOptions}
                    priorityOptions={priorityOptions}
                    userOptions={userOptions}
                    establishmentOptions={establishmentOptions}
                    contractOptions={contractOptions}
                    requesterOptions={requesterOptions}
                    technicianOptions={technicianOptions}
                    articleOptions={articleOptions}
                    onChange={(key, value) => form.setData(key, value)}
                    onSubmit={submit}
                    submitLabel={t('common.createItem', { resource: t('workOrders.resource') })}
                    submitIcon={<Plus className="size-4" aria-hidden />}
                />
            </div>
        </AppLayout>
    );
}
