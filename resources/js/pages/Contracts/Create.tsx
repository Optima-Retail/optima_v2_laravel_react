import { FormEvent } from 'react';
import { Head, useForm } from '@inertiajs/react';
import { Plus } from 'lucide-react';
import { useTranslation } from 'react-i18next';
import { ContractForm, defaultContractFormValues } from '@/components/contracts/ContractForm';
import { PageHeader } from '@/components/page/PageHeader';
import { AppLayout } from '@/layouts/AppLayout';
import { contractsService } from '@/services';
import type { UserOption } from '@/support/types/domain/common';
import type { EstablishmentOption } from '@/support/types/domain/establishment';

type CreateContractProps = {
    defaultCompanyId: number | null;
    defaultContractStatusId: number | null;
    suggestedCode: string | null;
    codeIsAutomatic: boolean;
    companyOptions: UserOption[];
    contractStatusOptions: UserOption[];
    languageOptions: UserOption[];
    userOptions: UserOption[];
    establishmentOptions: EstablishmentOption[];
};

export default function CreateContract({
    defaultCompanyId,
    defaultContractStatusId,
    suggestedCode,
    codeIsAutomatic,
    companyOptions,
    contractStatusOptions,
    languageOptions,
    userOptions,
    establishmentOptions,
}: CreateContractProps) {
    const { t } = useTranslation();
    const form = useForm(
        defaultContractFormValues({
            code: suggestedCode ?? '',
            company_id: defaultCompanyId ? String(defaultCompanyId) : '',
            contract_status_id: defaultContractStatusId ? String(defaultContractStatusId) : '',
        }),
    );

    function submit(event: FormEvent) {
        event.preventDefault();
        contractsService.store(form);
    }

    return (
        <AppLayout title={t('common.newItem', { resource: t('contracts.resource') })}>
            <Head title={t('common.newItem', { resource: t('contracts.resource') })} />
            <div className="w-full space-y-6">
                <PageHeader
                    eyebrow={t('contracts.title')}
                    title={t('common.createItem', { resource: t('contracts.resource') })}
                    description={t('contracts.createDescription')}
                    backHref={contractsService.indexPath}
                    backLabel={t('common.backTo', { resource: t('contracts.resourcePlural') })}
                />

                <ContractForm
                    values={form.data}
                    errors={form.errors}
                    processing={form.processing}
                    codeDisabled={codeIsAutomatic}
                    companyOptions={companyOptions}
                    contractStatusOptions={contractStatusOptions}
                    languageOptions={languageOptions}
                    userOptions={userOptions}
                    establishmentOptions={establishmentOptions}
                    onChange={(key, value) => form.setData(key, value)}
                    onSubmit={submit}
                    submitLabel={t('common.createItem', { resource: t('contracts.resource') })}
                    submitIcon={<Plus className="size-4" aria-hidden />}
                />
            </div>
        </AppLayout>
    );
}
