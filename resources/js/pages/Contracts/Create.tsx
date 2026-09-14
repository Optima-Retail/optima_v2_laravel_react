import { FormEvent, useMemo, useState } from 'react';
import { Head, useForm } from '@inertiajs/react';
import { Plus } from 'lucide-react';
import { useTranslation } from 'react-i18next';
import { ContractForm, defaultContractFormValues } from '@/components/contracts/ContractForm';
import { PageHeader } from '@/components/page/PageHeader';
import { TabPanel, Tabs, type TabItem } from '@/components/ui/Tabs';
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
    workOrderTypeOptions: UserOption[];
    formTemplateOptions: UserOption[];
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
    workOrderTypeOptions,
    formTemplateOptions,
}: CreateContractProps) {
    const { t } = useTranslation();
    const [activeTab, setActiveTab] = useState('details');
    const form = useForm(
        defaultContractFormValues({
            code: suggestedCode ?? '',
            company_id: defaultCompanyId ? String(defaultCompanyId) : '',
            contract_status_id: defaultContractStatusId ? String(defaultContractStatusId) : '',
        }),
    );

    const tabItems = useMemo<TabItem[]>(
        () => [
            { id: 'details', label: t('contracts.tabDetails') },
            { id: 'iterations', label: t('contracts.tabIterations') },
            { id: 'aggregations', label: t('contracts.tabAggregations') },
        ],
        [t],
    );

    function submit(event: FormEvent) {
        event.preventDefault();
        contractsService.store(form);
    }

    const formProps = {
        values: form.data,
        errors: form.errors,
        processing: form.processing,
        codeDisabled: codeIsAutomatic,
        companyOptions,
        contractStatusOptions,
        languageOptions,
        userOptions,
        establishmentOptions,
        workOrderTypeOptions,
        formTemplateOptions,
        onChange: (key: keyof typeof form.data, value: (typeof form.data)[keyof typeof form.data]) =>
            form.setData(key, value),
        onSubmit: submit,
        submitLabel: t('common.createItem', { resource: t('contracts.resource') }),
        submitIcon: <Plus className="size-4" aria-hidden />,
    } as const;

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

                <Tabs items={tabItems} value={activeTab} onValueChange={setActiveTab}>
                    <TabPanel id="details">
                        <ContractForm {...formProps} section="details" />
                    </TabPanel>
                    <TabPanel id="iterations">
                        <ContractForm {...formProps} section="iterations" />
                    </TabPanel>
                    <TabPanel id="aggregations">
                        <ContractForm {...formProps} section="aggregations" />
                    </TabPanel>
                </Tabs>
            </div>
        </AppLayout>
    );
}
