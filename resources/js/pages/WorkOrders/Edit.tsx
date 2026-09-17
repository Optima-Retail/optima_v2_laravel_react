import { FormEvent, useMemo, useState } from 'react';
import { Head, useForm, usePage } from '@inertiajs/react';
import { ArrowRightLeft, FileText, Save, Trash2 } from 'lucide-react';
import { useTranslation } from 'react-i18next';
import { DocumentChatPanel } from '@/components/chat/DocumentChatPanel';
import { EstimateWorkSummary } from '@/components/estimates/EstimateWorkSummary';
import { PageActionsMenu } from '@/components/page/PageActionsMenu';
import { PageHeader } from '@/components/page/PageHeader';
import { Button } from '@/components/ui/Button';
import { TabPanel, Tabs, type TabItem } from '@/components/ui/Tabs';
import { WorkOrderAttachmentsPanel } from '@/components/work-orders/WorkOrderAttachmentsPanel';
import {
    defaultWorkOrderFormValues,
    WorkOrderForm,
    type WorkOrderFormSection,
    type WorkOrderPriorityOption,
} from '@/components/work-orders/WorkOrderForm';
import { confirmAction } from '@/helpers/confirm';
import { confirmWorkOrderStatusChange } from '@/helpers/workOrderStatusChange';
import { AppLayout } from '@/layouts/AppLayout';
import { workOrdersService } from '@/services';
import type { DocumentChatPayload } from '@/support/types/domain/chat';
import type { CompanyOption, UserOption, WorkOrderArticleOption } from '@/support/types/domain/common';
import type { EstablishmentOption } from '@/support/types/domain/establishment';
import type { WorkOrderAttachmentItem, WorkOrderFormData } from '@/support/types/domain/work-order';
import type { WorkOrderStatusOption } from '@/support/types/domain/work-order-status';

type ChecklistItem = {
    id: number;
    name: string;
    completed: boolean;
};

type EditWorkOrderProps = {
    workOrder: WorkOrderFormData;
    attachments: WorkOrderAttachmentItem[];
    statusOptions: WorkOrderStatusOption[];
    typeOptions: UserOption[];
    priorityOptions: WorkOrderPriorityOption[];
    userOptions: UserOption[];
    establishmentOptions: EstablishmentOption[];
    contractOptions?: UserOption[];
    requesterOptions: UserOption[];
    technicianOptions: CompanyOption[];
    articleOptions: WorkOrderArticleOption[];
    technicianStatusOptions: UserOption[];
    attendanceTypeOptions: UserOption[];
    checklistItems: ChecklistItem[];
    fields_locked?: boolean;
    intervention_locked?: boolean;
    can_change_establishment?: boolean;
    related_estimate_url?: string | null;
    chat: DocumentChatPayload | null;
    can: {
        delete: boolean;
        update_closed: boolean;
        view_attachments: boolean;
        view_private_attachments: boolean;
        upload_attachments: boolean;
        download_attachments: boolean;
        delete_attachments: boolean;
        post_chat: boolean;
    };
};

const FORM_TABS: WorkOrderFormSection[] = ['details', 'tasks', 'technicians', 'notes', 'lines', 'checklists'];

function tabFromUrl(url: string, canViewAttachments: boolean): string {
    try {
        const query = url.includes('?') ? url.slice(url.indexOf('?')) : '';
        const tab = new URLSearchParams(query).get('tab') ?? 'details';

        if (tab === 'attachments') {
            return canViewAttachments ? 'attachments' : 'details';
        }

        return FORM_TABS.includes(tab as WorkOrderFormSection) ? tab : 'details';
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
    contractOptions = [],
    requesterOptions,
    technicianOptions,
    articleOptions,
    technicianStatusOptions,
    attendanceTypeOptions,
    checklistItems,
    chat,
    can,
    fields_locked = false,
    intervention_locked = false,
    can_change_establishment = true,
    related_estimate_url = null,
}: EditWorkOrderProps) {
    const { t } = useTranslation();
    const { url } = usePage();
    const [activeTab, setActiveTab] = useState(() => tabFromUrl(url, can.view_attachments));
    const [localChecklists, setLocalChecklists] = useState(checklistItems);
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
            contract_id: workOrder.contract_id ? String(workOrder.contract_id) : '',
            currency_id: workOrder.currency_id ? String(workOrder.currency_id) : '',
            responsible_user_id: workOrder.responsible_user_id ? String(workOrder.responsible_user_id) : '',
            requester_id: workOrder.requester_id ? String(workOrder.requester_id) : '',
            notes: workOrder.notes ?? '',
            internal_notes: workOrder.internal_notes ?? '',
            notes_alert: workOrder.notes_alert ?? false,
            internal_notes_alert: workOrder.internal_notes_alert ?? false,
            received_at: workOrder.received_at ?? '',
            intervention_at: workOrder.intervention_at ?? '',
            due_at: workOrder.due_at ?? '',
            sla_at: workOrder.sla_at ?? '',
            sla_justification: workOrder.sla_justification ?? '',
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
                quoted_at: technician.quoted_at ?? '',
                quote_total_euros:
                    technician.quote_total_euros !== null && technician.quote_total_euros !== undefined
                        ? String(technician.quote_total_euros)
                        : '',
                status_id: technician.status_id ? String(technician.status_id) : '',
                attendance_confirmation_type_id: technician.attendance_confirmation_type_id
                    ? String(technician.attendance_confirmation_type_id)
                    : '',
            })),
            tasks: (workOrder.tasks ?? []).map((task) => ({
                id: task.id,
                title: task.title ?? '',
                description: task.description ?? '',
                is_completed: task.is_completed,
            })),
        }),
    );

    const fieldsLocked = fields_locked || (workOrder.status_is_open === false && !can.update_closed);

    const tabItems = useMemo<TabItem[]>(() => {
        const items: TabItem[] = [
            { id: 'details', label: t('workOrders.tabDetails') },
            { id: 'tasks', label: t('workOrders.tabTasks') },
            { id: 'technicians', label: t('workOrders.tabTechnicians') },
            { id: 'notes', label: t('workOrders.tabNotes') },
            { id: 'lines', label: t('workOrders.tabLines') },
            { id: 'checklists', label: t('workOrders.tabChecklists') },
        ];

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

        const result = await confirmWorkOrderStatusChange({
            t,
            statusOptions,
            currentStatusId: workOrder.status_id ? String(workOrder.status_id) : '',
            nextStatusId: form.data.status_id,
        });

        if (!result.confirmed) {
            return;
        }

        form.transform((data) => ({
            ...data,
            status_justification: result.justification || null,
            checklist_completions: localChecklists
                .filter((item) => item.completed)
                .map((item) => item.id),
        }));
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

    const formProps = {
        mode: 'edit' as const,
        values: form.data,
        errors: form.errors,
        processing: form.processing,
        fieldsLocked,
        interventionLocked: intervention_locked,
        canChangeEstablishment: can_change_establishment && !fieldsLocked,
        statusOptions,
        typeOptions,
        priorityOptions,
        userOptions,
        establishmentOptions,
        contractOptions,
        requesterOptions,
        technicianOptions,
        articleOptions,
        technicianStatusOptions,
        attendanceTypeOptions,
        checklistItems: localChecklists,
        sourceLabel: workOrder.source_work_order_label,
        estimateNum: workOrder.estimate_num,
        workOrderNum: workOrder.work_order_num,
        currencyLabel: workOrder.currency_label,
        createdAt: workOrder.created_at,
        closedAt: workOrder.closed_at,
        onChange: (key: keyof typeof form.data, value: (typeof form.data)[keyof typeof form.data]) =>
            form.setData(key, value),
        onChecklistChange: (checklistId: number, completed: boolean) => {
            setLocalChecklists((prev) =>
                prev.map((item) => (item.id === checklistId ? { ...item, completed } : item)),
            );
        },
        onSubmit: submit,
        submitLabel: t('common.save'),
        submitIcon: <Save className="size-4" aria-hidden />,
        actions:
            can.delete && activeTab === 'details' ? (
                <Button type="button" variant="danger" onClick={destroyWorkOrder}>
                    <Trash2 className="size-4" aria-hidden />
                    {t('common.delete')}
                </Button>
            ) : null,
    };

    const title = [workOrder.work_order_num || workOrder.code, workOrder.subject]
        .filter(Boolean)
        .join(' - ') || String(workOrder.id);

    return (
        <AppLayout
            title={t('common.editResource', { resource: t('workOrders.resource') })}
            aside={
                chat ? (
                    <DocumentChatPanel
                        documentType="work_order"
                        documentId={workOrder.id}
                        initialChat={chat}
                        canPost={can.post_chat}
                    />
                ) : null
            }
        >
            <Head title={t('common.editItem', { name: title })} />
            <div className="w-full space-y-6">
                <PageHeader
                    eyebrow={t('workOrders.title')}
                    title={title}
                    description={t('common.updateDetails', {
                        name: workOrder.subject || workOrder.code || workOrder.id,
                    })}
                    backHref={workOrdersService.indexPath}
                    backLabel={t('common.backTo', { resource: t('workOrders.resourcePlural') })}
                    actions={
                        <div className="flex flex-wrap items-center gap-2">
                            <PageActionsMenu
                                items={[
                                    {
                                        key: 'pdf',
                                        label: t('workOrders.previewPdf'),
                                        icon: <FileText className="size-4" aria-hidden />,
                                        href: workOrdersService.pdfPath(workOrder.id),
                                        external: true,
                                    },
                                    ...(related_estimate_url
                                        ? [
                                              {
                                                  key: 'estimate',
                                                  label: t('workOrders.openEstimate'),
                                                  icon: <ArrowRightLeft className="size-4" aria-hidden />,
                                                  href: related_estimate_url,
                                              },
                                          ]
                                        : []),
                                ]}
                            />
                            <EstimateWorkSummary lines={form.data.lines} technicians={form.data.technicians} />
                        </div>
                    }
                />

                <Tabs items={tabItems} value={activeTab} onValueChange={setActiveTab}>
                    {FORM_TABS.map((section) => (
                        <TabPanel key={section} id={section}>
                            <WorkOrderForm {...formProps} section={section} />
                        </TabPanel>
                    ))}

                    {can.view_attachments ? (
                        <TabPanel id="attachments">
                            <WorkOrderAttachmentsPanel
                                workOrderId={workOrder.id}
                                attachments={attachments}
                                can={{
                                    upload_attachments: can.upload_attachments,
                                    download_attachments: can.download_attachments,
                                    delete_attachments: can.delete_attachments,
                                    view_private_attachments: can.view_private_attachments,
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
