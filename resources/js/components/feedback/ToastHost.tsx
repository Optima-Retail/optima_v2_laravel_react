import { router, usePage } from '@inertiajs/react';
import { useEffect, useRef } from 'react';
import { useTranslation } from 'react-i18next';
import { Toast } from '@/components/feedback/Toast';
import { useToastStore } from '@/stores/toastStore';
import type { FlashMessages, SharedPageProps } from '@/types';

function flashMessage(flash: FlashMessages | null | undefined): { text: string; tone: 'success' | 'error' } | null {
    if (flash?.error) {
        return { text: flash.error, tone: 'error' };
    }

    if (flash?.success) {
        return { text: flash.success, tone: 'success' };
    }

    return null;
}

export function ToastHost() {
    const { flash } = usePage<SharedPageProps>().props;
    const { t } = useTranslation();
    const push = useToastStore((state) => state.push);
    const initialFlash = useRef(flash);
    const handledInitial = useRef(false);

    useEffect(() => {
        function showFlash(next: FlashMessages | null | undefined) {
            const message = flashMessage(next);

            if (!message) {
                return;
            }

            push(t(`messages.${message.text}`, { defaultValue: message.text }), message.tone);
        }

        // Full document load with flashed session (e.g. hard refresh after redirect).
        if (!handledInitial.current) {
            handledInitial.current = true;
            showFlash(initialFlash.current);
        }

        // Every successful Inertia visit (store/update redirects back to edit, etc.).
        return router.on('success', (event) => {
            const pageFlash = (event.detail.page.props as SharedPageProps).flash;
            showFlash(pageFlash);
        });
    }, [push, t]);

    return <Toast />;
}
