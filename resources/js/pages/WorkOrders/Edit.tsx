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
import { workOrdersService } from '@/services';
import type { UserOption } from '@/support/types/domain/common';
import type { EstablishmentOption } from '@/support/types/domain/establishment';
import type { WorkOrderAttachmentItem, WorkOrderFormData } from '@/support/types/domain/work-order';

type EditWorkOrderProps = {
    workOrder: WorkOrderFormData;
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

export default function EditWorkOrder({
    workOrder,
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
}: EditWorkOrderProps) {
    const { t } = useTranslation();
    const { url } = usePage();
    const [activeTab, setActiveTab] = useState(() => {
        const tab = tabFromUrl(url);

        return tab === 'attachments' && !can.view_attachments ? 'details' : tab;
    });
    const form = useForm(
        defaultWorkOrderFormValues({
            code: workOrder.code ?? '',
            subject: workOrder.subject ?? '',
            reference: workOrder.reference ?? '',
            purchase_order: workOrder.purchase_order ?? '',
            stage: 'work_order',
            status_id: workOrder.status_id ? String(workOrder.status_id) : '',
            work_order_type_id: workOrder.work_order_type_id ? String(workOrder.work_order_type_id) : '',
            client_priority_id: workOrder.client_priority_id ? String(workOrder.client_priority_id) : '',
            is_urgent: workOrder.is_urgent,
            establishment_id: workOrder.establishment_id ? String(workOrder.establishment_id) : '',
            responsible_user_id: workOrder.responsible_user_id ? String(workOrder.responsible_user_id) : '',
            requester_id: workOrder.requester_id ? String(workOrder.requester_id) : '',
            notes: workOrder.notes ?? '',
            internal_notes: workOrder.internal_notes ?? '',
            received_at: workOrder.received_at ?? '',
            intervention_at: workOrder.intervention_at ?? '',
            due_at: workOrder.due_at ?? '',
            collaborator_ids: workOrder.collaborator_ids.map(String),
            lines: workOrder.lines.map((line) => ({
                id: line.id,
                article_id: line.article_id ? String(line.article_id) : '',
                description: line.description ?? '',
                quantity: line.quantity !== null && line.quantity !== undefined ? String(line.quantity) : '1',
                unit_price: line.unit_price !== null && line.unit_price !== undefined ? String(line.unit_price) : '0',
            })),
            technicians: workOrder.technicians.map((technician) => ({
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
        const items: TabItem[] = [{ id: 'details', label: t('workOrders.tabDetails') }];

        if (can.view_attachments) {
            items.push({
                id: 'attachments',
                label: t('workOrders.tabAttachments', { count: attachments.length }),
            });
        }

        return items;
    }, [attachments.length, can.view_attachments, t]);

    async function submit(event: FormEvent) {
        event.preventDefault();

        if (form.data.status_id === '12') {
            const confirmed = await confirmAction({
                title: t('workOrders.rejectTitle'),
                message: t('workOrders.rejectMessage'),
                confirmLabel: t('workOrders.rejectConfirm'),
            });

            if (!confirmed) {
                return;
            }
        }

        workOrdersService.update(workOrder.id, form);
    }

    async function destroyWorkOrder() {
        const confirmed = await confirmAction({
            title: t('common.deleteTitle', { resource: t('workOrders.resource') }),
            message: t('common.deleteMessage', {
                name: workOrder.subject || workOrder.code || workOrder.id,
            }),
            confirmLabel: t('common.delete'),
            tone: 'danger',
        });

        if (!confirmed) {
            return;
        }

        workOrdersService.destroy(workOrder.id);
    }

    return (
        <AppLayout title={t('common.editResource', { resource: t('workOrders.resource') })}>
            <Head
                title={t('common.editItem', {
                    name: workOrder.subject || workOrder.code || workOrder.id,
                })}
            />
            <div className="w-full space-y-6">
                <PageHeader
                    eyebrow={t('workOrders.title')}
                    title={workOrder.subject || workOrder.code || String(workOrder.id)}
                    description={t('common.updateDetails', {
                        name: workOrder.subject || workOrder.code || workOrder.id,
                    })}
                    backHref={workOrdersService.indexPath}
                    backLabel={t('common.backTo', { resource: t('workOrders.resourcePlural') })}
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
                            sourceLabel={workOrder.source_work_order_label}
                            onChange={(key, value) => form.setData(key, value)}
                            onSubmit={submit}
                            submitLabel={t('common.save')}
                            submitIcon={<Save className="size-4" aria-hidden />}
                            actions={
                                can.delete ? (
                                    <Button type="button" variant="danger" onClick={destroyWorkOrder}>
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
                                workOrderId={workOrder.id}
                                attachments={attachments}
                                can={{
                                    upload_attachments: can.upload_attachments,
                                    download_attachments: can.download_attachments,
                                    delete_attachments: can.delete_attachments,
                                }}
                                onUpload={workOrdersService.storeAttachment}
                                onDestroy={workOrdersService.destroyAttachment}
                            />
                        </TabPanel>
                    ) : null}
                </Tabs>
            </div>
        </AppLayout>
    );
}
