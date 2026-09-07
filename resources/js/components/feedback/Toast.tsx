import { AlertCircle, CheckCircle2, Info, X } from 'lucide-react';
import { useEffect } from 'react';
import { useTranslation } from 'react-i18next';
import { cn } from '@/support/cn';
import { useToastStore, type ToastItem, type ToastTone } from '@/stores/toastStore';

const toneStyles: Record<ToastTone, string> = {
    success: 'border-success bg-success text-white',
    error: 'border-danger bg-danger text-white',
    info: 'border-brand bg-brand text-white',
};

const toneIcons = {
    success: CheckCircle2,
    error: AlertCircle,
    info: Info,
};

function ToastCard({ toast }: { toast: ToastItem }) {
    const { t } = useTranslation();
    const dismiss = useToastStore((state) => state.dismiss);
    const Icon = toneIcons[toast.tone];

    useEffect(() => {
        const timer = window.setTimeout(() => dismiss(toast.id), 4200);
        return () => window.clearTimeout(timer);
    }, [dismiss, toast.id]);

    return (
        <div
            className={cn(
                'pointer-events-auto flex w-full max-w-sm items-start gap-2.5 rounded-xl border px-3 py-2.5 text-sm font-medium shadow-sm',
                toneStyles[toast.tone],
            )}
            role="status"
        >
            <Icon className="mt-0.5 size-4 shrink-0 text-white" aria-hidden />
            <p className="flex-1 text-white">{toast.message}</p>
            <button
                type="button"
                onClick={() => dismiss(toast.id)}
                className="rounded-lg p-1 text-white/80 transition-colors hover:bg-white/15 hover:text-white"
                aria-label={t('common.close')}
            >
                <X className="size-3.5" aria-hidden />
            </button>
        </div>
    );
}

export function Toast() {
    const toasts = useToastStore((state) => state.toasts);

    if (toasts.length === 0) {
        return null;
    }

    return (
        <div className="pointer-events-none fixed right-3 bottom-3 z-50 flex w-[min(100%-1.5rem,24rem)] flex-col-reverse gap-2">
            {toasts.map((toast) => (
                <ToastCard key={toast.id} toast={toast} />
            ))}
        </div>
    );
}
