import { useRef, useState } from 'react';
import { Download, FileText, Paperclip, Trash2, Upload } from 'lucide-react';
import { useTranslation } from 'react-i18next';
import { Button } from '@/components/ui/Button';
import { confirmAction } from '@/helpers/confirm';
import { contractsService } from '@/services';
import type { ContractAttachmentItem } from '@/support/types/domain/contract';

type ContractAttachmentsPanelProps = {
    contractId: number;
    attachments: ContractAttachmentItem[];
    can: {
        upload_attachments: boolean;
        download_attachments: boolean;
        delete_attachments: boolean;
    };
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

export function ContractAttachmentsPanel({
    contractId,
    attachments,
    can,
}: ContractAttachmentsPanelProps) {
    const { t, i18n } = useTranslation();
    const fileRef = useRef<HTMLInputElement>(null);
    const [uploading, setUploading] = useState(false);

    function upload(file: File | null | undefined) {
        if (!can.upload_attachments || !file || uploading) {
            return;
        }

        setUploading(true);
        contractsService.storeAttachment(contractId, file, {
            onFinish: () => setUploading(false),
        });

        if (fileRef.current) {
            fileRef.current.value = '';
        }
    }

    async function remove(attachment: ContractAttachmentItem) {
        if (!can.delete_attachments) {
            return;
        }

        const confirmed = await confirmAction({
            title: t('common.deleteTitle', { resource: t('contracts.attachment') }),
            message: t('common.deleteMessage', { name: attachment.name }),
            confirmLabel: t('common.delete'),
            tone: 'danger',
        });

        if (!confirmed) {
            return;
        }

        contractsService.destroyAttachment(contractId, attachment.id);
    }

    return (
        <div className="space-y-4 rounded-2xl border border-line bg-surface p-6 sm:p-8">
            <div className="flex flex-wrap items-start justify-between gap-3">
                <div>
                    <h2 className="text-base font-semibold text-ink">{t('contracts.attachmentsTitle')}</h2>
                    <p className="mt-1 text-sm text-ink-muted">{t('contracts.attachmentsDescription')}</p>
                </div>

                {can.upload_attachments ? (
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
                            {t('contracts.uploadAttachment')}
                        </Button>
                    </div>
                ) : null}
            </div>

            {attachments.length === 0 ? (
                <div className="flex flex-col items-center justify-center gap-2 rounded-xl border border-dashed border-line px-4 py-10 text-center">
                    <Paperclip className="size-5 text-ink-muted" aria-hidden />
                    <p className="text-sm text-ink-muted">{t('contracts.attachmentsEmpty')}</p>
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
                                {can.download_attachments ? (
                                    <a
                                        href={attachment.download_url}
                                        className="inline-flex h-8 items-center justify-center gap-1.5 rounded-lg border border-line bg-surface px-3 text-sm font-semibold text-ink transition-colors hover:bg-canvas"
                                    >
                                        <Download className="size-3.5" aria-hidden />
                                        {t('common.download')}
                                    </a>
                                ) : null}
                                {can.delete_attachments ? (
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
