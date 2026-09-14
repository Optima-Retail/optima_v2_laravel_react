import { FormEvent, useEffect, useMemo, useRef, useState, type ReactNode } from 'react';
import {
    Bell,
    BellOff,
    ChevronLeft,
    ChevronRight,
    Eye,
    Lock,
    LockOpen,
    MessageSquare,
    Paperclip,
    Send,
} from 'lucide-react';
import { useTranslation } from 'react-i18next';
import { AttachmentImagePreviewModal } from '@/components/attachments/AttachmentImagePreviewModal';
import { Button } from '@/components/ui/Button';
import { RichTextEditor, RichTextHtml } from '@/components/ui/RichTextEditor';
import { documentChatsService } from '@/services/documentChats';
import { cn } from '@/support/cn';
import { formatDate, formatTime } from '@/support/datetime';
import { isEmptyRichText, normalizeRichText, sanitizeRichText } from '@/support/richText';
import type {
    DocumentChatAttachment,
    DocumentChatMessage,
    DocumentChatPayload,
    DocumentChatType,
} from '@/support/types/domain/chat';

const COLLAPSED_STORAGE_KEY = 'document-chat-collapsed';

type ChatTab = 'chat' | 'history';

type DocumentChatPanelProps = {
    documentType: DocumentChatType;
    documentId: number;
    initialChat: DocumentChatPayload;
    canPost: boolean;
};

type MessageGroup = {
    dateKey: string;
    dateLabel: string;
    messages: DocumentChatMessage[];
};

function readCollapsedPreference(): boolean {
    if (typeof window === 'undefined') {
        return false;
    }

    try {
        return window.localStorage.getItem(COLLAPSED_STORAGE_KEY) === '1';
    } catch {
        return false;
    }
}

function dateKeyFromIso(value: string | null, locale: string): string {
    if (!value) {
        return 'unknown';
    }

    const date = new Date(value);

    if (Number.isNaN(date.getTime())) {
        return value;
    }

    return formatDate(value, locale) || value.slice(0, 10);
}

function groupMessagesByDate(messages: DocumentChatMessage[], locale: string): MessageGroup[] {
    const groups: MessageGroup[] = [];

    for (const message of messages) {
        const key = dateKeyFromIso(message.created_at, locale);
        const last = groups[groups.length - 1];

        if (last && last.dateKey === key) {
            last.messages.push(message);
            continue;
        }

        groups.push({
            dateKey: key,
            dateLabel: key === 'unknown' ? '—' : key,
            messages: [message],
        });
    }

    return groups;
}

function isHistoryMessage(message: DocumentChatMessage): boolean {
    return message.type === 'system';
}

export function DocumentChatPanel({
    documentType,
    documentId,
    initialChat,
    canPost,
}: DocumentChatPanelProps) {
    const { t, i18n } = useTranslation();
    const fileRef = useRef<HTMLInputElement>(null);
    const listRef = useRef<HTMLDivElement>(null);
    const [collapsed, setCollapsed] = useState(readCollapsedPreference);
    const [activeTab, setActiveTab] = useState<ChatTab>('chat');
    const [messages, setMessages] = useState<DocumentChatMessage[]>(initialChat.messages);
    const [isMuted, setIsMuted] = useState(initialChat.is_muted);
    const [body, setBody] = useState('');
    const [isPrivate, setIsPrivate] = useState(initialChat.can_see_private);
    const [sending, setSending] = useState(false);
    const [uploading, setUploading] = useState(false);
    const [error, setError] = useState<string | null>(null);
    const [preview, setPreview] = useState<DocumentChatAttachment | null>(null);

    useEffect(() => {
        setMessages(initialChat.messages);
        setIsMuted(initialChat.is_muted);
        setIsPrivate(initialChat.can_see_private);
    }, [initialChat]);

    useEffect(() => {
        void documentChatsService.markRead(documentType, documentId).catch(() => {
            // Silent: unread badge is best-effort.
        });
    }, [documentId, documentType]);

    const chatMessages = useMemo(
        () => messages.filter((message) => !isHistoryMessage(message)),
        [messages],
    );
    const historyMessages = useMemo(
        () => messages.filter((message) => isHistoryMessage(message)),
        [messages],
    );
    const visibleMessages = activeTab === 'history' ? historyMessages : chatMessages;
    const groupedMessages = useMemo(
        () => groupMessagesByDate(visibleMessages, i18n.language),
        [i18n.language, visibleMessages],
    );

    useEffect(() => {
        const el = listRef.current;
        if (!el || collapsed) {
            return;
        }

        el.scrollTop = el.scrollHeight;
    }, [activeTab, collapsed, visibleMessages.length]);

    useEffect(() => {
        try {
            window.localStorage.setItem(COLLAPSED_STORAGE_KEY, collapsed ? '1' : '0');
        } catch {
            // Ignore storage failures.
        }
    }, [collapsed]);

    async function appendMessage(message: DocumentChatMessage) {
        setMessages((current) => [...current, message]);
        if (!isHistoryMessage(message)) {
            setActiveTab('chat');
        }
    }

    async function submit(event: FormEvent) {
        event.preventDefault();

        const html = sanitizeRichText(normalizeRichText(body));
        if (!canPost || isEmptyRichText(html) || sending || activeTab === 'history') {
            return;
        }

        setSending(true);
        setError(null);

        try {
            const message = await documentChatsService.postMessage(documentType, documentId, {
                body: html,
                is_private: isPrivate && initialChat.can_see_private,
            });
            await appendMessage(message);
            setBody('');
        } catch {
            setError(t('chat.sendError'));
        } finally {
            setSending(false);
        }
    }

    async function upload(file: File | null | undefined) {
        if (!canPost || !file || uploading || activeTab === 'history') {
            return;
        }

        setUploading(true);
        setError(null);

        try {
            const message = await documentChatsService.postAttachment(
                documentType,
                documentId,
                file,
                isPrivate && initialChat.can_see_private,
            );
            await appendMessage(message);
            setActiveTab('chat');
        } catch {
            setError(t('chat.attachmentError'));
        } finally {
            setUploading(false);
            if (fileRef.current) {
                fileRef.current.value = '';
            }
        }
    }

    async function toggleNotify() {
        setError(null);

        try {
            if (isMuted) {
                await documentChatsService.unmute(documentType, documentId);
                setIsMuted(false);
            } else {
                await documentChatsService.mute(documentType, documentId);
                setIsMuted(true);
            }
        } catch {
            setError(t('chat.muteError'));
        }
    }

    function renderMessageBody(message: DocumentChatMessage): ReactNode {
        if (message.type === 'file' || message.attachments.length > 0) {
            return (
                <ul className="space-y-1">
                    {message.attachments.map((attachment) => (
                        <li key={attachment.id} className="flex flex-wrap items-center gap-2">
                            <a
                                href={attachment.download_url}
                                className="inline-flex items-center gap-1.5 text-sm font-medium text-brand hover:underline"
                            >
                                <Paperclip className="size-3.5" aria-hidden />
                                {attachment.name}
                            </a>
                            {attachment.is_image ? (
                                <button
                                    type="button"
                                    className="inline-flex items-center gap-1 text-xs font-semibold text-ink-muted hover:text-ink"
                                    onClick={() => setPreview(attachment)}
                                >
                                    <Eye className="size-3.5" aria-hidden />
                                    {t('common.view')}
                                </button>
                            ) : null}
                        </li>
                    ))}
                </ul>
            );
        }

        if (message.type === 'system') {
            if (message.body && /<[a-z][\s\S]*>/i.test(message.body)) {
                return <RichTextHtml html={message.body} className="text-center text-xs text-ink-muted" />;
            }

            return <p className="text-center text-xs text-ink-muted">{message.body}</p>;
        }

        if (message.body && /<[a-z][\s\S]*>/i.test(message.body)) {
            return <RichTextHtml html={message.body} />;
        }

        return <p className="whitespace-pre-wrap text-sm text-ink">{message.body}</p>;
    }

    if (collapsed) {
        return (
            <aside className="flex h-full w-12 shrink-0 flex-col items-center border-l border-line bg-surface">
                <button
                    type="button"
                    className="flex w-full flex-1 flex-col items-center gap-3 py-4 text-ink-muted transition-colors hover:bg-canvas hover:text-ink"
                    onClick={() => setCollapsed(false)}
                    aria-expanded={false}
                    aria-label={t('chat.expand')}
                    title={t('chat.expand')}
                >
                    <ChevronLeft className="size-4" aria-hidden />
                    <MessageSquare className="size-4" aria-hidden />
                    <span className="mt-1 rotate-180 text-[10px] font-semibold uppercase tracking-[0.14em] [writing-mode:vertical-rl]">
                        {t('chat.title')}
                    </span>
                </button>
            </aside>
        );
    }

    return (
        <aside className="flex h-full w-[22rem] shrink-0 flex-col border-l border-line bg-surface xl:w-[26rem]">
            <div className="flex items-center gap-2 border-b border-line px-3 py-2.5">
                <div className="min-w-0 flex-1">
                    <h2 className="truncate text-sm font-semibold text-ink">{t('chat.title')}</h2>
                    <p className="truncate text-xs text-ink-muted">{t('chat.description')}</p>
                </div>

                <button
                    type="button"
                    className="inline-flex size-8 items-center justify-center rounded-lg border border-line text-ink-muted transition-colors hover:bg-canvas hover:text-ink"
                    onClick={() => setCollapsed(true)}
                    aria-expanded={true}
                    aria-label={t('chat.collapse')}
                    title={t('chat.collapse')}
                >
                    <ChevronRight className="size-4" aria-hidden />
                </button>
            </div>

            <div className="flex border-b border-line px-2 pt-2">
                <button
                    type="button"
                    className={cn(
                        'flex-1 rounded-t-lg px-3 py-2 text-sm font-semibold transition-colors',
                        activeTab === 'chat'
                            ? 'bg-canvas text-ink'
                            : 'text-ink-muted hover:text-ink',
                    )}
                    onClick={() => setActiveTab('chat')}
                >
                    {t('chat.tabs.chat')}
                </button>
                <button
                    type="button"
                    className={cn(
                        'flex-1 rounded-t-lg px-3 py-2 text-sm font-semibold transition-colors',
                        activeTab === 'history'
                            ? 'bg-canvas text-ink'
                            : 'text-ink-muted hover:text-ink',
                    )}
                    onClick={() => setActiveTab('history')}
                >
                    {t('chat.tabs.history')}
                </button>
            </div>

            <div ref={listRef} className="app-scroll min-h-0 flex-1 space-y-4 overflow-y-auto bg-canvas/40 px-3 py-3">
                {groupedMessages.length === 0 ? (
                    <p className="py-8 text-center text-sm text-ink-muted">
                        {activeTab === 'history' ? t('chat.historyEmpty') : t('chat.empty')}
                    </p>
                ) : (
                    groupedMessages.map((group) => (
                        <div key={group.dateKey} className="space-y-3">
                            <div className="flex items-center gap-2">
                                <div className="h-px flex-1 bg-line" />
                                <span className="text-[11px] font-semibold uppercase tracking-wide text-ink-muted">
                                    {group.dateLabel}
                                </span>
                                <div className="h-px flex-1 bg-line" />
                            </div>

                            {group.messages.map((message) => {
                                if (message.type === 'system') {
                                    return (
                                        <div
                                            key={message.id}
                                            className="rounded-lg bg-brand-soft/40 px-3 py-2"
                                        >
                                            {renderMessageBody(message)}
                                            <p className="mt-1 text-center text-[10px] text-ink-muted">
                                                {formatTime(message.created_at, i18n.language)}
                                            </p>
                                        </div>
                                    );
                                }

                                return (
                                    <div
                                        key={message.id}
                                        className={cn(
                                            'rounded-xl border border-line bg-surface px-3 py-2',
                                            message.is_private && 'border-brand/30 bg-brand-soft/20',
                                        )}
                                    >
                                        <div className="mb-1 flex flex-wrap items-center gap-2 text-xs text-ink-muted">
                                            <span className="font-medium text-ink">
                                                {message.user_name || t('chat.unknownUser')}
                                            </span>
                                            {message.is_private ? (
                                                <span className="inline-flex items-center gap-1 rounded-full bg-brand/10 px-2 py-0.5 text-[10px] font-semibold uppercase tracking-wide text-brand">
                                                    <Lock className="size-2.5" aria-hidden />
                                                    {t('chat.private')}
                                                </span>
                                            ) : null}
                                            <span className="ml-auto tabular-nums">
                                                {formatTime(message.created_at, i18n.language)}
                                            </span>
                                        </div>
                                        {renderMessageBody(message)}
                                    </div>
                                );
                            })}
                        </div>
                    ))
                )}
            </div>

            {activeTab === 'chat' ? (
                <div className="border-t border-line px-3 py-3">
                    {error ? <p className="mb-2 text-sm text-danger">{error}</p> : null}

                    {canPost ? (
                        <form onSubmit={(event) => void submit(event)} className="space-y-2">
                            <div className="flex flex-wrap items-center gap-3 text-xs">
                                <button
                                    type="button"
                                    className={cn(
                                        'inline-flex items-center gap-1.5 font-semibold transition-colors',
                                        isMuted ? 'text-danger' : 'text-success',
                                    )}
                                    onClick={() => void toggleNotify()}
                                >
                                    {isMuted ? (
                                        <>
                                            <BellOff className="size-3.5" aria-hidden />
                                            {t('chat.notifyOff')}
                                        </>
                                    ) : (
                                        <>
                                            <Bell className="size-3.5" aria-hidden />
                                            {t('chat.notifyOn')}
                                        </>
                                    )}
                                </button>

                                {initialChat.can_see_private ? (
                                    <button
                                        type="button"
                                        className="inline-flex items-center gap-1.5 font-semibold text-ink-muted transition-colors hover:text-ink"
                                        onClick={() => setIsPrivate((current) => !current)}
                                    >
                                        {isPrivate ? (
                                            <>
                                                <Lock className="size-3.5" aria-hidden />
                                                {t('chat.private')}
                                            </>
                                        ) : (
                                            <>
                                                <LockOpen className="size-3.5" aria-hidden />
                                                {t('chat.public')}
                                            </>
                                        )}
                                    </button>
                                ) : null}

                                <button
                                    type="button"
                                    className="inline-flex items-center gap-1.5 font-semibold text-ink-muted transition-colors hover:text-ink"
                                    disabled={uploading}
                                    onClick={() => fileRef.current?.click()}
                                >
                                    <Paperclip className="size-3.5" aria-hidden />
                                    {uploading ? t('common.loading') : t('chat.attachment')}
                                </button>

                                <input
                                    ref={fileRef}
                                    type="file"
                                    className="hidden"
                                    onChange={(event) => void upload(event.target.files?.[0])}
                                />
                            </div>

                            <RichTextEditor
                                value={body}
                                onChange={setBody}
                                placeholder={t('chat.placeholder')}
                                disabled={sending}
                                minHeightClassName="min-h-24"
                                className="text-sm"
                            />

                            <div className="flex justify-end">
                                <Button
                                    type="submit"
                                    disabled={isEmptyRichText(body)}
                                    loading={sending}
                                >
                                    <Send className="size-4" aria-hidden />
                                    {t('chat.send')}
                                </Button>
                            </div>
                        </form>
                    ) : (
                        <p className="text-sm text-ink-muted">{t('chat.readOnly')}</p>
                    )}
                </div>
            ) : null}

            <AttachmentImagePreviewModal
                open={preview !== null}
                title={preview?.name ?? t('chat.attachment')}
                src={preview?.view_url ?? null}
                alt={preview?.name}
                onClose={() => setPreview(null)}
            />
        </aside>
    );
}
