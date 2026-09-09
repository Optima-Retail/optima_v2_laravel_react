import { FormEvent } from 'react';
import { Head, useForm } from '@inertiajs/react';
import { Plus } from 'lucide-react';
import { useTranslation } from 'react-i18next';
import { DelegationForm } from '@/components/config/delegations/DelegationForm';
import { PageHeader } from '@/components/page/PageHeader';
import { AppLayout } from '@/layouts/AppLayout';
import { delegationsService } from '@/services';
import type { UserOption } from '@/support/types/domain/common';

type CreateDelegationProps = {
    companyOptions: UserOption[];
    currencyOptions: UserOption[];
    countryOptions: UserOption[];
    seriesOptions: UserOption[];
};

export default function CreateDelegation({
    companyOptions,
    currencyOptions,
    countryOptions,
    seriesOptions,
}: CreateDelegationProps) {
    const { t } = useTranslation();
    const form = useForm({
        name: '',
        tax_id: '',
        company_id: '',
        address: '',
        currency_id: '',
        country_id: '',
        series_id: '',
        cost_includes_vat: false,
        recovers_vat: true,
        billing_info: '',
    });

    function submit(event: FormEvent) {
        event.preventDefault();
        delegationsService.store(form);
    }

    return (
        <AppLayout title={t('common.newItem', { resource: t('delegations.resource') })}>
            <Head title={t('common.newItem', { resource: t('delegations.resource') })} />
            <div className="w-full space-y-6">
                <PageHeader
                    eyebrow={t('delegations.title')}
                    title={t('common.createItem', { resource: t('delegations.resource') })}
                    description={t('delegations.createDescription')}
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
                    submitLabel={t('common.createItem', { resource: t('delegations.resource') })}
                    submitIcon={<Plus className="size-4" aria-hidden />}
                />
            </div>
        </AppLayout>
    );
}
