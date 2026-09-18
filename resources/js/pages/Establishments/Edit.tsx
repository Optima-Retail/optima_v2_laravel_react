import { FormEvent, useMemo } from 'react';
import { Head, useForm } from '@inertiajs/react';
import { Save, Trash2 } from 'lucide-react';
import { useTranslation } from 'react-i18next';
import type { EstablishmentDocumentTotalsData } from '@/components/establishments/EstablishmentDocumentTotals';
import { establishmentFormValuesFromData, EstablishmentForm } from '@/components/establishments/EstablishmentForm';
import { PageHeader } from '@/components/page/PageHeader';
import { Button } from '@/components/ui/Button';
import { confirmAction } from '@/helpers/confirm';
import { AppLayout } from '@/layouts/AppLayout';
import { establishmentsService } from '@/services';
import type { CompanyOption, UserOption } from '@/support/types/domain/common';
import type {
    EstablishmentAttachmentItem,
    EstablishmentFormData,
} from '@/support/types/domain/establishment';
import type { ProvinceOption } from '@/support/types/domain/province';

type EditEstablishmentProps = {
    establishment: EstablishmentFormData;
    attachments?: EstablishmentAttachmentItem[];
    workOrderTotals?: EstablishmentDocumentTotalsData | null;
    estimateTotals?: EstablishmentDocumentTotalsData | null;
    companyOptions: CompanyOption[];
    countryOptions: UserOption[];
    provinceOptions: ProvinceOption[];
    timezoneOptions: UserOption[];
    delegationOptions: UserOption[];
    languageOptions: UserOption[];
    establishmentTypeOptions: UserOption[];
    seriesOptions: UserOption[];
    userOptions: UserOption[];
    technicianOptions: CompanyOption[];
    workOrderTypeOptions: UserOption[];
    formTemplateOptions: UserOption[];
    can: {
        delete: boolean;
        view_attachments: boolean;
        upload_attachments: boolean;
        download_attachments: boolean;
        delete_attachments: boolean;
        view_private_attachments: boolean;
        view_work_orders: boolean;
        create_work_orders: boolean;
        update_work_orders: boolean;
        delete_work_orders: boolean;
        view_estimates: boolean;
        create_estimates: boolean;
        update_estimates: boolean;
        delete_estimates: boolean;
    };
};

export default function EditEstablishment({
    establishment,
    attachments,
    workOrderTotals,
    estimateTotals,
    companyOptions,
    countryOptions,
    provinceOptions,
    timezoneOptions,
    delegationOptions,
    languageOptions,
    establishmentTypeOptions,
    seriesOptions,
    userOptions,
    technicianOptions,
    workOrderTypeOptions,
    formTemplateOptions,
    can,
}: EditEstablishmentProps) {
    const { t } = useTranslation();
    const form = useForm(establishmentFormValuesFromData(establishment));

    const defaultTab = useMemo(() => {
        const tab = new URLSearchParams(window.location.search).get('tab');

        if (tab === 'work-orders' && can.view_work_orders) {
            return 'work-orders';
        }

        if (tab === 'estimates' && can.view_estimates) {
            return 'estimates';
        }

        if (tab === 'attachments' && can.view_attachments) {
            return 'attachments';
        }

        if (tab === 'templates') {
            return 'templates';
        }

        return 'identity';
    }, [can.view_attachments, can.view_estimates, can.view_work_orders]);

    function submit(event: FormEvent) {
        event.preventDefault();
        establishmentsService.update(establishment.id, form);
    }

    async function destroyEstablishment() {
        const confirmed = await confirmAction({
            title: t('common.deleteTitle', { resource: t('establishments.resource') }),
            message: t('common.deleteMessage', { name: establishment.name }),
            confirmLabel: t('common.delete'),
            tone: 'danger',
        });

        if (!confirmed) {
            return;
        }

        establishmentsService.destroy(establishment.id);
    }

    return (
        <AppLayout title={t('common.editResource', { resource: t('establishments.resource') })}>
            <Head title={t('common.editItem', { name: establishment.name })} />
            <div className="w-full space-y-6">
                <PageHeader
                    eyebrow={t('establishments.title')}
                    title={t('common.editResource', { resource: t('establishments.resource') })}
                    description={t('common.updateDetails', { name: establishment.name })}
                    backHref={establishmentsService.indexPath}
                    backLabel={t('common.backTo', { resource: t('establishments.resourcePlural') })}
                />

                <EstablishmentForm
                    values={form.data}
                    errors={form.errors}
                    processing={form.processing}
                    companyOptions={companyOptions}
                    countryOptions={countryOptions}
                    provinceOptions={provinceOptions}
                    timezoneOptions={timezoneOptions}
                    delegationOptions={delegationOptions}
                    languageOptions={languageOptions}
                    establishmentTypeOptions={establishmentTypeOptions}
                    seriesOptions={seriesOptions}
                    userOptions={userOptions}
                    technicianOptions={technicianOptions}
                    workOrderTypeOptions={workOrderTypeOptions}
                    formTemplateOptions={formTemplateOptions}
                    establishmentId={establishment.id}
                    attachments={attachments}
                    workOrderTotals={workOrderTotals}
                    estimateTotals={estimateTotals}
                    can={{
                        view_attachments: can.view_attachments,
                        upload_attachments: can.upload_attachments,
                        download_attachments: can.download_attachments,
                        delete_attachments: can.delete_attachments,
                        view_private_attachments: can.view_private_attachments,
                        view_work_orders: can.view_work_orders,
                        create_work_orders: can.create_work_orders,
                        update_work_orders: can.update_work_orders,
                        delete_work_orders: can.delete_work_orders,
                        view_estimates: can.view_estimates,
                        create_estimates: can.create_estimates,
                        update_estimates: can.update_estimates,
                        delete_estimates: can.delete_estimates,
                    }}
                    defaultTab={defaultTab}
                    onChange={(key, value) => form.setData(key, value)}
                    onSubmit={submit}
                    submitLabel={t('common.save')}
                    submitIcon={<Save className="size-4" aria-hidden />}
                    actions={
                        can.delete ? (
                            <Button type="button" variant="danger" onClick={destroyEstablishment}>
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
