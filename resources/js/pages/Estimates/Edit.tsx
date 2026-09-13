import { FormEvent, useMemo, useState } from 'react';
import { Head, useForm, usePage } from '@inertiajs/react';
import { Save, Trash2 } from 'lucide-react';
import { useTranslation } from 'react-i18next';
import { PageHeader } from '@/components/page/PageHeader';
import { Button } from '@/components/ui/Button';
import { TabPanel, Tabs, type TabItem } from '@/components/ui/Tabs';
import { WorkOrderAttachmentsPanel } from '@/components/work-orders/WorkOrderAttachmentsPanel';
import { defaultWorkOrderFormValues, WorkOrderForm } from '@/components/work-orders/WorkOrderForm';
import { confirmAction } from '@/helpers/confirm';
import { AppLayout } from '@/layouts/AppLayout';
import { estimatesService } from '@/services';
import type { UserOption } from '@/support/types/domain/common';
import type { EstablishmentOption } from '@/support/types/domain/establishment';
import type { WorkOrderAttachmentItem, WorkOrderFormData } from '@/support/types/domain/work-order';

type EditEstimateProps = {
    estimate: WorkOrderFormData;
    attachments: WorkOrderAttachmentItem[];
    statusOptions: UserOption[];
    typeOptions: UserOption[];
    priorityOptions: UserOption[];
    userOptions: UserOption[];
    establishmentOptions: EstablishmentOption[];
    requesterOptions: UserOption[];
    technicianOptions: UserOption[];
    articleOptions: UserOption[];
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

export default function EditEstimate({
    estimate,
    attachments,
    statusOptions,
    typeOptions,
    priorityOptions,
    userOptions,
    establishmentOptions,
    requesterOptions,
    technicianOptions,
    articleOptions,
    can,
}: EditEstimateProps) {
    const { t } = useTranslation();
    const { url } = usePage();
    const [activeTab, setActiveTab] = useState(() => {
        const tab = tabFromUrl(url);

        return tab === 'attachments' && !can.view_attachments ? 'details' : tab;
    });
    const form = useForm(
        defaultWorkOrderFormValues({
            code: estimate.code ?? '',
            subject: estimate.subject ?? '',
            reference: estimate.reference ?? '',
            purchase_order: estimate.purchase_order ?? '',
            stage: 'estimate',
            status_id: estimate.status_id ? String(estimate.status_id) : '',
            work_order_type_id: estimate.work_order_type_id ? String(estimate.work_order_type_id) : '',
            client_priority_id: estimate.client_priority_id ? String(estimate.client_priority_id) : '',
            is_urgent: estimate.is_urgent,
            establishment_id: estimate.establishment_id ? String(estimate.establishment_id) : '',
            responsible_user_id: estimate.responsible_user_id ? String(estimate.responsible_user_id) : '',
            requester_id: estimate.requester_id ? String(estimate.requester_id) : '',
            notes: estimate.notes ?? '',
            internal_notes: estimate.internal_notes ?? '',
            received_at: estimate.received_at ?? '',
            intervention_at: estimate.intervention_at ?? '',
            due_at: estimate.due_at ?? '',
            collaborator_ids: estimate.collaborator_ids.map(String),
            lines: estimate.lines.map((line) => ({
                id: line.id,
                article_id: line.article_id ? String(line.article_id) : '',
                description: line.description ?? '',
                quantity: line.quantity !== null && line.quantity !== undefined ? String(line.quantity) : '1',
                unit_price: line.unit_price !== null && line.unit_price !== undefined ? String(line.unit_price) : '0',
            })),
            technicians: estimate.technicians.map((technician) => ({
                id: technician.id,
                company_relationship_id: String(technician.company_relationship_id),
                is_selected: technician.is_selected,
                quote_net_amount:
                    technician.quote_net_amount !== null && technician.quote_net_amount !== undefined
                        ? String(technician.quote_net_amount)
                        : '',
            })),
        }),
    );

    const tabItems = useMemo<TabItem[]>(() => {
        const items: TabItem[] = [{ id: 'details', label: t('estimates.tabDetails') }];

        if (can.view_attachments) {
            items.push({
                id: 'attachments',
                label: t('estimates.tabAttachments', { count: attachments.length }),
            });
        }

        return items;
    }, [attachments.length, can.view_attachments, t]);

    async function submit(event: FormEvent) {
        event.preventDefault();

        if (form.data.status_id === '7') {
            const confirmed = await confirmAction({
                title: t('estimates.approveTitle'),
                message: t('estimates.approveMessage'),
                confirmLabel: t('estimates.approveConfirm'),
            });

            if (!confirmed) {
                return;
            }
        }

        estimatesService.update(estimate.id, form);
    }

    async function destroyEstimate() {
        const confirmed = await confirmAction({
            title: t('common.deleteTitle', { resource: t('estimates.resource') }),
            message: t('common.deleteMessage', {
                name: estimate.subject || estimate.code || estimate.id,
            }),
            confirmLabel: t('common.delete'),
            tone: 'danger',
        });

        if (!confirmed) {
            return;
        }

        estimatesService.destroy(estimate.id);
    }

    return (
        <AppLayout title={t('common.editResource', { resource: t('estimates.resource') })}>
            <Head
                title={t('common.editItem', {
                    name: estimate.subject || estimate.code || estimate.id,
                })}
            />
            <div className="w-full space-y-6">
                <PageHeader
                    eyebrow={t('estimates.title')}
                    title={estimate.subject || estimate.code || String(estimate.id)}
                    description={t('common.updateDetails', {
                        name: estimate.subject || estimate.code || estimate.id,
                    })}
                    backHref={estimatesService.indexPath}
                    backLabel={t('common.backTo', { resource: t('estimates.resourcePlural') })}
                />

                <Tabs items={tabItems} value={activeTab} onValueChange={setActiveTab}>
                    <TabPanel id="details">
                        <WorkOrderForm
                            values={form.data}
                            errors={form.errors}
                            processing={form.processing}
                            stageLocked
                            statusOptions={statusOptions}
                            typeOptions={typeOptions}
                            priorityOptions={priorityOptions}
                            userOptions={userOptions}
                            establishmentOptions={establishmentOptions}
                            requesterOptions={requesterOptions}
                            technicianOptions={technicianOptions}
                            articleOptions={articleOptions}
                            sourceLabel={estimate.source_work_order_label}
                            onChange={(key, value) => form.setData(key, value)}
                            onSubmit={submit}
                            submitLabel={t('common.save')}
                            submitIcon={<Save className="size-4" aria-hidden />}
                            actions={
                                can.delete ? (
                                    <Button type="button" variant="danger" onClick={destroyEstimate}>
                                        <Trash2 className="size-4" aria-hidden />
                                        {t('common.delete')}
                                    </Button>
                                ) : null
                            }
                        />
                    </TabPanel>

                    {can.view_attachments ? (
                        <TabPanel id="attachments">
                            <WorkOrderAttachmentsPanel
                                workOrderId={estimate.id}
                                attachments={attachments}
                                labelsNamespace="estimates"
                                can={{
                                    upload_attachments: can.upload_attachments,
                                    download_attachments: can.download_attachments,
                                    delete_attachments: can.delete_attachments,
                                }}
                                onUpload={estimatesService.storeAttachment}
                                onDestroy={estimatesService.destroyAttachment}
                            />
                        </TabPanel>
                    ) : null}
                </Tabs>
            </div>
        </AppLayout>
    );
}
