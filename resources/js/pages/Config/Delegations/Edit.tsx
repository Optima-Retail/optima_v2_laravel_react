import { FormEvent } from 'react';
import { Head, useForm } from '@inertiajs/react';
import { Save, Trash2 } from 'lucide-react';
import { useTranslation } from 'react-i18next';
import { DelegationForm } from '@/components/config/delegations/DelegationForm';
import { PageHeader } from '@/components/page/PageHeader';
import { Button } from '@/components/ui/Button';
import { confirmAction } from '@/helpers/confirm';
import { AppLayout } from '@/layouts/AppLayout';
import { delegationsService } from '@/services';
import type { DelegationFormData, UserOption } from '@/support/types/domain';

type EditDelegationProps = {
    delegation: DelegationFormData;
    companyOptions: UserOption[];
    currencyOptions: UserOption[];
    countryOptions: UserOption[];
    seriesOptions: UserOption[];
    can: {
        delete: boolean;
    };
};

function billingInfoToString(value: DelegationFormData['billing_info']): string {
    if (value === null || value === undefined) {
        return '';
    }

    if (typeof value === 'string') {
        return value;
    }

    return JSON.stringify(value, null, 2);
}

export default function EditDelegation({
    delegation,
    companyOptions,
    currencyOptions,
    countryOptions,
    seriesOptions,
    can,
}: EditDelegationProps) {
    const { t } = useTranslation();
    const form = useForm({
        name: delegation.name,
        tax_id: delegation.tax_id ?? '',
        company_id: delegation.company_id ? String(delegation.company_id) : '',
        address: delegation.address ?? '',
        currency_id: delegation.currency_id ? String(delegation.currency_id) : '',
        country_id: delegation.country_id ? String(delegation.country_id) : '',
        series_id: delegation.series_id ? String(delegation.series_id) : '',
        cost_includes_vat: delegation.cost_includes_vat,
        recovers_vat: delegation.recovers_vat,
        billing_info: billingInfoToString(delegation.billing_info),
    });

    function submit(event: FormEvent) {
        event.preventDefault();
        delegationsService.update(delegation.id, form);
    }

    async function destroyDelegation() {
        const confirmed = await confirmAction({
            title: t('common.deleteTitle', { resource: t('delegations.resource') }),
            message: t('common.deleteMessage', { name: delegation.name }),
            confirmLabel: t('common.delete'),
            tone: 'danger',
        });

        if (!confirmed) {
            return;
        }

        delegationsService.destroy(delegation.id);
    }

    return (
        <AppLayout title={t('common.editResource', { resource: t('delegations.resource') })}>
            <Head title={t('common.editItem', { name: delegation.name })} />
            <div className="w-full space-y-6">
                <PageHeader
                    eyebrow={t('delegations.title')}
                    title={t('common.editResource', { resource: t('delegations.resource') })}
                    description={t('common.updateDetails', { name: delegation.name })}
                    backHref={delegationsService.indexPath}
                    backLabel={t('common.backTo', { resource: t('delegations.resourcePlural') })}
                />

                <DelegationForm
                    values={form.data}
                    errors={form.errors}
                    processing={form.processing}
                    companyOptions={companyOptions}
                    currencyOptions={currencyOptions}
                    countryOptions={countryOptions}
                    seriesOptions={seriesOptions}
                    onChange={(key, value) => form.setData((data) => ({ ...data, [key]: value }))}
                    onSubmit={submit}
                    submitLabel={t('common.save')}
                    submitIcon={<Save className="size-4" aria-hidden />}
                    actions={
                        can.delete ? (
                            <Button type="button" variant="danger" onClick={destroyDelegation}>
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
