import { FormEvent } from 'react';
import { Head, useForm } from '@inertiajs/react';
import { Plus } from 'lucide-react';
import { useTranslation } from 'react-i18next';
import { defaultEstablishmentFormValues, EstablishmentForm } from '@/components/establishments/EstablishmentForm';
import { PageHeader } from '@/components/page/PageHeader';
import { AppLayout } from '@/layouts/AppLayout';
import { establishmentsService } from '@/services';
import type { UserOption } from '@/support/types/domain';

type CreateEstablishmentProps = {
    defaultCompanyId: number | null;
    companyOptions: UserOption[];
    countryOptions: UserOption[];
    timezoneOptions: UserOption[];
    delegationOptions: UserOption[];
    languageOptions: UserOption[];
    establishmentTypeOptions: UserOption[];
    seriesOptions: UserOption[];
    userOptions: UserOption[];
};

export default function CreateEstablishment({
    defaultCompanyId,
    companyOptions,
    countryOptions,
    timezoneOptions,
    delegationOptions,
    languageOptions,
    establishmentTypeOptions,
    seriesOptions,
    userOptions,
}: CreateEstablishmentProps) {
    const { t } = useTranslation();
    const form = useForm(
        defaultEstablishmentFormValues({
            company_id: defaultCompanyId ? String(defaultCompanyId) : '',
        }),
    );

    function submit(event: FormEvent) {
        event.preventDefault();
        establishmentsService.store(form);
    }

    return (
        <AppLayout title={t('common.newItem', { resource: t('establishments.resource') })}>
            <Head title={t('common.newItem', { resource: t('establishments.resource') })} />
            <div className="w-full space-y-6">
                <PageHeader
                    eyebrow={t('establishments.title')}
                    title={t('common.createItem', { resource: t('establishments.resource') })}
                    description={t('establishments.createDescription')}
                    backHref={establishmentsService.indexPath}
                    backLabel={t('common.backTo', { resource: t('establishments.resourcePlural') })}
                />

                <EstablishmentForm
                    values={form.data}
                    errors={form.errors}
                    processing={form.processing}
                    companyOptions={companyOptions}
                    countryOptions={countryOptions}
                    timezoneOptions={timezoneOptions}
                    delegationOptions={delegationOptions}
                    languageOptions={languageOptions}
                    establishmentTypeOptions={establishmentTypeOptions}
                    seriesOptions={seriesOptions}
                    userOptions={userOptions}
                    onChange={(key, value) => form.setData((data) => ({ ...data, [key]: value }))}
                    onSubmit={submit}
                    submitLabel={t('common.createItem', { resource: t('establishments.resource') })}
                    submitIcon={<Plus className="size-4" aria-hidden />}
                />
            </div>
        </AppLayout>
    );
}
