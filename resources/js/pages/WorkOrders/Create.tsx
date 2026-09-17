import { FormEvent } from 'react';
import { Head, useForm } from '@inertiajs/react';
import { Plus } from 'lucide-react';
import { useTranslation } from 'react-i18next';
import { EstimateWorkSummary } from '@/components/estimates/EstimateWorkSummary';
import {
    defaultWorkOrderFormValues,
    WorkOrderForm,
    type WorkOrderPriorityOption,
} from '@/components/work-orders/WorkOrderForm';
import { PageHeader } from '@/components/page/PageHeader';
import { AppLayout } from '@/layouts/AppLayout';
import { workOrdersService } from '@/services';
import type { CompanyOption, UserOption, WorkOrderArticleOption } from '@/support/types/domain/common';
import type { EstablishmentOption } from '@/support/types/domain/establishment';
import type { WorkOrderStatusOption } from '@/support/types/domain/work-order-status';

type CreateWorkOrderProps = {
    suggestedCode: string | null;
    codeIsAutomatic: boolean;
    defaultStatusId: number | null;
    defaultEstablishmentId: number | null;
    defaultContractId: number | null;
    defaultSubject: string | null;
    statusOptions: WorkOrderStatusOption[];
    typeOptions: UserOption[];
    priorityOptions: WorkOrderPriorityOption[];
    userOptions: UserOption[];
    establishmentOptions: EstablishmentOption[];
    contractOptions?: UserOption[];
    requesterOptions: UserOption[];
    technicianOptions: CompanyOption[];
    articleOptions: WorkOrderArticleOption[];
    technicianStatusOptions?: UserOption[];
    attendanceTypeOptions?: UserOption[];
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
    contractOptions = [],
    requesterOptions,
    technicianOptions,
    articleOptions,
    technicianStatusOptions = [],
    attendanceTypeOptions = [],
}: CreateWorkOrderProps) {
    const { t } = useTranslation();
    const defaultEstablishment = defaultEstablishmentId
        ? establishmentOptions.find((option) => option.id === defaultEstablishmentId)
        : null;

    const form = useForm(
        defaultWorkOrderFormValues({
            code: suggestedCode ?? '',
            subject: defaultSubject ?? '',
            stage: 'work_order',
            status_id: defaultStatusId ? String(defaultStatusId) : '',
            establishment_id: defaultEstablishmentId ? String(defaultEstablishmentId) : '',
            contract_id: defaultContractId ? String(defaultContractId) : '',
            currency_id: defaultEstablishment?.currency_id ? String(defaultEstablishment.currency_id) : '',
        }),
    );

    function submit(event: FormEvent) {
        event.preventDefault();
        if (codeIsAutomatic) {
            form.transform((data) => ({ ...data, code: '' }));
        }
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
                    actions={<EstimateWorkSummary lines={form.data.lines} technicians={form.data.technicians} />}
                />

                <WorkOrderForm
                    mode="create"
                    section="all"
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
                    technicianStatusOptions={technicianStatusOptions}
                    attendanceTypeOptions={attendanceTypeOptions}
                    onChange={(key, value) => form.setData(key, value)}
                    onSubmit={submit}
                    submitLabel={t('common.createItem', { resource: t('workOrders.resource') })}
                    submitIcon={<Plus className="size-4" aria-hidden />}
                />
            </div>
        </AppLayout>
    );
}
