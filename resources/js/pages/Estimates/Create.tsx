import { FormEvent } from 'react';
import { Head, useForm } from '@inertiajs/react';
import { Plus } from 'lucide-react';
import { useTranslation } from 'react-i18next';
import {
    EstimateForm,
    type EstimateRequesterOption,
} from '@/components/estimates/EstimateCreateForm';
import { defaultWorkOrderFormValues } from '@/components/work-orders/WorkOrderForm';
import { PageHeader } from '@/components/page/PageHeader';
import { AppLayout } from '@/layouts/AppLayout';
import { estimatesService } from '@/services';
import type { CompanyOption, UserOption } from '@/support/types/domain/common';
import type { EstablishmentOption } from '@/support/types/domain/establishment';

type CreateEstimateProps = {
    suggestedCode: string | null;
    codeIsAutomatic: boolean;
    defaultStatusId: number | null;
    defaultEstablishmentId: number | null;
    defaultContractId: number | null;
    defaultSubject: string | null;
    typeOptions: UserOption[];
    userOptions: UserOption[];
    establishmentOptions: EstablishmentOption[];
    requesterOptions: EstimateRequesterOption[];
    technicianOptions: CompanyOption[];
    articleOptions: UserOption[];
};

export default function CreateEstimate({
    defaultStatusId,
    defaultEstablishmentId,
    defaultSubject,
    typeOptions,
    userOptions,
    establishmentOptions,
    requesterOptions,
    technicianOptions,
    articleOptions,
}: CreateEstimateProps) {
    const { t } = useTranslation();
    const defaultEstablishment = defaultEstablishmentId
        ? establishmentOptions.find((option) => option.id === defaultEstablishmentId)
        : null;

    const form = useForm(
        defaultWorkOrderFormValues({
            subject: defaultSubject ?? '',
            stage: 'estimate',
            status_id: defaultStatusId ? String(defaultStatusId) : '',
            establishment_id: defaultEstablishmentId ? String(defaultEstablishmentId) : '',
            currency_id: defaultEstablishment?.currency_id ? String(defaultEstablishment.currency_id) : '',
            is_urgent: false,
        }),
    );

    function submit(event: FormEvent) {
        event.preventDefault();
        // Never send a peeked preview code — the backend allocates and increments.
        form.transform((data) => ({ ...data, code: '' }));
        estimatesService.store(form);
    }

    return (
        <AppLayout title={t('common.newItem', { resource: t('estimates.resource') })}>
            <Head title={t('common.newItem', { resource: t('estimates.resource') })} />
            <div className="w-full space-y-6">
                <PageHeader
                    eyebrow={t('estimates.title')}
                    title={t('common.createItem', { resource: t('estimates.resource') })}
                    description={t('estimates.createDescription')}
                    backHref={estimatesService.indexPath}
                    backLabel={t('common.backTo', { resource: t('estimates.resourcePlural') })}
                />

                <EstimateForm
                    mode="create"
                    section="details"
                    values={form.data}
                    errors={form.errors}
                    processing={form.processing}
                    typeOptions={typeOptions}
                    userOptions={userOptions}
                    establishmentOptions={establishmentOptions}
                    requesterOptions={requesterOptions}
                    technicianOptions={technicianOptions}
                    articleOptions={articleOptions}
                    onChange={(key, value) => form.setData(key, value)}
                    onSubmit={submit}
                    submitLabel={t('common.createItem', { resource: t('estimates.resource') })}
                    submitIcon={<Plus className="size-4" aria-hidden />}
                />
            </div>
        </AppLayout>
    );
}
