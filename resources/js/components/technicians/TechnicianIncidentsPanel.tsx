import { FormEvent, useEffect, useRef, useState } from 'react';
import { Link } from '@inertiajs/react';
import { BadgeCheck, FileText, Paperclip, Plus, Send } from 'lucide-react';
import { useTranslation } from 'react-i18next';
import { Button } from '@/components/ui/Button';
import { Field } from '@/components/ui/Field';
import { BaseModal } from '@/components/ui/BaseModal';
import { RichTextEditor, RichTextHtml } from '@/components/ui/RichTextEditor';
import { SearchableSelect } from '@/components/ui/SearchableSelect';
import {
    technicianIncidentsService,
    type TechnicianIncidentDetail,
    type TechnicianIncidentListItem,
    type TechnicianIncidentMessage,
    type TechnicianIncidentStatusOption,
} from '@/services/technicianIncidents';
import { cn } from '@/support/cn';
import { formatDate, formatDateTime } from '@/support/datetime';
import { isEmptyRichText, normalizeRichText } from '@/support/richText';

/** Legacy TecnicoIncidenciaTipoEnum::NEGOCIACION */
const NEGOTIATION_TYPE_ID = 2;

type TechnicianIncidentsPanelProps = {
    relationshipId: number;
    selectedIncidentId?: number | null;
    canPostMessages?: boolean;
    canCreate?: boolean;
    canVerify?: boolean;
    canUpdateStatus?: boolean;
};

function MetaRow({ label, value }: { label: string; value: string }) {
    return (
        <div className="grid gap-1 sm:grid-cols-[9rem_minmax(0,1fr)] sm:items-baseline">
            <dt className="text-sm text-ink-muted">{label}</dt>
            <dd className="text-sm font-medium text-ink break-words">{value}</dd>
        </div>
    );
}

function initials(name: string | null): string {
    if (!name) {
        return '?';
    }

    const parts = name.trim().split(/\s+/).slice(0, 2);

    return parts.map((part) => part.charAt(0).toUpperCase()).join('') || '?';
}

export function TechnicianIncidentsPanel({
    relationshipId,
    selectedIncidentId = null,
    canPostMessages = false,
    canCreate = false,
    canVerify = false,
    canUpdateStatus = false,
}: TechnicianIncidentsPanelProps) {
    const { t, i18n } = useTranslation();
    const messagesRef = useRef<HTMLDivElement>(null);
    const fileRef = useRef<HTMLInputElement>(null);
    const [items, setItems] = useState<TechnicianIncidentListItem[]>([]);
    const [loading, setLoading] = useState(true);
    const [error, setError] = useState<string | null>(null);
    const [activeId, setActiveId] = useState<number | null>(selectedIncidentId);
    const [detail, setDetail] = useState<TechnicianIncidentDetail | null>(null);
    const [statusOptions, setStatusOptions] = useState<TechnicianIncidentStatusOption[]>([]);
    const [messages, setMessages] = useState<TechnicianIncidentMessage[]>([]);
    const [detailLoading, setDetailLoading] = useState(false);
    const [messageBody, setMessageBody] = useState('');
    const [posting, setPosting] = useState(false);
    const [verifying, setVerifying] = useState(false);
    const [statusSaving, setStatusSaving] = useState(false);
    const [verifyOpen, setVerifyOpen] = useState(false);
    const [verifyError, setVerifyError] = useState<string | null>(null);
    const [responseText, setResponseText] = useState('');
    const [negotiationSucceeded, setNegotiationSucceeded] = useState<boolean | null>(null);
    const [negotiationSolution, setNegotiationSolution] = useState('');

    useEffect(() => {
        let cancelled = false;

        async function load() {
            setLoading(true);
            setError(null);

            try {
                const response = await fetch(
                    `${technicianIncidentsService.dataPathForTechnician(relationshipId)}?page=1&size=50`,
                    {
                        credentials: 'same-origin',
                        headers: {
                            Accept: 'application/json',
                            'X-Requested-With': 'XMLHttpRequest',
                        },
                    },
                );

                if (!response.ok) {
                    throw new Error(`Request failed (${response.status})`);
                }

                const payload = (await response.json()) as { data: TechnicianIncidentListItem[] };
                const list = payload.data ?? [];

                if (!cancelled) {
                    setItems(list);

                    if (!selectedIncidentId && list.length > 0) {
                        setActiveId((current) => current ?? list[0]!.id);
                    }
                }
            } catch {
                if (!cancelled) {
                    setError(t('technicianIncidents.loadFailed'));
                }
            } finally {
                if (!cancelled) {
                    setLoading(false);
                }
            }
        }

        void load();

        return () => {
            cancelled = true;
        };
    }, [relationshipId, selectedIncidentId, t]);

    useEffect(() => {
        if (selectedIncidentId) {
            setActiveId(selectedIncidentId);
        }
    }, [selectedIncidentId]);

    useEffect(() => {
        if (!activeId) {
            setDetail(null);
            setMessages([]);
            setStatusOptions([]);

            return;
        }

        let cancelled = false;

        async function loadDetail() {
            setDetailLoading(true);

            try {
                const payload = await technicianIncidentsService.fetchDetail(activeId!);

                if (!cancelled) {
                    setDetail(payload.incident);
                    setMessages(payload.messages);
                    setStatusOptions(payload.statusOptions ?? []);
                    setResponseText(payload.incident.response_text ?? '');
                    setNegotiationSucceeded(payload.incident.negotiation_succeeded);
                    setNegotiationSolution(payload.incident.unsuccessful_negotiation_solution ?? '');
                    setVerifyError(null);
                    setVerifyOpen(false);
                }
            } catch {
                if (!cancelled) {
                    setDetail(null);
                    setMessages([]);
                }
            } finally {
                if (!cancelled) {
                    setDetailLoading(false);
                }
            }
        }

        void loadDetail();

        return () => {
            cancelled = true;
        };
    }, [activeId]);

    useEffect(() => {
        if (detailLoading) {
            return;
        }

        let cancelled = false;
        const frame = window.requestAnimationFrame(() => {
            window.requestAnimationFrame(() => {
                if (cancelled) {
                    return;
                }

                const el = messagesRef.current;
                if (el) {
                    el.scrollTop = el.scrollHeight;
                }
            });
        });

        return () => {
            cancelled = true;
            window.cancelAnimationFrame(frame);
        };
    }, [messages, detailLoading, activeId]);

    function syncListItem(incident: TechnicianIncidentDetail) {
        setItems((current) =>
            current.map((item) =>
                item.id === incident.id
                    ? {
                          ...item,
                          status_id: incident.status_id,
                          status_name: incident.status_name,
                          status_color: incident.status_color,
                          is_verified: incident.is_verified,
                          type_name: incident.type_name,
                      }
                    : item,
            ),
        );
    }

    async function submitMessage(event: FormEvent) {
        event.preventDefault();

        if (!activeId || posting) {
            return;
        }

        const html = normalizeRichText(messageBody);

        if (isEmptyRichText(html)) {
            return;
        }

        setPosting(true);

        try {
            const message = await technicianIncidentsService.postMessage(activeId, { body: html });
            setMessages((current) => [...current, message]);
            setMessageBody('');
        } finally {
            setPosting(false);
        }
    }

    async function submitFile(file: File | null | undefined) {
        if (!activeId || !file || posting) {
            return;
        }

        setPosting(true);

        try {
            const message = await technicianIncidentsService.postMessage(activeId, { file });
            setMessages((current) => [...current, message]);
        } finally {
            setPosting(false);

            if (fileRef.current) {
                fileRef.current.value = '';
            }
        }
    }

    function renderAttachment(message: TechnicianIncidentMessage) {
        const label = message.download_name ?? t('technicianIncidents.messagesDownload');

        if (message.type === 'image' && message.preview_url) {
            return (
                <a href={message.preview_url} target="_blank" rel="noreferrer" className="block">
                    <img
                        src={message.preview_url}
                        alt={label}
                        className="max-h-48 rounded-lg object-contain"
                        onLoad={() => {
                            const el = messagesRef.current;
                            if (el) {
                                el.scrollTop = el.scrollHeight;
                            }
                        }}
                    />
                </a>
            );
        }

        if (message.preview_url) {
            return (
                <a
                    href={message.preview_url}
                    className="inline-flex items-center gap-2 font-medium text-brand hover:text-brand-strong"
                >
                    <FileText className="size-4 shrink-0" aria-hidden />
                    {label}
                </a>
            );
        }

        return (
            <span className="inline-flex items-center gap-2 text-ink-muted">
                <FileText className="size-4 shrink-0" aria-hidden />
                {label}
            </span>
        );
    }

    async function handleStatusChange(statusId: string) {
        if (!activeId || !detail || !statusId || Number(statusId) === detail.status_id) {
            return;
        }

        setStatusSaving(true);

        try {
            const payload = await technicianIncidentsService.updateStatus(activeId, Number(statusId));
            setDetail(payload.incident);
            setStatusOptions(payload.statusOptions ?? []);
            syncListItem(payload.incident);
        } finally {
            setStatusSaving(false);
        }
    }

    function openVerifyModal() {
        if (!detail) {
            return;
        }

        setResponseText(detail.response_text ?? '');
        setNegotiationSucceeded(detail.negotiation_succeeded);
        setNegotiationSolution(detail.unsuccessful_negotiation_solution ?? '');
        setVerifyError(null);
        setVerifyOpen(true);
    }

    async function submitVerify() {
        if (!activeId || !detail || detail.is_verified) {
            return;
        }

        const isNegotiationType = detail.technician_incident_type_id === NEGOTIATION_TYPE_ID;

        if (isNegotiationType && negotiationSucceeded === null) {
            setVerifyError(t('technicianIncidents.negotiationSucceededRequired'));

            return;
        }

        setVerifying(true);
        setVerifyError(null);

        try {
            const payload = await technicianIncidentsService.verify(activeId, {
                response_text: responseText.trim() || null,
                negotiation_succeeded: isNegotiationType ? negotiationSucceeded : null,
                unsuccessful_negotiation_solution:
                    isNegotiationType && negotiationSucceeded === false
                        ? negotiationSolution.trim() || null
                        : null,
            });

            setDetail(payload.incident);
            setStatusOptions(payload.statusOptions ?? []);
            syncListItem(payload.incident);
            setResponseText(payload.incident.response_text ?? '');
            setNegotiationSucceeded(payload.incident.negotiation_succeeded);
            setNegotiationSolution(payload.incident.unsuccessful_negotiation_solution ?? '');
            setVerifyOpen(false);
        } catch (err) {
            setVerifyError(err instanceof Error ? err.message : t('technicianIncidents.verifyFailed'));
        } finally {
            setVerifying(false);
        }
    }

    const createHref = technicianIncidentsService.createPathForTechnician(relationshipId);
    const canShowVerify = Boolean(canVerify && detail && !detail.is_verified);
    const isNegotiation = detail?.technician_incident_type_id === NEGOTIATION_TYPE_ID;
    const canEditStatus = Boolean(canUpdateStatus && detail);

    if (loading) {
        return <p className="text-sm text-ink-muted">{t('common.loading')}</p>;
    }

    if (error) {
        return <p className="text-sm text-danger">{error}</p>;
    }

    if (items.length === 0) {
        return (
            <div className="flex flex-wrap items-center justify-between gap-3 rounded-2xl border border-line bg-surface p-6">
                <p className="text-sm text-ink-muted">{t('technicianIncidents.emptyForTechnician')}</p>
                {canCreate ? (
                    <Link
                        href={createHref}
                        className="inline-flex h-8 items-center justify-center gap-1.5 rounded-lg bg-brand px-3 text-sm font-semibold text-white transition-colors hover:bg-brand-strong"
                    >
                        <Plus className="size-4" aria-hidden />
                        {t('common.newItem', { resource: t('technicianIncidents.resource') })}
                    </Link>
                ) : null}
            </div>
        );
    }

    return (
        <div className="space-y-4 rounded-2xl border border-line bg-surface p-5 sm:p-6">
            <div className="flex flex-wrap items-center justify-between gap-3">
                <div>
                    <h2 className="text-base font-semibold text-ink">{t('technicianIncidents.resourcePlural')}</h2>
                    <p className="mt-1 text-sm text-ink-muted">{t('technicianIncidents.description')}</p>
                </div>
                {canCreate ? (
                    <Link
                        href={createHref}
                        className="inline-flex h-8 items-center justify-center gap-1.5 rounded-lg bg-brand px-3 text-sm font-semibold text-white transition-colors hover:bg-brand-strong"
                    >
                        <Plus className="size-4" aria-hidden />
                        {t('common.create')}
                    </Link>
                ) : null}
            </div>

            <div className="grid h-[min(42rem,75vh)] min-h-[36rem] overflow-hidden rounded-xl border border-line lg:grid-cols-[minmax(13rem,16rem)_minmax(0,1fr)_minmax(0,1.15fr)]">
                <aside className="flex min-h-0 min-w-0 flex-col border-b border-line bg-canvas lg:border-b-0 lg:border-r">
                    <div className="border-b border-line px-3 py-3">
                        <h3 className="text-sm font-semibold text-ink">
                            {t('technicianIncidents.resourcePlural')}
                            <span className="ml-1.5 font-normal text-ink-muted">({items.length})</span>
                        </h3>
                    </div>
                    <div className="app-scroll max-h-56 flex-1 overflow-y-auto lg:max-h-none">
                        <ul>
                            {items.map((item) => {
                                const selected = item.id === activeId;
                                const listDate =
                                    formatDate(item.due_at, i18n.language) ||
                                    formatDate(item.created_at, i18n.language) ||
                                    t('common.emDash');

                                return (
                                    <li key={item.id} className="border-b border-line last:border-b-0">
                                        <button
                                            type="button"
                                            onClick={() => setActiveId(item.id)}
                                            className={cn(
                                                'flex min-h-16 w-full flex-col justify-center gap-1 px-3 py-3 text-left transition-colors',
                                                selected
                                                    ? 'bg-brand-soft text-brand'
                                                    : 'text-ink hover:bg-surface',
                                            )}
                                        >
                                            <span className="truncate text-sm font-semibold">
                                                #{item.id} · {item.type_name || t('technicianIncidents.resource')}
                                            </span>
                                            <span
                                                className={cn(
                                                    'truncate text-xs',
                                                    selected ? 'text-brand/80' : 'text-ink-muted',
                                                )}
                                            >
                                                {listDate}
                                            </span>
                                        </button>
                                    </li>
                                );
                            })}
                        </ul>
                    </div>
                </aside>

                {!activeId ? (
                    <div className="col-span-full flex items-center justify-center px-4 py-10 lg:col-span-2">
                        <p className="text-sm text-ink-muted">{t('technicianIncidents.selectIncident')}</p>
                    </div>
                ) : detailLoading ? (
                    <div className="col-span-full flex items-center justify-center px-4 py-10 lg:col-span-2">
                        <p className="text-sm text-ink-muted">{t('common.loading')}</p>
                    </div>
                ) : detail ? (
                    <>
                        <section className="app-scroll min-h-0 min-w-0 space-y-5 overflow-y-auto border-b border-line p-4 lg:border-b-0 lg:border-r">
                            <div className="flex flex-wrap items-center justify-between gap-3">
                                <div className="min-w-0">
                                    <h3 className="text-lg font-semibold text-ink">
                                        {detail.type_name || t('technicianIncidents.resource')}
                                        <span className="ml-2 font-normal text-ink-muted">#{detail.id}</span>
                                    </h3>
                                    {detail.is_verified ? (
                                        <p className="mt-1 inline-flex items-center gap-1.5 text-sm font-medium text-brand">
                                            <BadgeCheck className="size-4" aria-hidden />
                                            {t('technicianIncidents.alreadyVerified')}
                                        </p>
                                    ) : null}
                                </div>

                                <div className="flex min-w-0 flex-wrap items-center justify-end gap-2">
                                    {canEditStatus ? (
                                        <div className="w-44 shrink-0 sm:w-52">
                                            <SearchableSelect
                                                id={`technician-incident-status-${detail.id}`}
                                                value={detail.status_id ? String(detail.status_id) : ''}
                                                disabled={statusSaving}
                                                emptyLabel={t('common.select')}
                                                onChange={(value) => void handleStatusChange(value)}
                                                options={statusOptions.map((option) => ({
                                                    value: String(option.id),
                                                    label: option.label,
                                                    color: option.color,
                                                }))}
                                            />
                                        </div>
                                    ) : (
                                        <span className="text-sm font-medium text-ink">
                                            {detail.status_name || t('common.emDash')}
                                        </span>
                                    )}

                                    {canShowVerify ? (
                                        <Button type="button" variant="secondary" onClick={openVerifyModal}>
                                            <BadgeCheck className="size-4" aria-hidden />
                                            {t('technicianIncidents.resolve')}
                                        </Button>
                                    ) : null}
                                </div>
                            </div>

                            <dl className="space-y-3 border-t border-line pt-4">
                                <MetaRow
                                    label={t('technicianIncidents.requestedBy')}
                                    value={detail.requested_by_name || t('common.emDash')}
                                />
                                <MetaRow
                                    label={t('technicianIncidents.assignedTo')}
                                    value={detail.responded_by_name || t('common.emDash')}
                                />
                                <MetaRow
                                    label={t('technicianIncidents.dueAt')}
                                    value={formatDateTime(detail.due_at, i18n.language) || t('common.emDash')}
                                />
                                <MetaRow
                                    label={t('technicianIncidents.respondedAt')}
                                    value={formatDate(detail.responded_at, i18n.language) || t('common.emDash')}
                                />
                                {detail.is_verified ? (
                                    <>
                                        <MetaRow
                                            label={t('technicianIncidents.verifiedBy')}
                                            value={detail.verified_by_name || t('common.emDash')}
                                        />
                                        <MetaRow
                                            label={t('technicianIncidents.verifiedAt')}
                                            value={
                                                formatDateTime(detail.verified_at, i18n.language) ||
                                                t('common.emDash')
                                            }
                                        />
                                    </>
                                ) : null}
                                {detail.negotiation_succeeded !== null ? (
                                    <MetaRow
                                        label={t('technicianIncidents.negotiationSucceeded')}
                                        value={
                                            detail.negotiation_succeeded
                                                ? t('technicianIncidents.yes')
                                                : t('technicianIncidents.no')
                                        }
                                    />
                                ) : null}
                            </dl>

                            <div className="space-y-2 border-t border-line pt-4">
                                <p className="text-sm font-medium text-ink-muted">
                                    {t('technicianIncidents.incidentText')}
                                </p>
                                <p className="whitespace-pre-wrap text-sm leading-relaxed text-ink">
                                    {detail.incident_text || t('common.emDash')}
                                </p>
                            </div>

                            {detail.response_text ? (
                                <div className="space-y-2">
                                    <p className="text-sm font-medium text-ink-muted">
                                        {t('technicianIncidents.responseText')}
                                    </p>
                                    <p className="whitespace-pre-wrap text-sm leading-relaxed text-ink">
                                        {detail.response_text}
                                    </p>
                                </div>
                            ) : null}

                            {detail.unsuccessful_negotiation_solution ? (
                                <div className="space-y-2">
                                    <p className="text-sm font-medium text-ink-muted">
                                        {t('technicianIncidents.unsuccessfulNegotiationSolution')}
                                    </p>
                                    <p className="whitespace-pre-wrap text-sm leading-relaxed text-ink">
                                        {detail.unsuccessful_negotiation_solution}
                                    </p>
                                </div>
                            ) : null}
                        </section>

                        <section className="flex min-h-0 min-w-0 flex-col overflow-hidden bg-canvas">
                            <div className="flex items-center justify-between border-b border-line px-4 py-3">
                                <h4 className="text-base font-semibold text-ink">
                                    {t('technicianIncidents.messages')}
                                    {messages.length > 0 ? (
                                        <span className="ml-1.5 font-normal text-ink-muted">
                                            ({messages.length})
                                        </span>
                                    ) : null}
                                </h4>
                            </div>

                            <div ref={messagesRef} className="app-scroll flex-1 overflow-y-auto px-4 py-4">
                                {messages.length === 0 ? (
                                    <p className="py-8 text-center text-sm text-ink-muted">
                                        {t('technicianIncidents.noMessages')}
                                    </p>
                                ) : (
                                    <ul className="space-y-5">
                                        {messages.map((message) => (
                                            <li key={message.id} className="flex gap-3">
                                                <div
                                                    className="flex size-9 shrink-0 items-center justify-center rounded-full bg-brand-soft text-sm font-semibold text-brand"
                                                    aria-hidden
                                                >
                                                    {initials(message.user_name)}
                                                </div>
                                                <div className="min-w-0 flex-1">
                                                    <div className="flex flex-wrap items-baseline gap-x-2 gap-y-0.5">
                                                        <span className="text-sm font-semibold text-ink">
                                                            {message.user_name || t('common.emDash')}
                                                        </span>
                                                        {message.created_at ? (
                                                            <time className="text-sm text-ink-muted">
                                                                {formatDateTime(message.created_at, i18n.language)}
                                                            </time>
                                                        ) : null}
                                                    </div>
                                                    <div className="mt-1.5 text-sm leading-relaxed text-ink">
                                                        {message.type === 'text' || !message.type ? (
                                                            <RichTextHtml html={message.body} />
                                                        ) : (
                                                            renderAttachment(message)
                                                        )}
                                                    </div>
                                                </div>
                                            </li>
                                        ))}
                                    </ul>
                                )}
                            </div>

                            {canPostMessages ? (
                                <form
                                    onSubmit={submitMessage}
                                    className="space-y-2 border-t border-line bg-surface px-4 py-3"
                                >
                                    <RichTextEditor
                                        value={messageBody}
                                        onChange={setMessageBody}
                                        placeholder={t('technicianIncidents.newMessage')}
                                        minHeightClassName="min-h-20"
                                    />
                                    <div className="flex flex-wrap items-center justify-between gap-2">
                                        <div>
                                            <input
                                                ref={fileRef}
                                                type="file"
                                                className="hidden"
                                                onChange={(event) => void submitFile(event.target.files?.[0])}
                                            />
                                            <Button
                                                type="button"
                                                variant="secondary"
                                                onClick={() => fileRef.current?.click()}
                                                disabled={posting}
                                            >
                                                <Paperclip className="size-3.5" aria-hidden />
                                                {t('technicianIncidents.messagesAttach')}
                                            </Button>
                                        </div>
                                        <Button
                                            type="submit"
                                            loading={posting}
                                            disabled={isEmptyRichText(messageBody)}
                                            className="shrink-0"
                                        >
                                            <Send className="size-4" aria-hidden />
                                            {t('technicianIncidents.sendMessage')}
                                        </Button>
                                    </div>
                                </form>
                            ) : null}
                        </section>
                    </>
                ) : (
                    <div className="col-span-full flex items-center justify-center px-4 py-10 lg:col-span-2">
                        <p className="text-sm text-danger">{t('technicianIncidents.loadFailed')}</p>
                    </div>
                )}
            </div>

            <BaseModal
                open={verifyOpen}
                onClose={() => {
                    if (!verifying) {
                        setVerifyOpen(false);
                    }
                }}
                closeDisabled={verifying}
                title={t('technicianIncidents.resolveTitle')}
                description={t('technicianIncidents.resolveDescription')}
                size="lg"
                footer={
                    <>
                        <Button
                            type="button"
                            variant="secondary"
                            disabled={verifying}
                            onClick={() => setVerifyOpen(false)}
                        >
                            {t('common.cancel')}
                        </Button>
                        <Button type="button" loading={verifying} onClick={() => void submitVerify()}>
                            <BadgeCheck className="size-4" aria-hidden />
                            {t('technicianIncidents.verify')}
                        </Button>
                    </>
                }
            >
                <div className="space-y-4">
                    <Field label={t('technicianIncidents.responseText')} htmlFor="verify-response-text">
                        <textarea
                            id="verify-response-text"
                            value={responseText}
                            onChange={(event) => setResponseText(event.target.value)}
                            rows={4}
                            className="w-full rounded-lg border border-line bg-surface px-3 py-2 text-sm text-ink focus:border-brand focus:outline-none focus:ring-2 focus:ring-brand/20"
                        />
                    </Field>

                    {isNegotiation ? (
                        <fieldset className="space-y-3">
                            <legend className="text-sm font-medium text-ink">
                                {t('technicianIncidents.negotiationSucceeded')}
                            </legend>
                            <div className="flex flex-wrap gap-5 text-sm text-ink">
                                <label className="inline-flex items-center gap-2">
                                    <input
                                        type="radio"
                                        name="verify-negotiation"
                                        checked={negotiationSucceeded === true}
                                        onChange={() => setNegotiationSucceeded(true)}
                                    />
                                    {t('technicianIncidents.yes')}
                                </label>
                                <label className="inline-flex items-center gap-2">
                                    <input
                                        type="radio"
                                        name="verify-negotiation"
                                        checked={negotiationSucceeded === false}
                                        onChange={() => setNegotiationSucceeded(false)}
                                    />
                                    {t('technicianIncidents.no')}
                                </label>
                            </div>
                            {negotiationSucceeded === false ? (
                                <Field
                                    label={t('technicianIncidents.unsuccessfulNegotiationSolution')}
                                    htmlFor="verify-negotiation-solution"
                                >
                                    <textarea
                                        id="verify-negotiation-solution"
                                        value={negotiationSolution}
                                        onChange={(event) => setNegotiationSolution(event.target.value)}
                                        rows={3}
                                        className="w-full rounded-lg border border-line bg-surface px-3 py-2 text-sm text-ink focus:border-brand focus:outline-none focus:ring-2 focus:ring-brand/20"
                                    />
                                </Field>
                            ) : null}
                        </fieldset>
                    ) : null}

                    {verifyError ? <p className="text-sm text-danger">{verifyError}</p> : null}
                </div>
            </BaseModal>
        </div>
    );
}
