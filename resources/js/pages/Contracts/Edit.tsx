import { FormEvent, useMemo, useState } from 'react';
import { Head, router, useForm, usePage } from '@inertiajs/react';
import { Save, Trash2 } from 'lucide-react';
import { useTranslation } from 'react-i18next';
import { ContractAttachmentsPanel } from '@/components/contracts/ContractAttachmentsPanel';
import { ContractForm, contractFormValuesFromData } from '@/components/contracts/ContractForm';
import { PageHeader } from '@/components/page/PageHeader';
import { Button } from '@/components/ui/Button';
import { TabPanel, Tabs, type TabItem } from '@/components/ui/Tabs';
import { confirmAction } from '@/helpers/confirm';
import { AppLayout } from '@/layouts/AppLayout';
import { contractsService } from '@/services';
import type { UserOption } from '@/support/types/domain/common';
import type { ContractAttachmentItem, ContractFormData } from '@/support/types/domain/contract';
import type { EstablishmentOption } from '@/support/types/domain/establishment';

type EditContractProps = {
    contract: ContractFormData;
    attachments: ContractAttachmentItem[];
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
    };
};

function tabFromUrl(url: string): string {
    try {
        const query = url.includes('?') ? url.slice(url.indexOf('?')) : '';
        const tab = new URLSearchParams(query).get('tab');

        return tab === 'attachments' ? 'attachments' : 'details';
    } catch {
        return 'details';
    }
}

export default function EditContract({
    contract,
    attachments,
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

        return tab === 'attachments' && !can.view_attachments ? 'details' : tab;
    });
    const form = useForm(contractFormValuesFromData(contract));

    const tabItems = useMemo<TabItem[]>(() => {
        const items: TabItem[] = [{ id: 'details', label: t('contracts.tabDetails') }];

        if (can.view_attachments) {
            items.push({
                id: 'attachments',
                label: t('contracts.tabAttachments', { count: attachments.length }),
            });
        }

        return items;
    }, [attachments.length, can.view_attachments, t]);

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
        contractsService.update(contract.id, form);
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
                        <ContractForm
                            values={form.data}
                            errors={form.errors}
                            processing={form.processing}
                            companyOptions={companyOptions}
                            contractStatusOptions={contractStatusOptions}
                            languageOptions={languageOptions}
                            userOptions={userOptions}
                            establishmentOptions={establishmentOptions}
                            workOrderTypeOptions={workOrderTypeOptions}
                            formTemplateOptions={formTemplateOptions}
                            onChange={(key, value) => form.setData(key, value)}
                            onSubmit={submit}
                            submitLabel={t('common.save')}
                            submitIcon={<Save className="size-4" aria-hidden />}
                            actions={
                                can.delete ? (
                                    <Button type="button" variant="danger" onClick={destroyContract}>
                                        <Trash2 className="size-4" aria-hidden />
                                        {t('common.delete')}
                                    </Button>
                                ) : null
                            }
                        />
                    </TabPanel>

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
