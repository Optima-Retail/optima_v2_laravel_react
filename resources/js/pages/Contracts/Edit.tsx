import { FormEvent, useMemo, useState } from 'react';
import { Head, router, useForm, usePage } from '@inertiajs/react';
import { Save, Trash2 } from 'lucide-react';
import { useTranslation } from 'react-i18next';
import { ContractAttachmentsPanel } from '@/components/contracts/ContractAttachmentsPanel';
import { ContractForm, contractFormValuesFromData } from '@/components/contracts/ContractForm';
import { ContractWorkOrdersPanel } from '@/components/contracts/ContractWorkOrdersPanel';
import type { EstablishmentDocumentTotalsData } from '@/components/establishments/EstablishmentDocumentTotals';
import { PageHeader } from '@/components/page/PageHeader';
import { Button } from '@/components/ui/Button';
import { TabPanel, Tabs, type TabItem } from '@/components/ui/Tabs';
import { confirmAction } from '@/helpers/confirm';
import { AppLayout } from '@/layouts/AppLayout';
import { contractsService } from '@/services';
import type { UserOption } from '@/support/types/domain/common';
import type { ContractAttachmentItem, ContractFormData } from '@/support/types/domain/contract';
import type { EstablishmentOption } from '@/support/types/domain/establishment';

const FORM_TABS = new Set(['details', 'iterations', 'aggregations']);

type EditContractProps = {
    contract: ContractFormData;
    attachments: ContractAttachmentItem[];
    workOrderTotals: EstablishmentDocumentTotalsData | null;
    companyOptions: UserOption[];
    contractStatusOptions: UserOption[];
    languageOptions: UserOption[];
    userOptions: UserOption[];
    establishmentOptions: EstablishmentOption[];
    workOrderTypeOptions: UserOption[];
    formTemplateOptions: UserOption[];
    can: {
        delete: boolean;
        view_attachments: boolean;
        upload_attachments: boolean;
        download_attachments: boolean;
        delete_attachments: boolean;
        view_work_orders: boolean;
        create_work_orders: boolean;
        update_work_orders: boolean;
        delete_work_orders: boolean;
    };
};

function tabFromUrl(url: string): string {
    try {
        const query = url.includes('?') ? url.slice(url.indexOf('?')) : '';
        const tab = new URLSearchParams(query).get('tab');

        if (
            tab === 'attachments' ||
            tab === 'work-orders' ||
            tab === 'iterations' ||
            tab === 'aggregations'
        ) {
            return tab;
        }

        return 'details';
    } catch {
        return 'details';
    }
}

export default function EditContract({
    contract,
    attachments,
    workOrderTotals,
    companyOptions,
    contractStatusOptions,
    languageOptions,
    userOptions,
    establishmentOptions,
    workOrderTypeOptions,
    formTemplateOptions,
    can,
}: EditContractProps) {
    const { t } = useTranslation();
    const { url } = usePage();
    const [activeTab, setActiveTab] = useState(() => {
        const tab = tabFromUrl(url);

        if (tab === 'attachments' && !can.view_attachments) {
            return 'details';
        }

        if (tab === 'work-orders' && (!can.view_work_orders || workOrderTotals == null)) {
            return 'details';
        }

        return tab;
    });
    const form = useForm(contractFormValuesFromData(contract));

    const tabItems = useMemo<TabItem[]>(() => {
        const items: TabItem[] = [
            { id: 'details', label: t('contracts.tabDetails') },
            { id: 'iterations', label: t('contracts.tabIterations') },
            { id: 'aggregations', label: t('contracts.tabAggregations') },
        ];

        if (can.view_work_orders && workOrderTotals != null) {
            items.push({
                id: 'work-orders',
                label: t('contracts.tabWorkOrders', { count: workOrderTotals.count }),
            });
        }

        if (can.view_attachments) {
            items.push({
                id: 'attachments',
                label: t('contracts.tabAttachments', { count: attachments.length }),
            });
        }

        return items;
    }, [attachments.length, can.view_attachments, can.view_work_orders, t, workOrderTotals]);

    function changeTab(id: string) {
        setActiveTab(id);
        router.get(
            contractsService.editPath(contract.id),
            id === 'details' ? {} : { tab: id },
            { preserveState: true, preserveScroll: true, replace: true },
        );
    }

    function submit(event: FormEvent) {
        event.preventDefault();
        contractsService.update(contract.id, form, FORM_TABS.has(activeTab) ? activeTab : 'details');
    }

    async function destroyContract() {
        const confirmed = await confirmAction({
            title: t('common.deleteTitle', { resource: t('contracts.resource') }),
            message: t('common.deleteMessage', {
                name: contract.description || contract.code || contract.id,
            }),
            confirmLabel: t('common.delete'),
            tone: 'danger',
        });

        if (!confirmed) {
            return;
        }

        contractsService.destroy(contract.id);
    }

    const formProps = {
        values: form.data,
        errors: form.errors,
        processing: form.processing,
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
        submitLabel: t('common.save'),
        submitIcon: <Save className="size-4" aria-hidden />,
        actions:
            can.delete && activeTab === 'details' ? (
                <Button type="button" variant="danger" onClick={destroyContract}>
                    <Trash2 className="size-4" aria-hidden />
                    {t('common.delete')}
                </Button>
            ) : null,
    } as const;

    return (
        <AppLayout title={t('common.editResource', { resource: t('contracts.resource') })}>
            <Head title={t('common.editItem', { name: contract.description || contract.code || contract.id })} />
            <div className="w-full space-y-6">
                <PageHeader
                    eyebrow={t('contracts.title')}
                    title={t('common.editResource', { resource: t('contracts.resource') })}
                    description={t('common.updateDetails', {
                        name: contract.description || contract.code || contract.id,
                    })}
                    backHref={contractsService.indexPath}
                    backLabel={t('common.backTo', { resource: t('contracts.resourcePlural') })}
                />

                <Tabs items={tabItems} value={activeTab} onValueChange={changeTab}>
                    <TabPanel id="details">
                        <ContractForm {...formProps} section="details" />
                    </TabPanel>

                    <TabPanel id="iterations">
                        <ContractForm {...formProps} section="iterations" />
                    </TabPanel>

                    <TabPanel id="aggregations">
                        <ContractForm {...formProps} section="aggregations" />
                    </TabPanel>

                    {can.view_work_orders && workOrderTotals != null ? (
                        <TabPanel id="work-orders">
                            <ContractWorkOrdersPanel
                                contractId={contract.id}
                                totals={workOrderTotals}
                                can={{
                                    create: can.create_work_orders,
                                    update: can.update_work_orders,
                                    delete: can.delete_work_orders,
                                }}
                            />
                        </TabPanel>
                    ) : null}

                    {can.view_attachments ? (
                        <TabPanel id="attachments">
                            <ContractAttachmentsPanel
                                contractId={contract.id}
                                attachments={attachments}
                                can={{
                                    upload_attachments: can.upload_attachments,
                                    download_attachments: can.download_attachments,
                                    delete_attachments: can.delete_attachments,
                                }}
                            />
                        </TabPanel>
                    ) : null}
                </Tabs>
            </div>
        </AppLayout>
    );
}
