import { useRef, useState } from 'react';
import { Download, Eye, FileText, Lock, Paperclip, Trash2, Upload } from 'lucide-react';
import { useTranslation } from 'react-i18next';
import { AttachmentImagePreviewModal } from '@/components/attachments/AttachmentImagePreviewModal';
import { Button } from '@/components/ui/Button';
import { confirmAction } from '@/helpers/confirm';
import type { WorkOrderAttachmentItem } from '@/support/types/domain/work-order';

type WorkOrderAttachmentsPanelProps = {
    workOrderId: number;
    attachments: WorkOrderAttachmentItem[];
    can: {
        upload_attachments: boolean;
        download_attachments: boolean;
        delete_attachments: boolean;
        view_private_attachments: boolean;
    };
    labelsNamespace?: 'workOrders' | 'estimates';
    onUpload: (id: number, file: File, isPrivate?: boolean, options?: Record<string, unknown>) => void;
    onDestroy: (documentId: number, attachmentId: number, options?: Record<string, unknown>) => void;
};

function formatBytes(bytes: number | null, locale: string): string {
    if (bytes === null || bytes === undefined) {
        return '—';
    }

    if (bytes < 1024) {
        return `${bytes} B`;
    }

    const units = ['KB', 'MB', 'GB'];
    let value = bytes / 1024;
    let unitIndex = 0;

    while (value >= 1024 && unitIndex < units.length - 1) {
        value /= 1024;
        unitIndex += 1;
    }

    return `${value.toLocaleString(locale, { maximumFractionDigits: 1 })} ${units[unitIndex]}`;
}

function AttachmentSection({
    title,
    description,
    emptyLabel,
    attachments,
    canUpload,
    canDownload,
    canDelete,
    isPrivate,
    workOrderId,
    labelsNamespace,
    onUpload,
    onDestroy,
    onPreview,
}: {
    title: string;
    description: string;
    emptyLabel: string;
    attachments: WorkOrderAttachmentItem[];
    canUpload: boolean;
    canDownload: boolean;
    canDelete: boolean;
    isPrivate: boolean;
    workOrderId: number;
    labelsNamespace: 'workOrders' | 'estimates';
    onUpload: WorkOrderAttachmentsPanelProps['onUpload'];
    onDestroy: WorkOrderAttachmentsPanelProps['onDestroy'];
    onPreview: (attachment: WorkOrderAttachmentItem) => void;
}) {
    const { t, i18n } = useTranslation();
    const fileRef = useRef<HTMLInputElement>(null);
    const [uploading, setUploading] = useState(false);

    function upload(file: File | null | undefined) {
        if (!canUpload || !file || uploading) {
            return;
        }

        setUploading(true);
        onUpload(workOrderId, file, isPrivate, {
            onFinish: () => setUploading(false),
        });

        if (fileRef.current) {
            fileRef.current.value = '';
        }
    }

    async function remove(attachment: WorkOrderAttachmentItem) {
        if (!canDelete) {
            return;
        }

        const confirmed = await confirmAction({
            title: t('common.deleteTitle', { resource: t(`${labelsNamespace}.attachment`) }),
            message: t('common.deleteMessage', { name: attachment.name }),
            confirmLabel: t('common.delete'),
            tone: 'danger',
        });

        if (!confirmed) {
            return;
        }

        onDestroy(workOrderId, attachment.id);
    }

    return (
        <div className="space-y-4 rounded-2xl border border-line bg-surface p-6 sm:p-8">
            <div className="flex flex-wrap items-start justify-between gap-3">
                <div>
                    <h2 className="flex items-center gap-2 text-base font-semibold text-ink">
                        {isPrivate ? <Lock className="size-4 text-ink-muted" aria-hidden /> : null}
                        {title}
                    </h2>
                    <p className="mt-1 text-sm text-ink-muted">{description}</p>
                </div>

                {canUpload ? (
                    <div>
                        <input
                            ref={fileRef}
                            type="file"
                            className="hidden"
                            onChange={(event) => upload(event.target.files?.[0])}
                        />
                        <Button
                            type="button"
                            variant="secondary"
                            loading={uploading}
                            onClick={() => fileRef.current?.click()}
                        >
                            <Upload className="size-4" aria-hidden />
                            {t(`${labelsNamespace}.uploadAttachment`)}
                        </Button>
                    </div>
                ) : null}
            </div>

            {attachments.length === 0 ? (
                <div className="flex flex-col items-center justify-center gap-2 rounded-xl border border-dashed border-line px-4 py-10 text-center">
                    <Paperclip className="size-5 text-ink-muted" aria-hidden />
                    <p className="text-sm text-ink-muted">{emptyLabel}</p>
                </div>
            ) : (
                <ul className="divide-y divide-line rounded-xl border border-line">
                    {attachments.map((attachment) => (
                        <li
                            key={attachment.id}
                            className="flex flex-wrap items-center justify-between gap-3 px-4 py-3"
                        >
                            <div className="flex min-w-0 items-start gap-3">
                                <FileText className="mt-0.5 size-4 shrink-0 text-ink-muted" aria-hidden />
                                <div className="min-w-0">
                                    <p className="truncate text-sm font-medium text-ink">{attachment.name}</p>
                                    <p className="mt-1 text-xs text-ink-muted">
                                        {formatBytes(attachment.size_bytes, i18n.language)}
                                        {attachment.uploaded_by_name
                                            ? ` · ${attachment.uploaded_by_name}`
                                            : null}
                                        {attachment.created_at
                                            ? ` · ${new Date(attachment.created_at).toLocaleString(i18n.language)}`
                                            : null}
                                    </p>
                                </div>
                            </div>

                            <div className="flex shrink-0 items-center gap-2">
                                {attachment.is_image && canDownload ? (
                                    <Button
                                        type="button"
                                        variant="secondary"
                                        onClick={() => onPreview(attachment)}
                                    >
                                        <Eye className="size-3.5" aria-hidden />
                                        {t('common.view')}
                                    </Button>
                                ) : null}
                                {canDownload ? (
                                    <a
                                        href={attachment.download_url}
                                        className="inline-flex h-8 items-center justify-center gap-1.5 rounded-lg border border-line bg-surface px-3 text-sm font-semibold text-ink transition-colors hover:bg-canvas"
                                    >
                                        <Download className="size-3.5" aria-hidden />
                                        {t('common.download')}
                                    </a>
                                ) : null}
                                {canDelete ? (
                                    <Button
                                        type="button"
                                        variant="danger"
                                        onClick={() => remove(attachment)}
                                    >
                                        <Trash2 className="size-3.5" aria-hidden />
                                        {t('common.delete')}
                                    </Button>
                                ) : null}
                            </div>
                        </li>
                    ))}
                </ul>
            )}
        </div>
    );
}

export function WorkOrderAttachmentsPanel({
    workOrderId,
    attachments,
    can,
    labelsNamespace = 'workOrders',
    onUpload,
    onDestroy,
}: WorkOrderAttachmentsPanelProps) {
    const { t } = useTranslation();
    const [preview, setPreview] = useState<WorkOrderAttachmentItem | null>(null);

    const publicAttachments = attachments.filter((item) => !item.is_private);
    const privateAttachments = attachments.filter((item) => item.is_private);

    return (
        <>
            <div className="space-y-5">
                <AttachmentSection
                    title={t(`${labelsNamespace}.attachmentsPublicTitle`)}
                    description={t(`${labelsNamespace}.attachmentsPublicDescription`)}
                    emptyLabel={t(`${labelsNamespace}.attachmentsPublicEmpty`)}
                    attachments={publicAttachments}
                    canUpload={can.upload_attachments}
                    canDownload={can.download_attachments}
                    canDelete={can.delete_attachments}
                    isPrivate={false}
                    workOrderId={workOrderId}
                    labelsNamespace={labelsNamespace}
                    onUpload={onUpload}
                    onDestroy={onDestroy}
                    onPreview={setPreview}
                />

                {can.view_private_attachments ? (
                    <AttachmentSection
                        title={t(`${labelsNamespace}.attachmentsPrivateTitle`)}
                        description={t(`${labelsNamespace}.attachmentsPrivateDescription`)}
                        emptyLabel={t(`${labelsNamespace}.attachmentsPrivateEmpty`)}
                        attachments={privateAttachments}
                        canUpload={can.upload_attachments}
                        canDownload={can.download_attachments}
                        canDelete={can.delete_attachments}
                        isPrivate
                        workOrderId={workOrderId}
                        labelsNamespace={labelsNamespace}
                        onUpload={onUpload}
                        onDestroy={onDestroy}
                        onPreview={setPreview}
                    />
                ) : null}
            </div>

            <AttachmentImagePreviewModal
                open={preview !== null}
                title={preview?.name ?? t(`${labelsNamespace}.attachment`)}
                src={preview?.view_url ?? null}
                alt={preview?.name}
                onClose={() => setPreview(null)}
            />
        </>
    );
}
