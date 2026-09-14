import { FormEvent, useMemo, useState } from 'react';
import { Head, Link, useForm } from '@inertiajs/react';
import { Ban, Plus, Save, Trash2, UserPlus } from 'lucide-react';
import { useTranslation } from 'react-i18next';
import { DocumentChatPanel } from '@/components/chat/DocumentChatPanel';
import {
    defaultTechnicianRequestFormValues,
    TechnicianRequestForm,
} from '@/components/technician-requests/TechnicianRequestForm';
import { PageHeader } from '@/components/page/PageHeader';
import { Button } from '@/components/ui/Button';
import { SearchableSelect } from '@/components/ui/SearchableSelect';
import { confirmAction } from '@/helpers/confirm';
import { AppLayout } from '@/layouts/AppLayout';
import { technicianRequestsService } from '@/services';
import { toCompanySelectOptions } from '@/support/companySelect';
import type { DocumentChatPayload } from '@/support/types/domain/chat';
import type { CompanyOption } from '@/support/types/domain/common';

type Option = { id: number; label: string; color?: string | null; kind?: string };

type ScreeningItem = {
    id: number;
    code: string | null;
    status_name: string | null;
    status_color: string | null;
    created_at: string | null;
};

type TechnicianItem = {
    id: number;
    label: string;
};

type TechnicianRequestFormData = {
    id: number;
    is_screening: boolean;
    code: string | null;
    description: string | null;
    notes: string | null;
    internal_notes: string | null;
    city: string | null;
    postal_code: string | null;
    address_line: string | null;
    province_name: string | null;
    country_id: number | null;
    language_id: number | null;
    responsible_user_id: number | null;
    work_order_id: number | null;
    due_at: string | null;
    next_action_at: string | null;
    technician_request_priority_id: number | null;
    technician_request_status_id: number | null;
    service_type_ids: number[];
    technicians: TechnicianItem[];
    screenings: ScreeningItem[];
    status_is_open: boolean;
};

type EditTechnicianRequestProps = {
    technicianRequest: TechnicianRequestFormData;
    statusOptions: Option[];
    priorityOptions: Option[];
    userOptions: Option[];
    languageOptions: Option[];
    countryOptions: Option[];
    serviceTypeOptions: Option[];
    workOrderOptions: Option[];
    technicianOptions: CompanyOption[];
    chat: DocumentChatPayload | null;
    can: {
        delete: boolean;
        cancel: boolean;
        create_screening: boolean;
        manage_technicians: boolean;
        post_chat: boolean;
    };
};

function toLocalInput(value: string | null): string {
    if (!value) {
        return '';
    }

    return value.replace(' ', 'T').slice(0, 16);
}

export default function EditTechnicianRequest({
    technicianRequest,
    statusOptions,
    priorityOptions,
    userOptions,
    languageOptions,
    countryOptions,
    serviceTypeOptions,
    workOrderOptions,
    technicianOptions,
    chat,
    can,
}: EditTechnicianRequestProps) {
    const { t } = useTranslation();
    const [technicianId, setTechnicianId] = useState('');
    const form = useForm(
        defaultTechnicianRequestFormValues({
            is_screening: technicianRequest.is_screening,
            description: technicianRequest.description ?? '',
            notes: technicianRequest.notes ?? '',
            internal_notes: technicianRequest.internal_notes ?? '',
            city: technicianRequest.city ?? '',
            postal_code: technicianRequest.postal_code ?? '',
            address_line: technicianRequest.address_line ?? '',
            province_name: technicianRequest.province_name ?? '',
            country_id: technicianRequest.country_id ? String(technicianRequest.country_id) : '',
            language_id: technicianRequest.language_id ? String(technicianRequest.language_id) : '',
            responsible_user_id: technicianRequest.responsible_user_id
                ? String(technicianRequest.responsible_user_id)
                : '',
            work_order_id: technicianRequest.work_order_id ? String(technicianRequest.work_order_id) : '',
            due_at: toLocalInput(technicianRequest.due_at),
            next_action_at: toLocalInput(technicianRequest.next_action_at),
            technician_request_priority_id: technicianRequest.technician_request_priority_id
                ? String(technicianRequest.technician_request_priority_id)
                : '',
            technician_request_status_id: technicianRequest.technician_request_status_id
                ? String(technicianRequest.technician_request_status_id)
                : '',
            service_type_ids: technicianRequest.service_type_ids.map(String),
        }),
    );

    const availableTechnicians = useMemo(() => {
        const attached = new Set(technicianRequest.technicians.map((item) => item.id));

        return toCompanySelectOptions(
            technicianOptions.filter((option) => !attached.has(option.id)),
        );
    }, [technicianOptions, technicianRequest.technicians]);

    function submit(event: FormEvent) {
        event.preventDefault();
        technicianRequestsService.update(technicianRequest.id, form);
    }

    async function handleCancel() {
        const confirmed = await confirmAction({
            title: t('technicianRequests.cancelTitle'),
            message: t('technicianRequests.cancelMessage'),
            confirmLabel: t('technicianRequests.cancelAction'),
            tone: 'danger',
        });

        if (!confirmed) {
            return;
        }

        technicianRequestsService.cancel(technicianRequest.id, { preserveScroll: true });
    }

    async function handleCreateScreening() {
        const confirmed = await confirmAction({
            title: t('technicianRequests.createScreeningTitle'),
            message: t('technicianRequests.createScreeningMessage'),
            confirmLabel: t('technicianRequests.createScreening'),
        });

        if (!confirmed) {
            return;
        }

        technicianRequestsService.createScreening(technicianRequest.id);
    }

    function attachTechnician() {
        if (!technicianId) {
            return;
        }

        technicianRequestsService.attachTechnician(technicianRequest.id, Number(technicianId), {
            preserveScroll: true,
            onSuccess: () => setTechnicianId(''),
        });
    }

    async function detachTechnician(id: number, label: string) {
        const confirmed = await confirmAction({
            title: t('technicianRequests.detachTechnicianTitle'),
            message: t('technicianRequests.detachTechnicianMessage', { name: label }),
            confirmLabel: t('common.delete'),
            tone: 'danger',
        });

        if (!confirmed) {
            return;
        }

        technicianRequestsService.detachTechnician(technicianRequest.id, id, { preserveScroll: true });
    }

    async function handleDelete() {
        const confirmed = await confirmAction({
            title: t('common.deleteTitle', { resource: t('technicianRequests.resource') }),
            message: t('common.deleteMessage', {
                name: technicianRequest.code || technicianRequest.id,
            }),
            confirmLabel: t('common.delete'),
            tone: 'danger',
        });

        if (!confirmed) {
            return;
        }

        technicianRequestsService.destroy(technicianRequest.id);
    }

    return (
        <AppLayout
            title={t('common.editItem', { name: technicianRequest.code || technicianRequest.id })}
            aside={
                chat ? (
                    <DocumentChatPanel
                        documentType="technician_request"
                        documentId={technicianRequest.id}
                        initialChat={chat}
                        canPost={can.post_chat}
                    />
                ) : null
            }
        >
            <Head title={t('common.editItem', { name: technicianRequest.code || technicianRequest.id })} />
            <div className="w-full space-y-6">
                <PageHeader
                    eyebrow={t('technicianRequests.title')}
                    title={technicianRequest.code || `#${technicianRequest.id}`}
                    description={
                        technicianRequest.is_screening
                            ? t('technicianRequests.screening')
                            : t('technicianRequests.request')
                    }
                    backHref={technicianRequestsService.indexPath}
                    backLabel={t('common.backTo', { resource: t('technicianRequests.resourcePlural') })}
                    actions={
                        <div className="flex flex-wrap gap-2">
                            {can.create_screening ? (
                                <Button type="button" variant="secondary" onClick={handleCreateScreening}>
                                    <Plus className="size-4" aria-hidden />
                                    {t('technicianRequests.createScreening')}
                                </Button>
                            ) : null}
                            {can.cancel ? (
                                <Button type="button" variant="secondary" onClick={handleCancel}>
                                    <Ban className="size-4" aria-hidden />
                                    {t('technicianRequests.cancelAction')}
                                </Button>
                            ) : null}
                            {can.delete ? (
                                <Button type="button" variant="danger" onClick={handleDelete}>
                                    <Trash2 className="size-4" aria-hidden />
                                    {t('common.delete')}
                                </Button>
                            ) : null}
                        </div>
                    }
                />

                <TechnicianRequestForm
                    mode="edit"
                    values={form.data}
                    errors={form.errors}
                    processing={form.processing}
                    lockScreening
                    statusOptions={statusOptions}
                    priorityOptions={priorityOptions}
                    userOptions={userOptions}
                    languageOptions={languageOptions}
                    countryOptions={countryOptions}
                    serviceTypeOptions={serviceTypeOptions}
                    workOrderOptions={workOrderOptions}
                    onChange={(key, value) => form.setData(key, value)}
                    onSubmit={submit}
                    submitLabel={t('common.save')}
                    submitIcon={<Save className="size-4" aria-hidden />}
                />

                {!technicianRequest.is_screening ? (
                    <section className="space-y-4 rounded-2xl border border-line bg-surface p-6 sm:p-8">
                        <div>
                            <h2 className="text-base font-semibold text-ink">
                                {t('technicianRequests.screeningsTitle')}
                            </h2>
                            <p className="text-sm text-ink-muted">
                                {t('technicianRequests.screeningsDescription')}
                            </p>
                        </div>

                        {technicianRequest.screenings.length === 0 ? (
                            <p className="text-sm text-ink-muted">{t('technicianRequests.screeningsEmpty')}</p>
                        ) : (
                            <ul className="divide-y divide-line rounded-xl border border-line">
                                {technicianRequest.screenings.map((screening) => (
                                    <li
                                        key={screening.id}
                                        className="flex flex-wrap items-center justify-between gap-3 px-4 py-3"
                                    >
                                        <div>
                                            <Link
                                                href={technicianRequestsService.editPath(screening.id)}
                                                className="font-medium text-brand hover:underline"
                                            >
                                                {screening.code || `#${screening.id}`}
                                            </Link>
                                            <div className="text-xs text-ink-muted">
                                                {screening.status_name || t('common.emDash')}
                                                {screening.created_at ? ` · ${screening.created_at}` : ''}
                                            </div>
                                        </div>
                                    </li>
                                ))}
                            </ul>
                        )}
                    </section>
                ) : null}

                {can.manage_technicians ? (
                    <section className="space-y-4 rounded-2xl border border-line bg-surface p-6 sm:p-8">
                        <div>
                            <h2 className="text-base font-semibold text-ink">
                                {t('technicianRequests.techniciansTitle')}
                            </h2>
                            <p className="text-sm text-ink-muted">
                                {t('technicianRequests.techniciansDescription')}
                            </p>
                        </div>

                        <div className="flex flex-wrap items-end gap-3">
                            <div className="min-w-[16rem] flex-1">
                                <SearchableSelect
                                    value={technicianId}
                                    onChange={setTechnicianId}
                                    options={availableTechnicians}
                                    placeholder={t('technicianRequests.technicianPlaceholder')}
                                />
                            </div>
                            <Button type="button" onClick={attachTechnician} disabled={!technicianId}>
                                <UserPlus className="size-4" aria-hidden />
                                {t('technicianRequests.attachTechnician')}
                            </Button>
                        </div>

                        {technicianRequest.technicians.length === 0 ? (
                            <p className="text-sm text-ink-muted">{t('technicianRequests.techniciansEmpty')}</p>
                        ) : (
                            <ul className="divide-y divide-line rounded-xl border border-line">
                                {technicianRequest.technicians.map((technician) => (
                                    <li
                                        key={technician.id}
                                        className="flex items-center justify-between gap-3 px-4 py-3"
                                    >
                                        <span className="text-sm text-ink">{technician.label}</span>
                                        <Button
                                            type="button"
                                            variant="secondary"
                                            onClick={() => detachTechnician(technician.id, technician.label)}
                                        >
                                            <Trash2 className="size-4" aria-hidden />
                                            {t('common.remove', { label: technician.label })}
                                        </Button>
                                    </li>
                                ))}
                            </ul>
                        )}
                    </section>
                ) : null}
            </div>
        </AppLayout>
    );
}
