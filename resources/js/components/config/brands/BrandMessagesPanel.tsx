import { FormEvent, useEffect, useMemo, useRef, useState } from 'react';
import { FileText, Paperclip, Send } from 'lucide-react';
import { useTranslation } from 'react-i18next';
import { Button } from '@/components/ui/Button';
import { RichTextEditor, RichTextHtml } from '@/components/ui/RichTextEditor';
import { brandsService } from '@/services';
import { cn } from '@/support/cn';
import { isEmptyRichText, normalizeRichText } from '@/support/richText';
import type { BrandMessageItem } from '@/support/types/domain/brand';

export type BrandMessagesCapabilities = {
    send_messages: boolean;
    view_message_files: boolean;
    download_message_files: boolean;
    send_message_files: boolean;
};

type BrandMessagesPanelProps = {
    brandId: number;
    messages: BrandMessageItem[];
    can: BrandMessagesCapabilities;
};

export function BrandMessagesPanel({ brandId, messages, can }: BrandMessagesPanelProps) {
    const { t } = useTranslation();
    const [body, setBody] = useState('');
    const [sending, setSending] = useState(false);
    const listRef = useRef<HTMLDivElement>(null);
    const fileRef = useRef<HTMLInputElement>(null);

    const chronological = useMemo(() => [...messages].reverse(), [messages]);
    const canCompose = can.send_messages || can.send_message_files;

    function scrollToBottom(behavior: ScrollBehavior = 'auto') {
        const node = listRef.current;
        if (!node) {
            return;
        }

        node.scrollTo({ top: node.scrollHeight, behavior });
    }

    useEffect(() => {
        let cancelled = false;
        const frame = window.requestAnimationFrame(() => {
            window.requestAnimationFrame(() => {
                if (!cancelled) {
                    scrollToBottom('auto');
                }
            });
        });

        return () => {
            cancelled = true;
            window.cancelAnimationFrame(frame);
        };
    }, [chronological]);

    function dateLabel(message: BrandMessageItem): string {
        if (message.date_label === 'today') {
            return t('brands.messagesToday');
        }

        if (message.date_label === 'yesterday') {
            return t('brands.messagesYesterday');
        }

        return message.date_label;
    }

    function submitText(event: FormEvent) {
        event.preventDefault();
        const html = normalizeRichText(body);

        if (!can.send_messages || isEmptyRichText(html) || sending) {
            return;
        }

        setSending(true);
        brandsService.storeMessage(brandId, { body: html });
        setBody('');
        setSending(false);
    }

    function submitFile(file: File | null | undefined) {
        if (!can.send_message_files || !file || sending) {
            return;
        }

        setSending(true);
        brandsService.storeMessage(brandId, { file });
        setSending(false);

        if (fileRef.current) {
            fileRef.current.value = '';
        }
    }

    function renderAttachment(message: BrandMessageItem) {
        if (!can.view_message_files) {
            return <p className="text-xs text-ink-muted">{t('brands.messagesFileHidden')}</p>;
        }

        const label = message.download_name ?? t('brands.messagesDownload');

        if (message.type === 'image' && message.preview_url && can.download_message_files) {
            return (
                <a href={message.preview_url} target="_blank" rel="noreferrer" className="block">
                    <img
                        src={message.preview_url}
                        alt={label}
                        className="max-h-48 rounded-lg object-contain"
                        onLoad={() => scrollToBottom('auto')}
                    />
                </a>
            );
        }

        if (message.preview_url && can.download_message_files) {
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

    return (
        <section className="flex flex-col rounded-2xl border border-line bg-surface">
            <div className="border-b border-line px-4 py-3">
                <h2 className="text-sm font-semibold text-ink">{t('brands.messagesTitle')}</h2>
            </div>

            <div ref={listRef} className="flex max-h-96 min-h-56 flex-col gap-3 overflow-y-auto px-4 py-4">
                {chronological.length === 0 ? (
                    <p className="m-auto text-sm text-ink-muted">{t('brands.messagesEmpty')}</p>
                ) : (
                    chronological.map((message, index) => {
                        const previous = chronological[index - 1];
                        const showDate = !previous || previous.date !== message.date;
                        const showAuthor = !previous || previous.user_id !== message.user_id || showDate;

                        return (
                            <div key={message.id} className="space-y-2">
                                {showDate ? (
                                    <div className="flex justify-center">
                                        <span className="rounded-md bg-canvas px-2 py-0.5 text-xs text-ink-muted">
                                            {dateLabel(message)}
                                        </span>
                                    </div>
                                ) : null}

                                <div className={cn('flex', message.is_mine ? 'justify-end' : 'justify-start')}>
                                    <div
                                        className={cn(
                                            'max-w-[85%] rounded-2xl border px-3 py-2 text-sm shadow-sm',
                                            message.is_mine
                                                ? 'border-brand/20 bg-brand-soft text-ink'
                                                : 'border-line bg-canvas text-ink',
                                        )}
                                    >
                                        {showAuthor ? (
                                            <p className="mb-1 text-xs font-semibold text-ink-muted">{message.user_name}</p>
                                        ) : null}

                                        {message.type === 'text' ? (
                                            <RichTextHtml html={message.body} />
                                        ) : (
                                            renderAttachment(message)
                                        )}

                                        <p className="mt-1 text-right text-[10px] text-ink-muted">{message.time}</p>
                                    </div>
                                </div>
                            </div>
                        );
                    })
                )}
            </div>

            {canCompose ? (
                <form onSubmit={submitText} className="space-y-2 border-t border-line p-4">
                    {can.send_messages ? (
                        <RichTextEditor
                            value={body}
                            onChange={setBody}
                            placeholder={t('brands.messagesPlaceholder')}
                            minHeightClassName="min-h-20"
                        />
                    ) : null}

                    <div className="flex flex-wrap items-center justify-between gap-2">
                        <div>
                            {can.send_message_files ? (
                                <>
                                    <input
                                        ref={fileRef}
                                        type="file"
                                        className="hidden"
                                        onChange={(event) => submitFile(event.target.files?.[0])}
                                    />
                                    <Button
                                        type="button"
                                        variant="secondary"
                                        onClick={() => fileRef.current?.click()}
                                        disabled={sending}
                                    >
                                        <Paperclip className="size-3.5" aria-hidden />
                                        {t('brands.messagesAttach')}
                                    </Button>
                                </>
                            ) : null}
                        </div>

                        {can.send_messages ? (
                            <Button type="submit" disabled={sending || isEmptyRichText(body)}>
                                <Send className="size-3.5" aria-hidden />
                                {t('brands.messagesSend')}
                            </Button>
                        ) : null}
                    </div>
                </form>
            ) : null}
        </section>
    );
}
